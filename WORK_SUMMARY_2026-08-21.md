# สรุปงาน — 21 สิงหาคม 2569

บันทึกงานที่ทำในเซสชันนี้ แยกตามรอบที่สั่ง ทุกหัวข้อระบุ **อาการ/คำขอ → สาเหตุ → สิ่งที่แก้ → ไฟล์ → สถานะการทดสอบ** เพื่อให้ตามรอยย้อนกลับได้

ต่อยอดจาก commit ล่าสุด `c4b1591` (Restore the dashboard report to a single A4 page)

---

## สถานะ ณ ตอนปิดเซสชัน

| หัวข้อ | สถานะ |
|---|---|
| จำนวนไฟล์ที่แก้ | 22 ไฟล์ (+992 / −239 บรรทัด) |
| commit แล้วหรือยัง | **ยังไม่ commit** ทั้งหมดอยู่ใน working tree |
| `php -l` | ผ่านทุกไฟล์ PHP ที่แก้ |
| `node --check` | ผ่านทุกไฟล์ JS ที่แก้ |
| line ending | คงของเดิมทุกไฟล์ (ส่วนใหญ่ CRLF, บางไฟล์ LF) |
| `tests/smoke_test.php` | **ยังรันไม่ได้** — MariaDB ในเครื่องล่ม |
| ตรวจด้วยตาในเบราว์เซอร์ | **ยังไม่ได้ทำเลย** — เปิดแอปไม่ได้เพราะ DB ล่ม |

### ปัญหาเครื่องมือที่ยังค้าง (ต้องสิทธิ์ Administrator)

MariaDB 10.4.32 ของ XAMPP crash ระหว่างเซสชันนี้ (เขียน `C:\xampp\mysql\data\mysqld.dmp`) เหลือ process ค้าง 2 ตัวที่ `taskkill /F` ไม่ผ่าน — Access denied ทั้งในและนอก sandbox พอร์ต 3306 ไม่มี listener ตัวใหม่จึง bind data dir ไม่ได้

```
taskkill /F /PID 7832
taskkill /F /PID 15300
```

หรือเปิด XAMPP Control Panel แบบ Run as administrator แล้ว Stop/Start MySQL พอสตาร์ตใหม่ InnoDB จะทำ crash recovery ให้เอง

**ลำดับเหตุการณ์ที่ทำให้ล่ม** (บันทึกไว้กันซ้ำ): Docker daemon ไม่ได้รัน + ไม่มี PHP ใน PATH จึงหันไปใช้ XAMPP → `php -S` ค้างเพราะ Start-Process แบบ hidden ไม่ redirect stderr ทำให้ log ไม่มีที่ระบาย → kill process smoke test ที่ค้าง ทิ้ง MySQL connection ค้างถือ lock บนตาราง `users` → ทุก request ติด lock wait 50 วินาทีที่ `retire_expired_accounts_sweep()` (`public/index.php:46`) → สั่ง `KILL <thread_id>` ปลด lock → MariaDB crash

**บทเรียน:** อย่า kill process ที่คุยกับ MySQL อยู่กลางคัน และเวลาสตาร์ต `php -S` แบบ background บน Windows ต้อง redirect stdout/stderr ออกไฟล์เสมอ

### แถวตกค้างจาก smoke test

`cleanup()` ไม่ได้รันเพราะ process ถูก kill กลางทาง อาจมีบัญชีทดสอบ prefix `smk` ค้าง — พอ DB กลับมาให้เช็ค:

```sql
SELECT user_id, username, role FROM users WHERE username LIKE 'smk%';
```

---

## รอบที่ 1 — บั๊ก: หน้าแดชบอร์ดนักศึกษาไม่รีเฟรชสถานะเช็คอิน

**อาการ:** เมื่อ `auto_checkout_sweep()` ปิด visit ฝั่งเซิร์ฟเวอร์แล้ว หน้าจอที่เปิดค้างไว้ยังโชว์ "อยู่ในห้องสมุด" และนับเวลาต่อไปเรื่อยๆ ไม่มีอะไรบอกแท็บนั้นว่าสถานะเปลี่ยนแล้ว

**สิ่งที่แก้ — `public/assets/js/dashboard.js`**

