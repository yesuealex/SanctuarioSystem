<?php
require_once __DIR__ . '/security.php';
secure_session_start();

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    $dest = in_array($role, ['admin', 'super admin'], true) ? 'adminDashboard.php' : (in_array($role, ['crops staff','livestock staff'], true) ? 'staff/staffDashboard.php' : ($role === 'staff' ? 'staff_role_pending.php' : 'maintenanceDashboard.php'));
    header('Location: ' . $dest);
    exit();
}

$error = $_GET['error'] ?? '';
$remaining = isset($_GET['remaining']) ? (int) $_GET['remaining'] : 0;

$errorMessages = [
    'empty_fields' => 'Please enter your username/email and password.',
    'invalid_credentials' => 'Invalid username/email or password. Please try again.',
    'invalid_request' => 'Your session expired. Refresh and try again.',
    'user_not_found' => 'Invalid username/email or password. Please try again.',
    'wrong_password' => 'Invalid username/email or password. Please try again.',
    'account_locked' => 'Invalid username/email or password. Please try again.',
    'role_not_found' => 'Your account role is not configured correctly. Please contact an administrator.',
    'system_error' => 'Unable to process your login right now. Please try again.',
];

$errorText = $errorMessages[$error] ?? '';
$successText = isset($_GET['password_changed']) ? 'Password updated. Please sign in again.' : '';
if (false && $error === 'wrong_password' && $remaining > 0) {
    $errorText .= " You have {$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining before the account is locked.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in | Sanctuario Nature Farm</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --dark: #2e5129;
            --green: #2f4f35;
            --yellow: #edd966;
            --cream: #fffdf4;
            --soft: #eef7e4;
            --text: #2f4f35;
            --muted: #68786d;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; display: block; }
        button, input { font-family: "Poppins", sans-serif; max-width: 100%; }

        body {
            font-family: "Poppins", sans-serif;
            color: var(--text);
            line-height: 1.65;
            overflow-wrap: break-word;
        }

        /* ---------- Login design ---------- */
        body.login-page { min-height: 100vh; display: grid; place-items: center; padding: 24px; background: linear-gradient(135deg, #2e5129, #6b8e5a); }
        .login-panel { width: min(100%, 440px); background: #fffdf4; padding: clamp(24px, 5vw, 40px); border-radius: 22px; box-shadow: 0 18px 50px #102d1c55; }
        .login-brand { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
        .login-brand img { width: 76px; height: 76px; object-fit: contain; border-radius: 12px; }
        .login-brand strong { display: block; color: #2e5129; letter-spacing: .08em; }
        .login-brand span { color: #68786d; font-size: .78rem; }
        .login-panel h1 { font-size: clamp(1.6rem, 5vw, 2rem); color: #2e5129; margin-bottom: 8px; }
        .login-panel > p { color: #68786d; font-size: .92rem; margin-bottom: 22px; }
        .login-panel .form-group { margin-bottom: 16px; }
        .login-panel .btn { width: 100%; margin-top: 4px; }
        .back-link { display: inline-block; margin-top: 18px; color: #2f4f35; font-weight: 700; font-size: .9rem; }
        .back-link:hover { text-decoration: underline; }

        /* ---------- Form controls (same look as the website) ---------- */
        .form-group { margin-bottom: 18px; }

        .form-group label {
            display: block;
            font-weight: 800;
            font-size: .85rem;
            margin-bottom: 5px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cdddc9;
            border-radius: 11px;
            font: inherit;
            background: #fbfdf9;
        }

        .form-group input:focus {
            outline: 2px solid #6b8e5a;
            outline-offset: 2px;
            border-color: var(--green);
        }

        .btn {
            display: inline-block;
            border: 2px solid transparent;
            border-radius: 999px;
            padding: 11px 21px;
            background: var(--yellow);
            color: var(--dark);
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            transition: .2s;
        }

        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 18px #edd96644; }

        .btn:focus-visible, a:focus-visible, button:focus-visible {
            outline: 3px solid var(--yellow);
            outline-offset: 3px;
        }

        /* ---------- Show / hide password ---------- */
        .pass-wrap { position: relative; }
        .pass-wrap input { padding-right: 46px; }

        .toggle-pass {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
        }

        .toggle-pass:hover { color: var(--dark); }
        .toggle-pass .eye-off { display: none; }
        .toggle-pass.is-visible .eye-on { display: none; }
        .toggle-pass.is-visible .eye-off { display: block; }

        /* ---------- Error message ---------- */
        .error-box {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            margin-bottom: 16px;
            border: 1px solid #ffd1d1;
            border-radius: 10px;
            background: #fff1f1;
            color: #b42323;
            font-size: .8rem;
            line-height: 1.5;
        }

        .error-icon {
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #b42323;
            color: #fff;
            font-size: .75rem;
            font-weight: 800;
        }
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
<body class="login-page">
    <main class="login-panel">
        <a class="login-brand" href="SanctuarioWebsite/index.php">
            <img src="SanctuarioWebsite/SanctuarioWebsite/images/logofarm.jpg" alt="Sanctuario Nature Farms logo">
            <div><strong>SANCTUARIO</strong><span>NATURE FARMS SYSTEM</span></div>
        </a>

        <h1>Welcome back</h1>
        <p>Sign in to access the Sanctuario farm monitoring system.</p>

        <?php if ($successText !== ''): ?><p role="status" style="color:#2e5129;font-weight:700"><?= htmlspecialchars($successText, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <?php if ($errorText !== ''): ?>
            <div class="error-box" role="alert">
                <span class="error-icon">!</span>
                <span><?= htmlspecialchars($errorText, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <form action="login_process.php" method="POST" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label for="username">Username or email</label>
                <input
                    id="username"
                    type="text"
                    name="username"
                    placeholder="Enter your username or email"
                    value="<?= htmlspecialchars($_GET['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                    autocomplete="username"
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="pass-wrap">
                    <input
                        id="password"
                        type="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="toggle-pass" id="togglePass" aria-label="Show password" aria-pressed="false">
                        <svg class="eye-on" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg class="eye-off" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z"/>
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M3 3l18 18"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button class="btn" type="submit">Sign In</button>
        </form>

        <p style="margin-top:14px"><a href="forgot_password.php" style="color:#2e5129;font-weight:700">Forgot password?</a></p>
        <a class="back-link" href="SanctuarioWebsite/SanctuarioWebsite/index.php">&larr; Back to Home</a>
    </main>

    <script>
        (function () {
            var btn = document.getElementById('togglePass');
            var input = document.getElementById('password');
            if (!btn || !input) return;
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.classList.toggle('is-visible', show);
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        })();
    </script>
</body>
</html>