<?php
// PDO connection helper — mirrors BackEnd/db.py's DB_CONFIG defaults (XAMPP dev:
// host=localhost, user=root, no password, db=library_checkin).
function get_db_connection(): PDO
{
    $host = env('DB_HOST', 'localhost');
    $user = env('DB_USER', 'root');
    $password = env('DB_PASSWORD', '');
    $name = env('DB_NAME', 'library_checkin');

    $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Force this session's NOW()/CURRENT_TIMESTAMP onto Thailand's wall clock
    // (fixed +07:00 — no DST) instead of trusting the MySQL server's own
    // system timezone to already agree with date_default_timezone_set() above.
    // On the XAMPP dev box both happened to be Asia/Bangkok already, masking
    // this; the production host's MySQL runs on UTC, which silently shifted
    // every checkin_logs.timestamp (DEFAULT CURRENT_TIMESTAMP) and every
    // "planned_checkout_at <= NOW()" comparison in auto_checkout_sweep() by
    // 7 hours. A named zone ('Asia/Bangkok') would depend on the host's
    // mysql.time_zone tables being loaded, which shared hosting often skips —
    // a numeric offset always works.
    $conn->exec("SET time_zone = '+07:00'");

    // Pin the connection's collation to the one every table actually uses.
    // `charset=utf8mb4` in the DSN only picks the character set; the collation
    // that comes with it is whatever the server defaults to — utf8mb4_general_ci
    // on the production MariaDB, while schema.sql declares utf8mb4_unicode_ci
    // everywhere. Comparing a value carrying the connection collation against
    // one carrying the column's then fails outright:
    //   General error: 1267 Illegal mix of collations
    //   (utf8mb4_unicode_ci,COERCIBLE) and (utf8mb4_general_ci,COERCIBLE)
    // which is what took down /admin/reports (its
    // "DATE_FORMAT(c.timestamp, '%Y-%m') = ?" filter) on the live host while
    // the dev box, whose server default already matched, showed nothing wrong.
    $conn->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

    return $conn;
}
