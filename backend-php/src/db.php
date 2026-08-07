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
    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
