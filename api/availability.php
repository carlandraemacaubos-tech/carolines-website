<?php
/**
 * api/availability.php - read-only JSON endpoint used by the booking page calendar.
 * It reads the CURRENT availability from MySQL on every request (never cached), so any change the
 * administrator makes shows up for customers immediately.
 *
 *   ?action=month&month=2026-11
 *   ?action=start_times&date=2026-11-25
 *   ?action=end_times&start_date=2026-11-25&start_time=08:00&end_date=2026-11-26
 */
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_out(['error' => 'Method not allowed.'], 405);
}

$now    = date('Y-m-d H:i:s');
$action = (string) ($_GET['action'] ?? '');

try {
    // ---- A whole month for the calendar ---------------------------------------------------
    if ($action === 'month') {
        $month = (string) ($_GET['month'] ?? '');
        if (!preg_match('/^(20\d\d)-(0[1-9]|1[0-2])$/', $month)) {
            json_out(['error' => 'Invalid month.'], 400);
        }
        $first = $month . '-01';
        $last  = (new DateTimeImmutable($first))->modify('last day of this month')->format('Y-m-d');
        $ctx   = avail_load($first, $last);
        json_out([
            'month'    => $month,
            'today'    => substr($now, 0, 10),
            'slot'     => $ctx['slot'],
            'max_days' => MAX_BOOKING_DAYS,
            'days'     => avail_month($ctx, $month, $now),
        ]);
    }

    // ---- Start times for one date ---------------------------------------------------------
    if ($action === 'start_times') {
        $date = (string) ($_GET['date'] ?? '');
        if (!parse_date_strict($date)) {
            json_out(['error' => 'Invalid date.'], 400);
        }
        $ctx     = avail_load($date, $date);
        $options = avail_start_options($ctx, $date, $now);
        $status  = avail_day_status($ctx, $date, $now);

        $message = '';
        if (!$options) {
            $message = $status === 'past'
                ? 'This date has already passed. Please select another date and time.'
                : 'No available schedules for this date. Please select another date and time.';
        }
        json_out([
            'times'   => array_map(fn($t) => ['value' => $t, 'label' => minutes_label(time_to_minutes($t))], $options),
            'windows' => array_map('range_label', avail_free_windows($ctx, $date, $now)),
            'message' => $message,
        ]);
    }

    // ---- End times for a chosen start ---------------------------------------------------------
    if ($action === 'end_times') {
        $startDate = (string) ($_GET['start_date'] ?? '');
        $startTime = (string) ($_GET['start_time'] ?? '');
        $endDate   = (string) ($_GET['end_date'] ?? '');
        if (!parse_date_strict($startDate) || !parse_date_strict($endDate) || parse_time_value($startTime) === null) {
            json_out(['error' => 'Invalid request.'], 400);
        }
        if ($endDate < $startDate) {
            json_out(['times' => [], 'message' => 'The end date cannot be earlier than the start date.']);
        }
        if (date_diff_days($startDate, $endDate) + 1 > MAX_BOOKING_DAYS) {
            json_out(['times' => [], 'message' => 'A booking can last up to ' . MAX_BOOKING_DAYS . ' days. Please choose an earlier end date.']);
        }
        $ctx    = avail_load($startDate, $endDate);
        $result = avail_end_options($ctx, $startDate, $startTime, $endDate, $now);
        json_out([
            'times'   => array_map(fn($t) => ['value' => $t, 'label' => minutes_label(time_to_minutes($t))], $result['options']),
            'message' => $result['message'],
        ]);
    }

    json_out(['error' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    error_log('[PartyBooking] Availability API error: ' . $e->getMessage());
    json_out(['error' => 'Availability could not be loaded right now. Please try again.'], 500);
}
