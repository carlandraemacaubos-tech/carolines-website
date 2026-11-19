<?php
/**
 * admin/dashboard.php - the overview: totals, requests waiting for a decision,
 * upcoming approved bookings and recent activity.
 * (The full searchable list is on admin/bookings.php.)
 */
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();

$stats    = booking_stats();
$pending  = booking_pending_review_list(6);
$upcoming = booking_upcoming_approved(6);
$activity = booking_recent_activity(8);

$pageTitle   = 'Dashboard';
$adminActive = 'dashboard';
require __DIR__ . '/../includes/admin-header.php';
?>
    <div class="page-head">
      <div>
        <h1>Dashboard</h1>
        <p class="muted">Welcome back, <?= e($admin['username']) ?>. Nothing is approved automatically.</p>
      </div>
      <div class="actions">
        <a class="btn btn-dark" href="<?= e(url('admin/calendar.php')) ?>">📅 Open calendar</a>
        <a class="btn btn-outline" href="<?= e(url('admin/availability.php')) ?>">⚙ Availability</a>
      </div>
    </div>

    <div class="stats">
      <a class="stat" href="<?= e(url('admin/bookings.php')) ?>"><b><?= $stats['total'] ?></b><span>Total bookings</span></a>
      <a class="stat hot" href="<?= e(url('admin/bookings.php?status=pending_review')) ?>"><b><?= $stats['pending_review'] ?></b><span>Pending review</span></a>
      <a class="stat" href="<?= e(url('admin/bookings.php?status=pending_email')) ?>"><b><?= $stats['pending_email'] ?></b><span>Awaiting email verification</span></a>
      <a class="stat" href="<?= e(url('admin/bookings.php?email=verified')) ?>"><b><?= $stats['verified'] ?></b><span>Verified emails</span></a>
      <a class="stat" href="<?= e(url('admin/bookings.php?status=approved')) ?>"><b><?= $stats['approved'] ?></b><span>Approved</span></a>
      <a class="stat" href="<?= e(url('admin/bookings.php?status=rejected')) ?>"><b><?= $stats['rejected'] ?></b><span>Rejected</span></a>
    </div>

    <div class="cols">
      <div class="card">
        <h2>Waiting for your decision</h2>
        <?php if (!$pending): ?>
          <p class="muted">No requests are waiting. 🎉</p>
        <?php else: ?>
          <div class="table-wrap"><table class="data">
            <thead><tr><th>Reference</th><th>Customer</th><th>Schedule</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($pending as $b): ?>
              <tr>
                <td data-label="Reference"><a class="ref-link" href="<?= e(url('admin/booking-details.php?id=' . (int) $b['id'])) ?>"><?= e($b['booking_reference']) ?></a></td>
                <td data-label="Customer"><strong><?= e($b['customer_name']) ?></strong><br><small class="muted"><?= e($b['event_type']) ?></small></td>
                <td data-label="Schedule"><?= e(format_short($b['start_datetime'])) ?><br><small class="muted">to</small> <?= e(format_short($b['end_datetime'])) ?></td>
                <td data-label="Actions"><div class="actions">
                  <a class="btn btn-outline btn-sm" href="<?= e(url('admin/booking-details.php?id=' . (int) $b['id'])) ?>">Review</a>
                  <form method="post" action="<?= e(url('admin/approve-booking.php')) ?>" class="inline" data-confirm="Approve booking <?= e($b['booking_reference']) ?> and email the customer?" data-once>
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><input type="hidden" name="return" value="dashboard">
                    <button class="btn btn-green btn-sm" type="submit">Approve</button>
                  </form>
                </div></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
          <?php if ($stats['pending_review'] > count($pending)): ?><p class="small"><a href="<?= e(url('admin/bookings.php?status=pending_review')) ?>">See all <?= $stats['pending_review'] ?> pending requests →</a></p><?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>Upcoming approved bookings</h2>
        <?php if (!$upcoming): ?>
          <p class="muted">No upcoming approved bookings.</p>
        <?php else: ?>
          <ul class="activity">
            <?php foreach ($upcoming as $b): ?>
              <li><strong><?= e(format_short($b['start_datetime'])) ?></strong> → <?= e(format_short($b['end_datetime'])) ?><br>
                <a href="<?= e(url('admin/booking-details.php?id=' . (int) $b['id'])) ?>"><?= e($b['booking_reference']) ?></a> · <?= e($b['customer_name']) ?> · <?= e($b['event_type']) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <h2>Recent activity</h2>
      <?php if (!$activity): ?>
        <p class="muted">No activity yet.</p>
      <?php else: ?>
        <ul class="activity">
          <?php foreach ($activity as $item): ?>
            <li>
              <small class="muted"><?= e(format_short($item['created_at'])) ?></small> —
              <a href="<?= e(url('admin/booking-details.php?id=' . (int) $item['booking_id'])) ?>"><?= e($item['booking_reference']) ?></a>
              (<?= e($item['customer_name']) ?>): <?= e($item['details'] ?: ucfirst(str_replace('_', ' ', $item['action']))) ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="small"><a href="<?= e(url('admin/history.php')) ?>">View full history →</a></p>
      <?php endif; ?>
    </div>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
