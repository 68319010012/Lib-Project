<?php
// Ports app.py's /register, /login, /logout exactly (messages, status codes,
// validation order all preserved).

function handle_register(): void
{
    $body = request_body();
    $studentId = body_str($body, 'student_id');
    $username = $studentId;
    $password = (string) ($body['password'] ?? '');
    $prefix = body_str($body, 'prefix');
    $gender = body_str($body, 'gender');
    $firstName = body_str($body, 'first_name');
    $lastName = body_str($body, 'last_name');
    $department = body_str($body, 'department');
    $level = body_str($body, 'level');
    $yearLevel = body_str($body, 'year_level');

    if ($studentId === '' || $password === '') {
        json_error('กรุณากรอกรหัสนักศึกษาและรหัสผ่าน', 400);
    }
    if ($prefix === '' || $firstName === '' || $lastName === '' || $department === '') {
        json_error('กรุณากรอกคำนำหน้า ชื่อ นามสกุล และแผนกวิชา', 400);
    }
    if (!in_array($gender, ['male', 'female'], true)) {
        json_error('กรุณาเลือกเพศ', 400);
    }
    if (!in_array($level, ['ปวช.', 'ปวส.'], true)) {
        json_error('ระดับชั้นต้องเป็น ปวช. หรือ ปวส.', 400);
    }
    $validYears = $level === 'ปวช.' ? ['1', '2', '3'] : ['1', '2'];
    if (!in_array($yearLevel, $validYears, true)) {
        json_error("ชั้นปีของ $level ต้องเป็นหนึ่งใน " . implode(', ', $validYears), 400);
    }

    $conn = get_db_connection();
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE student_id = ?');
        $stmt->execute([$studentId]);
        if ($stmt->fetch() !== false) {
            $conn->rollBack();
            json_error('นักศึกษาคนนี้มีบัญชีอยู่แล้ว', 409);
        }

        // Student profile is entered manually at signup — upsert rather than require
        // the student_id to already exist in the imported roster.
        $conn->prepare(
            'INSERT INTO students (student_id, prefix, gender, first_name, last_name, department, level, year_level)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 prefix = VALUES(prefix), gender = VALUES(gender), first_name = VALUES(first_name), last_name = VALUES(last_name),
                 department = VALUES(department), level = VALUES(level), year_level = VALUES(year_level)'
        )->execute([$studentId, $prefix, $gender, $firstName, $lastName, $department, $level, $yearLevel]);

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $conn->prepare(
            "INSERT INTO users (username, password_hash, role, student_id, account_status)
             VALUES (?, ?, 'student', ?, 'approved')"
        )->execute([$username, $passwordHash, $studentId]);

        $userId = (int) $conn->lastInsertId();
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = 'student';
    $_SESSION['student_id'] = $studentId;
    json_response(['message' => 'สร้างบัญชีสำเร็จ', 'role' => 'student'], 201);
}

function handle_login(): void
{
    $conn = get_db_connection();
    if (!check_login_rate_limit($conn, client_ip())) {
        json_error('เข้าสู่ระบบผิดพลาดหลายครั้งเกินไป กรุณารอสักครู่แล้วลองใหม่', 429);
    }

    $body = request_body();
    $username = body_str($body, 'username');
    $password = (string) ($body['password'] ?? '');

    if ($username === '' || $password === '') {
        json_error('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน', 400);
    }

    $stmt = $conn->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user === false || !password_verify($password, $user['password_hash'])) {
        json_error('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 401);
    }
    if ($user['account_status'] === 'retired') {
        json_error('บัญชีนี้ถูกระงับเนื่องจากครบกำหนด 1 ปีการศึกษา กรุณาติดต่อฝ่ายทะเบียน', 403);
    }
    if ($user['account_status'] !== 'approved') {
        json_error('บัญชียังไม่ได้รับการอนุมัติจากแอดมิน', 403);
    }

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['student_id'] = $user['student_id'];
    // Used by reports/layout.php's print footer ("จัดทำโดย ...") — cheaper to
    // carry in the session than a users lookup on every report render.
    $_SESSION['username'] = $user['username'];
    json_response(['message' => 'เข้าสู่ระบบสำเร็จ', 'role' => $user['role']]);
}

function handle_logout(): void
{
    $_SESSION = [];
    session_destroy();
    json_response(['message' => 'ออกจากระบบแล้ว']);
}

// Bare "/" has no page of its own — send browsers to the static login page.
// Mirrors the old React router's <Navigate to="/login" replace> for "/".
function handle_root_redirect(): void
{
    header('Location: /login.php');
    exit;
}

// Suspends any student account whose users.created_at is a full academic
// year (365 days) in the past. Admin accounts are never retired. Called
// once per request (see public/index.php) — see auto_checkout_sweep() in
// checkin_handlers.php for why a background scheduler isn't needed here.
function retire_expired_accounts_sweep(): void
{
    $conn = get_db_connection();
    $conn->exec(
        "UPDATE users
         SET account_status = 'retired'
         WHERE role = 'student' AND account_status = 'approved'
           AND created_at <= NOW() - INTERVAL 1 YEAR"
    );
}
