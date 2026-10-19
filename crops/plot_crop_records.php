<?php
require_once __DIR__ . '/../staff_access.php';
sanctuario_staff_guard('crops');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../db.php';
require_once '../logfunc.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit(); }

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
$plotParam = trim((string)($_GET['plot'] ?? ''));
$plotIdParam = (int)($_GET['plot_id'] ?? 0);
$plot = null;
if ($plotIdParam > 0) {
    $st = $conn->prepare("SELECT plot_id, plot_code, plot_name FROM plots WHERE plot_id=? AND COALESCE(archived,0)=0 LIMIT 1");
    $st->bind_param('i', $plotIdParam);
} else {
    $key = strtolower(str_replace(' ', '_', $plotParam));
    if (preg_match('/^[abc]$/i', $key)) $key = 'plot_' . $key;
    if ($key === 'mno') $key = 'mno';
    if ($key === 'nursery' || $plotParam === '') { http_response_code(400); exit('Select a production plot. Nursery lifecycle is kept in the Nursery records.'); }
    $st = $conn->prepare("SELECT plot_id, plot_code, plot_name FROM plots WHERE LOWER(plot_code)=? OR LOWER(plot_name)=? LIMIT 1");
    $name = strtolower($plotParam);
    $st->bind_param('ss', $key, $name);
}
$st->execute(); $plot = $st->get_result()->fetch_assoc(); $st->close();
if (!$plot) { http_response_code(404); exit('Plot not found.'); }
if (strtolower((string)$plot['plot_code']) === 'nursery' || strtoupper(trim((string)$plot['plot_name'])) === 'NURSERY') { http_response_code(400); exit('Nursery lifecycle is maintained separately from plot production records.'); }
$plotId = (int)$plot['plot_id'];
$plotName = trim((string)($plot['plot_name'] ?: $plot['plot_code']));
$plotCode = strtolower((string)$plot['plot_code']);
$prefix = strtoupper(substr(preg_replace('/^plot_/', '', $plotCode), 0, 1));
$areaLabel = strtoupper($plotName);

// Lifecycle and application records are keyed to the distinct crop record in this plot.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $cropId = (int)($_POST['crop_id'] ?? 0);
    $check = $conn->prepare("SELECT crop_id FROM crops WHERE crop_id=? AND COALESCE(archived,0)=0 AND (plot_id=? OR UPPER(TRIM(transplanted_area))=? OR UPPER(TRIM(transplanted_area)) LIKE CONCAT(?, '%')) LIMIT 1");
    $prefixLike = $prefix;
    $check->bind_param('iiss', $cropId, $plotId, $areaLabel, $prefixLike);
    $check->execute(); $validCrop = $check->get_result()->fetch_assoc(); $check->close();
    if (!$validCrop) { http_response_code(400); exit('The selected crop does not belong to this plot.'); }

    if ($action === 'save_lifecycle') {
        $harvest = trim((string)($_POST['total_harvest'] ?? ''));
        $incomeRaw = trim((string)($_POST['total_income'] ?? ''));
        $income = $incomeRaw === '' ? null : (float)$incomeRaw;
        $problems = trim((string)($_POST['problems_encountered'] ?? ''));
        $actionTaken = trim((string)($_POST['action_taken'] ?? ''));
        $remarks = trim((string)($_POST['remarks'] ?? ''));
        $pullOutRaw = trim((string)($_POST['date_pull_out'] ?? ''));
        $pullOut = $pullOutRaw !== '' ? $pullOutRaw : null;
        $st = $conn->prepare("INSERT INTO crop_plot_lifecycle (crop_id,total_harvest,total_income,problems_encountered,action_taken,remarks,date_pull_out) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE total_harvest=VALUES(total_harvest),total_income=VALUES(total_income),problems_encountered=VALUES(problems_encountered),action_taken=VALUES(action_taken),remarks=VALUES(remarks),date_pull_out=VALUES(date_pull_out),updated_at=CURRENT_TIMESTAMP");
        $st->bind_param('isdssss', $cropId, $harvest, $income, $problems, $actionTaken, $remarks, $pullOut);
        if (!$st->execute()) { exit('Could not save plot lifecycle record: ' . h($st->error)); }
        $st->close();
        logActivity($conn, (int)$_SESSION['user_id'], 'Updated Plot Crop Lifecycle', "Crop ID: {$cropId} in {$plotName}");
    } elseif ($action === 'add_application') {
        $material = trim((string)($_POST['application_name'] ?? ''));
        $appDate = trim((string)($_POST['application_date'] ?? ''));
        $notes = trim((string)($_POST['application_notes'] ?? ''));
        if ($material === '' || $appDate === '') exit('Application name and date are required.');
        $st = $conn->prepare("INSERT INTO crop_plot_applications (crop_id,application_name,application_date,notes) VALUES (?,?,?,?)");
        $st->bind_param('isss', $cropId, $material, $appDate, $notes);
        if (!$st->execute()) exit('Could not add application: ' . h($st->error));
        $st->close();
        logActivity($conn, (int)$_SESSION['user_id'], 'Added Crop Application', "Crop ID: {$cropId}; {$material} on {$appDate}");
    } elseif ($action === 'delete_application') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $st = $conn->prepare("DELETE FROM crop_plot_applications WHERE application_id=? AND crop_id=?");
        $st->bind_param('ii', $appId, $cropId); $st->execute(); $st->close();
    }
    $go = 'plot_crop_records.php?plot_id=' . $plotId . '&crop_id=' . $cropId . '#record-form';
    header('Location: ' . $go); exit();
}

