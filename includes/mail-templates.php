<?php
/**
 * includes/mail-templates.php
 * Builds the HTML for each email. Styles are inline because email apps ignore <style> blocks.
 * Customer emails NEVER include private admin notes.
 */

/** Common wrapper: red header, white card, footer. */
function email_layout(string $heading, string $contentHtml): string
{
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
        . '<body style="margin:0;padding:0;background:#fff8e8;font-family:Arial,Helvetica,sans-serif;color:#242126;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff8e8;padding:24px 10px;"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #eee5dc;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="background:#c8102e;padding:22px 28px;">'
        . '<div style="font-size:20px;font-weight:bold;color:#ffffff;">🎈 Caroline\'s Party Helper</div>'
        . '<div style="font-size:11px;letter-spacing:1.5px;color:#ffd35c;margin-top:4px;">YOUR PARTY, MY HELPING HANDS</div>'
        . '</td></tr>'
        . '<tr><td style="padding:28px;font-size:15px;line-height:1.6;">'
        . '<h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#242126;">' . e($heading) . '</h1>'
        . $contentHtml
        . '</td></tr>'
        . '<tr><td style="background:#211f22;padding:18px 28px;font-size:12px;color:#c8c2c5;line-height:1.6;">'
        . 'Caroline\'s Party Helper &middot; (631) 997-9504 &middot; carolinespartyhelper@gmail.com'
        . '</td></tr></table></td></tr></table></body></html>';
}

/** Big red button (bulletproof link styled like a button). */
function email_button(string $href, string $label): string
{
    return '<p style="text-align:center;margin:26px 0;">'
        . '<a href="' . e($href) . '" style="display:inline-block;background:#c8102e;color:#ffffff;text-decoration:none;'
        . 'font-weight:bold;font-size:15px;padding:14px 28px;border-radius:30px;">' . e($label) . '</a></p>';
}

/** Yellow information box. */
function email_notice(string $html): string
{
    return '<div style="background:#fff8e8;border:1px solid #f5dfa8;border-radius:8px;padding:12px 14px;margin:18px 0;font-size:14px;color:#6b4a0f;">'
        . $html . '</div>';
}

/** Table of booking details. $forAdmin adds verification/approval status rows. */
function email_details_table(array $b, bool $forAdmin = false): string
{
    $rows = [
        'Booking reference' => '<strong>' . e($b['booking_reference']) . '</strong>',
        'Name'              => e($b['customer_name']),
    ];
    if ($forAdmin) {
        $rows['Email']  = e($b['email']);
        $rows['Phone']  = e($b['phone']);
    }
    $rows['Event type']  = e($b['event_type']);
    $rows['Location']    = e($b['event_location']);
    if (!empty($b['guests'])) {
        $rows['Estimated guests'] = e((string) $b['guests']);
    }
    if (!empty($b['help_needed'])) {
        $rows['Help needed'] = e($b['help_needed']);
    }
    $rows['Starts'] = e(format_datetime($b['start_datetime']));
    $rows['Ends']   = e(format_datetime($b['end_datetime']));
    if (!empty($b['message'])) {
        $rows['Message'] = nl2br(e($b['message']));
    }
    if ($forAdmin) {
        $rows['Email verification'] = $b['email_verified'] ? '✅ Verified' : 'Not verified';
        $rows['Approval status']    = e(BOOKING_STATUS_LABELS[$b['booking_status']] ?? $b['booking_status']);
    }

    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee5dc;border-radius:8px;margin:16px 0;font-size:14px;">';
    foreach ($rows as $label => $value) {
        $html .= '<tr><td style="padding:9px 12px;border-bottom:1px solid #f3ece4;width:38%;color:#68636a;vertical-align:top;">' . e($label) . '</td>'
              .  '<td style="padding:9px 12px;border-bottom:1px solid #f3ece4;vertical-align:top;">' . $value . '</td></tr>';
    }
    return $html . '</table>';
}

