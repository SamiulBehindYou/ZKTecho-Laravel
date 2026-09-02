<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Device;
use App\Models\DeviceUser;
use App\Models\Setting;
use App\Services\DashboardPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The punch time that goes over the wire must carry its UTC offset, so the
 * dashboard can convert it without guessing which timezone it was in.
 */
class DashboardPushTimeTest extends TestCase
{
    use RefreshDatabase;

    protected Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setMany([
            'push.enabled' => '1',
            'push.url' => 'https://dashboard.test/api/attendance',
            'push.batch_size' => 200,
            'push.source' => 'London',
        ]);

        $this->device = Device::create([
            'name' => 'Main entrance',
            'ip' => '192.168.1.201',
            'port' => 4370,
            'is_active' => true,
            'serial_number' => 'TEST0001',
        ]);
    }

    private function pushPunchAt(string $punchedAt): array
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $user = DeviceUser::create([
            'device_id' => $this->device->id,
            'uid' => 1,
            'userid' => '1001',
            'name' => 'Zoe Richards',
            'role' => DeviceUser::ROLE_USER,
            'location_id' => 1,
            'admin_id' => 7,
        ]);

        Attendance::create([
            'device_id' => $user->device_id,
            'uid' => $user->uid,
            'userid' => $user->userid,
            'state' => 1,
            'type' => 0,
            'punched_at' => $punchedAt,
        ]);

        app(DashboardPushService::class)->pushPending();

        $sent = [];
        Http::assertSent(function ($request) use (&$sent) {
            $sent = $request->data();

            return true;
        });

        return $sent['records'][0];
    }

    public function test_a_summer_punch_is_sent_with_its_bst_offset(): void
    {
        $record = $this->pushPunchAt('2026-09-02 16:04:00');

        // 16:04 on the device clock is 16:04 BST, i.e. 15:04 UTC.
        $this->assertSame('2026-09-02T16:04:00+01:00', $record['punched_at']);
    }

    public function test_a_winter_punch_is_sent_with_its_gmt_offset(): void
    {
        $record = $this->pushPunchAt('2026-12-02 16:04:00');

        $this->assertSame('2026-12-02T16:04:00+00:00', $record['punched_at']);
    }

    public function test_the_sent_time_always_carries_an_offset(): void
    {
        $record = $this->pushPunchAt('2026-09-02 16:04:00');

        // Without an offset the dashboard has to guess the timezone, which is
        // what put punches an hour out before.
        $this->assertMatchesRegularExpression(
            '/(Z|[+-]\d{2}:\d{2})$/',
            $record['punched_at']
        );
    }
}
