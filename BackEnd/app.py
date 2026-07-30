import calendar
import os
from datetime import datetime
from functools import wraps

import bcrypt
from dotenv import load_dotenv
from flask import Flask, jsonify, redirect, render_template, request, session, url_for
from flask_limiter import Limiter
from flask_limiter.util import get_remote_address

from db import get_db_connection

load_dotenv()

app = Flask(__name__)
app.secret_key = os.environ.get("FLASK_SECRET_KEY", "dev-secret-key-change-before-production")
# NOTE: dev-only default. Set FLASK_SECRET_KEY in .env with a real random value before deploying.

limiter = Limiter(get_remote_address, app=app, storage_uri="memory://")
# NOTE: in-memory storage resets on restart and isn't shared across worker
# processes — fine for the dev server; switch to Redis storage_uri before
# running multiple workers in production.


@app.errorhandler(429)
def rate_limit_exceeded(_error):
    return jsonify(error="เข้าสู่ระบบผิดพลาดหลายครั้งเกินไป กรุณารอสักครู่แล้วลองใหม่"), 429

def gender_from_prefix(prefix):
    prefix = (prefix or "").strip()
    if prefix == "นาย":
        return "ชาย"
    if prefix in ("นาง", "นางสาว"):
        return "หญิง"
    return "ไม่ระบุ"


def login_required(view):
    @wraps(view)
    def wrapped(*args, **kwargs):
        if "user_id" not in session:
            return jsonify(error="กรุณาเข้าสู่ระบบ"), 401
        return view(*args, **kwargs)

    return wrapped


def admin_required(view):
    @wraps(view)
    def wrapped(*args, **kwargs):
        if session.get("role") != "admin":
            return jsonify(error="ต้องใช้สิทธิ์แอดมิน"), 403
        return view(*args, **kwargs)

    return wrapped


def page_login_required(view):
    """Like login_required, but redirects to /login instead of returning JSON 401 —
    for page routes navigated to directly by the browser, not fetch()."""

    @wraps(view)
    def wrapped(*args, **kwargs):
        if "user_id" not in session:
            return redirect(url_for("login_page"))
        return view(*args, **kwargs)

    return wrapped


def page_admin_required(view):
    @wraps(view)
    def wrapped(*args, **kwargs):
        if session.get("role") != "admin":
            return redirect(url_for("dashboard_page"))
        return view(*args, **kwargs)

    return wrapped


@app.route("/")
def index():
    return redirect(url_for("login_page"))


@app.route("/login")
def login_page():
    return render_template("pages/login.html")


@app.route("/signup")
def signup_page():
    return render_template("pages/signup.html")


@app.route("/dashboard")
@page_login_required
def dashboard_page():
    return render_template("pages/dashboard.html")


@app.route("/admin/dashboard")
@page_login_required
@page_admin_required
def admin_dashboard_page():
    return render_template("pages/admin_dashboard.html")


@app.route("/admin/members-management")
@page_login_required
@page_admin_required
def members_management_page():
    return render_template("pages/members_management.html")


@app.route("/admin/attendance-logs")
@page_login_required
@page_admin_required
def attendance_logs_page():
    return render_template("pages/attendance_logs.html")


@app.route("/profile")
@page_login_required
def edit_profile_page():
    return render_template("pages/edit_profile.html")


