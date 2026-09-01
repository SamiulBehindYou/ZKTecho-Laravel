# Automatic Attendance Sync — Office PC Setup

How to make attendance pull from the ZKTeco device and push to the dashboard
automatically, with no one clicking anything.

---

## 1. How it works

Four pieces have to be in place. The app only runs unattended when all four are:

| Piece | What it does |
|---|---|
| `attendance:sync` | Connects to each active device, pulls new punches into the local database. |
| `attendance:push` | Sends unpushed punches to the dashboard API. |
| **Scheduler** (`schedule:work`) | Ticks every minute and fires the two commands above every 5 minutes. |
| **Queue worker** (`queue:work`) | Runs the `PushAttendanceToDashboard` job that does the actual sending. |

The flow:

```
ZKTeco device
      |  (attendance:sync, every 5 min)
      v
local `attendances` table  ---> dashboard push job ---> dashboard API
      ^                            (queue worker)
      |
   pushed_at stamped once accepted, so nothing is ever sent twice
```

Records are only marked `pushed_at` after the dashboard accepts them. If the
dashboard is down, punches stay in the local table and are retried on the next
run — nothing is lost.

---

## 2. Prerequisites on the office PC

Check each before continuing:

- **PHP 8.2+** with the **`sockets`** extension enabled. Verify:
  ```powershell
  php -r "echo extension_loaded('sockets') ? 'sockets OK' : 'SOCKETS MISSING';"
  ```
  If missing, uncomment `extension=sockets` in `php.ini` and restart.

- **MySQL running**, and the database from `.env` (`DB_DATABASE`) exists.

- **The PC can reach the device.** This is the most common failure:
  ```powershell
  ping 192.168.1.201
  ```
  If this fails, the sync cannot work no matter what else is configured. The PC
  and the device must be on the same network/VLAN.

- The PC **stays powered on and logged in**. The tasks trigger at logon; if
  nobody logs in, nothing runs.

---

## 3. Deploy the code

```powershell
cd C:\laragon\www\zkteco
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
```

---

## 4. Configure the device

Open the app in a browser → **Devices**.

Add or edit the device so that:

- **IP** matches the device's actual address on the office network (e.g. `192.168.1.201`)
- **Port** is `4370` (or leave blank — it falls back to 4370)
- **Active** is ticked

Click **Test** on the device row. You must see *"Connected successfully."*
**Do not continue until this passes** — everything downstream depends on it.

Then click **Sync Users** so employee names are available to match against punches.

---

## 5. Configure the dashboard push

Go to **Settings** and set:

| Field | Value |
|---|---|
| Dashboard URL | the dashboard's attendance-receiving endpoint |
| Token | the API token issued by the dashboard |
| Source | a name identifying this office (e.g. `Head Office`) |
| Batch size | `200` |
| Timeout | `30` |
| Enabled | ticked |

Click **Test Connection**. You must get an OK response before continuing.

> **Note on the URL:** it must be the actual API endpoint that accepts attendance
> records, not the dashboard homepage. See `docs/dashboard-push-api.md` for the
> payload contract. A bare site root will usually return 200 for the ping test
> while silently discarding real records.

---

## 6. Register the background services

This is the step that makes everything automatic.

Open **PowerShell as Administrator**, then:

```powershell
cd C:\laragon\www\zkteco
powershell -ExecutionPolicy Bypass -File scripts\setup-tasks.ps1
```

The script auto-detects PHP and the project path, and registers two tasks:

- **ZKTeco Scheduler** → `php artisan schedule:work`
- **ZKTeco Queue Worker** → `php artisan queue:work --tries=5 --sleep=3 --max-time=3600`

Both start at logon, restart automatically if they crash, and never time out.
Re-running the script is safe — it replaces the previous registration.

If PHP is not on the PATH, pass it explicitly:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup-tasks.ps1 -Php "C:\laragon\bin\php\php-8.4.14\php.exe"
```

---

## 7. Verify it is actually working

Run these in order. All four should pass.

**a. Both tasks are running**
```powershell
Get-ScheduledTask -TaskName 'ZKTeco*' | Select TaskName, State
```
Expect `Running` for both.

**b. The schedule is registered**
```powershell
php artisan schedule:list
```
Expect both `attendance:sync` and `attendance:push` on `*/5 * * * *`.

**c. A manual sync pulls real data**
```powershell
php artisan attendance:sync
```
Expect something like `Main Entrance: 12 new of 340 log(s)`.
If you get *"Could not reach the device"*, go back to step 4.

**d. Records reach the dashboard**
```powershell
php artisan attendance:push --now
```
Then in **Settings**, confirm **Last success** shows the current time and
**Pending** is `0`.

Finally, punch on the device, wait ~5 minutes, and confirm the record appears
in the app and on the dashboard without anyone clicking Sync.

---

## 8. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `Could not reach the device` | PC can't see the device | `ping` the IP. Check network, device power, and the IP in Devices. |
| Sync works, dashboard stays empty | Queue worker not running | `Get-ScheduledTask -TaskName 'ZKTeco Queue Worker'`. Restart it. |
| Nothing happens on its own, manual works | Scheduler not running | `Get-ScheduledTask -TaskName 'ZKTeco Scheduler'`. Restart it. |
| Pending count keeps climbing | Dashboard rejecting or unreachable | Check **Last error** in Settings. |
| Everything stops after a reboot | Nobody logged in | Tasks trigger at logon. Enable auto-logon, or change the trigger to At startup. |
| `sockets` error | Extension disabled | Enable `extension=sockets` in `php.ini`. |

**Logs:** `storage/logs/laravel.log` — successful syncs and pushes are logged there.

**Watch it live** (useful while setting up):
```powershell
Get-Content storage\logs\laravel.log -Wait -Tail 20
```

**Restart a service:**
```powershell
Stop-ScheduledTask  -TaskName 'ZKTeco Scheduler'
Start-ScheduledTask -TaskName 'ZKTeco Scheduler'
```

**Remove both services:**
```powershell
Unregister-ScheduledTask -TaskName 'ZKTeco Scheduler'    -Confirm:$false
Unregister-ScheduledTask -TaskName 'ZKTeco Queue Worker' -Confirm:$false
```

---

## 9. After changing code or settings

The queue worker holds code in memory, so it must be restarted after a deploy:

```powershell
php artisan config:clear
Stop-ScheduledTask  -TaskName 'ZKTeco Queue Worker'
Start-ScheduledTask -TaskName 'ZKTeco Queue Worker'
```

---

## Reference: commands

| Command | Purpose |
|---|---|
| `php artisan attendance:sync` | Pull punches from all active devices, queue a push. |
| `php artisan attendance:sync --device=1` | Sync one device only. |
| `php artisan attendance:sync --no-push` | Pull only, don't push. |
| `php artisan attendance:push` | Queue a push of everything pending. |
| `php artisan attendance:push --now` | Push immediately in this process (no queue worker needed). |
| `php artisan schedule:list` | Show the scheduled tasks. |

### Tuning

`ZKTECO_PROBE_TIMEOUT_MS` in `.env` (default `2000`) controls how long to wait
for the device to answer a ping before declaring it offline. Raise it on a slow
network. It exists because the ZKTeco library otherwise blocks for 60 seconds
per call on an unreachable device.

To change the sync frequency, edit `routes/console.php` — e.g.
`->everyFiveMinutes()` to `->everyMinute()`.
