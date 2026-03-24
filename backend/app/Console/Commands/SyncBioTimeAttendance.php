<?php
namespace App\Console\Commands;

use App\Models\AttendanceDevice;
use App\Services\Attendance\BioTimeService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncBioTimeAttendance extends Command
{
    protected $signature   = 'biotime:sync {--days=1 : Number of past days to sync} {--device= : Sync specific device ID only}';
    protected $description = 'Sync attendance punches from ZKTeco BioTime devices';

    public function handle(BioTimeService $biotime): int
    {
        $days   = (int) $this->option('days');
        $devId  = $this->option('device');
        $since  = now()->subDays($days)->startOfDay();

        $query  = AttendanceDevice::where('is_active', true)->where('brand', 'zkteco');
        if ($devId) $query->where('id', $devId);

        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->warn('No active ZKTeco devices found.');
            return 0;
        }

        $this->info("Syncing {$devices->count()} device(s) — last {$days} day(s) from " . $since->toDateString());

        foreach ($devices as $device) {
            $this->line("  → {$device->name} ({$device->ip_address})");
            $result = $biotime->fullSync($device, $since);

            if (!empty($result['errors'])) {
                $this->error("    ✗ " . implode('; ', $result['errors']));
                continue;
            }

            $this->info("    ✓ Fetched: {$result['fetched']}  New: {$result['new_raw']}  Created: {$result['created']}  Updated: {$result['updated']}  Unmatched: {$result['unmatched']}");
        }

        $this->info('Done.');
        return 0;
    }
}
