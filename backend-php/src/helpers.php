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

// MySQL DATETIME comes back as "YYYY-MM-DD HH:MM:SS" — Python's .isoformat()
// on the same value produces "YYYY-MM-DDTHH:MM:SS". Match it exactly since
// the frontend may rely on ISO-8601 parsing.
function to_isoformat(?string $mysqlDatetime): ?string
{
    return $mysqlDatetime === null ? null : str_replace(' ', 'T', $mysqlDatetime);
}
