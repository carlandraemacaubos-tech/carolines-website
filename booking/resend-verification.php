<?php
/**
 * booking/resend-verification.php
 * Lets a customer request a new verification email. They must give BOTH the booking reference and
 * the email address used, so strangers can't trigger emails to other people. Rate limited.
 * A new link replaces (and so cancels) any older one.
 */
require __DIR__ . '/../includes/bootstrap.php';

$message = null;      // ['type' => ..., 'text' => ...]
$ref     = strtoupper(trim((string) ($_POST['reference'] ?? $_GET['ref'] ?? '')));
$email   = strtolower(trim((string) ($_POST['email'] ?? '')));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();

    if (trim((string) ($_POST['website'] ?? '')) !== '') {                       // honeypot
        $message = ['type' => 'success', 'text' => 'Thanks.'];
    } elseif (!preg_match('/^CPH-\d{4}-[A-Z0-9]{6}$/', $ref) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = ['type' => 'error', 'text' => 'Please enter your booking reference (like CPH-2026-ABC123) and the email address you used.'];
    } elseif (!rate_limit_allow('resend_ip', client_ip(), 5, 900) || !rate_limit_allow('resend_ref', $ref, 3, 3600)) {
        $message = ['type' => 'error', 'text' => 'Too many requests. Please wait a while before asking for another email.'];
    } else {
        $booking = booking_find_by_reference($ref);

        if (!$booking || strtolower($booking['email']) !== $email) {
            $message = ['type' => 'error', 'text' => 'We could not find an unverified booking that matches those details. Please check the reference and email address.'];
        } elseif ((int) $booking['email_verified'] === 1 || $booking['booking_status'] !== 'pending_email') {
            $message = ['type' => 'info', 'text' => 'This booking\'s email is already verified, so no new link is needed. Caroline will contact you with her decision.'];
        } else {
            $newToken = booking_issue_new_token((int) $booking['id']);
            $result   = notify_customer_verification($booking, $newToken);
            if ($result['ok']) {
                booking_log((int) $booking['id'], null, 'verification_email_sent', 'New verification email sent at the customer\'s request.');
                $message = ['type' => 'success', 'text' => 'A new verification email is on its way to ' . $booking['email'] . '. The link is valid for ' . VERIFICATION_MINUTES . ' minutes, and older links no longer work.'];
            } else {
                booking_log((int) $booking['id'], null, 'verification_email_failed', 'Re-sending the verification email failed.');
                $message = ['type' => 'error', 'text' => 'We could not send the email right now. Please try again in a few minutes, or call/text Caroline at (631) 997-9504.'];
            }
        }
    }
}

$pageTitle  = 'Resend Verification Email';
$activePage = 'booking';
$noIndex    = true;
require __DIR__ . '/../includes/header.php';
?>
    <section class="page-hero">
      <div class="hero-shade"></div>
      <div class="container">
        <p class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> / Resend verification</p>
        <h1>Send a new verification email</h1>
        <p>Enter your booking reference and the email address you used on the form.</p>
      </div>
    </section>

    <section class="status-section">
      <div class="container">
        <div class="status-card">
          <div class="status-icon">✉️</div>
          <h2>Resend verification</h2>
          <?php if ($message): ?>
            <div class="alert alert-<?= e($message['type']) ?>" role="alert"><?= e($message['text']) ?></div>
          <?php endif; ?>
          <form class="inline-form" method="post" action="<?= e(url('booking/resend-verification.php')) ?>" data-single-submit novalidate>
            <?= csrf_field() ?>
            <div class="hp-field" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <label>Booking reference
              <input name="reference" value="<?= e($ref) ?>" placeholder="CPH-2026-ABC123" maxlength="20" required autocomplete="off">
            </label>
            <label>Email address
              <input name="email" type="email" value="<?= e($email) ?>" placeholder="you@example.com" maxlength="190" required autocomplete="email">
            </label>
            <button class="btn btn-red" type="submit">Send new verification email</button>
          </form>
          <div class="status-actions">
            <a class="btn btn-outline-dark" href="<?= e(url('booking.php')) ?>">Back to booking form</a>
          </div>
        </div>
      </div>
    </section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