1. **Interval รีเฟรชทุก 60 วินาที** — ตัวแปรใหม่ `historyRefreshId` + ค่าคงที่ `HISTORY_REFRESH_MS = 60000` แยกจาก `elapsedTimerId` (1 วินาที) และ `reminderWatcherId` (30 วินาที) เดิมโดยสิ้นเชิง ไม่ไปชนกับ `restartElapsedTimer()`
2. **`visibilitychange`** — เรียกรีเฟรชทันทีเมื่อ `document.visibilityState === 'visible'` เคสที่พบบ่อยสุดไม่ใช่แท็บเดสก์ท็อปที่เปิดทิ้ง แต่เป็นมือถือที่ล็อกหน้าจอ/สลับแอปไปนาน ซึ่งเบราว์เซอร์ throttle หรือ freeze timer อยู่แล้ว
3. **กันเรียกรัว** — ฟังก์ชัน `refreshHistoryQuietly()` มี guard 3 ชั้น
   - ข้ามถ้า `#checkin-modal` เปิดอยู่ (เช็คจาก `classList.contains('hidden')`) — re-render กลางคันจะสร้างปุ่มชั่วโมงและ bounds เวลาใหม่ใต้นิ้วผู้ใช้ interval ยังเดินต่อ รอบถัดไปค่อยรีเฟรช
   - ข้ามถ้า `busy` — `performCheckin()` โหลด history เองอยู่แล้วเมื่อ request สำเร็จ รีเฟรชซ้อนจะไปแข่งกับ request ที่กำลังจะแทนที่มันพอดี
   - `loadHistory().catch(() => {})` เงียบเมื่อ fail — มันยิงทุกนาทีโดยไม่มีคนดู ถ้าเด้ง toast ทุกครั้งที่เน็ตมือถือสะดุดจะกลบทั้งหน้า ปุ่มที่ผู้ใช้กดเองยังรายงาน error ตามปกติ
4. **Clear interval ตอนออกจากหน้า** — `stopHistoryAutoRefresh()` ผูกกับ `pagehide` เลือก `pagehide` แทน `beforeunload` เพราะมันยิงตอนเข้า bfcache ด้วย และผูก `pageshow` ไว้ re-arm timer เมื่อกลับมาหน้าที่ถูก restore ไม่งั้นกลับมาแล้ว interval ตายไปเลย `startHistoryAutoRefresh()` เคลียร์ตัวเก่าก่อนเสมอ `pageshow` รอบแรกหลังโหลดหน้าจึงไม่ซ้อน interval สองตัว

**ทำไมไม่ต้องแก้อย่างอื่น:** `loadHistory()` เรียก `render()` ซึ่งเรียก `restartElapsedTimer()` อยู่แล้ว พอ sweep ปิด visit `historyRows[0].type` กลายเป็น `'out'` → pill / เวลาที่นับ / ปุ่ม stamp กลับสถานะเองครบ และ `reminderNotifiedKey` ใช้ `planned_checkout_at` เป็น key จึงไม่เตือนซ้ำจากการรีเฟรช

**ทดสอบ:** `node --check` ผ่าน — พฤติกรรมจริงยังไม่ได้ทดสอบ (ต้องเปิดหน้าค้างไว้แล้วยิง auto-checkout ฝั่ง server)

---

## รอบที่ 2 — บั๊ก: ผู้ใช้หลุดล็อกอินเมื่อปิดเบราว์เซอร์

**อาการ:** ต้องการให้ล็อกอินค้างไว้ได้ แต่ปิดเบราว์เซอร์แล้วหลุดทุกครั้ง

**เช็คก่อนแก้ (ตามที่สั่งให้หยุดถาม):** `session_regenerate_id(true)` **มีอยู่แล้ว** ทั้งตอน login (`auth_handlers.php:148`), register (`:103`) และเปลี่ยนรหัสผ่าน (`profile_handlers.php:51`) พร้อมคอมเมนต์อธิบาย session fixation ครบ เงื่อนไขที่ให้หยุดถามจึงไม่เข้า และไม่ได้แตะส่วนนี้

**สิ่งที่แก้**

| ไฟล์ | การแก้ |
|---|---|
| `src/constants.php` | เพิ่ม `const SESSION_LIFETIME_SECONDS = 60 * 60 * 24 * 30;` (30 วัน) เขียนเป็นนิพจน์ให้อ่านออก วางต่อจาก `MAX_PASSWORD_BYTES` ตามสไตล์ `const` เดิมของไฟล์ |
| `src/auth.php` | `'lifetime' => 0` → `SESSION_LIFETIME_SECONDS` และเพิ่ม `ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME_SECONDS)` **ก่อน** `session_start()` |
| `public/partials/guard.php` | เพิ่ม `require constants.php` |

