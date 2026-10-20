<?php
require_once __DIR__ . '/security.php';
secure_session_start();
require_once __DIR__ . '/db.php';

$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(400);
        $notice = 'Invalid request. Please refresh and try again.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $notice = 'If this email is registered, a password reset link will be sent.';

        if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 150) {
            try {
                // Match a registered account, ignoring accidental whitespace and case.
                $stmt = $conn->prepare(
                    'SELECT user_id, email FROM users WHERE LOWER(TRIM(email)) = ? LIMIT 1'
                );
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$user) {
                    error_log('Sanctuario reset: No matching account email.');
                } else {
                    $userId = (int) $user['user_id'];
                    // Compare timestamps in MySQL to avoid PHP/MySQL timezone mismatches.
                    $stmt = $conn->prepare(
                        'SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds ' .
                        'FROM password_reset_tokens WHERE user_id = ? ORDER BY id DESC LIMIT 1'
                    );
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $latest = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $cooldownSeconds = 10; // Local testing only; use 120+ for production.
                    if ($latest && (int) $latest['age_seconds'] < $cooldownSeconds) {
                        error_log('Sanctuario reset: Request skipped due to cooldown.');
                    } else {
                        $token = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $token);

                        $stmt = $conn->prepare(
                            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) ' .
                            'VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
                        );
                        $stmt->bind_param('is', $userId, $tokenHash);
                        $stmt->execute();
                        $stmt->close();
                        error_log('Sanctuario reset: Token inserted; attempting email.');

                        $link = app_url('reset_password.php') . '?token=' . urlencode($token);
                        $body = "A password reset was requested for your Sanctuario account.\n\n" .
                            "Open this link within 30 minutes:\n" . $link . "\n\n" .
                            "If you did not request this, ignore this email.";

                        // Send to the registered email from MySQL, not a hardcoded address.
                        if (send_security_email((string) $user['email'], 'Sanctuario password reset', $body)) {
                            error_log('Sanctuario reset: SMTP accepted the reset email.');
                        } else {
                            error_log('Sanctuario reset: Email sending failed; see SMTP error above.');
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('Sanctuario reset: Database/process error: ' . $e->getMessage());
            }
        }
    }
}

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot password | Sanctuario</title>
    <style>
        body{font:16px Arial,sans-serif;background:linear-gradient(135deg,#2e5129,#6b8e5a);min-height:100vh;display:grid;place-items:center;margin:0}
        .panel{background:#fffdf4;border-radius:20px;padding:32px;width:min(90%,410px);box-shadow:0 15px 40px #102d1c55}
        h1{color:#2e5129}
        label{display:block;margin:18px 0 6px}
        input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #bccfb8;border-radius:9px}
        button{margin-top:18px;border:0;background:#edd966;padding:12px 20px;border-radius:24px;font-weight:bold;cursor:pointer}
        .notice{padding:12px;background:#e5f2df;border-radius:9px}
        a{color:#2e5129}
    </style>
<style id="sanctuario-responsive-refinement">
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
</head>
<body>
<main class="panel">
    <h1>Forgot password?</h1>
    <p>Enter the verified email address linked to your account.</p>
    <?php if ($notice): ?><p class="notice" role="status"><?= h($notice) ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label for="email">Email address</label>
        <input type="email" name="email" id="email" required maxlength="150" autocomplete="email">
        <button type="submit">Send reset link</button>
    </form>
    <p><a href="login.php">Back to login</a></p>
</main>
</body>
</html>