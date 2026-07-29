import pandas as pd

# ไฟล์มีแถวหัวกระดาษ (ชื่อวิทยาลัย/แผนกวิชา/ภาคเรียน) 6 แถวก่อนตารางจริง
# ตารางข้อมูลนักเรียนเริ่มที่แถวที่ 7 ในไฟล์ (index 6)
df = pd.read_excel("Student.xlsx", header=6)

# แสดงชื่อคอลัมน์ทั้งหมด
print("ชื่อคอลัมน์:")
print(df.columns.tolist())

# แสดงข้อมูล 5 แถวแรก
print("\nตัวอย่างข้อมูล:")
print(df.head())
