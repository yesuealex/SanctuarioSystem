<?php
require_once __DIR__ . '/security.php';
secure_session_start();
$loggedIn = !empty($_SESSION['user_id']);
$role = $loggedIn ? trim((string)($_SESSION['role'] ?? '')) : '';
$assigned = in_array($role, ['crops staff', 'livestock staff'], true);
function pending_escape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Staff Access | Sanctuario</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#eef7e4;color:#2f4f35;font:16px Arial,sans-serif}.panel{box-sizing:border-box;max-width:540px;margin:20px;background:#fffdf4;padding:35px;border-radius:16px;box-shadow:0 8px 28px #102d1c18}h1{font-size:24px}p{line-height:1.6}a{display:inline-block;margin:6px 12px 0 0;color:#2e5129;font-weight:bold}code{background:#eef7e4;padding:3px 6px;border-radius:5px}</style></head>
<body><main class="panel">
<?php if (!$loggedIn): ?>
<h1>Login session not detected</h1><p>Your login session is missing or expired. This page will not redirect automatically, so it cannot create a redirect loop.</p><a href="login.php">Go to Login</a>
<?php elseif ($assigned): ?>
<h1>Staff account ready</h1><p>Your assigned role is <strong><?= pending_escape(ucwords($role)) ?></strong>.</p><a href="staff/staffDashboard.php">Open Staff Dashboard</a><a href="logout.php">Log out</a>
<?php elseif ($role === 'staff'): ?>
<h1>Staff Assignment Required</h1><p>Your account still has the general <code>staff</code> role. An administrator must assign it to <strong>crops staff</strong> or <strong>livestock staff</strong> in the database before you can open the staff dashboard.</p><a href="logout.php">Log out</a>
<?php else: ?>
<h1>Account role needs checking</h1><p>Your current role is <code><?= pending_escape($role !== '' ? $role : '(empty)') ?></code>. This page is for unassigned staff accounts.</p><a href="logout.php">Log out</a>
<?php endif; ?>
</main></body></html>