**ทำไมต้องแก้ `guard.php` ด้วย:** ไฟล์นี้โหลดแค่ env, helpers, auth แล้วเรียก `bootstrap_session()` เอง ไม่ได้ require `constants.php` ถ้าใส่ค่าคงที่ไว้ที่ constants เฉยๆ **ทุกหน้าที่ผ่าน guard (dashboard, profile, admin-\*) จะ fatal ทันที** ส่วน `index.php` ไม่พังเพราะ require ไว้ที่บรรทัด 18 อยู่แล้ว

**ทำไม `gc_maxlifetime` ขาดไม่ได้:** cookie บอกว่า *เบราว์เซอร์* เก็บ session ID ไว้นานแค่ไหน ส่วน `gc_maxlifetime` บอกว่า *เซิร์ฟเวอร์* เก็บ record หลัง ID นั้นไว้นานแค่ไหน แก้ข้างเดียวฝั่งที่ยาวกว่าก็เป็นแค่ของประดับ

**flag ความปลอดภัยที่ห้ามหาย — ยืนยันแล้วว่าอยู่ครบ:** `httponly = true`, `samesite = 'Lax'`, `secure = ($appEnv === 'prod')`

**ทดสอบ:** รันจริงผ่านสคริปต์ที่เลียนแบบ include list ของ `guard.php` เป๊ะๆ (ไม่ต้องใช้ DB)

```
SESSION_LIFETIME_SECONDS : 2592000 (30 days)
gc_maxlifetime before    : 1440        <- ค่า default ของ PHP จริงตามที่คาด
gc_maxlifetime after     : 2592000
cookie lifetime          : 2592000
cookie httponly          : true
cookie samesite          : Lax
cookie secure            : false (APP_ENV=dev) / true (APP_ENV=prod)
session status           : ACTIVE
```

ค่า `gc_maxlifetime` เดิม 1440 วินาที (~24 นาที) ยืนยันว่าถ้าแก้แต่ cookie อย่างเดียว session ฝั่งเซิร์ฟเวอร์จะถูก garbage collector ลบก่อนภายใน 24 นาที

**ข้อจำกัดที่รักษาไว้:** ยังเป็น session cookie เหมือนเดิม ไม่มี JWT / localStorage token

---

## รอบที่ 3 — กราฟแดชบอร์ด + ระบบรายงาน PDF

### 3.1 กราฟในหน้ารายงานพิมพ์ออกมาเป็นกล่องเปล่า

**สาเหตุจริง:** ไม่ใช่ข้อมูลหาย ข้อมูลมาครบ แต่ **เบราว์เซอร์ไม่พิมพ์สีพื้นหลัง** ทุกแท่ง/ราง/pill ในรายงานเป็น `background` ทั้งหมด ไม่ใช่ text หรือ border พอ Chrome ปิด "Background graphics" (ค่าเริ่มต้นคือปิด) มันหายหมด — markup กับ layout ปกติดี แค่หมึกไม่ลง

**หลักฐานยืนยัน:** ใน `layout.php` มี `print-color-adjust: exact` อยู่แล้ว **ที่ `th` ตัวเดียว** แปลว่าเคยมีคนเจออาการนี้กับหัวตารางแล้วแก้เฉพาะจุด แต่กราฟไม่เคยได้

**สิ่งที่แก้ — `src/reports/layout.php` (`@media print`)**

เพิ่ม rule ครอบทุกคลาสที่เป็นตัวข้อมูล:

```
th,
.trend-chart .bar,
.bar-track, .bar-fill,
.dept-bar-track, .dept-bar-fill,
.month-track, .month-fill,
.rank-track, .rank-fill, .rank-badge,
.g-track, .g-fill,
.swatch, .status-pill, .type-pill
```

รอบแรกใส่ไม่ครบ ต้องไล่ชื่อคลาสใหม่ทั้งหมดจึงพบว่า `.dept-bar-*` (monthly), `.month-*` (semester), `.swatch` ตกไป

**เจตนาไม่ใช้ `*`:** แถบหัวรายงานกับ filter strip เป็นแค่กรอบ ถ้าบังคับพิมพ์ด้วยจะได้แถบทึบเข้มคาดหัวทุกหน้าโดยไม่ได้ตัวเลขเพิ่มสักตัว

### 3.2 กราฟแนวโน้มในหน้า admin-dashboard: เส้น → แท่ง

**บริบท:** กราฟนี้เพิ่งถูกเปลี่ยนจากแท่งเป็นเส้นเมื่อ 17 ส.ค. (commit `e4d781e`) โดยตั้งใจ เหตุผลคือช่วง 1 เดือนได้ ~31 แท่งในการ์ดกว้าง 700px อ่านเป็นบาร์โค้ด — **ผู้ใช้ยืนยันให้เปลี่ยนกลับเป็นแท่งทั้งหมด**

