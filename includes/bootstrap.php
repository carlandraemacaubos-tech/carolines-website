<?php
/**
 * includes/bootstrap.php
 *
 * Every page starts by including this file. It:
 *   1. loads Composer packages (PHPMailer, dotenv)
 *   2. reads the private .env settings
 *   3. sets the time zone, error handling, session and security headers
 *   4. loads all helper files
 */

define('ROOT_PATH', dirname(__DIR__));

/** Show a short, safe setup message (no secrets) and stop. */
function setup_error(string $message): void
{
    http_response_code(500);
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Setup required</title>'
        . '<body style="font-family:Arial,sans-serif;max-width:560px;margin:60px auto;line-height:1.6">'
        . '<h2>Website setup is not finished</h2><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>See README.md for the step-by-step setup.</p></body>';
    exit;
}

// 1. Composer packages -------------------------------------------------------
$autoload = ROOT_PATH . '/vendor/autoload.php';
if (!is_file($autoload)) {
    setup_error('The "vendor" folder is missing. Open a terminal in the project folder and run: composer install');
}
require $autoload;

// 2. Environment settings (.env) ---------------------------------------------
if (!is_file(ROOT_PATH . '/.env')) {
    setup_error('The ".env" file is missing. Copy ".env.example" to ".env" and fill in your details.');
}
$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
$dotenv->load();
try {
    $dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME',
                       'MAIL_PASSWORD', 'ADMIN_EMAIL', 'APP_URL'])->notEmpty();
    $dotenv->required('DB_PASS'); // may be empty (XAMPP default) but must exist
} catch (Throwable $e) {
    setup_error('Your ".env" file is missing a required setting. Compare it with ".env.example".');
}

/** Read a setting from .env, with an optional default. */
function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $default;
    return $value === null ? null : (string) $value;
}

// 3. Time zone, errors, session, headers ---------------------------------------
$tz = env('APP_TIMEZONE', 'America/New_York');
date_default_timezone_set(in_array($tz, timezone_identifiers_list(), true) ? $tz : 'America/New_York');

define('IS_LOCAL', env('APP_ENV', 'production') === 'local');
ini_set('display_errors', IS_LOCAL ? '1' : '0'); // never show PHP errors to visitors in production
ini_set('log_errors', '1');

/** URL folder where the site lives, e.g. "/party-booking-system" (empty at a domain root). */
define('BASE_PATH', rtrim((string) parse_url((string) env('APP_URL'), PHP_URL_PATH), '/'));

/** True when the visitor is using HTTPS. */
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}

// Any uncaught error: log the details privately, show a friendly page publicly.
set_exception_handler(function (Throwable $e): void {
    error_log('[PartyBooking] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Something went wrong</title>'
        . '<body style="font-family:Arial,sans-serif;max-width:560px;margin:60px auto;line-height:1.6">'
        . '<h2>Sorry, something went wrong</h2><p>Please try again in a moment, or call/text Caroline at (631) 997-9504.</p>';
    if (IS_LOCAL) {
        echo '<pre style="background:#f6f6f6;padding:12px;white-space:pre-wrap">'
            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    echo '</body>';
    exit;
});

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        session_name('cph_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => BASE_PATH === '' ? '/' : BASE_PATH,
            'secure'   => is_https(),
            'httponly' => true,      // JavaScript cannot read the session cookie
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// 4. Helper files ------------------------------------------------------------
require ROOT_PATH . '/config/database.php';
require ROOT_PATH . '/config/mailer.php';
require ROOT_PATH . '/includes/functions.php';
require ROOT_PATH . '/includes/csrf.php';
require ROOT_PATH . '/includes/auth.php';
require ROOT_PATH . '/includes/availability.php';
require ROOT_PATH . '/includes/bookings.php';
require ROOT_PATH . '/includes/mail-templates.php';
require ROOT_PATH . '/includes/notifications.php';
