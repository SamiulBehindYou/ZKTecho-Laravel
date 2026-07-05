<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Device;
use App\Models\DeviceUser;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'devices' => Device::count(),
            'active_devices' => Device::where('is_active', true)->count(),
            'users' => DeviceUser::count(),
            'total_logs' => Attendance::count(),
            'today_logs' => Attendance::whereDate('punched_at', today())->count(),
        ];

        $recent = Attendance::with('device')
            ->leftJoin('device_users', function ($join) {
                $join->on('device_users.device_id', '=', 'attendances.device_id')
                    ->on('device_users.uid', '=', 'attendances.uid');
            })
            ->select('attendances.*', 'device_users.name as user_name')
            ->orderByDesc('punched_at')
            ->limit(10)
            ->get();

        $devices = Device::withCount(['users', 'attendances'])->get();

        return view('dashboard', compact('stats', 'recent', 'devices'));
    }
}
