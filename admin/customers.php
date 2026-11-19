<?php
/** admin/customers.php - everyone who has sent a request, grouped by email address. */
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$q       = trim((string) ($_GET['q'] ?? ''));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$result  = customer_list($q, $page, $perPage);
$rows    = $result['rows'];
$pages   = max(1, (int) ceil($result['total'] / $perPage));

$pageTitle   = 'Customers';
$adminActive = 'customers';
require __DIR__ . '/../includes/admin-header.php';
?>
    <div class="page-head">
      <div><h1>Customers</h1><p class="muted">Everyone who has requested a booking. Click a name to see all of their requests.</p></div>
    </div>

    <form class="card" method="get" action="<?= e(url('admin/customers.php')) ?>" style="display:grid;grid-template-columns:1fr auto auto;gap:10px;align-items:end">
      <label class="f" style="margin:0">Search <input type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email or phone"></label>
      <button class="btn btn-dark" type="submit">Search</button>
      <a class="btn btn-outline" href="<?= e(url('admin/customers.php')) ?>">Reset</a>
    </form>

    <div class="card">
      <h2><?= (int) $result['total'] ?> customer<?= $result['total'] === 1 ? '' : 's' ?></h2>
      <?php if (!$rows): ?>
        <p class="muted">No customers found.</p>
      <?php else: ?>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Customer</th><th>Contact</th><th>Requests</th><th>Approved</th><th>Rejected</th><th>Email</th><th>Last request</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $c): ?>
          <tr>
            <td data-label="Customer"><a class="ref-link" href="<?= e(url('admin/bookings.php?q=' . urlencode($c['email']))) ?>"><?= e($c['name']) ?></a></td>
            <td data-label="Contact"><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><br><small><?= e($c['phone']) ?></small></td>
            <td data-label="Requests"><?= (int) $c['requests'] ?></td>
            <td data-label="Approved"><?= (int) $c['approved'] ?></td>
            <td data-label="Rejected"><?= (int) $c['rejected'] ?></td>
            <td data-label="Email"><?= email_badge((bool) $c['verified']) ?></td>
            <td data-label="Last request"><?= e(format_short($c['last_request'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Pages">
          <?php if ($page > 1): ?><a href="<?= e(url('admin/customers.php?' . http_build_query(['q' => $q, 'page' => $page - 1]))) ?>">← Previous</a><?php endif; ?>
          <span class="current">Page <?= $page ?> of <?= $pages ?></span>
          <?php if ($page < $pages): ?><a href="<?= e(url('admin/customers.php?' . http_build_query(['q' => $q, 'page' => $page + 1]))) ?>">Next →</a><?php endif; ?>
        </nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
