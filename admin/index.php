<?php
/** /admin/ simply opens the dashboard (which sends visitors who are not logged in to the login page). */
require __DIR__ . '/../includes/bootstrap.php';
redirect('admin/dashboard.php');
