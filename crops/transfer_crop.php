<?php

session_start();
require_once '../db.php';
require_once '../logfunc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$id = (int)($_GET['id'] ?? $_POST['crop_id'] ?? 0);
if ($id <= 0) exit('Invalid crop ID.');

$stmt = $conn->prepare("SELECT * FROM crops WHERE crop_id = ? AND COALESCE(archived, 0) = 0");
$stmt->bind_param('i', $id);
$stmt->execute();
$crop = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$crop) exit('Crop not found.');

$locations = [
    ['value' => 'PLOT A', 'label' => 'Plot A', 'plot_id' => 1],
    ['value' => 'PLOT B', 'label' => 'Plot B', 'plot_id' => 2],
    ['value' => 'PLOT C', 'label' => 'Plot C', 'plot_id' => 3],
    ['value' => 'MNO', 'label' => 'MNO', 'plot_id' => null],
];
$plotResult = $conn->query("SELECT plot_id, plot_name FROM plots WHERE COALESCE(archived, 0) = 0 ORDER BY plot_id ASC");
if ($plotResult) {
    while ($plot = $plotResult->fetch_assoc()) {
        $name = strtoupper(trim((string)$plot['plot_name']));
        if ($name === 'PLOT 9' || $name === '') continue;
        if (!in_array($name, array_column($locations, 'value'), true)) {
            $locations[] = ['value' => $name, 'label' => ucwords(strtolower($name)), 'plot_id' => (int)$plot['plot_id']];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = strtoupper(trim((string)($_POST['target_location'] ?? '')));
    $date = trim((string)($_POST['date_transplanted'] ?? ''));
    $bedCode = trim((string)($_POST['bed_code'] ?? ''));
    $targetRow = null;
    foreach ($locations as $location) {
        if ($location['value'] === $target) { $targetRow = $location; break; }
    }
    if (!$targetRow) exit('Please select a valid target plot.');

    $plotId = $targetRow['plot_id'] !== null ? (int)$targetRow['plot_id'] : null;
    $dateValue = $date !== '' ? $date : ($crop['date_transplanted'] ?: null);
    $bedId = null;
    if ($bedCode !== '' && $plotId !== null) {
        $bedStmt = $conn->prepare("SELECT bed_id FROM plot_beds WHERE plot_id = ? AND (bed_code = ? OR CAST(bed_number AS CHAR) = ?) LIMIT 1");
        $bedStmt->bind_param('iss', $plotId, $bedCode, $bedCode);
        $bedStmt->execute();
        $bedId = $bedStmt->get_result()->fetch_assoc()['bed_id'] ?? null;
        $bedStmt->close();
    }

    // transplanted_area is the existing current-location field. Keep the crop ID;
    // this is an UPDATE, never an INSERT + DELETE.
    if ($plotId === null) {
        $update = $conn->prepare("UPDATE crops SET plot_id = NULL, transplanted_area = ?, date_transplanted = ?, bed_id = NULL WHERE crop_id = ?");
        $update->bind_param('ssi', $target, $dateValue, $id);
    } else {
        $update = $conn->prepare("UPDATE crops SET plot_id = ?, transplanted_area = ?, date_transplanted = ?, bed_id = ? WHERE crop_id = ?");
        $update->bind_param('issii', $plotId, $target, $dateValue, $bedId, $id);
    }
    if (!$update->execute()) exit('Transfer failed: ' . htmlspecialchars($update->error));
    $update->close();

    logActivity($conn, (int)$_SESSION['user_id'], 'Transferred Crop', "Crop ID: {$id} moved to {$target}");
    echo '<script>if (window.parent && window.parent !== window) { window.parent.closeEditModal(); window.parent.location.reload(); } else { window.location.href = "crops.php"; }</script>';
    exit();
}

$currentLocation = strtoupper(trim((string)($crop['transplanted_area'] ?? 'NURSERY')));
?>
<!doctype html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Transfer Crop</title><link rel="stylesheet" href="../style.css"><style>
*{box-sizing:border-box}body{margin:0;background:#fff;font-family:'Poppins',Arial,sans-serif;color:#435044}.form-wrapper{padding:25px}.form-card{max-width:560px;margin:auto;background:#fff;border-radius:14px;padding:25px;border-top:5px solid #2f6b2f;box-shadow:0 6px 18px rgba(0,0,0,.08)}h2{margin:0 0 18px;color:#2f6b2f;text-align:center;font-size:1.35rem}.summary{padding:12px 14px;margin-bottom:16px;border:1px solid #e0e9dc;border-radius:9px;background:#f8fbf6;font-size:13px}.summary strong{display:block;color:#2f6b2f;font-size:15px;margin-bottom:3px}.form-group{margin-bottom:13px}label{display:block;margin-bottom:5px;font-size:12px;color:#59665b;font-weight:600}select,input{width:100%;padding:10px 11px;border:1px solid #d8e2d4;border-radius:8px;font:inherit;font-size:13px;outline:none}select:focus,input:focus{border-color:#6b8e23;box-shadow:0 0 0 3px rgba(107,142,35,.08)}.actions{display:flex;justify-content:flex-end;gap:9px;margin-top:18px}.btn{border:0;border-radius:8px;padding:10px 16px;font:600 12px 'Poppins',sans-serif;cursor:pointer}.cancel{background:#eef2ed;color:#576358}.confirm{background:#6b8e23;color:#fff}.hint{font-size:11px;color:#7c887d;margin-top:4px}
</style></head><body><div class="form-wrapper"><div class="form-card"><h2>Transfer to Plot</h2><div class="summary"><strong><?= htmlspecialchars($crop['crop_name'] ?? 'Crop') ?></strong>Current location: <?= htmlspecialchars($currentLocation) ?><br>Crop ID: <?= (int)$crop['crop_id'] ?></div><form method="post"><input type="hidden" name="crop_id" value="<?= (int)$crop['crop_id'] ?>"><div class="form-group"><label for="target_location">Target Plot</label><select id="target_location" name="target_location" required><option value="">Select plot</option><?php foreach ($locations as $location): ?><option value="<?= htmlspecialchars($location['value'], ENT_QUOTES) ?>"><?= htmlspecialchars($location['label']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label for="date_transplanted">Transplant Date</label><input id="date_transplanted" type="date" name="date_transplanted" value="<?= htmlspecialchars($crop['date_transplanted'] ?? '') ?>"></div><div class="form-group"><label for="bed_code">Bed</label><input id="bed_code" name="bed_code" placeholder="Optional bed code, e.g. A4"><div class="hint">The selected location is stored separately from the bed assignment.</div></div><div class="actions"><button type="button" class="btn cancel" onclick="window.parent.closeEditModal ? window.parent.closeEditModal() : history.back()">Cancel</button><button type="submit" class="btn confirm">Confirm Transfer</button></div></form></div></div></body></html>