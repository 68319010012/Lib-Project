# Library Check-in System — Backend Context

## Scope
Full stack: Flask backend (Database + API) plus the frontend, now integrated into this
same app and served same-origin from `templates/pages/` (see "Frontend integrated" below).

## Stack
- Python + Flask
- MySQL (via XAMPP locally, no hosting yet)
- pandas + openpyxl (Excel import)
- bcrypt (password hashing)
- Windows + VS Code

## Timeline
3 weeks total. Developer is learning Python/Flask/SQL while building (no prior coding background).

## Database Schema (draft — confirm columns against actual Excel file first)

**students** (imported from Excel)
- student_id (PK)
- prefix (คำนำหน้า: นาย/นาง/นางสาว)
- first_name
- last_name
- department (แผนกวิชา)
- level (ระดับชั้น: ปวช./ปวส.)
- year_level (ชั้นปีที่)
- room (ห้องที่)
- semester (ภาคเรียนที่)
- academic_year (ปีการศึกษา)
- gender — NOT stored as a column; derive from `prefix` at query time (นาย=ชาย, นาง/นางสาว=หญิง) to avoid duplicated/out-of-sync data

**users** (login accounts)
- user_id (PK)
- username
- password_hash (bcrypt)
- role (student/admin)
- student_id (FK -> students)
- id_card_photo_path (legacy column, unused since registration no longer requires an ID photo — see "Registration simplified to fully automatic" below)
- account_status (pending/approved) — legacy column; `/register` now always inserts 'approved', so every account is usable immediately

**checkin_logs**
- log_id (PK)
- user_id (FK -> users)
- timestamp
- planned_checkout_at (nullable — set on `type='in'` rows only; NULL means "until closing")
- type (in/out)
- checkout_source (nullable ENUM 'manual'/'auto'/'admin_forced' — set on `type='out'` rows only; NULL on legacy rows from before this column existed)

## Required Features

### 1. Excel Import
Read student list from .xlsx into `students` table via pandas/openpyxl.

### 2. Register
- User submits student_id + username + password
- Account is created and usable immediately (no photo, no admin approval — see
  "Registration simplified to fully automatic" below)
- Password stored via bcrypt hash, never plain text

### 3. Login / Logout
Standard session-based auth. Only approved accounts can log in.

### 4. Check-in / Check-out
API endpoint logs timestamp + type (in/out) per user.

### 5. Admin Dashboard / Reports
- Table view with columns: full name, department/major, year level, student ID, gender, check-in/out time
- Filters: by date / month / academic year
- Dashboard summary view (charts/aggregates)
- Print/export function for reports to send to management

## Open Decisions (flag to user if relevant during implementation)
- Registration approval flow: removed — see "Registration simplified to fully automatic" below
- Excel structure confirmed: NOT a single flat table. The source file has 17 sheets
  (one per department) each containing a printable attendance form repeated per
  classroom, e.g. rows 145-175 = one block: free-text header rows (college name, form
  title, "แผนกวิชา...ระดับชั้น...ชั้นปีที่...ห้องที่...", "ภาคเรียนที่...ปีการศึกษา...ครูที่ปรึกษา...",
  a blank fill-in-date row), then a sub-header row, then student rows (ลำดับที่, รหัสประจำตัว,
  คำนำหน้า, ชื่อ, นามสกุล, ลายมือชื่อ[blank], หมายเหตุ[blank]). One extra sheet "เอกสารพิม" is
  just a table of contents, no student data.
- ลายมือชื่อ (signature) and หมายเหตุ (remark) columns are blank fill-in fields on the paper
  form — not imported.
- Import script DONE: `import_students.py`. Parses all 17 sheets via regex per block,
  dedupes students who appear in multiple terms by keeping the row with the highest
  (academic_year, semester), then upserts into MySQL. Result: 1,619 raw rows → 1,406
  unique students imported into `library_checkin.students` (verified against source).
- Local dev DB: MySQL via XAMPP, host=localhost, user=root, no password, db=library_checkin.
  Change to real credentials before deploying anywhere real.

## Backend API — implemented (app.py, db.py)
- POST /register — student_id + username + password (JSON or form). Validates student_id
  exists in `students`, rejects duplicate username/student, hashes password with bcrypt,
  inserts user with account_status=approved, and logs the caller in immediately (same
  session behavior as /login).
- POST /login — session-based.
- POST /logout — clears session.
- POST /checkin — login required; auto-toggles in/out based on the user's last checkin_logs row.
- GET /me — login required; returns the caller's own account + roster info.
- GET /me/history?limit= — login required; caller's own check-in/out events, newest first.
- POST /profile/change-password — login required; verifies current password, updates hash.
- GET /admin/members?search=&department=&level= — admin-only, lists every registered member
  (all accounts are approved automatically) with an optional last-visit timestamp.
- GET /admin/reports?date=&month=&academic_year= — admin-only, joins checkin_logs+users+students, returns JSON.
- GET /admin/reports/print — HTML template picker page (daily / monthly / department-summary),
  each links to a printable Jinja2 page styled for browser print/save-as-PDF (templates/ folder):
  - /admin/reports/print/daily?date=YYYY-MM-DD — one row per student, check-in + check-out time
  - /admin/reports/print/monthly?month=YYYY-MM — per-student check-in count + last check-in for the month
  - /admin/reports/print/department?academic_year= — per-department student count + total check-ins
  Gender is derived from `prefix` at render time via `gender_from_prefix()`, not stored in DB.
