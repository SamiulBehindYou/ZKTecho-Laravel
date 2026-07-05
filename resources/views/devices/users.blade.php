@extends('layouts.app')

@section('title', $device->name.' — Users')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">{{ $device->name }} — enrolled users</h1>
            <p class="text-sm text-gray-500">{{ $device->ip }}{{ $device->port ? ':'.$device->port : '' }}</p>
        </div>
        <form method="POST" action="{{ route('devices.sync-users', $device) }}">
            @csrf
            <button class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Sync users from device
            </button>
        </form>
    </div>

    @if ($users->isEmpty())
        <div class="rounded-lg bg-white p-8 text-center shadow-sm">
            <p class="text-gray-500">No users synced yet. Click "Sync users from device" to pull the enrolled users.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50 text-left text-gray-500">
                        <th class="px-4 py-3">UID</th>
                        <th class="px-4 py-3">User ID</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Card no.</th>
                        <th class="px-4 py-3">Punches</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr class="border-b last:border-0">
                            <td class="px-4 py-3">{{ $user->uid }}</td>
                            <td class="px-4 py-3">{{ $user->userid }}</td>
                            <td class="px-4 py-3 font-medium">{{ $user->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $user->role_name }}</td>
                            <td class="px-4 py-3">{{ $user->cardno ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <a class="text-indigo-600 hover:underline"
                                   href="{{ route('attendance.index', ['device_id' => $device->id, 'q' => $user->userid]) }}">
                                    view
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
