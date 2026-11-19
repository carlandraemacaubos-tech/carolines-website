<?php
/** admin/history.php - the complete booking history: every action and every email, newest first. */
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$result  = booking_global_history($page, $perPage);
$pages   = max(1, (int) ceil($result['total'] / $perPage));

$pageTitle   = 'History';
$adminActive = 'history';
require __DIR__ . '/../includes/admin-header.php';
?>
    <div class="page-head">
      <div><h1>Booking history</h1><p class="muted">Every action and every email. Declined and old bookings always stay here.</p></div>
    </div>

    <div class="card">
      <?php if (!$result['rows']): ?>
        <p class="muted">Nothing has happened yet.</p>
      <?php else: ?>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>When</th><th>Booking</th><th>Event</th><th>Details</th></tr></thead>
        <tbody>
        <?php foreach ($result['rows'] as $h):
            $isEmail = $h['kind'] === 'email';
            $failed  = $isEmail && !(int) $h['ok']; ?>
          <tr>
            <td data-label="When"><?= e(format_short($h['happened_at'])) ?></td>
            <td data-label="Booking"><?php if ($h['booking_id']): ?><a class="ref-link" href="<?= e(url('admin/booking-details.php?id=' . (int) $h['booking_id'])) ?>"><?= e($h['ref']) ?></a><br><small><?= e($h['customer']) ?></small><?php else: ?><span class="muted">—</span><?php endif; ?></td>
            <td data-label="Event"><?= $isEmail ? ($failed ? '<span class="badge badge-rejected">Email FAILED</span>' : '<span class="badge badge-verified">Email sent</span>') : '<span class="badge badge-pending_email">Action</span>' ?> <?= e(ucfirst(str_replace('_', ' ', $h['title']))) ?></td>
            <td data-label="Details"><?= e((string) $h['text']) ?><?= !$isEmail && $h['admin'] ? ' <small class="muted">(by ' . e($h['admin']) . ')</small>' : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Pages">
          <?php if ($page > 1): ?><a href="<?= e(url('admin/history.php?page=' . ($page - 1))) ?>">← Newer</a><?php endif; ?>
          <span class="current">Page <?= $page ?> of <?= $pages ?></span>
          <?php if ($page < $pages): ?><a href="<?= e(url('admin/history.php?page=' . ($page + 1))) ?>">Older →</a><?php endif; ?>
        </nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