**สิ่งที่แก้**

- `public/assets/js/admin-dashboard.js` — `renderTrendChart()` วาด `<rect>` ต่อวันแทน area + line โครงเดิมอยู่ครบ: gridline, แกน y, ป้ายวันที่แบบบางลงไม่ให้ทับกัน, ป้ายตัวเลขเฉพาะวันที่มากสุด, hover band เต็มคอลัมน์, คลิก/Enter เปิดรายละเอียดวัน, debounced resize redraw
  - สเกลแบบ band: `slot = plotW / bars.length`, `barW = clamp(slot * 0.72, 2, 46)` เพดาน 46px กันไม่ให้ช่วง 1 วันวาดแท่งเดียวกว้างเท่าการ์ด
  - วันที่ไม่มีใครมาได้ **แท่งตอ 2px สีจาง** — ถ้าวาดว่างเปล่าจะอ่านเป็น "ข้อมูลขาด" แทนที่จะเป็น "นับแล้ว ได้ศูนย์"
  - hover band ยังเต็มคอลัมน์ เพราะจะให้คนไปจิ้มตอ 2px คงไม่ไหว
- `public/assets/css/styles.css` — เพิ่ม `.trend-bar` / `.is-zero` / `.is-active` สำหรับ SVG และ **ลบ** `.trend-area` `.trend-line` `.trend-dot` `.trend-crosshair` ที่ตายแล้ว รวมถึงลบซาก `.trend-bar` ยุค div เก่า (ตั้ง `background-color` / `box-shadow` ซึ่งไม่มีผลกับ SVG `<rect>` เลย) ที่จะชนชื่อกับของใหม่
- `public/admin-dashboard.php` — แก้ข้อความช่วยกลับไปพูดถึง "แท่งกราฟ"

### 3.3 ยก mpdf เป็นปุ่มหลัก + วาดกราฟ PNG ให้ครบทุกรายงาน

**เดิม:** ปุ่มหลักคือ `window.print()` (Ctrl+P) ส่วนปุ่ม mpdf ตั้งใจตัดกราฟออกทุกรายงาน เพราะ div-bar-chart ที่ใช้ `%` height ทำให้ mPDF พองเป็น 50–300 หน้าเปล่า มีแค่ `dashboard.php` ที่ส่งกราฟเป็นรูป PNG จาก GD ไปให้

**สิ่งที่แก้**

1. **`render_pdf_bar_chart()` รับ `$scaleMax`** — ให้กราฟคู่เปรียบเทียบใช้แกนร่วมกัน ถ้าต่างคนต่างสเกล เดือนที่คนน้อยจะสูงเท่าเดือนที่คนเยอะ แล้วกลับหัวข้อสรุปที่รายงานนั้นมีไว้เพื่อบอก ยังพับผ่าน `max()` กับค่าจริงเสมอ เพดานที่ตั้งต่ำไปจึงตัดยอดแท่งไม่ได้
2. **ต่อ `$pdfCharts` เข้ากับรายงาน** — `compare`, `department`, `monthly`, `semester`, `executive` (เดิมมีแค่ `dashboard`)

| รายงาน | กราฟที่เพิ่ม |
|---|---|
| `compare.php` | เปรียบเทียบตามแผนกวิชา เดือน A / เดือน B (แนวนอน, สเกลร่วม) |
| `department.php` | Ranking แผนกวิชา (แนวนอน), แนวโน้มของแผนกที่เลือก (แนวตั้ง), MoM เดือนก่อน/เดือนนี้ (แนวนอน, สเกลร่วม) |
| `monthly.php` | แนวโน้มรายสัปดาห์ (แนวตั้ง), แผนกที่เข้าใช้มากที่สุด top 8 (แนวนอน) |
| `semester.php` | แนวโน้มรายเดือน (แนวตั้ง), Ranking แผนกวิชา (แนวนอน) |
| `executive.php` | แนวโน้มรายสัปดาห์ (แนวตั้ง) เท่านั้น |
| `daily.php`, `student_lookup.php` | ไม่มี — รายงานสองตัวนี้ไม่มีกราฟอยู่แล้ว มีแต่ตาราง |

