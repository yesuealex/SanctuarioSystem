<?php
require_once __DIR__ . '/../staff_access.php';
sanctuario_staff_guard('crops');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include '../db.php';
require_once '../archive/archive_functions.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
$role = (string)($_SESSION['role'] ?? '');
$isArchiveAdmin = in_array($role, ['admin', 'super admin'], true);

try { ensureArchiveTable($conn, 'crops'); } catch (Throwable $e) { $archiveError = $e->getMessage(); }
$selectedCrop = trim((string)($_GET['crop'] ?? ''));

$archiveLocationOptions = [
    'NURSERY' => 'Nursery',
    'PLOT_A' => 'Plot A',
    'PLOT_B' => 'Plot B',
    'PLOT_C' => 'Plot C',
    'MNO' => 'MNO',
    'HYDRO' => 'Hydro',
    'OTHER' => 'Other / Unassigned'
];

function archivedCropLocationKey(array $row): string {
    $area = strtoupper(trim((string)($row['transplanted_area'] ?? '')));
    $values = [
        $area,
        strtoupper(trim((string)($row['archive_plot_code'] ?? ''))),
        strtoupper(trim((string)($row['archive_plot_name'] ?? ''))),
        strtoupper(trim((string)($row['plot_name'] ?? '')))
    ];
    $locationText = implode(' ', array_filter($values, static fn($value) => $value !== ''));

    if (preg_match('/NURSERY/', $locationText) || preg_match('/^N[0-9]+$/', $area)) return 'NURSERY';
    if (preg_match('/MNO/', $locationText) || preg_match('/^M[0-9]+$/', $area)) return 'MNO';
    if (preg_match('/HYDRO/', $locationText) || preg_match('/^H[0-9]+$/', $area)) return 'HYDRO';
    if (preg_match('/PLOT[_ ]?A/', $locationText) || preg_match('/^A[0-9]*$/', $area)) return 'PLOT_A';
    if (preg_match('/PLOT[_ ]?B/', $locationText) || preg_match('/^B[0-9]*$/', $area)) return 'PLOT_B';
    if (preg_match('/PLOT[_ ]?C/', $locationText) || preg_match('/^C[0-9]*$/', $area)) return 'PLOT_C';

    return 'OTHER';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['archive_action'] ?? '';
    $cropId = (int)($_POST['crop_id'] ?? 0);
    if ($cropId > 0) {
        try {
            if ($action === 'restore') {
                restoreRecord($conn, 'crops', 'crop_id', $cropId);
                header("Location: archived_crops.php?restored=1" . ($selectedCrop !== '' ? '&crop=' . urlencode($selectedCrop) : '')); exit();
            }
            if ($action === 'delete' && $isArchiveAdmin) {
                permanentlyDeleteArchived($conn, 'crops', 'crop_id', $cropId);
                header("Location: archived_crops.php?deleted=1" . ($selectedCrop !== '' ? '&crop=' . urlencode($selectedCrop) : '')); exit();
            }
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }
}

$typeResult = $conn->query("SELECT DISTINCT crop_name FROM archived_crops WHERE crop_name IS NOT NULL AND TRIM(crop_name) <> '' ORDER BY crop_name ASC");
$cropTypes=[];
if($typeResult){ while($r=$typeResult->fetch_assoc()) $cropTypes[]=$r['crop_name']; }

if ($selectedCrop !== '') {
    $stmt=$conn->prepare("SELECT ac.*, p.plot_code AS archive_plot_code, p.plot_name AS archive_plot_name, pb.bed_code AS archive_bed_code FROM archived_crops ac LEFT JOIN plots p ON p.plot_id=ac.plot_id LEFT JOIN plot_beds pb ON pb.bed_id=ac.bed_id WHERE LOWER(ac.crop_name)=LOWER(?) ORDER BY ac.crop_id DESC");
    $stmt->bind_param('s',$selectedCrop); $stmt->execute(); $cropsResult=$stmt->get_result();
} else {
    $cropsResult=$conn->query("SELECT ac.*, p.plot_code AS archive_plot_code, p.plot_name AS archive_plot_name, pb.bed_code AS archive_bed_code FROM archived_crops ac LEFT JOIN plots p ON p.plot_id=ac.plot_id LEFT JOIN plot_beds pb ON pb.bed_id=ac.bed_id ORDER BY ac.crop_id DESC");
}

$archivedCropRows = [];
$nurseryCropRows = [];
$plotCropRows = [];
if ($cropsResult) {
    while ($row = $cropsResult->fetch_assoc()) {
        $row['_archive_location_key'] = archivedCropLocationKey($row);
        $archivedCropRows[] = $row;
        if ($row['_archive_location_key'] === 'NURSERY') {
            $nurseryCropRows[] = $row;
        } else {
            $plotCropRows[] = $row;
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Archived Crops</title>

<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="../sidebar.css">

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
    rel="stylesheet"
>

<link
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:wght@400"
    rel="stylesheet"
>

<style>

body {
    font-family: 'Poppins', sans-serif;
}

.layout {
    display: flex;
}

.dashboard {
    margin-left: 260px;
    padding: 20px;
    width: 100%;
    box-sizing: border-box;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-header h2 {
    margin: 0;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 13px;
    background: #f1f5f9;
    color: #333;
    border-radius: 7px;
    text-decoration: none;
    font-size: .85rem;
}

.back-btn:hover {
    background: #e2e8f0;
}


.crop-filter { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:15px; }
.crop-filter a { display:inline-flex; align-items:center; gap:6px; padding:8px 13px; border-radius:8px; background:#f1f5f9; color:#444; text-decoration:none; font-size:.82rem; transition:.2s; }
.crop-filter a:hover, .crop-filter a.active { background:#e6f0e2; color:#3f6637; }

.archive-location-filter { display:flex; align-items:center; flex-wrap:wrap; gap:7px; margin:0 0 14px; }
.archive-location-button {
    border:1px solid #d9e3d4;
    border-radius:8px;
    padding:8px 13px;
    background:#fff;
    color:#526052;
    font:600 12px 'Poppins',sans-serif;
    cursor:pointer;
    transition:.18s ease;
}
.archive-location-button:hover { border-color:#6b8e23; color:#426331; background:#f5f8f1; }
.archive-location-button.active { background:#6b8e23; border-color:#6b8e23; color:#fff; }
.archive-section-heading { margin:0 0 8px; color:#365a31; font-size:1rem; font-weight:600; }
.archive-data-note { margin:0 0 10px; padding:9px 12px; border:1px solid #d6e0d1; border-radius:8px; background:#f7faf5; color:#60735b; font-size:12px; line-height:1.45; }
.plot-record-subtext { margin-top:4px; color:#718071; font-size:11px; }

.search-box {
    width: 300px;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 7px;
    padding: 8px 12px;
    margin-bottom: 15px;
}

.search-box input {
    width: 100%;
    border: none;
    outline: none;
    font-family: 'Poppins', sans-serif;
}

.table-container {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    border: 1px solid #d6e0d1;
    border-radius: 10px;
    -webkit-overflow-scrolling: touch;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
    font-family: 'Poppins', sans-serif;
}

th,
td {
    padding: 10px 12px;
    border: 1px solid #d6e0d1;
    text-align: left;
    font-size: 14px;
    line-height: 1.45;
    vertical-align: middle;
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: normal;
}

th {
    background: #e3ebdc;
    color: #365a31;
    font-size: 13px;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 2;
}

td {
    background: #fff;
    color: #344234;
}

#archiveTable tbody > tr > td,
#archiveTable tbody > tr:hover > td {
    background: #fff !important;
    background-color: #fff !important;
    background-image: none !important;
}

.action-buttons {
    display: flex;
    justify-content: center;
    gap: 8px;
}

.action-icon {
    width: 34px;
    height: 34px;
    border: none;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.restore-icon {
    background: #e8f5e9;
    color: #2f6b2f;
}

.restore-icon:hover {
    background: #2f6b2f;
    color: #fff;
}

.delete-icon {
    background: #fdecec;
    color: #d32f2f;
}

.delete-icon:hover {
    background: #d32f2f;
    color: #fff;
}

.no-records {
    text-align: center;
    color: #888;
    padding: 30px;
}

@media(max-width:700px) {

    .dashboard {
        margin-left: 0;
        padding: 12px;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .search-box {
        width: 100%;
    }

}


/* Archived record tables: separated columns and a visible horizontal drag bar. */
.archive-table-container {
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 8px;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: auto;
    scrollbar-color: #789b70 #edf3ea;
}
.archive-table-container::-webkit-scrollbar { height: 12px; }
.archive-table-container::-webkit-scrollbar-track { background: #edf3ea; border-radius: 8px; }
.archive-table-container::-webkit-scrollbar-thumb { background: #789b70; border-radius: 8px; border: 2px solid #edf3ea; }
.archive-partitioned-table {
    width: 100%;
    table-layout: auto;
    min-width: 1500px;
}
.archive-partitioned-table th,
.archive-partitioned-table td {
    border-right: 1px solid #dfe7dc;
}
.archive-partitioned-table th:last-child,
.archive-partitioned-table td:last-child { border-right: 0; }
.archive-plot-table { min-width: 1600px; }
.archive-plot-table th { text-align:center; vertical-align:middle; white-space:normal; }
.archive-plot-table th.transplant-group-heading,
.archive-plot-table th.harvest-group-heading,
.archive-plot-table th.sales-group-heading { background:#d5e2cc; color:#365a31; }
.archive-plot-table tbody > tr > td,
.archive-plot-table tbody > tr:hover > td { background:#fff!important; background-color:#fff!important; background-image:none!important; }
.archive-location-table[hidden] { display:none!important; }
@media (max-width: 700px) {
    .archive-partitioned-table { min-width: 1500px; }
    .archive-plot-table { min-width:1600px; }
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

<body>

<div class="layout">

<?php include '../sidebar.php'; ?>


<main class="dashboard">

<?php include '../topRightBar.php'; ?>


<div class="page-header">

    <h2>Archived Crops</h2>

    <a
        href="../records.php?type=crops"
        class="back-btn"
    >

        <span class="material-symbols-rounded">
            arrow_back
        </span>

        Back to Crops

    </a>

</div>


<div class="archive-location-filter" role="group" aria-label="Archived crop location sections">
<?php foreach ($archiveLocationOptions as $locationKey => $locationLabel): ?>
    <button
        type="button"
        class="archive-location-button<?= $locationKey === 'NURSERY' ? ' active' : '' ?>"
        data-location-filter="<?= htmlspecialchars($locationKey, ENT_QUOTES) ?>"
        aria-pressed="<?= $locationKey === 'NURSERY' ? 'true' : 'false' ?>"
        onclick="setArchivedCropLocation('<?= htmlspecialchars($locationKey, ENT_QUOTES) ?>', this)"
    ><?= htmlspecialchars($locationLabel) ?></button>
<?php endforeach; ?>
</div>

<div class="search-box">

    <span class="material-symbols-rounded">
        search
    </span>

    <input
        type="text"
        id="archiveSearch"
        placeholder="Search archived crops..."
        onkeyup="searchArchivedCrops()"
    >

</div>


<section class="archive-location-table" data-archive-kind="NURSERY">
<h3 class="archive-section-heading">Nursery records</h3>
<div class="table-container archive-table-container">

<table id="archiveTable" class="archive-partitioned-table">

<thead>

<tr>

<th>RECIPIENT</th>
<th>PLOT</th>
<th>CROP</th>
<th>VARIETY</th>
<th>NO. OF<br>PLANTS</th>
<th>DATE OF<br>SOWING</th>
<th>GERMINATION<br>RATE</th>
<th>TARGET<br>TRANSPLANT DATE</th>
<th>TARGET<br>AREA</th>
<th>SEEDLINGS<br>RECIEVED</th>
<th>DATE<br>TRANSPLANTED</th>
<th>EXPECTED<br>HARVEST</th>
<th>STATUS</th>
<th>BED<br>PLOT</th>

<?php if (in_array($role, ['admin', 'super admin', 'crops staff'], true)): ?>
<th>Action</th>
<?php endif; ?>

</tr>

</thead>


<tbody>

<?php foreach ($nurseryCropRows as $row): ?>
<tr class="archive-row" data-location="<?= htmlspecialchars($row['_archive_location_key'], ENT_QUOTES) ?>">

<td>
<?= htmlspecialchars($row['recipient']) ?>
</td>

<td>
<?= htmlspecialchars($row['plot_id']) ?>
</td>

<td>
<?= htmlspecialchars($row['crop_name']) ?>
</td>

<td>
<?= htmlspecialchars($row['variety']) ?>
</td>

<td>
<?= htmlspecialchars($row['pcs']) ?>
</td>

<td>
<?= htmlspecialchars($row['date_sowing']) ?>
</td>

<td>
<?= htmlspecialchars($row['germination_rate']) ?>%
</td>

<td>
<?= htmlspecialchars($row['target_transplant_date']) ?>
</td>

<td>
<?= htmlspecialchars($row['target_area']) ?>
</td>

<td>
<?= htmlspecialchars($row['seedlings_received']) ?>
</td>

<td>
<?= htmlspecialchars($row['date_transplanted']) ?>
</td>

<td>
<?= htmlspecialchars($row['expected_harvest']) ?>
</td>

<td>
<?= htmlspecialchars($row['status']) ?>
</td>

<td>
<?= htmlspecialchars($row['transplanted_area']) ?>
</td>






<?php if (in_array($role, ['admin', 'super admin', 'crops staff'], true)): ?>

<td>

<div class="action-buttons">


<!-- RESTORE -->

<button
    type="button"
    class="action-icon restore-icon"
    title="Restore Crop"
    onclick="restoreCrop(
        <?= intval($row['crop_id']) ?>
    )"
>

<span class="material-symbols-rounded">
restore
</span>

</button>


<?php if ($isArchiveAdmin): ?>
<!-- PERMANENT DELETE -->

<button
    type="button"
    class="action-icon delete-icon"
    title="Delete Permanently"
    onclick="deleteCrop(
        <?= intval($row['crop_id']) ?>
    )"
>

<span class="material-symbols-rounded">
delete
</span>

</button>
<?php endif; ?>


</div>

</td>

<?php endif; ?>

</tr>

<?php endforeach; ?>
<tr class="archive-empty-row">
    <td colspan="<?= in_array($role, ['admin', 'super admin', 'crops staff'], true) ? '15' : '14' ?>" class="no-records">No matching archived Nursery records found.</td>
</tr>

</tbody>

</table>

</div>
</section>

<section class="archive-location-table" data-archive-kind="PLOT">
<h3 class="archive-section-heading">Plot records</h3>
<div class="archive-data-note">Transplant details come from the archived crop record. Harvest, sales, use, reject, and pull/harvest details are blank because they were stored in separate plot records that this archive does not retain.</div>
<div class="table-container archive-table-container">
<table id="archivePlotTable" class="archive-partitioned-table archive-plot-table">
<thead>
<tr>
    <th colspan="4" class="transplant-group-heading">TRANSPLANT</th>
    <th colspan="8" class="harvest-group-heading">HARVEST</th>
    <th rowspan="3">DATE OF<br>PULL / HARVEST</th>
    <th rowspan="3">ACTION</th>
</tr>
<tr>
    <th rowspan="2">PLOT NUMBER</th>
    <th rowspan="2">DATE</th>
    <th rowspan="2">CROP</th>
    <th rowspan="2">NUMBER OF<br>CROPS</th>
    <th rowspan="2">TOTAL NUMBER<br>OF HARVEST</th>
    <th rowspan="2">TOTAL<br>QUANTITY</th>
    <th colspan="2" class="sales-group-heading">SALES</th>
    <th rowspan="2">FARM USE</th>
    <th rowspan="2">ANIMAL FEED</th>
    <th rowspan="2">REJECT</th>
    <th rowspan="2">OTHER<br>PURPOSES</th>
</tr>
<tr><th>QUANTITY</th><th>AMOUNT</th></tr>
</thead>
<tbody>
<?php foreach ($plotCropRows as $row):
    $plotNumber = trim((string)($row['archive_bed_code'] ?? ''));
    if ($plotNumber === '') $plotNumber = trim((string)($row['transplanted_area'] ?? ''));
    if ($plotNumber === '') $plotNumber = trim((string)($row['archive_plot_name'] ?? $row['plot_name'] ?? $row['plot_id'] ?? ''));
    $transplantDate = trim((string)($row['date_transplanted'] ?? ''));
?>
<tr class="archive-row" data-location="<?= htmlspecialchars($row['_archive_location_key'], ENT_QUOTES) ?>">
    <td><?= htmlspecialchars($plotNumber) ?></td>
    <td><?= $transplantDate !== '' && strtotime($transplantDate) ? htmlspecialchars(date('M j, Y', strtotime($transplantDate))) : '' ?></td>
    <td><?= htmlspecialchars((string)($row['crop_name'] ?? '')) ?><?php if (!empty($row['variety'])): ?><div class="plot-record-subtext"><?= htmlspecialchars((string)$row['variety']) ?></div><?php endif; ?></td>
    <td><?= htmlspecialchars((string)($row['pcs'] ?? '')) ?></td>
    <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
    <td>
        <div class="action-buttons">
            <button type="button" class="action-icon restore-icon" title="Restore Crop" onclick="restoreCrop(<?= (int)$row['crop_id'] ?>)"><span class="material-symbols-rounded">restore</span></button>
            <button type="button" class="action-icon delete-icon" title="Delete Permanently" onclick="deleteCrop(<?= (int)$row['crop_id'] ?>)"><span class="material-symbols-rounded">delete</span></button>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<tr class="archive-empty-row"><td colspan="14" class="no-records">No matching archived plot records found.</td></tr>
</tbody>
</table>
</div>
</section>

</main>

</div>


<script>

let activeArchivedCropLocation = 'NURSERY';

function searchArchivedCrops() {
    const search = (document.getElementById('archiveSearch')?.value || '').toLowerCase().trim();
    document.querySelectorAll('.archive-location-table').forEach(function(section) {
        const isNurserySection = section.dataset.archiveKind === 'NURSERY';
        const showSection = isNurserySection
            ? activeArchivedCropLocation === 'NURSERY'
            : activeArchivedCropLocation !== 'NURSERY';
        section.hidden = !showSection;

        let visible = 0;
        section.querySelectorAll('.archive-row').forEach(function(row) {
            const matchesLocation = row.dataset.location === activeArchivedCropLocation;
            const matchesSearch = row.textContent.toLowerCase().includes(search);
            const show = matchesLocation && matchesSearch;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        const emptyRow = section.querySelector('.archive-empty-row');
        if (emptyRow) emptyRow.style.display = visible > 0 ? 'none' : '';
    });
}

function setArchivedCropLocation(location, button) {
    activeArchivedCropLocation = location;
    document.querySelectorAll('.archive-location-button').forEach(function(item) {
        const isActive = item === button;
        item.classList.toggle('active', isActive);
        item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
    searchArchivedCrops();
}

searchArchivedCrops();


function restoreCrop(id) {

    if (
        confirm(
            'Restore this crop back to the active records?'
        )
    ) {

        window.location.href =
            'restore_crop.php?id=' + id;

    }

}


function deleteCrop(id) {

    if (
        confirm(
            'Permanently delete this crop? This action cannot be undone.'
        )
    ) {

        window.location.href =
            'permanent_delete_crop.php?id=' + id;

    }

}

</script>

</body>

</html>