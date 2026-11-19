<?php
/** About page: Caroline's story, mission and values. */
require __DIR__ . '/includes/bootstrap.php';

$pageTitle       = 'About';
$activePage      = 'about';
$metaDescription = "Meet Caroline and learn why she started Caroline's Party Helper.";
require __DIR__ . '/includes/header.php';
?>
    <section class="page-hero">
      <div class="hero-shade"></div>
      <div class="container">
        <p class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> / About</p>
        <h1>A little about Caroline's Party Helper</h1>
        <p>Friendly, dependable help so hosts can enjoy their own celebration.</p>
      </div>
    </section>

    <!-- ABOUT -->
    <section class="about about-page section-pad">
      <div class="container about-grid">
        <div>
          <p class="eyebrow light">A LITTLE ABOUT ME</p>
          <h2>Hi, I'm Caroline!</h2>
          <p class="about-quote">"I started Caroline's Party Helper because I enjoy helping people and making celebrations a little easier."</p>
          <p>Hosting a party should be fun — but too often the host spends the whole event worrying about the setup, serving the food, or keeping the tables and spaces tidy instead of enjoying time with family and friends.</p>
          <p>That's where I come in. Having spent time helping with events and gatherings, I know that small tasks can make a big difference. My goal is to make your celebration feel a little less stressful and a lot more enjoyable.</p>
          <p>I believe in friendly, dependable service, and I'm happy to discuss how I can help with your event.</p>
          <a class="btn btn-white" href="<?= e(url('booking.php')) ?>">Request a Booking</a>
        </div>
        <div class="about-seal"><div class="seal">🎈<strong>CAROLINE'S<br>PARTY HELPER</strong><small>LET'S CELEBRATE!</small></div></div>
      </div>
    </section>

    <!-- MISSION -->
    <section class="values intro section-pad">
      <div class="container">
        <p class="eyebrow">MY MISSION</p>
        <h2>Make your celebration a little less stressful and a lot more enjoyable</h2>
        <div class="three-grid">
          <article class="mini-card"><span class="icon-circle">🤝</span><h3>Friendly</h3><p>A warm, helpful presence at your event, so guests feel taken care of.</p></article>
          <article class="mini-card"><span class="icon-circle">⏰</span><h3>Dependable</h3><p>Clear communication before your event, and help you can count on during it.</p></article>
          <article class="mini-card"><span class="icon-circle">🧩</span><h3>Flexible</h3><p>Choose the help you need — setup, serving, guest assistance, or cleanup.</p></article>
        </div>
        <a class="section-link" href="<?= e(url('services.php')) ?>">See all services →</a>
      </div>
    </section>

    <!-- CALL TO ACTION -->
    <section class="cta-band section-pad">
      <div class="container">
        <p class="eyebrow">LET'S TALK</p>
        <h2>Have a celebration coming up?</h2>
        <p>Send a booking request or get in touch — I'm happy to discuss how I can help.</p>
        <div class="center">
          <a class="btn btn-red" href="<?= e(url('booking.php')) ?>">Request a Booking</a>
          <a class="btn btn-dark" href="<?= e(url('contact.php')) ?>">Contact Caroline</a>
          <a class="btn btn-outline-dark" href="<?= e(url('gallery.php')) ?>">See the Gallery</a>
        </div>
      </div>
    </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
