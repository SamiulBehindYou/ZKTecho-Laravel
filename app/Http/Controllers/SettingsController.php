<?php

namespace App\Http\Controllers;

use App\Jobs\PushAttendanceToDashboard;
use App\Models\Attendance;
use App\Models\Setting;
use App\Services\DashboardPushService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(DashboardPushService $pusher)
    {
        return view('settings.edit', [
            'settings' => Setting::allValues(),
            'pendingCount' => $pusher->pendingCount(),
            'pushedCount' => Attendance::whereNotNull('pushed_at')->count(),
            'blockedCount' => $pusher->blockedCount(),
            'blockedUsers' => $pusher->blockedUsers(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'token' => ['nullable', 'string', 'max:2048'],
            'source' => ['nullable', 'string', 'max:255'],
            'batch_size' => ['required', 'integer', 'min:1', 'max:1000'],
            'timeout' => ['required', 'integer', 'min:5', 'max:300'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        if (($data['enabled'] ?? false) && empty($data['url'])) {
            return back()->withInput()->withErrors([
                'url' => 'A dashboard API URL is required to enable pushing.',
            ]);
        }

        Setting::setMany([
            'push.enabled' => ($data['enabled'] ?? false) ? '1' : '0',
            'push.url' => $data['url'] ?? null,
            'push.token' => $data['token'] ?? null,
            'push.source' => $data['source'] ?? null,
            'push.batch_size' => $data['batch_size'],
            'push.timeout' => $data['timeout'],
        ]);

        return redirect()->route('settings.edit')->with('success', 'Settings saved.');
    }

    /**
     * Ping the configured dashboard endpoint without pushing any records.
     */
    public function test(DashboardPushService $pusher)
    {
        $result = $pusher->testConnection();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Queue an immediate push of everything pending.
     */
    public function pushNow(DashboardPushService $pusher)
    {
        if (! $pusher->enabled() || ! $pusher->configured()) {
            return back()->with('error', 'Enable and configure dashboard push first.');
        }

        $pending = $pusher->pendingCount();

        if ($pending === 0) {
            $blocked = $pusher->blockedCount();

            return $blocked > 0
                ? back()->with('error', "{$blocked} record(s) are on hold because their users have no location ID / admin ID. Set those on the Users page first.")
                : back()->with('success', 'Nothing to push — all records are already on the dashboard.');
        }

        PushAttendanceToDashboard::dispatch();

        return back()->with('success', "Queued push for {$pending} pending record(s). Make sure the queue worker is running.");
    }
}
