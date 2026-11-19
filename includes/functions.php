<?php
/**
 * includes/functions.php
 * Small reusable helpers: escaping, URLs, flash messages, rate limiting,
 * date/time formatting and booking-form validation.
 */

// ---------------------------------------------------------------------------
// Lists used by the form AND the server-side validation (one place to edit)
// ---------------------------------------------------------------------------
const EVENT_TYPES = [
    'Birthday Party', 'Anniversary', 'Baby Shower', 'Bridal Shower', 'Graduation',
    'Family Gathering', 'Church Event', 'Holiday Party', 'Wedding or Special Celebration', 'Other',
];
const HELP_OPTIONS = ['Setup', 'Serving', 'Guest Assistance', 'Keeping Things Tidy', 'Light Cleanup', 'Other'];
const BOOKING_STATUS_LABELS = [
    'pending_email'  => 'Waiting for email verification',
    'pending_review' => 'Pending review',
    'approved'       => 'Approved',
    'rejected'       => 'Rejected',
];
const VERIFICATION_MINUTES = 30;      // how long the emailed link stays valid

// ---------------------------------------------------------------------------
// Output and URLs
// ---------------------------------------------------------------------------

/** Escape text before printing it in HTML (prevents XSS). Use this on EVERY dynamic value. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Site-relative URL, e.g. url('booking.php') -> /party-booking-system/booking.php */
function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

/** Full URL (used inside emails). */
function absolute_url(string $path = ''): string
{
    return rtrim((string) env('APP_URL'), '/') . '/' . ltrim($path, '/');
}

/** URL of a file in /assets, e.g. asset('css/style.css') */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/** Redirect to a site page and stop. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Only allow POST requests on action pages. */
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method not allowed.');
    }
}

// ---------------------------------------------------------------------------
// Flash messages: shown once on the next page, then forgotten
// ---------------------------------------------------------------------------
function flash_set(string $key, $value): void
{
    $_SESSION['flash'][$key] = $value;
}

function flash_get(string $key, $default = null)
{
    $value = $_SESSION['flash'][$key] ?? $default;
    unset($_SESSION['flash'][$key]);
    return $value;
}

/** Print a flash "notice" (['type' => success|error|warning|info, 'text' => ...]) if there is one. */
function render_notice(): string
{
    $notice = flash_get('notice');
    if (!is_array($notice) || empty($notice['text'])) {
        return '';
    }
    $type = in_array($notice['type'] ?? '', ['success', 'error', 'warning', 'info'], true) ? $notice['type'] : 'info';
    return '<div class="alert alert-' . $type . '" role="alert">' . e($notice['text']) . '</div>';
}

// ---------------------------------------------------------------------------
// Rate limiting (stops spam and brute-force attempts)
// ---------------------------------------------------------------------------

/** The visitor's IP address as seen by the web server. */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** How many attempts were recorded in the last $window seconds? */
function rate_limit_count(string $bucket, string $identifier, int $window): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM rate_limits WHERE bucket = :b AND identifier_hash = :i AND created_at >= :since'
    );
    $stmt->execute([
        ':b' => $bucket,
        ':i' => hash('sha256', $identifier),
        ':since' => date('Y-m-d H:i:s', time() - $window),
    ]);
    return (int) $stmt->fetchColumn();
}

/** Record one attempt. */
function rate_limit_record(string $bucket, string $identifier): void
{
    db()->prepare('INSERT INTO rate_limits (bucket, identifier_hash, created_at) VALUES (:b, :i, :now)')
        ->execute([':b' => $bucket, ':i' => hash('sha256', $identifier), ':now' => date('Y-m-d H:i:s')]);

    // Occasionally tidy up old rows so the table stays small.
    if (random_int(1, 50) === 1) {
        db()->prepare('DELETE FROM rate_limits WHERE created_at < :old')
            ->execute([':old' => date('Y-m-d H:i:s', time() - 86400)]);
    }
}

/** Check AND record in one step. Returns false when the visitor has used up their allowance. */
function rate_limit_allow(string $bucket, string $identifier, int $max, int $window): bool
{
    if (rate_limit_count($bucket, $identifier, $window) >= $max) {
        return false;
    }
    rate_limit_record($bucket, $identifier);
    return true;
}

// ---------------------------------------------------------------------------
// Dates and times
// ---------------------------------------------------------------------------

function format_datetime(string $dt): string
{
    return (new DateTimeImmutable($dt))->format('l, F j, Y \a\t g:i A');
}

function format_date(string $dt): string
{
    return (new DateTimeImmutable($dt))->format('l, F j, Y');
}

function format_time(string $dt): string
{
    return (new DateTimeImmutable($dt))->format('g:i A');
}

/** Short form for tables: "Nov 25, 2026 8:00 AM". */
function format_short(string $dt): string
{
    return (new DateTimeImmutable($dt))->format('M j, Y g:i A');
}

/** Parse "YYYY-MM-DD" strictly. Returns null for anything invalid (e.g. 2026-02-31). */
function parse_date_strict(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }
    return $date->format('Y-m-d') === $value ? $date : null;
}

/**
 * Read a time value such as "08:00" or "17:30" (24-hour, sent by the booking page's time list).
 * Returns "HH:MM:00" or null when it is missing or invalid.
 */
function parse_time_value(string $value): ?string
{
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $m)) {
        return null;
    }
    return $m[1] . ':' . $m[2] . ':00';
}

// ---------------------------------------------------------------------------
// Booking form validation (the server never trusts the browser)
// ---------------------------------------------------------------------------

