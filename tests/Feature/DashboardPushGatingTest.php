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

class DashboardPushGatingTest extends TestCase
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
        ]);

        $this->device = Device::create([
            'name' => 'Main entrance',
            'ip' => '192.168.1.201',
            'port' => 4370,
            'is_active' => true,
            'serial_number' => 'TEST0001',
        ]);
    }

    protected function makeUser(array $attributes = []): DeviceUser
    {
        static $uid = 0;
        $uid++;

        return DeviceUser::create(array_merge([
            'device_id' => $this->device->id,
            'uid' => $uid,
            'userid' => (string) (1000 + $uid),
            'name' => 'User '.$uid,
            'role' => DeviceUser::ROLE_USER,
        ], $attributes));
    }

    protected function makePunch(DeviceUser $user): Attendance
    {
        return Attendance::create([
            'device_id' => $user->device_id,
            'uid' => $user->uid,
            'userid' => $user->userid,
            'state' => 1,
            'type' => 0,
            'punched_at' => now()->subMinutes(5),
        ]);
    }

    public function test_records_are_not_pushed_when_location_and_admin_are_missing(): void
    {
        Http::fake();

        $punch = $this->makePunch($this->makeUser());

        $pushed = app(DashboardPushService::class)->pushPending();

        $this->assertSame(0, $pushed);
        Http::assertNothingSent();
        $this->assertNull($punch->fresh()->pushed_at);
    }

    public function test_records_are_not_pushed_when_only_one_of_the_two_is_set(): void
    {
        Http::fake();

        $onlyLocation = $this->makePunch($this->makeUser(['location_id' => 3]));
        $onlyAdmin = $this->makePunch($this->makeUser(['admin_id' => 7]));

        $this->assertSame(0, app(DashboardPushService::class)->pushPending());

        Http::assertNothingSent();
        $this->assertNull($onlyLocation->fresh()->pushed_at);
        $this->assertNull($onlyAdmin->fresh()->pushed_at);
    }

    public function test_records_are_pushed_with_location_and_admin_once_both_are_set(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $user = $this->makeUser(['location_id' => 3, 'admin_id' => 7]);
        $punch = $this->makePunch($user);

        $this->assertSame(1, app(DashboardPushService::class)->pushPending());

        Http::assertSent(function ($request) use ($punch) {
            $record = $request->data()['records'][0];

            return $record['local_id'] === $punch->id
                && $record['location_id'] === 3
                && $record['admin_id'] === 7;
        });

        $this->assertNotNull($punch->fresh()->pushed_at);
    }

    public function test_only_the_complete_users_records_are_sent(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $ready = $this->makePunch($this->makeUser(['location_id' => 1, 'admin_id' => 2]));
        $blocked = $this->makePunch($this->makeUser());

        $this->assertSame(1, app(DashboardPushService::class)->pushPending());

        $this->assertNotNull($ready->fresh()->pushed_at);
        $this->assertNull($blocked->fresh()->pushed_at);

        Http::assertSent(fn ($request) => count($request->data()['records']) === 1);
    }

    public function test_blocked_records_are_released_after_the_ids_are_filled_in(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $user = $this->makeUser();
        $punch = $this->makePunch($user);

        $pusher = app(DashboardPushService::class);
        $this->assertSame(0, $pusher->pushPending());
        $this->assertSame(1, $pusher->blockedCount());
        $this->assertSame(0, $pusher->pendingCount());

        $user->update(['location_id' => 3, 'admin_id' => 7]);

        $this->assertSame(0, $pusher->blockedCount());
        $this->assertSame(1, $pusher->pendingCount());
        $this->assertSame(1, $pusher->pushPending());
        $this->assertNotNull($punch->fresh()->pushed_at);
    }

    public function test_users_page_saves_the_ids_for_one_user(): void
    {
        $user = $this->makeUser();

        $this->put(route('users.update', $user), [
            'location_id' => 3,
            'admin_id' => 7,
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame(3, $user->location_id);
        $this->assertSame(7, $user->admin_id);
        $this->assertTrue($user->isPushable());
    }

    public function test_bulk_assign_sets_the_location_for_many_users_without_touching_admin_id(): void
    {
        $a = $this->makeUser(['admin_id' => 99]);
        $b = $this->makeUser();

        $this->put(route('users.bulk-update'), [
            'user_ids' => [$a->id, $b->id],
            'location_id' => 5,
        ])->assertRedirect();

        $this->assertSame(5, $a->fresh()->location_id);
        $this->assertSame(99, $a->fresh()->admin_id, 'Bulk assign must leave admin_id alone.');
        $this->assertSame(5, $b->fresh()->location_id);
        $this->assertNull($b->fresh()->admin_id);
    }

    public function test_bulk_assign_requires_a_location_and_ignores_a_posted_admin_id(): void
    {
        $user = $this->makeUser();

        $this->put(route('users.bulk-update'), ['user_ids' => [$user->id]])
            ->assertRedirect()
            ->assertSessionHasErrors('location_id');

        // admin_id is not part of the bulk contract; posting it changes nothing.
        $this->put(route('users.bulk-update'), [
            'user_ids' => [$user->id],
            'location_id' => 5,
            'admin_id' => 42,
        ])->assertRedirect();

        $this->assertSame(5, $user->fresh()->location_id);
        $this->assertNull($user->fresh()->admin_id);
    }
}
