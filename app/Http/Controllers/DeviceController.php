<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\ZktecoService;
use Illuminate\Http\Request;
use Throwable;

class DeviceController extends Controller
{
    public function index()
    {
        $devices = Device::withCount(['users', 'attendances'])->orderBy('name')->get();

        return view('devices.index', compact('devices'));
    }

    public function create()
    {
        return view('devices.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Device::create($data);

        return redirect()->route('devices.index')->with('success', 'Device added.');
    }

    public function edit(Device $device)
    {
        return view('devices.edit', compact('device'));
    }

    public function update(Request $request, Device $device)
    {
        $device->update($this->validated($request, $device));

        return redirect()->route('devices.index')->with('success', 'Device updated.');
    }

    public function destroy(Device $device)
    {
        $device->delete();

        return redirect()->route('devices.index')->with('success', 'Device and its synced data removed.');
    }

    public function test(Device $device, ZktecoService $zkteco)
    {
        $result = $zkteco->testConnection($device);

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        $info = $result['info'];

        return back()->with('success', sprintf(
            'Connected to %s (S/N %s) — firmware %s, device time %s.',
            $device->device_name ?: $device->name,
            $device->serial_number ?: 'unknown',
            $info['version'] ?: 'unknown',
            $info['device_time'] ?: 'unknown',
        ));
    }

    public function users(Device $device)
    {
        $users = $device->users()->orderBy('uid')->get();

        return view('devices.users', compact('device', 'users'));
    }

    public function syncUsers(Device $device, ZktecoService $zkteco)
    {
        try {
            $count = $zkteco->syncUsers($device);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('devices.users', $device)
            ->with('success', "Synced {$count} user(s) from the device.");
    }

    protected function validated(Request $request, ?Device $device = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ip' => ['required', 'ip'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'is_active' => ['boolean'],
        ]);

        $data['port'] = $data['port'] ?? 4370;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