3. **ซ่อน div เดิมที่ถูกแทนด้วย PNG** — ถ้าใส่ PNG เข้าไปเฉยๆ ตัว div เดิมยังอยู่ใน PDF ด้วย จะได้ข้อมูลซ้ำสองชุด และ `.trend-chart` ของ `department.php` **อยู่นอก** `.mini-panel-row` ที่ซ่อนไว้ — flex row ของ div ที่ใช้ `%` height คือรูปแบบที่เคยทำให้ mPDF พองเป็น 50–300 หน้าเปล่าพอดี เพิ่มใน `$pdfStyle`:

```
.trend-chart,
.rank-bars, .mom-row, .mom-legend,
.dept-row, .month-row,
.dept-compare-row, .compare-legend { display: none; }
```

4. **ปุ่ม toolbar สลับบทบาท** — **ดาวน์โหลด PDF** (mpdf) เป็นปุ่มทึบหลัก, **พิมพ์หน้านี้** (`window.print()`) เป็นปุ่มรอง เพิ่มคลาส `.primary-action` / `.secondary-action` เพราะเดิมสไตล์ผูกกับชนิด element (`button` ทึบ, `a` โปร่ง) ไม่ได้ผูกกับความสำคัญของ action

**ข้อยกเว้นที่ตั้งใจ**

- `executive.php` ไม่ใส่กราฟแผนก เพราะ `.rank-list` ของมันคือส่วนที่ **ผู้ใช้กำลังจัดสไตล์ PDF ค้างไว้เองใน working tree** ใส่กราฟทับจะเป็นการทิ้งงานนั้น
- `dashboard.php` ไม่แตะ เพราะ commit ล่าสุดคือ "Restore the dashboard report to a single A4 page" การเพิ่มกราฟจะดันไปหน้าสอง

**ทดสอบ:** `render_pdf_bar_chart()` เป็นฟังก์ชันบริสุทธิ์ ทดสอบได้โดยไม่ต้องมี DB — รันจริงผ่าน GD ออก PNG ใช้ได้ทั้ง 3 แบบ (แนวตั้ง 720×150, แนวนอน 720×120 สองใบ) ภาษาไทยไม่แตก และสเกลร่วมทำงานถูก (ค่า 30 ยาวราวหนึ่งในสามของ 92 แทนที่จะเต็มเฟรม)

**ยังไม่ได้พิสูจน์:** จำนวนหน้าของ PDF แต่ละรายงานหลังใส่กราฟ ต้องเปิดของจริงดู

---

## รอบที่ 4 — หน้านักศึกษา + หน้าล็อกอิน

### 4.1 ประวัติการเข้าใช้ → ตารางรายครั้ง

**ปัญหาเดิม:** `/me/history` คืน log ดิบ **แถวละ 1 เหตุการณ์** การเข้าห้องสมุด 1 ครั้งจึงมาเป็นการ์ด 2 ใบแยกกัน คนอ่านต้องจับคู่เอาเองข้ามหน้า pagination

**สิ่งที่แก้**

- **endpoint ใหม่ `GET /me/visits`** (`src/handlers/profile_handlers.php` + route ใน `public/index.php`) จับคู่ฝั่ง server ด้วย `LEAD()` window function — แถว `in` ที่แถวถัดไปเป็น `out` คือการเข้าใช้ที่จบแล้ว ถ้าไม่มีคือยังอยู่ในห้องสมุด
  - ใช้ `LEAD()` แทน "หา out ตัวถัดไปที่ไหนก็ได้" เพราะถ้าเกิดมี `in` ติดกันสองแถว วิธีหลังจะจับคู่ผิด
  - แบ่งหน้าที่แถว `in` ทำให้ 1 หน้าเป็นการเข้าใช้ครบครั้งเสมอ
  - คืน `checkin_at`, `checkout_at` (null = ยังอยู่), `checkout_source`, `planned_checkout_at`
  - **ไม่แตะ `/me/history` เดิม** เพราะ `dashboard.js` ยังพึ่ง `historyRows[0].type`
- **`public/assets/js/history-modal.js`** — เขียนใหม่เป็นตาราง 3 คอลัมน์ **วันที่ | เช็คอิน | เช็คเอาต์** แต่ละช่องเป็นกล่องของตัวเอง
  - ครั้งที่ระบบปิดให้อัตโนมัติมีหมายเหตุ "ระบบบันทึกให้" / "เจ้าหน้าที่บันทึกให้" ใต้เวลา (อ่านจาก `checkout_source`) ไม่งั้นนักศึกษาเห็นเวลาเช็คเอาต์ที่ตัวเองไม่ได้กดแล้วจะงง
  - ครั้งที่ยังไม่ออกใช้กรอบประ อ่านว่า "ยังไม่จบ" ไม่ใช่ "ข้อมูลหาย"
  - pagination 10 แถว/หน้า ยังทำงานเหมือนเดิม (ขอเกินมา 1 แถวเพื่อรู้ว่ามีหน้าถัดไปไหม)
