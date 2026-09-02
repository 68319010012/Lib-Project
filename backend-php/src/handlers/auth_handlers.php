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
    // Everything below re-checks what the signup form already constrains.
    // The form is a convenience; anything can POST here directly, so the
    // dropdowns' option lists only mean something if the API enforces them.
    if (!preg_match('/^[0-9]{5,20}$/', $studentId)) {
        json_error('รหัสนักศึกษาต้องเป็นตัวเลข 5-20 หลัก', 400);
    }
    if (!password_length_ok($password, $passwordError)) {
        json_error($passwordError, 400);
    }
    // The profile rules live in validate_student_profile() (src/constants.php)
    // because the admin edit form writes these same columns and has to accept
    // exactly what signup accepts, no more.
    $profile = [
        'prefix' => $prefix,
        'gender' => $gender,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'department' => $department,
        'level' => $level,
        'year_level' => $yearLevel,
    ];
    if (!validate_student_profile($profile, $profileError)) {
        json_error($profileError, 400);
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

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = 'student';
    $_SESSION['student_id'] = $studentId;
    json_response(['message' => 'สร้างบัญชีสำเร็จ', 'role' => 'student'], 201);
}

function handle_login(): void
{
    $conn = get_db_connection();

    $body = request_body();
    $username = body_str($body, 'username');
    $password = (string) ($body['password'] ?? '');

    if ($username === '' || $password === '') {
        json_error('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน', 400);
    }

    // Checked after the username is known so the limit can be scoped to this
    // account rather than to an IP the whole college shares.
    if (!check_login_rate_limit($conn, client_ip(), $username)) {
        json_error('เข้าสู่ระบบผิดพลาดหลายครั้งเกินไป กรุณารอสักครู่แล้วลองใหม่', 429);
    }

    $stmt = $conn->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user === false || !password_verify($password, $user['password_hash'])) {
        record_failed_login($conn, client_ip(), $username);
        json_error('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 401);
    }
    if ($user['account_status'] === 'retired') {
        json_error('บัญชีนี้ถูกระงับเนื่องจากครบกำหนด 1 ปีการศึกษา กรุณาติดต่อฝ่ายทะเบียน', 403);
    }
    if ($user['account_status'] !== 'approved') {
        json_error('บัญชียังไม่ได้รับการอนุมัติจากแอดมิน', 403);
    }

    // Session fixation defence: issue a fresh session ID now that this
    // session has gained a privilege level. Without it, an ID an attacker
    // planted in the victim's browser before login stays valid after it,
    // handing them the authenticated session. `true` deletes the old record
    // server-side so the planted ID stops working immediately.
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['student_id'] = $user['student_id'];
    // Used by reports/layout.php's print footer ("จัดทำโดย ...") — cheaper to
    // carry in the session than a users lookup on every report render.
    $_SESSION['username'] = $user['username'];
    // บัญชีที่เจ้าหน้าที่สร้างให้หรือเพิ่งรีเซ็ตรหัส จะถูกบังคับให้ตั้งรหัสของ
    // ตัวเองก่อนใช้งาน (ดู public/partials/guard.php)
    // อ่านจากแถวที่ SELECT * มาอยู่แล้ว ไม่ได้ระบุชื่อคอลัมน์ในคำสั่ง — ถ้ายัง
    // ไม่ได้เพิ่มคอลัมน์นี้ในฐานข้อมูล ค่าจะหายไปเฉย ๆ แล้วฟีเจอร์ก็แค่ปิดอยู่
    // ไม่ใช่ทั้งเว็บล็อกอินไม่ได้
    $_SESSION['must_change_password'] = !empty($user['must_change_password']);
    json_response(['message' => 'เข้าสู่ระบบสำเร็จ', 'role' => $user['role']]);
}

function handle_logout(): void
{
    $_SESSION = [];
    // session_destroy() drops the server-side record but leaves the cookie in
    // the browser, which then keeps presenting a dead session ID. Expire it
    // explicitly, reusing the params bootstrap_session() set so the delete
    // targets the same path/domain/secure combination the cookie was set with.
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
    }
    session_destroy();
    json_response(['message' => 'ออกจากระบบแล้ว']);
}

// Bare "/" has no page of its own — send browsers to the static login page.
// Mirrors the old React router's <Navigate to="/login" replace> for "/".
function handle_root_redirect(): void
{
    // "/" is the PWA's start_url, so this is the first thing the installed app
    // opens — and the installed app is the one place where the visitor is
    // usually ALREADY signed in. Sending everyone to /login regardless of
    // session meant an admin who installed the app landed on the student
    // check-in screen (login.php happily renders for a signed-in user, and
    // /dashboard has no admin guard), so staff saw a "เช็คอิน" button that was
    // never meant for them. Route by the session's role instead.
    if (isset($_SESSION['user_id'])) {
        // A staff-issued password still has to be changed before anything else,
        // exactly as partials/guard.php enforces on every other page.
        if (!empty($_SESSION['must_change_password'])) {
            header('Location: /change-password?first=1');
            exit;
        }
        $home = ($_SESSION['role'] ?? null) === 'admin' ? '/admin-dashboard' : '/dashboard';
        header("Location: $home");
        exit;
    }
    header('Location: /login');
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
