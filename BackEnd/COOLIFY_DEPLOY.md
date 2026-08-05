# Deploy ขึ้น Coolify (self-hosted PaaS บน VPS ของตัวเอง)

คู่มือนี้เขียนแบบเดียวกับ `DEPLOY.md` เดิม (ที่ใช้กับ PythonAnywhere) แต่สำหรับ Coolify ที่ติดตั้งเองบน VPS
งานทั้งหมดในเอกสารนี้คือ**ห่อระบบเดิมให้พร้อมรัน production เท่านั้น — ไม่มีการเปลี่ยน logic การทำงานใดๆ ของระบบ**
ทุกฟีเจอร์ (login, เช็คอิน/เช็คเอาท์, รายงาน, ฯลฯ) ทำงานเหมือนเดิมทุกประการ สิ่งที่เปลี่ยนมีแค่ "วิธีรัน"
(Docker + Gunicorn แทน `python app.py`) ไม่ใช่ "สิ่งที่ระบบทำ"

---

## 0. สิ่งที่เตรียมไว้แล้วในโค้ด (อ้างอิง)

| ไฟล์ | หน้าที่ |
|---|---|
| `Dockerfile` | สร้าง image จาก Python 3.14-slim, ติดตั้ง `requirements.txt`, รันด้วย Gunicorn |
| `.dockerignore` | กัน `.env`, `venv/`, `Student.xlsx`, ไฟล์ dev-only ไม่ให้เข้า image |
| `.env.production.example` | รายการตัวแปรที่ต้องตั้งค่าจริงบน Coolify (ค่าไหนต้องเปลี่ยนจาก dev มีคอมเมนต์กำกับ) |
| `scripts/export_db.sh` | export ฐานข้อมูล dev ปัจจุบันทั้ง 4 ตาราง (มีข้อมูลจริง) เป็นไฟล์ `.sql` เดียว |
| `requirements.txt` | เพิ่ม `gunicorn==26.0.0` |
| `app.py` (ท้ายไฟล์) | แก้ให้ auto-checkout scheduler เริ่มทำงานถูกต้องไม่ว่าจะรันผ่าน `python app.py` หรือ Gunicorn — **ต้องรันด้วย 1 worker/1 instance เท่านั้น** (อธิบายเหตุผลในหัวข้อ 3) |

---

## 1. เตรียม MySQL บน Coolify

1. ใน Coolify: **New Resource → Database → MySQL**
2. ตั้งชื่อฐานข้อมูล เช่น `library_checkin` — จด **host ภายใน (internal hostname)**, **user**, **password** ที่ Coolify สร้างให้ไว้ (อยู่ในแท็บ "Configuration"/"Environment Variables" ของ resource นี้)
3. **ไม่ต้องเปิด public port** ให้ MySQL resource นี้ (ปล่อยเป็นค่า default ที่เข้าถึงได้แค่ภายใน network ของ Coolify) — ฐานข้อมูลไม่จำเป็นต้องเปิดสู่อินเทอร์เน็ตเลย มีแค่ app container ที่ต้องคุยกับมัน
4. รอจน MySQL resource ขึ้นสถานะ "Running"

## 2. เตรียม Application resource บน Coolify

1. **New Resource → Application → เชื่อม Git repository** ของโปรเจกต์นี้ (branch ที่จะ deploy)
2. **Build Pack**: เลือก **Dockerfile** (ไม่ใช่ Nixpacks/buildpack อัตโนมัติ) — ชี้ path ไปที่ `BackEnd/Dockerfile` ถ้า repo root ไม่ใช่ `BackEnd/` โดยตรง
3. **Port**: 5000 (ตรงกับ `EXPOSE 5000` ใน Dockerfile และค่า fallback ของ `${PORT:-5000}`) — หรือปล่อย Coolify ตั้ง `PORT` เป็นค่าอื่นก็ได้ เพราะ `CMD` ใน Dockerfile อ่านจาก env var `PORT` อัตโนมัติ

### ⚠️ ข้อจำกัดสำคัญที่สุด: ต้องรันแค่ 1 instance เท่านั้น

`Dockerfile` บังคับ Gunicorn ไว้ที่ **`--workers 1`** อยู่แล้ว (ห้ามแก้เป็นมากกว่านี้) เพราะแอปนี้มี
APScheduler background job (auto-checkout) กับ Flask-Limiter แบบ in-memory ที่เป็น per-process state
— ถ้ามีมากกว่า 1 process รันพร้อมกัน jobจะซ้ำ/rate-limit จะไม่ตรงกันข้ามกัน

