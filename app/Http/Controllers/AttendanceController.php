<?php

namespace App\Http\Controllers;

use App\Jobs\PushAttendanceToDashboard;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\DeviceUser;
use App\Services\DashboardPushService;
use App\Services\ZktecoService;
use Illuminate\Database\UniqueConstraintViolationException;
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
                    ->on('device_users.userid', '=', 'attendances.userid');
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
     * Show the form for adding an attendance entry by hand.
     */
    public function create(Request $request)
    {
        $users = DeviceUser::with('device')
            ->orderBy('name')
            ->orderBy('userid')
            ->get();

        return view('attendance.create', [
            'users' => $users,
            'types' => Attendance::TYPES,
            'selectedUserId' => $request->integer('user_id') ?: null,
        ]);
    }

    /**
     * Store a manual attendance entry. It is queued for the dashboard
     * push exactly like a punch synced from a device.
     */
    public function store(Request $request, DashboardPushService $pusher)
    {
        $data = $request->validate([
            'device_user_id' => ['required', 'integer', 'exists:device_users,id'],
            'type' => ['required', 'integer', 'in:'.implode(',', array_keys(Attendance::TYPES))],
            'punched_at' => ['required', 'date', 'before_or_equal:now'],
        ]);

        $user = DeviceUser::with('device')->findOrFail($data['device_user_id']);

        try {
            Attendance::create([
                'device_id' => $user->device_id,
                'uid' => $user->uid,
                'userid' => $user->userid,
                'state' => Attendance::STATE_MANUAL,
                'type' => $data['type'],
                'punched_at' => $data['punched_at'],
            ]);
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->with('error', 'An identical entry already exists for this user at that time.');
        }

        if ($pusher->enabled() && $pusher->configured()) {
            PushAttendanceToDashboard::dispatch();
        }

        return redirect()
            ->route('attendance.index', ['q' => $user->userid])
            ->with('success', "Manual entry added for {$user->name} ({$user->userid}).");
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
                // Keep names in sync so attendance rows can be matched to users.
                try {
                    $zkteco->syncUsers($device);
                } catch (Throwable) {
                    // A failed user sync should not block pulling attendance logs.
                }

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
