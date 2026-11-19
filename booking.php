<?php
/**
 * Booking page: the "Request a Booking" form.
 * The form posts to booking/submit-booking.php. If the server finds a problem, the visitor is sent
 * back here with their answers refilled and the error messages shown.
 */
require __DIR__ . '/includes/bootstrap.php';

$pageTitle       = 'Request a Booking';
$activePage      = 'booking';
$metaDescription = "Request party help from Caroline's Party Helper - choose your start and end date and time.";
$extraScripts    = ['js/booking.js'];

// Values and errors left over from a failed submission (they are shown once, then forgotten).
$old    = flash_get('booking_old', []);
$errors = flash_get('booking_errors', []);

// The home/services pages link here with ?event=Birthday+Party to pre-select the event type.
if (empty($old['event_type']) && isset($_GET['event']) && in_array($_GET['event'], EVENT_TYPES, true)) {
    $old['event_type'] = $_GET['event'];
}
$oldHelp = is_array($old['help'] ?? null) ? $old['help'] : [];

/** Small helper: old value of a text field, escaped. */
$val = fn(string $key): string => e((string) ($old[$key] ?? ''));
/** Small helper: error message for a field, escaped. */
$err = fn(string $key): string => e($errors[$key] ?? '');
/** Small helper: ' is-invalid' class when a field has an error. */
$bad = fn(string $key): string => isset($errors[$key]) ? ' is-invalid' : '';


