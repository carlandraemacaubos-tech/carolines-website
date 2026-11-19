<?php
/**
 * admin/availability-action.php - handles every availability form (POST only):
 *   save_weekly, save_settings, block_range, save_day, remove_override
 * Requires a valid admin session and CSRF token. Everything is validated again here.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_post();
$admin = require_admin();
csrf_require();

$action = (string) ($_POST['action'] ?? '');

/** Redirect with a flash message. */
function done(string $type, string $text, string $to): void
{
    flash_set('notice', ['type' => $type, 'text' => $text]);
    redirect($to);
}

/** Number of approved bookings touching [$from, $to]. */
function approved_in_period(string $from, string $to): int
{
    return count(booking_list_overlapping($from . ' 00:00:00', date_add_days($to, 1) . ' 00:00:00', ['approved']));
}

switch ($action) {
    // ---- Weekly hours --------------------------------------------------------------------
    case 'save_weekly':
        $byWeekday = [];
        for ($wd = 0; $wd <= 6; $wd++) {
            if (empty($_POST['open'][$wd])) {
                $byWeekday[$wd] = [];                       // unticked = closed every week
                continue;
            }
            $starts = is_array($_POST['start'][$wd] ?? null) ? $_POST['start'][$wd] : [];
            $ends   = is_array($_POST['end'][$wd] ?? null) ? $_POST['end'][$wd] : [];
            $parsed = avail_parse_ranges($starts, $ends, 2);
            if ($parsed['error'] !== null) {
                done('error', ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$wd] . ': ' . $parsed['error'], 'admin/availability.php');
            }
            if (!$parsed['ranges']) {
                done('error', ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$wd] . ' is ticked as open, so please enter its hours (or untick it).', 'admin/availability.php');
            }
            $byWeekday[$wd] = $parsed['ranges'];
        }
        avail_save_weekly($byWeekday);
        done('success', 'Weekly hours saved. The booking page now uses them.', 'admin/availability.php');

    // ---- Time step length ---------------------------------------------------------------------
    case 'save_settings':
        $minutes = (int) ($_POST['slot_minutes'] ?? 0);
        if (!in_array($minutes, SLOT_CHOICES, true)) {
            done('error', 'Please choose a valid step length.', 'admin/availability.php');
        }
        avail_set_slot_minutes($minutes);
        done('success', 'Time step length saved.', 'admin/availability.php');

    // ---- Block a date or a period ---------------------------------------------------------------
    case 'block_range':
        $from = (string) ($_POST['from'] ?? '');
        $to   = (string) ($_POST['to'] ?? '');
        $note = trim((string) ($_POST['note'] ?? ''));
        if (!parse_date_strict($from) || !parse_date_strict($to)) {
            done('error', 'Please choose valid dates.', 'admin/availability.php');
        }
        if ($from < date('Y-m-d')) {
            done('error', 'You cannot block dates in the past.', 'admin/availability.php');
        }
        if ($to < $from) {
            done('error', 'The "until" date cannot be earlier than the "from" date.', 'admin/availability.php');
        }
        if (date_diff_days($from, $to) > 365) {
            done('error', 'Please block at most one year at a time.', 'admin/availability.php');
        }
        $count = avail_block_range($from, $to, $note, $admin['id']);
        $existing = approved_in_period($from, $to);
        done($existing ? 'warning' : 'success',
            $count . ' date' . ($count === 1 ? '' : 's') . ' blocked.'
            . ($existing ? ' Note: ' . $existing . ' approved booking(s) already exist in that period and were NOT cancelled.' : ''),
            'admin/availability.php');

    // ---- One date: follow weekly hours / closed / custom hours -------------------------------------
    case 'save_day':
        $date = (string) ($_POST['date'] ?? '');
        $mode = (string) ($_POST['mode'] ?? '');
        $note = trim((string) ($_POST['note'] ?? ''));
        $back = 'admin/calendar-day.php?date=' . urlencode($date);
        if (!parse_date_strict($date) || !in_array($mode, ['default', 'closed', 'custom'], true)) {
            done('error', 'Invalid request.', 'admin/calendar.php');
        }
        $ranges = [];
        if ($mode === 'custom') {
            $parsed = avail_parse_ranges(is_array($_POST['start'] ?? null) ? $_POST['start'] : [], is_array($_POST['end'] ?? null) ? $_POST['end'] : []);
            if ($parsed['error'] !== null) {
                done('error', $parsed['error'], $back);
            }
            if (!$parsed['ranges']) {
                done('error', 'Please enter at least one available time range, or choose another option.', $back);
            }
            $ranges = $parsed['ranges'];
        }
        avail_save_day($date, $mode, $ranges, $note, $admin['id']);
        $existing = $mode === 'default' ? 0 : approved_in_period($date, $date);
        $text = ['default' => 'This date now follows the weekly hours.', 'closed' => 'This date is now unavailable.', 'custom' => 'Custom hours saved for this date.'][$mode];
        done($existing && $mode === 'closed' ? 'warning' : 'success',
            $text . ($existing && $mode === 'closed' ? ' Note: approved bookings on this date were NOT cancelled.' : ''), $back);

    // ---- Remove a date-specific change ---------------------------------------------------------------
    case 'remove_override':
        $date = (string) ($_POST['date'] ?? '');
        if (!parse_date_strict($date)) {
            done('error', 'Invalid date.', 'admin/availability.php');
        }
        avail_save_day($date, 'default', [], '', $admin['id']);
        done('success', 'Removed. ' . format_date($date . ' 00:00:00') . ' now follows the weekly hours.', 'admin/availability.php');

    default:
        done('error', 'Unknown action.', 'admin/availability.php');
}
