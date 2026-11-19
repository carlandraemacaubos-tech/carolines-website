<?php
/**
 * contact/submit-inquiry.php (receives the contact page form)
 * Emails the message to Caroline's Gmail. Reply-To is the visitor, so pressing Reply writes to them.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_post();
csrf_require();

/** Back to the contact page with answers refilled. */
function back_to_contact(array $errors, array $old): void
{
    unset($old['csrf_token'], $old['website']);
    flash_set('inquiry_errors', $errors);
    flash_set('inquiry_old', $old);
    redirect('contact.php');
}

// Honeypot
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    redirect('contact.php');
}

if (!rate_limit_allow('inquiry', client_ip(), 5, 3600)) {
    back_to_contact(['form' => 'Too many messages from this device. Please wait a while, or call/text (631) 997-9504.'], $_POST);
}

$name    = trim(preg_replace('/\s+/', ' ', (string) ($_POST['name'] ?? '')));
$email   = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone   = trim((string) ($_POST['phone'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

$errors = [];
if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors['name'] = 'Please enter your name.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    $errors['email'] = 'Please enter a valid email address.';
}
if ($phone !== '' && !preg_match('/^[0-9+().\-\s]{7,30}$/', $phone)) {
    $errors['phone'] = 'The phone number looks invalid (leave it empty if you prefer).';
}
if (mb_strlen($message) < 5 || mb_strlen($message) > 2000) {
    $errors['message'] = 'Please write a message (5 to 2000 characters).';
}
if ($errors) {
    back_to_contact($errors, $_POST);
}

$html = email_layout('New website message',
    '<p>Someone sent a message through the contact page.</p>'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee5dc;font-size:14px;margin:12px 0;">'
    . '<tr><td style="padding:9px 12px;border-bottom:1px solid #f3ece4;width:30%;color:#68636a;">Name</td><td style="padding:9px 12px;border-bottom:1px solid #f3ece4;">' . e($name) . '</td></tr>'
    . '<tr><td style="padding:9px 12px;border-bottom:1px solid #f3ece4;color:#68636a;">Email</td><td style="padding:9px 12px;border-bottom:1px solid #f3ece4;">' . e($email) . '</td></tr>'
    . '<tr><td style="padding:9px 12px;border-bottom:1px solid #f3ece4;color:#68636a;">Phone</td><td style="padding:9px 12px;border-bottom:1px solid #f3ece4;">' . e($phone !== '' ? $phone : 'Not provided') . '</td></tr>'
    . '<tr><td style="padding:9px 12px;color:#68636a;vertical-align:top;">Message</td><td style="padding:9px 12px;">' . nl2br(e($message)) . '</td></tr></table>'
    . '<p style="font-size:13px;color:#68636a;">Press <strong>Reply</strong> in Gmail to answer ' . e($name) . ' directly.</p>'
);

$result = send_email(
    env('ADMIN_EMAIL'),
    'Caroline',
    'New Website Message - ' . $name,
    $html,
    ['type' => 'inquiry', 'reply_to' => $email, 'reply_to_name' => $name]
);

if ($result['ok']) {
    flash_set('notice', ['type' => 'success', 'text' => 'Thank you, ' . $name . '! Your message was sent. Caroline will reply to ' . $email . '.']);
    redirect('contact.php');
}

back_to_contact(['form' => 'Sorry, your message could not be sent right now. Please call/text (631) 997-9504 or email carolinespartyhelper@gmail.com.'], $_POST);