**เงื่อนไขเดียวกันนี้ใช้กับระดับ container ด้วย**: ถ้า Coolify มีตัวเลือก "Replicas"/"Scale" ของ Application
resource นี้ **ต้องตั้งไว้ที่ 1 เสมอ ห้ามปรับขึ้นเพื่อรับ traffic** — เพราะแต่ละ replica คือ container แยกที่
import โมดูล `app.py` ของตัวเอง จะกลายเป็นปัญหาเดียวกับหลาย Gunicorn worker แค่ย้ายไปอยู่ระดับ container แทน
(สำหรับ scale ของระบบนี้ — ห้องสมุดวิทยาลัย — 1 instance เพียงพอแน่นอน)

## 3. ตั้งค่า Environment Variables ผ่าน Coolify UI

ในหน้า Application resource → แท็บ **Environment Variables** เพิ่มทีละตัวตาม `.env.production.example`:

| ตัวแปร | ค่า | หมายเหตุ |
|---|---|---|
| `DB_HOST` | internal hostname ของ MySQL resource (จากขั้นตอนที่ 1) | **ห้ามใส่ `localhost`** — คนละ container กัน |
| `DB_USER` | user ที่ Coolify สร้างให้ตอนสร้าง MySQL resource | ห้ามใช้ `root`/ไม่มีรหัสผ่านแบบ dev |
| `DB_PASSWORD` | password ของ user ข้างต้น | |
| `DB_NAME` | `library_checkin` (หรือชื่อที่ตั้งไว้ตอนสร้าง MySQL resource) | |
| `FLASK_SECRET_KEY` | สุ่มใหม่ด้วย `python -c "import secrets; print(secrets.token_hex(32))"` | ห้ามใช้ค่า dev เดิมหรือค่าที่เคยพิมพ์ในแชท |
| `FLASK_DEBUG` | `false` | **บังคับ** ไม่งั้น debug mode จะเปิดบน production |
| `LIBRARY_CLOSING_TIME` | `17:00` (หรือค่าที่ต้องการ) | ไม่ใส่ก็ได้ ใช้ค่า default |

กด **Save** แล้ว Coolify จะ inject ตัวแปรพวกนี้เป็น environment variable ของ container ตอนรัน (ไม่ต้องมีไฟล์
`.env` จริงในเครื่อง production — `python-dotenv` แค่ไม่เจอไฟล์ `.env` แล้วข้ามไปเฉยๆ ตัวแปรที่ Coolify inject
มาทาง `os.environ` อยู่แล้วใช้งานได้ปกติ)

## 4. Deploy ครั้งแรก

1. กด **Deploy** ใน Coolify — จะดึงโค้ดจาก git, รัน `docker build` ตาม `Dockerfile`, แล้วรัน container
2. ดู **Logs** ของ deployment — ควรเห็นบรรทัดประมาณนี้ (เหมือนที่ทดสอบไว้แล้วตอนพัฒนา):
   ```
   [INFO] Starting gunicorn 26.0.0
   [INFO] Listening at: http://0.0.0.0:5000
   [INFO] Using worker: sync
   [INFO] Booting worker with pid: N     <- ต้องมีบรรทัดนี้ "แค่บรรทัดเดียว" เท่านั้น
   ```
   ถ้าเห็น "Booting worker" มากกว่า 1 บรรทัด แปลว่า worker/replica ตั้งมากกว่า 1 ต้องกลับไปแก้ตามหัวข้อ 2
3. เปิด URL ที่ Coolify ให้มา ควรเจอหน้า `/login` — ตอนนี้ฐานข้อมูลยังว่างเปล่า (แค่โครงตาราง ไม่มีนักศึกษา) รอ import ข้อมูลจริงในขั้นตอนถัดไป

## 5. ย้ายข้อมูลจริงจาก dev เข้า production

1. บนเครื่อง dev (เครื่องนี้), รันจากโฟลเดอร์ `BackEnd/`:
   ```bash
   bash scripts/export_db.sh
   ```
   ได้ไฟล์ `library_checkin_YYYYMMDD_HHMMSS.sql` (มีข้อมูลจริงทั้ง 4 ตาราง — students/users/checkin_logs/announcements)
