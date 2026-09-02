# ZKTeco Attendance — Dashboard Push API

Integration guide for the **receiving side** (the live dashboard website).

The local attendance app collects punches from ZKTeco fingerprint devices and
**pushes** them to your dashboard over HTTPS. Your job is to expose **one HTTP
endpoint** that accepts the payload described below and answers with a 2xx
status. The local app handles everything else: batching, queuing, retries, and
making sure every record is delivered.

```
ZKTeco device(s) ──LAN──▶ Local app (this project) ──HTTPS POST──▶ Your dashboard endpoint
```

---

## 1. What you need to provide

| Item          | Value                                                                  |
|---------------|------------------------------------------------------------------------|
| Endpoint      | One URL accepting `POST`, e.g. `https://dashboard.example.com/api/attendance` |
| Method        | `POST` (JSON body)                                                     |
| Auth          | Validate the `Authorization: Bearer <token>` header (token is agreed out-of-band and entered in the local app's Settings page) |
| Success reply | Any **2xx** status. The response body is ignored.                      |
| Failure reply | Any non-2xx status (or a dropped/timed-out connection) makes the local app retry the same records later |

The full URL (including path) is configured on the local app's **Settings**
page — there is no fixed path you must use.

---

## 2. Request types

Your endpoint receives two kinds of requests, distinguished by the `ping` flag.

### 2.1 Connection test (ping)

Sent when an operator clicks **Test connection** in the local app. No data is
included; just confirm the URL and token work.

```json
{
  "ping": true,
  "records": []
}
```

Respond with any 2xx (e.g. `200 {"ok": true}`). Do **not** create any data.

### 2.2 Attendance batch

The real payload. Sent automatically after every device sync, after a manual
entry, and by the retry scheduler.

```json
{
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
}
```

Headers on every request:

```
Content-Type: application/json
Accept: application/json
Authorization: Bearer <token>        (only if a token is configured)
```

---

## 3. Field reference

### Top level

| Field     | Type   | Notes                                                                 |
|-----------|--------|-----------------------------------------------------------------------|
| `source`  | string | A free-text label identifying the local installation (configured in Settings). Used for de-duplication (`source` + `local_id`) and logging. **Do not resolve it to a location** — the location comes from each record's `location_id`. |
| `records` | array  | 1 to `batch_size` records (default 200, configurable up to 1000). Ordered oldest punch first. |

### Record object

| Field           | Type            | Notes                                                                        |
|-----------------|-----------------|------------------------------------------------------------------------------|
| `local_id`      | integer         | Primary key of the record in the local app's database. Unique per `source`.  |
| `device_serial` | string \| null  | Serial number of the ZKTeco device. Null if the device info was never read.  |
| `device_name`   | string \| null  | Human-readable device name from the local app (e.g. "Main entrance").        |
| `uid`           | integer         | Device-internal enrollment number. **Not stable** across re-enrollments — do not use it as a person identifier. |
| `userid`        | string          | The employee's ID as enrolled on the fingerprint device. Stored for reference only — **do not use it to identify the employee** on your side; use `admin_id`. |
| `user_name`     | string \| null  | Employee name from the device's user list. Null if the user was enrolled without a name or is no longer on the device. |
| `location_id`   | integer         | **The dashboard's own location id** for this employee (on CRP: `training_locations.id`). Set per user in the local app's **Users** page. **Always present** — records whose user has no `location_id` are never sent. |
| `admin_id`      | integer         | **The dashboard's own user id** for this employee (on CRP: `users.id`). This is the person identifier — join on this, not on `userid`. Set per user in the local app's **Users** page. **Always present** — records whose user has no `admin_id` are never sent. |
| `state`         | integer         | Verification method — see table below.                                       |
| `state_name`    | string          | Human-readable form of `state`.                                              |
| `type`          | integer         | Punch type — see table below.                                                |
| `type_name`     | string          | Human-readable form of `type`.                                               |
| `punched_at`    | string (ISO 8601) | Punch time with UTC offset, e.g. `2026-07-05T09:01:23+06:00`. The offset reflects the local app's configured timezone. |

### `state` values (verification method)

| Value | Meaning                                            |
|-------|-----------------------------------------------------|
| 0     | Password                                            |
| 1     | Fingerprint                                         |
| 2     | Card                                                |
| 255   | **Manual** — entered by hand in the local app, not a device punch |

Unknown values may appear on some firmwares; store them as-is.

### `type` values (punch type)

| Value | Meaning      |
|-------|--------------|
| 0     | Check-in     |
| 1     | Check-out    |
| 2     | Break-out    |
| 3     | Break-in     |
| 4     | Overtime-in  |
| 5     | Overtime-out |

---

## 4. Delivery semantics — read this before implementing

### Identify the employee by `admin_id`, never by `userid`

`userid` is whatever number the employee is enrolled under on the fingerprint
device. It is device-local, can be reassigned, and has no relationship to any id
on your side — it is included for traceability only.

`admin_id` is **your** user id, entered per employee in the local app, and is
the field to join on:

```php
// correct
$adminId = (int) $record['admin_id'];

// wrong - userid is a device enrollment number, not your user id
$adminId = (int) $record['userid'];
```

The same applies to `location_id` versus `source`: `location_id` is your
location's primary key; `source` is only a label.

### Records are withheld until they are fully identified

Every employee in the local app must be given a `location_id` and an `admin_id`
(entered on the local app's **Users** page) before any of their punches are
sent. Punches for a user missing either value are still captured and stored
locally, but they are **not** included in any batch — they stay pending until an
operator fills the values in, and are then delivered on the next push.

Consequences for your endpoint:

- `location_id` and `admin_id` are **always present and non-null** on every
  record you receive; you can treat them as required.
- After an operator fills in a previously-missing user, expect a backlog of that
  user's older punches to arrive at once. As always, use `punched_at`, not
  arrival time.

### At-least-once delivery: you MUST de-duplicate

The local app marks records as pushed only **after** your endpoint replies 2xx.
If the reply is lost (crash, network drop after you processed the batch), the
same records are **sent again** in a later batch. Therefore your endpoint must
be idempotent.

Recommended unique key, either of:

- `source` + `local_id` (simplest), or
- `device_serial` + `userid` + `punched_at` + `type` (matches the local app's own uniqueness rule)

On a duplicate, silently skip the record and still return 2xx.

### All-or-nothing per batch

The local app treats a batch as delivered only on a 2xx. There is no partial
acknowledgement — if some records in a batch are bad, either store the good
ones and skip the bad ones (recommended) or reject the whole batch with a
non-2xx (the entire batch will be retried as-is).

### Retry behavior

When your endpoint is unreachable or returns non-2xx:

1. The push job retries with increasing backoff: **1 min → 3 min → 10 min → 30 min**.
2. Independently, a scheduler on the local app re-queues all unpushed records
   **every 5 minutes** indefinitely.

So after an outage of any length, expect a burst of batches containing the
backlog (oldest first). Batches are sent sequentially, never in parallel, from
a given installation.

### Ordering

Records arrive oldest-first, but after an outage a single request may contain
punches spanning several days. Don't assume "arrived recently" means "punched
recently" — always use `punched_at`.

---

## 5. Response contract

| Your response                    | Local app behavior                                   |
|----------------------------------|------------------------------------------------------|
| `200`–`299`                      | Records marked as pushed. Never sent again.          |
| `400`–`499`                      | Batch retried later (fix the cause; it won't change on its own — e.g. `401` means the token is wrong) |
| `500`–`599`, timeout, DNS error  | Batch retried later automatically                    |

The response **body is never parsed** — return whatever is convenient
(`{"ok":true}`, empty body, anything).

Request timeout: the local app waits up to 30 seconds by default
(configurable 5–300 s). Long-running processing should be done async on your
side — acknowledge fast.

---

## 6. Reference implementation (Laravel example)

```php
// routes/api.php
Route::post('/attendance', function (Request $request) {
    abort_unless(
        hash_equals(config('services.attendance.token'), (string) $request->bearerToken()),
        401
    );

    if ($request->boolean('ping')) {
        return response()->json(['ok' => true]);
    }

    $validated = $request->validate([
        'source' => ['nullable', 'string'],
        'records' => ['required', 'array'],
        'records.*.local_id' => ['required', 'integer'],
        'records.*.userid' => ['required', 'string'],
        'records.*.location_id' => ['required', 'integer'],
        'records.*.admin_id' => ['required', 'integer'],
        'records.*.punched_at' => ['required', 'date'],
        'records.*.type' => ['required', 'integer'],
        'records.*.state' => ['required', 'integer'],
    ]);

    foreach ($validated['records'] as $r) {
        AttendanceRecord::updateOrCreate(
            [
                'source' => $validated['source'] ?? 'default',
                'local_id' => $r['local_id'],
            ],
            [
                'employee_id' => $r['admin_id'],      // your users.id
                'employee_name' => $r['user_name'] ?? null,
                'location_id' => $r['location_id'],   // your locations.id
                'device_userid' => $r['userid'],      // reference only
                'device_serial' => $r['device_serial'] ?? null,
                'punch_type' => $r['type'],
                'verification' => $r['state'],
                'punched_at' => $r['punched_at'],
            ]
        );
    }

    return response()->json(['ok' => true]);
});
```

Equivalent logic in any stack works the same way: authenticate, short-circuit
pings, upsert by unique key, reply 2xx.

## 7. Quick test with curl

Simulate what the local app sends to verify your endpoint before connecting
the real thing:

```bash
curl -X POST https://dashboard.example.com/api/attendance \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "source": "Head office",
    "records": [{
      "local_id": 1,
      "device_serial": "TEST0001",
      "device_name": "Test device",
      "uid": 1,
      "userid": "1001",
      "user_name": "Test User",
      "location_id": 3,
      "admin_id": 7,
      "state": 1,
      "state_name": "Fingerprint",
      "type": 0,
      "type_name": "Check-in",
      "punched_at": "2026-07-05T09:00:00+06:00"
    }]
  }'
```

Then run the same request **twice** and confirm the second call doesn't create
a duplicate — that's the de-duplication requirement from section 4.

---

## 8. Checklist for the dashboard team

- [ ] `POST` endpoint deployed over **HTTPS**
- [ ] Bearer token validated; `401` on mismatch
- [ ] `ping: true` requests answered with 2xx and no side effects
- [ ] Records upserted with a unique key (`source` + `local_id` recommended)
- [ ] `location_id` and `admin_id` stored against each record
- [ ] Employees resolved via `admin_id` (**not** `userid`) and locations via `location_id` (**not** `source`)
- [ ] Location/admin IDs shared with the local app operator so they can be entered per user
- [ ] Duplicate deliveries return 2xx without creating duplicates
- [ ] Responds within 30 seconds (ideally < 2 s)
- [ ] Token + final URL shared with the local app operator (entered in Settings)
