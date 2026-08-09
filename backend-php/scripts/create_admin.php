<?php
/**
 * Create an admin account. Run this directly on the server/machine that hosts
 * the database — there is no HTTP endpoint for this, on purpose: admin
 * accounts must never be creatable over the network.
 *
 * Usage:
 *   php create_admin.php <username>
 * Then type the password when prompted. Hidden input works via `stty -echo`
 * on Linux/macOS; on Windows CLI the password will echo to the terminal (php.exe
 * has no built-in hidden-input primitive) — same platform caveat the original
 * create_admin.py accepted for its own getpass() usage.
 */

require __DIR__ . '/../src/env.php';
load_env(__DIR__ . '/../.env');
require __DIR__ . '/../src/db.php';

const MIN_PASSWORD_LENGTH = 8;

function create_admin_account(string $username, string $password): void
{
    $conn = get_db_connection();

    $stmt = $conn->prepare('SELECT user_id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch() !== false) {
        fwrite(STDERR, "Error: username '$username' already exists.\n");
        exit(1);
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $conn->prepare(
        "INSERT INTO users (username, password_hash, role, student_id, account_status)
         VALUES (?, ?, 'admin', NULL, 'approved')"
    )->execute([$username, $passwordHash]);

    echo "Admin account '$username' created.\n";
}

function read_hidden(string $prompt): string
{
    echo $prompt;
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    if ($isWindows) {
        $value = rtrim((string) fgets(STDIN), "\r\n");
        return $value;
    }
    system('stty -echo');
    $value = rtrim((string) fgets(STDIN), "\n");
    system('stty echo');
    echo "\n";
    return $value;
}

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php create_admin.php <username>\n");
    exit(1);
}

$adminUsername = trim($argv[1]);
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $adminUsername)) {
    fwrite(STDERR, "Error: username must be 3-50 chars, letters/numbers/._- only.\n");
    exit(1);
}

$adminPassword = read_hidden('New admin password: ');
$confirmPassword = read_hidden('Confirm password: ');

if ($adminPassword !== $confirmPassword) {
    fwrite(STDERR, "Error: passwords do not match.\n");
    exit(1);
}
if (strlen($adminPassword) < MIN_PASSWORD_LENGTH) {
    fwrite(STDERR, 'Error: password must be at least ' . MIN_PASSWORD_LENGTH . " characters.\n");
    exit(1);
}

create_admin_account($adminUsername, $adminPassword);