2. SSH เข้า VPS ที่ติดตั้ง Coolify แล้วหาชื่อ container ของ MySQL:
   ```bash
   ssh youruser@your-vps-ip "docker ps --format '{{.Names}}' | grep -i mysql"
   ```
3. ยิงไฟล์ dump เข้า container ผ่าน SSH ทีเดียว (เอา root password จากแท็บ Environment Variables ของ MySQL resource ใน Coolify):
   ```bash
   cat library_checkin_YYYYMMDD_HHMMSS.sql | \
     ssh youruser@your-vps-ip "docker exec -i <ชื่อ-container-mysql> mysql -u root -p'<MYSQL_ROOT_PASSWORD>' library_checkin"
   ```
4. รีเฟรชหน้าเว็บ production — ควรเห็นนักศึกษา/ประวัติเช็คอินเดิมครบ

### ⚠️ ต้องเปลี่ยนรหัสผ่านแอดมินทันทีหลัง import เสร็จ

ไฟล์ dump พาบัญชีแอดมินเดิมไปด้วยทั้งหมด (รวม password hash เดิมที่รหัสผ่านตั้งต้นเคยถูกพิมพ์อยู่ในแชทมาก่อน
— ดู `PROJECT_CONTEXT.md`) **ก่อนเปิดให้ใครใช้งานจริง ต้อง SSH เข้า container ของแอป แล้วรัน:**
```bash
docker exec -it <ชื่อ-container-แอป> python create_admin.py <username-ใหม่>
```
หรือเข้าเว็บด้วยบัญชีแอดมินเดิมแล้วเปลี่ยนรหัสผ่านผ่าน `/profile/change-password` ทันที — ทำอย่างใดอย่างหนึ่งนี้
**ก่อน** เผยแพร่ URL ให้นักศึกษา/เจ้าหน้าที่คนอื่นใช้งาน

## 6. เปิด Scheduled Backup ของ MySQL บน Coolify

Coolify มีฟีเจอร์ backup ในตัวสำหรับ database resource:

1. ไปที่ MySQL resource → แท็บ **Backups** (หรือ "Scheduled Backups" แล้วแต่เวอร์ชัน Coolify)
2. กด **Add Scheduled Backup** ตั้งความถี่ (แนะนำ: ทุกวัน ตอนกลางคืน เช่น ตี 2-3 ที่คนใช้น้อยสุด)
3. เลือกปลายทางเก็บไฟล์ backup — ถ้ามี S3-compatible storage (Coolify รองรับ S3/MinIO เป็นปลายทาง) ให้ตั้งไว้เพื่อไม่ให้ backup หายไปพร้อม VPS ถ้าเครื่องมีปัญหา; ถ้าไม่มี อย่างน้อยให้เก็บไว้ใน local storage ของ Coolify ก็ยังดีกว่าไม่มี backup เลย
4. ตั้งจำนวนวันที่จะเก็บ backup ย้อนหลัง (retention) ตามพื้นที่ดิสก์ที่มี — ระบบนี้ข้อมูลไม่ใหญ่ (นักศึกษาหลักพันคน) เก็บย้อนหลัง 30 วันขึ้นไปได้สบาย

หมายเหตุ: schema เดิมไม่มี stored procedure/trigger ให้ backup ยุ่งยาก เป็นแค่ 4 ตารางข้อมูลตรงไปตรงมา
กู้คืนกลับมาก็แค่ import ไฟล์ backup แบบเดียวกับขั้นตอนที่ 5 ด้านบน

## 7. แนะนำเปิด unattended-upgrades บน VPS (ตั้งครั้งแรกตอนติดตั้ง VPS)

ระบบนี้อาจไม่มีคนดูแลต่อเนื่องหลังจบการศึกษา (ตามที่ระบุใน `PROJECT_CONTEXT.md`) — ความเสี่ยงระยะยาวที่สุด
คือ VPS ไม่เคยได้รับ security patch เลย เปิด auto-security-update ไว้แต่แรกช่วยลดความเสี่ยงนี้ได้มาก
โดยไม่ต้องมีใครมาดูแลเป็นประจำ:

