<?php
require_once __DIR__ . '/security.php';
secure_session_start();
require_once __DIR__ . '/db.php';
$token = (string)($_GET['token'] ?? '');
$notice = 'This verification link is invalid or expired.';
if (preg_match('/^[a-f0-9]{64}$/D', $token)) {
    try {
        $hash = hash('sha256', $token);
        $conn->begin_transaction();
        $stmt = $conn->prepare('SELECT id, user_id, pending_email FROM email_verification_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE');
        $stmt->bind_param('s', $hash); $stmt->execute(); $record = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($record) {
            $stmt = $conn->prepare('UPDATE users SET email = ? WHERE user_id = ?');
            $stmt->bind_param('si', $record['pending_email'], $record['user_id']); $stmt->execute(); $stmt->close();
            $stmt = $conn->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
            $stmt->bind_param('i', $record['user_id']); $stmt->execute(); $stmt->close();
            $conn->commit(); $notice = 'Your email address has been verified and saved.';
        } else $conn->rollback();
    } catch (Throwable $e) { $conn->rollback(); error_log('Email verification error: ' . $e->getMessage()); $notice = 'Unable to verify email. Please request a new link.'; }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify email | Sanctuario</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font: 16px Arial, sans-serif;
            background: linear-gradient(135deg, #2e5129, #6b8e5a);
            min-height: 100vh;
            display: grid;
            place-items: center;
            margin: 0;
            padding: 20px;
        }
        .panel {
            background: #fffdf4;
            border-radius: 20px;
            padding: 32px;
            width: min(100%, 410px);
            box-shadow: 0 15px 40px #102d1c55;
            text-align: center;
        }
        h1 { color: #2e5129; margin: 0 0 16px; }
        .notice { color: #2e5129; line-height: 1.6; margin: 0 0 24px; }
        .login-button {
            display: inline-block;
            padding: 12px 22px;
            background: #edd966;
            color: #2e5129;
            border-radius: 24px;
            text-decoration: none;
            font-weight: bold;
            transition: filter .2s ease;
        }
        .login-button:hover { filter: brightness(.95); }
        .login-button:focus-visible { outline: 3px solid #2e5129; outline-offset: 3px; }
    </style>
</head>
<body>
    <main class="panel">
        <h1>Email verification</h1>
        <p class="notice" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p>
        <a class="login-button" href="login.php">Return to login</a>
    </main>
</body>
</html>
