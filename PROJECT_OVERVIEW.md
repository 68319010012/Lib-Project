# ระบบเช็คชื่อเข้าใช้ห้องสมุด (NNTC Library Check-in System)

เอกสารรวมเนื้อหาโปรเจกต์แบบละเอียด — ไฟล์เดียวจบ ครอบคลุมทุกอย่าง: ภาพรวม, ฟีเจอร์, สถาปัตยกรรม,
โครงสร้างฐานข้อมูลเต็ม, สเปก API ทุก endpoint, หน้าเว็บทั้งหมด, ความปลอดภัย, วิธีติดตั้ง/รัน/deploy
และประวัติการพัฒนา รวมเนื้อหาจากไฟล์เอกสารย่อยทั้งหมดในโปรเจกต์ (`PROJECT_CONTEXT.md`,
`API_CONTRACT.md`, `schema.sql`, `NEW_COMPUTER_SETUP.md`, `DEPLOY.md`, `WORK_SUMMARY_2026-07-30.md`)
ไว้ในที่เดียว

**สารบัญ**
1. [ภาพรวมโปรเจกต์](#1-ภาพรวมโปรเจกต์)
2. [เทคโนโลยีที่ใช้](#2-เทคโนโลยีที่ใช้-tech-stack)
3. [สถาปัตยกรรมระบบ](#3-สถาปัตยกรรมระบบ)
4. [โครงสร้างฐานข้อมูลเต็ม](#4-โครงสร้างฐานข้อมูล-database-schema)
5. [ฟีเจอร์หลัก](#5-ฟีเจอร์หลัก)
6. [สเปก API ทุก endpoint](#6-สเปก-api-แบบละเอียด)
7. [หน้าเว็บฝั่งผู้ใช้](#7-หน้าเว็บฝั่งผู้ใช้-pages)
8. [ความปลอดภัย](#8-ความปลอดภัยที่ทำไว้-hardening)
9. [ติดตั้ง / รัน / Deploy](#9-ติดตั้ง--รัน--deploy-แบบละเอียด)
10. [โครงสร้างไฟล์ในโปรเจกต์](#10-โครงสร้างไฟล์หลักในโปรเจกต์)
11. [ประวัติการพัฒนา](#11-ประวัติการพัฒนา-changelog)
12. [สถานะปัจจุบันและสิ่งที่ควรทำต่อ](#12-สถานะปัจจุบันและสิ่งที่ควรทำต่อ)

---

## 1. ภาพรวมโปรเจกต์

**ชื่อโปรเจกต์ (repo):** Lib-Project
**ชื่อระบบ:** ระบบเช็คชื่อเข้าใช้ห้องสมุด วิทยาลัยเทคโนโลยีนนทบุรี (NNTC)

ระบบเว็บแอปพลิเคชันสำหรับบันทึกเวลาเข้า-ออกห้องสมุดของนักศึกษาแบบดิจิทัล ทดแทนการเซ็นชื่อบนกระดาษ
พร้อมระบบจัดการสมาชิกและรายงานสถิติสำหรับเจ้าหน้าที่/แอดมิน นักศึกษาสมัครสมาชิกด้วยรหัสประจำตัว
นักศึกษาที่มีอยู่ในฐานข้อมูลแล้วเข้าใช้งานได้ทันที ไม่ต้องรออนุมัติ

**ระยะเวลาพัฒนา:** ประมาณ 3 สัปดาห์ ผู้พัฒนาเรียนรู้ Python/Flask/SQL ไปพร้อมกับการสร้างระบบจริง (ไม่มีพื้นฐานเขียนโปรแกรมมาก่อน)

**ผู้พัฒนา:** นักศึกษา วิทยาลัยเทคโนโลยีนนทบุรี (68319010012)

---

## 2. เทคโนโลยีที่ใช้ (Tech Stack)

| ส่วน | เทคโนโลยี |
|---|---|
| Backend | Python 3 + Flask 3.1 |
| ฐานข้อมูล | MySQL (รันผ่าน XAMPP ในเครื่อง dev) |
| การเชื่อมต่อ DB | mysql-connector-python |
| การเข้ารหัสผ่าน | bcrypt |
| นำเข้าข้อมูล Excel | pandas + openpyxl |
| จำกัดอัตราการเรียก (rate limit) | Flask-Limiter |
| งานตามกำหนดเวลาเบื้องหลัง | APScheduler (BackgroundScheduler) |
| ตัวแปรสภาพแวดล้อม | python-dotenv (ไฟล์ `.env`) |
| เทสต์ | pytest (ยิงจริงผ่าน Flask test client ใส่ฐานข้อมูล dev) |
| Frontend | HTML + Tailwind CSS (ผ่าน CDN) + JavaScript (fetch API) — ไม่มี framework SPA, render ฝั่งเซิร์ฟเวอร์ด้วย Jinja2 |
| Session/Auth | Flask session cookie (server-side session, ไม่ใช่ JWT) |
| Environment | Windows + VS Code |

รายการแพ็กเกจ + เวอร์ชันตรงจาก `BackEnd/requirements.txt`:
```
Flask==3.1.3
mysql-connector-python==9.7.0
bcrypt==5.0.0
pandas==2.3.3
openpyxl==3.1.5
python-dotenv==1.2.2
Flask-Limiter==4.1.1
pytest==9.1.1
APScheduler==3.11.3
```

---

## 3. สถาปัตยกรรมระบบ

```
Browser (Jinja2 templates + Tailwind + vanilla JS)
        │  fetch() same-origin, credentials: include
        ▼
Flask app (BackEnd/app.py)
   ├── Page routes  → render_template (templates/pages/*.html)
   ├── JSON API     → session-based auth (@login_required, @admin_required)
   ├── APScheduler  → background job เช็คเอาท์อัตโนมัติทุก 2 นาที
   └── Flask-Limiter → จำกัด /login 5 ครั้ง/นาที/IP
        │
        ▼
MySQL (library_checkin) — students / users / checkin_logs / announcements
```

Frontend เดิมเป็น mockup แยกต่างหาก (โฟลเดอร์ `FrontEnd/`) ไม่มีการเชื่อม API จริง
ภายหลังถูกย้ายเข้ามารวมเป็นส่วนหนึ่งของ Flask app (`templates/pages/`) และเชื่อมกับ API จริงผ่าน
`static/js/api.js` (fetch helper ที่ใช้ร่วมกันทุกหน้า) ทำให้ served แบบ same-origin ไม่ต้องตั้งค่า CORS
องค์ประกอบ UI ที่ไม่มี backend รองรับจริง (ยืม-คืนหนังสือ, แคตตาล็อกหนังสือ, สถิติหนังสือถูกยืม,
export CSV/PDF ปลอม, กราฟ analytics ปลอม) ถูกตัดออกจากหน้าที่ย้ายมาใช้งานจริงแล้ว

Scheduler (APScheduler) มีการป้องกันไม่ให้ทำงานซ้ำสองครั้งตอน Werkzeug debug-mode reloader
รันสคริปต์ซ้ำเป็นสองโปรเซส (ดูใน `if __name__ == "__main__":` ท้ายไฟล์ `app.py`)

---

## 4. โครงสร้างฐานข้อมูล (Database Schema)

ไฟล์ต้นฉบับเต็ม: `BackEnd/schema.sql`

```sql
CREATE TABLE students (
    student_id VARCHAR(20) PRIMARY KEY,
    prefix VARCHAR(20) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    level VARCHAR(10),
    year_level VARCHAR(20),
    room VARCHAR(10),
    semester VARCHAR(10),
    academic_year VARCHAR(10)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') NOT NULL,
    student_id VARCHAR(20),
    id_card_photo_path VARCHAR(255),
    account_status ENUM('pending', 'approved') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES students(student_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE checkin_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    planned_checkout_at DATETIME NULL,
    type ENUM('in', 'out') NOT NULL,
    checkout_source ENUM('manual', 'auto', 'admin_forced') NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Single-row table (id is always 1) holding the one active announcement banner
-- shown on the student dashboard. NULL/empty message means no banner is shown.
CREATE TABLE announcements (
    id INT PRIMARY KEY,
    message TEXT NULL,
    updated_by INT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### หมายเหตุแต่ละตาราง

**`students`** — นำเข้าจากไฟล์ Excel ของวิทยาลัย ไม่มีคอลัมน์เพศ — คำนวณจาก `prefix` ตอน query
(`นาย`→ชาย, `นาง`/`นางสาว`→หญิง) เพื่อไม่ให้มีข้อมูลซ้ำซ้อน/ไม่ตรงกัน

**`users`** — บัญชีล็อกอิน `username` ปัจจุบัน = `student_id` โดยอัตโนมัติ (ผู้ใช้ไม่ได้ตั้งเอง)
คอลัมน์ `id_card_photo_path` และ `account_status` เป็น **legacy** ที่เหลือไว้เฉยๆ ไม่ใช้งานแล้ว
(ดูเหตุผลในหัวข้อ 11 — "ลดขั้นตอนสมัครสมาชิกให้อัตโนมัติเต็มรูปแบบ") ไม่คุ้มที่จะ migrate schema ออก
เพราะไม่มีผลอะไรตอนปล่อยไว้ที่ค่า default

**`checkin_logs`** — `planned_checkout_at` ตั้งเฉพาะแถว `type='in'` (NULL = "จนกว่าจะปิด")
`checkout_source` ตั้งเฉพาะแถว `type='out'` (NULL บนแถวเก่าก่อนมีคอลัมน์นี้)

**`announcements`** — ตารางแถวเดียว (`id` เป็น 1 เสมอ) เก็บประกาศที่กำลังแสดงอยู่หน้าเดียว

---

## 5. ฟีเจอร์หลัก

### 5.1 นำเข้ารายชื่อนักศึกษาจาก Excel
- สคริปต์ `import_students.py` อ่านไฟล์ Excel ต้นฉบับของวิทยาลัย (`Student.xlsx`, ไม่ commit เข้า git เพราะเป็นข้อมูลส่วนตัว)
- ไฟล์จริงมี 17 ชีต (แยกตามแผนกวิชา) แต่ละชีตเป็นแบบฟอร์มเช็คชื่อสำหรับพิมพ์ ซ้ำเป็นบล็อกต่อห้องเรียน
  ไม่ใช่ตารางแบบเรียบ (flat table) เช่น แถว 145-175 คือหนึ่งบล็อก: แถวหัวกระดาษอิสระ (ชื่อวิทยาลัย,
  ชื่อฟอร์ม, "แผนกวิชา...ระดับชั้น...ชั้นปีที่...ห้องที่...", "ภาคเรียนที่...ปีการศึกษา...ครูที่ปรึกษา...",
  แถวเว้นว่างให้กรอกวันที่), ตามด้วยแถวหัวตารางย่อย แล้วค่อยเป็นแถวนักศึกษา (ลำดับที่, รหัสประจำตัว,
  คำนำหน้า, ชื่อ, นามสกุล, ลายมือชื่อ[ว่าง], หมายเหตุ[ว่าง]) — มีอีก 1 ชีตชื่อ "เอกสารพิม" ที่เป็นแค่สารบัญ ไม่มีข้อมูลนักศึกษา
- คอลัมน์ลายมือชื่อ/หมายเหตุ เป็นช่องว่างให้กรอกด้วยมือบนกระดาษ ไม่ได้ import
- สคริปต์ parse แต่ละบล็อกด้วย regex, คัดชื่อซ้ำ (นักศึกษาที่ปรากฏหลายภาคเรียน) โดยเก็บแถวที่มี
  (ปีการศึกษา, ภาคเรียน) ล่าสุด แล้ว upsert เข้า MySQL
- ผลลัพธ์จริง: 1,619 แถวดิบ → 1,406 นักศึกษาที่ไม่ซ้ำกัน (ตรวจสอบกับไฟล์ต้นฉบับแล้ว)

### 5.2 สมัครสมาชิก (Register) — อัตโนมัติเต็มรูปแบบ
- กรอกแค่ **รหัสนักศึกษา + รหัสผ่าน** (username = รหัสนักศึกษาโดยอัตโนมัติ ไม่ต้องตั้งเอง)
- ระบบเช็คว่ารหัสนักศึกษามีอยู่ในตาราง `students` จริง (มาจากไฟล์ Excel ของวิทยาลัย)
- ไม่มีการอัปโหลดรูปบัตรนักศึกษา ไม่มีขั้นตอนรออนุมัติจากแอดมิน — สมัครเสร็จ **ล็อกอินอัตโนมัติทันที**
- รหัสผ่านเก็บเป็น bcrypt hash เท่านั้น ไม่เก็บ plain text

### 5.3 ล็อกอิน / ล็อกเอาต์
- ใช้ session cookie ของ Flask (ไม่ใช่ JWT)
- `/login` จำกัดไว้ **5 ครั้ง/นาที/IP** ป้องกัน brute-force (ตอบกลับ 429 เป็น JSON ให้ตรงรูปแบบเดียวกับ error อื่น)

### 5.4 เช็คอิน / เช็คเอาท์ พร้อมระบบ "ตั้งเวลาจะออก"
- กดปุ่มเดียวสลับ เข้า ⇄ ออก อัตโนมัติ (ดูจากแถวล่าสุดของผู้ใช้คนนั้น; ครั้งแรกสุดเป็น "เข้า" เสมอ)
- ตอนเช็คอิน เลือกได้ว่าจะอยู่กี่นาที (`duration_minutes`) หรือระบุเวลาที่จะออก (`checkout_time`)
  หรือไม่ระบุเลย = "จนกว่าจะปิด" — เวลาทั้งหมดถูกจำกัดไม่ให้เกินเวลาปิดห้องสมุด (`LIBRARY_CLOSING_TIME`, ค่าเริ่มต้น 17:00)
- `POST /checkin/extend` ขอต่อเวลาที่ตั้งไว้ได้ (เช่น +30 นาที)
- **เช็คเอาท์อัตโนมัติ**: งานเบื้องหลัง (APScheduler) รันทุก 2 นาที บังคับเช็คเอาท์ให้คนที่เลยเวลาที่ตั้งไว้
  (`checkout_source='auto'`) — เป็นระบบกันลืมกดออก
- เคส "จนกว่าจะปิด" ไม่มีเวลาให้บังคับอัตโนมัติ จึงมีเมนูแอดมิน "บังคับเช็คเอาท์" (`checkout_source='admin_forced'`)
  รองรับกรณีนี้และกรณีอื่นที่ต้องแทรกแซงด้วยมือ

### 5.5 หน้าโปรไฟล์
- ดูข้อมูลตัวเอง + ประวัติเข้าใช้ย้อนหลัง (`/me`, `/me/history`)
- เปลี่ยนรหัสผ่าน (ต้องยืนยันรหัสเดิมก่อน, รหัสใหม่ขั้นต่ำ 8 ตัวอักษร)

### 5.6 จัดการสมาชิก (แอดมิน)
- ตารางสมาชิกทั้งหมด ค้นหา/กรองตามชื่อ, แผนกวิชา, ระดับชั้น
- ดูสถานะ "กำลังอยู่ในห้องสมุดตอนนี้" แบบเรียลไทม์ พร้อมธง `is_overdue` (ค้างเกิน 6 ชม.) เรียงคนที่อยู่นานสุดก่อน
- บังคับเช็คเอาท์รายบุคคลได้

### 5.7 ประกาศ (Announcement)
- แอดมินตั้งข้อความประกาศเดียว แสดงบนหน้าแดชบอร์ดของนักศึกษาทุกคน (เช่น "ห้องสมุดจะปิดทำการวันเสาร์นี้")
- ส่งข้อความว่าง/เว้นวรรคล้วน = ล้างประกาศ (มีได้แค่ 1 ประกาศที่ active เสมอ ไม่ใช่การเพิ่มรายการใหม่)

### 5.8 รายงานและแดชบอร์ด
รายงาน JSON ดิบ (`GET /admin/reports`) + รายงานแบบพิมพ์/PDF อีก 6 แบบ (server-render HTML, กด "พิมพ์/บันทึกเป็น PDF" ได้ทันที):

| รายงาน | เส้นทาง | เนื้อหา |
|---|---|---|
| รายวัน | `/admin/reports/print/daily?date=YYYY-MM-DD` | 1 แถวต่อนักศึกษา: ชื่อ เพศ แผนก ระดับ/ชั้นปี เวลาเข้า/ออก |
| รายเดือน | `/admin/reports/print/monthly?month=YYYY-MM` | 1 แถวต่อนักศึกษา: จำนวนครั้งที่เข้าใช้ + ครั้งล่าสุดของเดือนนั้น |
| แยกตามแผนก | `/admin/reports/print/department?academic_year=2568` | 1 แถวต่อแผนก: จำนวนนักศึกษาไม่ซ้ำ + ยอดรวมการเข้าใช้ |
| แดชบอร์ดภาพรวม | `/admin/reports/print/dashboard?month=YYYY-MM` | KPI 4 การ์ด (รวมรายการ/นักศึกษาไม่ซ้ำ/เฉลี่ยต่อวัน/วันที่คนมากสุด) + กราฟแนวโน้มรายวันตลอดเดือน + กราฟ top แผนก/ระดับชั้น (CSS ล้วน ไม่พึ่ง JS ภายนอก เพื่อพิมพ์ได้แม้ไม่มีเน็ต) |
| สรุปผู้บริหาร | `/admin/reports/print/executive?month=YYYY-MM` | KPI พร้อม % เทียบเดือนก่อนหน้า + top 3 แผนกที่เข้าใช้มากที่สุด |
| เปรียบเทียบ 2 เดือน | `/admin/reports/print/compare?month_a=&month_b=` | เทียบสถิติของสองเดือนที่เลือกแบบเคียงข้างกัน |

รายงานทุกหน้ารองรับ **export เป็น CSV/XLSX** ผ่าน query param `?format=csv` หรือ `?format=xlsx`
(ไฟล์ export มีชื่อเป็นภาษาไทยตามชื่อรายงาน) นอกเหนือจากพิมพ์/PDF
เพศคำนวณจากคำนำหน้าเสมอ (`นาย`→ชาย, `นาง`/`นางสาว`→หญิง) ไม่เก็บเป็นคอลัมน์แยกในฐานข้อมูล

### 5.9 โหมดมืด/สว่าง (Dark Mode)
- ทุกหน้าที่ผู้ใช้เข้าถึง (login, signup, dashboard, edit_profile, admin_dashboard, members_management,
  attendance_logs) มีปุ่มสลับโหมด — ยกเว้นหน้ารายงานที่พิมพ์ ซึ่งบังคับเป็นสีอ่อนเสมอผ่าน `@media print`
- จำค่าไว้ใน `localStorage`; ถ้ายังไม่เคยตั้งค่า จะใช้ `prefers-color-scheme` ของเครื่องเป็นค่าเริ่มต้น
- มี inline script เล็กๆ ที่หัวหน้าเว็บทุกหน้า ทำงานก่อนวาดผล กัน flash สีตอนโหลด
- โทนสีมืด: พื้นหลัง `#141112`, การ์ด/พาเนล `#1f191b`, เส้นขอบ `#4a3a3c`, ตัวอักษรรอง `#c9b8ba`
  (เข้ากับธีมสีแดงเลือดหมู/ส้มของเว็บหลัก)
- ฟังก์ชันส่วนกลาง `initThemeToggle` อยู่ใน `static/js/api.js` ใช้ร่วมกันทุกหน้า

### 5.10 ภาษา
UI ทั้งหมดเป็นภาษาไทย รวมถึงข้อความ error จาก backend (เช่น "invalid username or password" → "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง")

---

## 6. สเปก API แบบละเอียด

Base URL (dev): `http://127.0.0.1:5000`
Auth เป็น **session-cookie based** (Flask session ที่เซ็นด้วย secret key — ไม่ใช่ JWT/bearer token)
Frontend ต้องส่ง request พร้อม `credentials: 'include'` (fetch) หรือ `withCredentials: true` (axios)
เพื่อให้ session cookie ถูกส่งไปด้วย — เว็บที่แนบมาในโปรเจกต์นี้ served same-origin อยู่แล้ว ไม่ต้องตั้ง CORS

ทุก response เป็น JSON ยกเว้น 4 เส้นทาง `/admin/reports/print*` ที่คืนเป็น HTML
ทุก error ตอบกลับรูปแบบเดียวกันเสมอ: `{"error": "<ข้อความ>"}` พร้อม HTTP status ที่ตรงกัน (400/401/403/404/409)

### POST /register
JSON หรือ form body

| field | type | required |
|---|---|---|
| `student_id` | string | ต้องมีอยู่ในตาราง `students` (นำเข้าจาก Excel) |
| `username` | string | ต้องไม่ซ้ำ |
| `password` | string | เก็บเป็น bcrypt hash |

Responses:
- `201` `{"message": "account created", "role": "student"}` — เซ็ต session cookie ทันที (ล็อกอินอัตโนมัติ)
- `400` `{"error": "student_id, username, and password are required"}`
- `404` `{"error": "student_id not found"}`
- `409` `{"error": "username already taken"}`
- `409` `{"error": "this student already has an account"}`

### POST /login
JSON หรือ form body — **rate limit: 5 ครั้ง/นาที/IP**
Body: `{"username": "...", "password": "..."}`

Responses:
- `200` `{"message": "logged in", "role": "student" | "admin"}` — เซ็ต session cookie
- `400` `{"error": "username and password are required"}`
- `401` `{"error": "invalid username or password"}`
- `429` `{"error": "too many login attempts, please wait and try again"}`

### POST /logout
ไม่มี body — เคลียร์ session
- `200` `{"message": "logged out"}`

### POST /checkin
ต้องล็อกอิน (role ใดก็ได้) Body (optional, อ่านเฉพาะตอนเปลี่ยนสถานะเป็น "in"):

| field | type | ผล |
|---|---|---|
| `duration_minutes` | int | ตั้งใจอยู่กี่นาทีนับจากตอนนี้ |
| `checkout_time` | string `HH:MM` | ตั้งใจออกตอนเวลานี้ของวันนี้ |
| *(ไม่ส่งทั้งคู่)* | — | "จนกว่าจะปิด" — ไม่มีเวลาที่ตั้งไว้ |

ส่งได้แค่หนึ่งใน `duration_minutes`/`checkout_time` ค่าถูก clamp ไม่ให้เกิน `LIBRARY_CLOSING_TIME`
(env var, ค่าเริ่มต้น `17:00`) เสมอ ระบบสลับ in⇄out อัตโนมัติจากแถวล่าสุดของผู้ใช้ (body ถูกละเว้นตอนเปลี่ยนเป็น "out")

Responses:
- `200` `{"message": "checked in", "type": "in", "planned_checkout_at": "...timestamp..." | null}`
  หรือ `{"message": "checked out", "type": "out", "planned_checkout_at": null}`
- `400` ข้อความ error ภาษาไทย เช่น `"duration_minutes ต้องเป็นตัวเลข"` / `"duration_minutes ต้องมากกว่า 0"` /
  `"รูปแบบ checkout_time ต้องเป็น HH:MM"` / `"เวลาที่เลือกต้องอยู่ในอนาคต"`
- `401` `{"error": "login required"}`

### POST /checkin/extend
ต้องล็อกอิน Body: `{"minutes": 30}`
เพิ่มเวลาที่ตั้งไว้ (clamp ไม่เกินเวลาปิดเหมือนกัน) ใช้ได้เฉพาะตอนเช็คอินอยู่และมีเวลาที่ตั้งไว้เท่านั้น
(ไม่ใช่ "จนกว่าจะปิด")

Responses:
- `200` `{"message": "ต่อเวลาสำเร็จ", "planned_checkout_at": "..."}`
- `400` `"minutes ต้องเป็นตัวเลข"` / `"minutes ต้องมากกว่า 0"` / `"ไม่มีสถานะเช็คอินค้างอยู่"` / `"เลือกจนกว่าจะปิดไว้ ไม่มีเวลาให้ต่อ"`

### GET /library-info
ต้องล็อกอิน — คืนเวลาปิดวันนี้ให้ frontend ไม่ต้อง hardcode:
```json
{"closing_time": "17:00"}
```

### GET /me
ต้องล็อกอิน — คืนข้อมูลบัญชี + ข้อมูลนักศึกษา (เป็น `null` ถ้าไม่มี record ตรงกัน):
```json
{
  "user_id": 5, "username": "somchai01", "role": "student", "account_status": "approved",
  "student_id": "68319010012", "prefix": "นาย", "first_name": "...", "last_name": "...",
  "department": "...", "level": "ปวช", "year_level": "1", "room": "1"
}
```

### POST /profile/change-password
ต้องล็อกอิน Body: `{"current_password": "...", "new_password": "..."}` (`new_password` ≥ 8 ตัวอักษร)

Responses:
- `200` `{"message": "password updated"}`
- `400` `{"error": "current_password and new_password are required"}` / `"new_password must be at least 8 characters"`
- `401` `{"error": "current password is incorrect"}`

### GET /me/history?limit=20
ต้องล็อกอิน `limit` เลือกได้ (ค่าเริ่มต้น 20, จำกัดช่วง 1–100) คืนประวัติเข้า/ออกของตัวเอง เรียงใหม่สุดก่อน:
```json
[{"type": "in" | "out", "timestamp": "...", "planned_checkout_at": "..." | null}]
```

### GET /admin/members
ต้องเป็นแอดมิน Query params (optional ทั้งหมด): `search` (ค้นชื่อ/นามสกุล/รหัสนักศึกษา/username),
`department`, `level` — คืนสมาชิกทุกคน (บัญชีทุกอันอนุมัติอัตโนมัติอยู่แล้ว):
```json
[{
  "user_id": 5, "username": "somchai01",
  "student_id": "68319010012", "prefix": "นาย", "first_name": "...", "last_name": "...",
  "department": "...", "level": "ปวช", "year_level": "1", "room": "1",
  "last_visit": "..." | null
}]
```
`403` `{"error": "admin access required"}` ถ้าไม่ใช่แอดมิน

### GET /admin/checkins/current
ต้องเป็นแอดมิน — คืนทุกคนที่กำลังเช็คอินอยู่ (แถวล่าสุดของ user เป็น `type='in'`):
```json
[{
  "user_id": 5, "student_id": "68319010012", "prefix": "นาย", "first_name": "...", "last_name": "...",
  "department": "...", "level": "ปวช", "year_level": "1",
  "checked_in_at": "...", "planned_checkout_at": "..." | null,
  "duration_minutes": 76, "is_overdue": false
}]
```
`is_overdue` เป็น `true` เมื่อ `duration_minutes >= 360` (6 ชม.) ไม่ว่าจะตั้งเวลาไว้หรือไม่ เรียงคนอยู่นานสุดก่อน

### POST /admin/checkins/force-checkout
ต้องเป็นแอดมิน Body: `{"user_id": 5}` — เช็คเอาท์ทันที (`checkout_source='admin_forced'`)

Responses:
- `200` `{"message": "บังคับเช็คเอาท์สำเร็จ"}`
- `400` `{"error": "user_id ไม่ถูกต้อง"}` / `"ผู้ใช้นี้ไม่ได้ค้างสถานะเช็คอินอยู่"`

### Auto checkout (background job)
ไม่ใช่ HTTP endpoint — เป็นงานตามกำหนดเวลา (APScheduler, ทุก 2 นาที) เช็คเอาท์ให้อัตโนมัติ
คนที่ `planned_checkout_at` เลยเวลาไปแล้ว (`checkout_source='auto'`) ทำงานตราบเท่าที่โปรเซส Flask ยังรันอยู่
เคส "จนกว่าจะปิด" (`planned_checkout_at IS NULL`) จะไม่ถูกแตะโดย job นี้ — ต้องใช้
`/admin/checkins/force-checkout` หรือให้นักศึกษาเช็คเอาท์เองเท่านั้น

### GET /announcement
ต้องล็อกอิน (role ใดก็ได้) — คืนประกาศปัจจุบัน หรือ `null` ถ้าไม่มี:
```json
{"message": "ห้องสมุดจะปิดทำการวันเสาร์นี้" | null, "updated_at": "..." | null}
```

### POST /admin/announcement
ต้องเป็นแอดมิน Body: `{"message": "..."}` — ตั้งประกาศเดียวที่แสดงทุกหน้านักศึกษา
ข้อความว่าง/เว้นวรรคล้วน = ล้างประกาศ (มีได้แค่ 1 ประกาศ active เสมอ)
- `200` `{"message": "บันทึกประกาศสำเร็จ"}`

### GET /admin/reports
ต้องเป็นแอดมิน Query params (optional, รวมกันแบบ AND): `date` (`YYYY-MM-DD`), `month` (`YYYY-MM`),
`academic_year` (เช่น `2568`) — คืนหนึ่งแถวต่อ**เหตุการณ์เช็คอิน/เช็คเอาท์** (ไม่ใช่ต่อคน) เรียงใหม่สุดก่อน:
```json
[{
  "student_id": "68319010012",
  "prefix": "นาย", "first_name": "...", "last_name": "...",
  "department": "...", "level": "ปวส", "year_level": "1",
  "type": "in" | "out", "timestamp": "..."
}]
```
ไม่มีเพศในผลลัพธ์นี้ — คำนวณจาก `prefix` เอง

### Printable/HTML report templates
render เป็น HTML ฝั่งเซิร์ฟเวอร์ สำหรับเปิดในแท็บเบราว์เซอร์โดยตรง (เช่น `window.open()` หรือ
`<a target="_blank">`) — ไม่ใช่เรียกผ่าน fetch/JSON แต่ละหน้ามีปุ่ม "พิมพ์ / บันทึกเป็น PDF" ในตัว:

- `GET /admin/reports/print` — หน้าเลือกเทมเพลตรายงาน (ลิงก์ไปทั้ง 6 แบบด้านล่าง)
- `GET /admin/reports/print/daily?date=YYYY-MM-DD`
- `GET /admin/reports/print/monthly?month=YYYY-MM`
- `GET /admin/reports/print/department?academic_year=2568`
- `GET /admin/reports/print/dashboard?month=YYYY-MM`
- `GET /admin/reports/print/executive?month=YYYY-MM`
- `GET /admin/reports/print/compare?month_a=YYYY-MM&month_b=YYYY-MM`

ทั้งหมดต้องมี session cookie ของแอดมิน (เปิดจากแท็บเบราว์เซอร์ same-origin ที่ล็อกอินไว้แล้วเท่านั้น
— เรียก `fetch()` ข้าม origin โดยไม่ส่ง credentials จะโดน redirect ไปที่ JSON 401 แทนหน้า HTML)
ทุกหน้ารายงานรองรับ `?format=csv` หรือ `?format=xlsx` เพื่อ export แทนการ render HTML

---

## 7. หน้าเว็บฝั่งผู้ใช้ (Pages)

อยู่ที่ `BackEnd/templates/pages/`:

| หน้า | เส้นทาง | ผู้ใช้ |
|---|---|---|
| เข้าสู่ระบบ | `/login` | ทุกคน (มีแท็บนักศึกษา/แอดมิน — แท็บแอดมินซ่อนลิงก์สมัครสมาชิก) |
| สมัครสมาชิก | `/signup` | นักศึกษา |
| แดชบอร์ดนักศึกษา | `/dashboard` | นักศึกษา — ปุ่มเช็คอิน/เช็คเอาท์, ประวัติ, ประกาศ |
| แก้ไขโปรไฟล์ | `/profile` | นักศึกษา |
| แดชบอร์ดแอดมิน | `/admin/dashboard` | แอดมิน — สถิติ/กราฟ (คำนวณฝั่ง client จากข้อมูล `/admin/reports`) |
| จัดการสมาชิก | `/admin/members-management` | แอดมิน |
| ประวัติการเข้าใช้ทั้งหมด | `/admin/attendance-logs` | แอดมิน |

โฟลเดอร์ `FrontEnd/` เก็บ mockup ต้นฉบับ (ก่อนเชื่อม API จริง) ไว้เป็นข้อมูลอ้างอิง/ภาพหน้าจอเปรียบเทียบ

---

## 8. ความปลอดภัยที่ทำไว้ (Hardening)

- รหัสผ่านทุกบัญชีเก็บเป็น **bcrypt hash** เท่านั้น ไม่มีที่ใดเก็บ plain text
- ตัวแปรลับ (DB credentials, secret key) ย้ายไปอยู่ใน `.env` (ไม่ commit เข้า git) แทนการฝังในซอร์สโค้ด
  — `import_students.py` เองก็ import `get_db_connection` จาก `db.py` แทนที่จะประกาศ config ซ้ำ
- `.gitignore` กัน `.env`, `venv/`, `uploads/`, `.claude/settings.local.json`, ไฟล์รายชื่อนักเรียนจริง
  (`Student.xlsx`) ไม่ให้หลุดขึ้น git
- `/login` จำกัดอัตราการลองผิด (rate limiting, in-memory storage — พอสำหรับ dev server โปรเซสเดียว
  ควรเปลี่ยนเป็น Redis ก่อนรันหลาย worker) กัน brute-force
- **ไม่มี endpoint สร้างบัญชีแอดมินผ่านเว็บ** โดยตั้งใจ (กันเป็นช่องโหว่ privilege escalation) —
  ต้องรัน `create_admin.py` บนเครื่อง server เท่านั้น รับรหัสผ่านผ่าน `getpass` (ไม่ echo/ไม่ log)
  บังคับ username ไม่ซ้ำและรหัสผ่านขั้นต่ำ 8 ตัวอักษร
- ทดสอบทุก endpoint หลักด้วย pytest บนฐานข้อมูลจริง (ไม่ mock) เพื่อกันเคสที่ mock กับของจริงไม่ตรงกัน
  (`test_app.py` + `conftest.py`) — fixture สร้าง/ลบบัญชีทดสอบของตัวเองทุกครั้งที่รัน ฐานข้อมูลกลับสู่
  สถานะเดิมเสมอหลังรันเทส และปิด rate limiting ระหว่างเทส

---

## 9. ติดตั้ง / รัน / Deploy แบบละเอียด

### 9.1 ตั้งค่าเครื่องใหม่เพื่อพัฒนาต่อ (เช่น คอมเพื่อน)

ใช้ตอนอยาก **แก้ไข/เขียนโค้ดต่อ** (ถ้าแค่เปิดดูเว็บที่ deploy ไว้แล้ว ใช้ URL จริงได้เลย ไม่ต้องทำตามนี้)
สิ่งที่ต้องมี: **Python 3.10+** และ **MySQL** (ผ่าน XAMPP ก็ได้) — เพราะทดสอบโค้ดต้องมีฐานข้อมูลเชื่อมต่อเสมอ

1. **Clone โค้ด**
   ```bash
   git clone https://github.com/68319010012/Lib-Project.git
   cd Lib-Project/BackEnd
   ```
2. **ติดตั้ง Python packages**
   ```bash
   python -m venv venv
   venv\Scripts\activate        # Mac/Linux: source venv/bin/activate
   pip install -r requirements.txt
   ```
3. **ติดตั้ง XAMPP + สร้างฐานข้อมูล**
   - โหลด XAMPP จาก https://www.apachefriends.org/ เปิด **MySQL** ใน Control Panel (ไม่ต้องเปิด Apache)
   - เปิด phpMyAdmin (`http://localhost/phpmyadmin`) → สร้างฐานข้อมูลชื่อ `library_checkin`
   - แท็บ **Import** ในฐานข้อมูลนั้น → เลือกไฟล์ `schema.sql` จากโฟลเดอร์ `BackEnd` (โครงตารางเปล่า ไม่มีข้อมูลนักเรียน)
4. **สร้างไฟล์ `.env`** ในโฟลเดอร์ `BackEnd` (ไม่ commit เข้า git เพราะมีรหัสผ่าน):
   ```
   DB_HOST=localhost
   DB_USER=root
   DB_PASSWORD=
   DB_NAME=library_checkin

   FLASK_SECRET_KEY=<สุ่มมาสัก 32 ตัวอักษร>

   LIBRARY_CLOSING_TIME=17:00
   ```
   สุ่ม `FLASK_SECRET_KEY` ด้วย `python -c "import secrets; print(secrets.token_hex(32))"`
   `LIBRARY_CLOSING_TIME` ไม่ใส่ก็ได้ (ค่า default คือ 17:00) — เป็นเพดานเวลาที่นักศึกษาตั้งใจจะออกตอนเช็คอิน
   และเป็นเวลาที่ระบบเช็คเอาท์อัตโนมัติใช้อ้างอิง
5. **นำเข้ารายชื่อนักเรียน** (ถ้ามีไฟล์ Excel จริง — ไฟล์นี้ไม่ commit เข้า git เพราะเป็นข้อมูลส่วนตัว
   ต้องส่งให้กันทางอื่น เช่น ไดรฟ์ส่วนตัว) วางไว้ที่ `BackEnd/Student.xlsx` แล้วรัน:
   ```bash
   python import_students.py
   ```
   ถ้ายังไม่มีไฟล์จริง ข้ามได้ก่อน — สมัครบัญชีทดสอบเองก็ได้ (แต่ต้องมี student_id ที่อยู่ในตาราง `students` แล้ว)
6. **รันเซิร์ฟเวอร์**
   ```bash
   python app.py
   ```
   เปิด `http://127.0.0.1:5000` (debug mode เปิดอยู่ แก้โค้ดแล้ว refresh เห็นผลทันที)
7. **สร้างบัญชีแอดมิน** (แนะนำ)
   ```bash
   python create_admin.py <username>
   ```

### 9.2 Workflow git ประจำวัน (ทำซ้ำทุกครั้งที่แก้โค้ด)

```
[เครื่องคุณ]  ←→  [GitHub (Lib-Project)]  ←→  [เครื่องเพื่อน]
```
วนซ้ำทุกครั้ง:
1. เปิดคอม → `git pull` (รับงานล่าสุดของอีกฝั่งก่อนเสมอ)
2. แก้โค้ด ทดสอบด้วย `python app.py`
3. แก้เสร็จ → `git add -A` → `git commit -m "..."` → `git push`
4. เช็คสถานะได้ตลอดด้วย `git status`

**กฎทอง**: ปิดงานทุกครั้งด้วย `push`, เปิดงานทุกครั้งด้วย `pull`
ถ้า push แล้วเจอ "rejected"/"fetch first" แปลว่าอีกฝั่ง push ไปก่อนแล้ว — แก้ด้วย `git pull` แล้วค่อย `git push` อีกครั้ง

### 9.3 Deploy ขึ้น PythonAnywhere (แผนฟรี)

เป้าหมาย: ได้ URL ถาวรแบบ `https://ชื่อคุณ.pythonanywhere.com` ไม่ต้องเปิดคอมทิ้งไว้/รัน XAMPP อีก
ฐานข้อมูล production เริ่มจากค่าว่างเปล่า พอมีข้อมูลจริงค่อย import ทีหลัง

1. สมัครแผน **Beginner (Free)** ที่ pythonanywhere.com (ไม่ต้องผูกบัตร) — จด username ไว้แทน `YOURNAME` ทุกจุดด้านล่าง
2. **อัปโหลดโค้ด** (ไม่ต้องใช้ git ก็ได้): zip โฟลเดอร์ `BackEnd` (ยกเว้น `venv/`) → อัปโหลดผ่านแท็บ **Files** →
   เปิด **Bash console** แล้ว `unzip BackEnd.zip`
3. **ติดตั้ง packages**:
   ```bash
   mkvirtualenv --python=python3.10 libraryenv
   cd BackEnd
   pip install -r requirements.txt
   ```
   (ครั้งหน้าเปิด console ใหม่ พิมพ์ `workon libraryenv` กลับเข้า virtualenv)
4. **สร้างฐานข้อมูล MySQL**: แท็บ **Databases** → ตั้งรหัสผ่าน → สร้างฐานข้อมูล `library_checkin`
   (ชื่อจริงจะกลายเป็น `YOURNAME$library_checkin`) แล้ว import โครงสร้าง:
   ```bash
   mysql -u YOURNAME -h YOURNAME.mysql.pythonanywhere-services.com -p "YOURNAME\$library_checkin" < schema.sql
   ```
5. **ตั้งค่า `.env`** (`nano .env` ใน `~/BackEnd`):
   ```
   DB_HOST=YOURNAME.mysql.pythonanywhere-services.com
   DB_USER=YOURNAME
   DB_PASSWORD=<รหัสผ่าน MySQL ที่ตั้งไว้>
   DB_NAME=YOURNAME$library_checkin

   FLASK_SECRET_KEY=<สุ่มใหม่ ห้ามใช้ค่า dev เดิม>
   ```
6. **ตั้งค่า Web App**: แท็บ **Web** → Add a new web app → **Manual configuration** → Python 3.10
   - Source code: `/home/YOURNAME/BackEnd`
   - Virtualenv: `/home/YOURNAME/.virtualenvs/libraryenv`
   - WSGI configuration file → แทนที่เนื้อหาทั้งหมดด้วย:
     ```python
     import sys
     path = '/home/YOURNAME/BackEnd'
     if path not in sys.path:
         sys.path.append(path)

     from app import app as application
     ```
   - Static files: URL `/static/` → Directory `/home/YOURNAME/BackEnd/static/`
   - กด **Reload**
7. **สร้างบัญชีแอดมินจริง**:
   ```bash
   workon libraryenv
   cd ~/BackEnd
   python create_admin.py <username>
   ```
8. เสร็จแล้ว — เปิด `https://YOURNAME.pythonanywhere.com` ได้เลย

**พอพร้อมข้อมูลนักเรียนจริงจาก Excel**: อัปโหลดไฟล์ไปแทนที่ `~/BackEnd/Student.xlsx` แล้วรัน
`python import_students.py` อีกครั้งใน virtualenv — นักเรียนสมัครบัญชีเองได้ทันที ไม่ต้องรออนุมัติ

**ข้อจำกัดแผนฟรี**: มีโควตา CPU seconds ต่อวัน (สำหรับโปรเจกต์ในวิทยาลัยเพียงพอ) และต้องล็อกอินเข้า
PythonAnywhere อย่างน้อยทุก 3 เดือน ไม่งั้นเว็บแอปจะถูกปิดอัตโนมัติ

---

## 10. โครงสร้างไฟล์หลักในโปรเจกต์

```
BackEnd/
├── app.py                    # Flask app หลัก — routes ทั้งหมด (page + API)
├── db.py                     # การเชื่อมต่อ MySQL
├── create_admin.py           # CLI สร้างบัญชีแอดมิน (รันจาก server เท่านั้น)
├── import_students.py        # นำเข้ารายชื่อนักศึกษาจาก Excel
├── schema.sql                # โครงสร้างตารางฐานข้อมูล (เปล่า ไม่มีข้อมูลจริง)
├── requirements.txt          # รายการ Python packages
├── test_app.py / conftest.py # ชุดทดสอบ pytest
├── API_CONTRACT.md           # สเปก API แบบละเอียดทุก endpoint (ต้นฉบับของหัวข้อ 6 ในไฟล์นี้)
├── PROJECT_CONTEXT.md         # บันทึกบริบท/ประวัติการพัฒนาแบบ dev log
├── NEW_COMPUTER_SETUP.md     # คู่มือ setup เครื่องใหม่ + workflow git (ต้นฉบับของหัวข้อ 9.1-9.2)
├── DEPLOY.md                  # คู่มือ deploy (ต้นฉบับของหัวข้อ 9.3)
├── static/js/api.js          # fetch helper กลาง + theme toggle ใช้ร่วมทุกหน้า
└── templates/
    ├── pages/                # หน้าเว็บหลักที่ผู้ใช้เข้าถึง (login, dashboard, ...)
    ├── base_print.html       # เทมเพลตแม่ของหน้ารายงานที่พิมพ์ได้ทั้งหมด
    ├── reports_select.html   # หน้าเลือกประเภทรายงาน
    └── report_*.html         # รายงาน 6 แบบ (daily/monthly/department/dashboard/executive/compare)

FrontEnd/                     # Mockup ต้นฉบับ (ก่อนเชื่อม API จริง) + ภาพหน้าจอ
```

---

## 11. ประวัติการพัฒนา (Changelog)

เรียงตามลำดับที่ทำจริง สรุปจาก dev log ในโปรเจกต์:

1. **Excel import** — วิเคราะห์โครงสร้างไฟล์ Excel จริง (17 ชีตแบบฟอร์มพิมพ์ ไม่ใช่ตารางเรียบ) เขียน
   `import_students.py` แยกบล็อกด้วย regex, คัดชื่อซ้ำ, upsert เข้า MySQL (1,619 แถวดิบ → 1,406 นักศึกษา)
2. **Backend API หลัก** — register/login/logout, checkin แบบ auto-toggle, `/me`, `/me/history`,
   เปลี่ยนรหัสผ่าน, `/admin/members`, `/admin/reports` (+ 3 หน้ารายงานพิมพ์ daily/monthly/department)
   ทดสอบ end-to-end ด้วย curl ครบทุก flow
3. **ระบบเช็คอินแบบตั้งเวลา + เช็คเอาท์อัตโนมัติ** — เพิ่ม `duration_minutes`/`checkout_time`,
   `POST /checkin/extend`, background job (APScheduler ทุก 2 นาที) และเมนูแอดมิน
   `/admin/checkins/current` + `/admin/checkins/force-checkout` เป็นตัวสำรองสำหรับเคส "จนกว่าจะปิด"
4. **สร้างบัญชีแอดมินแบบปลอดภัย** — ตัด endpoint สร้างแอดมินผ่านเว็บออก เปลี่ยนเป็น `create_admin.py`
   สคริปต์ CLI รันจาก server เท่านั้น (กัน privilege escalation) ลบบัญชีทดสอบที่เคยสร้างแบบไม่ปลอดภัยทิ้ง
5. **ลดขั้นตอนสมัครสมาชิกให้อัตโนมัติเต็มรูปแบบ** — ตัดการอัปโหลดรูปบัตรนักศึกษาและขั้นตอนรออนุมัติจาก
   แอดมินออกทั้งหมด (`/register` ใส่ `account_status='approved'` ตรงและล็อกอินทันที) ทำให้ endpoint
   ที่เกี่ยวกับ pending account ทั้งหมดไม่ถูกใช้งานอีก (`/admin/pending`, `/admin/approve/<id>`, ฯลฯ) — ถูกลบออก
6. **รวม Frontend เข้ากับ Backend** — ย้าย mockup เดิม (`FrontEnd/`, ไม่มี fetch จริง) เข้ามาเป็น
   `templates/pages/` ผูกกับ API จริงผ่าน `static/js/api.js`, เพิ่ม Flask page routes ให้ served
   same-origin ตัด UI ที่ไม่มี backend จริงรองรับออก (ยืม-คืนหนังสือ, แคตตาล็อก, export ปลอม ฯลฯ)
7. **Hardening pass** — ปักหมุดเวอร์ชัน `requirements.txt`, ย้าย config ไป `.env` (python-dotenv),
   เพิ่ม `.gitignore`, rate-limit `/login` (Flask-Limiter, 5 ครั้ง/นาที/IP), เพิ่มชุดทดสอบ pytest
   ยิงจริงกับฐานข้อมูล dev (ไม่ mock)
8. **Thai-ify + ฟีเจอร์เพิ่มเติม (30 ก.ค. 2569)**:
   - แปล UI ทุกหน้า (login, signup, dashboard, admin_dashboard, members_management, attendance_logs,
     edit_profile) และข้อความ error จาก backend เป็นภาษาไทยทั้งหมด
   - เปลี่ยนชื่อแผนกวิชา "คอมพิวเตอร์" → "เทคโนโลยีธุรกิจดิจิทัล" (132 นักศึกษา) พร้อมอัปเดต dropdown สมัครสมาชิก
   - ซ่อนลิงก์ "สมัครสมาชิก" อัตโนมัติเมื่อเลือกแท็บ "แอดมิน" ในหน้า login
   - ตัดช่อง "เลือก Username" ออกจากฟอร์มสมัครสมาชิก — ใช้รหัสนักศึกษาเป็น username โดยอัตโนมัติแทน
   - สร้างบัญชีแอดมินจริงผ่าน `create_admin.py`
   - เพิ่มรายงานแบบแดชบอร์ด (`report_dashboard.html`) — KPI 4 การ์ด + กราฟแนวโน้ม/แผนก ด้วย CSS ล้วน
   - ออกแบบหน้าเลือกเทมเพลตรายงานใหม่ (`reports_select.html`) พร้อมแก้บั๊ก stacking-context ที่ทำให้การ์ด
     hover โผล่ทับ header (แก้โดยเปลี่ยนพื้นหลังลวดลายจาก pseudo-element เป็น layered `background-image`)
   - ปรับปรุง `base_print.html` ให้ responsive มี viewport meta, header แบรนด์, ตาราง zebra-stripe,
     scroll แนวนอนบนจอเล็ก — มีผลกับรายงานทุกแบบที่ extends จากเทมเพลตนี้
   - เพิ่มโหมดมืด/สว่างทั้งเว็บ (ยกเว้นหน้ารายงานพิมพ์) พร้อม anti-flash script และจำค่าใน `localStorage`
9. **แก้บั๊ก contrast โหมดมืด** — ปรับโทนสีมืดให้อ่านง่ายขึ้น (คอมมิตล่าสุด)
10. **เพิ่มรายงานสรุปผู้บริหารและเปรียบเทียบเดือน** — เพิ่ม `report_executive.html` (KPI พร้อม %
    เทียบเดือนก่อน + top 3 แผนก) และ `report_compare.html` (เทียบสถิติ 2 เดือนเคียงข้างกัน) พร้อม
    export CSV/XLSX ให้ทุกหน้ารายงาน

---

## 12. สถานะปัจจุบันและสิ่งที่ควรทำต่อ

- ระบบพร้อมใช้งานครบทุกฟีเจอร์หลัก ผ่านการทดสอบ manual (curl) และ pytest แล้ว
- ควรเปลี่ยนรหัสผ่านบัญชีแอดมินตัวแรกที่เคยสร้างไว้ (รหัสผ่านเดิมเคยพิมพ์ในแชท ไม่ปลอดภัย 100%)
- ยังรันบน dev server ของ Flask (`python app.py`) — ก่อน deploy จริงระยะยาวควรใช้ WSGI server
  (เช่น gunicorn/waitress) และเปลี่ยน rate-limit storage จาก in-memory เป็น Redis หากมีหลาย worker process
- ไฟล์ `Student.xlsx` และ `.env` ไม่ถูก track ใน git โดยตั้งใจ (ข้อมูลส่วนตัว/ความลับ) ต้องส่งต่อกันทางอื่นเวลาย้ายเครื่อง
- ไฟล์ `BackEnd.zip` ที่ค้างอยู่ในโฟลเดอร์โปรเจกต์ (ไม่ track ใน git) ลบทิ้งได้ถ้าไม่ใช้แล้ว