**ถ้า VPS เป็น Ubuntu/Debian** (พบบ่อยสุดสำหรับ Coolify):
```bash
sudo apt update
sudo apt install unattended-upgrades apt-listchanges
sudo dpkg-reconfigure -plow unattended-upgrades   # ตอบ "Yes" เมื่อถามว่าจะเปิดใช้งานอัตโนมัติไหม
```
ค่า default ที่ได้จะติดตั้งเฉพาะ **security patch** อัตโนมัติ (ไม่ใช่ทุก package update ทั่วไปที่อาจทำ Docker/Coolify พังได้) — ตรวจสอบว่าเปิดใช้งานจริงด้วย:
```bash
sudo systemctl status unattended-upgrades
cat /etc/apt/apt.conf.d/20auto-upgrades
```
ควรเห็น `APT::Periodic::Unattended-Upgrade "1";`

**ข้อควรระวัง**: unattended-upgrades จะไม่ auto-restart Docker/Coolify เอง หลังแพตช์เคอร์เนล/ระบบสำคัญ
อาจต้อง reboot VPS เองเป็นครั้งคราว (Coolify container จะ auto-start กลับมาเองถ้าตั้ง restart policy ไว้ถูกต้อง
ซึ่งเป็นค่า default ของ Docker Compose ที่ Coolify ใช้อยู่แล้ว)

---

## Known issues (ไม่เกี่ยวกับงาน deploy นี้ — พบระหว่างทดสอบ ไม่ได้แก้เพราะนอกขอบเขต)

`test_app.py`/`conftest.py` มี test debt ค้างอยู่ก่อนงาน deploy นี้ (13/18 เคส fail) จาก 2 สาเหตุที่ไม่เกี่ยวกับ
Docker/production เลย:
1. `/register` ปัจจุบันต้องการฟิลด์เพิ่ม (`prefix`, `first_name`, `last_name`, `department`, `level`,
   `year_level`) แต่ fixture `registered_student` ใน `conftest.py` ยังส่งแค่ `student_id + username + password`
   แบบเดิม ทำให้การสมัครใน fixture ล้มเหลวเงียบๆ (fixture ไม่เช็ค status code) แล้วเทสต์ที่พึ่ง fixture นี้
   ล้มเหลวตามเป็นลูกโซ่
2. `test_login_wrong_password` เช็คข้อความ error เป็นภาษาอังกฤษ แต่ error message ถูกแปลเป็นไทยไปแล้วตั้งแต่
   งาน Thai-ify (`WORK_SUMMARY_2026-07-30.md`) โดยไม่มีใครอัปเดตเทสต์ตอนนั้น

5 เคสที่ผ่านอยู่ก่อนและหลังงาน deploy นี้เหมือนกัน (ยืนยันไม่มี regression ใหม่จากงานนี้): `test_register_missing_fields`,
`test_checkin_requires_login`, `test_me_requires_login`, `test_change_password_wrong_current_password`,
`test_admin_members_search_filter`

แก้ไขแนะนำ (นอกขอบเขตงานนี้ ทำแยกภายหลังถ้าต้องการ): อัปเดต fixture ให้ส่งฟิลด์ครบตามที่ `/register` ต้องการจริง
และแก้ข้อความที่ `test_login_wrong_password` เช็คให้เป็นภาษาไทยตรงกับที่ `app.py` ส่งจริงตอนนี้

---

## Checklist สรุปก่อนเปิดให้ใช้งานจริง

- [ ] MySQL resource สร้างแล้ว, ไม่เปิด public port
- [ ] Application resource ตั้งค่า build จาก `Dockerfile`, **1 instance/replica เท่านั้น**
- [ ] Environment variables ครบทั้ง 7 ตัวตามหัวข้อ 3 (โดยเฉพาะ `FLASK_DEBUG=false` และ `FLASK_SECRET_KEY` สุ่มใหม่)
- [ ] Deploy แล้ว log แสดง "Booting worker" แค่ 1 บรรทัด
- [ ] Import ข้อมูลจริงจาก dev ด้วย `export_db.sh` + `docker exec` สำเร็จ
- [ ] **เปลี่ยนรหัสผ่านแอดมินแล้ว** (หรือสร้างบัญชีใหม่ผ่าน `create_admin.py` แล้วลบบัญชีเดิม)
- [ ] เปิด Scheduled Backup ของ MySQL แล้ว
- [ ] เปิด unattended-upgrades บน VPS แล้ว (ทำครั้งเดียวตอน setup VPS)
