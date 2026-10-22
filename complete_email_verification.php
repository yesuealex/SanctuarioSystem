<?php
require_once __DIR__ . '/security.php';
secure_session_start();
require_once __DIR__ . '/db.php';

if (empty($_SESSION['pending_verification_user_id']) || time() - (int)($_SESSION['pending_verification_at'] ?? 0) > 1800) {
    header('Location: login.php'); exit;
}
$userId = (int)$_SESSION['pending_verification_user_id'];
$stmt = $conn->prepare('SELECT email, email_verified_at FROM users WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $userId); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$user || $user['email_verified_at'] !== null) { header('Location: login.php'); exit; }

function escape_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
$notice = 'Enter the 6-digit code sent to your email address.';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $notice = 'Invalid request. Please refresh the page.';
    } elseif (($_POST['action'] ?? '') === 'send') {
        $email = strtolower(trim((string)($user['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $notice = 'Your account has no valid registered email. Contact the administrator.';
        } else {
            try {
                $stmt = $conn->prepare('SELECT id FROM email_verification_tokens WHERE user_id = ? AND created_at > NOW() - INTERVAL 2 MINUTE ORDER BY id DESC LIMIT 1');
                $stmt->bind_param('i', $userId); $stmt->execute();
                $recent = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if ($recent) {
                    $notice = 'A verification code was recently requested. Please wait two minutes.';
                } else {
                    $code = (string)random_int(100000, 999999);
                    // Bind the code to this account, not to an easily brute-forced global hash.
                    $codeHash = hash_hmac('sha256', (string)$userId . ':' . $code, session_id());
                    $stmt = $conn->prepare('INSERT INTO email_verification_tokens (user_id, pending_email, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
                    $stmt->bind_param('iss', $userId, $email, $codeHash); $stmt->execute();
                    $tokenId = $conn->insert_id; $stmt->close();
                    $safeCode = escape_html($code);
                    $html = '<!doctype html><html><body style="margin:0;padding:30px 12px;background:#f1f5ee;font-family:Arial,sans-serif;color:#263b2a">'
                        . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;margin:auto;background:#fff;border-radius:18px;overflow:hidden">'
                        . '<tr><td style="background:#285536;color:#fff;padding:30px;text-align:center"><div style="font-size:27px;font-weight:bold;letter-spacing:2px">SANCTUARIO</div><div style="font-size:12px;letter-spacing:3px;margin-top:6px">NATURE FARMS</div></td></tr>'
                        . '<tr><td style="padding:32px"><h1 style="font-size:25px;color:#285536;margin:0 0 12px">Verify your email</h1><p style="line-height:1.6">Use this one-time verification code to finish signing in to your Sanctuario Farm Management System account.</p>'
                        . '<div style="background:#edf5e8;border:1px solid #cfe0c7;border-radius:12px;text-align:center;padding:22px 8px;margin:24px 0;font-size:36px;font-weight:bold;letter-spacing:9px;color:#285536">' . $safeCode . '</div>'
                        . '<p style="font-size:14px;line-height:1.7">This code expires in <b>10 minutes</b> and can be used only once. Never share it with anyone.</p><p style="font-size:13px;color:#637166">If you did not request this, you can safely ignore this email.</p></td></tr>'
                        . '<tr><td style="background:#f5f7f2;text-align:center;padding:18px;font-size:12px;color:#58705c">Growing today for a greener tomorrow<br>Sanctuario Farm Management System</td></tr></table></body></html>';
                    $plain = "Your Sanctuario email verification code is: $code\n\nValid for 10 minutes. Do not share this code.\nIf you did not request this, ignore this email.";
                    if (send_security_email($email, 'Your Sanctuario verification code', $plain, $html)) {
                        $_SESSION['verification_code_attempts'] = 0;
                        $_SESSION['verification_code_token_id'] = $tokenId;
                        $notice = 'Gmail SMTP accepted the verification email for ' . $email . '. Delivery to your inbox is not guaranteed. Check the exact recipient address, Spam, All Mail, and Gmail search for subject: Your Sanctuario verification code.';
                        $success = true;
                    } else {
                        $stmt = $conn->prepare('DELETE FROM email_verification_tokens WHERE id = ? AND user_id = ?');
                        $stmt->bind_param('ii', $tokenId, $userId); $stmt->execute(); $stmt->close();
                        $notice = 'The email server did not accept this message. Check the PHP error log for Sanctuario SMTP Error and try again later.';
                    }
                }
            } catch (Throwable $e) {
                error_log('Sanctuario verification send error: ' . $e->getMessage());
                $notice = 'Unable to send the verification code right now.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'verify') {
        $code = trim((string)($_POST['code'] ?? ''));
        $attempts = (int)($_SESSION['verification_code_attempts'] ?? 0);
        if ($attempts >= 5) {
            $notice = 'Too many incorrect attempts. Request a new code after the resend cooldown.';
        } elseif (!preg_match('/^[0-9]{6}$/D', $code)) {
            $notice = 'Enter the complete 6-digit code.';
        } else {
            $_SESSION['verification_code_attempts'] = $attempts + 1;
            $tokenId = (int)($_SESSION['verification_code_token_id'] ?? 0);
            $codeHash = hash_hmac('sha256', (string)$userId . ':' . $code, session_id());
            try {
                $conn->begin_transaction();
                $stmt = $conn->prepare('SELECT id, pending_email FROM email_verification_tokens WHERE id = ? AND user_id = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE');
                $stmt->bind_param('iis', $tokenId, $userId, $codeHash); $stmt->execute();
                $record = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if ($record) {
                    $stmt = $conn->prepare('UPDATE users SET email = ?, email_verified_at = NOW() WHERE user_id = ?');
                    $stmt->bind_param('si', $record['pending_email'], $userId); $stmt->execute(); $stmt->close();
                    $stmt = $conn->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
                    $stmt->bind_param('i', $userId); $stmt->execute(); $stmt->close();
                    $conn->commit();
                    unset($_SESSION['pending_verification_user_id'], $_SESSION['pending_verification_at'], $_SESSION['verification_code_attempts'], $_SESSION['verification_code_token_id']);
                    $notice = 'Email verified successfully! You can now log in.';
                    $success = true;
                } else {
                    $conn->rollback();
                    $notice = 'Incorrect or expired code. Please check and try again.';
                }
            } catch (Throwable $e) {
                $conn->rollback(); error_log('Sanctuario verification error: ' . $e->getMessage());
                $notice = 'Unable to verify the code right now.';
            }
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verify email | Sanctuario</title>
<style>body{font:16px Arial,sans-serif;background:linear-gradient(135deg,#2e5129,#6b8e5a);min-height:100vh;display:grid;place-items:center;margin:0;padding:20px;box-sizing:border-box}.panel{background:#fffdf4;border-radius:20px;padding:32px;width:min(100%,420px);box-sizing:border-box;box-shadow:0 15px 40px #102d1c55}h1,a{color:#2e5129}h1{margin-top:0}.notice{padding:12px;background:#e5f2df;border-radius:9px;line-height:1.5}label{display:block;margin:18px 0 6px}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #bccfb8;border-radius:9px}input.code{text-align:center;letter-spacing:6px;font-size:24px}button,.button{display:inline-block;margin-top:16px;border:0;background:#edd966;padding:12px 20px;border-radius:24px;font-weight:bold;cursor:pointer;color:#263b2a;text-decoration:none}p.small{font-size:13px;color:#4e674d}</style></head>
<body><main class="panel"><h1>Verify your email</h1><p class="notice" role="status"><?= escape_html($notice) ?></p>
<?php if ($success && empty($_SESSION['pending_verification_user_id'])): ?><a class="button" href="login.php">Return to login</a>
<?php else: ?><p class="small">Code will be sent to: <?= escape_html((string)$user['email']) ?></p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="action" value="verify"><label for="code">6-digit verification code</label><input class="code" id="code" type="text" name="code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required><button type="submit">Verify code</button></form>
<form method="post"><input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="action" value="send"><button type="submit">Send / Resend code</button></form><p><a href="login.php">Return to login</a></p><?php endif; ?></main></body></html>