$sql = "SELECT c.crop_id,c.crop_name,c.variety,c.pcs,c.date_sowing,c.date_transplanted,c.expected_harvest,c.status,c.transplanted_area,pcr.bed_id,b.bed_code,l.total_harvest,l.total_income,l.problems_encountered,l.action_taken,l.remarks,l.date_pull_out
        FROM crops c INNER JOIN plot_crop_records pcr ON pcr.crop_id=c.crop_id AND COALESCE(pcr.archived,0)=0 LEFT JOIN plot_beds b ON b.bed_id=pcr.bed_id LEFT JOIN crop_plot_lifecycle l ON l.crop_id=c.crop_id
        WHERE COALESCE(c.archived,0)=0 AND (c.plot_id=? OR UPPER(TRIM(c.transplanted_area))=? OR UPPER(TRIM(c.transplanted_area)) LIKE CONCAT(?, '%'))
        ORDER BY c.crop_id ASC";
$st = $conn->prepare($sql); $st->bind_param('iss', $plotId, $areaLabel, $prefix); $st->execute(); $result = $st->get_result();
$crops = []; while ($row = $result->fetch_assoc()) $crops[] = $row; $st->close();
$applications = [];
if ($crops) {
    $ids = array_map(static fn($c) => (int)$c['crop_id'], $crops);
    $in = implode(',', $ids);
    $res = $conn->query("SELECT application_id,crop_id,application_name,application_date,notes FROM crop_plot_applications WHERE crop_id IN ({$in}) ORDER BY application_date ASC, application_id ASC");
    if ($res) while ($a = $res->fetch_assoc()) $applications[(int)$a['crop_id']][] = $a;
}
$selectedId = (int)($_GET['crop_id'] ?? ($crops[0]['crop_id'] ?? 0));
$selected = null; foreach ($crops as $c) if ((int)$c['crop_id'] === $selectedId) { $selected = $c; break; }
if (!$selected && $crops) $selected = $crops[0];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= h($plotName) ?> | Crop Production Records</title><link rel="stylesheet" href="../style.css"><style>
:root{--green:#2f6b2f;--line:#dfe7db;--muted:#718071}*{box-sizing:border-box}body{margin:0;background:#f6f8f4;color:#344236;font-family:Poppins,Arial,sans-serif}.page{max-width:1600px;margin:auto;padding:24px}.heading{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px}.heading h1{margin:0;color:var(--green);font-size:clamp(20px,3vw,28px)}.heading p{margin:5px 0 0;color:var(--muted);font-size:13px}.card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:18px;margin-bottom:18px;box-shadow:0 3px 12px #1b321108}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:9px}table{border-collapse:collapse;width:100%;min-width:1250px;font-size:12px}th,td{border:1px solid #cfd8cc;padding:10px;vertical-align:top;text-align:left}th{background:#edf3e9;color:#334a32;font-weight:700;white-space:normal}td{line-height:1.6}td em{font-style:italic}.app-row{display:flex;gap:7px;align-items:flex-start;margin-bottom:6px}.app-row form{margin:0}.tiny{font-size:11px;color:var(--muted)}.btn{display:inline-block;border:0;border-radius:7px;padding:9px 13px;font:600 12px Poppins,Arial,sans-serif;cursor:pointer;text-decoration:none}.primary{background:var(--green);color:white}.soft{background:#edf2e9;color:#38513a}.danger{background:#f9e8e5;color:#923c31}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.field label{display:block;font-size:12px;font-weight:600;margin-bottom:5px;color:#536353}.field input,.field select,.field textarea{width:100%;border:1px solid #d7e0d2;border-radius:7px;padding:10px;font:13px Poppins,Arial,sans-serif;background:#fff}.field textarea{min-height:82px;resize:vertical}.field.full{grid-column:1/-1}.section-title{margin:0 0 12px;color:var(--green);font-size:16px}.form-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:14px}.application-form{display:grid;grid-template-columns:1fr 180px 1.2fr auto;gap:8px;margin-top:10px}.application-form input{min-width:0;border:1px solid #d7e0d2;border-radius:7px;padding:9px;font:12px Poppins,Arial,sans-serif}.crop-select{width:100%;max-width:360px;border:1px solid #d7e0d2;border-radius:7px;padding:10px;font:13px Poppins,Arial,sans-serif;margin-bottom:14px}.empty{padding:22px;text-align:center;color:var(--muted)}@media(max-width:760px){.page{padding:12px}.card{padding:12px}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.application-form{grid-template-columns:1fr}.heading .btn{width:100%;text-align:center}}
</style></head><body><main class="page">
<div class="heading"><a class="btn soft" href="crops.php">Back to Crop Records</a></div>
<section class="card"><h2 class="section-title">Plot production table</h2><?php if (!$crops): ?><div class="empty">No crop records are currently assigned to this plot. Transfer a crop from the Nursery first.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Crop</th><th>Variety</th><th>Date of transplant</th><th>Concoction / Vermicast applied & date of application</th><th>Total number of harvest</th><th>Total income</th><th>Problems encountered</th><th>Action taken to solve the problem</th><th>Remarks (solution effectiveness)</th><th>Date of pull out</th><th>Action</th></tr></thead><tbody>
<?php foreach ($crops as $c): $cid=(int)$c['crop_id']; ?><tr><td><em><?= h($c['crop_name']) ?></em><div class="tiny">Crop ID: <?= $cid ?><?= $c['bed_code'] ? ' · Bed '.h($c['bed_code']) : '' ?></div></td><td><em><?= h($c['variety'] ?: '—') ?></em></td><td><em><?= h($c['date_transplanted'] ?: '—') ?></em></td><td><?php if (!empty($applications[$cid])): foreach ($applications[$cid] as $a): ?><div class="app-row"><span><em><?= h($a['application_name']) ?>, <?= h($a['application_date']) ?></em><?php if ($a['notes']): ?><div class="tiny"><?= h($a['notes']) ?></div><?php endif; ?></span><form method="post" onsubmit="return confirm('Delete this application entry?')"><input type="hidden" name="action" value="delete_application"><input type="hidden" name="crop_id" value="<?= $cid ?>"><input type="hidden" name="application_id" value="<?= (int)$a['application_id'] ?>"><button class="btn danger" type="submit">×</button></form></div><?php endforeach; else: ?><span class="tiny">No applications recorded</span><?php endif; ?></td><td><em><?= h($c['total_harvest'] ?: '—') ?></em></td><td><em><?= $c['total_income'] !== null ? 'PHP '.number_format((float)$c['total_income'],2) : '—' ?></em></td><td><em><?= nl2br(h($c['problems_encountered'] ?: '—')) ?></em></td><td><em><?= nl2br(h($c['action_taken'] ?: '—')) ?></em></td><td><em><?= nl2br(h($c['remarks'] ?: '—')) ?></em></td><td><em><?= h($c['date_pull_out'] ?: '—') ?></em></td><td><a class="btn soft" href="plot_crop_records.php?plot_id=<?= $plotId ?>&crop_id=<?= $cid ?>#record-form">Edit record</a> <a class="btn soft" href="edit_crop.php?id=<?= $cid ?>">Edit bed</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php if ($selected): ?><section class="card" id="record-form"><h2 class="section-title">Update lifecycle — <?= h($selected['crop_name']) ?></h2><form method="get"><input type="hidden" name="plot_id" value="<?= $plotId ?>"><label class="tiny" for="crop_id">Choose crop record</label><select class="crop-select" id="crop_id" name="crop_id" onchange="this.form.submit()"><?php foreach($crops as $c): ?><option value="<?= (int)$c['crop_id'] ?>" <?= (int)$c['crop_id']===(int)$selected['crop_id']?'selected':'' ?>><?= h($c['crop_name']) ?> — <?= h($c['variety'] ?: 'No variety') ?> (ID <?= (int)$c['crop_id'] ?>)</option><?php endforeach; ?></select></form>
<form method="post"><input type="hidden" name="action" value="save_lifecycle"><input type="hidden" name="crop_id" value="<?= (int)$selected['crop_id'] ?>"><div class="form-grid"><div class="field"><label>Crop</label><input value="<?= h($selected['crop_name']) ?>" readonly></div><div class="field"><label>Variety</label><input value="<?= h($selected['variety']) ?>" readonly></div><div class="field"><label>Total number of harvest (e.g. 30 kg)</label><input name="total_harvest" value="<?= h($selected['total_harvest']) ?>" placeholder="e.g. 30 kg"></div><div class="field"><label>Total income (PHP)</label><input type="number" min="0" step="0.01" name="total_income" value="<?= h($selected['total_income']) ?>" placeholder="0.00"></div><div class="field"><label>Problems encountered</label><textarea name="problems_encountered" placeholder="Describe pests, diseases, or other issues"><?= h($selected['problems_encountered']) ?></textarea></div><div class="field"><label>Action taken to solve the problem</label><textarea name="action_taken" placeholder="Treatment or action taken"><?= h($selected['action_taken']) ?></textarea></div><div class="field"><label>Remarks (solution effectiveness)</label><textarea name="remarks" placeholder="Record the outcome of the treatment"><?= h($selected['remarks']) ?></textarea></div><div class="field"><label>Date of pull out</label><input type="date" name="date_pull_out" value="<?= h($selected['date_pull_out']) ?>"></div></div><div class="form-actions"><button class="btn primary" type="submit">Save lifecycle record</button></div></form>
<hr style="border:0;border-top:1px solid #e3e9df;margin:20px 0"><h3 class="section-title">Add concoction / vermicast application</h3><form method="post" class="application-form"><input type="hidden" name="action" value="add_application"><input type="hidden" name="crop_id" value="<?= (int)$selected['crop_id'] ?>"><input name="application_name" required placeholder="e.g. Vermicast"><input type="date" name="application_date" required><input name="application_notes" placeholder="Optional notes"><button class="btn primary" type="submit">Add application</button></form></section><?php endif; ?>
</main></body></html>