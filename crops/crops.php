<?php

session_start();

include '../db.php';
include '../map_special_plots.php';


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login.php");

    exit();

}


/*
|--------------------------------------------------------------------------
| USER ROLE
|--------------------------------------------------------------------------
*/

$role = $_SESSION['role'] ?? 'staff';
$selectedLocation = trim((string)($_GET['location'] ?? 'all'));
$selectedLocationKey = strtoupper(preg_replace('/\s+/', ' ', $selectedLocation));
$isNurseryView = in_array(str_replace('_', ' ', $selectedLocationKey), ['NURSERY', 'NURSERY BED'], true);
$isPlotView = preg_match('/^PLOT\s+[A-Z0-9_-]+$/', $selectedLocationKey) === 1
    || in_array($selectedLocationKey, ['MNO', 'HYDRO', 'TENT HOUSE', 'TENT_HOUSE'], true);
$plotNameMatch = $selectedLocationKey;
$plotCodeMatch = strtolower(str_replace(' ', '_', $selectedLocationKey));
$plotRecords = null;
$plotRecordError = false;
// Keep older databases compatible with the harvest columns used by the plot table.
$harvestColumns = [
    'harvest_count' => 'DECIMAL(10,2) NOT NULL DEFAULT 1 AFTER harvest_date',
    'other_purposes_quantity' => 'DECIMAL(12,3) DEFAULT NULL AFTER rejected_unit',
    'other_purposes_unit' => 'VARCHAR(30) DEFAULT NULL AFTER other_purposes_quantity'
];
foreach ($harvestColumns as $column => $definition) {
    $columnEscaped = $conn->real_escape_string($column);
    $columnCheck = $conn->query("SHOW COLUMNS FROM plot_crop_harvests LIKE '{$columnEscaped}'");
    if ($columnCheck && $columnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE plot_crop_harvests ADD COLUMN `{$column}` {$definition}");
    }
}
if ($isPlotView) {
    // Plot-specific records are read from the new production tables. Each harvest
    // event remains a separate row so historical entries are never overwritten.
    $plotCode = strtolower(str_replace(' ', '_', $selectedLocationKey));
    $plotStmt = $conn->prepare("SELECT pcr.record_id, pcr.crop_id, pcr.plot_id, pcr.bed_id,
            COALESCE(NULLIF(pcr.crop_name,''), c.crop_name) AS crop_name,
            c.variety, c.pcs AS crop_count, p.plot_name, p.plot_code,
            COALESCE(pb.bed_code, pb2.bed_code, c.transplanted_area) AS bed_code,
            COALESCE(pcr.record_date, c.date_transplanted, c.date_sowing) AS record_date,
            pcr.fertilizer_insecticide_applied, pcr.archived AS record_archived,
            h.harvest_id, h.harvest_date, h.harvest_count, h.total_quantity, h.total_unit,
            h.sales_quantity, h.sales_unit, h.sales_amount,
            h.farm_use_quantity, h.farm_use_unit, h.animal_feed_quantity,
            h.animal_feed_unit, h.rejected_quantity, h.rejected_unit,
            h.other_purposes_quantity, h.other_purposes_unit,
            (SELECT COUNT(*) FROM plot_crop_harvests hx WHERE hx.record_id=pcr.record_id) AS harvest_event_count
        FROM plots p
        LEFT JOIN crops c ON (
            c.plot_id=p.plot_id
            OR UPPER(TRIM(c.transplanted_area))=UPPER(TRIM(p.plot_name))
            OR LOWER(TRIM(c.transplanted_area))=LOWER(TRIM(p.plot_code))
        )
        LEFT JOIN plot_crop_records pcr ON pcr.crop_id=c.crop_id AND pcr.plot_id=p.plot_id AND COALESCE(pcr.archived,0)=0
        LEFT JOIN plot_beds pb ON pb.bed_id=pcr.bed_id
        LEFT JOIN plot_beds pb2 ON pb2.bed_id=c.bed_id
        LEFT JOIN plot_crop_harvests h ON h.record_id=pcr.record_id
        WHERE c.crop_id IS NOT NULL AND COALESCE(c.archived,0)=0 AND COALESCE(p.archived,0)=0
          AND (UPPER(p.plot_name)=? OR LOWER(p.plot_code)=?)
        ORDER BY COALESCE(h.harvest_date,pcr.record_date,c.date_transplanted,c.date_sowing) DESC, c.crop_id DESC, h.harvest_id DESC");
    if ($plotStmt) {
        $plotNameMatch = $selectedLocationKey;
        $plotCodeMatch = strtolower(str_replace(' ', '_', $selectedLocationKey));
        $plotStmt->bind_param('ss', $plotNameMatch, $plotCodeMatch);
        $plotStmt->execute();
        $plotRecords = $plotStmt->get_result();
    } else {
        $plotRecordError = true;
    }
}

function cropLocationKey($value): string {
    $value = strtoupper(trim((string)$value));
    $compact = preg_replace('/\s+/', '', $value);
    if ($compact === 'NURSERY') return 'NURSERY';
    if ($compact === 'MNO') return 'MNO';
    if (preg_match('/^(?:PLOT[_ ]?)?([ABC])(?:[0-9]+)?$/', $compact, $match)) return 'PLOT ' . $match[1];
    if (preg_match('/^PLOT[_ ]?([0-9]+)$/', $compact, $match)) return 'PLOT ' . $match[1];
    return preg_replace('/\s+/', ' ', $value);
}

// transplanted_area is the existing current-location field for crop records.
$locationLabels = [
    'NURSERY' => 'Nursery',
    'PLOT A' => 'Plot A',
    'PLOT B' => 'Plot B',
    'PLOT C' => 'Plot C',
    'MNO' => 'MNO',
    'HYDRO' => 'Hydro',
    'TENT HOUSE' => 'Tent House'
];
$allowedLocationKeys = array_keys($locationLabels);
$locationResult = $conn->query("SELECT DISTINCT TRIM(transplanted_area) AS location FROM crops WHERE COALESCE(archived, 0) = 0 AND COALESCE(transferred_out, 0) = 0 AND TRIM(COALESCE(transplanted_area, '')) <> '' ORDER BY location ASC");
if ($locationResult) {
    while ($locationRow = $locationResult->fetch_assoc()) {
        $rawLocation = strtoupper(trim((string)$locationRow['location']));
        $normalLocation = cropLocationKey($rawLocation);
        if (preg_match('/^([ABC])(?:[0-9]+)?$/', str_replace(' ', '', $normalLocation), $match)) {
            $normalLocation = 'PLOT ' . $match[1];
        }
        if (!in_array($normalLocation, $allowedLocationKeys, true)) {
            continue;
        }
        $locationLabels[$normalLocation] = ucwords(strtolower($normalLocation));
    }
}

// Include every active database plot as a selector, even when it currently has no crops.
$plotsForSelector = $conn->query("SELECT plot_code, plot_name FROM plots WHERE COALESCE(archived,0)=0 ORDER BY plot_id ASC");
if ($plotsForSelector) {
    while ($plotOption = $plotsForSelector->fetch_assoc()) {
        $code = strtoupper(trim((string)($plotOption['plot_code'] ?? '')));
        $name = strtoupper(trim((string)($plotOption['plot_name'] ?? '')));
        if ($code === 'NURSERY' || $name === 'NURSERY') {
            $locationLabels['NURSERY'] = 'Nursery';
        } elseif ($code === 'MNO' || $name === 'MNO') {
            $locationLabels['MNO'] = 'MNO';
        } else {
            $normal = cropLocationKey($name !== '' ? $name : $code);
            if (!in_array($normal, $allowedLocationKeys, true)) {
                continue;
            }
            $locationLabels[$normal] = ucwords(strtolower($normal));
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET ACTIVE CROP RECORDS
|--------------------------------------------------------------------------
*/

if (!$isPlotView && $selectedLocationKey === 'NURSERY') {
    $cropsStmt = $conn->prepare("SELECT crops.*, plots.plot_name, plots.plot_code, nc.nursery_bed_label, pb.bed_code FROM crops LEFT JOIN plots ON crops.plot_id=plots.plot_id LEFT JOIN nursery_crops nc ON nc.crop_id=crops.crop_id AND COALESCE(nc.archived,0)=0 LEFT JOIN plot_beds pb ON pb.bed_id=crops.bed_id WHERE COALESCE(crops.archived,0)=0 AND COALESCE(crops.transferred_out,0)=0 AND (UPPER(TRIM(crops.transplanted_area))='NURSERY' OR LOWER(TRIM(COALESCE(plots.plot_code,'')))='nursery' OR UPPER(TRIM(COALESCE(plots.plot_name,'')))='NURSERY') ORDER BY crops.crop_id DESC");
    $cropsStmt->execute();
    $cropsResult = $cropsStmt->get_result();
} elseif (!$isPlotView && $selectedLocationKey === 'MNO') {
    $cropsStmt = $conn->prepare("SELECT crops.*, plots.plot_name, plots.plot_code, nc.nursery_bed_label, pb.bed_code FROM crops LEFT JOIN plots ON crops.plot_id=plots.plot_id LEFT JOIN nursery_crops nc ON nc.crop_id=crops.crop_id AND COALESCE(nc.archived,0)=0 LEFT JOIN plot_beds pb ON pb.bed_id=crops.bed_id WHERE COALESCE(crops.archived,0)=0 AND COALESCE(crops.transferred_out,0)=0 AND (UPPER(TRIM(crops.transplanted_area))='MNO' OR LOWER(TRIM(COALESCE(plots.plot_code,'')))='mno' OR UPPER(TRIM(COALESCE(plots.plot_name,'')))='MNO') ORDER BY crops.crop_id DESC");
    $cropsStmt->execute();
    $cropsResult = $cropsStmt->get_result();
} else {
    $cropsResult = $conn->query("SELECT crops.*, plots.plot_name, plots.plot_code, nc.nursery_bed_label, pb.bed_code FROM crops LEFT JOIN plots ON crops.plot_id=plots.plot_id LEFT JOIN nursery_crops nc ON nc.crop_id=crops.crop_id AND COALESCE(nc.archived,0)=0 LEFT JOIN plot_beds pb ON pb.bed_id=crops.bed_id WHERE COALESCE(crops.archived,0)=0 AND COALESCE(crops.transferred_out,0)=0 ORDER BY crops.crop_id DESC");
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

<title>
    Crops Records
</title>


<!-- MAIN CSS -->

<link
    rel="stylesheet"
    href="../style.css"
>


<!-- SIDEBAR -->

<link
    rel="stylesheet"
    href="../sidebar.css"
>


<!-- CROPS CSS -->

<link
    rel="stylesheet"
    href="crops.css?v=20261016-table-grid-2"
>


<!-- GOOGLE FONT -->

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
    rel="stylesheet"
>


<!-- MATERIAL ICONS -->

<link
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:wght@400"
    rel="stylesheet"
>

<style>
.crop-location-btn{display:inline-flex;align-items:center;text-decoration:none;cursor:pointer}
.plot-harvest-table-wrapper{max-width:100%;overflow-x:auto}
.plot-harvest-table{min-width:1200px;border-collapse:collapse}
.plot-harvest-table th{text-align:center;vertical-align:middle;white-space:normal}
.plot-harvest-table .harvest-group-heading,.plot-harvest-table .sales-group-heading{background:#eaf1e7}
.plot-record-subtext,.plot-record-note{font-size:11px;color:#718071;margin-top:4px}
.plot-record-note{padding:8px 2px}

</style>

</head>


<body>


<div class="layout">


    <!-- =====================================================
         SIDEBAR
         ===================================================== -->

    <?php include '../sidebar.php'; ?>


    <!-- =====================================================
         MAIN CONTENT
         ===================================================== -->

    <main class="dashboard">


        <!-- TOP RIGHT BAR -->

        <?php include '../topRightBar.php'; ?>


        <!-- =====================================================
             CROP RECORDS
         ===================================================== -->

        <section class="crop-records">


            <!-- =================================================
                 TOP CONTROLS
                 ================================================= -->

            <div class="crop-topbar">


                <!-- LEFT -->

                <div class="crop-filters">


                    <!-- LOCATION BUTTONS -->

                    <div class="crop-location-buttons" id="cropLocationButtons">
                        <?php foreach ($locationLabels as $locationValue => $locationLabel): ?>
                            <a class="crop-location-btn <?= $selectedLocationKey === strtoupper($locationValue) ? 'active' : '' ?>" href="crops.php?location=<?= rawurlencode($locationValue) ?>">
                                <?= htmlspecialchars($locationLabel) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>


                    <!-- SEARCH -->

                    <div class="crop-search">

                        <span class="material-symbols-rounded">
                            search
                        </span>

                        <input
                            type="text"
                            id="cropSearch"
                            placeholder="Search here..."
                            onkeyup="filterCropRecords()"
                        >

                    </div>

                    <div class="crop-extra-filters">

                        <input
                            type="date"
                            id="cropDateFilter"
                            class="crop-date-filter"
                            title="Filter by date"
                            onchange="filterCropRecords()"
                        >

                        <input
                            type="text"
                            id="cropStaffFilter"
                            class="crop-staff-filter"
                            placeholder="Staff / recipient"
                            onkeyup="filterCropRecords()"
                        >

                    </div>


                </div>


                <!-- RIGHT -->

                <div class="crop-actions">


                    <?php if ($role === 'admin'): ?>


                        <!-- ADD CROP -->

                        <button
                            type="button"
                            class="add-crop-btn"
                            onclick="openEditModal('add_crop.php', 'Add Crop')"
                        >

                            <span class="material-symbols-rounded">
                                add
                            </span>

                            Add Crop

                        </button>


                        <!-- ARCHIVED -->

                        <button
                            type="button"
                            class="archived-crops-btn"
                            onclick="location.href='archived_crops.php'"
                        >

                            View Archived Crops

                            <span class="material-symbols-rounded">
                                chevron_right
                            </span>

                        </button>


                    <?php endif; ?>


                </div>


            </div>


            <!-- =================================================
                 TABLE
                 ================================================= -->

            <?php if ($isPlotView): ?>
                <div class="crop-table-wrapper plot-harvest-table-wrapper">
                    <?php if ($plotRecordError): ?>
                        <div class="no-records">Plot Records tables are not available yet. Import <code>migration_plot_nursery_tables.sql</code> first.</div>
                    <?php else: ?>
                    <table id="cropTable" class="plot-harvest-table">
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
                            <tr>
                                <th>QUANTITY</th>
                                <th>AMOUNT</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($plotRecords && $plotRecords->num_rows): ?>
                            <?php while ($pr=$plotRecords->fetch_assoc()):
                                $dateVal = $pr['harvest_date'] ?: $pr['record_date'];
                                $formatQty = static function($q,$u) { return ($q !== null && $q !== '') ? rtrim(rtrim(number_format((float)$q,3,'.',','),'0'),'.') . ($u ? ' '.$u : '') : ''; };
                            ?>
                            <tr class="crop-row" data-sowing="" data-transplanted="<?= htmlspecialchars($pr['record_date'] ?? '', ENT_QUOTES) ?>" data-harvest="<?= htmlspecialchars($pr['harvest_date'] ?? '', ENT_QUOTES) ?>" data-recipient="" data-location="<?= htmlspecialchars($selectedLocationKey, ENT_QUOTES) ?>">
                                <td><?= htmlspecialchars($pr['bed_code'] ?? '') ?></td>
                                <td><?= !empty($pr['record_date']) ? htmlspecialchars(date('M j, Y', strtotime($pr['record_date']))) : '' ?></td>
                                <td><?= htmlspecialchars($pr['crop_name'] ?? '') ?><?php if (!empty($pr['variety'])): ?><div class="plot-record-subtext"><?= htmlspecialchars($pr['variety']) ?></div><?php endif; ?></td>
                                <td><?= htmlspecialchars($pr['crop_count'] ?? '') ?></td>
                                <td><?= !empty($pr['harvest_id']) ? htmlspecialchars($formatQty($pr['harvest_count'] ?? 1, '') ?: '1') : '' ?></td>
                                <td><?= $formatQty($pr['total_quantity'] ?? null, $pr['total_unit'] ?? null) ?></td>
                                <td><?= $formatQty($pr['sales_quantity'] ?? null, $pr['sales_unit'] ?? null) ?></td>
                                <td><?= ($pr['sales_amount'] !== null && $pr['sales_amount'] !== '') ? '₱'.number_format((float)$pr['sales_amount'],2) : '' ?></td>
                                <td><?= $formatQty($pr['farm_use_quantity'] ?? null, $pr['farm_use_unit'] ?? null) ?></td>
                                <td><?= $formatQty($pr['animal_feed_quantity'] ?? null, $pr['animal_feed_unit'] ?? null) ?></td>
                                <td><?= $formatQty($pr['rejected_quantity'] ?? null, $pr['rejected_unit'] ?? null) ?></td>
                                <td><?= $formatQty($pr['other_purposes_quantity'] ?? null, $pr['other_purposes_unit'] ?? null) ?></td>
                                <td><?= !empty($pr['harvest_date']) ? htmlspecialchars(date('M j, Y', strtotime($pr['harvest_date']))) : '' ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="action-icon edit-icon" title="Edit Crop" onclick="openEditModal('edit_crop.php?id=<?= intval($pr['crop_id']) ?>&harvest_id=<?= intval($pr['harvest_id'] ?? 0) ?>', 'Edit Crop')">
                                            <span class="material-symbols-rounded">edit</span>
                                        </button>
                                        <button type="button" class="action-icon archive-icon" title="Archive Crop" onclick="openArchiveModal('archive_crop.php?id=<?= intval($pr['crop_id']) ?>', '<?= htmlspecialchars($pr['crop_name'] ?? '', ENT_QUOTES) ?>')">
                                            <span class="material-symbols-rounded">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="14" class="no-records">No active plot production records found for <?= htmlspecialchars(ucwords(strtolower($selectedLocationKey))) ?>. Make sure the SQL migration has been imported and crops have been assigned to this plot.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            <?php else: ?>
            <div class="crop-table-wrapper">


                <table id="cropTable">


                    <thead>

                        <tr>

                            <th class="row-number-col">#</th>

                            <th>
                                RECIPIENT
                            </th>

                            <th>
                                PLOT
                            </th>

                            <th>
                                CROP
                            </th>

                            <th>
                                VARIETY
                            </th>

                            <th>
                                NO. OF<br>PLANTS
                            </th>

                            <th>
                                DATE OF<br>SOWING
                            </th>

                            <th>
                                GERMINATION<br>RATE
                            </th>

                            <th>TARGET<br>TRANSPLANT DATE</th>
                            <th>TARGET<br>AREA</th>

                            <th>
                                SEEDLINGS<br>RECEIVED
                            </th>

                            <?php if (!$isNurseryView): ?>
                                <th>DATE<br>TRANSPLANTED</th>
                            <?php endif; ?>

                            <th>
                                EXPECTED<br>HARVEST
                            </th>

                            <?php if ($isNurseryView): ?>
                                <th>PLOT<br>NUMBER</th>
                            <?php endif; ?>

                            <th>STATUS</th>

                            <?php if (!$isNurseryView): ?>
                                <th>TRANSPLANTED<br>AREA</th>
                            <?php endif; ?>

                            <?php if ($role === 'admin'): ?>

                                <th>
                                    ACTION
                                </th>

                            <?php endif; ?>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        $cropsResult &&
                        $cropsResult->num_rows > 0
                    ): ?>


                        <?php $cropRowNumber = 1; ?>
                        <?php while (
                            $row = $cropsResult->fetch_assoc()
                        ): ?>


                            <tr
                                class="crop-row"
                                data-sowing="<?= htmlspecialchars($row['date_sowing'] ?? '', ENT_QUOTES) ?>"
                                data-target-transplant="<?= htmlspecialchars($row['target_transplant_date'] ?? '', ENT_QUOTES) ?>"
                                data-transplanted="<?= htmlspecialchars($row['date_transplanted'] ?? '', ENT_QUOTES) ?>"
                                data-harvest="<?= htmlspecialchars($row['expected_harvest'] ?? '', ENT_QUOTES) ?>"
                                data-recipient="<?= htmlspecialchars($row['recipient'] ?? '', ENT_QUOTES) ?>"
                                data-location="<?= htmlspecialchars(cropLocationKey($row['transplanted_area'] ?? ''), ENT_QUOTES) ?>"
                            >

                                <td class="row-number"><?= $cropRowNumber++ ?></td>

                                <!-- RECIPIENT -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['recipient'] ?? ''
                                    ) ?>

                                </td>


                                <!-- PLOT -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['plot_name'] ?: ($row['transplanted_area'] ?? '')
                                    ) ?>

                                </td>


                                <!-- CROP -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['crop_name'] ?? ''
                                    ) ?>

                                </td>


                                <!-- VARIETY -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['variety'] ?? ''
                                    ) ?>

                                </td>


                                <!-- PLANTS -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['pcs'] ?? ''
                                    ) ?>

                                </td>


                                <!-- DATE SOWING -->

                                <td>

                                    <?php $dateOfSowing = $row['date_sowing'] ?? ''; ?>
                                    <?= !empty($dateOfSowing) ? date('m/d/y', strtotime($dateOfSowing)) : '' ?>

                                </td>


                                <!-- GERMINATION -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['germination_rate'] ?? ''
                                    ) ?>%

                                </td>


                                <td><?= !empty($row['target_transplant_date']) ? date('m/d/y', strtotime($row['target_transplant_date'])) : '' ?></td>
                                <td><?= htmlspecialchars($row['target_area'] ?? '') ?></td>


                                <!-- SEEDLINGS RECEIVED -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['seedlings_received'] ?? ''
                                    ) ?>PCS

                                </td>


                                <?php if (!$isNurseryView): ?>
                                    <!-- Actual transplant date is populated after transfer to a plot. -->
                                    <td><?= !empty($row['date_transplanted']) ? date('m/d/y', strtotime($row['date_transplanted'])) : '' ?></td>
                                <?php endif; ?>


                                <!-- EXPECTED HARVEST -->

                                <td>

                                    <?= !empty(
                                        $row['expected_harvest']
                                    )
                                        ? date(
                                            'm/d/y',
                                            strtotime(
                                                $row['expected_harvest']
                                            )
                                        )
                                        : ''
                                    ?>

                                </td>


                                <?php
                                    $recordArea = strtoupper(trim((string)($row['transplanted_area'] ?? '')));
                                    $recordBedCode = $recordArea === 'NURSERY'
                                        ? trim((string)($row['nursery_bed_label'] ?? ''))
                                        : trim((string)($row['transplanted_area'] ?? ''));
                                    $recordBedCode = strtoupper(preg_replace('/\s+/', '', $recordBedCode));
                                ?>

                                <?php if ($isNurseryView): ?>
                                    <!-- PLOT NUMBER / ASSIGNED NURSERY BED -->
                                    <td><?= htmlspecialchars($recordBedCode !== '' ? $recordBedCode : ($row['bed_code'] ?? '')) ?></td>
                                <?php endif; ?>

                                <!-- STATUS -->
                                <td>
                                    <?php
                                        $displayStatus = trim((string)($row['status'] ?? ''));
                                        if ($isNurseryView) {
                                            $targetDate = $row['target_transplant_date'] ?? '';
                                            if ($targetDate !== '' && $targetDate <= date('Y-m-d')) {
                                                $displayStatus = 'Ready for Transplant';
                                            }
                                            $statusKey = strtolower(str_replace(['-', '_'], ' ', $displayStatus));
                                            $statusMap = [
                                                'planted' => 'Growing',
                                                'growing' => 'Growing',
                                                'soon to harvest' => 'Soon to Harvest',
                                                'ready' => 'Ready to Harvest',
                                                'ready for harvest' => 'Ready to Harvest',
                                                'ready to harvest' => 'Ready to Harvest',
                                                'ready for harvesting' => 'Ready to Harvest'
                                            ];
                                            $displayStatus = $statusMap[$statusKey] ?? $displayStatus;
                                        }
                                    ?>
                                    <?= htmlspecialchars($displayStatus) ?>
                                </td>

                                <?php if (!$isNurseryView): ?>
                                    <!-- TRANSPLANTED AREA -->
                                    <td>
                                        <?= htmlspecialchars($row['transplanted_area'] ?? '') ?>
                                        <?php if ($recordBedCode !== '' && preg_match('/^[A-Z]+\d+$/i', $recordBedCode)): ?>
                                            <div class="plot-record-subtext">Bed: <?= htmlspecialchars($recordBedCode) ?></div>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>


                                <!-- ACTION -->

                                <?php if ($role === 'admin'): ?>


                                    <td>

                                        <div class="action-buttons">


                                            <?php if ((strtoupper(trim($row['transplanted_area'] ?? '')) === 'NURSERY' || strtoupper(trim($row['plot_name'] ?? '')) === 'NURSERY') && empty($row['transferred_out'])): ?>
                                                <button
                                                    type="button"
                                                    class="action-icon transfer-icon"
                                                    title="Transfer to Plot"
                                                    onclick="openEditModal('transfer_crop.php?id=<?= intval($row['crop_id']) ?>', 'Transfer to Plot')"
                                                >
                                                    <span class="material-symbols-rounded">move_up</span>
                                                </button>
                                            <?php endif; ?>

                                            <!-- EDIT -->

                                            <button
                                                type="button"
                                                class="action-icon edit-icon"
                                                title="Edit Crop"
                                                onclick="openEditModal(
                                                    'edit_crop.php?id=<?= intval($row['crop_id']) ?>',
                                                    'Edit Crop'
                                                )"
                                            >

                                                <span class="material-symbols-rounded">
                                                    edit
                                                </span>

                                            </button>


                                            <!-- ARCHIVE -->

                                            <button
                                                type="button"
                                                class="action-icon archive-icon"
                                                title="Archive Crop"
                                                onclick="openArchiveModal(
                                                    'archive_crop.php?id=<?= intval($row['crop_id']) ?>',
                                                    '<?= htmlspecialchars(
                                                        $row['crop_name'] ?? '',
                                                        ENT_QUOTES
                                                    ) ?>'
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
                                colspan="<?= $isNurseryView ? ($role === 'admin' ? '15' : '14') : ($role === 'admin' ? '16' : '15') ?>"
                                class="no-records"
                            >

                                No active crop records found.

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>

            <?php endif; ?>

        </section>


    </main>


