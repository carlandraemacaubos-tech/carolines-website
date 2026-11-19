<?php
/**
 * booking/booking-submitted.php
 * Shown right after the form is submitted: "Booking Submitted" + "Verification Email Sent".
 * Only the visitor who just submitted can see it (the booking id is stored in their session).
 */
require __DIR__ . '/../includes/bootstrap.php';

$last    = $_SESSION['last_booking'] ?? null;
$booking = $last ? booking_find((int) $last['id']) : null;
if (!$booking) {
    redirect('booking.php');
}
$emailSent = !empty($last['email_sent']);

$pageTitle  = 'Booking Request Submitted';
$activePage = 'booking';
$noIndex    = true;
require __DIR__ . '/../includes/header.php';
?>
    <section class="page-hero">
      <div class="hero-shade"></div>
      <div class="container">
        <p class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> / <a href="<?= e(url('booking.php')) ?>">Request a Booking</a> / Submitted</p>
        <h1>Booking Request Submitted</h1>
      </div>
    </section>

    <section class="status-section">
      <div class="container">
        <div class="status-card <?= $emailSent ? 'ok' : 'warn' ?>">
          <div class="status-icon"><?= $emailSent ? '✉️' : '⚠️' ?></div>
          <h2><?= $emailSent ? 'Request received — now verify your email' : 'Request saved — but we could not send your email' ?></h2>

          <?php if ($emailSent): ?>
            <p class="lead">Thank you, <?= e($booking['customer_name']) ?>! We emailed a verification link to <strong><?= e($booking['email']) ?></strong>. It expires in <?= VERIFICATION_MINUTES ?> minutes. Please check your inbox (and spam folder).</p>
          <?php else: ?>
            <div class="alert alert-warning" role="alert">Your request was saved, but we couldn't send the verification email just now. Please use the button below to try again, or call/text Caroline at (631) 997-9504 with your booking reference.</div>
          <?php endif; ?>

          <ol class="progress" aria-label="Booking progress">
            <li class="done">1. Request submitted</li>
            <li class="current">2. Verify your email</li>
            <li>3. Caroline reviews</li>
            <li>4. Decision by email</li>
          </ol>

          <div class="alert alert-info" role="status"><strong>This is a request, not a confirmed booking.</strong> Your booking is only confirmed after you verify your email <em>and</em> Caroline approves it.</div>

          <table class="summary">
            <tr><th>Booking reference</th><td class="ref"><?= e($booking['booking_reference']) ?></td></tr>
            <tr><th>Status</th><td><?= status_badge($booking['booking_status']) ?></td></tr>
            <tr class="when"><th>Start date</th><td><?= e(format_date($booking['start_datetime'])) ?></td></tr>
            <tr class="when"><th>End date</th><td><?= e(format_date($booking['end_datetime'])) ?></td></tr>
            <tr class="when"><th>Start time</th><td><?= e(format_time($booking['start_datetime'])) ?></td></tr>
            <tr class="when"><th>End time</th><td><?= e(format_time($booking['end_datetime'])) ?></td></tr>
            <tr><th>Name</th><td><?= e($booking['customer_name']) ?></td></tr>
            <tr><th>Email</th><td><?= e($booking['email']) ?></td></tr>
            <tr><th>Phone</th><td><?= e($booking['phone']) ?></td></tr>
            <tr><th>Event type</th><td><?= e($booking['event_type']) ?></td></tr>
            <tr><th>Location</th><td><?= e($booking['event_location']) ?></td></tr>
            <?php if ($booking['guests']): ?><tr><th>Estimated guests</th><td><?= e((string) $booking['guests']) ?></td></tr><?php endif; ?>
            <?php if ($booking['help_needed']): ?><tr><th>Help needed</th><td><?= e($booking['help_needed']) ?></td></tr><?php endif; ?>
            <?php if ($booking['message']): ?><tr><th>Your message</th><td><?= nl2br(e($booking['message'])) ?></td></tr><?php endif; ?>
          </table>

          <div class="status-actions">
            <?php if (!$emailSent || $booking['booking_status'] === 'pending_email'): ?>
              <a class="btn btn-red" href="<?= e(url('booking/resend-verification.php?ref=' . urlencode($booking['booking_reference']))) ?>">Didn't get the email? Send it again</a>
            <?php endif; ?>
            <a class="btn btn-dark" href="<?= e(url('index.php')) ?>">Back to Home</a>
            <a class="btn btn-outline-dark" href="<?= e(url('contact.php')) ?>">Contact Caroline</a>
          </div>
        </div>
      </div>
    </section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
