<?php
/**
 * scripts/test-connections.php - checks your setup from the terminal.
 *
 *     php scripts/test-connections.php                 (tests database + Gmail login only)
 *     php scripts/test-connections.php me@example.com  (also sends a real test email there)
 *
 * It prints only OK / FAILED messages - never your password.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';

echo PHP_EOL . "=== Party Booking System - connection test ===" . PHP_EOL;

// 1) Database ---------------------------------------------------------------
try {
    $pdo = db();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $needed = ['administrators', 'bookings', 'booking_logs', 'email_logs', 'rate_limits', 'weekly_hours', 'availability_overrides', 'settings'];
    $missing = array_diff($needed, $tables);
    echo "[OK] Database connection to '" . env('DB_NAME') . "'" . PHP_EOL;
    if ($missing) {
        echo "[FAILED] Missing tables: " . implode(', ', $missing) . " - import database/schema.sql (new install) or database/migration-002-availability.sql (existing database) in phpMyAdmin." . PHP_EOL;
    } else {
        echo "[OK] All required tables exist" . PHP_EOL;
        $admins = (int) $pdo->query('SELECT COUNT(*) FROM administrators')->fetchColumn();
        echo $admins > 0
            ? "[OK] $admins administrator account(s) found" . PHP_EOL
            : "[TODO] No administrator yet - run: php scripts/create-admin.php" . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "[FAILED] Database: " . $e->getMessage() . PHP_EOL;
}

// 2) Gmail SMTP login (connects and authenticates, sends nothing) ----------------
try {
    $mail = create_mailer();
    $mail->SMTPDebug = 0;
    if ($mail->smtpConnect()) {
        echo "[OK] Gmail SMTP login worked for " . env('MAIL_USERNAME') . PHP_EOL;
        $mail->smtpClose();
    } else {
        echo "[FAILED] Gmail SMTP login was refused." . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "[FAILED] Gmail SMTP: " . $e->getMessage() . PHP_EOL;
    echo "         Check MAIL_USERNAME / MAIL_PASSWORD (must be a Google App Password) in .env" . PHP_EOL;
}

// 3) Optional: send a real test email -----------------------------------------------
$recipient = $argv[1] ?? null;
if ($recipient) {
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        echo "[FAILED] '$recipient' is not a valid email address." . PHP_EOL;
    } else {
        $result = send_email(
            $recipient,
            'Test',
            'Test email from Caroline\'s Party Helper',
            email_layout('It works!', '<p>If you can read this, your Gmail SMTP settings are correct.</p>'),
            ['type' => 'test']
        );
        echo $result['ok']
            ? "[OK] Test email accepted by Gmail for delivery to $recipient" . PHP_EOL
            : "[FAILED] Test email: " . $result['error'] . PHP_EOL;
    }
}

echo PHP_EOL . "APP_URL is set to: " . env('APP_URL') . PHP_EOL
   . "Time zone: " . date_default_timezone_get() . " (now " . date('Y-m-d H:i') . ")" . PHP_EOL . PHP_EOL;
