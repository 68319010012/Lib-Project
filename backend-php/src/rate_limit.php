<?php
// DB-backed replacement for Flask-Limiter's in-memory "5 per minute" limit on
// /login. DB-backed (not APCu/memory) so it works correctly across multiple
// PHP-FPM/Apache worker processes and any shared host, matching the note in
// the original app.py that in-memory storage "isn't shared across worker
// processes" — here we don't have that problem at all.
function check_login_rate_limit(PDO $conn, string $ip): bool
{
    $conn->prepare('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 MINUTE)')->execute();

    $stmt = $conn->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= (NOW() - INTERVAL 1 MINUTE)');
    $stmt->execute([$ip]);
    $count = (int) $stmt->fetchColumn();

    if ($count >= 5) {
        return false;
    }

    $conn->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, NOW())')->execute([$ip]);
    return true;
}
