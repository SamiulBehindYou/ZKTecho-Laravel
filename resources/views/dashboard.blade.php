@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Dashboard</h1>
        <form method="POST" action="{{ route('attendance.sync') }}">
            @csrf
            <button class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Sync attendance from all devices
            </button>
        </form>
    </div>

    <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ([
            ['label' => 'Devices', 'value' => $stats['devices']],
            ['label' => 'Active devices', 'value' => $stats['active_devices']],
            ['label' => 'Enrolled users', 'value' => $stats['users']],
            ['label' => 'Total punches', 'value' => number_format($stats['total_logs'])],
            ['label' => "Today's punches", 'value' => number_format($stats['today_logs'])],
        ] as $card)
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <div class="text-sm text-gray-500">{{ $card['label'] }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ $card['value'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="mb-3 font-semibold">Recent punches</h2>
            @if ($recent->isEmpty())
                <p class="text-sm text-gray-500">No attendance data yet. Add a device and sync.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="py-2">User</th>
                            <th class="py-2">Device</th>
                            <th class="py-2">Type</th>
                            <th class="py-2">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recent as $log)
                            <tr class="border-b last:border-0">
                                <td class="py-2">{{ $log->user_name ?? 'ID '.$log->userid }}</td>
                                <td class="py-2">{{ $log->device->name }}</td>
                                <td class="py-2">{{ $log->type_name }}</td>
                                <td class="py-2">{{ $log->punched_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="rounded-lg bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">Devices</h2>
                <a href="{{ route('devices.create') }}" class="text-sm text-indigo-600 hover:underline">Add device</a>
            </div>
            @if ($devices->isEmpty())
                <p class="text-sm text-gray-500">
                    No devices yet. <a href="{{ route('devices.create') }}" class="text-indigo-600 hover:underline">Add your first fingerprint device</a>.
                </p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="py-2">Name</th>
                            <th class="py-2">IP</th>
                            <th class="py-2">Users</th>
                            <th class="py-2">Logs</th>
                            <th class="py-2">Last sync</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($devices as $device)
                            <tr class="border-b last:border-0">
                                <td class="py-2">
                                    <a href="{{ route('devices.edit', $device) }}" class="text-indigo-600 hover:underline">{{ $device->name }}</a>
                                    @unless ($device->is_active)
                                        <span class="ml-1 rounded bg-gray-200 px-1.5 py-0.5 text-xs text-gray-600">inactive</span>
                                    @endunless
                                </td>
                                <td class="py-2">{{ $device->ip }}:{{ $device->port }}</td>
                                <td class="py-2">{{ $device->users_count }}</td>
                                <td class="py-2">{{ number_format($device->attendances_count) }}</td>
                                <td class="py-2">{{ $device->last_synced_at?->diffForHumans() ?? 'never' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>
@endsection
