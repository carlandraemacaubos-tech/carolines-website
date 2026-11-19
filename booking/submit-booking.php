<?php
/**
 * booking/submit-booking.php  (receives the booking form)
 *
 * Steps:
 *   1. Security checks: POST only, CSRF token, honeypot, single-use form token, rate limit
 *   2. Validate every field on the server, including that the schedule is genuinely available
 *   3. Block duplicate requests
 *   4. Save the booking (status: pending_email) with a hashed verification token
 *   5. Email the verification link through Gmail
 *   6. Show the "Booking Submitted" page
 */
require __DIR__ . '/../includes/bootstrap.php';
require_post();

/** Send the visitor back to the form with their answers and an error message. */
function back_to_form(array $errors, array $old): void
{
    unset($old['csrf_token'], $old['form_nonce'], $old['website']);
    flash_set('booking_errors', $errors);
    flash_set('booking_old', $old);
    redirect('booking.php');
}

// 1. Security checks ---------------------------------------------------------
csrf_require();

// Bots fill in the hidden "website" field. Pretend everything worked, but do nothing.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    redirect('booking.php');
}

// Each form view can be submitted only once (stops double-clicks and replays).
if (!form_nonce_consume('booking', $_POST['form_nonce'] ?? null)) {
    back_to_form(['form' => 'This form was already submitted or has expired. Please check your email in case your request went through, or review the details below and send again.'], $_POST);
}

// At most 5 booking requests per visitor per hour.
if (!rate_limit_allow('booking_submit', client_ip(), 5, 3600)) {
    back_to_form(['form' => 'Too many booking requests from this device. Please wait a while and try again, or call/text Caroline at (631) 997-9504.'], $_POST);
}

// 2. Validate ------------------------------------------------------------------
$result = validate_booking_input($_POST);
if ($result['errors']) {
    back_to_form($result['errors'], $_POST);
}
$clean = $result['clean'];

// 2b. Availability: the chosen schedule must be genuinely open (never trust the browser's calendar).
$ctx = avail_load(substr($clean['start_datetime'], 0, 10), substr($clean['end_datetime'], 0, 10));
$problem = avail_check_schedule($ctx, $clean['start_datetime'], $clean['end_datetime'], date('Y-m-d H:i:s'));
if ($problem) {
    back_to_form(['schedule' => $problem['message']], $_POST);
}

// Also limit per email address (3 per hour) so nobody can flood one inbox with verification emails.
if (!rate_limit_allow('booking_email', $clean['email'], 3, 3600)) {
    back_to_form(['email' => 'Several requests were already sent for this email address. Please wait a while before trying again.'], $_POST);
}

// 3. Duplicate check ---------------------------------------------------------------
$duplicate = booking_duplicate_exists($clean['email'], $clean['start_datetime'], $clean['end_datetime']);
if ($duplicate) {
    $hint = $duplicate['booking_status'] === 'pending_email'
        ? 'Please check your inbox (and spam folder) for the verification email, or request a new one from the verification page.'
        : 'Caroline already has this request (reference ' . $duplicate['booking_reference'] . ') and will contact you.';
    back_to_form(['form' => 'You already submitted a request for this exact schedule. ' . $hint], $_POST);
}

// 4. Save ---------------------------------------------------------------------------------
$created = booking_create($clean);
$booking = $created['booking'];

// 5. Send the verification email. We only say "sent" if PHPMailer confirms it. -----------------
$mailResult = notify_customer_verification($booking, $created['token']);
if (!$mailResult['ok']) {
    booking_log((int) $booking['id'], null, 'verification_email_failed', 'The verification email could not be sent.');
} else {
    booking_log((int) $booking['id'], null, 'verification_email_sent', 'Verification email sent to the customer.');
}

// 6. Show confirmation page (remember which booking this visitor just made) ----------------------
$_SESSION['last_booking'] = ['id' => (int) $booking['id'], 'email_sent' => $mailResult['ok']];
redirect('booking/booking-submitted.php');
