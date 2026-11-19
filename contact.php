<?php
/** Contact page: phone/email details plus an inquiry form (sent to Caroline's Gmail). */
require __DIR__ . '/includes/bootstrap.php';

$pageTitle       = 'Contact';
$activePage      = 'contact';
$metaDescription = "Call, text, or email Caroline's Party Helper about your party.";

$old    = flash_get('inquiry_old', []);
$errors = flash_get('inquiry_errors', []);
$val    = fn(string $key): string => e((string) ($old[$key] ?? ''));
$err    = fn(string $key): string => e($errors[$key] ?? '');
$bad    = fn(string $key): string => isset($errors[$key]) ? 'is-invalid' : '';

require __DIR__ . '/includes/header.php';
?>
    <section class="page-hero">
      <div class="hero-shade"></div>
      <div class="container">
        <p class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> / Contact</p>
        <h1>Let's talk about your party</h1>
        <p>Call, text, or email — I'd love to hear about your event.</p>
      </div>
    </section>

    <section class="contact section-pad">
      <div class="container">
        <?= render_notice() ?>
        <div class="contact-layout">
          <div>
            <p class="eyebrow">GET IN TOUCH</p>
            <h2>Contact details</h2>
            <div class="contact-cards">
              <article><span>☎</span><h3>Call or Text</h3><a href="tel:+16319979504">(631) 997-9504</a></article>
              <article><span>✉</span><h3>Email</h3><a href="mailto:carolinespartyhelper@gmail.com">carolinespartyhelper@gmail.com</a></article>
            </div>
            <p class="section-lead" style="margin:24px 0 0;text-align:left">Ready with the dates? <a class="section-link" style="margin:0" href="<?= e(url('booking.php')) ?>">Request a booking →</a><br>Not sure what's included? <a class="section-link" style="margin:0" href="<?= e(url('services.php')) ?>">View services →</a></p>
          </div>

          <form class="inquiry-form" method="post" action="<?= e(url('contact/submit-inquiry.php')) ?>" data-single-submit novalidate>
            <h3>Send me a message</h3>
            <p class="section-lead" style="margin:0;text-align:left">Have a question before booking? Ask below and I'll reply by email.</p>
            <?= csrf_field() ?>
            <div class="hp-field" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <?php if ($errors): ?>
              <div class="alert alert-error" role="alert" style="margin-top:14px"><ul style="margin:0;padding-left:20px"><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <div class="form-grid">
              <label class="field">Name <span class="required-star">*</span>
                <input name="name" value="<?= $val('name') ?>" maxlength="100" autocomplete="name" required class="<?= $bad('name') ?>">
              </label>
              <label class="field">Phone (optional)
                <input name="phone" type="tel" value="<?= $val('phone') ?>" maxlength="30" autocomplete="tel" class="<?= $bad('phone') ?>">
              </label>
              <label class="field full">Email <span class="required-star">*</span>
                <input name="email" type="email" value="<?= $val('email') ?>" maxlength="190" autocomplete="email" required class="<?= $bad('email') ?>">
              </label>
              <label class="field full">Message <span class="required-star">*</span>
                <textarea name="message" rows="5" maxlength="2000" required class="<?= $bad('message') ?>"><?= $val('message') ?></textarea>
              </label>
            </div>
            <button class="btn btn-red submit-btn" type="submit">✉ &nbsp; Send Message</button>
          </form>
        </div>
      </div>
    </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
