<?php
namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Models\DeviceAttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ZKTeco BioTime 8.x REST API integration.
 *
 * BioTime base URL: http(s)://<host>:<port>
 * Auth endpoint:    POST /jwt-api-token-auth/
 * Transactions:     GET  /att/api/transactionHistory/
 * Employees:        GET  /personnel/api/employees/
 *
 * Token is cached for 6 hours (BioTime default expiry).
 */
class BioTimeService
{
    private ?string $token    = null;
    private ?string $baseUrl  = null;
    private int     $timeout  = 30;

    // ── Auth ──────────────────────────────────────────────────────────────

    public function connect(AttendanceDevice $device): array
    {
        $this->baseUrl = rtrim("{$device->protocol}://{$device->ip_address}:{$device->port}", '/');
        $this->timeout = $device->timeout_seconds ?? 30;

        // If an api_key (JWT token) is stored and not expired, reuse it
        if ($device->api_key && $this->isTokenFresh($device)) {
            $this->token = $device->api_key;
            return ['ok' => true, 'message' => 'Using cached token.'];
        }

        return $this->authenticate($device);
    }

    private function authenticate(AttendanceDevice $device): array
    {
        try {
            $resp = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/jwt-api-token-auth/", [
                    'username' => $device->username,
                    'password' => $device->password,
                ]);

            if ($resp->successful() && $resp->json('token')) {
                $this->token = $resp->json('token');
                // Persist token so next sync skips re-auth
                $device->update(['api_key' => $this->token, 'last_sync_status' => 'connected']);
                return ['ok' => true, 'message' => 'Authenticated with BioTime successfully.'];
            }