- All manually tested end-to-end via curl: register (auto-logged-in) -> checkin toggles
  in/out -> /me/history reflects it -> logout blocks further checkin -> admin login ->
  /admin/members shows the new account -> reports returns joined rows. All passed.

## Planned checkout + auto checkout — DONE
Students choose how long they'll stay when checking in (`duration_minutes` or
`checkout_time`, clamped to `LIBRARY_CLOSING_TIME` env var, default 17:00) or
leave it unset for "until closing". `POST /checkin/extend` pushes that time
back +N minutes. A `BackgroundScheduler` (APScheduler) job runs every 2
minutes and force-checks-out anyone past their planned time
(`checkout_source='auto'`) — this is the backstop for people who forget to tap
out. "Until closing" check-ins have no auto-checkout time, so admins have
`GET /admin/checkins/current` (everyone currently in, with an `is_overdue`
flag past 6h) and `POST /admin/checkins/force-checkout` as the manual backstop
for those. See `API_CONTRACT.md` for full request/response shapes.

Scheduler startup is guarded against Werkzeug's debug-mode reloader (which
re-executes the whole script in two processes) — see the `if __name__ ==
"__main__":` block at the bottom of `app.py` for why.

## Admin account creation — DONE
No HTTP endpoint creates admin accounts, by design — that would be an attack surface for
privilege escalation. Instead: `create_admin.py`, a local CLI script run directly on the
machine hosting the DB. Prompts for username + password via `getpass` (hidden input, never
logged/echoed), enforces username uniqueness and an 8-char minimum password, then inserts
role=admin/account_status=approved directly via bcrypt hash.

Usage: `python create_admin.py <username>` (then type password when prompted).

The earlier insecure test admin (`admin1` / plaintext password `adminpass123`, seeded
directly into the dev DB during testing and exposed in chat) has been deleted. Run
`create_admin.py` yourself to create a real admin account — the password should only ever
be typed by the person who will use it, never handed to an assistant/script as plaintext.

## Registration simplified to fully automatic
Per user request: no ID-card-photo identity verification, no admin approval step.
`/register` now only takes student_id + username + password, inserts the user
with account_status='approved' directly, and sets the session immediately (the
caller is logged in right after registering, no separate /login call needed).
Removed as a result (no longer reachable since no account is ever pending):
`GET /admin/pending`, `POST /admin/approve/<id>`, `POST /admin/reject/<id>`,
`GET /admin/id-card/<id>`, the `uploads/id_cards/` upload handling in
`/register`, and the corresponding "Pending Approvals" UI in
`templates/pages/members_management.html`. `id_card_photo_path` and
`account_status` remain as unused/legacy columns in `users` — not worth a
schema migration since they're harmless left at their defaults.

## Frontend integrated
The frontend (originally standalone HTML/Tailwind mockups in `../FrontEnd/`, no working
fetch/API calls) has been ported into `templates/pages/` and wired to the real API via
a shared `static/js/api.js` fetch helper, with matching Flask page routes (`/login`,
`/signup`, `/dashboard`, `/admin/dashboard`, `/admin/members-management`,
`/admin/attendance-logs`, `/profile`) in `app.py`. Served same-origin — no CORS needed.
UI elements with no backend behind them (Borrow/Return, Inventory/Catalog nav, book-
borrowed stats, CSV/PDF export, fake analytics) were removed from the ported pages;
admin dashboard charts are computed client-side from `/admin/reports` data instead.
`API_CONTRACT.md` still documents every endpoint for reference.

## Hardening pass — DONE
- `requirements.txt` — pins Flask, mysql-connector-python, bcrypt, pandas, openpyxl,
  python-dotenv, Flask-Limiter, pytest (the packages actually imported by this project).
- Config moved to `.env` (loaded via python-dotenv in db.py/app.py): DB_HOST/USER/PASSWORD/NAME
  and FLASK_SECRET_KEY. Same dev defaults as before (XAMPP root/no password) — just no longer
  hardcoded in source, so a real deployment only needs to edit `.env`. `import_students.py` now
  imports `get_db_connection` from db.py instead of duplicating its own DB_CONFIG.
  Added `.gitignore` (.env, venv/, uploads/, .claude/settings.local.json) since this project
  isn't a git repo yet but will need one eventually.
- `/login` rate-limited to 5 attempts/minute/IP via Flask-Limiter (in-memory storage — fine for
  the single-process dev server; switch to Redis storage before running multiple workers).
  429 responses return JSON (`{"error": "too many login attempts..."}`) via a custom error
  handler, matching every other endpoint's error shape — Flask-Limiter's default 429 is HTML.
- `test_app.py` + `conftest.py` — pytest suite using Flask's test client against the real
  dev DB (not mocks): register validation, duplicate username/student_id, wrong password,
  checkin requires login, admin-only routes, full register->checkin-toggle->reports flow,
  `/me`, `/me/history`, `/profile/change-password`, `/admin/members` search/filter.
  Fixtures create/tear down their own throwaway admin + student accounts each run — DB is
  back to the pre-test state after every run. Rate limiting is disabled during tests
  (`limiter.enabled = False` in the `client` fixture).
