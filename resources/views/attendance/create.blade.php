@extends('layouts.app')

@section('title', 'Add manual attendance')

@section('content')
    <div class="mx-auto max-w-lg">
        <h1 class="mb-6 text-2xl font-semibold">Add manual attendance</h1>

        @if ($users->isEmpty())
            <div class="rounded-lg bg-white p-8 text-center shadow-sm">
                <p class="text-gray-500">
                    No users available. Sync users from the
                    <a href="{{ route('devices.index') }}" class="text-indigo-600 hover:underline">Devices</a> page first.
                </p>
            </div>
        @else
            <form method="POST" action="{{ route('attendance.store') }}" class="rounded-lg bg-white p-6 shadow-sm">
                @csrf

                <div class="space-y-4">
                    <div>
                        <label for="device_user_id" class="mb-1 block text-sm font-medium">User</label>
                        <select name="device_user_id" id="device_user_id" required
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                            <option value="">Select a user…</option>
                            @foreach ($users->groupBy(fn ($u) => $u->device->name) as $deviceName => $deviceUsers)
                                <optgroup label="{{ $deviceName }}">
                                    @foreach ($deviceUsers as $user)
                                        <option value="{{ $user->id }}"
                                                @selected((int) old('device_user_id', $selectedUserId) === $user->id)>
                                            {{ $user->name ?? 'Unnamed' }} ({{ $user->userid }})
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="type" class="mb-1 block text-sm font-medium">Punch type</label>
                        <select name="type" id="type" required
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected((string) old('type', '0') === (string) $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="punched_at" class="mb-1 block text-sm font-medium">Date &amp; time</label>
                        <input type="datetime-local" name="punched_at" id="punched_at" required
                               max="{{ now()->format('Y-m-d\TH:i') }}"
                               value="{{ old('punched_at', now()->format('Y-m-d\TH:i')) }}"
                               class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        <p class="mt-1 text-xs text-gray-500">Cannot be in the future.</p>
                    </div>
                </div>

                <p class="mt-4 rounded bg-gray-50 px-3 py-2 text-xs text-gray-500">
                    The entry is marked as <span class="font-medium">Manual</span> so it stays distinguishable
                    from device punches, and it is pushed to the dashboard like any other log.
                </p>

                <div class="mt-6 flex gap-2">
                    <button type="submit"
                            class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Add entry
                    </button>
                    <a href="{{ route('attendance.index') }}"
                       class="rounded border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Cancel</a>
                </div>
            </form>
        @endif
    </div>
@endsection
