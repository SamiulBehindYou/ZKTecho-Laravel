<?php

namespace App\Http\Controllers;

use App\Jobs\PushAttendanceToDashboard;
use App\Models\Attendance;
use App\Models\Device;
use App\Services\DashboardPushService;
use App\Services\ZktecoService;
use Illuminate\Http\Request;
use Throwable;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $logs = Attendance::with('device')
            ->leftJoin('device_users', function ($join) {
                $join->on('device_users.device_id', '=', 'attendances.device_id')
                    ->on('device_users.uid', '=', 'attendances.uid');
            })
            ->select('attendances.*', 'device_users.name as user_name')
            ->when($filters['device_id'] ?? null, fn ($q, $id) => $q->where('attendances.device_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('punched_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('punched_at', '<=', $to.' 23:59:59'))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(function ($q) use ($term) {
                    $q->where('attendances.userid', 'like', "%{$term}%")
                        ->orWhere('device_users.name', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('punched_at')
            ->paginate(25)
            ->withQueryString();

        $devices = Device::orderBy('name')->get();

        return view('attendance.index', compact('logs', 'devices', 'filters'));
    }

    /**
     * Pull new attendance logs from one device, or from every active device.
     */
    public function sync(Request $request, ZktecoService $zkteco, DashboardPushService $pusher)
    {
        $request->validate(['device_id' => ['nullable', 'integer', 'exists:devices,id']]);

        $devices = $request->filled('device_id')
            ? Device::whereKey($request->integer('device_id'))->get()
            : Device::where('is_active', true)->get();

        if ($devices->isEmpty()) {
            return back()->with('error', 'No active devices to sync. Add a device first.');
        }

        $messages = [];
        $failures = 0;

        foreach ($devices as $device) {
            try {
                $result = $zkteco->syncAttendance($device);
                $messages[] = "{$device->name}: {$result['new']} new of {$result['fetched']} log(s)";
            } catch (Throwable $e) {
                $failures++;
                $messages[] = "{$device->name}: {$e->getMessage()}";
            }
        }

        if ($failures < $devices->count() && $pusher->enabled() && $pusher->configured() && $pusher->pendingCount() > 0) {
            PushAttendanceToDashboard::dispatch();
        }

        $summary = implode(' | ', $messages);

        return $failures === $devices->count()
            ? back()->with('error', $summary)
            : back()->with('success', $summary);
    }
}
