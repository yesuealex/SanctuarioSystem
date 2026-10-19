<?php
require_once __DIR__ . '/security.php';
secure_session_start();
require_once __DIR__ . '/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: login.php'); exit; }
if (!csrf_verify()) { header('Location: login.php?error=invalid_request'); exit; }
$identifier = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
if ($identifier === '' || $password === '' || strlen($identifier) > 150 || strlen($password) > 1024) {
    header('Location: login.php?error=invalid_credentials'); exit;
}
try {
    $stmt = $conn->prepare('SELECT user_id, name, username, password, role, failed_login_attempts, account_locked, locked_at FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    // Run password verification even for unknown users to reduce timing differences.
    $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $valid = password_verify($password, $user['password'] ?? $dummyHash);
    if ($user && (int)$user['account_locked'] === 1) {
        $lockedAt = strtotime((string)($user['locked_at'] ?? ''));
        if ($lockedAt && time() - $lockedAt >= 900) {
            $stmt = $conn->prepare('UPDATE users SET account_locked = 0, failed_login_attempts = 0, locked_at = NULL WHERE user_id = ?');
            $stmt->bind_param('i', $user['user_id']); $stmt->execute(); $stmt->close();
        } else {
            header('Location: login.php?error=invalid_credentials'); exit;
        }
    }
    if (!$user || !$valid) {
        if ($user) {
            $stmt = $conn->prepare('UPDATE users SET failed_login_attempts = failed_login_attempts + 1, account_locked = IF(failed_login_attempts + 1 >= 5, 1, 0), locked_at = IF(failed_login_attempts + 1 >= 5, NOW(), NULL) WHERE user_id = ?');
            $stmt->bind_param('i', $user['user_id']); $stmt->execute(); $stmt->close();
        }
        header('Location: login.php?error=invalid_credentials'); exit;
    }
    if (!in_array($user['role'], ['admin','super admin','staff','maintenance'], true)) {
        header('Location: login.php?error=role_not_found'); exit;
    }
    $stmt = $conn->prepare('UPDATE users SET failed_login_attempts = 0, account_locked = 0, locked_at = NULL WHERE user_id = ?');
    $stmt->bind_param('i', $user['user_id']); $stmt->execute(); $stmt->close();
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
        $stmt->bind_param('si', $hash, $user['user_id']); $stmt->execute(); $stmt->close();
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['user_id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['authenticated_at'] = time();
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']);
    $dest = in_array($user['role'], ['admin','super admin'], true) ? 'adminDashboard.php' : ($user['role'] === 'staff' ? 'staffDashboard.php' : 'maintenanceDashboard.php');
    header('Location: ' . $dest); exit;
} catch (Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    header('Location: login.php?error=system_error'); exit;
}