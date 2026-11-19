<?php
/**
 * includes/availability.php
 *
 * The availability engine. It decides which dates and times a customer may book.
 * It is used by BOTH the booking page (through api/availability.php) and the server-side
 * validation, so the browser and the server always agree.
 *
 * How availability is decided for a date:
 *   1. If the admin set an OVERRIDE for that date:  "closed" -> unavailable (manually blocked),
 *      "custom" -> only those time ranges are open.
 *   2. Otherwise the WEEKLY HOURS for that weekday are used (no hours = closed).
 *   3. Time already taken by APPROVED bookings is removed. Pending requests never block anything.
 *
 * Datetimes are plain strings "YYYY-MM-DD HH:MM:SS" in the site's time zone and times of day are
 * whole minutes after midnight (08:00 = 480). Plain strings keep the maths simple and avoid
 * daylight-saving surprises.
 */

const MAX_BOOKING_DAYS = 14;            // longest booking a customer may request
const SLOT_CHOICES     = [15, 30, 60];  // allowed lengths of the time steps (minutes)

// ---------------------------------------------------------------------------
// Small time helpers
// ---------------------------------------------------------------------------

/** "08:30" or "08:30:00" -> 510 */
function time_to_minutes(string $time): int
{
    $parts = explode(':', $time);
    return ((int) $parts[0]) * 60 + (int) ($parts[1] ?? 0);
}