@app.route("/register", methods=["POST"])
def register():
    data = request.get_json(silent=True) or request.form
    student_id = (data.get("student_id") or "").strip()
    username = student_id
    password = data.get("password") or ""
    prefix = (data.get("prefix") or "").strip()
    first_name = (data.get("first_name") or "").strip()
    last_name = (data.get("last_name") or "").strip()
    department = (data.get("department") or "").strip()
    level = (data.get("level") or "").strip()
    year_level = (data.get("year_level") or "").strip()

    if not student_id or not password:
        return jsonify(error="กรุณากรอกรหัสนักศึกษาและรหัสผ่าน"), 400
    if not prefix or not first_name or not last_name or not department:
        return jsonify(error="กรุณากรอกคำนำหน้า ชื่อ นามสกุล และแผนกวิชา"), 400
    if level not in ("ปวช", "ปวส"):
        return jsonify(error="ระดับชั้นต้องเป็น ปวช หรือ ปวส"), 400
    valid_years = ("1", "2", "3") if level == "ปวช" else ("1", "2")
    if year_level not in valid_years:
        return jsonify(error=f"ชั้นปีของ {level} ต้องเป็นหนึ่งใน {', '.join(valid_years)}"), 400

    conn = get_db_connection()
    cur = conn.cursor(dictionary=True)
    try:
        cur.execute("SELECT user_id FROM users WHERE student_id = %s", (student_id,))
        if cur.fetchone() is not None:
            return jsonify(error="นักศึกษาคนนี้มีบัญชีอยู่แล้ว"), 409

        # Student profile is entered manually at signup — upsert rather than require
        # the student_id to already exist in the imported roster.
        cur.execute(
            """
            INSERT INTO students (student_id, prefix, first_name, last_name, department, level, year_level)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                prefix = VALUES(prefix), first_name = VALUES(first_name), last_name = VALUES(last_name),
                department = VALUES(department), level = VALUES(level), year_level = VALUES(year_level)
            """,
            (student_id, prefix, first_name, last_name, department, level, year_level),
        )

        password_hash = bcrypt.hashpw(password.encode("utf-8"), bcrypt.gensalt()).decode("utf-8")

        cur.execute(
            """
            INSERT INTO users (username, password_hash, role, student_id, account_status)
            VALUES (%s, %s, 'student', %s, 'approved')
            """,
            (username, password_hash, student_id),
        )
        conn.commit()
        user_id = cur.lastrowid
    finally:
        cur.close()
        conn.close()

    session["user_id"] = user_id
    session["role"] = "student"
    session["student_id"] = student_id
    return jsonify(message="สร้างบัญชีสำเร็จ", role="student"), 201


@app.route("/login", methods=["POST"])
@limiter.limit("5 per minute")
def login():
    data = request.get_json(silent=True) or request.form
    username = (data.get("username") or "").strip()
    password = data.get("password") or ""

    if not username or not password:
        return jsonify(error="กรุณากรอกชื่อผู้ใช้และรหัสผ่าน"), 400

    conn = get_db_connection()
    cur = conn.cursor(dictionary=True)
    try:
        cur.execute("SELECT * FROM users WHERE username = %s", (username,))
        user = cur.fetchone()
    finally:
        cur.close()
        conn.close()

    if user is None or not bcrypt.checkpw(password.encode("utf-8"), user["password_hash"].encode("utf-8")):
        return jsonify(error="ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"), 401
    if user["account_status"] != "approved":
        return jsonify(error="บัญชียังไม่ได้รับการอนุมัติจากแอดมิน"), 403

    session["user_id"] = user["user_id"]
    session["role"] = user["role"]
    session["student_id"] = user["student_id"]
    return jsonify(message="เข้าสู่ระบบสำเร็จ", role=user["role"])


@app.route("/logout", methods=["POST"])
def logout():
    session.clear()
    return jsonify(message="ออกจากระบบแล้ว")


@app.route("/checkin", methods=["POST"])
@login_required
def checkin():
    user_id = session["user_id"]
    conn = get_db_connection()
    cur = conn.cursor(dictionary=True)
    try:
        cur.execute(
            "SELECT type FROM checkin_logs WHERE user_id = %s ORDER BY log_id DESC LIMIT 1",
            (user_id,),
        )
        last = cur.fetchone()
        next_type = "out" if last and last["type"] == "in" else "in"

        cur.execute(
            "INSERT INTO checkin_logs (user_id, type) VALUES (%s, %s)",
            (user_id, next_type),
        )
        conn.commit()
        checkin_message = "เช็คอินสำเร็จ" if next_type == "in" else "เช็คเอาต์สำเร็จ"
        return jsonify(message=checkin_message, type=next_type)
    finally:
        cur.close()
        conn.close()


