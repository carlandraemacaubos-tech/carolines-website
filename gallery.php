<?php
/** Gallery page: party photos with working category filters (see assets/js/main.js). */
require __DIR__ . '/includes/bootstrap.php';

$pageTitle       = 'Gallery';
$activePage      = 'gallery';
$metaDescription = 'A few party moments - setup, serving, decorations and celebrations.';

// Add or change photos here. "category" must match one of the filter buttons below.
$photos = [
    ['setup',   'photo-1519167758481-83f550bb49b3', 'A decorated party table',        'Party Setup'],
    ['serving', 'photo-1555244162-803834f70033',    'Food prepared for guests',       'Serving & Guest Assistance'],
    ['decor',   'photo-1530103862676-de8c9debaa1f', 'Colorful party decorations',     'Decorations'],
    ['before',  'photo-1464366400600-7168b8af9bc3', 'Celebration table arrangement',  'Before & After'],
    ['setup',   'photo-1469371670807-013ccf25f16a', 'Outdoor celebration setup',      'Party Setup'],
    ['decor',   'photo-1492684223066-81342ee5ff30', 'Festive celebration with lights','Special Celebrations'],
];

require __DIR__ . '/includes/header.php';
?>
    <section class="page-hero">
      <div class="hero-shade"></div>
      <div class="container">
        <p class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> / Gallery</p>
        <h1>A few party moments</h1>
        <p>A little inspiration for your next celebration.</p>
      </div>
    </section>

    <section class="gallery section-pad">
      <div class="container">
        <p class="eyebrow">BROWSE BY CATEGORY</p>
        <h2>Gallery</h2>
        <div class="gallery-filters" id="galleryFilters">
          <button type="button" class="filter active" data-filter="all" aria-pressed="true">All</button>
          <button type="button" class="filter" data-filter="setup" aria-pressed="false">Party Setup</button>
          <button type="button" class="filter" data-filter="serving" aria-pressed="false">Serving &amp; Guest Assistance</button>
          <button type="button" class="filter" data-filter="decor" aria-pressed="false">Decorations</button>
          <button type="button" class="filter" data-filter="before" aria-pressed="false">Before &amp; After</button>
        </div>
        <div class="gallery-grid">
          <?php foreach ($photos as [$category, $photoId, $alt, $caption]): ?>
          <figure data-category="<?= e($category) ?>">
            <img src="https://images.unsplash.com/<?= e($photoId) ?>?auto=format&amp;fit=crop&amp;w=900&amp;q=85" alt="<?= e($alt) ?>" loading="lazy">
            <figcaption><?= e($caption) ?> <span aria-hidden="true">↗</span></figcaption>
          </figure>
          <?php endforeach; ?>
        </div>
        <div class="center" style="margin-top:34px">
          <a class="btn btn-red" href="<?= e(url('booking.php')) ?>">Request a Booking</a>
          <a class="btn btn-outline-dark" href="<?= e(url('services.php')) ?>">View Services</a>
        </div>
      </div>
    </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
