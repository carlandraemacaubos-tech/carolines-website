<?php
/**
 * scripts/create-admin.php - creates (or resets the password of) an administrator account.
 * Run it in a terminal from the project folder:
 *
 *     php scripts/create-admin.php
 *
 * It cannot be run from a web browser. The password is stored only as a password_hash() hash.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';

/** Ask a question in the terminal. $hidden hides typing on Mac/Linux. */
function ask(string $question, bool $hidden = false): string
{
    echo $question;
    $isWindows = stripos(PHP_OS, 'WIN') === 0;
    if ($hidden && !$isWindows) {
        shell_exec('stty -echo');
    }
    $answer = trim((string) fgets(STDIN));
    if ($hidden && !$isWindows) {
        shell_exec('stty echo');
        echo PHP_EOL;
    }
    return $answer;
}

echo PHP_EOL . "=== Create administrator account ===" . PHP_EOL;
if (stripos(PHP_OS, 'WIN') === 0) {
    echo "(Note: on Windows the password is visible while you type.)" . PHP_EOL;
}

$username = ask('Username (letters/numbers, 3-50 characters): ');
$email    = strtolower(ask('Email address: '));
$password = ask('Password (at least 10 characters): ', true);
$confirm  = ask('Repeat password: ', true);

$errors = [];
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    $errors[] = 'Username must be 3-50 characters: letters, numbers, dot, dash or underscore.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email address is not valid.';
}
if (strlen($password) < 10) {
    $errors[] = 'Password must be at least 10 characters.';
}
if ($password !== $confirm) {
    $errors[] = 'The two passwords do not match.';
}
if ($errors) {
    fwrite(STDERR, PHP_EOL . implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

$pdo  = db();
$hash = password_hash($password, PASSWORD_DEFAULT);

$exists = $pdo->prepare('SELECT id FROM administrators WHERE username = :u OR email = :e LIMIT 1');
$exists->execute([':u' => $username, ':e' => $email]);
$row = $exists->fetch();

if ($row) {
    $answer = strtolower(ask(PHP_EOL . 'An account with that username or email exists. Reset its password? (yes/no): '));
    if ($answer !== 'yes') {
        echo 'Nothing changed.' . PHP_EOL;
        exit(0);
    }
    $pdo->prepare('UPDATE administrators SET username = :u, email = :e, password_hash = :h WHERE id = :id')
        ->execute([':u' => $username, ':e' => $email, ':h' => $hash, ':id' => $row['id']]);
    echo PHP_EOL . "Password updated for '$username'." . PHP_EOL;
} else {
    $pdo->prepare('INSERT INTO administrators (username, email, password_hash) VALUES (:u, :e, :h)')
        ->execute([':u' => $username, ':e' => $email, ':h' => $hash]);
    echo PHP_EOL . "Administrator '$username' created. Log in at " . absolute_url('admin/login.php') . PHP_EOL;
}
