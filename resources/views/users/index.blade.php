@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Enrolled users</h1>
        <a href="{{ route('attendance.create') }}"
           class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Add manual attendance
        </a>
    </div>

    <form method="GET" action="{{ route('users.index') }}"
          class="mb-6 grid grid-cols-2 gap-4 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-4">
        <div>
            <label for="device_id" class="mb-1 block text-xs font-medium text-gray-500">Device</label>
            <select name="device_id" id="device_id"
                    class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
                <option value="">All devices</option>
                @foreach ($devices as $device)
                    <option value="{{ $device->id }}" @selected(($filters['device_id'] ?? null) == $device->id)>
                        {{ $device->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="q" class="mb-1 block text-xs font-medium text-gray-500">User ID / name / card</label>
            <input type="text" name="q" id="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search…"
                   class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
        </div>
        <div class="flex items-end gap-2">
            <button class="rounded bg-slate-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
            <a href="{{ route('users.index') }}" class="rounded border border-gray-300 px-4 py-1.5 text-sm hover:bg-gray-50">Reset</a>
        </div>
    </form>

    @if ($users->isEmpty())
        <div class="rounded-lg bg-white p-8 text-center shadow-sm">
            <p class="text-gray-500">
                No users found. Sync users from the <a href="{{ route('devices.index') }}" class="text-indigo-600 hover:underline">Devices</a> page,
                or adjust the filters.
            </p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50 text-left text-gray-500">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">User ID</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Card no.</th>
                        <th class="px-4 py-3">Device</th>
                        <th class="px-4 py-3">Punches</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr class="border-b last:border-0">
                            <td class="px-4 py-3 font-medium">{{ $user->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $user->userid }}</td>
                            <td class="px-4 py-3">{{ $user->role_name }}</td>
                            <td class="px-4 py-3">{{ $user->cardno ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $user->device->name }}</td>
                            <td class="px-4 py-3">
                                <a class="text-indigo-600 hover:underline"
                                   href="{{ route('attendance.index', ['device_id' => $user->device_id, 'q' => $user->userid]) }}">
                                    {{ number_format($user->punches_count) }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('attendance.create', ['user_id' => $user->id]) }}"
                                   class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50">
                                    Add attendance
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    @endif
@endsection
