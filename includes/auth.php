<?php
/**
 * includes/auth.php
 * Administrator login helpers. Passwords are checked with password_verify()
 * against hashes created by password_hash() - the real password is never stored.
 */

const ADMIN_IDLE_TIMEOUT = 1800;      // log out after 30 minutes of inactivity
const LOGIN_MAX_FAILURES = 5;         // failed logins allowed...
const LOGIN_WINDOW       = 900;       // ...per 15 minutes per visitor

/** Is an administrator logged in (and still active)? */
function admin_logged_in(): bool
{
    if (empty($_SESSION['admin_id'])) {
        return false;
    }
    // Expire idle sessions.
    if (time() - (int) ($_SESSION['admin_last_activity'] ?? 0) > ADMIN_IDLE_TIMEOUT) {
        admin_clear_session();
        return false;
    }
    // Session binding: a stolen cookie used from a different browser is rejected.
    if (($_SESSION['admin_ua'] ?? '') !== hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
        admin_clear_session();
        return false;
    }
    $_SESSION['admin_last_activity'] = time();
    return true;
}

/** Protect an admin page: visitors who are not logged in are sent to the login page. */
function require_admin(): array
{
    if (!admin_logged_in()) {
        flash_set('notice', ['type' => 'error', 'text' => 'Please log in to continue.']);
        redirect('admin/login.php');
    }
    return ['id' => (int) $_SESSION['admin_id'], 'username' => (string) $_SESSION['admin_username']];
}

/** After an admin action, go back to the booking page (or the dashboard if the action started there). */
function admin_back(int $bookingId): void
{
    $return = $_POST['return'] ?? '';
    if ($return === 'dashboard') {
        redirect('admin/dashboard.php');
    }
    if ($return === 'bookings') {
        redirect('admin/bookings.php');
    }
    redirect('admin/booking-details.php?id=' . $bookingId);
}

/** Try to log in. Returns true on success. */
function admin_attempt_login(string $login, string $password): bool
{
    $stmt = db()->prepare('SELECT id, username, password_hash FROM administrators WHERE username = :login OR email = :login2 LIMIT 1');
    $stmt->execute([':login' => $login, ':login2' => $login]);
    $admin = $stmt->fetch();

    // Always run password_verify (even for unknown users) so response time doesn't reveal valid usernames.
    $dummyHash = '$2y$10$VyUA/0q9bKt4v2wxjLC.uuKNYwkWr5bYd7unrS80JJ8k1Q/4/BAie';
    $hash = $admin['password_hash'] ?? $dummyHash;
    $ok = password_verify($password, $hash);

    if (!$admin || !$ok) {
        return false;
    }

    session_regenerate_id(true);          // new session ID after login (prevents session fixation)
    $_SESSION['admin_id']            = (int) $admin['id'];
    $_SESSION['admin_username']      = $admin['username'];
    $_SESSION['admin_last_activity'] = time();
    $_SESSION['admin_ua']            = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

    // Upgrade the stored hash if PHP's recommended algorithm/cost has changed.
    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE administrators SET password_hash = :h WHERE id = :id')
            ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $admin['id']]);
    }
    db()->prepare('UPDATE administrators SET last_login_at = :now WHERE id = :id')
        ->execute([':now' => date('Y-m-d H:i:s'), ':id' => $admin['id']]);

    return true;
}

/** Remove admin data from the session. */
function admin_clear_session(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_last_activity'], $_SESSION['admin_ua']);
}

/** Full logout. */
function admin_logout(): void
{
    admin_clear_session();
    session_regenerate_id(true);
}