</div>


<!-- =========================================================
     EDIT / ADD MODAL
     ========================================================= -->

<div
    class="modal-overlay"
    id="editModal"
>


    <div class="edit-modal">


        <!-- HEADER -->

        <div class="modal-header">


            <h3 id="editModalTitle">
                Record
            </h3>


            <button
                type="button"
                class="close-modal"
                onclick="closeEditModal()"
                title="Close"
            >

                <span class="material-symbols-rounded">
                    close
                </span>

            </button>


        </div>


        <!-- IFRAME -->

        <iframe
            id="editFrame"
            title="Crop form"
        ></iframe>


    </div>


</div>


<!-- =========================================================
     ARCHIVE MODAL
     ========================================================= -->

<div
    class="modal-overlay"
    id="archiveModal"
>


    <div class="delete-modal">


        <div class="delete-icon-large">

            <span class="material-symbols-rounded">
                archive
            </span>

        </div>


        <h3>
            Archive Crop?
        </h3>


        <p id="archiveMessage">

            Are you sure you want to archive this crop?

        </p>


        <div class="delete-actions">


            <button
                type="button"
                class="cancel-btn"
                onclick="closeArchiveModal()"
            >

                Cancel

            </button>


            <button
                type="button"
                class="confirm-delete-btn"
                onclick="confirmArchive()"
            >

                Archive

            </button>


        </div>


    </div>


