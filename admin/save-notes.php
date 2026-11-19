<?php
/** admin/save-notes.php - saves the private administrator notes for a booking. */
require __DIR__ . '/../includes/bootstrap.php';
require_post();
$admin = require_admin();
csrf_require();

$id    = (int) ($_POST['id'] ?? 0);
$notes = trim((string) ($_POST['notes'] ?? ''));

if ($id < 1 || !booking_find($id)) {
    flash_set('notice', ['type' => 'error', 'text' => 'That booking could not be found.']);
    redirect('admin/dashboard.php');
}
if (mb_strlen($notes) > 4000) {
    flash_set('notice', ['type' => 'error', 'text' => 'Notes must be 4000 characters or fewer.']);
    redirect('admin/booking-details.php?id=' . $id);
}

booking_save_notes($id, $admin['id'], $notes);
flash_set('notice', ['type' => 'success', 'text' => 'Notes saved.']);
redirect('admin/booking-details.php?id=' . $id);