@app.route("/me", methods=["GET"])
@login_required
def me():
    conn = get_db_connection()
    cur = conn.cursor(dictionary=True)
    try:
        cur.execute(
            """
            SELECT u.user_id, u.username, u.role, u.account_status,
                   s.student_id, s.prefix, s.first_name, s.last_name,
                   s.department, s.level, s.year_level, s.room
            FROM users u
            LEFT JOIN students s ON s.student_id = u.student_id
            WHERE u.user_id = %s
            """,
            (session["user_id"],),
        )
        return jsonify(cur.fetchone())
    finally:
        cur.close()
        conn.close()


@app.route("/profile/change-password", methods=["POST"])
@login_required
def change_password():
    data = request.get_json(silent=True) or request.form
    current_password = data.get("current_password") or ""
    new_password = data.get("new_password") or ""

    if not current_password or not new_password:
        return jsonify(error="กรุณากรอกรหัสผ่านปัจจุบันและรหัสผ่านใหม่"), 400
    if len(new_password) < 8:
        return jsonify(error="รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร"), 400

    conn = get_db_connection()
    cur = conn.cursor(dictionary=True)
    try:
        cur.execute("SELECT password_hash FROM users WHERE user_id = %s", (session["user_id"],))
        user = cur.fetchone()
        if user is None or not bcrypt.checkpw(
            current_password.encode("utf-8"), user["password_hash"].encode("utf-8")
        ):
            return jsonify(error="รหัสผ่านปัจจุบันไม่ถูกต้อง"), 401

        new_hash = bcrypt.hashpw(new_password.encode("utf-8"), bcrypt.gensalt()).decode("utf-8")
        cur.execute("UPDATE users SET password_hash = %s WHERE user_id = %s", (new_hash, session["user_id"]))
        conn.commit()
        return jsonify(message="เปลี่ยนรหัสผ่านสำเร็จ")
    finally:
        cur.close()
        conn.close()


@app.route("/me/history", methods=["GET"])
@login_required
def my_history():
    try:
        limit = int(request.args.get("limit", 20))
    except ValueError:
        limit = 20
    limit = max(1, min(limit, 100))

    conn = get_db_connection()
    cur = conn.cursor(dictionary=True)
    try:
        cur.execute(
            "SELECT type, timestamp FROM checkin_logs WHERE user_id = %s ORDER BY log_id DESC LIMIT %s",
            (session["user_id"], limit),
        )
        rows = cur.fetchall()
        for row in rows:
            row["timestamp"] = row["timestamp"].isoformat()
        return jsonify(rows)
    finally:
        cur.close()
        conn.close()


@app.route("/admin/members", methods=["GET"])
@login_required
@admin_required
def admin_members():
    search = request.args.get("search", "").strip()
    department = request.args.get("department", "").strip()
    level = request.args.get("level", "").strip()
    year_level = request.args.get("year_level", "").strip()

    conditions = ["u.account_status = 'approved'"]
    params = []
    if search:
        conditions.append(
            "(s.first_name LIKE %s OR s.last_name LIKE %s OR s.student_id LIKE %s OR u.username LIKE %s)"
        )
        like = f"%{search}%"
        params.extend([like, like, like, like])
    if department:
        conditions.append("s.department = %s")
        params.append(department)
    if level:
        conditions.append("s.level = %s")
        params.append(level)
    if year_level:
        conditions.append("s.year_level = %s")
        params.append(year_level)

    where_clause = " AND ".join(conditions)

    sql = f"""
        SELECT u.user_id, u.username, s.student_id, s.prefix, s.first_name, s.last_name,
               s.department, s.level, s.year_level, s.room,
               (SELECT MAX(c.timestamp) FROM checkin_logs c WHERE c.user_id = u.user_id) AS last_visit
        FROM users u
        JOIN students s ON s.student_id = u.student_id
        WHERE {where_clause}
        ORDER BY s.first_name, s.last_name
    """

    conn = get_db_connection()
    cur = conn.cursor(dictionary=True)
    try:
        cur.execute(sql, params)
        rows = cur.fetchall()
        for row in rows:
            row["last_visit"] = row["last_visit"].isoformat() if row["last_visit"] else None
        return jsonify(rows)
    finally:
        cur.close()
        conn.close()


