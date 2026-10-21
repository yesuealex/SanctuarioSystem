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
            $stmt = $conn->prepare('UPDATE users SET email = ?, email_verified_at = NOW() WHERE user_id = ?');
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
<style id="sanctuario-responsive-refinement">
/* Sanctuario responsive refinement â€” layout geometry only, no feature changes. */
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
</head>
<body>
    <main class="panel">
        <h1>Email verification</h1>
        <p class="notice" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p>
        <a class="login-button" href="login.php">Return to login</a>
    </main>
</body>
</html>