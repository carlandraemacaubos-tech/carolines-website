<?php
/**
 * includes/admin-header.php - top bar for every admin page.
 * Set $pageTitle before including. The admin pages call require_admin() first.
 */
$pageTitle = $pageTitle ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($pageTitle) ?> | Admin - Caroline's Party Helper</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Nunito:wght@600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body>
  <header class="admin-bar">
    <div class="admin-wrap admin-bar-inner">
      <a class="admin-brand" href="<?= e(url('admin/dashboard.php')) ?>">🎈 Caroline's <em>Party Helper</em> <small>ADMIN</small></a>
      <?php if (!empty($_SESSION['admin_id'])): ?>
      <nav class="admin-nav" aria-label="Admin navigation">
        <?php
        $adminActive = $adminActive ?? '';
        $adminLinks = ['dashboard' => ['Dashboard', 'admin/dashboard.php'], 'bookings' => ['Bookings', 'admin/bookings.php'],
                       'calendar' => ['Calendar', 'admin/calendar.php'], 'availability' => ['Availability', 'admin/availability.php'],
                       'customers' => ['Customers', 'admin/customers.php'], 'history' => ['History', 'admin/history.php']];
        foreach ($adminLinks as $key => [$label, $path]): ?>
          <a href="<?= e(url($path)) ?>"<?= $key === $adminActive ? ' class="current" aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">Website ↗</a>
        <span class="admin-user">👤 <?= e($_SESSION['admin_username'] ?? '') ?></span>
        <form method="post" action="<?= e(url('admin/logout.php')) ?>" class="inline">
          <?= csrf_field() ?>
          <button type="submit" class="btn-link">Log out</button>
        </form>
      </nav>
      <?php endif; ?>
    </div>
  </header>
  <main class="admin-wrap admin-main">
    <?= render_notice() ?>