@app.route("/admin/reports", methods=["GET"])
@login_required
@admin_required
def admin_reports():
    date = request.args.get("date")
    month = request.args.get("month")
    academic_year = request.args.get("academic_year")

    conditions = []
    params = []
    if date:
        conditions.append("DATE(c.timestamp) = %s")
        params.append(date)
    if month:
        conditions.append("DATE_FORMAT(c.timestamp, '%Y-%m') = %s")
        params.append(month)
    if academic_year:
        conditions.append("s.academic_year = %s")
        params.append(academic_year)

    where_clause = f"WHERE {' AND '.join(conditions)}" if conditions else ""

    sql = f"""
        SELECT s.student_id, s.prefix, s.first_name, s.last_name, s.department, s.level, s.year_level,
               c.type, c.timestamp
        FROM checkin_logs c
        JOIN users u ON u.user_id = c.user_id
        JOIN students s ON s.student_id = u.student_id
        {where_clause}
        ORDER BY c.timestamp DESC
    """

    conn = get_db_connection()
    cur = conn.cursor(dictionary=True)
    try:
        cur.execute(sql, params)
        rows = cur.fetchall()
        for row in rows:
            row["timestamp"] = row["timestamp"].isoformat()
        return jsonify(rows)
    finally:
        cur.close()
        conn.close()


@app.route("/admin/reports/print", methods=["GET"])
@login_required
@admin_required
def admin_reports_select():
    today = datetime.now().strftime("%Y-%m-%d")
    this_month = datetime.now().strftime("%Y-%m")
    return render_template("reports_select.html", today=today, this_month=this_month)


@app.route("/admin/reports/print/daily", methods=["GET"])
@login_required
@admin_required
def admin_report_daily():
    date = request.args.get("date") or datetime.now().strftime("%Y-%m-%d")

    sql = """
        SELECT s.student_id, s.prefix, s.first_name, s.last_name, s.department, s.level, s.year_level,
               c.type, c.timestamp
        FROM checkin_logs c
        JOIN users u ON u.user_id = c.user_id
        JOIN students s ON s.student_id = u.student_id
        WHERE DATE(c.timestamp) = %s
        ORDER BY s.student_id, c.timestamp
    """
    conn = get_db_connection()
    cur = conn.cursor(dictionary=True)
    try:
        cur.execute(sql, (date,))
        logs = cur.fetchall()
    finally:
        cur.close()
        conn.close()

    by_student = {}
    for log in logs:
        row = by_student.setdefault(
            log["student_id"],
            {
                "student_id": log["student_id"],
                "prefix": log["prefix"],
                "first_name": log["first_name"],
                "last_name": log["last_name"],
                "gender": gender_from_prefix(log["prefix"]),
                "department": log["department"],
                "level": log["level"],
                "year_level": log["year_level"],
                "time_in": None,
                "time_out": None,
            },
        )
        time_str = log["timestamp"].strftime("%H:%M:%S")
        if log["type"] == "in" and row["time_in"] is None:
            row["time_in"] = time_str
        if log["type"] == "out":
            row["time_out"] = time_str

    rows = sorted(by_student.values(), key=lambda r: r["student_id"])
    return render_template("report_daily.html", date=date, rows=rows)


@app.route("/admin/reports/print/monthly", methods=["GET"])
@login_required
@admin_required
def admin_report_monthly():
    month = request.args.get("month") or datetime.now().strftime("%Y-%m")

    sql = """
        SELECT s.student_id, s.prefix, s.first_name, s.last_name, s.department, s.level, s.year_level,
               COUNT(CASE WHEN c.type = 'in' THEN 1 END) AS checkin_count,
               MAX(c.timestamp) AS last_checkin
        FROM checkin_logs c
        JOIN users u ON u.user_id = c.user_id
        JOIN students s ON s.student_id = u.student_id
        WHERE DATE_FORMAT(c.timestamp, '%Y-%m') = %s
        GROUP BY s.student_id, s.prefix, s.first_name, s.last_name, s.department, s.level, s.year_level
        ORDER BY s.student_id
    """
    conn = get_db_connection()
    cur = conn.cursor(dictionary=True)
    try:
        cur.execute(sql, (month,))
        rows = cur.fetchall()
    finally:
        cur.close()
        conn.close()

    for row in rows:
        row["gender"] = gender_from_prefix(row["prefix"])
        row["last_checkin"] = row["last_checkin"].strftime("%Y-%m-%d %H:%M:%S") if row["last_checkin"] else "-"

    return render_template("report_monthly.html", month=month, rows=rows)


