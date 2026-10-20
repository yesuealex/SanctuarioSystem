<?php
require_once dirname(__DIR__) . '/staff_access.php';
if (empty($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$role = sanctuario_staff_role();
if (!in_array($role, ['crops staff','livestock staff'], true)) {
    header('Location: ' . (sanctuario_staff_is_admin() ? '../adminDashboard.php' : ($role === 'staff' ? '../staff_role_pending.php' : '../login.php'))); exit;
}
require_once dirname(__DIR__) . '/db.php';
$section = $role === 'crops staff' ? 'crops' : 'livestock';
$today = date('Y-m-d');
$userId = (int)$_SESSION['user_id'];
$taskRows = [];
$taskError = null;
try {
    // Seed the original checklist once per account; keep existing task checkmarks for today.
    $conn->begin_transaction();
    $init = $conn->prepare('INSERT IGNORE INTO staff_task_initializations (user_id,section) VALUES (?,?)');
    $init->bind_param('is', $userId, $section); $init->execute();
    $firstVisit = $init->affected_rows === 1; $init->close();
    if ($firstVisit) {
        $defaults = $section === 'crops'
            ? ['Inspect crop health and growing conditions','Check irrigation and soil moisture','Update crop growth records','Check for pests and plant diseases','Review harvesting or transplanting needs']
            : ['Check animal health and behavior','Verify feeding and water supplies','Clean and inspect animal housing','Update livestock records','Report any health or safety concerns'];
        $legacyDone = [];
        try {
            $old = $conn->prepare('SELECT task_index FROM staff_task_checks WHERE user_id=? AND section=? AND task_date=? AND completed=1');
            $old->bind_param('iss', $userId, $section, $today); $old->execute();
            foreach ($old->get_result() as $r) $legacyDone[(int)$r['task_index']] = true;
            $old->close();
        } catch (Throwable $ignored) {}
        $insert = $conn->prepare('INSERT INTO staff_custom_tasks (user_id,section,title,start_date,due_date,completed,completed_at) VALUES (?,?,?,?,?,?,?)');
        foreach ($defaults as $i => $title) {
            $completed = isset($legacyDone[$i]) ? 1 : 0;
            $completedAt = $completed ? date('Y-m-d H:i:s') : null;
            $insert->bind_param('issssis', $userId, $section, $title, $today, $today, $completed, $completedAt);
            $insert->execute();
        }
        $insert->close();
    }
    $conn->commit();
    $stmt = $conn->prepare("SELECT id, title, start_date, due_date, completed, completed_at FROM staff_custom_tasks WHERE user_id = ? AND section = ? ORDER BY completed ASC, due_date ASC, id ASC");
    $stmt->bind_param('is', $userId, $section);
    $stmt->execute(); $taskRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
} catch (Throwable $e) { try { $conn->rollback(); } catch (Throwable $ignored) {} $taskError = 'Please import staff_tasks_update.sql to enable editable tasks.'; }
$completedToday = 0;
foreach ($taskRows as $t) if ((int)$t['completed'] === 1) $completedToday++;
$summary = [];
$queries = $section === 'crops' ? [
 'Active Crops' => "SELECT COUNT(*) FROM crops WHERE COALESCE(archived,0)=0 AND COALESCE(transferred_out,0)=0",
 'Farm Plots' => "SELECT COUNT(*) FROM plots WHERE COALESCE(archived,0)=0"
] : [
 'Livestock Total' => "SELECT COALESCE(SUM(number),0) FROM livestocks WHERE COALESCE(archived,0)=0",
 'Livestock Groups' => "SELECT COUNT(*) FROM livestocks WHERE COALESCE(archived,0)=0"
];
foreach ($queries as $label => $sql) {
    try { $res = $conn->query($sql); $summary[$label] = $res ? (int)($res->fetch_row()[0] ?? 0) : '—'; }
    catch (Throwable $e) { $summary[$label] = '—'; }
}
$summary['Tasks Completed'] = $completedToday;
function staff_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=staff_h(ucwords($role))?> Dashboard | Sanctuario</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="../sidebar.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:wght@400" rel="stylesheet">
<style>

/* ===========================
   FARM MAP FIX
   =========================== */

.map-box {
    min-width: 0;
    overflow: hidden;
}

.svg-wrapper {
    position: relative;
    width: 100%;
    height: auto;
    min-height: 0;
    overflow: hidden;
    cursor: grab;
    user-select: none;
    -webkit-user-select: none;
    touch-action: none;
}

.svg-wrapper:active {
    cursor: grabbing;
}

#farmMap {
    display: block;
    width: 100%;
    height: auto;
    max-width: none;
    margin: 0;
    padding: 0;
    transform-origin: top left;
    will-change: transform;
}

#farmMap > image:first-child {
    display: block;
}

