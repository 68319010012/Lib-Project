# API Contract — Library Check-in Backend

Base URL (dev): `http://127.0.0.1:5000`

Auth is **session-cookie based** (Flask's built-in session, signed cookie — not a
JWT/bearer token). The frontend must send requests with `credentials: 'include'`
(fetch) or `withCredentials: true` (axios) so the session cookie round-trips.
The bundled frontend (`templates/pages/`) is served by this same Flask app, so
it's same-origin and doesn't need CORS.

All responses are JSON except the four `/admin/reports/print*` routes, which
return HTML (see bottom section).

Registration is **fully automatic**: no ID photo upload, no admin approval step.
A student who registers is immediately logged in and can use the system.

---

## POST /register
JSON or form body.

**Fields:**
| field | type | required |
|---|---|---|
| `student_id` | string | yes — must exist in the `students` table (imported from the school's Excel roster) |
| `username` | string | yes — must be unique |
| `password` | string | yes — stored as a bcrypt hash, never returned |

**Responses:**
- `201` `{"message": "account created", "role": "student"}` — also sets the session cookie (the caller is logged in immediately, same as `/login`)
- `400` `{"error": "student_id, username, and password are required"}`
- `404` `{"error": "student_id not found"}` — student_id isn't in the imported roster
- `409` `{"error": "username already taken"}`
- `409` `{"error": "this student already has an account"}`

---

## POST /login
JSON or form body. **Rate limited: 5 attempts per minute per IP** — the frontend
should show a "too many attempts, wait a bit" message on 429 rather than
treating it like a normal invalid-credentials error.

**Body:** `{"username": "...", "password": "..."}`

**Responses:**
- `200` `{"message": "logged in", "role": "student" | "admin"}` — sets the session cookie
- `400` `{"error": "username and password are required"}`
- `401` `{"error": "invalid username or password"}`
- `429` `{"error": "too many login attempts, please wait and try again"}`

## POST /logout
No body. Clears the session.
- `200` `{"message": "logged out"}`

---

## POST /checkin
Requires login (any role). No body.

Auto-toggles: looks at the caller's most recent `checkin_logs` row and flips
`in`↔`out` (first ever check-in is always `in`).

**Responses:**
- `200` `{"message": "checked in", "type": "in"}` or `{"message": "checked out", "type": "out"}`
- `401` `{"error": "login required"}` — not logged in / session expired

---

## GET /me
Requires login. No params.

Returns the logged-in user's account + roster info (roster fields are `null`
for accounts with no matching student record):
```json
{
  "user_id": 5, "username": "somchai01", "role": "student", "account_status": "approved",
  "student_id": "68319010012", "prefix": "นาย", "first_name": "...", "last_name": "...",
  "department": "...", "level": "ปวช", "year_level": "1", "room": "1"
}
```

## POST /profile/change-password
Requires login. JSON or form body: `{"current_password": "...", "new_password": "..."}`
(`new_password` must be at least 8 characters).

**Responses:**
- `200` `{"message": "password updated"}`
- `400` `{"error": "current_password and new_password are required"}`
- `400` `{"error": "new_password must be at least 8 characters"}`
- `401` `{"error": "current password is incorrect"}`

## GET /me/history?limit=20
Requires login. `limit` optional (default 20, clamped 1–100).

Returns the caller's own check-in/out events, newest first:
```json
[{"type": "in" | "out", "timestamp": "2026-07-29T10:43:50"}]
```

---

## GET /admin/members
Requires login + `role == admin`. Query params (all optional):

| param | effect |
|---|---|
| `search` | matches first name, last name, student_id, or username (substring) |
| `department` | exact match on department |
| `level` | exact match on level |

Returns every registered member (all accounts are approved automatically):
```json
[
  {
    "user_id": 5, "username": "somchai01",
    "student_id": "68319010012", "prefix": "นาย", "first_name": "...", "last_name": "...",
    "department": "...", "level": "ปวช", "year_level": "1", "room": "1",
    "last_visit": "2026-07-29T10:43:50" | null
  }
]
```
- `403` `{"error": "admin access required"}` if caller isn't an admin

---

## GET /admin/reports
Requires login + admin. Query params (all optional, combine with AND):

| param | format | effect |
|---|---|---|
| `date` | `YYYY-MM-DD` | only that day's logs |
| `month` | `YYYY-MM` | only that month's logs |
| `academic_year` | e.g. `2568` | only students from that academic year |

Returns one row **per check-in/out event** (not per student), newest first:
```json
[
  {
    "student_id": "68319010012",
    "prefix": "นาย", "first_name": "...", "last_name": "...",
    "department": "...", "level": "ปวส", "year_level": "1",
    "type": "in" | "out",
    "timestamp": "2026-07-18T20:41:12"
  }
]
```
Gender is not included here — derive it from `prefix` if needed
(`นาย` → male, `นาง`/`นางสาว` → female), same rule the print templates use.

---

## Printable/HTML report templates (for admin dashboard "print/export")
These render server-side HTML meant to be opened directly in a browser tab
(e.g. via `window.open()` or an `<a target="_blank">` link) — not fetched as
JSON/fetch(). Each has a "Print / Save as PDF" button built in.

- `GET /admin/reports/print` — template picker page (links to the 3 below)
- `GET /admin/reports/print/daily?date=YYYY-MM-DD` — one row per student: name, gender, department, level/year, check-in time, check-out time
- `GET /admin/reports/print/monthly?month=YYYY-MM` — one row per student: check-in count + last check-in for the month
- `GET /admin/reports/print/department?academic_year=2568` — one row per department: distinct student count + total check-ins

All four require an active admin session cookie, so they only work if opened
in a context that already has the cookie (e.g. a same-origin browser tab after
login) — a plain `fetch()` from a different origin without credentials will
get redirected to a JSON 401, not the page.

---

## Error shape (all endpoints)
Every error response is `{"error": "<message>"}` with a matching HTTP status
code (400/401/403/404/409). There is no shared error code enum yet — match on
the HTTP status, not the message string, since messages may still change.
