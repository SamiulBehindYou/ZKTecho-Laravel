<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\DeviceUser;
use Illuminate\Database\Seeder;

class DeviceUserSeeder extends Seeder
{
    /**
     * Seed dummy device users.
     */
    public function run(): void
    {
        $device = Device::first() ?? Device::create([
            'name' => 'Dummy Device',
            'ip' => '192.168.1.201',
            'port' => 4370,
            'is_active' => true,
            'serial_number' => 'DUMMY-'.strtoupper(fake()->bothify('??######')),
            'device_name' => 'ZKTeco Dummy',
        ]);

        $startUid = (int) DeviceUser::where('device_id', $device->id)->max('uid') + 1;

        for ($i = 0; $i < 20; $i++) {
            $uid = $startUid + $i;

            DeviceUser::create([
                'device_id' => $device->id,
                'uid' => $uid,
                'userid' => (string) $uid,
                'name' => fake()->name(),
                'role' => $i === 0 ? DeviceUser::ROLE_ADMIN : DeviceUser::ROLE_USER,
                'cardno' => fake()->boolean(70) ? (string) fake()->numberBetween(1000000, 9999999) : null,
            ]);
        }
    }
}
