<?php
require_once __DIR__ . '/security.php';
secure_session_start();
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
require_once __DIR__ . '/db.php';
$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$error = ''; $done = false;
$tokenHash = preg_match('/^[a-f0-9]{64}$/D', $token) ? hash('sha256', $token) : '';
$record = null;
if ($tokenHash) {
    $stmt = $conn->prepare('SELECT id, user_id FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
    $stmt->bind_param('s', $tokenHash); $stmt->execute(); $record = $stmt->get_result()->fetch_assoc(); $stmt->close();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) $error = 'Invalid request. Refresh the page.';
    elseif (!$record) $error = 'This reset link is invalid or expired.';
    else {
        $password = (string)($_POST['password'] ?? '');
        if (!password_is_strong($password)) $error = 'Use a password of 12–72 characters.';
        elseif ($password !== (string)($_POST['confirm_password'] ?? '')) $error = 'Passwords do not match.';
        else {
            try {
                $conn->begin_transaction();
                $stmt = $conn->prepare('SELECT id, user_id FROM password_reset_tokens WHERE id = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE');
                $stmt->bind_param('is', $record['id'], $tokenHash); $stmt->execute(); $locked = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$locked) throw new RuntimeException('Token no longer valid');
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE users SET password = ?, failed_login_attempts = 0, account_locked = 0, locked_at = NULL WHERE user_id = ?');
                $stmt->bind_param('si', $hash, $locked['user_id']); $stmt->execute(); $stmt->close();
                $stmt = $conn->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
                $stmt->bind_param('i', $locked['user_id']); $stmt->execute(); $stmt->close();
                $conn->commit(); $done = true;
            } catch (Throwable $e) { $conn->rollback(); error_log('Password reset error: ' . $e->getMessage()); $error = 'Unable to reset password. Try again.'; }
        }
    }
}
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reset password | Sanctuario</title><style>body{font:16px Arial,sans-serif;background:linear-gradient(135deg,#2e5129,#6b8e5a);min-height:100vh;display:grid;place-items:center;margin:0}.panel{background:#fffdf4;border-radius:20px;padding:32px;width:min(90%,410px)}h1{color:#2e5129}label{display:block;margin:18px 0 6px}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #bccfb8;border-radius:9px}button{margin-top:18px;border:0;background:#edd966;padding:12px 20px;border-radius:24px;font-weight:bold;cursor:pointer}a{color:#2e5129}.notice{background:#e5f2df;padding:12px;border-radius:8px}</style><style id="sanctuario-responsive-refinement">
/* Sanctuario responsive refinement — layout geometry only, no feature changes. */
@media (max-width: 1199px) {
  html { -webkit-text-size-adjust: 100%; }
  body { min-width: 0; }
  .dashboard, .farm-map-page, .main-content, .page-content, .dashboard-content,
  .content-wrapper, .inventory-page, .records-page, .settings-page {
    min-width: 0; max-width: 100%; box-sizing: border-box;
  }
  .dashboard, .farm-map-page { padding-left: clamp(12px, 2vw, 24px); padding-right: clamp(12px, 2vw, 24px); }
  .hero-container, .farm-summary { padding: clamp(12px, 2vw, 26px); }
  .hero-cards, .top-grid, .bottom-grid, .summary-cards, .farm-map-layout,
  [class*="-grid"], [class*="_grid"] { min-width: 0; }
  .hero-cards > *, .summary-cards > *, .farm-map-layout > *,
  [class*="-grid"] > *, [class*="_grid"] > * { min-width: 0; }
  h1 { font-size: clamp(1.4rem, 2.6vw, 2.15rem); }
  h2 { font-size: clamp(1.2rem, 2.25vw, 1.9rem); }
  h3 { font-size: clamp(1.05rem, 1.8vw, 1.5rem); }
  img, video, canvas, svg { max-width: 100%; }
  input, select, textarea { max-width: 100%; min-width: 0; }
  .modal-content, .popup-content, [class*="modal-content"], [class*="popup-content"] {
    box-sizing: border-box; max-width: min(94vw, 100%); max-height: 90dvh;
  }
  .table-wrapper, .table-container, .table-responsive, [class*="table-wrap"],
  [class*="table-container"] { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
}
@media (min-width: 701px) and (max-width: 1199px) {
  .dashboard, .farm-map-page { padding-top: 14px; padding-bottom: 16px; }
  .hero-container, .farm-summary { margin-bottom: 12px; }
  .hero-cards, .summary-cards, .top-grid, .bottom-grid { gap: clamp(9px, 1.4vw, 18px); }
}
@media (max-width: 900px) {
  :root { --sidebar-width: 0px; }
  .dashboard, .farm-map-page { margin-left: 0; width: 100%; }
}
@media (max-width: 700px) {
  .dashboard, .farm-map-page { padding: 68px 12px 16px; }
  .hero-container, .farm-summary { padding: 12px; border-radius: 12px; }
  .hero-text h2 { font-size: clamp(1.35rem, 5vw, 1.85rem); }
  .hero-text p, .farm-map-title-area p { font-size: clamp(.75rem, 3vw, .95rem); }
  .hero-cards { gap: 9px; }
  .modal-content, .popup-content, [class*="modal-content"], [class*="popup-content"] {
    max-width: calc(100vw - 20px); max-height: calc(100dvh - 20px);
  }
}
@media (max-width: 380px) {
  .dashboard, .farm-map-page { padding-left: 8px; padding-right: 8px; }
  .hero-container, .farm-summary { padding: 10px; }
}

</style>
</head><body><main class="panel"><h1>Reset password</h1><?php if ($done): ?><p class="notice">Your password has been updated. <a href="login.php">Sign in</a>.</p><?php elseif (!$record): ?><p>This reset link is invalid or expired.</p><a href="forgot_password.php">Request a new link</a><?php else: ?><?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="token" value="<?= h($token) ?>"><label for="password">New password (12–72 characters)</label><input type="password" id="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required><label for="confirm_password">Confirm new password</label><input type="password" id="confirm_password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password" required><button type="submit">Reset password</button></form><?php endif; ?><p><a href="login.php">Back to login</a></p></main></body></html>