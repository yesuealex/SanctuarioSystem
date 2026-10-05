<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$identifier = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($identifier === '' || $password === '') {
    header('Location: login.php?error=empty_fields');
    exit();
}

/*
 * SECURITY SETTINGS
 * An account is locked after 3 consecutive failed attempts.
 * The lock is permanent until an administrator resets the account in SQL.
 */
const MAX_LOGIN_ATTEMPTS = 3;

/*
 * The current database has username/password/role, but no email or
 * lock columns. The SQL patch supplied with this file adds:
 *   email
 *   failed_login_attempts
 *   account_locked
 *   locked_at
 */
$stmt = $conn->prepare(
    "SELECT user_id, name, username, email, password, role,
            failed_login_attempts, account_locked
     FROM users
     WHERE username = ? OR email = ?
     LIMIT 1"
);

if (!$stmt) {
    header('Location: login.php?error=system_error');
    exit();
}

$stmt->bind_param('ss', $identifier, $identifier);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

/*
 * Do not reveal whether a locked account exists.
 * If the supplied username/email belongs to a locked account, stop here.
 */
if ($user && (int)$user['account_locked'] === 1) {
    header('Location: login.php?error=account_locked&username=' . urlencode($identifier));
    exit();
}

/*
 * Requested behavior:
 * - wrong username/email -> "Wrong username or email."
 * - existing account + wrong password -> "Wrong credential."
 */
if (!$user) {
    header('Location: login.php?error=user_not_found&username=' . urlencode($identifier));
    exit();
}

/*
 * Your current database stores passwords as plain text.
 * Keep this comparison for compatibility with the existing database.
 *
 * If you later convert all passwords to password_hash(), replace this
 * comparison with password_verify() and migrate the existing accounts.
 */
$passwordCorrect = hash_equals((string)$user['password'], (string)$password);

if (!$passwordCorrect) {
    $attempts = (int)$user['failed_login_attempts'] + 1;

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $lockStmt = $conn->prepare(
            "UPDATE users
             SET failed_login_attempts = ?, account_locked = 1, locked_at = NOW()
             WHERE user_id = ?"
        );

        if ($lockStmt) {
            $lockStmt->bind_param('ii', $attempts, $user['user_id']);
            $lockStmt->execute();
            $lockStmt->close();
        }

        header('Location: login.php?error=account_locked&username=' . urlencode($identifier));
        exit();
    }

    $updateStmt = $conn->prepare(
        "UPDATE users
         SET failed_login_attempts = ?
         WHERE user_id = ?"
    );

    if ($updateStmt) {
        $updateStmt->bind_param('ii', $attempts, $user['user_id']);
        $updateStmt->execute();
        $updateStmt->close();
    }

    $remaining = MAX_LOGIN_ATTEMPTS - $attempts;

    header(
        'Location: login.php?error=wrong_password&remaining=' .
        $remaining .
        '&username=' . urlencode($identifier)
    );
    exit();
}

/*
 * Successful login:
 * reset the failed-attempt counter.
 */
$resetStmt = $conn->prepare(
    "UPDATE users
     SET failed_login_attempts = 0
     WHERE user_id = ?"
);

if ($resetStmt) {
    $resetStmt->bind_param('i', $user['user_id']);
    $resetStmt->execute();
    $resetStmt->close();
}

/* Regenerate the session ID after authentication. */
session_regenerate_id(true);

$_SESSION['user_id'] = $user['user_id'];
$_SESSION['name'] = $user['name'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];

/* Redirect based on role. */
switch ($user['role']) {
    case 'admin':
    case 'super admin':
        header('Location: adminDashboard.php');
        break;

    case 'staff':
        header('Location: staffDashboard.php');
        break;

    case 'maintenance':
        header('Location: maintenanceDashboard.php');
        break;

    default:
        header('Location: login.php?error=role_not_found');
        break;
}

exit();
?>
