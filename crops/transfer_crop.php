<?php
session_start();
require_once '../db.php';
require_once '../logfunc.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit(); }
$id = (int)($_GET['id'] ?? $_POST['crop_id'] ?? 0);
if ($id <= 0) exit('Invalid crop ID.');
$stmt = $conn->prepare("SELECT * FROM crops WHERE crop_id=? AND COALESCE(archived,0)=0");
$stmt->bind_param('i', $id); $stmt->execute(); $crop = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$crop) exit('Crop not found.');
if (!empty($crop['transferred_out'])) exit('This Nursery crop batch has already been transferred. Its original Nursery lifecycle has been preserved.');
$currentLocation = strtoupper(trim((string)($crop['transplanted_area'] ?? 'NURSERY')));
if ($currentLocation !== 'NURSERY') exit('Only crops currently in the Nursery can be transferred using this form.');
$locations = [];
$plotResult = $conn->query("SELECT plot_id, plot_code, plot_name FROM plots WHERE COALESCE(archived,0)=0 ORDER BY plot_id ASC");
if ($plotResult) while ($p = $plotResult->fetch_assoc()) {
    $name = strtoupper(trim((string)($p['plot_name'] ?? '')));
    $code = strtolower(trim((string)($p['plot_code'] ?? '')));
    if ($name === '' || $name === 'NURSERY' || $code === 'nursery' || $name === 'PLOT 9') continue;
    $locations[] = ['plot_id'=>(int)$p['plot_id'], 'value'=>$name, 'label'=>ucwords(strtolower($name)), 'plot_code'=>$code];
}
$activeCropRows = [];
$activeResult = $conn->query("SELECT plot_id, bed_id, transplanted_area FROM crops WHERE COALESCE(archived,0)=0 AND COALESCE(transferred_out,0)=0");
if ($activeResult) while ($row = $activeResult->fetch_assoc()) $activeCropRows[] = $row;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetPlotId = (int)($_POST['target_plot_id'] ?? 0);
    $date = trim((string)($_POST['date_transplanted'] ?? ''));
    $bedCode = strtoupper(trim((string)($_POST['bed_code'] ?? '')));
    $targetRow = null;
    foreach ($locations as $location) if ($location['plot_id'] === $targetPlotId) { $targetRow = $location; break; }
    if (!$targetRow) $error = 'Please select a valid destination plot.';
    $bedId = null; $finalArea = $targetRow['value'] ?? '';
    if ($error === '' && $bedCode === '') $error = 'Please select an available bed in the destination plot.';
    if ($error === '' && $bedCode !== '') {
        $bedStmt = $conn->prepare("SELECT bed_id, bed_code FROM plot_beds WHERE plot_id=? AND UPPER(bed_code)=? LIMIT 1");
        $bedStmt->bind_param('is', $targetPlotId, $bedCode); $bedStmt->execute(); $bed = $bedStmt->get_result()->fetch_assoc(); $bedStmt->close();
        if (!$bed && preg_match('/^([ABC])(\d{1,2})$/', $bedCode, $bedMatch)) {
            $expectedPrefix = strtoupper(substr(preg_replace('/^plot_/', '', (string)$targetRow['plot_code']), 0, 1));
            $bedNumber = (int)$bedMatch[2];
            $bedLimit = ['A'=>17, 'B'=>15, 'C'=>19][$expectedPrefix] ?? 0;
            if ($bedMatch[1] !== $expectedPrefix || $bedNumber < 1 || $bedNumber > $bedLimit) {
                $error = 'Please choose a bed code that belongs to the selected plot.';
            } else {
                $createBed = $conn->prepare("INSERT INTO plot_beds (plot_id,bed_number,bed_code) VALUES (?,?,?)");
                $createBed->bind_param('iis', $targetPlotId, $bedNumber, $bedCode);
                if ($createBed->execute()) $bed = ['bed_id'=>(int)$conn->insert_id, 'bed_code'=>$bedCode];
                else $error = 'Could not create the selected bed: ' . $createBed->error;
                $createBed->close();
            }
        }
        if (!$bed && $error === '') $error = 'Use an existing bed code, such as A4, for this plot.';
        if ($bed && $error === '') { $bedId = (int)$bed['bed_id']; $finalArea = strtoupper((string)$bed['bed_code']); }
        if ($error === '') {
            $occupied = $conn->prepare("SELECT crop_id FROM crops WHERE COALESCE(archived,0)=0 AND COALESCE(transferred_out,0)=0 AND ((bed_id=? AND bed_id IS NOT NULL) OR (plot_id=? AND UPPER(TRIM(transplanted_area))=?)) LIMIT 1");
            $occupied->bind_param('iis', $bedId, $targetPlotId, $finalArea); $occupied->execute();
            if ($occupied->get_result()->num_rows > 0) $error = 'That bed is already occupied by an active crop.';
            $occupied->close();
        }
    }
    if ($error === '') {
        $dateValue = $date !== '' ? $date : null;
        $conn->begin_transaction();
        try {
            // A new crop_id represents the production phase in the plot. The Nursery row stays as history.
            $insert = $conn->prepare("INSERT INTO crops (parent_crop_id, recipient, plot_id, plot_name, crop_name, variety, pcs, date_sowing, germination_rate, target_transplant_date, target_area, seedlings_received, date_transplanted, expected_harvest, status, image, transplanted_area, archived, transferred_out, bed_id) SELECT crop_id, recipient, ?, ?, crop_name, variety, CASE WHEN COALESCE(seedlings_received,0)>0 THEN seedlings_received ELSE COALESCE(pcs,0) END, date_sowing, germination_rate, target_transplant_date, ?, CASE WHEN COALESCE(seedlings_received,0)>0 THEN seedlings_received ELSE COALESCE(pcs,0) END, ?, expected_harvest, 'planted', image, ?, 0, 0, ? FROM crops WHERE crop_id=? AND COALESCE(archived,0)=0 AND COALESCE(transferred_out,0)=0");
            $plotName = $targetRow['label'];
            $insert->bind_param('issssii', $targetPlotId, $plotName, $finalArea, $dateValue, $finalArea, $bedId, $id);
            if (!$insert->execute() || $insert->affected_rows !== 1) throw new RuntimeException($insert->error ?: 'The crop could not be transferred.');
            $newCropId = (int)$conn->insert_id; $insert->close();
            $recordDate = $dateValue ?: date('Y-m-d');
            $recordSync = $conn->prepare("INSERT INTO plot_crop_records (crop_id, plot_id, bed_id, crop_name, variety, record_date, date_transplanted, expected_harvest, germination_rate) SELECT crop_id, ?, ?, crop_name, variety, ?, date_transplanted, expected_harvest, germination_rate FROM crops WHERE crop_id=?");
            if ($recordSync) {
                $recordSync->bind_param('iisi', $targetPlotId, $bedId, $recordDate, $newCropId);
                if (!$recordSync->execute()) throw new RuntimeException($recordSync->error ?: 'The plot record could not be created.');
                $recordSync->close();
            }
            $update = $conn->prepare("UPDATE crops SET transferred_out=1 WHERE crop_id=? AND COALESCE(transferred_out,0)=0");
            $update->bind_param('i', $id); if (!$update->execute() || $update->affected_rows !== 1) throw new RuntimeException($update->error ?: 'Could not update the Nursery history.'); $update->close();
            $conn->commit();
            logActivity($conn, (int)$_SESSION['user_id'], 'Transferred Crop to Plot', "Nursery Crop ID: {$id}; Plot Crop ID: {$newCropId}; Destination: {$plotName}; Bed: {$finalArea}");
            echo '<script>if (window.parent && window.parent !== window) { if (window.parent.closeEditModal) window.parent.closeEditModal(); window.parent.location.reload(); } else { window.location.href = "crops.php"; }</script>'; exit();
        } catch (Throwable $e) { $conn->rollback(); $error = 'Transfer failed: ' . $e->getMessage(); }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Transfer Crop to Plot</title><link rel="stylesheet" href="../style.css"><style>
*{box-sizing:border-box}body{margin:0;background:#fff;font-family:'Poppins',Arial,sans-serif;color:#435044}.form-wrapper{padding:25px}.form-card{max-width:560px;margin:auto;background:#fff;border-radius:14px;padding:25px;border-top:5px solid #2f6b2f;box-shadow:0 6px 18px rgba(0,0,0,.08)}h2{margin:0 0 18px;color:#2f6b2f;text-align:center;font-size:1.35rem}.summary{padding:12px 14px;margin-bottom:16px;border:1px solid #e0e9dc;border-radius:9px;background:#f8fbf6;font-size:13px}.summary strong{display:block;color:#2f6b2f;font-size:15px;margin-bottom:3px}.form-group{margin-bottom:13px}label{display:block;margin-bottom:5px;font-size:12px;color:#59665b;font-weight:600}select,input{width:100%;padding:10px 11px;border:1px solid #d8e2d4;border-radius:8px;font:inherit;font-size:13px;outline:none}.actions{display:flex;justify-content:flex-end;gap:9px;margin-top:18px}.btn{border:0;border-radius:8px;padding:10px 16px;font:600 12px 'Poppins',sans-serif;cursor:pointer}.cancel{background:#eef2ed;color:#576358}.confirm{background:#6b8e23;color:#fff}.hint{font-size:11px;color:#7c887d;margin-top:4px}.error{background:#fff0ee;color:#8b3028;padding:10px 12px;border-radius:8px;margin-bottom:14px;font-size:12px}.notice{background:#f5f8ef;border:1px solid #dfe8d7;border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:12px;line-height:1.5}
</style></head><body><div class="form-wrapper"><div class="form-card"><h2>Transfer to Plot</h2><div class="summary"><strong><?= htmlspecialchars($crop['crop_name'] ?? 'Crop', ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($crop['variety'] ?? 'No variety', ENT_QUOTES, 'UTF-8') ?></strong>Current location: <?= htmlspecialchars($currentLocation, ENT_QUOTES, 'UTF-8') ?><br>Nursery Crop ID: <?= (int)$crop['crop_id'] ?><br>Seedlings: <?= (int)($crop['seedlings_received'] ?? $crop['pcs'] ?? 0) ?></div><div class="notice">This creates a separate production record in the destination plot. The original Nursery record is retained as lifecycle history.</div><?php if ($error !== ''): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><form method="post"><input type="hidden" name="crop_id" value="<?= (int)$crop['crop_id'] ?>"><div class="form-group"><label for="target_plot_id">Destination plot</label><select id="target_plot_id" name="target_plot_id" required><option value="">Select plot</option><?php foreach ($locations as $location): ?><option value="<?= (int)$location['plot_id'] ?>" data-plot-code="<?= htmlspecialchars($location['plot_code'], ENT_QUOTES, 'UTF-8') ?>" <?= (int)($_POST['target_plot_id'] ?? 0)===$location['plot_id']?'selected':'' ?>><?= htmlspecialchars($location['label'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div><div class="form-group"><label for="date_transplanted">Date transplanted</label><input id="date_transplanted" type="date" name="date_transplanted" value="<?= htmlspecialchars($_POST['date_transplanted'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"></div><div class="form-group"><label for="bed_code">Destination bed / Available bed</label><select id="bed_code" name="bed_code" required disabled><option value="">Select a plot first</option></select><div class="hint">Only unoccupied beds for the selected plot are shown.</div></div><div class="actions"><button type="button" class="btn cancel" onclick="window.parent.closeEditModal ? window.parent.closeEditModal() : history.back()">Cancel</button><button type="submit" class="btn confirm">Confirm Transfer</button></div></form></div></div>
<script>
(() => {
 const plot = document.getElementById('target_plot_id');
 const bed = document.getElementById('bed_code');
 const active = <?= json_encode($activeCropRows, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
 const selectedBed = <?= json_encode(strtoupper(trim((string)($_POST['bed_code'] ?? ''))), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
 const limits = {plot_a:17, plot_b:15, plot_c:19};
 const norm = value => String(value || '').trim().toUpperCase().replace(/\s+/g, '');
 function refreshBeds() {
   const option = plot.options[plot.selectedIndex];
   const code = String(option?.dataset.plotCode || '').toLowerCase();
   bed.innerHTML = '';
   if (!code) {
     bed.append(new Option('Select a plot first', ''));
     bed.disabled = true;
     bed.required = true;
     return;
   }
   const prefix = code.replace(/^plot_/, '').charAt(0).toUpperCase();
   const limit = limits[code] || 20;
   const occupied = new Set(active.filter(row => String(row.plot_id) === String(plot.value))
     .map(row => norm(row.transplanted_area)).filter(Boolean));
   const available = [];
   for (let number = 1; number <= limit; number++) {
     const bedCode = prefix + number;
     if (!occupied.has(bedCode)) available.push(bedCode);
   }
   bed.append(new Option(available.length ? 'Select an available bed' : 'No unoccupied beds available', ''));
   available.forEach(bedCode => {
     const opt = new Option(bedCode, bedCode);
     if (bedCode === selectedBed) opt.selected = true;
     bed.append(opt);
   });
   bed.disabled = available.length === 0;
   bed.required = true;
 }
 plot.addEventListener('change', refreshBeds);
 refreshBeds();
})();
</script>
</body></html>