<?php
// Sends a JSON response and stops execution — mirrors Flask's `jsonify(...), status`.
function json_response(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status): void
{
    json_response(['error' => $message], $status);
}

// Accepts JSON body or form-encoded body, same as Flask's
// `request.get_json(silent=True) or request.form`.
function request_body(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

function body_str(array $body, string $key): string
{
    return trim((string) ($body[$key] ?? ''));
}

// Mirrors app.py's gender_from_prefix().
function gender_from_prefix(?string $prefix): string
{
    $prefix = trim((string) $prefix);
    if ($prefix === 'นาย') {
        return 'ชาย';
    }
    if ($prefix === 'นาง' || $prefix === 'นางสาว') {
        return 'หญิง';
    }
    return 'ไม่ระบุ';
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Random temp password for admin-initiated resets (see handle_admin_reset_password
// in admin_handlers.php) — no email/SMTP in this system, so the admin reads this
// out to the student in person instead of a mailed reset link. Excludes visually
// ambiguous characters (0/O, 1/l/I) since it's meant to be read aloud/retyped.
function generate_temp_password(int $length = 8): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $password;
}

// MySQL DATETIME comes back as "YYYY-MM-DD HH:MM:SS" — Python's .isoformat()
// on the same value produces "YYYY-MM-DDTHH:MM:SS". Match it exactly since
// the frontend may rely on ISO-8601 parsing.
function to_isoformat(?string $mysqlDatetime): ?string
{
    return $mysqlDatetime === null ? null : str_replace(' ', 'T', $mysqlDatetime);
}

// Mirrors app.py's LIBRARY_CLOSING_TIME env var (default 17:00).
function library_closing_time(): string
{
    return env('LIBRARY_CLOSING_TIME', '17:00');
}

// วันที่ห้องสมุดเปิดทำการ เก็บเป็นเลขวันแบบ ISO-8601 (จันทร์ = 1 ... อาทิตย์ = 7)
// ค่าเริ่มต้นคือจันทร์ถึงศุกร์ ตั้งทับได้ด้วย LIBRARY_OPEN_DAYS ใน .env เผื่อ
// ภาคเรียนไหนเปิดเสาร์ หรือมีวันหยุดพิเศษ เช่น LIBRARY_OPEN_DAYS=1,2,3,4,5,6
function library_open_days(): array
{
    $raw = env('LIBRARY_OPEN_DAYS', '1,2,3,4,5');
    $days = [];
    foreach (explode(',', $raw) as $part) {
        $n = (int) trim($part);
        if ($n >= 1 && $n <= 7) {
            $days[$n] = true;
        }
    }
    // ค่าที่ตั้งมาผิดทั้งหมด (พิมพ์ผิด/ว่าง) จะกลายเป็น "ปิดทุกวัน" ซึ่งทำให้
    // ทั้งระบบเช็คอินไม่ได้เลย ถอยกลับไปใช้ค่าเริ่มต้นแทนที่จะล็อกตัวเองไว้
    return $days ? array_keys($days) : [1, 2, 3, 4, 5];
}

// วันนี้ (หรือวันของ $reference) ห้องสมุดเปิดหรือไม่
function library_is_open_on(?DateTime $reference = null): bool
{
    $reference = $reference ?? new DateTime();
    return in_array((int) $reference->format('N'), library_open_days(), true);
}

// ชื่อวันภาษาไทยของวันที่เปิดทำการ ใช้ประกอบข้อความแจ้งผู้ใช้
function library_open_days_label(): string
{
    $names = [1 => 'จันทร์', 2 => 'อังคาร', 3 => 'พุธ', 4 => 'พฤหัสบดี', 5 => 'ศุกร์', 6 => 'เสาร์', 7 => 'อาทิตย์'];
    $days = library_open_days();
    sort($days);
    // ช่วงต่อเนื่องเขียนเป็น "จันทร์-ศุกร์" อ่านง่ายกว่าไล่ชื่อทั้งห้าวัน
    $isRun = count($days) > 2 && ($days[count($days) - 1] - $days[0] + 1) === count($days);
    if ($isRun) {
        return $names[$days[0]] . '-' . $names[$days[count($days) - 1]];
    }
    return implode(', ', array_map(static fn ($d) => $names[$d], $days));
}

// ข้อความบอกว่าวันนี้ปิด ใช้ทั้งฝั่ง API และฝั่งหน้าเว็บ
function library_closed_today_message(): string
{
    return 'วันนี้ห้องสมุดปิดทำการ เปิดให้บริการวัน' . library_open_days_label();
}

// `$reference`'s date combined with library_closing_time(), as a DateTime.
function closing_datetime(DateTime $reference): DateTime
{
    [$hour, $minute] = array_map('intval', explode(':', library_closing_time()));
    $closing = clone $reference;
    $closing->setTime($hour, $minute, 0);
    return $closing;
}

// Returns the planned checkout DateTime, clamped to today's closing time —
// or null, meaning "until closing" (no specific time chosen). Mirrors
// app.py's compute_planned_checkout().
function compute_planned_checkout(?int $durationMinutes, ?string $checkoutTime, ?DateTime $now = null): ?DateTime
{
    $now = $now ?? new DateTime();
    $closing = closing_datetime($now);

    if ($durationMinutes !== null) {
        $planned = (clone $now)->modify("+{$durationMinutes} minutes");
    } elseif ($checkoutTime !== null) {
        [$hour, $minute] = array_map('intval', explode(':', $checkoutTime));
        $planned = (clone $now)->setTime($hour, $minute, 0);
    } else {
        return null;
    }

    return $planned < $closing ? $planned : $closing;
}
