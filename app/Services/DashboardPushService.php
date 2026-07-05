<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Pushes locally-synced attendance records to the live dashboard API.
 *
 * Records are pushed in batches; each attendance row is stamped with
 * pushed_at once the dashboard has accepted it, so nothing is ever sent
 * twice and anything unsent is retried on the next run.
 */
class DashboardPushService
{
    public function enabled(): bool
    {
        return (bool) Setting::get('push.enabled', false);
    }

    public function configured(): bool
    {
        return Setting::get('push.url') !== null;
    }

    public function pendingCount(): int
    {
        return Attendance::whereNull('pushed_at')->count();
    }

    /**
     * Push every unpushed attendance record, oldest first, in batches.
     * Throws on the first failed batch so the queue can retry with backoff;
     * batches already accepted stay marked as pushed.
     *
     * @return int Number of records pushed.
     */
    public function pushPending(): int
    {
        if (! $this->enabled() || ! $this->configured()) {
            return 0;
        }

        $batchSize = max(1, (int) Setting::get('push.batch_size', 200));
        $pushed = 0;

        while (true) {
            $records = Attendance::with('device')
                ->leftJoin('device_users', function ($join) {
                    $join->on('device_users.device_id', '=', 'attendances.device_id')
                        ->on('device_users.userid', '=', 'attendances.userid');
                })
                ->select('attendances.*', 'device_users.name as user_name')
                ->whereNull('attendances.pushed_at')
                ->orderBy('attendances.punched_at')
                ->orderBy('attendances.id')
                ->limit($batchSize)
                ->get();

            if ($records->isEmpty()) {
                break;
            }

            try {
                $this->sendBatch($records);
            } catch (Throwable $e) {
                $this->recordFailure($e->getMessage());
                throw $e;
            }

            Attendance::whereIn('id', $records->pluck('id'))->update(['pushed_at' => now()]);
            $pushed += $records->count();
        }

        $this->recordSuccess();

        return $pushed;
    }

    /**
     * Send a lightweight ping to the configured endpoint to verify
     * the URL and token without pushing any records.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'message' => 'Set the dashboard URL first.'];
        }

        try {
            $response = $this->request()->post(Setting::get('push.url'), [
                'ping' => true,
                'records' => [],
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return $response->successful()
            ? ['ok' => true, 'message' => "Dashboard responded with HTTP {$response->status()}."]
            : ['ok' => false, 'message' => "Dashboard responded with HTTP {$response->status()}: ".mb_substr($response->body(), 0, 300)];
    }

    /**
     * @param  Collection<int, Attendance>  $records
     */
    protected function sendBatch(Collection $records): void
    {
        $payload = [
            'source' => Setting::get('push.source', config('app.name')),
            'records' => $records->map(fn (Attendance $a) => [
                'local_id' => $a->id,
                'device_serial' => $a->device?->serial_number,
                'device_name' => $a->device?->name,
                'uid' => $a->uid,
                'userid' => $a->userid,
                'user_name' => $a->user_name,
                'state' => (int) $a->state,
                'state_name' => $a->state_name,
                'type' => (int) $a->type,
                'type_name' => $a->type_name,
                'punched_at' => $a->punched_at->toIso8601String(),
            ])->all(),
        ];

        $response = $this->request()->post(Setting::get('push.url'), $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Dashboard rejected batch with HTTP {$response->status()}: ".mb_substr($response->body(), 0, 300)
            );
        }
    }

    protected function request(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout((int) Setting::get('push.timeout', 30))
            ->connectTimeout(10);

        if ($token = Setting::get('push.token')) {
            $request = $request->withToken($token);
        }

        return $request;
    }

    protected function recordSuccess(): void
    {
        Setting::setMany([
            'push.last_success_at' => now()->toDateTimeString(),
            'push.last_error' => null,
            'push.last_error_at' => null,
        ]);
    }

    protected function recordFailure(string $message): void
    {
        Setting::setMany([
            'push.last_error' => mb_substr($message, 0, 1000),
            'push.last_error_at' => now()->toDateTimeString(),
        ]);
    }
}