/** 1) Verification email to the customer. */
function email_verification_html(array $b, string $verifyUrl): string
{
    $body = '<p>Hi ' . e($b['customer_name']) . ',</p>'
        . '<p>Thank you for requesting help with your celebration! To finish submitting your request, please confirm that this is your email address.</p>'
        . '<p style="margin:18px 0 4px;color:#68636a;font-size:13px;">Your booking reference</p>'
        . '<p style="margin:0;font-size:22px;font-weight:bold;color:#c8102e;">' . e($b['booking_reference']) . '</p>'
        . email_button($verifyUrl, 'Verify My Email Address')
        . '<p style="font-size:13px;color:#68636a;">This link expires in ' . VERIFICATION_MINUTES . ' minutes. If the button does not work, copy and paste this address into your browser:<br>'
        . '<span style="word-break:break-all;">' . e($verifyUrl) . '</span></p>'
        . email_notice('<strong>Your booking is not confirmed yet.</strong> After you verify your email, Caroline will review your request and email you her decision.')
        . '<p style="font-size:14px;color:#68636a;">Your request:</p>'
        . email_details_table($b)
        . '<p style="font-size:13px;color:#68636a;">If you did not make this request, you can safely ignore this email.</p>';
    return email_layout('Please verify your email address', $body);
}

/** 2) Notification to the administrator (Reply-To is the customer). */
function email_admin_notice_html(array $b): string
{
    $dashboard = absolute_url('admin/booking-details.php?id=' . (int) $b['id']);
    $body = '<div style="background:#e9f7ee;border:1px solid #b9e2c6;border-radius:8px;padding:12px 14px;font-weight:bold;color:#14532d;">'
        . 'Email Verified - Pending Administrator Approval</div>'
        . '<p>A customer has verified their email address. This booking has <strong>not</strong> been approved yet.</p>'
        . email_details_table($b, true)
        . email_button($dashboard, 'Review in Dashboard')
        . '<p style="font-size:13px;color:#68636a;">Tip: press <strong>Reply</strong> in Gmail to write directly to ' . e($b['customer_name']) . ' (' . e($b['email']) . ').</p>';
    return email_layout('New verified booking request', $body);
}

/** 3) Approval email to the customer. */
function email_approved_html(array $b): string
{
    $body = '<p>Hi ' . e($b['customer_name']) . ',</p>'
        . '<p>Great news &mdash; Caroline has <strong>approved</strong> your party booking request! 🎉</p>'
        . email_details_table($b)
        . '<p>If anything about your event changes, or you have questions, just reply to this email or call/text Caroline at (631) 997-9504. Please include your booking reference.</p>'
        . '<p>We look forward to helping make your celebration a little easier!</p>';
    return email_layout('Your booking request has been approved!', $body);
}

/** 4) Rejection email to the customer. Only the optional customer-facing reason is included. */
function email_rejected_html(array $b, string $reason): string
{
    $body = '<p>Hi ' . e($b['customer_name']) . ',</p>'
        . '<p>Thank you for thinking of Caroline\'s Party Helper. Unfortunately, we are not able to accept your booking request (reference <strong>' . e($b['booking_reference']) . '</strong>) this time.</p>';
    if ($reason !== '') {
        $body .= email_notice('<strong>Note from Caroline:</strong><br>' . nl2br(e($reason)));
    }
    $body .= email_details_table($b)
        . '<p>If you would like to try a different date or time, you are welcome to submit a new request on our website, or reply to this email or call/text (631) 997-9504.</p>'
        . '<p>Thank you for your understanding.</p>';
    return email_layout('An update on your booking request', $body);
}

/** 5) A status update / message written by the administrator (private notes are never included). */
function email_update_html(array $b, string $message): string
{
    $body = '<p>Hi ' . e($b['customer_name']) . ',</p>'
        . '<p>Here is an update about your booking request (reference <strong>' . e($b['booking_reference']) . '</strong>):</p>'
        . email_notice(nl2br(e($message)))
        . '<p style="font-size:14px;">Current status: <strong>' . e(BOOKING_STATUS_LABELS[$b['booking_status']] ?? $b['booking_status']) . '</strong></p>'
        . email_details_table($b)
        . '<p>You can reply to this email or call/text Caroline at (631) 997-9504.</p>';
    return email_layout('An update on your booking request', $body);
}
