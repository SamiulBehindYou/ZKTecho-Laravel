<?php

namespace App\Jobs;

use App\Services\DashboardPushService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Pushes all pending attendance records to the live dashboard.
 *
 * Retries with increasing backoff while the network or dashboard is down.
 * Even if every retry is exhausted, records stay marked as unpushed and the
 * attendance:push scheduler dispatches a fresh job on its next tick, so no
 * record is ever lost — local attendance capture is never blocked by this.
 */
class PushAttendanceToDashboard implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 5;

    /** Only one copy of this job may be queued or running at a time. */
    public int $uniqueFor = 3600;

    /**
     * @return array<int, int> Seconds to wait between retries.
     */
    public function backoff(): array
    {
        return [60, 180, 600, 1800];
    }

    public function handle(DashboardPushService $pusher): void
    {
        if (! $pusher->enabled() || ! $pusher->configured()) {
            return;
        }

        $pushed = $pusher->pushPending();

        if ($pushed > 0) {
            Log::info("Pushed {$pushed} attendance record(s) to the dashboard.");
        }
    }
}
