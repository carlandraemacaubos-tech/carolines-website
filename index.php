<?php
/** Home page: hero, quick overview, celebrations and how booking works. */
require __DIR__ . '/includes/bootstrap.php';

$pageTitle  = "Let Me Help With the Rest!";
$activePage = 'home';
require __DIR__ . '/includes/header.php';
?>
    <!-- HOME / HERO -->
    <section class="hero" id="home">
      <div class="hero-shade"></div>
      <div class="container hero-content">
        <span class="eyebrow light">✨ YOUR DAY, YOUR PARTY, I'M HERE TO HELP</span>
        <h1>Enjoy Your Party — <span>Let Me Help With the Rest!</span></h1>
        <p>Hosting a party can be exciting, but there's a lot to do. At Caroline's Party Helper, I help make your celebration easier with setup, serving, and cleanup support — so you can enjoy making memories with the people who matter most.</p>
        <div class="button-row">
          <a class="btn btn-red" href="<?= e(url('booking.php')) ?>">▣ &nbsp; Request a Booking</a>
          <a class="btn btn-dark" href="tel:+16319979504">☎ &nbsp; Call Caroline</a>
          <a class="btn btn-outline-light" href="sms:+16319979504">▤ &nbsp; Text Caroline</a>
        </div>
        <div class="hero-note">✓ Friendly help &nbsp; ✓ Flexible support &nbsp; ✓ More time to enjoy your event</div>
      </div>
    </section>

    <!-- QUICK SERVICE INTRO -->
    <section class="intro section-pad">
      <div class="container">
        <p class="eyebrow">A LITTLE HELP GOES A LONG WAY</p>
        <h2>Before, during &amp; after your event</h2>
        <div class="three-grid">
          <article class="mini-card"><span class="icon-circle">🎉</span><h3>Setup</h3><p>Tables, decorations, and getting your party space ready.</p><a class="card-link" href="<?= e(url('services.php')) ?>">See what's included →</a></article>
          <article class="mini-card"><span class="icon-circle">🍽️</span><h3>Serving Guests</h3><p>Refreshments, food assistance, and keeping guests comfortable.</p><a class="card-link" href="<?= e(url('services.php')) ?>">See what's included →</a></article>
          <article class="mini-card"><span class="icon-circle">🧹</span><h3>Keeping Things Tidy</h3><p>Light cleanup and helping things stay organized.</p><a class="card-link" href="<?= e(url('services.php')) ?>">See what's included →</a></article>
        </div>
      </div>
    </section>

    <!-- EVENT TYPES (each one opens the booking page with that event already chosen) -->
    <section class="event-types section-pad">
      <div class="container">
        <p class="eyebrow">MADE FOR YOUR MOMENTS</p>
        <h2>Celebrations big and small</h2>
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
          <div class="step"><h3>Send a request</h3><p>Tell me about your event, the dates and times you need, and the kind of help you'd like.</p></div>
          <div class="step"><h3>Verify your email</h3><p>We email you a link to confirm your address. It takes a few seconds.</p></div>
          <div class="step"><h3>I review it</h3><p>I check my availability and the details of your event.</p></div>
          <div class="step"><h3>You get my answer</h3><p>You receive an email once your request is approved or if I can't take it on.</p></div>
        </div>
        <a class="section-link" href="<?= e(url('services.php')) ?>">Explore all services →</a>
      </div>
    </section>

    <!-- CALL TO ACTION -->
    <section class="cta-band section-pad">
      <div class="container">
        <p class="eyebrow">READY WHEN YOU ARE</p>
        <h2>Let's plan something lovely</h2>
        <p>Send a booking request, or reach out by phone, text, or email — I'd love to hear about your event.</p>
        <div class="center">
          <a class="btn btn-red" href="<?= e(url('booking.php')) ?>">Request a Booking</a>
          <a class="btn btn-dark" href="<?= e(url('contact.php')) ?>">Contact Caroline</a>
          <a class="btn btn-outline-dark" href="<?= e(url('gallery.php')) ?>">See the Gallery</a>
        </div>
      </div>
    </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
