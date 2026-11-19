<?php
/** Services page: the full list of help available before, during and after a party. */
require __DIR__ . '/includes/bootstrap.php';

$pageTitle       = 'Services';
$activePage      = 'services';
$metaDescription = "Party setup, serving, guest assistance and light cleanup from Caroline's Party Helper.";
require __DIR__ . '/includes/header.php';
?>
    <section class="page-hero">
      <div class="hero-shade"></div>
      <div class="container">
        <p class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> / Services</p>
        <h1>Support from start to finish</h1>
        <p>Choose the kind of help that would make your event easier. We can discuss the details when you reach out.</p>
      </div>
    </section>

    <!-- SERVICE DETAILS -->
    <section class="services section-pad">
      <div class="container">
        <p class="eyebrow">HOW I CAN HELP</p>
        <h2>Before, during &amp; after your party</h2>
        <div class="service-columns">
          <article class="service-card"><div class="service-head gold"><b>01</b><h3>Before the Party</h3>✦</div><ul><li>Help with party setup</li><li>Arranging tables and decorations</li><li>Organizing serving areas</li><li>Preparing the space for guests</li></ul></article>
          <article class="service-card"><div class="service-head red"><b>02</b><h3>During the Party</h3>✦</div><ul><li>Assist with serving food and refreshments</li><li>Refilling beverages</li><li>Help guests</li><li>Keep tables tidy</li><li>Collect plates, cups, and trash</li><li>Help maintain the event flow</li></ul></article>
          <article class="service-card"><div class="service-head black"><b>03</b><h3>After the Party</h3>↻</div><ul><li>Light cleanup</li><li>Clear tables and serving areas</li><li>Gather leftover items</li><li>Leave the area neat and organized</li></ul></article>
        </div>
        <div class="notice"><strong>ⓘ &nbsp; Please note</strong><p>Caroline's Party Helper is a personal helping service. Services do not include heavy lifting, professional catering, or specialized cleaning. We'll discuss what's needed for your event before confirming a booking.</p></div>
        <div class="center"><a class="btn btn-red" href="<?= e(url('booking.php')) ?>">Request a Booking</a> <a class="btn btn-dark" href="tel:+16319979504">Call Caroline</a> <a class="btn btn-outline-dark" href="sms:+16319979504">Text Caroline</a></div>
      </div>
    </section>

    <!-- EVENT TYPES -->
    <section class="event-types section-pad">
      <div class="container">
        <p class="eyebrow">MADE FOR YOUR MOMENTS</p>
        <h2>Celebrations I can help with</h2>
        <div class="event-grid">
          <a href="<?= e(url('booking.php?event=' . urlencode('Birthday Party'))) ?>">🎂 &nbsp; Birthday Parties</a>
          <a href="<?= e(url('booking.php?event=' . urlencode('Anniversary'))) ?>">💍 &nbsp; Anniversary Parties</a>
          <a href="<?= e(url('booking.php?event=' . urlencode('Baby Shower'))) ?>">👶 &nbsp; Baby Showers</a>
          <a href="<?= e(url('booking.php?event=' . urlencode('Bridal Shower'))) ?>">🌸 &nbsp; Bridal Showers</a>
          <a href="<?= e(url('booking.php?event=' . urlencode('Graduation'))) ?>">🎓 &nbsp; Graduation Parties</a>
          <a href="<?= e(url('booking.php?event=' . urlencode('Family Gathering'))) ?>">👨‍👩‍👧 &nbsp; Family Gatherings</a>
          <a href="<?= e(url('booking.php?event=' . urlencode('Church Event'))) ?>">🙏 &nbsp; Church Events</a>
          <a href="<?= e(url('booking.php?event=' . urlencode('Holiday Party'))) ?>">🎈 &nbsp; Holiday Parties</a>
          <a href="<?= e(url('booking.php?event=' . urlencode('Wedding or Special Celebration'))) ?>">✨ &nbsp; Special Weddings &amp; Celebrations</a>
        </div>
      </div>
    </section>

    <!-- HOW BOOKING WORKS -->
    <section class="how-it-works section-pad">
      <div class="container">
        <p class="eyebrow">SIMPLE &amp; FRIENDLY</p>
        <h2>How booking works</h2>
        <div class="steps">
          <div class="step"><h3>Send a request</h3><p>Pick your start and end date and time, and the help you'd like.</p></div>
          <div class="step"><h3>Verify your email</h3><p>Click the link we email you. It is valid for <?= VERIFICATION_MINUTES ?> minutes.</p></div>
          <div class="step"><h3>I review it</h3><p>I check availability and the details of your event.</p></div>
          <div class="step"><h3>You get my answer</h3><p>Approval or an update arrives by email. A request is never confirmed until I approve it.</p></div>
        </div>
        <a class="section-link" href="<?= e(url('about.php')) ?>">Learn more about Caroline →</a>
      </div>
    </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
