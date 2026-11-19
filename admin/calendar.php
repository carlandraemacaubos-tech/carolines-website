<?php
/**
 * admin/calendar.php - monthly calendar.
 * Shows, per day: opening hours, manually blocked / closed days, approved bookings (green)
 * and pending requests (amber). Click a day to edit its availability.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$month = (string) ($_GET['month'] ?? '');
if (!preg_match('/^(20\d\d)-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}
$first       = new DateTimeImmutable($month . '-01');
$last        = $first->modify('last day of this month')->format('Y-m-d');
$daysInMonth = (int) $first->format('t');
$lead        = (int) $first->format('w');                     // empty cells before the 1st
$prevMonth   = $first->modify('-1 month')->format('Y-m');
$nextMonth   = $first->modify('+1 month')->format('Y-m');
$today       = date('Y-m-d');

$ctx = avail_load($first->format('Y-m-d'), $last);
$bookings = booking_list_overlapping($first->format('Y-m-d') . ' 00:00:00', date_add_days($last, 1) . ' 00:00:00', ['approved', 'pending_review']);

// Group the bookings by every date they touch.
$byDay = [];
foreach ($bookings as $b) {
    $from = max(substr($b['start_datetime'], 0, 10), $first->format('Y-m-d'));
    $to   = min(substr($b['end_datetime'], 0, 10), $last);
    for ($d = $from; $d <= $to; $d = date_add_days($d, 1)) {
        $byDay[$d][] = $b;
    }
}

$pageTitle   = 'Calendar ' . $first->format('F Y');
$adminActive = 'calendar';
require __DIR__ . '/../includes/admin-header.php';
?>
    <div class="page-head">
      <div>
        <h1>Calendar</h1>
        <p class="muted">Click a day to change its availability or see its bookings.</p>
      </div>
      <div class="actions">
        <a class="btn btn-outline" href="<?= e(url('admin/calendar.php?month=' . $prevMonth)) ?>">← <?= e((new DateTimeImmutable($prevMonth . '-01'))->format('M')) ?></a>
        <a class="btn btn-outline" href="<?= e(url('admin/calendar.php')) ?>">Today</a>
        <a class="btn btn-outline" href="<?= e(url('admin/calendar.php?month=' . $nextMonth)) ?>"><?= e((new DateTimeImmutable($nextMonth . '-01'))->format('M')) ?> →</a>
        <a class="btn btn-dark" href="<?= e(url('admin/availability.php')) ?>">⚙ Weekly hours &amp; blocking</a>
      </div>
    </div>

    <div class="card">
      <h2 class="cal-title"><?= e($first->format('F Y')) ?></h2>
      <ul class="acal-legend">
        <li><i class="lg-box lg-open"></i> Available</li>
        <li><i class="lg-box lg-closed"></i> Closed (weekly day off)</li>
        <li><i class="lg-box lg-blocked"></i> Manually blocked</li>
        <li><i class="lg-box lg-approved"></i> Approved booking</li>
        <li><i class="lg-box lg-pending"></i> Pending request</li>
        <li><i class="lg-box lg-custom"></i> Custom hours</li>
      </ul>

      <div class="acal">
        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $w): ?><div class="acal-wd"><?= $w ?></div><?php endforeach; ?>
        <?php for ($i = 0; $i < $lead; $i++): ?><div class="acal-cell blank"></div><?php endfor; ?>

        <?php for ($n = 1; $n <= $daysInMonth; $n++):
            $date    = $first->modify('+' . ($n - 1) . ' days')->format('Y-m-d');
            $day     = avail_day($ctx, $date);
            $list    = $byDay[$date] ?? [];
            $classes = ['acal-cell'];
            if ($day['state'] === 'blocked') { $classes[] = 'is-blocked'; }
            elseif ($day['state'] === 'closed') { $classes[] = 'is-closed'; }
            if ($date < $today) { $classes[] = 'is-past'; }
            if ($date === $today) { $classes[] = 'is-today'; }
            ?>
          <a class="<?= e(implode(' ', $classes)) ?>" href="<?= e(url('admin/calendar-day.php?date=' . $date)) ?>">
            <span class="acal-num"><?= $n ?></span>
            <?php if ($day['state'] === 'blocked'): ?>
              <span class="acal-tag tag-blocked">⛔ Blocked<?= $day['note'] !== '' ? ': ' . e(mb_strimwidth($day['note'], 0, 18, '…')) : '' ?></span>
            <?php elseif ($day['state'] === 'closed'): ?>
              <span class="acal-tag tag-closed">Closed</span>
            <?php else: ?>
              <span class="acal-hours<?= $day['source'] === 'override' ? ' custom' : '' ?>"><?= e(implode(', ', array_map(fn($r) => minutes_short($r[0]) . '–' . minutes_short($r[1]), $day['ranges']))) ?></span>
            <?php endif; ?>
            <?php
              // Approved bookings first (up to 2), then ONE chip that always shows how many requests are pending.
              $approvedList = array_values(array_filter($list, fn($b) => $b['booking_status'] === 'approved'));
              $pendingCount = count($list) - count($approvedList);
              foreach (array_slice($approvedList, 0, 2) as $b): ?>
              <span class="acal-chip chip-approved">✓ <?= e(mb_strimwidth($b['customer_name'], 0, 14, '…')) ?></span>
            <?php endforeach; ?>
            <?php if (count($approvedList) > 2): ?><span class="acal-more">+<?= count($approvedList) - 2 ?> more approved</span><?php endif; ?>
            <?php if ($pendingCount): ?><span class="acal-chip chip-pending">⏳ <?= $pendingCount ?> pending</span><?php endif; ?>
          </a>
        <?php endfor; ?>
      </div>
    </div>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
