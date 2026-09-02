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

    @if ($incompleteCount > 0)
        <div class="mb-6 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <span class="font-medium">{{ number_format($incompleteCount) }} user(s) have no location ID and/or admin ID.</span>
            Their attendance is recorded locally but <span class="font-medium">is not pushed to the dashboard</span> until both are set.
            <a href="{{ route('users.index', ['incomplete' => 1]) }}" class="underline">Show only these users</a>.
        </div>
    @endif

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
        <div class="flex items-end">
            <label class="flex items-center gap-2 pb-1.5 text-sm">
                <input type="checkbox" name="incomplete" value="1" @checked(request()->boolean('incomplete'))
                       class="rounded border-gray-300">
                Missing location / admin
            </label>
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
        <form method="POST" action="{{ route('users.bulk-update') }}" id="bulk-form">
            @csrf
            @method('PUT')

            <div class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div>
                    <label for="bulk_location_id" class="mb-1 block text-xs font-medium text-gray-500">Location ID</label>
                    <input type="number" min="1" name="location_id" id="bulk_location_id" placeholder="e.g. 3" required
                           class="w-32 rounded border border-gray-300 px-2 py-1.5 text-sm">
                </div>
                <button class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    Assign location to selected
                </button>
                <p class="text-xs text-gray-500">
                    <span id="bulk-count">0</span> selected. Admin IDs are set per user in the table below.
                </p>
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50 text-left text-gray-500">
                            <th class="px-3 py-3">
                                <input type="checkbox" id="check-all" class="rounded border-gray-300">
                            </th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">User ID</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Device</th>
                            <th class="px-4 py-3">Location ID</th>
                            <th class="px-4 py-3">Admin ID</th>
                            <th class="whitespace-nowrap px-4 py-3">Push</th>
                            <th class="px-4 py-3">Punches</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            @php($ready = $user->isPushable())
                            <tr class="border-b last:border-0 {{ $ready ? '' : 'bg-amber-50/60' }}">
                                <td class="px-3 py-3">
                                    <input type="checkbox" name="user_ids[]" value="{{ $user->id }}"
                                           class="row-check rounded border-gray-300">
                                </td>
                                <td class="px-4 py-3 font-medium">{{ $user->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $user->userid }}</td>
                                <td class="px-4 py-3">{{ $user->role_name }}</td>
                                <td class="px-4 py-3">{{ $user->device->name }}</td>
                                <td class="px-4 py-3">
                                    <input type="number" min="1" form="user-form-{{ $user->id }}" name="location_id"
                                           value="{{ $user->location_id }}" placeholder="—"
                                           class="w-24 rounded border px-2 py-1 text-sm {{ $user->location_id === null ? 'border-amber-400 bg-amber-50' : 'border-gray-300' }}">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" min="1" form="user-form-{{ $user->id }}" name="admin_id"
                                           value="{{ $user->admin_id }}" placeholder="—"
                                           class="w-24 rounded border px-2 py-1 text-sm {{ $user->admin_id === null ? 'border-amber-400 bg-amber-50' : 'border-gray-300' }}">
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ($ready)
                                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-green-500"></span>
                                            Ready
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/30"
                                              title="Attendance is held locally until both the location ID and admin ID are set">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                                            On hold
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <a class="text-indigo-600 hover:underline"
                                       href="{{ route('attendance.index', ['device_id' => $user->device_id, 'q' => $user->userid]) }}">
                                        {{ number_format($user->punches_count) }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button form="user-form-{{ $user->id }}"
                                            class="rounded bg-slate-700 px-2 py-1 text-xs font-medium text-white hover:bg-slate-800">
                                        Save
                                    </button>
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
        </form>

        {{-- One form per row, outside the bulk form: HTML forms cannot nest, so
             the row inputs above reference these by their form attribute. --}}
        @foreach ($users as $user)
            <form method="POST" action="{{ route('users.update', $user) }}" id="user-form-{{ $user->id }}" class="hidden">
                @csrf
                @method('PUT')
            </form>
        @endforeach

        <script>
            (function () {
                const all = document.getElementById('check-all');
                const rows = Array.from(document.querySelectorAll('.row-check'));
                const count = document.getElementById('bulk-count');

                const sync = () => {
                    const checked = rows.filter((r) => r.checked).length;
                    count.textContent = checked;
                    all.checked = checked > 0 && checked === rows.length;
                    all.indeterminate = checked > 0 && checked < rows.length;
                };

                all.addEventListener('change', () => {
                    rows.forEach((r) => { r.checked = all.checked; });
                    sync();
                });
                rows.forEach((r) => r.addEventListener('change', sync));
                sync();
            })();
        </script>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    @endif
@endsection