            $err = $resp->json('non_field_errors') ?? $resp->json('detail') ?? $resp->body();
            return ['ok' => false, 'message' => "Auth failed ({$resp->status()}): {$err}"];

        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Connection error: ' . $e->getMessage()];
        }
    }

    private function isTokenFresh(AttendanceDevice $device): bool
    {
        // Treat as fresh if last sync < 5 hours ago
        return $device->last_synced_at && $device->last_synced_at->gt(now()->subHours(5));
    }

    // ── Fetch punch transactions ──────────────────────────────────────────

    /**
     * Pull attendance transactions from BioTime.
     *
     * @return array ['ok'=>bool, 'punches'=>array, 'message'=>string]
     */
    public function fetchTransactions(AttendanceDevice $device, Carbon $since, ?Carbon $until = null): array
    {
        $until = $until ?? now();
        $auth  = $this->connect($device);
        if (!$auth['ok']) return ['ok' => false, 'punches' => [], 'message' => $auth['message']];

        try {
            $page  = 1;
            $all   = [];

            do {
                $resp = Http::timeout($this->timeout)
                    ->withToken($this->token)
                    ->get("{$this->baseUrl}/att/api/transactionHistory/", [
                        'start_time' => $since->format('Y-m-d\TH:i:s'),
                        'end_time'   => $until->format('Y-m-d\TH:i:s'),
                        'page_size'  => 500,
                        'page'       => $page,
                    ]);

                if (!$resp->successful()) {
                    $err = $resp->json('detail') ?? $resp->body();
                    return ['ok' => false, 'punches' => $all, 'message' => "API error ({$resp->status()}): {$err}"];
                }

                $data    = $resp->json();
                $records = $data['data'] ?? $data['results'] ?? $data ?? [];
                $count   = $data['count'] ?? count($records);

                foreach ($records as $r) {
                    $all[] = $this->mapTransaction($r);
                }

                // Paginate if more pages
                $fetched = $page * 500;
                $page++;

            } while ($fetched < $count);

            return ['ok' => true, 'punches' => array_filter($all, fn($p) => !empty($p['employee_number'])), 'message' => "Fetched " . count($all) . " transactions."];

        } catch (\Throwable $e) {
            return ['ok' => false, 'punches' => [], 'message' => 'Fetch error: ' . $e->getMessage()];
        }
    }

    // ── Fetch employees from BioTime ──────────────────────────────────────

    /**
     * Pull employee list from BioTime for mapping review in the UI.
     *
     * @return array ['ok'=>bool, 'employees'=>array, 'message'=>string]
     */
    public function fetchEmployees(AttendanceDevice $device): array
    {
        $auth = $this->connect($device);
        if (!$auth['ok']) return ['ok' => false, 'employees' => [], 'message' => $auth['message']];

        try {
            $resp = Http::timeout($this->timeout)
                ->withToken($this->token)
                ->get("{$this->baseUrl}/personnel/api/employees/", [
                    'page_size' => 1000,
                ]);

            if (!$resp->successful()) {
                return ['ok' => false, 'employees' => [], 'message' => "API error ({$resp->status()})"];
            }

            $data      = $resp->json();
            $records   = $data['data'] ?? $data['results'] ?? $data ?? [];
            $hrmsMap   = $this->buildEmployeeMap();

            $employees = array_map(fn($e) => [
                'biotime_id'      => (string)($e['emp_code'] ?? $e['id'] ?? ''),
                'biotime_name'    => $e['first_name'] . ' ' . $e['last_name'],
                'department'      => $e['department']['dept_name'] ?? '—',
                'hrms_employee'   => $hrmsMap[(string)($e['emp_code'] ?? '')] ?? null,
                'matched'         => isset($hrmsMap[(string)($e['emp_code'] ?? '')]),
            ], $records);

            return ['ok' => true, 'employees' => $employees, 'message' => count($employees) . ' employees fetched.'];

        } catch (\Throwable $e) {
            return ['ok' => false, 'employees' => [], 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    // ── Full sync ─────────────────────────────────────────────────────────

    /**
     * Full sync: fetch → store raw → process into attendance_logs.
     * Used by both manual sync UI and scheduled cron job.
     */
    public function fullSync(AttendanceDevice $device, Carbon $since, ?Carbon $until = null, ?int $userId = null): array
    {
        $result = [
            'device'    => $device->name,
            'period'    => $since->toDateString() . ' → ' . ($until ?? now())->toDateString(),
            'fetched'   => 0,
            'new_raw'   => 0,
            'processed' => 0,
            'created'   => 0,
            'updated'   => 0,
            'unmatched' => 0,
            'errors'    => [],
        ];

        // 1 ─ Fetch from BioTime
        $fetch = $this->fetchTransactions($device, $since, $until);
        if (!$fetch['ok']) {
            $result['errors'][] = $fetch['message'];
            $device->update(['last_sync_status' => 'failed', 'last_sync_error' => $fetch['message'], 'last_synced_at' => now()]);
            return $result;
        }

        $punches        = $fetch['punches'];
        $result['fetched'] = count($punches);

        // 2 ─ Store raw punches
        $empMap     = $this->buildEmployeeMap();
        $newRaw     = 0;
        $unmatched  = 0;

        foreach ($punches as $punch) {
            $empNum    = (string)($punch['employee_number'] ?? '');
            $punchTime = Carbon::parse($punch['punch_time']);
            $empId     = $empMap[$empNum] ?? null;

            if (!$empId) { $unmatched++; continue; }

            $exists = DeviceAttendanceLog::where('device_id', $device->id)
                ->where('device_employee_number', $empNum)
                ->where('punch_time', $punchTime)
                ->exists();

            if ($exists) continue;

            DeviceAttendanceLog::create([
                'device_id'              => $device->id,
                'device_employee_number' => $empNum,
                'employee_id'            => $empId,
                'punch_time'             => $punchTime,
                'punch_type'             => (int)($punch['punch_type'] ?? 0),
                'verification_mode'      => $punch['verification_mode'] ?? null,
                'processed'              => false,
            ]);
            $newRaw++;
        }

        $result['new_raw']   = $newRaw;
        $result['unmatched'] = $unmatched;

        // 3 ─ Process raw → attendance_logs
        $devService = app(AttendanceDeviceService::class);
        $processed  = $devService->processDeviceLogs($device);

        $result['processed'] = $processed['processed'] ?? 0;
        $result['created']   = $processed['created']   ?? 0;
        $result['updated']   = $processed['updated']   ?? 0;

        // 4 ─ Update device sync status
        $device->update([
            'last_synced_at'    => now(),
            'last_sync_status'  => 'success',
            'last_sync_count'   => $result['created'] + $result['updated'],
            'last_sync_error'   => null,
        ]);

        Log::info("BioTime sync complete: {$device->name}", $result);
        return $result;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function mapTransaction(array $r): array
    {
        return [
            'employee_number'   => (string)($r['emp_code'] ?? $r['employee'] ?? $r['emp_id'] ?? ''),
            'punch_time'        => $r['punch_time'] ?? $r['att_date'] ?? '',
            // BioTime punch_state: 0=check-in, 1=check-out, 2=break-out, 3=break-in, 4=OT-in, 5=OT-out
            'punch_type'        => (int)($r['punch_state'] ?? $r['status'] ?? 0),
            'verification_mode' => $r['verify_type'] ?? $r['verify'] ?? null,
        ];
    }

    private function buildEmployeeMap(): array
    {
        return Employee::whereIn('status', ['active','probation','on_leave'])
            ->get(['employee_code','id'])
            ->mapWithKeys(fn($e) => [(string)$e->employee_code => $e->id])
            ->toArray();
    }
}
