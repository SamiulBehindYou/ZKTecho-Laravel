<?php

namespace App\Http\Controllers;

use App\Jobs\PushAttendanceToDashboard;
use App\Models\Device;
use App\Models\DeviceUser;
use App\Services\DashboardPushService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'q' => ['nullable', 'string', 'max:255'],
            'incomplete' => ['nullable', 'boolean'],
        ]);

        $users = DeviceUser::with('device')
            ->select('device_users.*')
            ->selectSub(function ($query) {
                $query->from('attendances')
                    ->selectRaw('count(*)')
                    ->whereColumn('attendances.device_id', 'device_users.device_id')
                    ->whereColumn('attendances.userid', 'device_users.userid');
            }, 'punches_count')
            ->when($filters['device_id'] ?? null, fn ($q, $id) => $q->where('device_users.device_id', $id))
            ->when($request->boolean('incomplete'), fn ($q) => $q->incomplete())
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(function ($q) use ($term) {
                    $q->where('device_users.userid', 'like', "%{$term}%")
                        ->orWhere('device_users.name', 'like', "%{$term}%")
                        ->orWhere('device_users.cardno', 'like', "%{$term}%");
                });
            })
            ->orderBy('device_users.name')
            ->orderBy('device_users.userid')
            ->paginate(50)
            ->withQueryString();

        $devices = Device::orderBy('name')->get();
        $incompleteCount = DeviceUser::incomplete()->count();

        return view('users.index', compact('users', 'devices', 'filters', 'incompleteCount'));
    }

    /**
     * Set the dashboard location_id / admin_id for one user. Attendance for
     * a user is not pushed until both are filled in.
     */
    public function update(Request $request, DeviceUser $user, DashboardPushService $pusher)
    {
        $data = $request->validate([
            'location_id' => ['nullable', 'integer', 'min:1'],
            'admin_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $user->update([
            'location_id' => $data['location_id'] ?? null,
            'admin_id' => $data['admin_id'] ?? null,
        ]);

        // Newly completed users may have a backlog of punches waiting.
        if ($user->isPushable() && $pusher->enabled() && $pusher->configured() && $pusher->pendingCount() > 0) {
            PushAttendanceToDashboard::dispatch();
        }

        $label = $user->name ?: $user->userid;

        return back()->with(
            'success',
            $user->isPushable()
                ? "Saved location and admin for {$label}. Pending attendance will be pushed."
                : "Saved {$label}, but attendance stays on hold until both location and admin are set."
        );
    }

    /**
     * Assign the same location_id to several users at once. admin_id is set
     * per user only, since it differs from one employee to the next.
     */
    public function bulkUpdate(Request $request, DashboardPushService $pusher)
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:device_users,id'],
            'location_id' => ['required', 'integer', 'min:1'],
        ]);

        $count = DeviceUser::whereIn('id', $data['user_ids'])
            ->update(['location_id' => $data['location_id']]);

        if ($pusher->enabled() && $pusher->configured() && $pusher->pendingCount() > 0) {
            PushAttendanceToDashboard::dispatch();
        }

        return back()->with('success', "Set the location for {$count} user(s).");
    }
}
