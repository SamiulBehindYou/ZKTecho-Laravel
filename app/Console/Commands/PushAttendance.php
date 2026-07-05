<?php

namespace App\Console\Commands;

use App\Jobs\PushAttendanceToDashboard;
use App\Services\DashboardPushService;
use Illuminate\Console\Command;
use Throwable;

class PushAttendance extends Command
{
    protected $signature = 'attendance:push
                            {--now : Push in this process instead of queueing a job}';

    protected $description = 'Push pending attendance records to the live dashboard';

    public function handle(DashboardPushService $pusher): int
    {
        if (! $pusher->enabled() || ! $pusher->configured()) {
            $this->line('Dashboard push is disabled or not configured — nothing to do.');

            return self::SUCCESS;
        }

        $pending = $pusher->pendingCount();

        if ($pending === 0) {
            $this->line('No pending attendance records.');

            return self::SUCCESS;
        }

        if ($this->option('now')) {
            try {
                $pushed = $pusher->pushPending();
            } catch (Throwable $e) {
                $this->error("Push failed: {$e->getMessage()}");

                return self::FAILURE;
            }

            $this->info("Pushed {$pushed} attendance record(s).");

            return self::SUCCESS;
        }

        PushAttendanceToDashboard::dispatch();
        $this->info("Queued push for {$pending} pending record(s).");

        return self::SUCCESS;
    }
}
