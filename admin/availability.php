<?php
/**
 * admin/availability.php - the administrator's control over WHEN customers can book:
 *   1. weekly opening hours (per weekday, up to 2 ranges)
 *   2. length of the time steps customers choose from
 *   3. blocking a date or a whole period (holiday, trip...)
 *   4. a list of all upcoming date-specific changes, with Edit / Remove
 */
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$ctx = avail_load(date('Y-m-d'), date('Y-m-d'));      // weekly hours + slot size are all we need here
$weekly = array_map('ranges_normalize', $ctx['weekly']);
$slot   = $ctx['slot'];
$overrides = avail_list_overrides(date('Y-m-d'));
$names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$order = [1, 2, 3, 4, 5, 6, 0];                        // show Monday first

$pageTitle   = 'Availability';
$adminActive = 'availability';
require __DIR__ . '/../includes/admin-header.php';
?>
    <div class="page-head">
      <div>
        <h1>Availability</h1>
        <p class="muted">Customers can only book the days and times set here. Changes appear on the booking page immediately.</p>
      </div>
      <a class="btn btn-dark" href="<?= e(url('admin/calendar.php')) ?>">📅 Open calendar</a>
    </div>

    <div class="cols">
      <div>
        <form class="card" method="post" action="<?= e(url('admin/availability-action.php')) ?>" data-once>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_weekly">
          <h2>Weekly opening hours</h2>
          <p class="small muted">Tick the days you work and set your hours. Add a second range for a break (for example 8:00 AM–12:00 PM and 1:00 PM–8:00 PM).</p>
          <div class="table-wrap">
            <table class="data weekly">
              <thead><tr><th>Day</th><th>Open</th><th>Hours</th><th>Second range (optional)</th></tr></thead>
              <tbody>
              <?php foreach ($order as $wd):
                  $r  = $weekly[$wd] ?? [];
                  $r1 = $r[0] ?? ['', ''];
                  $r2 = $r[1] ?? ['', ''];
                  $t  = fn($v) => $v === '' ? '' : minutes_to_time((int) $v); ?>
                <tr>
                  <td data-label="Day"><strong><?= e($names[$wd]) ?></strong></td>
                  <td data-label="Open"><label><input type="checkbox" name="open[<?= $wd ?>]" value="1"<?= $r ? ' checked' : '' ?>> Open</label></td>
                  <td data-label="Hours"><div class="range-row"><input type="time" name="start[<?= $wd ?>][]" value="<?= e($t($r1[0])) ?>"><span>to</span><input type="time" name="end[<?= $wd ?>][]" value="<?= e($t($r1[1])) ?>"></div></td>
                  <td data-label="Second range"><div class="range-row"><input type="time" name="start[<?= $wd ?>][]" value="<?= e($t($r2[0])) ?>"><span>to</span><input type="time" name="end[<?= $wd ?>][]" value="<?= e($t($r2[1])) ?>"></div></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <button class="btn btn-red" type="submit">Save weekly hours</button>
        </form>

        <div class="card">
          <h2>Block dates</h2>
          <p class="small muted">Make a date (or a period) unavailable, for example a holiday or trip. Customers cannot book blocked dates. Already approved bookings are <strong>not</strong> cancelled.</p>
          <form method="post" action="<?= e(url('admin/availability-action.php')) ?>" data-once>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="block_range">
            <div class="two">
              <label class="f">From <input type="date" name="from" min="<?= e(date('Y-m-d')) ?>" required></label>
              <label class="f">Until (same day for one date) <input type="date" name="to" min="<?= e(date('Y-m-d')) ?>" required></label>
            </div>
            <label class="f">Private note (optional) <input type="text" name="note" maxlength="255" placeholder="e.g. Vacation"></label>
            <button class="btn btn-dark" type="submit">⛔ Block these dates</button>
          </form>
        </div>
      </div>

      <div>
        <form class="card" method="post" action="<?= e(url('admin/availability-action.php')) ?>" data-once>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_settings">
          <h2>Time steps</h2>
          <p class="small muted">Customers pick start and end times in steps of this length.</p>
          <label class="f">Step length
            <select name="slot_minutes">
              <?php foreach (SLOT_CHOICES as $choice): ?>
                <option value="<?= $choice ?>"<?= $slot === $choice ? ' selected' : '' ?>><?= $choice === 60 ? '1 hour' : $choice . ' minutes' ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <button class="btn btn-dark" type="submit">Save</button>
        </form>

        <div class="card">
          <h2>Upcoming changes</h2>
          <?php if (!$overrides): ?>
            <p class="muted">No blocked dates or custom hours. Weekly hours apply to every date.</p>
          <?php else: ?>
            <ul class="activity">
              <?php foreach ($overrides as $o): ?>
                <li>
                  <strong><?= e(format_date($o['date'] . ' 00:00:00')) ?></strong><br>
                  <?= $o['mode'] === 'closed'
                      ? '<span class="badge badge-rejected">Blocked</span>'
                      : '<span class="badge badge-pending_review">Custom: ' . e(implode(', ', array_map('range_label', ranges_normalize($o['ranges'])))) . '</span>' ?>
                  <?= $o['note'] !== '' ? '<small class="muted"> · ' . e($o['note']) . '</small>' : '' ?>
                  <div class="actions" style="margin-top:6px">
                    <a class="btn btn-outline btn-sm" href="<?= e(url('admin/calendar-day.php?date=' . $o['date'])) ?>">Edit</a>
                    <form method="post" action="<?= e(url('admin/availability-action.php')) ?>" class="inline" data-confirm="Remove this change and use the weekly hours for <?= e($o['date']) ?> again?" data-once>
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="remove_override">
                      <input type="hidden" name="date" value="<?= e($o['date']) ?>">
                      <button class="btn btn-outline-red btn-sm" type="submit">Remove</button>
                    </form>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
