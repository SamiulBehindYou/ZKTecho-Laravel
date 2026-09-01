<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Device;
use Illuminate\Support\Facades\DB;
use Jmrashed\Zkteco\Lib\ZKTeco;
use RuntimeException;
use Throwable;

class ZktecoService
{
    /**
     * Test connectivity and read basic device info.
     *
     * @return array{ok: bool, message: string, info?: array<string, mixed>}
     */
    public function testConnection(Device $device): array
    {
        try {
            $zk = $this->connect($device);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        try {
            $info = [
                'version' => $zk->version(),
                'serial_number' => $zk->serialNumber(),
                'device_name' => $zk->deviceName(),
                'device_time' => $zk->getTime(),
            ];

            $device->update([
                'serial_number' => $this->cleanDeviceString($info['serial_number']),
                'device_name' => $this->cleanDeviceString($info['device_name']),
            ]);

            return ['ok' => true, 'message' => 'Connected successfully.', 'info' => $info];
        } finally {
            $zk->disconnect();
        }
    }

    /**
     * Pull the user list from the device into the device_users table.
     *
     * @return int Number of users on the device.
     */
    public function syncUsers(Device $device): int
    {
        $zk = $this->connect($device);

        try {
            $zk->disableDevice();
            $users = $zk->getUser();
        } finally {
            $zk->enableDevice();
            $zk->disconnect();
        }

        DB::transaction(function () use ($device, $users) {
            foreach ($users as $user) {
                $device->users()->updateOrCreate(
                    ['uid' => (int) $user['uid']],
                    [
                        'userid' => trim((string) $user['userid']),
                        'name' => trim((string) $user['name']) ?: null,
                        'role' => (int) $user['role'],
                        'cardno' => trim((string) $user['cardno']) ?: null,
                    ]
                );
            }

            // Drop rows for users no longer on the device (e.g. stale uids
            // left behind by re-enrollment), so each userid maps to one user.
            if ($users !== []) {
                $device->users()
                    ->whereNotIn('uid', array_map(fn ($u) => (int) $u['uid'], $users))
                    ->delete();
            }
        });

        return count($users);
    }

    /**
     * Pull attendance logs from the device into the attendances table.
     * Existing records are skipped, so re-syncing is safe.
     *
     * @return array{fetched: int, new: int}
     */
    public function syncAttendance(Device $device): array
    {
        $zk = $this->connect($device);

        try {
            $zk->disableDevice();
            $logs = $zk->getAttendance();
        } finally {
            $zk->enableDevice();
            $zk->disconnect();
        }

        $before = $device->attendances()->count();

        foreach (array_chunk($logs, 500) as $chunk) {
            $rows = [];
            foreach ($chunk as $log) {
                $rows[] = [
                    'device_id' => $device->id,
                    'uid' => (int) $log['uid'],
                    'userid' => trim((string) $log['id']),
                    'state' => (int) $log['state'],
                    'type' => (int) $log['type'],
                    'punched_at' => $log['timestamp'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            Attendance::insertOrIgnore($rows);
        }

        $device->update(['last_synced_at' => now()]);

        return [
            'fetched' => count($logs),
            'new' => $device->attendances()->count() - $before,
        ];
    }

    /**
     * Erase all attendance logs stored on the physical device.
     */
    public function clearDeviceAttendance(Device $device): void
    {
        $zk = $this->connect($device);

        try {
            $zk->clearAttendance();
        } finally {
            $zk->disconnect();
        }
    }

    protected function connect(Device $device): ZKTeco
    {
        if (! extension_loaded('sockets')) {
            throw new RuntimeException('The PHP sockets extension is not enabled.');
        }

        $port = (int) ($device->port ?: 4370);

        // The ZKTeco library blocks for 60s on its socket read, which is far
        // too long for the unattended sync. Probe the host first so an offline
        // device fails in a couple of seconds instead of stalling the run.
        $this->assertReachable($device->ip, $port);

        $zk = new ZKTeco($device->ip, $port);

        if (! $zk->connect()) {
            throw new RuntimeException(
                "Could not reach the device at {$device->ip}:{$port}. ".
                'Check that it is powered on and on the same network.'
            );
        }

        return $zk;
    }

    /**
     * Cheap liveness probe before the slow UDP handshake.
     *
     * The device speaks UDP, which gives no connection signal, so this pings
     * the host with a short-timeout ICMP echo. A host that does not answer is
     * treated as unreachable; if ICMP is unavailable or blocked we say nothing
     * and let the library's own handshake decide.
     */
    protected function assertReachable(string $ip, int $port): void
    {
        // Only meaningful for a literal IP on the local network.
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return;
        }

        $timeoutMs = (int) config('zkteco.probe_timeout_ms', 2000);

        if (windows_os()) {
            $command = sprintf('ping -n 1 -w %d %s', $timeoutMs, escapeshellarg($ip));
        } else {
            $command = sprintf('ping -c 1 -W %d %s', max(1, (int) ceil($timeoutMs / 1000)), escapeshellarg($ip));
        }

        exec($command, $output, $status);

        if ($status !== 0) {
            throw new RuntimeException(
                "Could not reach the device at {$ip}:{$port}. ".
                'Check that it is powered on and on the same network.'
            );
        }
    }

    /**
     * Device strings come back with a "~SerialNumber=XXX" style prefix on some firmwares.
     */
    protected function cleanDeviceString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = trim($value);
        if (str_contains($value, '=')) {
            $value = trim(explode('=', $value, 2)[1]);
        }

        return $value !== '' ? $value : null;
    }
}
