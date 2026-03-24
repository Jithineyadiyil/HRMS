<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\DeviceAttendanceLog;
use App\Services\Attendance\BioTimeService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BioTimeController extends Controller
{
    protected BioTimeService $biotime;

    public function __construct(BioTimeService $biotime)
    {
        $this->biotime = $biotime;
    }

    // ── CRUD: Devices ─────────────────────────────────────────────────────

    public function index()
    {
        $devices = AttendanceDevice::orderBy('name')->get();
        return response()->json($devices);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'protocol'   => 'required|in:http,https',
            'ip_address' => 'required|string|max:255',
            'port'       => 'required|integer|min:1|max:65535',
            'username'   => 'required|string|max:100',
            'password'   => 'required|string',
        ]);

        $device = AttendanceDevice::create([
            'name'                  => $request->name,
            'brand'                 => 'zkteco',
            'protocol'              => $request->protocol,
            'ip_address'            => $request->ip_address,
            'port'                  => $request->port,
            'username'              => $request->username,
            'password'              => $request->password,
            'timeout_seconds'       => $request->timeout_seconds ?? 30,
            'employee_number_field' => 'employee_code',
            'is_active'             => true,
        ]);

        return response()->json(['device' => $device], 201);
    }

    public function update(Request $request, $id)
    {
        $device = AttendanceDevice::findOrFail($id);
        $data   = $request->except(['api_key','last_synced_at','last_sync_status']); // don't let client overwrite these
        if (empty($data['password'])) unset($data['password']); // blank = keep existing
        $device->update($data);
        return response()->json(['device' => $device]);
    }

    public function destroy($id)
    {
        AttendanceDevice::findOrFail($id)->delete();
        return response()->json(['message' => 'Device deleted.']);
    }

    // ── Test connection ───────────────────────────────────────────────────

    public function testConnection($id)
    {
        $device = AttendanceDevice::findOrFail($id);
        $result = $this->biotime->connect($device);
        return response()->json($result);
    }

    // ── Sync actions ──────────────────────────────────────────────────────

    /**
     * Manual sync: pull from BioTime for the specified date range.
     * POST /api/v1/biotime/devices/{id}/sync
     * Body: { from: "2026-01-01", to: "2026-03-15" }  (optional, defaults to last 30 days)
     */
    public function sync(Request $request, $id)
    {
        $device = AttendanceDevice::findOrFail($id);

        if (!$device->is_active) {
            return response()->json(['message' => 'Device is inactive.'], 422);
        }

        $from  = $request->from ? Carbon::parse($request->from)->startOfDay() : now()->subDays(30)->startOfDay();
        $to    = $request->to   ? Carbon::parse($request->to)->endOfDay()     : now();

        $result = $this->biotime->fullSync($device, $from, $to, auth()->id());

        return response()->json($result);
    }

    /**
     * Sync ALL active devices (called by cron job).
     * POST /api/v1/biotime/sync-all
     */
    public function syncAll(Request $request)
    {
        $devices = AttendanceDevice::where('is_active', true)->where('brand', 'zkteco')->get();

        if ($devices->isEmpty()) {
            return response()->json(['message' => 'No active ZKTeco devices configured.']);
        }

        $since   = now()->subDays((int)($request->days ?? 1))->startOfDay();
        $results = [];

        foreach ($devices as $device) {
            $results[] = $this->biotime->fullSync($device, $since);
        }

        return response()->json(['synced_devices' => count($results), 'results' => $results]);
    }

    // ── Employee mapping ──────────────────────────────────────────────────

    /**
     * Preview employee mapping between BioTime and HRMS.
     * GET /api/v1/biotime/devices/{id}/employees
     */
    public function employees($id)
    {
        $device = AttendanceDevice::findOrFail($id);
        $result = $this->biotime->fetchEmployees($device);
        return response()->json($result);
    }

    // ── Unmatched punches ─────────────────────────────────────────────────

    /**
     * Punches from device that couldn't be matched to HRMS employees.
     * GET /api/v1/biotime/devices/{id}/unmatched
     */
    public function unmatched($id)
    {
        $records = DeviceAttendanceLog::where('device_id', $id)
            ->whereNull('employee_id')
            ->orderBy('punch_time', 'desc')
            ->limit(200)
            ->get(['id','device_employee_number','punch_time','punch_type','verification_mode','processed']);

        $uniq = $records->groupBy('device_employee_number')->map(fn($g) => [
            'device_code'  => $g->first()->device_employee_number,
            'punch_count'  => $g->count(),
            'last_punch'   => $g->sortByDesc('punch_time')->first()->punch_time,
        ])->values();

        return response()->json(['unmatched' => $uniq]);
    }

    // ── Sync log ──────────────────────────────────────────────────────────

    /**
     * Recent raw device punch log (last 500 records).
     * GET /api/v1/biotime/devices/{id}/logs?date=2026-03-15
     */
    public function logs(Request $request, $id)
    {
        $query = DeviceAttendanceLog::with('employee:id,first_name,last_name,employee_code')
            ->where('device_id', $id)
            ->when($request->date, fn($q) => $q->whereDate('punch_time', $request->date))
            ->orderBy('punch_time', 'desc')
            ->limit(500)
            ->get();

        return response()->json(['logs' => $query]);
    }

    // ── Stats ─────────────────────────────────────────────────────────────

    public function stats($id)
    {
        $device    = AttendanceDevice::findOrFail($id);
        $total     = DeviceAttendanceLog::where('device_id', $id)->count();
        $today     = DeviceAttendanceLog::where('device_id', $id)->whereDate('punch_time', today())->count();
        $unmatched = DeviceAttendanceLog::where('device_id', $id)->whereNull('employee_id')->count();
        $processed = DeviceAttendanceLog::where('device_id', $id)->where('processed', true)->count();

        return response()->json([
            'device'            => $device,
            'total_punches'     => $total,
            'today_punches'     => $today,
            'unmatched_punches' => $unmatched,
            'processed_punches' => $processed,
            'pending_punches'   => $total - $processed,
        ]);
    }
}