</div>


<!-- =========================================================
     JAVASCRIPT
     ========================================================= -->

<script>


/* =========================================================
   EDIT / ADD MODAL
   ========================================================= */

function openEditModal(url, title) {

    const modal =
        document.getElementById('editModal');

    const frame =
        document.getElementById('editFrame');

    const modalTitle =
        document.getElementById('editModalTitle');


    if (
        !modal ||
        !frame ||
        !modalTitle
    ) {

        console.error(
            'Edit modal elements were not found.'
        );

        return;

    }


    modalTitle.textContent = title;

    frame.src = url;

    modal.classList.add('active');

    document.body.style.overflow = 'hidden';

}


/* =========================================================
   CLOSE EDIT / ADD MODAL
   ========================================================= */

function closeEditModal() {

    const modal =
        document.getElementById('editModal');

    const frame =
        document.getElementById('editFrame');


    if (modal) {

        modal.classList.remove('active');

    }


    if (frame) {

        frame.src = '';

    }


    document.body.style.overflow = '';

}


/* =========================================================
   ARCHIVE MODAL
   ========================================================= */

let archiveUrl = '';


function openArchiveModal(
    url,
    cropName
) {

    archiveUrl = url;


    const message =
        document.getElementById(
            'archiveMessage'
        );


    const modal =
        document.getElementById(
            'archiveModal'
        );


    if (message) {

        message.textContent =
            'Are you sure you want to archive "' +
            cropName +
            '"?';

    }


    if (modal) {

        modal.classList.add('active');

    }


    document.body.style.overflow = 'hidden';

}