- **`public/assets/css/styles.css`** — เพิ่มชุด `.visit-table` / `.visit-cell` / `.visit-time` ฯลฯ ใช้ `font-variant-numeric: tabular-nums` ให้คอลัมน์เวลาตรงกันทุกหลัก

### 4.2 เลือกเวลาออก → ล้อเลื่อน

**สิ่งที่แก้** — `public/dashboard.php` (markup), `public/assets/css/styles.css`, `public/assets/js/dashboard.js`

แทน `<input type="text">` ที่พิมพ์ HH:MM ด้วยล้อเลื่อน 2 คอลัมน์ (ชั่วโมง / นาที) ใช้ `scroll-snap-type: y mandatory` มีแถบกลางนิ่งเป็นตัวชี้ค่าที่เลือก ตามภาพตัวอย่างที่ส่งมา

**หมายเหตุสำคัญ:** ช่องพิมพ์เดิมมีคอมเมนต์กำกับว่าถูกทำขึ้นเพื่อ *หนี* native time picker เพราะอันนั้นคือคำติเดิม ("fiddly on touch") ตัวที่ทำใหม่**ไม่ใช่** native picker — เป็น list ในหน้าเว็บเอง ไม่มี overlay ของ OS ให้ต้องเรียกหรือปิด จึงไม่ย้อนกลับไปหาปัญหาเดิม

รายละเอียดการทำงาน:

- `WHEEL_ITEM_H = 44` ต้องตรงกับความสูงของ `.time-wheel-item` ใน CSS เพราะค่าที่เลือกคำนวณจาก `scrollTop` ถ้าไม่ตรงจะอ่านได้คนละแถวกับที่อยู่ใต้แถบกลาง
- ใส่ padding บน/ล่างครึ่งล้อ (66px) ให้ค่าแรกและค่าสุดท้ายเลื่อนมาอยู่กลางได้เหมือนแถวอื่น
- `clampWheels()` ดึงค่ากลับเข้าช่วงเวลาเปิดทำการหลังเลื่อนหยุด (debounce 120ms) — เลือกเวลาเกินเวลาปิดไม่ได้ตั้งแต่แรก แทนที่จะปล่อยให้เลือกแล้วค่อยขึ้น error ตอนกดยืนยัน
- ตั้งค่าด้วย `scrollTop` ตรงๆ ไม่ใช้ `behavior: 'smooth'` เพราะ smooth scroll ตีกับ scroll-snap แล้วอาจหยุดคนละแถวกับที่สั่ง
- สร้างล้อ **หลัง** modal ถูก un-hide แล้ว เพราะ `scrollTop` ค้างที่ 0 ตอน `display:none`
- ค่าเริ่มต้น = "อีก 1 ชั่วโมงจากนี้" ไม่เกินเวลาปิด
- ลูกศรขึ้น/ลงเลื่อนทีละแถวเมื่อคอลัมน์มี focus
- ลบ `formatTimeTyping()` และ `parseTypedTime()` ที่ไม่ได้ใช้แล้ว
- แท็บ "เลือกจำนวนชั่วโมง" (1–6 ชม.) ยังอยู่เหมือนเดิม

### 4.3 แบนเนอร์หน้าล็อกอิน (อาจารย์ติมาว่าไม่สวยและใหญ่เกินไป)

**สิ่งที่แก้** — `public/assets/css/styles.css` (11 จุด)

ความสูงลดจากประมาณ 267px เหลือประมาณ 142px (ราวครึ่งหนึ่ง):

| ส่วน | เดิม | ใหม่ |
|---|---|---|
| `.content` padding | 24px (900px+: 28/40) | 14/16px (900px+: 16/18) |
| `.logo-badge` | 58px (รูป 52px) | 42px (รูป 36px) |
| `.title-frame` padding | 14/20/16px | 8/18/10px |
| `h1` | 27px | 20px |
| `h2` | 16px | 13px |
| `.sub` (ศูนย์กลางแห่งการเรียนรู้…) | 13.5px | ซ่อน |
| `.footer-line` (แถบ 3 วลี) | 11.5px | ซ่อน |
| `.brand` (ชื่อข้างโลโก้) | 14.5px | ซ่อน — `<h2>` ใต้ลงมาสองบรรทัดพูดคำเดียวกัน |