@app.route("/admin/reports/print/department", methods=["GET"])
@login_required
@admin_required
def admin_report_department():
    academic_year = request.args.get("academic_year")

    conditions = []
    params = []
    if academic_year:
        conditions.append("s.academic_year = %s")
        params.append(academic_year)
    where_clause = f"WHERE {' AND '.join(conditions)}" if conditions else ""

    sql = f"""
        SELECT s.department,
               COUNT(DISTINCT s.student_id) AS student_count,
               COUNT(c.log_id) AS checkin_count
        FROM checkin_logs c
        JOIN users u ON u.user_id = c.user_id
        JOIN students s ON s.student_id = u.student_id
        {where_clause}
        GROUP BY s.department
        ORDER BY s.department
    """
    conn = get_db_connection()
    cur = conn.cursor(dictionary=True)
    try:
        cur.execute(sql, params)
        rows = cur.fetchall()
    finally:
        cur.close()
        conn.close()

    return render_template("report_department.html", academic_year=academic_year, rows=rows)


@app.route("/admin/reports/print/dashboard", methods=["GET"])
@login_required
@admin_required
def admin_report_dashboard():
    month = request.args.get("month") or datetime.now().strftime("%Y-%m")

    sql = """
        SELECT s.student_id, s.department, c.timestamp
        FROM checkin_logs c
        JOIN users u ON u.user_id = c.user_id
        JOIN students s ON s.student_id = u.student_id
        WHERE DATE_FORMAT(c.timestamp, '%Y-%m') = %s
    """
    conn = get_db_connection()
    cur = conn.cursor(dictionary=True)
    try:
        cur.execute(sql, (month,))
        rows = cur.fetchall()
    finally:
        cur.close()
        conn.close()

    total_events = len(rows)
    unique_students = len({row["student_id"] for row in rows})

    day_counts = {}
    dept_counts = {}
    for row in rows:
        day_key = row["timestamp"].strftime("%d")
        day_counts[day_key] = day_counts.get(day_key, 0) + 1
        dept = row["department"] or "ไม่ระบุแผนก"
        dept_counts[dept] = dept_counts.get(dept, 0) + 1

    days_with_data = len(day_counts)
    avg_daily = round(total_events / days_with_data) if days_with_data else 0

    year, mon = (int(part) for part in month.split("-"))
    days_in_month = calendar.monthrange(year, mon)[1]
    daily_trend = [
        {"day": f"{d:02d}", "count": day_counts.get(f"{d:02d}", 0)} for d in range(1, days_in_month + 1)
    ]
    max_daily = max((d["count"] for d in daily_trend), default=0)
    for d in daily_trend:
        d["pct"] = round((d["count"] / max_daily) * 100) if max_daily else 0

    dept_breakdown = sorted(dept_counts.items(), key=lambda item: -item[1])[:8]
    max_dept = dept_breakdown[0][1] if dept_breakdown else 0
    dept_breakdown = [
        {"name": name, "count": count, "pct": round((count / max_dept) * 100) if max_dept else 0}
        for name, count in dept_breakdown
    ]

    busiest_day = None
    if day_counts:
        busiest_key, busiest_count = max(day_counts.items(), key=lambda item: item[1])
        busiest_day = {"day": busiest_key, "count": busiest_count}

    return render_template(
        "report_dashboard.html",
        month=month,
        total_events=total_events,
        unique_students=unique_students,
        avg_daily=avg_daily,
        busiest_day=busiest_day,
        daily_trend=daily_trend,
        dept_breakdown=dept_breakdown,
    )


if __name__ == "__main__":
    app.run(debug=True)
