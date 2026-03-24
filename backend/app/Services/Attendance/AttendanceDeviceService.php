<?php
namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Models\DeviceAttendanceLog;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class AttendanceDeviceService
{
    // ── Public API ──────────────────────────────────────────────────────

    /**
     * Test connection to a device — returns ['ok'=>bool, 'message'=>string]
     */
    public function testConnection(AttendanceDevice $device): array
    {
        try {
            if ($device->protocol === 'tcp') {
                return $this->testTcp($device);
            }
            return $this->testHttp($device);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }

    /**
     * Fetch punch records from the device and store in device_attendance_logs.
     * Returns summary: ['fetched'=>int, 'new'=>int, 'matched'=>int, 'unmatched'=>int]
     */
    public function fetchFromDevice(AttendanceDevice $device, ?Carbon $since = null): array
    {
        $since = $since ?? Carbon::now()->subDays(30);

        $punches = match ($device->brand) {
            'zkteco'   => $this->fetchZKTeco($device, $since),
            'hikvision'=> $this->fetchHikvision($device, $since),
            default    => $this->fetchRestApi($device, $since),
        };

        $fetched   = count($punches);
        $new       = 0;
        $matched   = 0;
        $unmatched = 0;

        // Build employee lookup map: employee_code/number → employee_id
        $empMap = $this->buildEmployeeMap($device->employee_number_field);

        foreach ($punches as $punch) {
            $empNum    = (string)($punch['employee_number'] ?? '');
            $punchTime = Carbon::parse($punch['punch_time']);
            $punchType = (int)($punch['punch_type'] ?? 0);
            $mode      = $punch['verification_mode'] ?? null;

            // Skip if already stored
            $exists = DeviceAttendanceLog::where('device_id', $device->id)
                ->where('device_employee_number', $empNum)
                ->where('punch_time', $punchTime)
                ->exists();

            if ($exists) continue;

            $empId = $empMap[$empNum] ?? null;
            if ($empId) $matched++; else $unmatched++;

            DeviceAttendanceLog::create([
                'device_id'               => $device->id,
                'device_employee_number'  => $empNum,
                'employee_id'             => $empId,
                'punch_time'              => $punchTime,
                'punch_type'              => $punchType,
                'verification_mode'       => $mode,
                'processed'               => false,
            ]);
            $new++;
        }

        $device->update([
            'last_synced_at'    => now(),
            'last_sync_status'  => 'success',
            'last_sync_count'   => $new,
            'last_sync_error'   => null,
        ]);

        return compact('fetched','new','matched','unmatched');
    }

    /**
     * Process unprocessed device logs → create/update AttendanceLogs.
     * Merges first punch = check-in, last punch = check-out for each employee/day.
     */
    public function processDeviceLogs(AttendanceDevice $device): array
    {
        $logs = DeviceAttendanceLog::where('device_id', $device->id)
            ->where('processed', false)
            ->whereNotNull('employee_id')
            ->orderBy('punch_time')
            ->get();

        $processed = 0;
        $created   = 0;
        $updated   = 0;

        // Group by employee + date
        $grouped = $logs->groupBy(fn($l) => $l->employee_id . '|' . $l->punch_time->toDateString());

        foreach ($grouped as $key => $dayPunches) {
            [$empId, $date] = explode('|', $key);

            // Determine check-in (first punch) and check-out (last punch)
            $sorted   = $dayPunches->sortBy('punch_time');
            $first    = $sorted->first();
            $last     = $sorted->last();

            $checkIn  = $first->punch_time->format('H:i:s');
            $checkOut = ($last->id !== $first->id) ? $last->punch_time->format('H:i:s') : null;

            // Determine status
            $checkInHour = (int)$first->punch_time->format('H') * 60 + (int)$first->punch_time->format('i');
            $workStart   = 8 * 60; // 08:00
            $lateThresh  = 8 * 60 + 15; // 08:15 grace period
            $status = $checkInHour > $lateThresh ? 'late' : 'present';

            // Duration in minutes
            $duration = $checkOut ? $first->punch_time->diffInMinutes($last->punch_time) : null;
            $durationLabel = $duration ? floor($duration/60) . 'h ' . ($duration%60) . 'm' : null;

            // Upsert attendance log
            $existing = AttendanceLog::where('employee_id', $empId)->where('date', $date)->first();

            if ($existing) {
                // Only update from device if source is 'device' or no check-out yet
                if ($existing->source === 'device' || !$existing->check_out) {
                    $existing->update([
                        'check_in'       => $checkIn,
                        'check_out'      => $checkOut ?? $existing->check_out,
                        'status'         => $status,
                        'source'         => 'device',
                        'duration_label' => $durationLabel ?? $existing->duration_label,
                        'notes'          => 'Synced from ' . $device->name,
                    ]);
                    $updated++;
                }
            } else {
                AttendanceLog::create([
                    'employee_id'    => $empId,
                    'date'           => $date,
                    'check_in'       => $checkIn,
                    'check_out'      => $checkOut,
                    'status'         => $status,
                    'source'         => 'device',
                    'duration_label' => $durationLabel,
                    'notes'          => 'Synced from ' . $device->name,
                ]);
                $created++;
            }

            // Mark as processed
            DeviceAttendanceLog::whereIn('id', $dayPunches->pluck('id'))->update(['processed' => true]);
            $processed += $dayPunches->count();
        }

        return compact('processed','created','updated');
    }

    // ── Device-specific fetch methods ────────────────────────────────────

    /**
     * ZKTeco via HTTP (ADMS protocol) — most ZKTeco devices support this
     * endpoint: GET /iclock/getrequest or /AttLog.cgi
     */
    private function fetchZKTeco(AttendanceDevice $device, Carbon $since): array
    {
        $baseUrl = "http://{$device->ip_address}:{$device->port}";

        // Try ZKTeco ADMS HTTP protocol first
        try {
            $resp = Http::timeout($device->timeout_seconds)
                ->withBasicAuth($device->username ?? 'admin', $device->password ?? '')
                ->get("{$baseUrl}/iclock/getrequest", [
                    'SN'   => '',
                    'INFO' => 'ATTLOG',
                    'From' => $since->format('Y-m-d H:i:s'),
                    'To'   => now()->format('Y-m-d H:i:s'),
                ]);

            if ($resp->successful()) {
                return $this->parseZKTecoAttlog($resp->body());
            }
        } catch (\Throwable $e) {
            Log::warning("ZKTeco ADMS fetch failed: " . $e->getMessage());
        }

        // Fallback: ZKTeco REST API (newer models — ZK-Bio series)
        try {
            $resp = Http::timeout($device->timeout_seconds)
                ->withHeaders(['Authorization' => 'Token ' . ($device->api_key ?? '')])
                ->get("{$baseUrl}/att/api/transactionHistory", [
                    'start_time' => $since->toIso8601String(),
                    'page_size'  => 10000,
                ]);

            if ($resp->successful()) {
                return $this->parseZKTecoRestApi($resp->json());
            }
        } catch (\Throwable $e) {
            Log::warning("ZKTeco REST fetch failed: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Parse ZKTeco ATTLOG text format:
     * PIN\tDate\tTime\tStatus\tVerify\tWorkCode
     * e.g.: 1001\t2024-01-15\t08:05:32\t0\t1\t0
     */
    private function parseZKTecoAttlog(string $body): array
    {
        $punches = [];
        $lines   = explode("\n", trim($body));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = preg_split('/\t/', $line);
            if (count($parts) < 3) continue;

            $pin    = trim($parts[0]); // employee PIN
            $date   = trim($parts[1]);
            $time   = trim($parts[2]);
            $status = isset($parts[3]) ? (int)trim($parts[3]) : 0;
            $verify = isset($parts[4]) ? (int)trim($parts[4]) : 0;

            $verifyMode = match($verify) {
                0 => 'finger', 1 => 'finger', 2 => 'face',
                3 => 'card',   15 => 'pin',   default => 'unknown'
            };

            $punches[] = [
                'employee_number'   => $pin,
                'punch_time'        => $date . ' ' . $time,
                'punch_type'        => $status, // 0=check-in, 1=check-out in ZKTeco
                'verification_mode' => $verifyMode,
            ];
        }

        return $punches;
    }

    /**
     * Parse ZKTeco REST API JSON response
     */
    private function parseZKTecoRestApi(array $data): array
    {
        $punches = [];
        $records = $data['data']['records'] ?? $data['records'] ?? $data['data'] ?? [];

        foreach ($records as $r) {
            $punches[] = [
                'employee_number'   => (string)($r['emp_code'] ?? $r['userId'] ?? $r['pin'] ?? ''),
                'punch_time'        => $r['punch_time'] ?? $r['time'] ?? '',
                'punch_type'        => (int)($r['punch_state'] ?? $r['status'] ?? 0),
                'verification_mode' => $r['verify_type'] ?? null,
            ];
        }

        return array_filter($punches, fn($p) => !empty($p['employee_number']) && !empty($p['punch_time']));
    }

    /**
     * Hikvision ISAPI attendance records
     */
    private function fetchHikvision(AttendanceDevice $device, Carbon $since): array
    {
        $baseUrl = "{$device->protocol}://{$device->ip_address}:{$device->port}";

        try {
            $resp = Http::timeout($device->timeout_seconds)
                ->withBasicAuth($device->username ?? 'admin', $device->password ?? '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$baseUrl}/ISAPI/AccessControl/AcsEvent?format=json", [
                    'AcsEventCond' => [
                        'searchID'       => uniqid(),
                        'searchResultPosition' => 0,
                        'maxResults'     => 10000,
                        'startTime'      => $since->format('Y-m-d\TH:i:s+03:00'),
                        'endTime'        => now()->format('Y-m-d\TH:i:s+03:00'),
                        'major'          => 5, // 5 = access control event
                    ],
                ]);

            if ($resp->successful()) {
                $events = $resp->json('AcsEvent.InfoList') ?? [];
                $punches = [];
                foreach ($events as $e) {
                    $punches[] = [
                        'employee_number'   => (string)($e['employeeNoString'] ?? $e['cardNo'] ?? ''),
                        'punch_time'        => $e['time'] ?? '',
                        'punch_type'        => 0, // Hikvision doesn't always distinguish in/out
                        'verification_mode' => strtolower($e['type'] ?? 'card'),
                    ];
                }
                return array_filter($punches, fn($p) => !empty($p['employee_number']));
            }
        } catch (\Throwable $e) {
            Log::warning("Hikvision fetch failed: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Generic REST API — configurable endpoint
     * Expects JSON with an array of punch records.
     * Maps using standard or configurable field names.
     */
    private function fetchRestApi(AttendanceDevice $device, Carbon $since): array
    {
        $url = "{$device->protocol}://{$device->ip_address}:{$device->port}" . ($device->api_path ?? '/api/attendance');

        try {
            $request = Http::timeout($device->timeout_seconds);

            if ($device->api_key) {
                $request = $request->withHeaders(['Authorization' => 'Bearer ' . $device->api_key]);
            } elseif ($device->username) {
                $request = $request->withBasicAuth($device->username, $device->password ?? '');
            }

            $resp = $request->get($url, [
                'from' => $since->toIso8601String(),
                'to'   => now()->toIso8601String(),
            ]);

            if ($resp->successful()) {
                $records = $resp->json('data') ?? $resp->json('records') ?? $resp->json() ?? [];
                if (!is_array($records)) return [];

                return array_map(fn($r) => [
                    'employee_number'   => (string)($r['employee_number'] ?? $r['emp_code'] ?? $r['id'] ?? ''),
                    'punch_time'        => $r['punch_time'] ?? $r['time'] ?? $r['timestamp'] ?? '',
                    'punch_type'        => (int)($r['punch_type'] ?? $r['type'] ?? 0),
                    'verification_mode' => $r['mode'] ?? $r['verify'] ?? null,
                ], array_filter($records, fn($r) => is_array($r)));
            }
        } catch (\Throwable $e) {
            Log::warning("REST device fetch failed for device {$device->id}: " . $e->getMessage());
        }

        return [];
    }

    // ── Connection test helpers ─────────────────────────────────────────

    private function testTcp(AttendanceDevice $device): array
    {
        $socket = @fsockopen($device->ip_address, $device->port, $errno, $errstr, 5);
        if ($socket) {
            fclose($socket);
            return ['ok' => true, 'message' => "Connected to {$device->ip_address}:{$device->port} — device is reachable."];
        }
        return ['ok' => false, 'message' => "Cannot connect to {$device->ip_address}:{$device->port} — {$errstr} (errno {$errno})"];
    }

    private function testHttp(AttendanceDevice $device): array
    {
        $url = "{$device->protocol}://{$device->ip_address}:{$device->port}";
        try {
            $resp = Http::timeout(5)->get($url);
            return ['ok' => true, 'message' => "HTTP {$resp->status()} — device responded at {$url}"];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => "HTTP request failed: " . $e->getMessage()];
        }
    }

    // ── Employee number map ─────────────────────────────────────────────

    private function buildEmployeeMap(string $field): array
    {
        $col = in_array($field, ['employee_code','id','national_id','iqama_number'])
            ? $field : 'employee_code';

        return Employee::whereIn('status', ['active','probation','on_leave'])
            ->get([$col, 'id'])
            ->mapWithKeys(fn($e) => [(string)$e->{$col} => $e->id])
            ->toArray();
    }
}
