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
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#eef7e4;color:#2f4f35;font:16px Arial,sans-serif}.panel{box-sizing:border-box;max-width:540px;margin:20px;background:#fffdf4;padding:35px;border-radius:16px;box-shadow:0 8px 28px #102d1c18}h1{font-size:24px}p{line-height:1.6}a{display:inline-block;margin:6px 12px 0 0;color:#2e5129;font-weight:bold}code{background:#eef7e4;padding:3px 6px;border-radius:5px}</style><style id="sanctuario-responsive-refinement">
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