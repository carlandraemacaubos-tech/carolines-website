<?php
/**
 * admin/login.php - administrator login.
 * Protected by: CSRF token, rate limiting (5 failures / 15 min), password_verify(),
 * and a new session ID after login.
 */
require __DIR__ . '/../includes/bootstrap.php';

if (admin_logged_in()) {
    redirect('admin/dashboard.php');
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $login    = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (rate_limit_count('admin_login', client_ip(), LOGIN_WINDOW) >= LOGIN_MAX_FAILURES) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } elseif ($login === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } elseif (admin_attempt_login($login, $password)) {
        redirect('admin/dashboard.php');
    } else {
        rate_limit_record('admin_login', client_ip());            // count only failures
        $error = 'Incorrect username or password.';              // same message either way
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/../includes/admin-header.php';
?>
    <div class="login-box card">
      <h1>Admin log in</h1>
      <p class="muted" style="text-align:center">Caroline's Party Helper bookings</p>
      <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="<?= e(url('admin/login.php')) ?>" data-once>
        <?= csrf_field() ?>
        <label class="f">Username or email
          <input type="text" name="login" value="<?= e((string) ($_POST['login'] ?? '')) ?>" autocomplete="username" required autofocus>
        </label>
        <label class="f">Password
          <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="btn btn-red" type="submit" style="width:100%">Log in</button>
      </form>
      <p class="small muted" style="text-align:center;margin-top:16px"><a href="<?= e(url('index.php')) ?>">← Back to the website</a></p>
    </div>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
