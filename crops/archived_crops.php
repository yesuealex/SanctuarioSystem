<?php
session_start();
include '../db.php';
require_once '../archive/archive_functions.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if (($_SESSION['role'] ?? '') !== 'admin') { header("Location: ../records.php?type=crops"); exit(); }

try { ensureArchiveTable($conn, 'crops'); } catch (Throwable $e) { $archiveError = $e->getMessage(); }
$selectedCrop = trim($_GET['crop'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['archive_action'] ?? '';
    $cropId = (int)($_POST['crop_id'] ?? 0);
    if ($cropId > 0) {
        try {
            if ($action === 'restore') {
                restoreRecord($conn, 'crops', 'crop_id', $cropId);
                header("Location: archived_crops.php?restored=1" . ($selectedCrop !== '' ? '&crop=' . urlencode($selectedCrop) : '')); exit();
            }
            if ($action === 'delete') {
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
    $stmt=$conn->prepare("SELECT * FROM archived_crops WHERE LOWER(crop_name)=LOWER(?) ORDER BY crop_id DESC");
    $stmt->bind_param('s',$selectedCrop); $stmt->execute(); $cropsResult=$stmt->get_result();
} else {
    $cropsResult=$conn->query("SELECT * FROM archived_crops ORDER BY crop_id DESC");
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
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}

th,
td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    text-align: left;
    font-size: .9rem;
}

th {
    background: #f1f5f9;
}

tr:hover {
    background: #f9fafb;
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
    table-layout: fixed;
    min-width: 1180px;
}
.archive-partitioned-table th,
.archive-partitioned-table td {
    border-right: 1px solid #dfe7dc;
}
.archive-partitioned-table th:last-child,
.archive-partitioned-table td:last-child { border-right: 0; }
@media (max-width: 700px) {
    .archive-partitioned-table { min-width: 1180px; }
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

<?php if ($role === 'admin'): ?>
<th>Action</th>
<?php endif; ?>

</tr>

</thead>


<tbody>

<?php if ($cropsResult && $cropsResult->num_rows > 0): ?>

<?php while ($row = $cropsResult->fetch_assoc()): ?>

<tr class="archive-row">

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






<?php if ($role === 'admin'): ?>

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


</div>

</td>

<?php endif; ?>

</tr>

<?php endwhile; ?>


<?php else: ?>

<tr>

<td
    colspan="<?= $role === 'admin' ? '10' : '9' ?>"
    class="no-records"
>

No archived crop records found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</main>

</div>


<script>

function searchArchivedCrops() {

    const search =
        document
            .getElementById('archiveSearch')
            .value
            .toLowerCase();

    const rows =
        document.querySelectorAll('.archive-row');

    rows.forEach(function(row) {

        row.style.display =
            row.textContent
                .toLowerCase()
                .includes(search)
                ? ''
                : 'none';

    });

}


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