<?php
/**
 * admin/approve-booking.php
 * Checks (all server-side): logged-in admin, POST, CSRF, booking exists, email verified,
 * still pending review, no conflicting approved booking (all inside booking_approve()).
 * Then records the action and emails the customer.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_post();
$admin = require_admin();
csrf_require();

$id = (int) ($_POST['id'] ?? 0);
if ($id < 1) {
    flash_set('notice', ['type' => 'error', 'text' => 'Invalid booking.']);
    redirect('admin/dashboard.php');
}

$result = booking_approve($id, $admin['id']);

if (!$result['ok']) {
    flash_set('notice', ['type' => 'error', 'text' => $result['message']]);
    admin_back($id);
}

// Approved. Tell the customer, and report honestly whether the email was delivered to Gmail.
$mail = notify_customer_approved($result['booking']);
booking_log($id, $admin['id'], $mail['ok'] ? 'approval_email_sent' : 'approval_email_failed',
    $mail['ok'] ? 'Approval email sent to the customer.' : 'Approval email could not be sent.');

flash_set('notice', $mail['ok']
    ? ['type' => 'success', 'text' => 'Booking approved and the customer was emailed.']
    : ['type' => 'warning', 'text' => 'Booking approved, but the email to the customer could NOT be sent. Please contact them yourself at ' . $result['booking']['email'] . '.']);
admin_back($id);