ตัดของที่รกออกทั้งหมด **ทุกความกว้าง**: ภาพประกอบ SVG ทั้งฉาก (`.illust`), จุดกะพริบ (`.spark`), dot grid, เส้นแบ่ง (`.divider`), ไอคอนลอยสองมุม (`.corner-deco`) — ของพวกนี้เดิมโผล่เฉพาะจอกว้างกว่า 1320px ซึ่งเป็นจุดที่เห็นชัดที่สุดว่ามันเยอะ

เหลือโลโก้ + ชื่อ + แถบบน (`.top-stripe`) + คลื่นล่าง (`.wave`) ทั้งสองอย่างหลังแนบขอบ ไม่กินความสูง **markup ยังอยู่ครบ** (`partials/lib-banner.php` ไม่ถูกแตะ) ย้อนได้ด้วยการแก้ rule เดียว

### 4.4 ตัดตัวเลือกนักศึกษา/แอดมินออกจากหน้าล็อกอิน

**สิ่งที่แก้** — `public/login.php`, `public/assets/js/login.js`

- ลบแท็บ `data-role-tab` ทั้งบล็อก เหลือช่อง username + password ชุดเดียว
- `login.js` เขียนใหม่: ลบ `selectedRole`, `setRole()`, `ROLE_LABEL_TH` และ error "บัญชีนี้เป็นบัญชี…" ทิ้ง เหลือ `landingPathForRole(role)` ที่ส่งไป `/admin-dashboard` หรือ `/dashboard` ตาม `role` ที่ `/login` คืนมา
- label เปลี่ยนจาก "ชื่อผู้ใช้ (รหัสนักศึกษา)" เป็น "ชื่อผู้ใช้", placeholder เป็น "รหัสนักศึกษา หรือชื่อผู้ใช้แอดมิน", เพิ่ม `for=` ให้ label ทั้งสอง

**การแยกสิทธิ์อยู่ที่ backend อยู่แล้ว ไม่ต้องเพิ่ม:** `/login` คืน `role` มาให้ และ `public/partials/guard.php` บังคับซ้ำอีกชั้นฝั่ง server (`$requireAdmin`) — นักศึกษาพิมพ์ URL แอดมินเองก็โดนเด้งกลับ การ redirect ฝั่ง JS จึงเป็นแค่การพาไปถูกที่ ไม่ใช่การคุมสิทธิ์

**ทำไมของเดิมถึงผิด:** แท็บบังคับให้คนตอบว่าตัวเองเป็นใคร *ก่อน* ที่ server จะได้เห็น username ด้วยซ้ำ เลือกผิดแท็บก็ขึ้น error ทั้งที่รหัสถูก

### 4.5 ข้อกังวลเรื่องหลุดล็อกอินบนมือถือ

**แก้ไปแล้วในรอบที่ 2** — cookie 30 วัน + `gc_maxlifetime` คู่กัน ดูหัวข้อ "รอบที่ 2"

---

## ไฟล์ที่แก้ทั้งหมด

### ไฟล์ที่แก้ในเซสชันนี้

| ไฟล์ | รอบ | สาระ |
|---|---|---|
| `public/assets/js/dashboard.js` | 1, 4.2 | auto-refresh + ล้อเลื่อนเวลา |
| `src/constants.php` | 2 | `SESSION_LIFETIME_SECONDS` |
| `src/auth.php` | 2 | cookie lifetime + `gc_maxlifetime` |
| `public/partials/guard.php` | 2 | require `constants.php` |
| `src/reports/layout.php` | 3.1, 3.3 | print-color-adjust, `$scaleMax`, ซ่อน div ที่ซ้ำ, ปุ่ม toolbar |
| `public/assets/js/admin-dashboard.js` | 3.2 | เส้น → แท่ง |
| `public/admin-dashboard.php` | 3.2 | ข้อความช่วย |
| `public/assets/css/styles.css` | 3.2, 4.1, 4.2, 4.3 | `.trend-bar`, `.visit-*`, `.time-wheel*`, แบนเนอร์ |
| `src/reports/compare.php` | 3.3 | `$pdfCharts` |
| `src/reports/department.php` | 3.3 | `$pdfCharts` |
| `src/reports/monthly.php` | 3.3 | `$pdfCharts` |
| `src/reports/semester.php` | 3.3 | `$pdfCharts` |
| `src/reports/executive.php` | 3.3 | `$pdfCharts` (เฉพาะรายสัปดาห์) |
| `src/handlers/profile_handlers.php` | 4.1 | `handle_my_visits()` |
| `public/index.php` | 4.1 | route `GET /me/visits` |
| `public/assets/js/history-modal.js` | 4.1 | ตารางรายครั้ง |
| `public/dashboard.php` | 4.2 | markup ล้อเลื่อน |
| `public/login.php` | 4.4 | ลบแท็บ role |
| `public/assets/js/login.js` | 4.4 | เขียนใหม่ |

