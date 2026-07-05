@extends('layouts.app')

@section('title', 'Devices')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Devices</h1>
        <a href="{{ route('devices.create') }}"
           class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Add device</a>
    </div>

    @if ($devices->isEmpty())
        <div class="rounded-lg bg-white p-8 text-center shadow-sm">
            <p class="text-gray-500">No devices yet. Add your ZKTeco fingerprint device to start collecting attendance.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50 text-left text-gray-500">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Address</th>
                        <th class="px-4 py-3">Serial</th>
                        <th class="px-4 py-3">Users</th>
                        <th class="px-4 py-3">Logs</th>
                        <th class="px-4 py-3">Last sync</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($devices as $device)
                        <tr class="border-b last:border-0">
                            <td class="px-4 py-3 font-medium">{{ $device->name }}</td>
                            <td class="px-4 py-3">{{ $device->ip }}:{{ $device->port }}</td>
                            <td class="px-4 py-3">{{ $device->serial_number ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('devices.users', $device) }}" class="text-indigo-600 hover:underline">{{ $device->users_count }}</a>
                            </td>
                            <td class="px-4 py-3">{{ number_format($device->attendances_count) }}</td>
                            <td class="px-4 py-3">{{ $device->last_synced_at?->diffForHumans() ?? 'never' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs {{ $device->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">
                                    {{ $device->is_active ? 'active' : 'inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <form method="POST" action="{{ route('devices.test', $device) }}">
                                        @csrf
                                        <button class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50">Test</button>
                                    </form>
                                    <form method="POST" action="{{ route('attendance.sync') }}">
                                        @csrf
                                        <input type="hidden" name="device_id" value="{{ $device->id }}">
                                        <button class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50">Sync logs</button>
                                    </form>
                                    <a href="{{ route('devices.edit', $device) }}"
                                       class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50">Edit</a>
                                    <form method="POST" action="{{ route('devices.destroy', $device) }}"
                                          onsubmit="return confirm('Delete this device and all of its synced users and attendance logs?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded border border-red-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