/**
 * Validate the booking form.
 * Returns ['errors' => [field => message], 'clean' => [...cleaned values...]].
 */
function validate_booking_input(array $in, ?DateTimeImmutable $now = null): array
{
    $now    = $now ?? new DateTimeImmutable('now');
    $errors = [];
    $clean  = [];

    // Text fields ------------------------------------------------------------
    $clean['name'] = trim(preg_replace('/\s+/', ' ', (string) ($in['name'] ?? '')));
    if (mb_strlen($clean['name']) < 2 || mb_strlen($clean['name']) > 100) {
        $errors['name'] = 'Please enter your full name (2 to 100 characters).';
    }

    $clean['email'] = strtolower(trim((string) ($in['email'] ?? '')));
    if ($clean['email'] === '' || mb_strlen($clean['email']) > 190 || !filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address (we send a verification link there).';
    }

    $clean['phone'] = trim((string) ($in['phone'] ?? ''));
    $phoneDigits = preg_replace('/\D/', '', $clean['phone']);
    if (!preg_match('/^[0-9+().\-\s]{7,30}$/', $clean['phone']) || strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
        $errors['phone'] = 'Please enter a valid contact number.';
    }

    $clean['event_type'] = (string) ($in['event_type'] ?? '');
    if (!in_array($clean['event_type'], EVENT_TYPES, true)) {
        $errors['event_type'] = 'Please choose a type of event.';
    }

    $clean['event_location'] = trim(preg_replace('/\s+/', ' ', (string) ($in['event_location'] ?? '')));
    if (mb_strlen($clean['event_location']) < 3 || mb_strlen($clean['event_location']) > 255) {
        $errors['event_location'] = 'Please enter where the event will be held.';
    }

    // Optional fields ----------------------------------------------------------
    $guests = trim((string) ($in['guests'] ?? ''));
    $clean['guests'] = null;
    if ($guests !== '') {
        if (!ctype_digit($guests) || (int) $guests < 1 || (int) $guests > 5000) {
            $errors['guests'] = 'Estimated guests must be a whole number from 1 to 5000.';
        } else {
            $clean['guests'] = (int) $guests;
        }
    }

    $help = $in['help'] ?? [];
    $help = is_array($help) ? $help : [];
    $clean['help'] = [];
    foreach ($help as $option) {
        if (is_string($option) && in_array($option, HELP_OPTIONS, true)) {
            $clean['help'][] = $option;
        } else {
            $errors['help'] = 'One of the selected help options is not valid.';
        }
    }
    $clean['help'] = array_values(array_unique($clean['help']));

    $clean['message'] = trim((string) ($in['message'] ?? ''));
    if (mb_strlen($clean['message']) > 2000) {
        $errors['message'] = 'Please keep your message under 2000 characters.';
    }

    // Schedule -------------------------------------------------------------------
    $startDate = parse_date_strict((string) ($in['start_date'] ?? ''));
    $endDate   = parse_date_strict((string) ($in['end_date'] ?? ''));
    $startTime = parse_time_value((string) ($in['start_time'] ?? ''));
    $endTime   = parse_time_value((string) ($in['end_time'] ?? ''));
    $today     = $now->setTime(0, 0);

    $clean['start_date'] = (string) ($in['start_date'] ?? '');
    $clean['start_time'] = (string) ($in['start_time'] ?? '');
    $clean['end_time']   = (string) ($in['end_time'] ?? '');
    $clean['end_date']   = (string) ($in['end_date'] ?? '');

    if (!$startDate) {
        $errors['start_date'] = 'Please choose a start date.';
    } elseif ($startDate < $today) {
        $errors['start_date'] = 'The start date cannot be in the past.';
    }

    if (!$endDate) {
        $errors['end_date'] = 'Please choose an end date.';
    } elseif ($startDate && $endDate < $startDate) {
        $errors['end_date'] = 'The end date cannot be earlier than the start date.';
    }

    if (!$startTime) {
        $errors['start_time'] = 'Please choose a start time.';
    }
    if (!$endTime) {
        $errors['end_time'] = 'Please choose an end time.';
    }

    // Cross-field checks only make sense when every field above is valid.
    if ($startDate && $endDate && $startTime && $endTime && !isset($errors['start_date']) && !isset($errors['end_date'])) {
        $start = new DateTimeImmutable($startDate->format('Y-m-d') . ' ' . $startTime);
        $end   = new DateTimeImmutable($endDate->format('Y-m-d') . ' ' . $endTime);

        if ($start <= $now) {
            $errors['start_time'] = 'The start time must be later than the current time today.';
        } elseif ($end <= $start) {
            $errors['end_time'] = 'The end time must be later than the start time.';
        } else {
            $clean['start_datetime'] = $start->format('Y-m-d H:i:s');
            $clean['end_datetime']   = $end->format('Y-m-d H:i:s');
        }
    }

    return ['errors' => $errors, 'clean' => $clean];
}

/** Colored status label used on the dashboard (HTML-safe). */
function status_badge(string $status): string
{
    $label = BOOKING_STATUS_LABELS[$status] ?? $status;
    return '<span class="badge badge-' . e($status) . '">' . e($label) . '</span>';
}

/** "Verified" / "Not verified" label. */
function email_badge(bool $verified): string
{
    return $verified
        ? '<span class="badge badge-verified">✓ Verified</span>'
        : '<span class="badge badge-unverified">Not verified</span>';
}
