<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: adminDashboard.php");
    exit();
}

$error = $_GET['error'] ?? '';
$remaining = isset($_GET['remaining']) ? (int) $_GET['remaining'] : 0;

$errorMessages = [
    'empty_fields' => 'Please enter your username/email and password.',
    'user_not_found' => 'Wrong username or email.',
    'wrong_password' => 'Wrong credential.',
    'account_locked' => 'This account is temporarily locked. An administrator must unlock it before you can log in again.',
    'role_not_found' => 'Your account role is not configured correctly. Please contact an administrator.',
    'system_error' => 'Unable to process your login right now. Please try again.',
];

$errorText = $errorMessages[$error] ?? '';
if ($error === 'wrong_password' && $remaining > 0) {
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --green-deep: #12361a;
            --green-text: #4d7a4a;
            --green-btn-1: #7fae68;
            --green-btn-2: #9cc468;
            --notice-bg: #f6f8d2;
            --border: #d9ddd9;
            --text: #1d2a1f;
            --muted: #6b766d;
            --danger: #c62828;
            --danger-bg: #fff4f4;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            min-height: 100vh;
            font-family: "Poppins", sans-serif;
            background: #ffffff;
            color: var(--text);
            overflow-x: hidden;
        }

        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 90px 40px;
            position: relative;
        }

        .stage {
            position: relative;
            width: min(1000px, 100%);
        }

        /* ---------- Botanical leaves ---------- */
        .leaf-defs {
            position: absolute;
            width: 0;
            height: 0;
        }

        .leaf {
            position: absolute;
            z-index: 0;
            height: auto;
            aspect-ratio: 1 / 2;
            pointer-events: none;
            filter: drop-shadow(0 6px 10px rgba(31, 63, 36, .12));
        }

        /* top-left cluster */
        .l1  { left: 8%;   top: -19%;  width: 88px; transform: rotate(8deg); }
        .l2  { left: 18%;  top: -10%;  width: 56px; transform: rotate(-22deg); }
        .l3  { left: 15%;  top: -7%;   width: 46px; transform: rotate(28deg); opacity: .45; }
        .l4  { left: 31%;  top: -11%;  width: 38px; transform: rotate(62deg); }
        .l5  { left: 45%;  top: -17%;  width: 42px; transform: rotate(-58deg); }
        /* left side */
        .l6  { left: -7%;  top: 13%;   width: 84px; transform: rotate(-72deg); }
        .l7  { left: -4%;  top: 31%;   width: 40px; transform: rotate(-28deg); }
        .l8  { left: -6%;  top: 52%;   width: 40px; transform: rotate(18deg); }
        .l9  { left: -8%;  top: 67%;   width: 46px; transform: rotate(-12deg); }
        /* right side */
        .l10 { right: -1%; top: -13%;  width: 52px; transform: rotate(58deg); }
        .l11 { right: -6%; top: 14%;   width: 52px; transform: rotate(48deg); }
        .l12 { right: -4%; top: 30%;   width: 88px; transform: rotate(22deg); }
        .l13 { right: -3%; top: 54%;   width: 96px; transform: rotate(-16deg); }
        .l14 { right: -1%; top: 73%;   width: 40px; transform: rotate(40deg); }
        /* bottom */
        .l15 { left: 42%;  bottom: -22%; width: 92px; transform: rotate(200deg); }
        .l16 { left: 50%;  bottom: -17%; width: 62px; transform: rotate(160deg); }
        .l17 { left: 58%;  bottom: -12%; width: 46px; transform: rotate(138deg); opacity: .55; }
        .l18 { left: 22%;  bottom: -14%; width: 42px; transform: rotate(38deg); }
        .l19 { left: 33%;  bottom: -19%; width: 38px; transform: rotate(18deg); }
        .l20 { right: 12%; bottom: -13%; width: 42px; transform: rotate(46deg); }
        .l21 { left: 3%;   bottom: -9%;  width: 44px; transform: rotate(14deg); }

        /* ---------- Card ---------- */
        .login-shell {
            position: relative;
            z-index: 2;
            display: flex;
            min-height: 560px;
        }

        .brand-panel {
            position: relative;
            flex: 1 1 54%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 50px 9% 50px 40px;
            border-radius: 36px 0 0 36px;
            overflow: hidden;
            background:
                radial-gradient(circle at 30% 105%, rgba(176, 202, 108, .6), transparent 55%),
                radial-gradient(circle at 78% 0%, rgba(120, 165, 105, .55), transparent 45%),
                linear-gradient(135deg, #3a6636 0%, #568451 45%, #6a9556 75%, #7ea25e 100%);
            box-shadow: 0 24px 60px rgba(31, 63, 36, .16);
        }

        .brand-content {
            position: relative;
            z-index: 2;
            width: 100%;
        }

        .brand-logo {
            width: min(340px, 92%);
            height: auto;
            display: block;
            margin: 0 auto;
            object-fit: contain;
            animation: logoBounce 2.8s ease-in-out infinite;
        }

        @keyframes logoBounce {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-6px); }
        }

        .form-panel {
            position: relative;
            flex: 0 0 50%;
            margin-left: -4%;
            display: flex;
            align-items: center;
            padding: 52px 56px;
            background: #fff;
            border-radius: 40px;
            box-shadow: 0 22px 55px rgba(31, 63, 36, .16);
        }

        .login-card {
            width: 100%;
        }

        .login-card h1 {
            font-size: 50px;
            line-height: 1.05;
            color: var(--green-deep);
            font-weight: 700;
            letter-spacing: -1.5px;
        }

        .subtitle {
            margin: 10px 0 22px;
            font-size: 16px;
            color: var(--green-text);
        }

        .error-box {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            margin-bottom: 14px;
            border: 1px solid #ffd1d1;
            border-radius: 12px;
            background: var(--danger-bg);
            color: var(--danger);
            font-size: 13px;
            line-height: 1.5;
        }

        .error-icon {
            flex: 0 0 20px;
            width: 20px;
            height: 20px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--danger);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
        }

        .notice {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px 18px;
            margin-bottom: 26px;
            border-radius: 12px;
            background: var(--notice-bg);
            color: #1b1f1b;
            font-size: 14px;
            line-height: 1.55;
        }

        .notice svg {
            flex: 0 0 26px;
            margin-top: 3px;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
        }

        .field {
            position: relative;
            margin-bottom: 16px;
        }

        .field .icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            color: var(--green-deep);
            pointer-events: none;
        }

        .field input {
            width: 100%;
            height: 60px;
            border: 1.5px solid var(--border);
            border-radius: 14px;
            outline: none;
            padding: 0 20px 0 58px;
            font: 400 15px "Poppins", sans-serif;
            color: #222;
            background: #fff;
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .field input::placeholder {
            color: #6f7a71;
        }

        .field input:focus {
            border-color: var(--green-text);
            box-shadow: 0 0 0 4px rgba(127, 174, 104, .22);
        }

        .field.has-toggle input {
            padding-right: 56px;
        }

        .toggle-pass {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border: 0;
            background: transparent;
            color: var(--green-deep);
            border-radius: 50%;
            cursor: pointer;
        }

        .toggle-pass:focus-visible {
            outline: 2px solid var(--green-text);
        }

        .toggle-pass .eye-off { display: none; }
        .toggle-pass.is-visible .eye-on { display: none; }
        .toggle-pass.is-visible .eye-off { display: block; }

        .btn-login {
            width: 100%;
            height: 60px;
            margin-top: 10px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(90deg, var(--green-btn-1), var(--green-btn-2));
            color: #fff;
            font: 600 17px "Poppins", sans-serif;
            cursor: pointer;
            transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
        }

        .btn-login:hover {
            filter: brightness(.96);
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(31, 63, 36, .2);
        }

        .btn-login:focus-visible {
            outline: 3px solid rgba(31, 63, 36, .45);
            outline-offset: 2px;
        }


        .remember {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            color: #1b1f1b;
        }

        .remember input {
            width: 18px;
            height: 18px;
            accent-color: var(--green-text);
            cursor: pointer;
        }

        .forgot {
            color: var(--green-text);
            text-decoration: none;
        }

        .forgot:hover {
            text-decoration: underline;
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 860px) {
            .page {
                padding: 70px 22px;
            }

            .login-shell {
                flex-direction: column;
                max-width: 460px;
                margin: 0 auto;
                min-height: auto;
            }

            .brand-panel {
                flex: none;
                min-height: 250px;
                padding: 40px 30px 60px;
                border-radius: 36px 36px 0 0;
            }

            .form-panel {
                flex: none;
                margin: -34px 0 0;
                padding: 42px 30px 44px;
                border-radius: 36px;
            }

            .login-card h1 {
                font-size: 46px;
            }

            .leaf { transform-origin: center; }
        }

        @media (max-width: 430px) {
            .page {
                padding: 60px 14px;
            }

            .form-panel {
                padding: 36px 22px 38px;
            }

            .l6, .l9, .l11, .l12, .l13 { width: 48px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .brand-logo {
                animation: none;
            }
        }
    </style>
</head>

<body>
    <!-- Leaf shapes (decorative) -->
    <svg class="leaf-defs" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="gLeafDark" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#8fbf3f"/>
                <stop offset=".55" stop-color="#4f9238"/>
                <stop offset="1" stop-color="#2f6a2c"/>
            </linearGradient>
            <linearGradient id="gLeafLight" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#d6e36a"/>
                <stop offset="1" stop-color="#8fbf4a"/>
            </linearGradient>

            <symbol id="leafDark" viewBox="0 0 100 200">
                <path d="M50 0C92 52 104 124 50 200C-4 124 8 52 50 0Z" fill="url(#gLeafDark)"/>
                <path d="M50 10L50 192" stroke="rgba(255,255,255,.4)" stroke-width="2" fill="none"/>
                <path d="M50 60L26 38M50 90L20 64M50 122L24 96M50 60L74 38M50 90L80 64M50 122L76 96" stroke="rgba(255,255,255,.22)" stroke-width="1.5" fill="none"/>
            </symbol>

            <symbol id="leafLight" viewBox="0 0 100 200">
                <path d="M50 0C92 52 104 124 50 200C-4 124 8 52 50 0Z" fill="url(#gLeafLight)"/>
                <path d="M50 10L50 192" stroke="rgba(255,255,255,.5)" stroke-width="2" fill="none"/>
                <path d="M50 70L28 48M50 104L22 78M50 70L72 48M50 104L78 78" stroke="rgba(255,255,255,.3)" stroke-width="1.5" fill="none"/>
            </symbol>
        </defs>
    </svg>

    <main class="page">
        <div class="stage">

            <div aria-hidden="true">
                <svg class="leaf l1"  viewBox="0 0 100 200"><use href="#leafDark"/></svg>
                <svg class="leaf l2"  viewBox="0 0 100 200"><use href="#leafDark"/></svg>
                <svg class="leaf l3"  viewBox="0 0 100 200"><use href="#leafLight"/></svg>
                <svg class="leaf l4"  viewBox="0 0 100 200"><use href="#leafDark"/></svg>
                <svg class="leaf l5"  viewBox="0 0 100 200"><use href="#leafLight"/></svg>
                <svg class="leaf l6"  viewBox="0 0 100 200"><use href="#leafDark"/></svg>
                <svg class="leaf l7"  viewBox="0 0 100 200"><use href="#leafLight"/></svg>
                <svg class="leaf l8"  viewBox="0 0 100 200"><use href="#leafDark"/></svg>
                <svg class="leaf l9"  viewBox="0 0 100 200"><use href="#leafLight"/></svg>
                <svg class="leaf l10" viewBox="0 0 100 200"><use href="#leafDark"/></svg>
                <svg class="leaf l11" viewBox="0 0 100 200"><use href="#leafLight"/></svg>
                <svg class="leaf l12" viewBox="0 0 100 200"><use href="#leafDark"/></svg>
                <svg class="leaf l13" viewBox="0 0 100 200"><use href="#leafDark"/></svg>
                <svg class="leaf l14" viewBox="0 0 100 200"><use href="#leafLight"/></svg>
                <svg class="leaf l15" viewBox="0 0 100 200"><use href="#leafDark"/></svg>
                <svg class="leaf l16" viewBox="0 0 100 200"><use href="#leafDark"/></svg>
                <svg class="leaf l17" viewBox="0 0 100 200"><use href="#leafLight"/></svg>
                <svg class="leaf l18" viewBox="0 0 100 200"><use href="#leafLight"/></svg>
                <svg class="leaf l19" viewBox="0 0 100 200"><use href="#leafDark"/></svg>
                <svg class="leaf l20" viewBox="0 0 100 200"><use href="#leafLight"/></svg>
                <svg class="leaf l21" viewBox="0 0 100 200"><use href="#leafDark"/></svg>
            </div>

            <div class="login-shell">

                <section class="brand-panel">
                    <div class="brand-content">
                        <img src="logo2.png" alt="Sanctuario" class="brand-logo">
                    </div>
                </section>

                <section class="form-panel">
                    <div class="login-card">
                        <h1>Log in</h1>
                        <p class="subtitle">Access your Sanctuario account</p>

                        <?php if ($errorText !== ''): ?>
                            <div class="error-box" role="alert">
                                <span class="error-icon">!</span>
                                <span><?= htmlspecialchars($errorText, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="notice">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#12361a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/>
                                <path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>
                                <circle cx="12" cy="15.3" r="1" fill="#12361a"/>
                            </svg>
                            <span>After 3 wrong attempts, your account will be temporarily locked. Please contact an administrator to unlock it.</span>
                        </div>

                        <form action="login_process.php" method="POST" autocomplete="on">
                            <div class="field">
                                <label class="sr-only" for="username">Username or Email</label>
                                <span class="icon" aria-hidden="true">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="8" r="4"/>
                                        <path d="M4.5 20.5c.8-3.8 3.9-6 7.5-6s6.7 2.2 7.5 6"/>
                                    </svg>
                                </span>
                                <input
                                    id="username"
                                    type="text"
                                    name="username"
                                    placeholder="Username or Email"
                                    value="<?= htmlspecialchars($_GET['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                    autocomplete="username"
                                    autofocus
                                >
                            </div>

                            <div class="field has-toggle">
                                <label class="sr-only" for="password">Password</label>
                                <span class="icon" aria-hidden="true">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/>
                                        <path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>
                                        <circle cx="12" cy="15.3" r="1" fill="currentColor"/>
                                    </svg>
                                </span>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    placeholder="Password"
                                    required
                                    autocomplete="current-password"
                                >
                                <button type="button" class="toggle-pass" id="togglePass" aria-label="Show password" aria-pressed="false">
                                    <svg class="eye-on" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg class="eye-off" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                        <path d="M3 3l18 18"/>
                                    </svg>
                                </button>
                            </div>

                            <button type="submit" class="btn-login">Log In</button>

                        </form>
                    </div>
                </section>

            </div>
        </div>
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