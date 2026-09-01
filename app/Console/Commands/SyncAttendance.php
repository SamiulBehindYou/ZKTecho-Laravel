<?php

namespace App\Console\Commands;

use App\Jobs\PushAttendanceToDashboard;
use App\Models\Device;
use App\Services\DashboardPushService;
use App\Services\ZktecoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls attendance logs from every active device and hands anything new
 * to the dashboard push job. This is the unattended counterpart to the
 * Sync button on the attendance page.
 */
class SyncAttendance extends Command
{
    protected $signature = 'attendance:sync
                            {--device= : Only sync the device with this id}
                            {--no-push : Skip dispatching the dashboard push job}';

    protected $description = 'Pull new attendance logs from the devices and queue a dashboard push';

    public function handle(ZktecoService $zkteco, DashboardPushService $pusher): int
    {
        $devices = $this->option('device')
            ? Device::whereKey((int) $this->option('device'))->get()
            : Device::where('is_active', true)->get();

        if ($devices->isEmpty()) {
            $this->line('No active devices to sync.');

            return self::SUCCESS;
        }

        $failures = 0;
        $totalNew = 0;

        foreach ($devices as $device) {
            try {
                // Keep names in sync so attendance rows can be matched to users.
                try {
                    $zkteco->syncUsers($device);
                } catch (Throwable) {
                    // A failed user sync should not block pulling attendance logs.
                }

                $result = $zkteco->syncAttendance($device);
                $totalNew += $result['new'];

                $this->info("{$device->name}: {$result['new']} new of {$result['fetched']} log(s)");

                if ($result['new'] > 0) {
                    Log::info("Synced {$result['new']} new attendance log(s) from {$device->name}.");
                }
            } catch (Throwable $e) {
                $failures++;
                $this->error("{$device->name}: {$e->getMessage()}");
                Log::warning("Attendance sync failed for {$device->name}: {$e->getMessage()}");
            }
        }

        if (! $this->option('no-push')
            && $failures < $devices->count()
            && $pusher->enabled()
            && $pusher->configured()
            && $pusher->pendingCount() > 0) {
            PushAttendanceToDashboard::dispatch();
            $this->line('Queued dashboard push.');
        }

        // Every device failing is a real failure; a partial sync is not.
        return $failures === $devices->count() ? self::FAILURE : self::SUCCESS;
    }
}
