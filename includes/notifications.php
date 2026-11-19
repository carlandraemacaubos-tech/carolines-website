<?php
/**
 * includes/notifications.php
 * One function per email. Each returns send_email()'s result:
 * ['ok' => true/false, 'error' => ...]. 'ok' is true only if PHPMailer confirmed delivery to Gmail.
 */

/** Verification link email to the customer. */
function notify_customer_verification(array $booking, string $rawToken): array
{
    $link = absolute_url('booking/verify-email.php?token=' . $rawToken);
    return send_email(
        $booking['email'],
        $booking['customer_name'],
        'Verify Your Booking Email Address (Ref ' . $booking['booking_reference'] . ')',
        email_verification_html($booking, $link),
        ['booking_id' => (int) $booking['id'], 'type' => 'verification', 'reply_to' => env('ADMIN_EMAIL')]
    );
}

/** Email to Caroline. Reply-To = the customer, so pressing Reply in Gmail writes to them. */
function notify_admin_new_booking(array $booking): array
{
    return send_email(
        env('ADMIN_EMAIL'),
        'Caroline',
        'New Verified Booking Request - ' . $booking['customer_name'] . ' (Ref ' . $booking['booking_reference'] . ')',
        email_admin_notice_html($booking),
        [
            'booking_id'    => (int) $booking['id'],
            'type'          => 'admin_notice',
            'reply_to'      => $booking['email'],
            'reply_to_name' => $booking['customer_name'],
        ]
    );
}

function notify_customer_approved(array $booking): array
{
    return send_email(
        $booking['email'],
        $booking['customer_name'],
        'Your Party Booking Request Has Been Approved! (Ref ' . $booking['booking_reference'] . ')',
        email_approved_html($booking),
        ['booking_id' => (int) $booking['id'], 'type' => 'approved', 'reply_to' => env('ADMIN_EMAIL')]
    );
}

function notify_customer_rejected(array $booking, string $reason): array
{
    return send_email(
        $booking['email'],
        $booking['customer_name'],
        'Update Regarding Your Party Booking Request (Ref ' . $booking['booking_reference'] . ')',
        email_rejected_html($booking, $reason),
        ['booking_id' => (int) $booking['id'], 'type' => 'rejected', 'reply_to' => env('ADMIN_EMAIL')]
    );
}

/** A message from Caroline about a booking. Reply-To is the admin Gmail. */
function notify_customer_update(array $booking, string $message): array
{
    return send_email(
        $booking['email'],
        $booking['customer_name'],
        'Update on Your Party Booking Request (Ref ' . $booking['booking_reference'] . ')',
        email_update_html($booking, $message),
        ['booking_id' => (int) $booking['id'], 'type' => 'update', 'reply_to' => env('ADMIN_EMAIL')]
    );
}
