<?php
/**
 * includes/csrf.php
 *
 * CSRF protection: every form carries a secret token that only our own pages know.
 * A malicious website cannot guess it, so it cannot submit forms on a visitor's behalf.
 *
 * Also: single-use "form nonces" so one form render can only be submitted once
 * (this stops accidental double-submits and replayed requests).
 */

/** The session's CSRF token (created on first use). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to place inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** True when the submitted token matches the session token. */
function csrf_verify(?string $submitted): bool
{
    return is_string($submitted)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

/** Convenience: stop with a 400 error if the CSRF check fails. */
function csrf_require(): void
{
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Your session expired or the request was invalid. Please go back, refresh the page and try again.');
    }
}

/** Create a single-use token for a form (keeps the 5 most recent, so several tabs work). */
function form_nonce_issue(string $form): string
{
    $nonce = bin2hex(random_bytes(16));
    $list  = $_SESSION['nonces'][$form] ?? [];
    $list[] = $nonce;
    $_SESSION['nonces'][$form] = array_slice($list, -5);
    return $nonce;
}

/** Use up a nonce. Returns false if it was already used or never issued. */
function form_nonce_consume(string $form, ?string $nonce): bool
{
    if (!is_string($nonce) || empty($_SESSION['nonces'][$form])) {
        return false;
    }
    $index = array_search($nonce, $_SESSION['nonces'][$form], true);
    if ($index === false) {
        return false;
    }
    unset($_SESSION['nonces'][$form][$index]);
    return true;
}
