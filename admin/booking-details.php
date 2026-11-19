<?php
/**
 * admin/booking-details.php - everything about one booking, plus the decision forms:
 * Approve, Reject (with optional message for the customer) and private admin notes.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id      = (int) ($_GET['id'] ?? 0);
$booking = $id > 0 ? booking_find($id) : null;
if (!$booking) {
    flash_set('notice', ['type' => 'error', 'text' => 'That booking could not be found.']);
    redirect('admin/bookings.php');
}

$history   = booking_history($id);
$isPending = $booking['booking_status'] === 'pending_review' && (int) $booking['email_verified'] === 1;
// Warn about overlaps with already-approved bookings (the approval is also blocked server-side).
$conflicts = $isPending ? booking_conflicts($booking['start_datetime'], $booking['end_datetime'], $id) : [];
// Is the schedule still open (not blocked / outside hours)? Approval is refused if not.
$schedProblem = null;
if ($isPending) {
    $ctx = avail_load(substr($booking['start_datetime'], 0, 10), substr($booking['end_datetime'], 0, 10));
    $schedProblem = avail_check_schedule($ctx, $booking['start_datetime'], $booking['end_datetime'], date('Y-m-d H:i:s'), ['skip_past' => true, 'skip_grid' => true]);
    if ($schedProblem && $schedProblem['code'] === 'booked') {
        $schedProblem = null;                 // already explained by the conflict box
    }
}
$blockApprove = $conflicts || $schedProblem;

$mailtoSubject = rawurlencode('Re: Your party booking request (Ref ' . $booking['booking_reference'] . ')');

$pageTitle   = 'Booking ' . $booking['booking_reference'];
$adminActive = 'bookings';
require __DIR__ . '/../includes/admin-header.php';
?>
    <p class="small"><a href="<?= e(url('admin/bookings.php')) ?>">← Back to bookings</a></p>
    <div class="page-head">
      <div>
        <h1><?= e($booking['booking_reference']) ?></h1>
        <p><?= status_badge($booking['booking_status']) ?> &nbsp; <?= email_badge((bool) $booking['email_verified']) ?></p>
      </div>
      <a class="btn btn-dark" href="mailto:<?= e($booking['email']) ?>?subject=<?= $mailtoSubject ?>">✉ Email <?= e($booking['customer_name']) ?></a>
    </div>

    <div class="cols">
      <div>
        <div class="card">
          <h2>Customer</h2>
          <dl class="details">
            <dt>Name</dt><dd><?= e($booking['customer_name']) ?></dd>
            <dt>Email</dt><dd><a href="mailto:<?= e($booking['email']) ?>"><?= e($booking['email']) ?></a> <?= email_badge((bool) $booking['email_verified']) ?></dd>
            <dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $booking['phone'])) ?>"><?= e($booking['phone']) ?></a></dd>
            <dt>Request sent</dt><dd><?= e(format_datetime($booking['created_at'])) ?></dd>
            <?php if ($booking['verified_at']): ?><dt>Email verified</dt><dd><?= e(format_datetime($booking['verified_at'])) ?></dd><?php endif; ?>
          </dl>
        </div>

        <div class="card">
          <h2>Event</h2>
          <dl class="details">
            <dt>Type</dt><dd><?= e($booking['event_type']) ?></dd>
            <dt>Location</dt><dd><?= e($booking['event_location']) ?></dd>
            <dt class="when-row">Starts</dt><dd class="when-row"><?= e(format_datetime($booking['start_datetime'])) ?></dd>
            <dt class="when-row">Ends</dt><dd class="when-row"><?= e(format_datetime($booking['end_datetime'])) ?></dd>
            <dt>Guests</dt><dd><?= $booking['guests'] ? e((string) $booking['guests']) : '<span class="muted">Not given</span>' ?></dd>
            <dt>Help needed</dt><dd><?= $booking['help_needed'] ? e($booking['help_needed']) : '<span class="muted">Not given</span>' ?></dd>
            <dt>Message</dt><dd><?= $booking['message'] ? nl2br(e($booking['message'])) : '<span class="muted">None</span>' ?></dd>
            <?php if ($booking['rejection_reason']): ?><dt>Message sent to customer with rejection</dt><dd><?= nl2br(e($booking['rejection_reason'])) ?></dd><?php endif; ?>
          </dl>
        </div>

        <div class="card">
          <h2>History</h2>
          <ul class="timeline">
            <?php foreach ($history as $item): ?>
              <li class="<?= $item['ok'] ? 'ok' : 'bad' ?>">
                <strong><?= e($item['title']) ?></strong>
                <?php if ($item['text'] !== ''): ?><br><?= e($item['text']) ?><?php endif; ?>
                <small><?= e(format_datetime($item['at'])) ?></small>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <div>
        <div class="card" id="decision">
          <h2>Decision</h2>

          <?php if ($conflicts): ?>
            <div class="alert alert-warning" role="alert">
              <strong>Schedule conflict.</strong> This overlaps with approved booking(s):
              <?php foreach ($conflicts as $c): ?>
                <br>• <a href="<?= e(url('admin/booking-details.php?id=' . (int) $c['id'])) ?>"><?= e($c['booking_reference']) ?></a>
                (<?= e(format_short($c['start_datetime'])) ?> – <?= e(format_short($c['end_datetime'])) ?>)
              <?php endforeach; ?>
              <br>Approving is blocked while this overlap exists. The calendar shows the other booking.
            </div>
          <?php endif; ?>

          <?php if ($schedProblem): ?>
            <div class="alert alert-warning" role="alert">
              <strong>Schedule is no longer open.</strong> <?= e($schedProblem['message']) ?>
              <br><a href="<?= e(url('admin/calendar-day.php?date=' . substr($booking['start_datetime'], 0, 10))) ?>">Check the availability calendar</a>.
            </div>
          <?php endif; ?>

          <?php if ($isPending): ?>
            <div class="decision-box">
              <h3>Approve</h3>
              <p class="small muted">The customer is emailed right away. The system re-checks the schedule before approving.</p>
              <form method="post" action="<?= e(url('admin/approve-booking.php')) ?>" data-confirm="Approve this booking and email the customer?" data-once>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">
                <input type="hidden" name="return" value="details">
                <button class="btn btn-green" type="submit"<?= $blockApprove ? ' disabled' : '' ?>>✓ Approve booking</button>
              </form>
            </div>
            <div class="decision-box" id="reject">
              <h3>Reject</h3>
              <form method="post" action="<?= e(url('admin/reject-booking.php')) ?>" data-confirm="Reject this booking and email the customer?" data-once>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">
                <label class="f">Message for the customer (optional - they WILL see this)
                  <textarea name="reason" maxlength="1000" placeholder="e.g. I'm not available on those dates, but please try another day."></textarea>
                </label>
                <button class="btn btn-outline-red" type="submit">✕ Reject booking</button>
              </form>
            </div>
          <?php elseif ($booking['booking_status'] === 'pending_email'): ?>
            <div class="alert alert-info">Waiting for the customer to verify their email. You can approve or reject only after that.</div>
          <?php else: ?>
            <div class="alert alert-info">This booking was <strong><?= e(strtolower(BOOKING_STATUS_LABELS[$booking['booking_status']])) ?></strong><?= $booking['reviewed_at'] ? ' on ' . e(format_datetime($booking['reviewed_at'])) : '' ?>. Decisions cannot be changed here.</div>
          <?php endif; ?>
        </div>

        <div class="card">
          <h2>Email the customer</h2>
          <p class="small muted">Sends a short update to <?= e($booking['email']) ?>. Your private notes are never included.</p>
          <form method="post" action="<?= e(url('admin/send-update.php')) ?>" data-confirm="Send this message to the customer?" data-once>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">
            <label class="f"><span class="small muted">Message</span>
              <textarea name="message" maxlength="2000" rows="4" required placeholder="e.g. Thanks! Could you confirm the venue address?"></textarea>
            </label>
            <button class="btn btn-dark" type="submit">✉ Send update</button>
          </form>
        </div>

        <div class="card">
          <h2>Private notes</h2>
          <p class="small muted">Only administrators can see these. They are never included in customer emails.</p>
          <form method="post" action="<?= e(url('admin/save-notes.php')) ?>" data-once>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">
            <label class="f"><span class="small muted">Internal notes</span>
              <textarea name="notes" maxlength="4000" rows="5"><?= e($booking['admin_notes']) ?></textarea>
            </label>
            <button class="btn btn-dark" type="submit">Save notes</button>
          </form>
        </div>
      </div>
    </div>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
