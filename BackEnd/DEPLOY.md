# Deploy ขึ้น PythonAnywhere (ฟรี ไม่ต้องรัน XAMPP อีกเลย)

เป้าหมาย: ได้ URL ถาวรแบบ `https://ชื่อคุณ.pythonanywhere.com` ที่คลิกแล้วเข้าเว็บได้เลย
ไม่ต้องเปิดคอมเครื่องนี้ทิ้งไว้ ไม่ต้องรัน XAMPP/cmd อีก

ฐานข้อมูลบน production จะเริ่มจาก**ค่าว่างเปล่า** (ไม่มีข้อมูลนักเรียนตัวอย่างติดไปด้วย)
พอพร้อมข้อมูลจริงจาก Excel ค่อย import ทีหลังตามขั้นตอนท้ายไฟล์นี้

---

## ขั้นตอนที่ 1 — สมัครบัญชี
ไปที่ https://www.pythonanywhere.com/registration/register/beginner/
สมัครแผน **Beginner (Free)** — ไม่ต้องผูกบัตรเครดิต

จด **username** ที่ตั้งไว้ให้ดี เพราะจะใช้แทนคำว่า `YOURNAME` ทุกจุดด้านล่าง

## ขั้นตอนที่ 2 — อัปโหลดโค้ด
ไม่ต้องใช้ git ก็ได้ — วิธีง่ายสุด:

1. บนคอมนี้ ให้ zip โฟลเดอร์ `BackEnd` (ยกเว้น `venv/` เพราะไม่จำเป็นต้องอัปโหลดขึ้นไป — venv จะสร้างใหม่บน PythonAnywhere)
2. ใน PythonAnywhere แถบ **Files** → อัปโหลดไฟล์ zip เข้าไปที่ home directory
3. เปิดแถบ **Consoles** → เปิด **Bash console** ใหม่ แล้วรัน:
   ```bash
   unzip BackEnd.zip
   ```

## ขั้นตอนที่ 3 — ติดตั้ง Python packages
ใน Bash console เดิม:
```bash
mkvirtualenv --python=python3.10 libraryenv
cd BackEnd
pip install -r requirements.txt
```
(ครั้งหน้าที่เปิด console ใหม่ ให้พิมพ์ `workon libraryenv` เพื่อกลับเข้า virtualenv นี้)

## ขั้นตอนที่ 4 — สร้างฐานข้อมูล MySQL
1. แถบ **Databases** → ตั้งรหัสผ่าน MySQL (จดไว้)
2. ในหน้าเดียวกัน สร้างฐานข้อมูลชื่อ `library_checkin` — PythonAnywhere จะตั้งชื่อจริงเป็น
   `YOURNAME$library_checkin` (ต้องมี `$YOURNAME` นำหน้าเสมอ)
3. กลับไป Bash console แล้ว import โครงสร้างตาราง (schema.sql มีอยู่แล้ว ไม่มีข้อมูลตัวอย่างติดไปด้วย):
   ```bash
   mysql -u YOURNAME -h YOURNAME.mysql.pythonanywhere-services.com -p "YOURNAME\$library_checkin" < schema.sql
   ```
   ใส่รหัสผ่านที่ตั้งไว้ตอนถูกถาม

## ขั้นตอนที่ 5 — ตั้งค่า .env
ใน Bash console:
```bash
cd ~/BackEnd
nano .env
```
แก้เป็น:
```
DB_HOST=YOURNAME.mysql.pythonanywhere-services.com
DB_USER=YOURNAME
DB_PASSWORD=<รหัสผ่าน MySQL ที่ตั้งไว้ขั้นตอนที่ 4>
DB_NAME=YOURNAME$library_checkin

FLASK_SECRET_KEY=<สุ่มมาสัก 32 ตัวอักษร ห้ามใช้ค่า dev เดิม>
```
สุ่ม FLASK_SECRET_KEY ได้ด้วยคำสั่ง `python3 -c "import secrets; print(secrets.token_hex(32))"`
บันทึกด้วย Ctrl+O, Enter แล้ว Ctrl+X ออก

## ขั้นตอนที่ 6 — ตั้งค่า Web App
1. แถบ **Web** → **Add a new web app** → เลือก **Manual configuration** (ไม่ใช่ wizard Flask) → เลือก Python 3.10
2. ในหน้า config ที่ได้ ตั้งค่า:
   - **Source code**: `/home/YOURNAME/BackEnd`
   - **Virtualenv**: `/home/YOURNAME/.virtualenvs/libraryenv`
3. คลิกลิงก์ **WSGI configuration file** แล้วลบเนื้อหาเดิมทั้งหมด แทนที่ด้วย:
   ```python
   import sys
   path = '/home/YOURNAME/BackEnd'
   if path not in sys.path:
       sys.path.append(path)

   from app import app as application
   ```
4. เลื่อนลงมาส่วน **Static files** เพิ่ม 1 แถว:
   - URL: `/static/`
   - Directory: `/home/YOURNAME/BackEnd/static/`
5. กด **Reload** ปุ่มสีเขียวด้านบน

## ขั้นตอนที่ 7 — สร้างบัญชีแอดมินจริง
ใน Bash console:
```bash
workon libraryenv
cd ~/BackEnd
python create_admin.py <username ที่ต้องการ>
```
ใส่รหัสผ่านตอนถูกถาม (ไม่ต้องบอกใคร รวมถึงไม่ต้องบอก AI)

## เสร็จแล้ว
เปิด `https://YOURNAME.pythonanywhere.com` ได้เลย ไม่ต้องรัน XAMPP อีกต่อไป

---

## พอพร้อมข้อมูลนักเรียนจริงจาก Excel
1. อัปโหลดไฟล์ Excel ใหม่ไปแทน `Student.xlsx` ในแถบ Files (path: `~/BackEnd/Student.xlsx`)
2. Bash console:
   ```bash
   workon libraryenv
   cd ~/BackEnd
   python import_students.py
   ```
นักเรียนจะเข้าเว็บสมัครบัญชีเองได้ทันที (กรอกรหัสนักเรียน + ตั้ง username/password เอง)
สมัครเสร็จปุ๊บเข้าใช้งานได้เลย ไม่ต้องรอแอดมินอนุมัติและไม่ต้องแนบรูปบัตรใดๆ

## ข้อจำกัดของแผนฟรี (รู้ไว้)
- CPU seconds มีโควตาต่อวัน ถ้าคนใช้เยอะมากอาจต้องอัปเกรดแผน (สำหรับโปรเจกต์ในวิทยาลัยแผนฟรีเพียงพอ)
- ต้องล็อกอินเข้า PythonAnywhere อย่างน้อยทุก 3 เดือน ไม่งั้นเว็บแอปจะถูกปิดอัตโนมัติ (แค่ล็อกอินดูเฉยๆ ก็นับ)
