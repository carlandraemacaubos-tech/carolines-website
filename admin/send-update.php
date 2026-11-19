<?php
/**
 * admin/send-update.php - email a short update to the customer of a booking.
 * Only the text typed here is sent. Private admin notes are never included.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_post();
$admin = require_admin();
csrf_require();

$id      = (int) ($_POST['id'] ?? 0);
$message = trim((string) ($_POST['message'] ?? ''));
$booking = $id > 0 ? booking_find($id) : null;
if (!$booking) {
    flash_set('notice', ['type' => 'error', 'text' => 'That booking could not be found.']);
    redirect('admin/bookings.php');
}
if (mb_strlen($message) < 3 || mb_strlen($message) > 2000) {
    flash_set('notice', ['type' => 'error', 'text' => 'Please write a message of 3 to 2000 characters.']);
    redirect('admin/booking-details.php?id=' . $id);
}
if (!rate_limit_allow('admin_update_mail', (string) $admin['id'], 30, 3600)) {
    flash_set('notice', ['type' => 'error', 'text' => 'Too many emails were sent in the last hour. Please wait a little.']);
    redirect('admin/booking-details.php?id=' . $id);
}

$result = notify_customer_update($booking, $message);
booking_log($id, $admin['id'], $result['ok'] ? 'update_email_sent' : 'update_email_failed',
    $result['ok'] ? 'Update email sent to the customer.' : 'Update email could not be sent.');

flash_set('notice', $result['ok']
    ? ['type' => 'success', 'text' => 'Update emailed to ' . $booking['email'] . '.']
    : ['type' => 'error', 'text' => 'The email could NOT be sent. Please contact the customer yourself at ' . $booking['email'] . '.']);
redirect('admin/booking-details.php?id=' . $id);