#farmMap .marker {
    cursor: pointer;
    pointer-events: auto;
}

 .map-popup {
    position: fixed;
    z-index: 99999;
    width: min(360px, calc(100vw - 24px));
    max-width: 360px;
    max-height: min(520px, calc(100vh - 24px));
    overflow-y: auto;
    overscroll-behavior: contain;
    touch-action: pan-y;
    padding: 0;
    border: 1px solid rgba(82, 123, 66, .16);
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 18px 50px rgba(32, 58, 42, .28);
    color: #334337;
    font-family: 'Poppins', sans-serif;
    box-sizing: border-box;
}

.map-popup::-webkit-scrollbar { width: 6px; }
.map-popup::-webkit-scrollbar-thumb {
    background: #b8c9b0;
    border-radius: 10px;
}
.map-popup::-webkit-scrollbar-track { background: transparent; }
.map-popup.hidden { display: none; }
.map-popup-head {
    padding: 16px 18px 15px;
    background: linear-gradient(135deg, #527b42, #6b8e5a);
    color: #fff;
}
.map-popup-head .popup-kicker {
    display: block;
    margin-bottom: 3px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    opacity: .78;
}
.map-popup-head h4 {
    margin: 0;
    font-size: 18px;
    line-height: 1.3;
}
.map-popup-body { padding: 14px; }
.map-popup-section-title {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 0 2px 9px;
    color: #365a3c;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: .7px;
    text-transform: uppercase;
}
.map-popup-section-title i { font-size: 15px; }
.map-popup-card {
    padding: 12px 13px;
    margin-bottom: 9px;
    border: 1px solid #e1ebdd;
    border-radius: 11px;
    background: #f9fbf8;
}
.map-popup-card:last-child { margin-bottom: 0; }
.map-popup-card-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 7px;
    color: #2f4f35;
    font-size: 13px;
    font-weight: 700;
}
.map-popup-card-title i {
    color: #527b42;
    font-size: 12px;
}
.map-popup-detail {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 7px 0;
    border-bottom: 1px solid #edf2ea;
    font-size: 15px;
    line-height: 1.35;
}
.map-popup-detail:last-child { border-bottom: 0; padding-bottom: 0; }
.map-popup-detail span:first-child { color: #718075; }
.map-popup-detail strong { color: #3f4d42; text-align: right; }
.map-popup-empty {
    padding: 14px;
    border: 1px dashed #cbd9c5;
    border-radius: 10px;
    color: #7a867b;
    background: #fbfcfa;
    text-align: center;
    font-size: 15px;
}
.map-popup-error {
    padding: 14px;
    color: #8a4d4d;
    background: #fff8f8;
    font-size: 11px;
}


/* Staff-specific adjustments only. The admin hero, dashboard, and map styling come from ../style.css. */
.staff-check-panel{background:#fff;border-radius:18px;padding:20px;box-shadow:0 8px 18px rgba(0,0,0,.08);min-width:0}
.staff-check-panel h3{margin:0 0 12px}.staff-check-subtitle{font-size:13px;color:#667666;margin-bottom:14px}
.staff-progress-track{height:10px;background:#e6eee0;border-radius:20px;overflow:hidden;margin:12px 0 16px}
.staff-progress-fill{height:100%;background:#4F7D3A;transition:width .2s}
.staff-task{display:flex;align-items:center;gap:11px;padding:12px 3px;border-bottom:1px solid #e9eee6;cursor:pointer;font-size:13px}
.staff-task:last-of-type{border-bottom:0}
.staff-task input{width:18px;height:18px;accent-color:#4F7D3A;flex-shrink:0}
.staff-task input:checked+.staff-task-content{text-decoration:line-through;color:#879187}
.staff-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:17px}
.staff-action{display:inline-flex;align-items:center;gap:6px;padding:10px 14px;border-radius:10px;background:#4F7D3A;color:white;text-decoration:none;font-size:13px;font-weight:600}
.staff-action:hover{background:#3d642d}
.staff-alert{background:#fff2db;border-radius:9px;padding:11px;margin:12px 0;font-size:13px}
.staff-completion{font-size:13px;color:#4F7D3A;font-weight:600}
/* Match admin top-grid layout; checklist replaces the admin chart column. */
.hero-cards.staff-hero-cards{grid-template-columns:repeat(2,minmax(0,1fr));}
.dashboard-charts.staff-dashboard-tasks{display:block;min-width:0;}
.dashboard-charts.staff-dashboard-tasks .staff-check-panel{height:100%;box-sizing:border-box;}
.staff-map-link{margin-top:14px;}
@media(max-width:650px){.hero-cards.staff-hero-cards{grid-template-columns:1fr;}}


/* Staff-only task management controls; admin styling remains untouched. */
.staff-task-heading{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.staff-task-btn{border:0;border-radius:9px;background:#4F7D3A;color:white;padding:9px 13px;font:inherit;font-size:12px;font-weight:600;cursor:pointer}
.staff-task-btn.secondary{background:#e7eee1;color:#36552a}
.staff-task-content{display:flex;flex-direction:column;gap:3px;min-width:0}
.staff-task-content small{font-size:11px;color:#687768}
.staff-task-dialog{border:0;border-radius:16px;padding:22px;box-shadow:0 15px 55px #0003;width:min(430px,calc(100% - 36px));max-height:85vh;overflow:auto;font-family:inherit}
.staff-task-dialog::backdrop{background:#0007}
.staff-task-dialog form{display:grid;gap:13px}
.staff-task-dialog form label{display:grid;gap:5px;font-size:13px;font-weight:600}
.staff-task-dialog input{box-sizing:border-box;width:100%;padding:10px;border:1px solid #d2decc;border-radius:8px;font:inherit}
.staff-task-dialog-actions{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin-top:14px}
.staff-manage-row{display:flex;align-items:center;gap:7px;padding:9px 0;border-bottom:1px solid #edf1ea;flex-wrap:wrap}
/* Task actions sit at the right of each checklist row. */
.staff-task-row{display:flex;align-items:center;gap:10px;border-bottom:1px solid #e9eee6;min-width:0}
.staff-task-row .staff-task{flex:1;min-width:0;border-bottom:0}
.staff-task-row .staff-task-content{overflow-wrap:anywhere}
.staff-task-row .staff-task-actions{display:flex;align-items:center;gap:5px;flex-shrink:0;margin-left:auto}
.staff-task-icon{display:inline-flex;align-items:center;justify-content:center;width:33px;height:33px;padding:0;border:0;border-radius:8px;background:#e7eee1;color:#36552a;cursor:pointer}
.staff-task-icon:hover{background:#d8e6cf}
.staff-task-icon.danger{background:#fff0ed;color:#a63f37}
.staff-task-icon.danger:hover{background:#fbdcd7}
.staff-task-icon svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.staff-task-dialog{position:fixed;inset:0;margin:auto;max-width:calc(100vw - 36px);max-height:calc(100dvh - 36px)}
.staff-task-dialog:not([open]){display:none}
@media(max-width:450px){.staff-task-row{gap:4px}.staff-task-icon{width:30px;height:30px}.staff-task-row .staff-task{gap:7px}}
.staff-manage-row span{flex:1;min-width:150px;font-size:12px}
</style><style id="sanctuario-responsive-refinement">
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
<body><div class="layout">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>
<main class="dashboard">
<?php include dirname(__DIR__) . '/topRightBar.php'; ?>
<!-- The same hero structure and color palette as adminDashboard.php. -->
<div class="hero-container">
 <div class="hero-text">
  <div><h2><span class="material-symbols-rounded">wb_sunny</span>Welcome back, <?=staff_h(ucfirst($_SESSION['name'] ?? 'Staff'))?>!</h2>
  <p>Here's what's happening around Sanctuario Nature Farm today.</p></div>
  <div class="hero-date"><span class="material-symbols-rounded">calendar_month</span><?=date('F d, Y')?></div>
 </div>
 <div class="hero-cards staff-hero-cards">
  <div class="hero-card"><div class="hero-icon green"><span class="material-symbols-rounded"><?= $section === 'crops' ? 'eco' : 'pets' ?></span></div>
   <div class="hero-info"><span><?= $section === 'crops' ? 'Active Crops' : 'Total Livestock' ?></span><h3><?=staff_h($section === 'crops' ? ($summary['Active Crops'] ?? '—') : ($summary['Livestock Total'] ?? '—'))?></h3></div></div>
  <div class="hero-card"><div class="hero-icon lime"><span class="material-symbols-rounded">task_alt</span></div>
   <div class="hero-info"><span>Tasks Completed</span><h3 id="heroTasksCompleted"><?=$completedToday?></h3></div></div>
 </div>
</div>
<!-- Interactive farm map: markup and behavior preserved from Admin Dashboard. -->

<section class="top-grid">

<div class="map-box">
<h3>Farm Map</h3>

<div class="svg-wrapper" id="mapContainer">

<svg id="farmMap"
     viewBox="0 0 1706 600"
     preserveAspectRatio="xMidYMid meet">

    <image
        href="../farm_map.jpg?v=20261013-map-cleanup"
        x="0"
        y="0"
        width="1706"
        height="600" />

    <!-- Nursery -->
    <image class="marker"
        href="../markers/nursery.png"
        x="1438"
        y="96"
        width="35"
        height="35"
        data-plot="nursery"/>

    <!-- Plot A -->
    <image class="marker"
        href="../markers/plot_a.png"
        x="1385"
        y="301"
        width="35"
        height="36"
        data-plot="plot_a"/>

    <!-- Plot B -->
    <image class="marker"
        href="../markers/plot_b.png"
        x="1218"
        y="294"
        width="35"
        height="35"
        data-plot="plot_b"/>

    <!-- Plot C -->
    <image class="marker"
        href="../markers/plot_c.png"
        x="1061"
        y="295"
        width="35"
        height="35"
        data-plot="plot_c"/>

    <!-- MNO -->
    <image class="marker"
        href="../markers/mno.png"
        x="728"
        y="420"
        width="35"
        height="35"
        data-plot="mno"/>

    <!-- Hydro -->
    <image class="marker"
        href="../markers/hydro.png"
        x="1281"
        y="110"
        width="35"
        height="35"
        data-plot="hydro"/>

    <!-- Tent House -->
    <image class="marker"
        href="../markers/tent_house.png"
        x="1142"
        y="102"
        width="35"
        height="35"
        data-plot="tent_house"/>

    <!-- Rabbit -->
    <image class="marker"
        href="../markers/rabbit_house.png"
        x="387"
        y="47"
        width="35"
        height="35"
        data-plot="rabbit_house"/>

    <!-- Goat -->
    <image class="marker"
        href="../markers/goat_house.png"
        x="240"
        y="98"
        width="35"
        height="35"
        data-plot="goat_house"/>

    <!-- Goat 2 -->
    <image class="marker"
        href="../markers/goat_house.png"
        x="139"
        y="55"
        width="35"
        height="35"
        data-plot="goat_house_2"/>

    <!-- Poultry -->
    <image class="marker"
        href="../markers/poultry_house.png"
        x="91"
        y="116"
        width="35"
        height="35"
        data-plot="poultry_house"/>

    <!-- Swine -->
    <image class="marker"
        href="../markers/swine_house.png"
        x="190"
        y="416"
        width="35"
        height="35"
        data-plot="swine_house"/>

</svg>

<div class="map-interaction-hint" aria-hidden="true">
    <span>Drag and Zoom the Map to interact</span>
</div>

<div id="mapPopup" class="map-popup hidden"></div>

</div>

</div>

<div class="dashboard-charts staff-dashboard-tasks">
<div class="staff-check-panel">
  <div class="staff-task-heading"><h3>Today's Task Checklist</h3><button type="button" class="staff-task-btn" id="openTaskEditor">+ Add New Task</button></div>
  <p class="staff-check-subtitle">Unfinished tasks carry over automatically. Set a due date for multi-day work.</p>
  <?php if ($taskError): ?><p class="staff-alert"><?=staff_h($taskError)?></p><?php endif; ?>
  <p id="taskProgressText" class="staff-completion"><?=$completedToday?> of <?=count($taskRows)?> tasks completed</p>
  <div class="staff-progress-track"><div class="staff-progress-fill" id="taskProgressBar" style="width:<?=count($taskRows) ? round($completedToday/count($taskRows)*100) : 0?>%"></div></div>
  <div id="staffTaskList">
  <?php foreach ($taskRows as $task): ?>
    <div class="staff-task-row">
      <label class="staff-task"><input type="checkbox" data-task-id="<?=(int)$task['id']?>" <?=(int)$task['completed'] ? 'checked' : ''?> <?= $taskError ? 'disabled' : '' ?>><span class="staff-task-content"><span class="staff-task-name"><?=staff_h($task['title'])?></span><small>Due <?=staff_h(date('M j, Y',strtotime($task['due_date'])))?><?=(!(int)$task['completed'] && $task['due_date'] < $today) ? ' · Overdue' : ''?></small></span></label>
      <div class="staff-task-actions">
        <button type="button" class="staff-task-icon" data-edit-task="<?=(int)$task['id']?>" title="Edit task" aria-label="Edit <?=staff_h($task['title'])?>" <?= $taskError ? 'disabled' : '' ?>><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L9 17l-4 1 1-4Z"/></svg></button>
        <button type="button" class="staff-task-icon danger" data-remove-task="<?=(int)$task['id']?>" title="Remove task" aria-label="Remove <?=staff_h($task['title'])?>" <?= $taskError ? 'disabled' : '' ?>><svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m5 4v7m4-7v7"/></svg></button>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$taskRows && !$taskError): ?><p class="staff-check-subtitle">No pending tasks. Click + Add New Task to add one.</p><?php endif; ?>
  </div>
  <dialog id="staffTaskDialog" class="staff-task-dialog"><form id="staffTaskForm" method="dialog">
    <h3 id="staffTaskDialogTitle">Add Task</h3>
    <input type="hidden" id="editTaskId" value="">
    <label>Task description<input id="taskTitleInput" maxlength="180" required placeholder="e.g. Transplant seedlings"></label>
    <label>Start date<input type="date" id="taskStartInput" required></label>
    <label>Due date<input type="date" id="taskDueInput" required></label>
    <p id="taskFormError" role="alert" style="color:#a33030"></p>
    <div class="staff-task-dialog-actions"><button type="button" class="staff-task-btn secondary" id="cancelTaskDialog">Cancel</button><button type="submit" class="staff-task-btn">Save Task</button></div>
  </form></dialog>


 </div>

</div>
</section>
<div class="staff-actions staff-map-link"><a class="staff-action" href="farmMap.php">Open Full Farm Map</a><a class="staff-action" href="<?= $section === 'crops' ? 'crops/index.php' : 'livestock/index.php' ?>">Open Assigned Records</a></div>
</main></div>
<script>
document.addEventListener("DOMContentLoaded", () => {

    const markers = document.querySelectorAll(".marker");
    const popup = document.getElementById("mapPopup");
    const svg = document.getElementById("farmMap");
    const container = document.getElementById("mapContainer");

    if (!svg || !container) return;

    /* ===========================
       MAP SETTINGS
       =========================== */

    /* Phone starts already zoomed; desktop keeps the normal scale. */
    const isPhone = () => window.innerWidth <= 600;
    let scale = isPhone() ? 1.5 : 1.6;
    const MIN_SCALE = isPhone() ? 1.5 : 1.6;
    const MAX_SCALE = 3.25;

    let translateX = 0;
    let translateY = 0;
    let dragging = false;
    let startX = 0;
    let startY = 0;

    const activePointers = new Map();
    let pinchStartDistance = 0;
    let pinchStartScale = scale;
    let pinchStartCenter = null;
    let pinchStartTranslate = null;

    function getBaseSize() {
        const width = Math.max(1, container.clientWidth);
        return { width, height: width * (600 / 1706) };
    }

    function clampMapPosition() {
        const { width: baseWidth, height: baseHeight } = getBaseSize();
        const scaledWidth = baseWidth * scale;
        const scaledHeight = baseHeight * scale;
        const containerWidth = container.clientWidth;
        const containerHeight = container.clientHeight;

        const minX = Math.min(0, containerWidth - scaledWidth);
        const minY = Math.min(0, containerHeight - scaledHeight);

        translateX = scaledWidth <= containerWidth
            ? (containerWidth - scaledWidth) / 2
            : Math.max(minX, Math.min(0, translateX));

        translateY = scaledHeight <= containerHeight
            ? (containerHeight - scaledHeight) / 2
            : Math.max(minY, Math.min(0, translateY));
    }

    function updateContainerHeight() {
        /* Keep the map viewport filled; desktop shows a zoomed section at a time. */
        container.style.height = isPhone() ? '158px' : '500px';
    }

    function updateTransform() {
        svg.style.transformOrigin = 'top left';
        svg.style.transform =
            `translate3d(${translateX}px, ${translateY}px, 0) scale(${scale})`;
    }

    function refreshMapLayout(resetScale = false) {
        if (resetScale) {
            /* Start zoomed in on desktop so the map fills its container.
               Users can drag horizontally to explore the rest of the farm. */
            scale = isPhone() ? 1.5 : 1.6;
            translateX = 0; // Start at the left side of the map.
            translateY = 0;
        }
        updateContainerHeight();
        clampMapPosition();
        updateTransform();
    }

    function zoomMap(nextScale, focalX, focalY) {
        const oldScale = scale;
        const newScale = Math.max(MIN_SCALE, Math.min(MAX_SCALE, nextScale));
        if (newScale === oldScale) return;

        const mapX = (focalX - translateX) / oldScale;
        const mapY = (focalY - translateY) / oldScale;

        scale = newScale;
        translateX = focalX - (mapX * scale);
        translateY = focalY - (mapY * scale);
        clampMapPosition();
        updateTransform();
    }

    function pointerDistance(a, b) {
        return Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY);
    }

    function pointerCenter(a, b) {
        return {
            x: (a.clientX + b.clientX) / 2,
            y: (a.clientY + b.clientY) / 2
        };
    }

    function getLocalPoint(clientX, clientY) {
        const rect = container.getBoundingClientRect();
        return { x: clientX - rect.left, y: clientY - rect.top };
    }

    requestAnimationFrame(() => refreshMapLayout(true));

    /* ===========================
       DRAG + PINCH ZOOM
       =========================== */

    container.addEventListener('pointerdown', (e) => {
        if (e.target.closest('.marker')) return;
        if (e.pointerType === 'mouse' && e.button !== 0) return;

        activePointers.set(e.pointerId, e);

        if (activePointers.size === 2) {
            const points = [...activePointers.values()];
            pinchStartDistance = pointerDistance(points[0], points[1]);
            pinchStartScale = scale;
            pinchStartCenter = pointerCenter(points[0], points[1]);
            pinchStartTranslate = { x: translateX, y: translateY };
            dragging = false;
            e.preventDefault();
            return;
        }

        dragging = true;
        startX = e.clientX;
        startY = e.clientY;
        if (container.setPointerCapture) {
            try { container.setPointerCapture(e.pointerId); } catch (_) {}
        }
        container.style.cursor = 'grabbing';
        e.preventDefault();
    });

    container.addEventListener('pointermove', (e) => {
        if (activePointers.has(e.pointerId)) activePointers.set(e.pointerId, e);

        if (activePointers.size >= 2) {
            const points = [...activePointers.values()].slice(0, 2);
            const distance = pointerDistance(points[0], points[1]);
            const center = pointerCenter(points[0], points[1]);

            if (pinchStartDistance > 0 && pinchStartCenter && pinchStartTranslate) {
                const rect = container.getBoundingClientRect();
                const nextScale = Math.max(
                    MIN_SCALE,
                    Math.min(MAX_SCALE, pinchStartScale * (distance / pinchStartDistance))
                );
                const focalX = pinchStartCenter.x - rect.left;
                const focalY = pinchStartCenter.y - rect.top;
                const mapX = (focalX - pinchStartTranslate.x) / pinchStartScale;
                const mapY = (focalY - pinchStartTranslate.y) / pinchStartScale;

                scale = nextScale;
                translateX = center.x - rect.left - (mapX * scale);
                translateY = center.y - rect.top - (mapY * scale);
                clampMapPosition();
                updateTransform();
            }
            e.preventDefault();
            return;
        }

        if (!dragging) return;
        translateX += e.clientX - startX;
        translateY += e.clientY - startY;
        startX = e.clientX;
        startY = e.clientY;
        clampMapPosition();
        updateTransform();
        e.preventDefault();
    }, { passive: false });

    function endPointer(e) {
        activePointers.delete(e.pointerId);
        if (activePointers.size < 2) {
            pinchStartDistance = 0;
            pinchStartCenter = null;
            pinchStartTranslate = null;
        }
        if (activePointers.size === 0) {
            dragging = false;
            container.style.cursor = 'grab';
        }
    }

    container.addEventListener('pointerup', endPointer);
    container.addEventListener('pointercancel', endPointer);

    /* Desktop mouse-wheel zoom; phones use pinch zoom. */
    container.addEventListener('wheel', (e) => {
        e.preventDefault();
        const point = getLocalPoint(e.clientX, e.clientY);
        zoomMap(scale * (e.deltaY < 0 ? 1.12 : 0.89), point.x, point.y);
    }, { passive: false });

    /* Double-tap zoom on phones. */
    let lastTap = 0;
    container.addEventListener('pointerup', (e) => {
        if (e.pointerType !== 'touch') return;
        const now = Date.now();
        if (now - lastTap < 280) {
            const point = getLocalPoint(e.clientX, e.clientY);
            zoomMap(scale < 2 ? 2 : MIN_SCALE, point.x, point.y);
            e.preventDefault();
        }
        lastTap = now;
    });

    svg.addEventListener('dragstart', (e) => e.preventDefault());
    window.addEventListener('resize', () => refreshMapLayout(true));

    /* ===========================
       MARKER POPUPS
       =========================== */

    markers.forEach(marker => {

        marker.addEventListener("click", async (e) => {

            e.stopPropagation();

            const plot = marker.dataset.plot;

            const pt = svg.createSVGPoint();

            const x = parseFloat(marker.getAttribute("x")) || 0;
            const y = parseFloat(marker.getAttribute("y")) || 0;
            const width =
                parseFloat(marker.getAttribute("width")) || 0;
            const height =
                parseFloat(marker.getAttribute("height")) || 0;

            pt.x = x + (width / 2);
            pt.y = y + (height / 2);

            const screen = pt.matrixTransform(
                svg.getScreenCTM()
            );

            popup.style.left = `${screen.x + 14}px`;
            popup.style.top = `${screen.y + 14}px`;

            popup.classList.remove("hidden");
            popup.innerHTML = "Loading...";


            try {

                const res = await fetch(
                    `../get_plot_data.php?plot=${encodeURIComponent(plot)}`
                );

                if (!res.ok) {
                    throw new Error(
                        `HTTP ${res.status}`
                    );
                }

                const data = await res.json();

                if (!data || data.error) {

                    popup.innerHTML =
                        `<b>Error:</b> ${
                            data?.error || "Invalid response"
                        }`;

                    return;
                }


                const safe = (value) => String(value ?? "--")
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");

                const formatNumber = (value) =>
                    Number(value || 0).toLocaleString();

                // Keep the summary tied to the records returned for this marker.
                // This accepts both canonical and legacy ready-status values.
                const readyStatuses = new Set([
                    "ready",
                    "ready for harvest",
                    "ready to harvest",
                    "ready_for_harvest",
                    "ready-to-harvest"
                ]);
                const readyFromRecords = Array.isArray(data.crop_records)
                    ? data.crop_records.filter((crop) => {
                        if (Number(crop.archived || 0) !== 0) return false;
                        if (Number(crop.transferred_out || 0) !== 0) return false;
                        const status = String(crop.status || "")
                            .trim()
                            .toLowerCase();
                        if (status === "harvested") return false;
                        if (readyStatuses.has(status)) return true;
                        if (!crop.expected_harvest) return false;
                        const expectedHarvest = new Date(`${crop.expected_harvest}T23:59:59`);
                        return !Number.isNaN(expectedHarvest.getTime()) && expectedHarvest <= new Date();
                    }).length
                    : Number(data.ready_to_harvest || 0);

                let html = "";

                if (data.type === "livestock") {
                    html = `
                        <div class="map-popup-head">
                            <span class="popup-kicker">Farm map location</span>
                            <h4>${safe(data.plot_house_name || plot)}</h4>
                        </div>
                        <div class="map-popup-body">
                            
                            <div class="map-popup-card">
                                <div class="map-popup-card-title">
                                    <span>Livestock house</span>
                                    <i class="fa-solid fa-paw"></i>
                                </div>
                                <div class="map-popup-detail">
                                    <span>PLOT HOUSE NAME:</span>
                                    <strong>${safe(data.plot_house_name)}</strong>
                                </div>
                                <div class="map-popup-detail">
                                    <p> NUMBER OF LIVESTOCKS </p>
                                
                                </div>
                            </div>

                            <div class="map-popup-record-list">
                                ${(Array.isArray(data.livestock_records) && data.livestock_records.length)
                                    ? data.livestock_records.map((animal, index) => `
                                        <div class="map-popup-record-item">
                                            <strong>${safe(animal.name || animal.tag_number || `${animal.type || "Livestock"} ${index + 1}`)}</strong>
                                            <span>${safe(animal.type || "Livestock")} · Qty ${formatNumber(animal.number || 1)}</span>
                                        </div>
                                    `).join("")
                                    : '<div class="map-popup-empty">No active livestock records assigned to this house.</div>'}
                            </div>
                        </div>
                    `;
                } else {
                    html = `
                        <div class="map-popup-head">
                            <span class="popup-kicker">Farm map location</span>
                            <h4>${safe(data.plot_name || plot)}</h4>
                        </div>
                        <div class="map-popup-body">
                            
                            <div class="map-popup-card">
                                <div class="map-popup-card-title">
                                    <span>Plot crop summary</span>
                                    <i class="fa-solid fa-leaf"></i>
                                </div>
                                <div class="map-popup-detail">
                                    <span>PLOT NAME:</span>
                                    <strong>${safe(data.plot_name)}</strong>
                                </div>
                                <div class="map-popup-detail">
                                    <span>PLANTS PLANTED:</span>
                                    <strong>${formatNumber(data.plants_planted)}</strong>
                                </div>
                                <div class="map-popup-detail">
                                    <span>BED AVAILABLE:</span>
                                    <strong>${formatNumber(data.bed_available)}</strong>
                                </div>
                                <div class="map-popup-detail">
                                    <span>SOON TO HARVEST:</span>
                                    <strong>${formatNumber(data.soon_to_harvest)}</strong>
                                </div>
                                <div class="map-popup-detail">
                                    <span>READY TO HARVEST:</span>
                                    <strong>${formatNumber(readyFromRecords)}</strong>
                                </div>
                            </div>
                            <div class="map-popup-record-list">
                                ${(Array.isArray(data.crop_records) && data.crop_records.length)
                                    ? data.crop_records.map((crop, index) => `
                                        <div class="map-popup-record-item">
                                            <strong>${safe(crop.crop_name || crop.crop || crop.name || crop.variety || `Crop ${index + 1}`)}</strong>
                                            <span>Bed ${safe(crop.bed_id || "--")} · ${safe(crop.status || "No status")}</span>
                                        </div>
                                    `).join("")
                                    : '<div class="map-popup-empty">No active crop records assigned to this plot.</div>'}
                            </div>
                        </div>
                    `;
                }

                popup.innerHTML = html;

                /* The popup is viewport-level now, so it can never be clipped
                   by the dashboard map/card. Keep it beside the clicked marker
                   and automatically flip it above/left when near an edge. */
                requestAnimationFrame(() => {
                    const margin = 12;
                    const gap = 14;
                    const popupRect = popup.getBoundingClientRect();
                    let left = screen.x + gap;
                    let top = screen.y + gap;

                    if (left + popupRect.width > window.innerWidth - margin) {
                        left = screen.x - popupRect.width - gap;
                    }
                    if (top + popupRect.height > window.innerHeight - margin) {
                        top = screen.y - popupRect.height - gap;
                    }

                    left = Math.max(margin, Math.min(left, window.innerWidth - popupRect.width - margin));
                    top = Math.max(margin, Math.min(top, window.innerHeight - popupRect.height - margin));

                    popup.style.left = `${left}px`;
                    popup.style.top = `${top}px`;
                });

            } catch (err) {

                popup.innerHTML =
                    "Failed to load data";

                console.error(
                    "Map popup error:",
                    err
                );
            }
        });
    });


    /* Keep wheel/touch gestures inside the popup from reaching the map drag layer. */
    ['wheel', 'touchstart', 'touchmove', 'pointerdown'].forEach((eventName) => {
        popup.addEventListener(eventName, (event) => event.stopPropagation(), { passive: eventName !== 'wheel' });
    });
    /* ===========================
       CLOSE POPUP
       =========================== */

    document.addEventListener("click", (e) => {

        if (
            !popup.contains(e.target) &&
            !e.target.closest(".marker")
        ) {
            popup.classList.add("hidden");
        }
    });

});


</script>
<script>
const taskData = <?=json_encode($taskRows, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const csrf = <?=json_encode(csrf_token())?>;
const today = <?=json_encode($today)?>;
const editor = document.getElementById('staffTaskDialog');
const taskForm = document.getElementById('staffTaskForm');
const titleField = document.getElementById('taskTitleInput');
const startField = document.getElementById('taskStartInput');
const dueField = document.getElementById('taskDueInput');
async function taskRequest(action, fields) {
 const form = new FormData(); form.append('action',action); form.append('csrf_token',csrf);
 for(const [key,value] of Object.entries(fields)) form.append(key,value);
 const response=await fetch('staff_task_save.php',{method:'POST',body:form,credentials:'same-origin'});
 const data=await response.json(); if(!response.ok || !data.ok) throw new Error(data.error || 'Unable to save task');
 return data;
}
function openTaskEditor(task) {
 document.getElementById('taskFormError').textContent='';
 document.getElementById('editTaskId').value=task?.id || '';
 document.getElementById('staffTaskDialogTitle').textContent=task?'Edit Task':'Add Task';
 titleField.value=task?.title || '';
 startField.value=task?.start_date || today;
 dueField.value=task?.due_date || today;
 editor.showModal();
}
document.getElementById('openTaskEditor').onclick=()=>openTaskEditor(null);
document.querySelectorAll('[data-edit-task]').forEach(button=>button.addEventListener('click',()=>{
 const task=taskData.find(item=>String(item.id)===button.dataset.editTask);
 if(task)openTaskEditor(task);
}));
document.querySelectorAll('[data-remove-task]').forEach(button=>button.addEventListener('click',async()=>{
 if(!confirm('Remove this task?'))return;
 button.disabled=true;
 try{await taskRequest('delete',{id:button.dataset.removeTask});location.reload()}
 catch(e){alert(e.message);button.disabled=false}
}));
document.getElementById('cancelTaskDialog').onclick=()=>editor.close();
taskForm.addEventListener('submit',async e=>{
 e.preventDefault(); if(dueField.value < startField.value){document.getElementById('taskFormError').textContent='Due date cannot be earlier than start date.';return;}
 const button=taskForm.querySelector('[type=submit]');button.disabled=true;
 try{await taskRequest('save',{id:document.getElementById('editTaskId').value,title:titleField.value,start_date:startField.value,due_date:dueField.value});location.reload()}
 catch(err){document.getElementById('taskFormError').textContent=err.message;button.disabled=false}
});
document.querySelectorAll('[data-task-id]').forEach(el=>el.addEventListener('change',async()=>{
 const checked=el.checked;el.disabled=true;
 try{await taskRequest('toggle',{id:el.dataset.taskId,completed:checked?'1':'0'});location.reload()}
 catch(err){el.checked=!checked;alert(err.message);el.disabled=false}
}));
</script></body></html>