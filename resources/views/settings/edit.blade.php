@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Settings</h1>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('settings.update') }}" class="rounded-lg bg-white p-6 shadow-sm">
                @csrf
                @method('PUT')

                <h2 class="mb-1 text-lg font-semibold">Dashboard push</h2>
                <p class="mb-5 text-sm text-gray-500">
                    Attendance is always recorded locally first. Pending records are pushed to your live
                    dashboard in the background and retried automatically whenever the network is back.
                </p>

                <div class="space-y-4">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="checkbox" name="enabled" value="1"
                               @checked(old('enabled', ($settings['push.enabled'] ?? '0') === '1'))
                               class="rounded border-gray-300">
                        Enable pushing to the live dashboard
                    </label>

                    <div>
                        <label for="url" class="mb-1 block text-sm font-medium">Dashboard API URL</label>
                        <input type="url" name="url" id="url"
                               value="{{ old('url', $settings['push.url'] ?? '') }}"
                               placeholder="https://dashboard.example.com/api/attendance"
                               class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        <p class="mt-1 text-xs text-gray-500">Records are sent as a JSON <code>POST</code> to this exact URL.</p>
                    </div>

                    <div>
                        <label for="token" class="mb-1 block text-sm font-medium">API token</label>
                        <input type="password" name="token" id="token"
                               value="{{ old('token', $settings['push.token'] ?? '') }}"
                               placeholder="Optional bearer token"
                               autocomplete="off"
                               class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        <p class="mt-1 text-xs text-gray-500">Sent as <code>Authorization: Bearer &lt;token&gt;</code>.</p>
                    </div>

                    <div>
                        <label for="source" class="mb-1 block text-sm font-medium">Source name</label>
                        <input type="text" name="source" id="source"
                               value="{{ old('source', $settings['push.source'] ?? '') }}"
                               placeholder="e.g. Head office"
                               class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        <p class="mt-1 text-xs text-gray-500">Identifies this local installation on the dashboard.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="batch_size" class="mb-1 block text-sm font-medium">Batch size</label>
                            <input type="number" name="batch_size" id="batch_size" required min="1" max="1000"
                                   value="{{ old('batch_size', $settings['push.batch_size'] ?? 200) }}"
                                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                            <p class="mt-1 text-xs text-gray-500">Records per request.</p>
                        </div>
                        <div>
                            <label for="timeout" class="mb-1 block text-sm font-medium">Timeout (seconds)</label>
                            <input type="number" name="timeout" id="timeout" required min="5" max="300"
                                   value="{{ old('timeout', $settings['push.timeout'] ?? 30) }}"
                                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit"
                            class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Save settings
                    </button>
                </div>
            </form>

            <div class="mt-6 rounded-lg bg-white p-6 shadow-sm">
                <h2 class="mb-2 text-lg font-semibold">Payload format</h2>
                <p class="mb-3 text-sm text-gray-500">
                    Your dashboard endpoint receives this JSON body. Use <code>device_serial</code> +
                    <code>userid</code> + <code>punched_at</code> + <code>type</code> (or <code>local_id</code>)
                    to de-duplicate on the dashboard side, and respond with any 2xx status to acknowledge.
                </p>
                <p class="mb-3 text-sm text-gray-500">
                    <code>location_id</code> and <code>admin_id</code> come from each user's row on the
                    <a href="{{ route('users.index') }}" class="text-indigo-600 hover:underline">Users</a> page.
                    Records whose user is missing either one are never sent.
                </p>
<pre class="overflow-x-auto rounded bg-slate-800 p-4 text-xs leading-relaxed text-slate-100">{
  "source": "Head office",
  "records": [
    {
      "local_id": 123,
      "device_serial": "A8N5203260001",
      "device_name": "Main entrance",
      "uid": 12,
      "userid": "1042",
      "user_name": "Jane Doe",
      "location_id": 3,
      "admin_id": 7,
      "state": 1,
      "state_name": "Fingerprint",
      "type": 0,
      "type_name": "Check-in",
      "punched_at": "2026-07-05T09:01:23+06:00"
    }
  ]
}</pre>
                <p class="mt-3 text-xs text-gray-500">
                    "Test connection" sends <code>{"ping": true, "records": []}</code> to the same URL.
                </p>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">Push status</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Pending records</dt>
                        <dd class="font-medium {{ $pendingCount > 0 ? 'text-amber-600' : 'text-green-600' }}">
                            {{ number_format($pendingCount) }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">On hold (no location/admin)</dt>
                        <dd class="font-medium {{ $blockedCount > 0 ? 'text-amber-600' : 'text-gray-900' }}">
                            {{ number_format($blockedCount) }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Pushed records</dt>
                        <dd class="font-medium">{{ number_format($pushedCount) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Last successful push</dt>
                        <dd class="font-medium">{{ $settings['push.last_success_at'] ?? 'Never' }}</dd>
                    </div>
                </dl>

                @if ($blockedCount > 0)
                    <div class="mt-4 rounded border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                        <p class="font-semibold">{{ number_format($blockedCount) }} record(s) are not being pushed.</p>
                        <p class="mt-1">These users need a location ID and an admin ID:</p>
                        <ul class="mt-1 list-inside list-disc">
                            @foreach ($blockedUsers->take(10) as $blocked)
                                <li>{{ $blocked->name ?: $blocked->userid }} ({{ $blocked->userid }}) —
                                    missing {{ collect(['location ID' => $blocked->location_id, 'admin ID' => $blocked->admin_id])
                                        ->filter(fn ($v) => $v === null)->keys()->join(' and ') }}
                                </li>
                            @endforeach
                        </ul>
                        @if ($blockedUsers->count() > 10)
                            <p class="mt-1">…and {{ $blockedUsers->count() - 10 }} more.</p>
                        @endif
                        <a href="{{ route('users.index', ['incomplete' => 1]) }}" class="mt-2 inline-block font-medium underline">
                            Fill these in on the Users page
                        </a>
                    </div>
                @endif

                @if (!empty($settings['push.last_error']))
                    <div class="mt-4 rounded border border-red-200 bg-red-50 p-3 text-xs text-red-800">
                        <p class="font-semibold">Last error ({{ $settings['push.last_error_at'] ?? 'unknown time' }})</p>
                        <p class="mt-1 break-words">{{ $settings['push.last_error'] }}</p>
                    </div>
                @endif

                <div class="mt-5 flex flex-col gap-2">
                    <form method="POST" action="{{ route('settings.test') }}">
                        @csrf
                        <button type="submit"
                                class="w-full rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">
                            Test connection
                        </button>
                    </form>
                    <form method="POST" action="{{ route('settings.push-now') }}">
                        @csrf
                        <button type="submit"
                                class="w-full rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                            Push pending now
                        </button>
                    </form>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm text-sm text-gray-600">
                <h2 class="mb-2 text-lg font-semibold text-gray-900">How retries work</h2>
                <ul class="list-inside list-disc space-y-1.5">
                    <li>New records are queued for push right after every device sync.</li>
                    <li>If the dashboard is unreachable, the job retries with increasing delays (1&nbsp;min → 30&nbsp;min).</li>
                    <li>A scheduler re-queues anything still pending every 5 minutes.</li>
                    <li>Local attendance capture keeps working even while pushing fails.</li>
                </ul>
                <p class="mt-3 text-xs text-gray-500">
                    Requires the queue worker and scheduler to be running
                    (<code>composer dev</code> starts both).
                </p>
            </div>
        </div>
    </div>
@endsection