/** 510 -> "08:30" */
function minutes_to_time(int $minutes): string
{
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

/** 510 -> "8:30 AM" */
function minutes_label(int $minutes): string
{
    $h  = intdiv($minutes, 60) % 24;
    $m  = $minutes % 60;
    $h12 = $h % 12 ?: 12;
    return $h12 . ':' . sprintf('%02d', $m) . ' ' . ($h >= 12 ? 'PM' : 'AM');
}

/** 480 -> "8 AM", 510 -> "8:30 AM" (short form for the calendar cells) */
function minutes_short(int $minutes): string
{
    return str_replace(':00', '', minutes_label($minutes));
}

/** "8:00 AM - 12:00 PM" */
function range_label(array $range): string
{
    return minutes_label($range[0]) . ' – ' . minutes_label($range[1]);
}

/** Add days to a "YYYY-MM-DD" date. */
function date_add_days(string $date, int $days): string
{
    return (new DateTimeImmutable($date))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

/** Whole days between two "YYYY-MM-DD" dates (b - a). */
function date_diff_days(string $a, string $b): int
{
    return (int) (new DateTimeImmutable($a))->diff(new DateTimeImmutable($b))->format('%r%a');
}

// ---------------------------------------------------------------------------
// Range maths: a "range" is [startMinute, endMinute]
// ---------------------------------------------------------------------------

/** Sort ranges and merge the ones that touch or overlap. */
function ranges_normalize(array $ranges): array
{
    $ranges = array_values(array_filter($ranges, fn($r) => $r[1] > $r[0]));
    usort($ranges, fn($a, $b) => $a[0] <=> $b[0]);
    $out = [];
    foreach ($ranges as $r) {
        if ($out && $r[0] <= $out[count($out) - 1][1]) {
            $out[count($out) - 1][1] = max($out[count($out) - 1][1], $r[1]);
        } else {
            $out[] = $r;
        }
    }
    return $out;
}

/** Remove [$from, $to) from every range. */
function ranges_subtract(array $ranges, int $from, int $to): array
{
    $out = [];
    foreach ($ranges as [$s, $e]) {
        if ($to <= $s || $from >= $e) {
            $out[] = [$s, $e];                      // no overlap
            continue;
        }
        if ($from > $s) {
            $out[] = [$s, $from];
        }
        if ($to < $e) {
            $out[] = [$to, $e];
        }
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Loading data from MySQL into a "context" array
// ---------------------------------------------------------------------------

/** Length of the time steps in minutes (15, 30 or 60). */
function availability_slot_minutes(): int
{
    $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = 'slot_minutes'");
    $stmt->execute();
    $value = (int) $stmt->fetchColumn();
    return in_array($value, SLOT_CHOICES, true) ? $value : 30;
}

/**
 * Load everything needed to answer availability questions for the dates $fromDate..$toDate
 * (both "YYYY-MM-DD", inclusive). One call = 4 small queries, no matter how many questions follow.
 */
function avail_load(string $fromDate, string $toDate): array
{
    $pdo = db();

    $weekly = array_fill(0, 7, []);
    foreach ($pdo->query('SELECT weekday, start_time, end_time FROM weekly_hours ORDER BY weekday, start_time') as $row) {
        $weekly[(int) $row['weekday']][] = [time_to_minutes($row['start_time']), time_to_minutes($row['end_time'])];
    }

    $stmt = $pdo->prepare(
        'SELECT date_value, mode, start_time, end_time, note FROM availability_overrides
          WHERE date_value BETWEEN :f AND :t ORDER BY date_value, start_time'
    );
    $stmt->execute([':f' => $fromDate, ':t' => $toDate]);
    $overrides = [];
    foreach ($stmt->fetchAll() as $row) {
        $d = $row['date_value'];
        $overrides[$d] = $overrides[$d] ?? ['mode' => 'custom', 'ranges' => [], 'note' => (string) $row['note']];
        if ($row['mode'] === 'closed') {
            $overrides[$d]['mode'] = 'closed';
        } elseif ($row['start_time'] !== null && $row['end_time'] !== null) {
            $overrides[$d]['ranges'][] = [time_to_minutes($row['start_time']), time_to_minutes($row['end_time'])];
        }
    }

    // Approved bookings that touch the period (they block their time).
    $stmt = $pdo->prepare(
        "SELECT id, booking_reference, start_datetime, end_datetime FROM bookings
          WHERE booking_status = 'approved' AND start_datetime < :end AND end_datetime > :start"
    );
    $stmt->execute([':start' => $fromDate . ' 00:00:00', ':end' => date_add_days($toDate, 1) . ' 00:00:00']);

    return [
        'slot'      => availability_slot_minutes(),
        'weekly'    => $weekly,
        'overrides' => $overrides,
        'approved'  => $stmt->fetchAll(),
    ];
}

// ---------------------------------------------------------------------------
// Questions about one date (these functions never touch the database)
// ---------------------------------------------------------------------------

/**
 * The opening hours of a date BEFORE bookings are subtracted.
 * state: 'open' | 'closed' (weekly day off) | 'blocked' (admin blocked this date)
 */
function avail_day(array $ctx, string $date): array
{
    if (isset($ctx['overrides'][$date])) {
        $o = $ctx['overrides'][$date];
        if ($o['mode'] === 'closed') {
            return ['state' => 'blocked', 'ranges' => [], 'source' => 'override', 'note' => $o['note']];
        }
        $ranges = ranges_normalize($o['ranges']);
        return ['state' => $ranges ? 'open' : 'blocked', 'ranges' => $ranges, 'source' => 'override', 'note' => $o['note']];
    }
    $weekday = (int) (new DateTimeImmutable($date))->format('w');
    $ranges  = ranges_normalize($ctx['weekly'][$weekday] ?? []);
    return ['state' => $ranges ? 'open' : 'closed', 'ranges' => $ranges, 'source' => 'weekly', 'note' => ''];
}

/** Minutes of $date that are used by approved bookings: list of [start, end]. */
function avail_booked_ranges(array $ctx, string $date): array
{
    $out = [];
    foreach ($ctx['approved'] as $b) {
        $startDate = substr($b['start_datetime'], 0, 10);
        $endDate   = substr($b['end_datetime'], 0, 10);
        if ($startDate > $date || $endDate < $date) {
            continue;
        }
        $s = $startDate < $date ? 0 : time_to_minutes(substr($b['start_datetime'], 11, 5));
        $e = $endDate > $date ? 1440 : time_to_minutes(substr($b['end_datetime'], 11, 5));
        if ($e > $s) {
            $out[] = [$s, $e];
        }
    }
    return $out;
}

/**
 * The free time windows of a date: opening hours minus approved bookings.
 * With $now given, time that has already passed today is removed as well.
 * Windows shorter than one time step are dropped (nobody can book them).
 */
function avail_free_windows(array $ctx, string $date, ?string $now = null): array
{
    $day = avail_day($ctx, $date);
    if ($day['state'] !== 'open') {
        return [];
    }
    $free = $day['ranges'];
    foreach (avail_booked_ranges($ctx, $date) as [$bs, $be]) {
        $free = ranges_subtract($free, $bs, $be);
    }
    if ($now !== null) {
        $today = substr($now, 0, 10);
        if ($date < $today) {
            return [];
        }
        if ($date === $today) {
            $slot = $ctx['slot'];
            $firstStart = (intdiv(time_to_minutes(substr($now, 11, 5)), $slot) + 1) * $slot;   // next step after "now"
            $free = ranges_subtract($free, 0, $firstStart);
        }
    }
    return array_values(array_filter($free, fn($r) => ($r[1] - $r[0]) >= $ctx['slot']));
}

/**
 * Status of a date for the calendar:
 * 'past' | 'blocked' | 'closed' | 'full' (open, but nothing left) | 'available'
 */
function avail_day_status(array $ctx, string $date, string $now): string
{
    if ($date < substr($now, 0, 10)) {
        return 'past';
    }
    $day = avail_day($ctx, $date);
    if ($day['state'] !== 'open') {
        return $day['state'];
    }
    return avail_free_windows($ctx, $date, $now) ? 'available' : 'full';
}

/** Start times a customer may choose on $date (list of "HH:MM"). */
function avail_start_options(array $ctx, string $date, string $now): array
{
    $slot = $ctx['slot'];
    $out  = [];
    foreach (avail_free_windows($ctx, $date, $now) as [$s, $e]) {
        for ($t = (int) ceil($s / $slot) * $slot; $t + $slot <= $e; $t += $slot) {
            $out[] = minutes_to_time($t);
        }
    }
    return $out;
}

/**
 * The master rule: may this exact schedule be booked?
 * Returns null when YES, otherwise ['code' => ..., 'message' => ...] (friendly text for customers).
 * $opts: 'skip_past' and 'skip_grid' are used when an administrator re-checks an existing request.
 */
function avail_check_schedule(array $ctx, string $start, string $end, string $now, array $opts = []): ?array
{
    $slot = $ctx['slot'];
    $fail = fn(string $code, string $message) => ['code' => $code, 'message' => $message];

    if ($end <= $start) {
        return $fail('order', 'The end date and time must be later than the start date and time.');
    }
    if (empty($opts['skip_past']) && $start <= $now) {
        return $fail('past', 'The start must be later than the current date and time.');
    }

    $startDate = substr($start, 0, 10);
    $endDate   = substr($end, 0, 10);
    $startMin  = time_to_minutes(substr($start, 11, 5));
    $endMin    = time_to_minutes(substr($end, 11, 5));

    if (date_diff_days($startDate, $endDate) + 1 > MAX_BOOKING_DAYS) {
        return $fail('too_long', 'A booking can last up to ' . MAX_BOOKING_DAYS . ' days. Please choose an earlier end date.');
    }
    if (empty($opts['skip_grid']) && ($startMin % $slot !== 0 || $endMin % $slot !== 0)) {
        return $fail('grid', 'Please choose the start and end times from the list of available times.');
    }

    // Every day of the booking must be open, and the start/end times must fall inside opening hours.
    for ($date = $startDate; $date <= $endDate; $date = date_add_days($date, 1)) {
        $day = avail_day($ctx, $date);
        if ($day['state'] === 'blocked') {
            return $fail('blocked', 'Sorry, ' . (new DateTimeImmutable($date))->format('F j') . ' is not available. Please select another date and time.');
        }
        if ($day['state'] === 'closed') {
            return $fail('closed', 'No available schedules for ' . (new DateTimeImmutable($date))->format('F j') . '. Please select another date and time.');
        }

        $fits = false;
        foreach ($day['ranges'] as [$s, $e]) {
            if ($startDate === $endDate)   { $fits = $startMin >= $s && $endMin <= $e; }       // one-day booking
            elseif ($date === $startDate)  { $fits = $startMin >= $s && $startMin < $e; }      // first day: must start inside opening hours
            elseif ($date === $endDate)    { $fits = $endMin > $s && $endMin <= $e; }          // last day: must end inside opening hours
            else                           { $fits = true; }                                   // days in between just need to be open
            if ($fits) {
                break;
            }
        }
        if (!$fits) {
            return $fail('hours', 'That time is outside the available hours for ' . (new DateTimeImmutable($date))->format('F j') . '. Please select another date and time.');
        }
    }

    // No overlap with an approved booking (two bookings may share a date only if their times do not overlap).
    foreach ($ctx['approved'] as $b) {
        if ($b['start_datetime'] < $end && $b['end_datetime'] > $start) {
            return $fail('booked', 'This time slot is already booked. Please select another date and time.');
        }
    }
    return null;
}

/**
 * End times a customer may choose on $endDate for a booking that starts at $startDate $startTime.
 * Returns ['options' => ['HH:MM', ...], 'message' => text shown when the list is empty].
 */
function avail_end_options(array $ctx, string $startDate, string $startTime, string $endDate, string $now): array
{
    $slot  = $ctx['slot'];
    $start = $startDate . ' ' . $startTime . ':00';
    $options = [];
    $firstError = null;
    $sawBooked  = false;

    for ($t = $slot; $t < 1440; $t += $slot) {
        $err = avail_check_schedule($ctx, $start, $endDate . ' ' . minutes_to_time($t) . ':00', $now);
        if ($err === null) {
            $options[] = minutes_to_time($t);
        } elseif ($err['code'] === 'booked') {
            $sawBooked = true;
        } elseif ($firstError === null && !in_array($err['code'], ['order', 'hours'], true)) {
            $firstError = $err['message'];
        }
    }

    $message = '';
    if (!$options) {
        $message = $firstError
            ?? ($sawBooked ? 'This time slot is already booked. Please select another date and time.'
                           : 'No available end times for this selection. Please select another date and time.');
    }
    return ['options' => $options, 'message' => $message];
}

/** Calendar data for a whole month: [date => ['status' => ..., 'windows' => ['8:00 AM – 12:00 PM', ...]]] */
function avail_month(array $ctx, string $month, string $now): array
{
    $first = new DateTimeImmutable($month . '-01');
    $days  = [];
    for ($i = 0, $n = (int) $first->format('t'); $i < $n; $i++) {
        $date   = $first->modify('+' . $i . ' days')->format('Y-m-d');
        $status = avail_day_status($ctx, $date, $now);
        $days[$date] = [
            'status'  => $status,
            'windows' => $status === 'available' ? array_map('range_label', avail_free_windows($ctx, $date, $now)) : [],
        ];
    }
    return $days;
}

// ---------------------------------------------------------------------------
// Administrator actions (write to the database)
// ---------------------------------------------------------------------------

/**
 * Turn parallel lists of start/end times ("HH:MM") from a form into ranges.
 * Returns ['ranges' => [[start, end], ...], 'error' => string|null]. Empty rows are skipped.
 */
function avail_parse_ranges(array $starts, array $ends, int $max = 4): array
{
    $ranges = [];
    foreach ($starts as $i => $startRaw) {
        $startRaw = trim((string) $startRaw);
        $endRaw   = trim((string) ($ends[$i] ?? ''));
        if ($startRaw === '' && $endRaw === '') {
            continue;
        }
        $s = parse_time_value($startRaw);
        $e = parse_time_value($endRaw);
        if ($s === null || $e === null) {
            return ['ranges' => [], 'error' => 'Please fill in both the start and end time of each row.'];
        }
        $sm = time_to_minutes($s);
        $em = time_to_minutes($e);
        if ($em <= $sm) {
            return ['ranges' => [], 'error' => 'Each end time must be later than its start time.'];
        }
        $ranges[] = [$sm, $em];
    }
    if (count($ranges) > $max) {
        return ['ranges' => [], 'error' => 'Please use at most ' . $max . ' time ranges per day.'];
    }
    usort($ranges, fn($a, $b) => $a[0] <=> $b[0]);
    for ($i = 1; $i < count($ranges); $i++) {
        if ($ranges[$i][0] < $ranges[$i - 1][1]) {
            return ['ranges' => [], 'error' => 'Time ranges on the same day must not overlap.'];
        }
    }
    return ['ranges' => $ranges, 'error' => null];
}

/** Replace the weekly hours. $byWeekday = [0..6 => [[start, end], ...]] (missing/empty = closed). */
function avail_save_weekly(array $byWeekday): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM weekly_hours');
        $ins = $pdo->prepare('INSERT INTO weekly_hours (weekday, start_time, end_time) VALUES (:d, :s, :e)');
        foreach ($byWeekday as $weekday => $ranges) {
            foreach ($ranges as [$s, $e]) {
                $ins->execute([':d' => (int) $weekday, ':s' => minutes_to_time($s) . ':00', ':e' => minutes_to_time($e) . ':00']);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Set what applies to ONE date. $mode: 'default' (follow weekly hours - removes any override),
 * 'closed' (blocked) or 'custom' (only $ranges are open).
 */
function avail_save_day(string $date, string $mode, array $ranges, string $note, int $adminId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM availability_overrides WHERE date_value = :d')->execute([':d' => $date]);
        $note = $note !== '' ? mb_substr($note, 0, 255) : null;

        if ($mode === 'closed') {
            $pdo->prepare("INSERT INTO availability_overrides (date_value, mode, note, created_by_admin_id) VALUES (:d, 'closed', :n, :a)")
                ->execute([':d' => $date, ':n' => $note, ':a' => $adminId]);
        } elseif ($mode === 'custom') {
            $ins = $pdo->prepare("INSERT INTO availability_overrides (date_value, mode, start_time, end_time, note, created_by_admin_id)
                                  VALUES (:d, 'custom', :s, :e, :n, :a)");
            foreach ($ranges as [$s, $e]) {
                $ins->execute([':d' => $date, ':s' => minutes_to_time($s) . ':00', ':e' => minutes_to_time($e) . ':00', ':n' => $note, ':a' => $adminId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Block every date from $from to $to (inclusive). Returns the number of dates blocked. */
function avail_block_range(string $from, string $to, string $note, int $adminId): int
{
    $count = 0;
    for ($d = $from; $d <= $to; $d = date_add_days($d, 1)) {
        avail_save_day($d, 'closed', [], $note, $adminId);
        $count++;
    }
    return $count;
}

function avail_set_slot_minutes(int $minutes): void
{
    db()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('slot_minutes', :v)
                   ON DUPLICATE KEY UPDATE setting_value = :v2")
        ->execute([':v' => (string) $minutes, ':v2' => (string) $minutes]);
}

/** Overrides from $fromDate onwards, grouped by date, for the admin list. */
function avail_list_overrides(string $fromDate): array
{
    $stmt = db()->prepare(
        'SELECT date_value, mode, start_time, end_time, note FROM availability_overrides
          WHERE date_value >= :d ORDER BY date_value, start_time'
    );
    $stmt->execute([':d' => $fromDate]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $d = $row['date_value'];
        $out[$d] = $out[$d] ?? ['date' => $d, 'mode' => 'custom', 'ranges' => [], 'note' => (string) $row['note']];
        if ($row['mode'] === 'closed') {
            $out[$d]['mode'] = 'closed';
        } else {
            $out[$d]['ranges'][] = [time_to_minutes($row['start_time']), time_to_minutes($row['end_time'])];
        }
    }
    return array_values($out);
}
