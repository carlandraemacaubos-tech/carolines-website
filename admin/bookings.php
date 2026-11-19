<?php
/**
 * admin/bookings.php - booking management: every request with search, filters (name, date, status),
 * pagination and quick Approve button.
 */
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();

// Read the filters from the URL (GET) and keep only allowed values.
$q       = trim((string) ($_GET['q'] ?? ''));
$status  = (string) ($_GET['status'] ?? 'all');
$status  = isset(BOOKING_STATUS_LABELS[$status]) ? $status : 'all';
$emailF  = (string) ($_GET['email'] ?? 'all');
$dateF   = trim((string) ($_GET['date'] ?? ''));
$dateF   = parse_date_strict($dateF) ? $dateF : '';
$emailF  = in_array($emailF, ['verified', 'unverified'], true) ? $emailF : 'all';
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$stats    = booking_stats();
$search   = booking_search(['q' => $q, 'status' => $status, 'email' => $emailF, 'date' => $dateF], $page, $perPage);
$rows     = $search['rows'];
$total    = $search['total'];
$pages    = max(1, (int) ceil($total / $perPage));

/** Build a dashboard URL that keeps the current filters. */
function dash_url(array $changes = []): string
{
    $params = array_merge(array_filter([
        'q'      => $_GET['q'] ?? '',
        'status' => $_GET['status'] ?? '',
        'email'  => $_GET['email'] ?? '',
        'date'   => $_GET['date'] ?? '',
    ], fn($v) => $v !== '' && $v !== 'all'), $changes);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== 'all' && $v !== null);
    return url('admin/bookings.php') . ($params ? '?' . http_build_query($params) : '');
}

$pageTitle   = 'Bookings';
$adminActive = 'bookings';
require __DIR__ . '/../includes/admin-header.php';
?>
    <div class="page-head">
      <div>
        <h1>Bookings</h1>
        <p class="muted">Review, approve or reject requests. Nothing is approved automatically.</p>
      </div>
    </div>

    <!-- Statistics (click a card to filter) -->
    <div class="stats">
      <a class="stat<?= $status === 'all' && $emailF === 'all' && $q === '' && $dateF === '' ? ' active' : '' ?>" href="<?= e(url('admin/bookings.php')) ?>"><b><?= $stats['total'] ?></b><span>All requests</span></a>
      <a class="stat hot<?= $status === 'pending_review' ? ' active' : '' ?>" href="<?= e(url('admin/bookings.php?status=pending_review')) ?>"><b><?= $stats['pending_review'] ?></b><span>Pending review</span></a>
      <a class="stat<?= $status === 'pending_email' ? ' active' : '' ?>" href="<?= e(url('admin/bookings.php?status=pending_email')) ?>"><b><?= $stats['pending_email'] ?></b><span>Awaiting email verification</span></a>
      <a class="stat<?= $emailF === 'verified' ? ' active' : '' ?>" href="<?= e(url('admin/bookings.php?email=verified')) ?>"><b><?= $stats['verified'] ?></b><span>Verified emails</span></a>
      <a class="stat<?= $status === 'approved' ? ' active' : '' ?>" href="<?= e(url('admin/bookings.php?status=approved')) ?>"><b><?= $stats['approved'] ?></b><span>Approved</span></a>
      <a class="stat<?= $status === 'rejected' ? ' active' : '' ?>" href="<?= e(url('admin/bookings.php?status=rejected')) ?>"><b><?= $stats['rejected'] ?></b><span>Rejected</span></a>
    </div>

    <!-- Search + filters -->
    <form class="card filters" method="get" action="<?= e(url('admin/bookings.php')) ?>">
      <label class="grow">Search
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Customer name, reference, email, phone or location">
      </label>
      <label>Status
        <select name="status">
          <option value="all">All statuses</option>
          <?php foreach (BOOKING_STATUS_LABELS as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Event date
        <input type="date" name="date" value="<?= e($dateF) ?>">
      </label>
      <label>Email
        <select name="email">
          <option value="all"<?= $emailF === 'all' ? ' selected' : '' ?>>Verified &amp; unverified</option>
          <option value="verified"<?= $emailF === 'verified' ? ' selected' : '' ?>>Verified only</option>
          <option value="unverified"<?= $emailF === 'unverified' ? ' selected' : '' ?>>Not verified only</option>
        </select>
      </label>
      <button class="btn btn-dark" type="submit">Search</button>
      <a class="btn btn-outline" href="<?= e(url('admin/bookings.php')) ?>">Reset</a>
    </form>

    <!-- Bookings table -->
    <div class="card">
      <h2><?= $total ?> request<?= $total === 1 ? '' : 's' ?> found</h2>
      <?php if (!$rows): ?>
        <p class="muted">No booking requests match your search.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Reference</th><th>Customer</th><th>Event</th><th>Schedule</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $b): ?>
            <tr>
              <td data-label="Reference"><a class="ref-link" href="<?= e(url('admin/booking-details.php?id=' . (int) $b['id'])) ?>"><?= e($b['booking_reference']) ?></a><br><small class="muted">Sent <?= e(format_short($b['created_at'])) ?></small></td>
              <td data-label="Customer"><strong><?= e($b['customer_name']) ?></strong><br><a href="mailto:<?= e($b['email']) ?>"><?= e($b['email']) ?></a><br><small><?= e($b['phone']) ?></small></td>
              <td data-label="Event"><?= e($b['event_type']) ?><br><small class="muted"><?= e($b['event_location']) ?></small></td>
              <td data-label="Schedule"><?= e(format_short($b['start_datetime'])) ?><br><small class="muted">to</small> <?= e(format_short($b['end_datetime'])) ?></td>
              <td data-label="Email"><?= email_badge((bool) $b['email_verified']) ?></td>
              <td data-label="Status"><?= status_badge($b['booking_status']) ?></td>
              <td data-label="Actions">
                <div class="actions">
                  <a class="btn btn-outline btn-sm" href="<?= e(url('admin/booking-details.php?id=' . (int) $b['id'])) ?>">View</a>
                  <?php if ($b['booking_status'] === 'pending_review'): ?>
                    <form method="post" action="<?= e(url('admin/approve-booking.php')) ?>" class="inline" data-confirm="Approve booking <?= e($b['booking_reference']) ?> and email the customer?" data-once>
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                      <input type="hidden" name="return" value="bookings">
                      <button class="btn btn-green btn-sm" type="submit">Approve</button>
                    </form>
                    <a class="btn btn-outline-red btn-sm" href="<?= e(url('admin/booking-details.php?id=' . (int) $b['id'] . '#reject')) ?>">Reject…</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Pages">
          <?php if ($page > 1): ?><a href="<?= e(dash_url(['page' => $page - 1])) ?>">← Previous</a><?php endif; ?>
          <span class="current">Page <?= $page ?> of <?= $pages ?></span>
          <?php if ($page < $pages): ?><a href="<?= e(dash_url(['page' => $page + 1])) ?>">Next →</a><?php endif; ?>
        </nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
