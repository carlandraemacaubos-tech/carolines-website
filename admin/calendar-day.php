<?php
/**
 * admin/calendar-day.php - one date: its bookings and its availability settings.
 * The administrator can follow the weekly hours, block the date, or set custom open hours.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$date = (string) ($_GET['date'] ?? '');
if (!parse_date_strict($date)) {
    redirect('admin/calendar.php');
}

$ctx      = avail_load($date, $date);
$day      = avail_day($ctx, $date);
$bookings = booking_list_overlapping($date . ' 00:00:00', date_add_days($date, 1) . ' 00:00:00', ['approved', 'pending_review']);
$weekday  = (int) (new DateTimeImmutable($date))->format('w');
$weeklyRanges = ranges_normalize($ctx['weekly'][$weekday] ?? []);
$weekdayName  = (new DateTimeImmutable($date))->format('l');

// Current setting -> which radio is selected, and the rows to show in "custom hours".
$mode = $day['source'] === 'override' ? ($day['state'] === 'blocked' ? 'closed' : 'custom') : 'default';
$rows = $mode === 'custom' ? $day['ranges'] : $weeklyRanges;
while (count($rows) < 3) { $rows[] = ['', '']; }
$rows = array_slice($rows, 0, 4);
$cell = fn($v) => $v === '' ? '' : minutes_to_time((int) $v);

$approvedCount = count(array_filter($bookings, fn($b) => $b['booking_status'] === 'approved'));
$monthLink = url('admin/calendar.php?month=' . substr($date, 0, 7));

$pageTitle   = format_date($date . ' 00:00:00');
$adminActive = 'calendar';
require __DIR__ . '/../includes/admin-header.php';
?>
    <p class="small"><a href="<?= e($monthLink) ?>">← Back to calendar</a></p>
    <div class="page-head">
      <div>
        <h1><?= e(format_date($date . ' 00:00:00')) ?></h1>
        <p class="muted">
          <?php if ($day['state'] === 'blocked'): ?><span class="badge badge-rejected">Manually blocked</span>
          <?php elseif ($day['state'] === 'closed'): ?><span class="badge badge-pending_email">Closed (weekly day off)</span>
          <?php else: ?><span class="badge badge-approved">Open: <?= e(implode(', ', array_map('range_label', $day['ranges']))) ?></span><?php endif; ?>
          <?= $day['source'] === 'override' ? ' <span class="badge badge-pending_review">Custom setting for this date</span>' : '' ?>
        </p>
      </div>
    </div>

    <div class="cols">
      <div class="card">
        <h2>Availability for this date</h2>
        <p class="small muted">Customers see changes immediately. Approved bookings always keep blocking their own time.</p>
        <?php if ($approvedCount): ?><div class="alert alert-warning">This date already has <?= $approvedCount ?> approved booking(s). Blocking it does <strong>not</strong> cancel them.</div><?php endif; ?>

        <form method="post" action="<?= e(url('admin/availability-action.php')) ?>" data-once>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_day">
          <input type="hidden" name="date" value="<?= e($date) ?>">

          <label class="choice"><input type="radio" name="mode" value="default"<?= $mode === 'default' ? ' checked' : '' ?>>
            <span><strong>Follow the weekly hours</strong><br><small class="muted"><?= $weeklyRanges ? e($weekdayName . 's: ' . implode(', ', array_map('range_label', $weeklyRanges))) : e($weekdayName . 's are closed') ?></small></span></label>
          <label class="choice"><input type="radio" name="mode" value="closed"<?= $mode === 'closed' ? ' checked' : '' ?>>
            <span><strong>Unavailable all day</strong> (block this date)</span></label>
          <label class="choice"><input type="radio" name="mode" value="custom"<?= $mode === 'custom' ? ' checked' : '' ?>>
            <span><strong>Custom available hours</strong> (also opens a day that is normally closed)</span></label>

          <div class="range-rows">
            <?php foreach ($rows as $r): ?>
              <div class="range-row">
                <input type="time" name="start[]" value="<?= e($cell($r[0])) ?>" aria-label="Opens at">
                <span>to</span>
                <input type="time" name="end[]" value="<?= e($cell($r[1])) ?>" aria-label="Closes at">
              </div>
            <?php endforeach; ?>
            <p class="small muted">Leave unused rows empty. Used only with "Custom available hours".</p>
          </div>

          <label class="f"><span class="small muted">Private note (optional)</span>
            <input type="text" name="note" maxlength="255" value="<?= e($day['note']) ?>" placeholder="e.g. Family trip">
          </label>
          <button class="btn btn-red" type="submit">Save availability</button>
        </form>
      </div>

      <div class="card">
        <h2>Bookings on this date</h2>
        <?php if (!$bookings): ?>
          <p class="muted">No approved or pending bookings touch this date.</p>
        <?php else: ?>
          <ul class="activity">
            <?php foreach ($bookings as $b): ?>
              <li><?= status_badge($b['booking_status']) ?><br>
                <a href="<?= e(url('admin/booking-details.php?id=' . (int) $b['id'])) ?>"><strong><?= e($b['booking_reference']) ?></strong></a> · <?= e($b['customer_name']) ?><br>
                <small><?= e(format_short($b['start_datetime'])) ?> → <?= e(format_short($b['end_datetime'])) ?></small></li>
            <?php endforeach; ?>
          </ul>
          <p class="small muted">Pending requests do not block the date. They are checked for overlaps when you approve them.</p>
        <?php endif; ?>
      </div>
    </div>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
