<?php

session_start();

include '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'staff';


$livestocksResult = $conn->query("
    SELECT *
    FROM livestocks
    WHERE archived = 1
    ORDER BY livestock_id DESC
");

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Archived Livestocks</title>

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

</style>

</head>

<body>

<div class="layout">

<?php include '../sidebar.php'; ?>


<main class="dashboard">

<?php include '../topRightBar.php'; ?>


<div class="page-header">

    <h2>Archived livestocks</h2>

    <a
        href="livestocks.php?type=livestocks"
        class="back-btn"
    >

        <span class="material-symbols-rounded">
            arrow_back
        </span>

        Back to Livestocks

    </a>

</div>


<div class="search-box">

    <span class="material-symbols-rounded">
        search
    </span>

    <input
        type="text"
        id="archiveSearch"
        placeholder="Search archived livestocks..."
        onkeyup="searchArchivedLivestocks()"
    >

</div>


<div class="table-container">

<table id="archiveTable">

<thead>

<tr>

<th>Birth Date</th>
<th>Status</th>
<th>Free Range Schedule</th>
<th>Type of Drinks</th>
<th>Disinfect Cleaning</th>
<th>Notes Observation</th>

<?php if ($role === 'admin'): ?>
<th>Action</th>
<?php endif; ?>

</tr>

</thead>


<tbody>

<?php if ($livestocksResult && $livestocksResult->num_rows > 0): ?>

<?php while ($row = $livestocksResult->fetch_assoc()): ?>

<tr class="archive-row">

<td>
<?= htmlspecialchars($row['livestock_id']) ?>
</td>

<td>
<?= htmlspecialchars($row['type']) ?>
</td>

<td>
<?= htmlspecialchars($row['name']) ?>
</td>

<td>
<?= htmlspecialchars($row['breed']) ?>
</td>

<td>
<?= htmlspecialchars($row['date_of_birth']) ?>
</td>

<td>
<?= htmlspecialchars($row['age']) ?>%
</td>

<td>
<?= htmlspecialchars($row['estimated_weight']) ?>
</td>

<td>
<?= htmlspecialchars($row['for_sale']) ?>
</td>

<td>
<?= htmlspecialchars($row['number']) ?>
</td>


<?php if ($role === 'admin'): ?>

<td>

<div class="action-buttons">


<!-- RESTORE -->

<button
    type="button"
    class="action-icon restore-icon"
    title="Restore Livestocks"
    onclick="restoreLivestock(
        <?= intval($row['livetock_id']) ?>
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
    onclick="deleteLIvestock(
        <?= intval($row['livetstock_id']) ?>
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

No archived livestocks records found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</main>

</div>


<script>

function searchArchivedLivetsocks() {

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


function restoreLivetstocks(id) {

    if (
        confirm(
            'Restore this livestocks back to the active records?'
        )
    ) {

        window.location.href =
            'restore_livestock.php?id=' + id;

    }

}


function deletelivestock(id) {

    if (
        confirm(
            'Permanently delete this livestocks? This action cannot be undone.'
        )
    ) {

        window.location.href =
            'permanent_delete_livestock.php?id=' + id;

    }

}

</script>

</body>

</html>