<?php
/**
 * includes/header.php - shared top of every public page (logo + navigation).
 * Set these variables BEFORE including it:
 *   $pageTitle   - text for the browser tab
 *   $activePage  - 'home' | 'services' | 'about' | 'gallery' | 'booking' | 'contact'
 * Optional: $metaDescription, $noIndex (true hides the page from search engines)
 */
$pageTitle       = $pageTitle ?? "Caroline's Party Helper";
$activePage      = $activePage ?? '';
$metaDescription = $metaDescription ?? "Caroline's Party Helper — friendly setup, serving, decorating, and cleanup help for your celebration.";
$noIndex         = $noIndex ?? false;

// label, page file. The "booking" link keeps the red button style from the original design.
$navItems = [
    'home'     => ['Home', 'index.php'],
    'services' => ['Services', 'services.php'],
    'about'    => ['About', 'about.php'],
    'gallery'  => ['Gallery', 'gallery.php'],
    'booking'  => ['Request a Booking', 'booking.php'],
    'contact'  => ['Contact', 'contact.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e($metaDescription) ?>">
  <?php if ($noIndex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
  <title><?= e($pageTitle) ?> | Caroline's Party Helper</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/pages.css')) ?>">
</head>
<body>
  <a class="skip-link" href="#main-content">Skip to content</a>

  <!-- Sticky navigation: every link opens its own page. -->
  <header class="site-header">
    <div class="container nav-wrap">
      <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="Caroline's Party Helper home">
        <span class="brand-mark">🎈</span>
        <span><strong>Caroline's <em>Party Helper</em></strong><small>YOUR PARTY, MY HELPING HANDS</small></span>
      </a>
      <button class="menu-toggle" id="menuToggle" aria-label="Open navigation" aria-expanded="false" aria-controls="mainNav">☰</button>
      <nav class="main-nav" id="mainNav" aria-label="Main navigation">
        <?php foreach ($navItems as $key => [$label, $file]): ?>
          <a href="<?= e(url($file)) ?>"
             <?= $key === 'booking' ? 'class="nav-cta"' : '' ?>
             <?= $key === $activePage ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
  </header>

  <main id="main-content">
