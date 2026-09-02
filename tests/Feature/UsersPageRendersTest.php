<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersPageRendersTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_and_settings_pages_render_with_the_new_columns(): void
    {
        $device = Device::create([
            'name' => 'Main entrance',
            'ip' => '192.168.1.201',
            'is_active' => true,
        ]);

        DeviceUser::create([
            'device_id' => $device->id,
            'uid' => 1,
            'userid' => '1001',
            'name' => 'Jane Doe',
            'role' => DeviceUser::ROLE_USER,
            'location_id' => 3,
            'admin_id' => 7,
        ]);

        DeviceUser::create([
            'device_id' => $device->id,
            'uid' => 2,
            'userid' => '1002',
            'name' => 'John Roe',
            'role' => DeviceUser::ROLE_USER,
        ]);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('Location ID')
            ->assertSee('Admin ID')
            ->assertSee('Assign location to selected')
            ->assertSee('On hold')
            ->assertSee('Ready')
            ->assertSee('1 user(s) have no location ID and/or admin ID.');

        // The "incomplete" filter narrows to just the unfilled user.
        $this->get(route('users.index', ['incomplete' => 1]))
            ->assertOk()
            ->assertSee('John Roe')
            ->assertDontSee('Jane Doe');

        $this->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('On hold (no location/admin)')
            ->assertSee('location_id');
    }
}
