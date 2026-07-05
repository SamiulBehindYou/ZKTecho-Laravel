@extends('layouts.app')

@section('title', 'Attendance')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Attendance logs</h1>
        <div class="flex gap-2">
            <a href="{{ route('attendance.create') }}"
               class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                Add manual entry
            </a>
            <form method="POST" action="{{ route('attendance.sync') }}">
                @csrf
                <button class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Sync from device(s)
                </button>
            </form>
        </div>
    </div>

    <form method="GET" action="{{ route('attendance.index') }}"
          class="mb-6 grid grid-cols-2 gap-4 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-5">
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
            <label for="from" class="mb-1 block text-xs font-medium text-gray-500">From</label>
            <input type="date" name="from" id="from" value="{{ $filters['from'] ?? '' }}"
                   class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
        </div>
        <div>
            <label for="to" class="mb-1 block text-xs font-medium text-gray-500">To</label>
            <input type="date" name="to" id="to" value="{{ $filters['to'] ?? '' }}"
                   class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
        </div>
        <div>
            <label for="q" class="mb-1 block text-xs font-medium text-gray-500">User ID / name</label>
            <input type="text" name="q" id="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search…"
                   class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
        </div>
        <div class="flex items-end gap-2">
            <button class="rounded bg-slate-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
            <a href="{{ route('attendance.index') }}" class="rounded border border-gray-300 px-4 py-1.5 text-sm hover:bg-gray-50">Reset</a>
        </div>
    </form>

    @if ($logs->isEmpty())
        <div class="rounded-lg bg-white p-8 text-center shadow-sm">
            <p class="text-gray-500">No attendance records found. Sync from your device or adjust the filters.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50 text-left text-gray-500">
                        <th class="px-4 py-3">Date &amp; time</th>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">User ID</th>
                        <th class="px-4 py-3">Punch</th>
                        <th class="px-4 py-3">Verified by</th>
                        <th class="px-4 py-3">Device</th>
                        <th class="px-4 py-3">Pushed</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr class="border-b last:border-0">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $log->punched_at->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 font-medium">{{ $log->user_name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $log->userid }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs {{ (int) $log->type === 0 ? 'bg-green-100 text-green-700' : ((int) $log->type === 1 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                                    {{ $log->type_name }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $log->state_name }}</td>
                            <td class="px-4 py-3">{{ $log->device->name }}</td>
                            <td class="px-4 py-3">
                                @if ($log->pushed_at)
                                    <span class="rounded bg-green-100 px-2 py-0.5 text-xs text-green-700" title="{{ $log->pushed_at }}">Yes</span>
                                @else
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-500">Pending</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    @endif
@endsection
