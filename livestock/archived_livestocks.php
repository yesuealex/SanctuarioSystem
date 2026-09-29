<?php

session_start();

include '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'staff';

if ($role !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

/*
|--------------------------------------------------------------------------
| RESTORE / PERMANENT DELETE
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['archive_action'] ?? '';
    $livestockId = isset($_POST['livestock_id'])
        ? (int) $_POST['livestock_id']
        : 0;

    if ($livestockId <= 0) {
        header("Location: archived_livestocks.php");
        exit();
    }

    if ($action === 'restore') {

        $stmt = $conn->prepare("
            UPDATE livestocks
            SET archived = 0
            WHERE livestock_id = ?
            AND archived = 1
        ");

        if ($stmt) {
            $stmt->bind_param("i", $livestockId);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: archived_livestocks.php?restored=1");
        exit();
    }

    if ($action === 'delete') {

        /*
         * Remove monitoring records belonging to this livestock first.
         * These table names match the monitoring tables used by livestocks.php.
         * If a table does not exist, it is simply skipped.
         */
        $monitoringTables = [
            'chicken_weight_records',
            'chicken_egg_records',
            'chicken_mortality_records',
            'chicken_feed_ingredients',
            'chicken_drinking_water',
            'chicken_feeding_schedule',
            'goat_monitoring_records',
            'goat_forage_records'
        ];

        foreach ($monitoringTables as $table) {
            $check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");

            if ($check && $check->num_rows > 0) {
                $delete = $conn->prepare("
                    DELETE FROM `$table`
                    WHERE livestock_id = ?
                ");

                if ($delete) {
                    $delete->bind_param("i", $livestockId);
                    $delete->execute();
                    $delete->close();
                }
            }

            if ($check) {
                $check->free();
            }
        }

        $stmt = $conn->prepare("
            DELETE FROM livestocks
            WHERE livestock_id = ?
            AND archived = 1
        ");

        if ($stmt) {
            $stmt->bind_param("i", $livestockId);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: archived_livestocks.php?deleted=1");
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| GET ARCHIVED LIVESTOCK
|--------------------------------------------------------------------------
*/
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

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Archived Livestocks</title>

<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="../sidebar.css">
<link rel="stylesheet" href="livestocks.css">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

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

.archive-page {
    width: 100%;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
}

.page-header-left h1 {
    margin: 0;
    font-size: 1.7rem;
}

.page-header-left p {
    margin: 5px 0 0;
    color: #777;
    font-size: .9rem;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 14px;
    background: #f1f5f9;
    color: #333;
    border-radius: 8px;
    text-decoration: none;
    font-size: .85rem;
    transition: .2s;
}

.back-btn:hover {
    background: #e2e8f0;
}

.archive-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.search-box {
    width: 340px;
    max-width: 100%;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 9px 12px;
}

.search-box input {
    width: 100%;
    border: none;
    outline: none;
    font-family: 'Poppins', sans-serif;
    font-size: .9rem;
}

.archive-count {
    color: #777;
    font-size: .85rem;
}

.table-container {
    width: 100%;
    overflow-x: auto;
    background: #fff;
    border-radius: 12px;
}

table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}

th,
td {
    padding: 12px 13px;
    border-bottom: 1px solid #eee;
    text-align: left;
    font-size: .88rem;
    white-space: nowrap;
}

th {
    background: #f1f5f9;
    font-weight: 600;
}

tbody tr:hover {
    background: #f9fafb;
}

.row-number {
    width: 45px;
    text-align: center;
}

.action-buttons {
    display: flex;
    justify-content: center;
    gap: 8px;
}

.action-icon {
    width: 35px;
    height: 35px;
    border: none;
    border-radius: 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: .2s;
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
    padding: 35px;
}

.archive-message {
    margin-bottom: 15px;
    padding: 11px 14px;
    border-radius: 8px;
    background: #e8f5e9;
    color: #2f6b2f;
    font-size: .88rem;
}

@media(max-width:700px) {
    .dashboard {
        margin-left: 0;
        padding: 12px;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .archive-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .search-box {
        width: auto;
    }
}
</style>

</head>

<body>

<div class="layout">

<?php include '../sidebar.php'; ?>

<main class="dashboard">

<?php include '../topRightBar.php'; ?>

<div class="archive-page">

    <div class="page-header">

        <div class="page-header-left">
            <h1>ARCHIVED LIVESTOCKS</h1>
            <p>Manage livestock records that have been archived.</p>
        </div>

        <a href="livestocks.php" class="back-btn">
            <span class="material-symbols-rounded">arrow_back</span>
            Back to Livestocks
        </a>

    </div>

    <?php if (isset($_GET['restored'])): ?>
        <div class="archive-message">
            Livestock record restored successfully.
        </div>
    <?php elseif (isset($_GET['deleted'])): ?>
        <div class="archive-message">
            Livestock record permanently deleted.
        </div>
    <?php endif; ?>

    <div class="archive-toolbar">

        <div class="search-box">

            <span class="material-symbols-rounded">search</span>

            <input
                type="text"
                id="archiveSearch"
                placeholder="Search archived livestock..."
                onkeyup="searchArchivedLivestocks()"
            >

        </div>

        <div class="archive-count" id="archiveCount"></div>

    </div>

    <div class="table-container">

        <table id="archiveTable">

            <thead>
                <tr>
                    <th class="row-number">#</th>
                    <th>ID</th>
                    <th>TYPE</th>
                    <th>NAME</th>
                    <th>BREED</th>
                    <th>DATE OF BIRTH</th>
                    <th>AGE</th>
                    <th>ESTIMATED WEIGHT</th>
                    <th>SEX</th>
                    <th>FOR SALE</th>
                    <th>NUMBER</th>
                    <th>ACTION</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($livestocksResult && $livestocksResult->num_rows > 0): ?>

                <?php $rowNumber = 1; ?>

                <?php while ($row = $livestocksResult->fetch_assoc()): ?>

                    <tr class="archive-row">

                        <td class="row-number">
                            <?= $rowNumber++ ?>
                        </td>

                        <td>
                            <?= (int)($row['livestock_id'] ?? 0) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['type'] ?? '-') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['name'] ?? '-') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['breed'] ?? '-') ?>
                        </td>

                        <td>
                            <?php
                            if (!empty($row['date_of_birth'])) {
                                $timestamp = strtotime($row['date_of_birth']);
                                echo $timestamp
                                    ? htmlspecialchars(date('F d, Y', $timestamp))
                                    : htmlspecialchars($row['date_of_birth']);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['age'] ?? '-') ?>
                        </td>

                        <td>
                            <?php
                            if (
                                isset($row['estimated_weight']) &&
                                $row['estimated_weight'] !== ''
                            ) {
                                echo htmlspecialchars($row['estimated_weight']) . ' kg';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['sex'] ?? '-') ?>
                        </td>

                        <td>
                            <?php
                            $forSale = $row['for_sale'] ?? 0;
                            echo (
                                $forSale === '1' ||
                                $forSale === 1 ||
                                strtolower((string)$forSale) === 'yes'
                            ) ? 'Yes' : 'No';
                            ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['number'] ?? '-') ?>
                        </td>

                        <td>

                            <div class="action-buttons">

                                <form
                                    method="post"
                                    onsubmit="return confirm('Restore this livestock back to the active records?');"
                                >

                                    <input
                                        type="hidden"
                                        name="archive_action"
                                        value="restore"
                                    >

                                    <input
                                        type="hidden"
                                        name="livestock_id"
                                        value="<?= (int)$row['livestock_id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="action-icon restore-icon"
                                        title="Restore Livestock"
                                    >
                                        <span class="material-symbols-rounded">
                                            restore
                                        </span>
                                    </button>

                                </form>

                                <form
                                    method="post"
                                    onsubmit="return confirm('Permanently delete this livestock? This action cannot be undone.');"
                                >

                                    <input
                                        type="hidden"
                                        name="archive_action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="livestock_id"
                                        value="<?= (int)$row['livestock_id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="action-icon delete-icon"
                                        title="Delete Permanently"
                                    >
                                        <span class="material-symbols-rounded">
                                            delete
                                        </span>
                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td colspan="12" class="no-records">
                        No archived livestock records found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</main>

</div>

<script>

function searchArchivedLivestocks() {

    const input = document.getElementById('archiveSearch');

    const search = input
        ? input.value.toLowerCase().trim()
        : '';

    const rows = document.querySelectorAll('.archive-row');

    let visible = 0;

    rows.forEach(function(row) {

        const matches = row.textContent
            .toLowerCase()
            .includes(search);

        row.style.display = matches ? '' : 'none';

        if (matches) {
            visible++;
        }

    });

    updateArchiveCount(visible);

}

function updateArchiveCount(visibleCount) {

    const count = document.getElementById('archiveCount');

    if (!count) {
        return;
    }

    const total = document.querySelectorAll('.archive-row').length;

    count.textContent =
        visibleCount + ' of ' + total + ' archived record' +
        (total === 1 ? '' : 's');

}

document.addEventListener('DOMContentLoaded', function() {

    updateArchiveCount(
        document.querySelectorAll('.archive-row').length
    );

});

</script>

</body>
</html>