/* =========================================================
   CLOSE ARCHIVE MODAL
   ========================================================= */

function closeArchiveModal() {

    const modal =
        document.getElementById(
            'archiveModal'
        );


    if (modal) {

        modal.classList.remove('active');

    }


    archiveUrl = '';

    document.body.style.overflow = '';

}


/* =========================================================
   CONFIRM ARCHIVE
   ========================================================= */

function confirmArchive() {

    if (archiveUrl) {

        window.location.href =
            archiveUrl;

    }

}


/* =========================================================
   SEARCH + FILTER
   ========================================================= */

function filterCropRecords() {

    const searchInput = document.getElementById('cropSearch');
    const dateFilter = document.getElementById('cropDateFilter');
    const staffFilter = document.getElementById('cropStaffFilter');
    const activeButton = document.querySelector('.crop-location-btn.active');

    const search = (searchInput?.value || '').toLowerCase().trim();
    const date = dateFilter?.value || '';
    const staff = (staffFilter?.value || '').toLowerCase().trim();
    const location = activeButton?.dataset.filter || 'all';

    document.querySelectorAll('.crop-row').forEach(function(row) {

        const text = row.textContent.toLowerCase();
        const recipient = (row.dataset.recipient || '').toLowerCase();

        const matchesSearch = !search || text.includes(search);
        const matchesStaff = !staff || recipient.includes(staff);

        const rowLocation = (row.dataset.location || '').toLowerCase().replace(/\s+/g, ' ').trim();
        const selectedLocation = location.toLowerCase().replace(/\s+/g, ' ').trim();
        const matchesLocation =
            location === 'all' ||
            rowLocation === selectedLocation ||
            (selectedLocation.startsWith('plot ') && rowLocation.replace(/^plot\s*/, '') === selectedLocation.replace(/^plot\s*/, ''));

        const rowDates = [
            row.dataset.sowing || '',
            row.dataset.targetTransplant || '',
            row.dataset.transplanted || '',
            row.dataset.harvest || ''
        ];

        const matchesDate =
            !date || rowDates.includes(date);

        row.style.display =
            matchesSearch &&
            matchesStaff &&
            matchesLocation &&
            matchesDate
                ? ''
                : 'none';
    });

    // Visible numbering is UI-only and always follows the current display order.
    let visibleNumber = 1;
    document.querySelectorAll('.crop-row').forEach(function(row) {
        if (row.style.display !== 'none') {
            const numberCell = row.querySelector('.row-number');
            if (numberCell) numberCell.textContent = visibleNumber++;
        }
    });
}


/* =========================================================
   LOCATION BUTTONS
   ========================================================= */

document.querySelectorAll('.crop-location-btn').forEach(function(button) {

    button.addEventListener('click', function() {

        document.querySelectorAll('.crop-location-btn').forEach(function(item) {
            item.classList.remove('active');
        });

        this.classList.add('active');
        filterCropRecords();
    });
});


/* =========================================================
   CLOSE EDIT MODAL WHEN CLICKING OUTSIDE
   ========================================================= */

const editModal =
    document.getElementById(
        'editModal'
    );


if (editModal) {

    editModal.addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                closeEditModal();

            }

        }
    );

}


/* =========================================================
   CLOSE ARCHIVE MODAL WHEN CLICKING OUTSIDE
   ========================================================= */

const archiveModal =
    document.getElementById(
        'archiveModal'
    );


if (archiveModal) {

    archiveModal.addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                closeArchiveModal();

            }

        }
    );

}


/* =========================================================
   ESC KEY
   ========================================================= */

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeEditModal();

            closeArchiveModal();

        }

    }
);


</script>


</body>

</html>
