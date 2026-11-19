<?php
/**
 * booking/verify-email.php  (the page the "Verify My Email Address" button opens)
 *
 * Checks the token from the link, then:
 *   email_verified = 1, booking_status = pending_review, token erased,
 * and emails Caroline. The booking is NEVER approved here - only the administrator can approve.
 */
require __DIR__ . '/../includes/bootstrap.php';

$state   = 'invalid';        // invalid | expired | already | success
$booking = null;
$adminNotified = null;       // true/false/null (null = not attempted)

// Limit guessing: 30 link visits per visitor per 15 minutes.
if (!rate_limit_allow('verify_email', client_ip(), 30, 900)) {
    http_response_code(429);
    $state = 'limited';
} else {
    $token = (string) ($_GET['token'] ?? '');

    // A real token is exactly 64 hex characters; anything else is rejected without touching the database.
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $booking = booking_find_by_token($token);

        if (!$booking) {
            $state = 'invalid';      // never existed, already used (token is erased after use), or replaced
        } elseif ((int) $booking['email_verified'] === 1) {
            $state = 'already';
        } elseif (!$booking['verification_expires_at'] || new DateTimeImmutable($booking['verification_expires_at']) < new DateTimeImmutable('now')) {
            $state = 'expired';
        } elseif (booking_mark_verified($booking)) {
            $state   = 'success';
            $booking = booking_find((int) $booking['id']);

            // Tell Caroline. If this email fails, the booking is still verified and visible in the dashboard.
            $adminResult   = notify_admin_new_booking($booking);
            $adminNotified = $adminResult['ok'];
            booking_log((int) $booking['id'], null, $adminNotified ? 'admin_email_sent' : 'admin_email_failed',
                $adminNotified ? 'Administrator notified by email.' : 'Administrator email could not be sent (see email log).');
        } else {
            // Someone clicked twice at the same moment; the other request won.
            $booking = booking_find((int) $booking['id']);
            $state   = ($booking && (int) $booking['email_verified'] === 1) ? 'already' : 'invalid';
        }
    }
}

$pageTitle  = 'Email Verification';
$activePage = 'booking';
$noIndex    = true;
require __DIR__ . '/../includes/header.php';

$resendUrl = url('booking/resend-verification.php') . ($booking ? '?ref=' . urlencode($booking['booking_reference']) : '');
?>
    <section class="page-hero">
      <div class="hero-shade"></div>
      <div class="container">
        <p class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> / Email Verification</p>
        <h1>Email verification</h1>
      </div>
    </section>

    <section class="status-section">
      <div class="container">

      <?php if ($state === 'success'): ?>
        <div class="status-card ok">
          <div class="status-icon">✅</div>
          <h2>Email successfully verified!</h2>
          <p class="lead">Your email has been successfully verified! Your booking request is now waiting for administrator review.</p>
          <ol class="progress" aria-label="Booking progress">
            <li class="done">1. Request submitted</li>
            <li class="done">2. Email verified</li>
            <li class="current">3. Caroline reviews</li>
            <li>4. Decision by email</li>
          </ol>
          <div class="alert alert-info" role="status"><strong>Booking pending approval.</strong> Your booking is <u>not confirmed yet</u>. Caroline will email you as soon as she approves or declines it.</div>
          <?php if ($adminNotified === false): ?>
            <div class="alert alert-warning" role="status">We couldn't send Caroline her notification email automatically, but your request is saved and visible to her. For a faster reply you can also call/text (631) 997-9504 with your reference.</div>
          <?php endif; ?>
          <table class="summary">
            <tr><th>Booking reference</th><td class="ref"><?= e($booking['booking_reference']) ?></td></tr>
            <tr><th>Status</th><td><?= status_badge($booking['booking_status']) ?></td></tr>
            <tr class="when"><th>Starts</th><td><?= e(format_datetime($booking['start_datetime'])) ?></td></tr>
            <tr class="when"><th>Ends</th><td><?= e(format_datetime($booking['end_datetime'])) ?></td></tr>
            <tr><th>Event</th><td><?= e($booking['event_type']) ?> — <?= e($booking['event_location']) ?></td></tr>
          </table>
          <div class="status-actions">
            <a class="btn btn-dark" href="<?= e(url('index.php')) ?>">Back to Home</a>
            <a class="btn btn-outline-dark" href="<?= e(url('services.php')) ?>">View Services</a>
          </div>
        </div>

      <?php elseif ($state === 'already'): ?>
        <div class="status-card ok">
          <div class="status-icon">✔️</div>
          <h2>This email is already verified</h2>
          <p class="lead">Thank you — your email address was already confirmed<?= $booking ? ' for booking <strong>' . e($booking['booking_reference']) . '</strong>' : '' ?>. Your request is waiting for Caroline's review; you'll receive an email with her decision.</p>
          <div class="status-actions"><a class="btn btn-dark" href="<?= e(url('index.php')) ?>">Back to Home</a></div>
        </div>

      <?php elseif ($state === 'expired'): ?>
        <div class="status-card warn">
          <div class="status-icon">⏰</div>
          <h2>Verification link expired</h2>
          <p class="lead">For your security, verification links only work for <?= VERIFICATION_MINUTES ?> minutes. Your request (<strong><?= e($booking['booking_reference']) ?></strong>) is saved — request a new link to continue.</p>
          <div class="status-actions"><a class="btn btn-red" href="<?= e($resendUrl) ?>">Send me a new verification email</a></div>
        </div>

      <?php elseif ($state === 'limited'): ?>
        <div class="status-card warn">
          <div class="status-icon">⏳</div>
          <h2>Too many attempts</h2>
          <p class="lead">Please wait a few minutes and open the link from your email again.</p>
        </div>

      <?php else: ?>
        <div class="status-card bad">
          <div class="status-icon">❌</div>
          <h2>This verification link isn't valid</h2>
          <p class="lead">The link may be incorrect, may have been replaced by a newer email, or may already have been used. If you haven't verified yet, you can request a new link.</p>
          <div class="status-actions">
            <a class="btn btn-red" href="<?= e($resendUrl) ?>">Send me a new verification email</a>
            <a class="btn btn-outline-dark" href="<?= e(url('booking.php')) ?>">Start a new request</a>
          </div>
        </div>
      <?php endif; ?>

      </div>
    </section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
