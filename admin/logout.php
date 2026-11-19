<?php
/** admin/logout.php - ends the admin session. Uses POST + CSRF so another site cannot log you out. */
require __DIR__ . '/../includes/bootstrap.php';
require_post();
csrf_require();

admin_logout();
flash_set('notice', ['type' => 'success', 'text' => 'You have been logged out.']);
redirect('admin/login.php');
