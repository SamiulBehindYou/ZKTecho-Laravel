<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceUser;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'q' => ['nullable', 'string', 'max:255'],
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

        return view('users.index', compact('users', 'devices', 'filters'));
    }
}