require __DIR__ . '/includes/header.php';
?>
    <section class="page-hero">
      <div class="hero-shade"></div>
      <div class="container">
        <p class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> / Request a Booking</p>
        <h1>Request a Booking</h1>
        <p>Tell me a little about your event and what kind of help you're looking for.</p>
      </div>
    </section>

    <!-- BOOKING FORM -->
    <section class="booking section-pad">
      <div class="container booking-layout">
        <div class="booking-copy">
          <p class="eyebrow light">LET'S PLAN SOMETHING LOVELY</p>
          <h2>What happens next?</h2>
          <p>I'll get back to you to discuss availability and details.</p>
          <ol class="next-steps">
            <li>Pick an available date and time on the calendar and send your request.</li>
            <li>Verify your email using the link we send you (valid <?= VERIFICATION_MINUTES ?> minutes).</li>
            <li>I review your request.</li>
            <li>You receive my decision by email.</li>
          </ol>
          <div class="contact-box">
            <strong>(631) 997-9504</strong>
            <a class="btn btn-gold" href="tel:+16319979504">☎ &nbsp; Call Caroline</a>
            <a class="btn btn-outline-dark" href="sms:+16319979504">▤ &nbsp; Text Caroline</a>
            <small>Prefer email? <a href="mailto:carolinespartyhelper@gmail.com">carolinespartyhelper@gmail.com</a></small>
            <small>Questions first? <a href="<?= e(url('contact.php')) ?>">Visit the contact page</a> or see <a href="<?= e(url('services.php')) ?>">what's included</a>.</small>
          </div>
        </div>

        <form class="booking-form" id="bookingForm" method="post" action="<?= e(url('booking/submit-booking.php')) ?>" data-api="<?= e(url('api/availability.php')) ?>" data-max-days="<?= (int) MAX_BOOKING_DAYS ?>" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="form_nonce" value="<?= e(form_nonce_issue('booking')) ?>">
          <!-- Honeypot: invisible to people, tempting to spam bots. -->
          <div class="hp-field" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

          <div class="form-note">Submitting this form does not confirm a reservation. You will verify your email, then Caroline will review your request and contact you.</div>

          <?php if ($errors): ?>
            <div class="alert alert-error form-errors" role="alert">
              <strong>Please fix the following:</strong>
              <ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
            </div>
          <?php endif; ?>
          <div class="alert alert-error form-errors" id="formErrors" role="alert" hidden></div>

          <div class="form-grid">
            <label class="field">Full Name <span class="required-star">*</span>
              <input name="name" value="<?= $val('name') ?>" maxlength="100" autocomplete="name" placeholder="Your name" class="<?= $bad('name') ?>" data-field="name">
              <span class="field-error" id="err-name"><?= $err('name') ?></span>
            </label>
            <label class="field">Phone Number <span class="required-star">*</span>
              <input name="phone" type="tel" value="<?= $val('phone') ?>" maxlength="30" autocomplete="tel" placeholder="Your phone number" class="<?= $bad('phone') ?>" data-field="phone">
              <span class="field-error" id="err-phone"><?= $err('phone') ?></span>
            </label>
            <label class="field full">Email <span class="required-star">*</span>
              <input name="email" type="email" value="<?= $val('email') ?>" maxlength="190" autocomplete="email" placeholder="you@example.com" class="<?= $bad('email') ?>" data-field="email">
              <span class="field-hint">We'll send a link to this address to verify your request.</span>
              <span class="field-error" id="err-email"><?= $err('email') ?></span>
            </label>
            <label class="field">Type of Event <span class="required-star">*</span>
              <select name="event_type" class="<?= $bad('event_type') ?>" data-field="event_type">
                <option value="">Choose an event</option>
                <?php foreach (EVENT_TYPES as $type): ?>
                  <option value="<?= e($type) ?>"<?= ($old['event_type'] ?? '') === $type ? ' selected' : '' ?>><?= e($type) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="field-error" id="err-event_type"><?= $err('event_type') ?></span>
            </label>
            <label class="field">Estimated Number of Guests
              <input name="guests" type="number" min="1" max="5000" value="<?= $val('guests') ?>" placeholder="e.g. 30" class="<?= $bad('guests') ?>" data-field="guests">
              <span class="field-error" id="err-guests"><?= $err('guests') ?></span>
            </label>
            <label class="field full">Event Location <span class="required-star">*</span>
              <input name="event_location" value="<?= $val('event_location') ?>" maxlength="255" autocomplete="street-address" placeholder="Address or venue name, city" class="<?= $bad('event_location') ?>" data-field="event_location">
              <span class="field-error" id="err-event_location"><?= $err('event_location') ?></span>
            </label>

            <div class="full">
              <span class="field-title">What kind of help do you need?</span>
              <div class="check-grid">
                <?php foreach (HELP_OPTIONS as $option): ?>
                  <label><input type="checkbox" name="help[]" value="<?= e($option) ?>"<?= in_array($option, $oldHelp, true) ? ' checked' : '' ?>> <?= e($option) ?></label>
                <?php endforeach; ?>
              </div>
              <span class="field-error" id="err-help"><?= $err('help') ?></span>
            </div>

            <!-- EVENT SCHEDULE: an interactive calendar + time lists. Everything shown comes from the admin's
                 availability settings (api/availability.php); the server checks it again on submit. -->
            <fieldset class="schedule full" id="schedule">
              <legend>📅 EVENT SCHEDULE</legend>
              <p class="schedule-help">Choose your <strong>start date</strong> on the calendar, then your times. For a multi-day booking, click <strong>End Date</strong> and pick a later date.</p>
              <noscript><div class="alert alert-warning">Please turn on JavaScript to choose your dates, or call/text Caroline at (631) 997-9504.</div></noscript>

              <input type="hidden" name="start_date" id="start_date" value="<?= $val('start_date') ?>">
              <input type="hidden" name="end_date" id="end_date" value="<?= $val('end_date') ?>">

              <div class="schedule-fields">
                <div class="field">
                  <span class="field-label" id="lbl-start-date">📅 Start Date <span class="required-star">*</span></span>
                  <button type="button" class="date-btn" id="startDateBtn" aria-labelledby="lbl-start-date startDateBtn" aria-pressed="true" data-field="start_date">Choose a date</button>
                  <span class="field-error" id="err-start_date"><?= $err('start_date') ?></span>
                </div>
                <div class="field">
                  <span class="field-label" id="lbl-end-date">📅 End Date <span class="required-star">*</span></span>
                  <button type="button" class="date-btn" id="endDateBtn" aria-labelledby="lbl-end-date endDateBtn" aria-pressed="false" data-field="end_date">Choose a date</button>
                  <span class="field-error" id="err-end_date"><?= $err('end_date') ?></span>
                </div>
                <div class="field">
                  <label class="field-label" for="start_time">🕗 Start Time <span class="required-star">*</span></label>
                  <select name="start_time" id="start_time" data-selected="<?= $val('start_time') ?>" class="<?= $bad('start_time') ?>" data-field="start_time" disabled>
                    <option value="">Choose a start date first</option>
                  </select>
                  <span class="field-error" id="err-start_time"><?= $err('start_time') ?></span>
                </div>
                <div class="field">
                  <label class="field-label" for="end_time">🕔 End Time <span class="required-star">*</span></label>
                  <select name="end_time" id="end_time" data-selected="<?= $val('end_time') ?>" class="<?= $bad('end_time') ?>" data-field="end_time" disabled>
                    <option value="">Choose a start time first</option>
                  </select>
                  <span class="field-error" id="err-end_time"><?= $err('end_time') ?></span>
                </div>
              </div>

              <div class="cal" id="calendar" aria-label="Availability calendar">
                <div class="cal-head">
                  <button type="button" class="cal-nav" id="calPrev" aria-label="Previous month">‹</button>
                  <strong id="calTitle" aria-live="polite">Loading…</strong>
                  <button type="button" class="cal-nav" id="calNext" aria-label="Next month">›</button>
                </div>
                <p class="cal-mode" id="calMode">Selecting: <strong>start date</strong></p>
                <div class="cal-weekdays" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
                <div class="cal-grid" id="calGrid" role="grid"></div>
                <ul class="cal-legend" aria-label="Calendar key">
                  <li><i class="lg lg-available"></i> Available</li>
                  <li><i class="lg lg-selected"></i> Your dates</li>
                  <li><i class="lg lg-off"></i> Unavailable</li>
                </ul>
              </div>

              <p class="avail-message" id="availMessage" role="status" aria-live="polite"></p>
              <p class="duration-note" id="durationNote" aria-live="polite"></p>
              <span class="field-error" id="err-schedule"><?= $err('schedule') ?></span>
            </fieldset>

            <label class="field full">Tell me a little more about your event
              <textarea name="message" rows="4" maxlength="2000" placeholder="Theme, special requests, or anything I should know..." class="<?= $bad('message') ?>" data-field="message"><?= $val('message') ?></textarea>
              <span class="field-error" id="err-message"><?= $err('message') ?></span>
            </label>
          </div>

          <button class="btn btn-red submit-btn" type="submit" data-label="✉ &nbsp; Send Booking Request">✉ &nbsp; Send Booking Request</button>
          <p class="form-status" id="formStatus" aria-live="polite"></p>
        </form>
      </div>
    </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