### งานค้างของผู้ใช้เองที่ **ไม่ได้แตะ**

ไฟล์เหล่านี้มีการแก้ค้างอยู่ใน working tree ตั้งแต่ก่อนเซสชันนี้ (งาน month-picker และการจัดสไตล์ PDF ของ executive) — บันทึกไว้เพื่อไม่ให้สับสนว่าเป็นงานของใคร

| ไฟล์ | เนื้อหา |
|---|---|
| `src/reports/aggregate.php` | ฟังก์ชันใหม่ `render_month_select()` |
| `src/reports/select.php` | เปลี่ยน `<input type="month">` เป็น `render_month_select()` 5 จุด |
| `src/reports/dashboard.php` | เปลี่ยนเป็น `render_month_select()` 1 จุด |
| `src/reports/layout.php` | (บางส่วน) สไตล์ PDF ของ `.headline-grid` / `.rank-list` / `.split-col` และ selector ของ auto-submit |
| `src/reports/executive.php` | (บางส่วน) สไตล์ PDF |
| `src/reports/compare.php`, `department.php`, `monthly.php` | (บางส่วน) `render_month_select()` |
| `public/assets/js/dashboard.js` | (บางส่วน) สีปุ่ม stamp เขียว/แดง |

---

## ความเสี่ยงและสิ่งที่ต้องทำต่อ

### เสี่ยงสูง — ยังไม่เคยรัน

1. **SQL ของ `/me/visits`** — `LEAD()` มีใน MariaDB ตั้งแต่ 10.2 และเครื่องนี้ 10.4.32 จึงรองรับแน่ แต่ยังพิสูจน์ไม่ได้ว่า query รันผ่านจริง ถ้าพัง หน้าประวัติจะว่างเปล่า (JS catch ไว้แล้ว ไม่ค้าง)
2. **GD ใน production** — `Dockerfile` ลงแค่ `pdo pdo_mysql` **ไม่ได้ลง `gd`** ตอนนี้ GD กลายเป็นของจำเป็นของทุกรายงานแล้ว ไม่ใช่แค่ dashboard ถ้า PDF ของ dashboard ใช้งานได้อยู่บน production แปลว่า host จริงมี GD (คนละทางกับ Docker image) แต่ถ้าจะใช้ Docker ต้องเพิ่ม `gd` ไม่งั้น `imagecreatetruecolor()` fatal

### ยังไม่ได้ตรวจด้วยตา

ทุกอย่างในรอบที่ 3 และ 4 — กราฟแท่งหน้า admin, ตารางประวัติ, ล้อเลื่อนเวลา, แบนเนอร์, หน้าล็อกอิน, จำนวนหน้าของ PDF แต่ละรายงาน

### ขั้นตอนถัดไปเมื่อ DB กลับมา

1. `taskkill /F /PID 7832` และ `/PID 15300` (ต้อง Administrator) แล้วสตาร์ต MySQL ใหม่
2. เช็คแถวตกค้าง `SELECT ... WHERE username LIKE 'smk%'`
3. รัน `php tests/smoke_test.php http://127.0.0.1:8000` (สตาร์ต dev server ด้วย `php -S 127.0.0.1:8000 -t public` และ **redirect stderr ออกไฟล์**)
4. เปิดหน้าจริงตรวจทั้ง 4 รอบ
5. ค่อย commit

### เรื่อง deploy (ยังไม่ได้ทำ)

ยืนยันแล้วว่ามีของขึ้น production จริงที่ `ntclibrary.com` แต่ **repo นี้ไม่มี config สำหรับ deploy backend PHP** — มีแค่ `Dockerfile` + `docker-compose.yml` ที่เป็น dev stack, ไม่มี CI, README ว่างเปล่า หัวข้อ deploy ใน `PROJECT_OVERVIEW.md` เป็นของ backend Flask ตัวเก่าขึ้น PythonAnywhere ใช้กับ PHP ไม่ได้ ทางขึ้นจริงน่าจะเป็น FTP ตามที่ `.gitignore` ระบุไว้ว่าคู่มือ PDF มีบท deployment ที่ฝัง FTP host และชื่อ DB production
