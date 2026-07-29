# Setup โปรเจกต์บนคอมเครื่องใหม่ (เช่น คอมเพื่อน)

ใช้ตอนอยาก **แก้ไข/เขียนโค้ดต่อ** จากคอมเครื่องอื่น (ไม่ใช่แค่เปิดเว็บดู — ถ้าแค่เปิดดู ใช้ URL
ที่ deploy ไว้ตาม `DEPLOY.md` ได้เลย ไม่ต้องทำตามไฟล์นี้)

**สิ่งที่ต้องมีในคอม**: Python 3.10+ และ MySQL (ผ่าน XAMPP ก็ได้) — เพราะการรันโค้ดเพื่อทดสอบ
ต้องมีฐานข้อมูลให้เชื่อมต่อเสมอ ไม่ว่าจะ deploy จริงอยู่ที่ไหนก็ตาม (XAMPP ที่ไม่อยากรันซ้ำๆ
คือตอน**ใช้งานเว็บจริง** ไม่ใช่ตอน**พัฒนา/แก้โค้ด** — สองอย่างนี้แยกกัน)

---

## 1. Clone โค้ด
```bash
git clone https://github.com/68319010012/Lib-Project.git
cd Lib-Project/BackEnd
```

## 2. ติดตั้ง Python packages
```bash
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
```
(ถ้าเป็น Mac/Linux ใช้ `source venv/bin/activate` แทนบรรทัดที่สอง)

## 3. ติดตั้ง XAMPP + สร้างฐานข้อมูล
1. โหลด XAMPP จาก https://www.apachefriends.org/ ติดตั้งแล้วเปิด **MySQL** ใน XAMPP Control Panel (ไม่ต้องเปิด Apache)
2. เปิด phpMyAdmin (`http://localhost/phpmyadmin`) → สร้างฐานข้อมูลชื่อ `library_checkin`
3. Import โครงสร้างตาราง — เลือกแท็บ **Import** ในฐานข้อมูล `library_checkin` แล้วเลือกไฟล์
   `schema.sql` จากโฟลเดอร์ `BackEnd` (โครงสร้างตารางเปล่า ไม่มีข้อมูลนักเรียนติดมาด้วย)

## 4. สร้างไฟล์ .env
สร้างไฟล์ชื่อ `.env` ในโฟลเดอร์ `BackEnd` (ไฟล์นี้ไม่ได้ติดมากับ git โดยตั้งใจ เพราะมีรหัสผ่าน)
ใส่เนื้อหา:
```
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=library_checkin

FLASK_SECRET_KEY=<สุ่มมาสัก 32 ตัวอักษร>
```
สุ่ม FLASK_SECRET_KEY ด้วย: `python -c "import secrets; print(secrets.token_hex(32))"`

(ถ้า XAMPP ตั้งรหัสผ่าน MySQL ไว้ ให้ใส่ใน DB_PASSWORD ด้วย ปกติ XAMPP ค่าเริ่มต้นไม่มีรหัสผ่าน)

## 5. นำเข้ารายชื่อนักเรียน (ถ้ามีไฟล์ Excel)
ถ้ามี `Student.xlsx` ของจริง (ไฟล์นี้ไม่ติดมากับ git เพราะเป็นข้อมูลส่วนตัวนักเรียน — ต้องส่งให้กัน
ทางอื่น เช่น ไดรฟ์ส่วนตัว ไม่ใช่ git) วางไฟล์ไว้ที่ `BackEnd/Student.xlsx` แล้วรัน:
```bash
python import_students.py
```
ถ้ายังไม่มีไฟล์จริง ข้ามขั้นตอนนี้ไปก่อนได้ — สมัครบัญชีทดสอบเองก็ได้ (แต่ตอนสมัครต้องมี
student_id ที่อยู่ในตาราง students ก่อน ไม่งั้นจะสมัครไม่ผ่าน)

## 6. รันเซิร์ฟเวอร์
```bash
python app.py
```
เปิด `http://127.0.0.1:5000` — แก้โค้ดแล้ว refresh ดูผลได้เลย (debug mode เปิดอยู่ รีโหลดอัตโนมัติ)

## 7. (แนะนำ) สร้างบัญชีแอดมิน
```bash
python create_admin.py <username>
```

---

## เวลาจะ push โค้ดที่แก้กลับขึ้น GitHub
```bash
git add -A
git commit -m "อธิบายว่าแก้อะไร"
git push
```
คนละเครื่องกัน (บ้าน/เพื่อน) ให้ `git pull` ก่อนเริ่มแก้ทุกครั้ง เพื่อดึงงานล่าสุดของอีกฝ่ายมาก่อน
ป้องกันโค้ดชนกัน
