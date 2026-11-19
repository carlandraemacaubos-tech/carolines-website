<?php
/**
 * admin/reject-booking.php
 * Requires a valid admin session, POST and CSRF. Only bookings that are still pending review can be rejected.
 * The optional "reason" is shown to the customer; private admin notes never are.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_post();
$admin = require_admin();
csrf_require();

$id     = (int) ($_POST['id'] ?? 0);
$reason = trim((string) ($_POST['reason'] ?? ''));
if ($id < 1) {
    flash_set('notice', ['type' => 'error', 'text' => 'Invalid booking.']);
    redirect('admin/dashboard.php');
}
if (mb_strlen($reason) > 1000) {
    flash_set('notice', ['type' => 'error', 'text' => 'The message for the customer must be 1000 characters or fewer.']);
    admin_back($id);
}

$result = booking_reject($id, $admin['id'], $reason);

if (!$result['ok']) {
    flash_set('notice', ['type' => 'error', 'text' => $result['message']]);
    admin_back($id);
}

$mail = notify_customer_rejected($result['booking'], $reason);
booking_log($id, $admin['id'], $mail['ok'] ? 'rejection_email_sent' : 'rejection_email_failed',
    $mail['ok'] ? 'Rejection email sent to the customer.' : 'Rejection email could not be sent.');

flash_set('notice', $mail['ok']
    ? ['type' => 'success', 'text' => 'Booking rejected and the customer was emailed.']
    : ['type' => 'warning', 'text' => 'Booking rejected, but the email to the customer could NOT be sent. Please contact them yourself at ' . $result['booking']['email'] . '.']);
admin_back($id);
