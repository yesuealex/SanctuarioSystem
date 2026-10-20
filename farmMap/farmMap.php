<?php
require_once __DIR__ . "/../staff_access.php";
if (empty($_SESSION["user_id"]) || !in_array(sanctuario_staff_role(), ["admin", "super admin", "crops staff", "livestock staff"], true)) { http_response_code(403); exit("Access denied."); }
$mapCanEditCrops = sanctuario_staff_can_access('crops');
$mapCanEditLivestock = sanctuario_staff_can_access('livestock');
$mapCanEditAll = sanctuario_staff_is_admin();
?>
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'admin';
$adminName = $_SESSION['name'] ?? 'Admin';

$basePath = '/SanctuarioSystem/';

include '../db.php';
include '../map_special_plots.php';

/*
|--------------------------------------------------------------------------
| SUMMARY COUNTS (ACTIVE RECORDS)
|--------------------------------------------------------------------------
*/

$totalActiveCrops = 0;
$totalActiveLivestock = 0;

if ($result = $conn->query("SELECT COUNT(*) AS total FROM crops WHERE COALESCE(archived, 0) = 0 AND COALESCE(transferred_out, 0) = 0")) {
    $totalActiveCrops = (int)($result->fetch_assoc()['total'] ?? 0);
    $result->free();
}

if ($result = $conn->query("SELECT COALESCE(SUM(number), 0) AS total FROM livestocks WHERE COALESCE(archived, 0) = 0")) {
    $totalActiveLivestock = (int)($result->fetch_assoc()['total'] ?? 0);
    $result->free();
}

$plotHarvestReady = ['A' => 0, 'B' => 0, 'C' => 0, 'MNO' => 0, 'NURSERY' => 0, 'HYDRO' => 0, 'TENT_HOUSE' => 0];
/*
|--------------------------------------------------------------------------
| PLOTS
|--------------------------------------------------------------------------
*/

$plotRowsByCode = [];

$plotsRes = $conn->query("SELECT * FROM plots WHERE archived = 0");

if ($plotsRes) {
    while ($p = $plotsRes->fetch_assoc()) {
        $plotRowsByCode[$p['plot_code']] = $p;
    }
}

$plotCodeByLetter = [
    'A' => 'plot_a',
    'B' => 'plot_b',
    'C' => 'plot_c'
];

/*
|--------------------------------------------------------------------------
| CROPS / NURSERY / PLOT RECORDS (authoritative map source)
|--------------------------------------------------------------------------
|
| The records pages use nursery_crops and plot_crop_records as the
| location-specific record tables. Keep the map on the same source so the
| number of records shown in a plot is exactly the number of crop markers.
| The crops row remains the shared crop detail record.
*/
$dbBeds = [];
$bedsRes = $conn->query("SELECT bed_id, plot_id, bed_number, bed_code, map_x, map_y FROM plot_beds ORDER BY plot_id ASC, bed_number ASC");
if ($bedsRes) while ($bedRow = $bedsRes->fetch_assoc()) $dbBeds[] = $bedRow;
$normaliseCropLocation = static function ($area, $plotId = null) use ($plotCodeByLetter, $plotRowsByCode) {
    $area = trim((string)$area);
    $compact = preg_replace('/\s+/', '', $area);
    $lower = strtolower($compact);
    if ($lower === 'nursery') return 'NURSERY';
    if ($lower === 'mno') return 'MNO';
    if ($lower === 'hydro') return 'HYDRO';
    if ($lower === 'tent_house' || $lower === 'tent-house' || $lower === 'tent') return 'TENT_HOUSE';
    if (preg_match('/^(?:plot_)?([abc])(?:[0-9]+)?$/i', $compact, $m)) return strtoupper($m[1]);
    if ($plotId !== null) foreach ($plotCodeByLetter as $letter => $code) {
        if ((int)($plotRowsByCode[$code]['plot_id'] ?? 0) === (int)$plotId) return $letter;
    }
    return '';
};
// Fill missing record-to-bed links from the crop's selected bed. Explicit links are never overwritten.
$conn->query("UPDATE plot_crop_records pcr INNER JOIN crops c ON c.crop_id=pcr.crop_id SET pcr.bed_id=c.bed_id WHERE COALESCE(pcr.archived,0)=0 AND pcr.bed_id IS NULL AND c.bed_id IS NOT NULL");
$conn->query("UPDATE plot_crop_records pcr INNER JOIN crops c ON c.crop_id=pcr.crop_id INNER JOIN plot_beds pb ON pb.plot_id=pcr.plot_id AND UPPER(TRIM(pb.bed_code))=UPPER(TRIM(c.transplanted_area)) SET pcr.bed_id=pb.bed_id WHERE COALESCE(pcr.archived,0)=0 AND pcr.bed_id IS NULL");
$conn->query("UPDATE crops c INNER JOIN plot_crop_records pcr ON pcr.crop_id=c.crop_id SET c.bed_id=pcr.bed_id WHERE COALESCE(c.archived,0)=0 AND c.bed_id IS NULL AND pcr.bed_id IS NOT NULL");
$cropsByLocation = [];
$recordTablesAvailable = false;
$plotRecordsRes = $conn->query("\n    SELECT pcr.record_id, pcr.crop_id, pcr.plot_id, COALESCE(pcr.bed_id,c.bed_id) AS bed_id,\n           COALESCE(NULLIF(pcr.crop_name,''),c.crop_name) AS crop_name,\n           COALESCE(NULLIF(pcr.variety,''),c.variety) AS variety, c.recipient, c.pcs,\n           c.date_sowing, COALESCE(pcr.germination_rate,c.germination_rate) AS germination_rate,\n           COALESCE(pcr.date_transplanted,c.date_transplanted) AS date_transplanted,\n           COALESCE((SELECT MAX(h.harvest_date) FROM plot_crop_harvests h INNER JOIN plot_crop_records hr ON hr.record_id=h.record_id WHERE hr.crop_id=pcr.crop_id), COALESCE(pcr.expected_harvest,c.expected_harvest)) AS expected_harvest,\n           c.target_transplant_date, c.target_area, c.seedlings_received, c.image,\n           c.transplanted_area, c.status, p.plot_code, p.plot_name, pb.bed_code, pb.bed_number\n    FROM plot_crop_records pcr INNER JOIN crops c ON c.crop_id=pcr.crop_id\n    INNER JOIN plots p ON p.plot_id=pcr.plot_id\n    LEFT JOIN plot_beds pb ON pb.bed_id=COALESCE(pcr.bed_id,c.bed_id)\n    WHERE COALESCE(pcr.archived,0)=0 AND COALESCE(c.archived,0)=0 AND COALESCE(c.transferred_out,0)=0\n    ORDER BY pcr.record_id ASC\n");
$nurseryRecordsRes = $conn->query("\n    SELECT c.crop_id AS record_id, c.crop_id, c.plot_id,
           COALESCE(ncBed.bed_id, cropBed.bed_id, c.bed_id) AS bed_id,
           c.crop_name, c.variety, c.recipient, c.pcs, c.date_sowing, c.germination_rate,
           c.date_transplanted, COALESCE((SELECT MAX(h.harvest_date) FROM plot_crop_harvests h INNER JOIN plot_crop_records hr ON hr.record_id=h.record_id WHERE hr.crop_id=c.crop_id), c.expected_harvest) AS expected_harvest, c.target_transplant_date, c.target_area,
           c.seedlings_received, c.image, 'NURSERY' AS transplanted_area, c.status,
           'nursery' AS plot_code, 'Nursery' AS plot_name,
           COALESCE(cropBed.bed_code, ncBed.bed_code, NULLIF(TRIM(nc.nursery_bed_label),''),
                    CASE WHEN UPPER(REPLACE(TRIM(COALESCE(c.transplanted_area,'')),' ','')) REGEXP '^N(URSERY)?[0-9]+$'
                         THEN UPPER(REPLACE(TRIM(c.transplanted_area),' ','')) ELSE NULL END) AS bed_code,
           COALESCE(ncBed.bed_number, cropBed.bed_number) AS bed_number
    FROM crops c
    LEFT JOIN nursery_crops nc ON nc.crop_id=c.crop_id AND COALESCE(nc.archived,0)=0
    LEFT JOIN plot_beds ncBed ON ncBed.plot_id=c.plot_id AND UPPER(TRIM(ncBed.bed_code))=UPPER(TRIM(nc.nursery_bed_label))
    LEFT JOIN plot_beds cropBed ON cropBed.plot_id=c.plot_id AND cropBed.bed_id=c.bed_id
    WHERE COALESCE(c.archived,0)=0 AND COALESCE(c.transferred_out,0)=0
      AND (c.plot_id IN (SELECT plot_id FROM plots WHERE LOWER(TRIM(plot_code))='nursery') OR UPPER(TRIM(COALESCE(c.transplanted_area,'')))='NURSERY'
           OR LOWER(TRIM(COALESCE(c.plot_name,'')))='nursery')
    ORDER BY c.crop_id ASC
");
if ($plotRecordsRes && $nurseryRecordsRes) {
    $recordTablesAvailable = true;
    while ($row = $plotRecordsRes->fetch_assoc()) {
        $location = $normaliseCropLocation($row['plot_code'] ?? $row['transplanted_area'] ?? '', $row['plot_id'] ?? null);
        if ($location !== '' && $location !== 'NURSERY') $cropsByLocation[$location][] = $row;
    }
    while ($row = $nurseryRecordsRes->fetch_assoc()) $cropsByLocation['NURSERY'][] = $row;
}
// MNO has no separate record table in the current schema; retain its active crop rows.
if (!$recordTablesAvailable) {
    $legacyCropsRes = $conn->query("SELECT * FROM crops WHERE COALESCE(archived,0)=0 AND COALESCE(transferred_out,0)=0 ORDER BY crop_id ASC");
    if ($legacyCropsRes) while ($row = $legacyCropsRes->fetch_assoc()) {
        $location = $normaliseCropLocation($row['transplanted_area'] ?? '', $row['plot_id'] ?? null);
        if ($location !== '') $cropsByLocation[$location][] = $row;
    }
} else {
    $mnoRes = $conn->query("SELECT * FROM crops WHERE COALESCE(archived,0)=0 AND COALESCE(transferred_out,0)=0 AND (UPPER(TRIM(COALESCE(transplanted_area,'')))='MNO' OR plot_id IN (SELECT plot_id FROM plots WHERE LOWER(plot_code)='mno')) ORDER BY crop_id ASC");
    if ($mnoRes) while ($row = $mnoRes->fetch_assoc()) $cropsByLocation['MNO'][] = $row;
}
$dbCropsByPlot = [
    'A' => array_values($cropsByLocation['A'] ?? []),
    'B' => array_values($cropsByLocation['B'] ?? []),
    'C' => array_values($cropsByLocation['C'] ?? []),
    'NURSERY' => array_values($cropsByLocation['NURSERY'] ?? []),
    'MNO' => array_values($cropsByLocation['MNO'] ?? []),
    'HYDRO' => array_values($cropsByLocation['HYDRO'] ?? []),
    'TENT_HOUSE' => array_values($cropsByLocation['TENT_HOUSE'] ?? [])
];
foreach ($dbCropsByPlot as $location => $rows) foreach ($rows as $row) {
    $status = strtolower(trim((string)($row['status'] ?? '')));
    $ready = in_array($status, ['ready','ready for harvest'], true);
    if (!$ready && !empty($row['expected_harvest'])) $ready = (string)$row['expected_harvest'] <= date('Y-m-d') && $status !== 'harvested';
    if ($ready && array_key_exists($location, $plotHarvestReady)) $plotHarvestReady[$location]++;
}
/*
|--------------------------------------------------------------------------
| LIVESTOCK HOUSES + LIVESTOCK (grouped by house, active only)
|--------------------------------------------------------------------------
*/

$houseRowsByCode = [];

$housesRes = $conn->query("SELECT * FROM livestock_houses WHERE archived = 0");

if ($housesRes) {
    while ($h = $housesRes->fetch_assoc()) {
        $houseRowsByCode[$h['house_code']] = $h;
    }
}

$livestocksByHouseId = [];
$unassignedLivestocks = [];

$liveRes = $conn->query("
    SELECT *
    FROM livestocks
    WHERE COALESCE(archived, 0) = 0
    ORDER BY livestock_id ASC
");

if ($liveRes) {
    while ($l = $liveRes->fetch_assoc()) {
        if (!empty($l['house_id'])) {
            $livestocksByHouseId[(int)$l['house_id']][] = $l;
        } else {
            $unassignedLivestocks[] = $l;
        }
    }
}

/*
 * IMPORTANT: livestock houses display only records explicitly assigned to
 * that house through livestocks.house_id. Do not infer house membership
 * from livestock type; otherwise the same chicken/pig/goat/rabbit can appear
 * in more than one house.
 */
$dbLivestockHouses = [];

foreach ($houseRowsByCode as $code => $h) {
    $animals = $livestocksByHouseId[(int)$h['house_id']] ?? [];

    $dbLivestockHouses[$code] = [
        'house'   => $h,
        'animals' => array_values($animals)
    ];
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

    <title>Farm Map | Sanctuario Nature Farm</title>

    <!-- POPPINS -->
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- FONT AWESOME -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <!-- SIDEBAR -->
    <link
        rel="stylesheet"
        href="<?= $basePath ?>sidebar.css"
    >

    <!-- FARM MAP CSS -->
    <link
        rel="stylesheet"
        href="<?= $basePath ?>farmMap/farmMap.css?v=20261007-crop-icons-png"
    >

    <!-- CROP LIFECYCLE GANTT (ADD-ON) -->
    <link
        rel="stylesheet"
        href="<?= $basePath ?>farmMap/farmMap_lifecycle.css?v=20261016-lifecycle-milestones"
    >


<style>

/* =========================================================
   MAP EDIT TOOLBAR
========================================================= */

.map-edit-toolbar-wrapper {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
}

.map-edit-toolbar {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
}

.map-edit-btn,
.map-edit-controls button {
    border: none;
    padding: 9px 15px;
    border-radius: 7px;
    background: #6b8e23;
    color: white;
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition:
        background-color .2s ease,
        transform .15s ease,
        opacity .2s ease;
}

.map-edit-btn:hover,
.map-edit-controls button:hover {
    transform: translateY(-1px);
    opacity: .92;
}

.map-edit-btn.editing {
    background: #8b6f35;
}

.map-edit-controls {
    display: none;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.map-edit-controls.show {
    display: flex;
}

.map-edit-status {
    display: inline-flex;
    align-items: center;
    min-height: 30px;
    padding: 0 7px;
    font-size: 11px;
    font-weight: 600;
    color: rgba(255,255,255,.9);
}


/* =========================================================
   MAP EDIT MODE
========================================================= */

.map-container.edit-mode {
    cursor: crosshair;
}

.map-container.edit-mode .editable-marker {
    cursor: grab;
}

.map-container.edit-mode .editable-marker:hover {
    filter:
        drop-shadow(
            0 0 6px rgba(95,143,69,.8)
        );
}

.map-container.edit-mode .editable-marker:active {
    cursor: grabbing;
}

.map-container.edit-mode .editable-marker::after {
    content: "⋮";

    position: absolute;

    top: -8px;
    right: -8px;

    width: 18px;
    height: 18px;

    border-radius: 50%;

    background: #5f8f45;
    color: white;

    font-size: 13px;
    font-weight: bold;

    display: flex;
    align-items: center;
    justify-content: center;

    z-index: 100;
}


/* =========================================================
   NEW MAP MARKERS
========================================================= */

.new-marker {
    z-index: 50 !important;
}

.new-map-icon {
    width: 38px;
    height: 38px;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    background: white;

    border: 3px solid #5f8f45;

    color: #5f8f45;

    box-shadow:
        0 3px 10px rgba(0,0,0,.2);
}

.new-map-icon i {
    font-size: 17px;
}


/* =========================================================
   BED MARKERS
========================================================= */

.popup-map {
    position: relative;
    overflow: hidden;
}

/* =========================================================
   IMAGE CROP MARKERS
========================================================= */

.bed-marker.crop-image-marker {

    width: 50px;
    height: 50px;

    padding: 0;

    background: transparent;

    border: none;

    border-radius: 0;

    box-shadow: none;

    overflow: visible;
}

.bed-marker.crop-image-marker:hover {

    transform:
        translate(-50%, -50%)
        scale(1.1);

}

.bed-marker.crop-image-marker img {

    width: 50px;
    height: 50px;

    display: block;

    object-fit: contain;

    pointer-events: none;

    user-select: none;

}


/* Keep bed number available as a small badge */

.bed-marker.crop-image-marker .crop-bed-number {

    position: absolute;

    right: -6px;
    bottom: -5px;

    min-width: 16px;
    height: 16px;

    padding: 0 3px;

    border-radius: 50%;

    background: #5f8f45;


    border: 2px solid #ffffff;

    font-size: 10px;
    font-weight: 700;

    line-height: 12px;

    display: flex;
    align-items: center;
    justify-content: center;

}


.bed-marker.crop-image-marker .harvest-alert-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    z-index: 4;
    width: 12px;
    height: 12px;
    min-width: 12px;
    padding: 0;
    border: 2px solid #fff;
    border-radius: 50%;
    background: #d62828;
    box-shadow: 0 1px 5px rgba(91, 20, 20, .3);
    pointer-events: none;
}
@keyframes harvestAlertPulse {
    0%, 100% { box-shadow: 0 1px 5px rgba(91, 20, 20, .3); }
    50% { box-shadow: 0 0 0 4px rgba(214, 40, 40, .14), 0 1px 5px rgba(91, 20, 20, .3); }
}

/* =========================================================
   LIVESTOCK HOUSE EDIT CONTROLS
========================================================= */
.livestock-house-toolbar {
    margin-top: 12px;
}
.livestock-house-toolbar > button,
.livestock-house-edit-actions button {
    border: none;
    padding: 8px 12px;
    border-radius: 8px;
    background: #6b8e23;
    color: #fff;
    font-family: 'Poppins', sans-serif;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: .2s ease;
}
.livestock-house-toolbar button:hover {
    transform: translateY(-1px);
    opacity: .92;
}
.livestock-house-edit-actions {
    display: none;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 8px;
}
.livestock-house-toolbar.is-editing .livestock-house-edit-actions {
    display: flex;
}
.livestock-house-toolbar .delete-bed {
    background: #b84a4a;
}
.livestock-house-toolbar .position-active {
    background: #315d2e;
}
.livestock-house-marker.position-editing { cursor: grab; }
.livestock-house-marker.position-editing:active { cursor: grabbing; }
.livestock-house-marker.selected { outline: 3px solid rgba(95,143,69,.3); }


/* =========================================================
   IMAGE LIVESTOCK MARKERS
========================================================= */

.livestock-house-marker.livestock-image-marker {

    width: 46px;
    height: 46px;

    padding: 0;

    background: transparent;

    border: none;

    border-radius: 0;

    box-shadow: none;

    overflow: visible;
}

.livestock-house-marker.livestock-image-marker:hover {

    transform:
        translate(-50%,-50%)
        scale(1.1);

}

.livestock-house-marker.livestock-image-marker img {

    width: 38px;
    height: 38px;

    object-fit: contain;

    display: block;

    pointer-events: none;

    user-select: none;

}


/* =========================================================
   ICON CHOOSER
   (selectors are prefixed with .marker-icon-chooser so they
    win over the generic ".crop-input-group label" and
    ".livestock-input-group label" rules)
========================================================= */

.marker-icon-chooser {

    grid-column: 1 / -1;

}

.marker-icon-chooser .marker-icon-chooser-label {

    display: flex;
    align-items: center;
    gap: 6px;

    margin: 0 0 5px;

    color: #3f4f3a;

    font-size: 9px;
    font-weight: 700;
    letter-spacing: .3px;

}

.marker-icon-chooser .marker-icon-chooser-label::before {

    content: "";

    width: 3px;
    height: 10px;

    border-radius: 3px;

    background: linear-gradient(180deg, #8fb339, #6b8e23);

}

.marker-icon-chooser > .marker-icon-options {

    display: grid;

    grid-template-columns:
        repeat(auto-fill, minmax(58px, 1fr));

    gap: 5px;

    max-height: 158px;

    padding: 6px;

    overflow-y: auto;

    border: 1px solid #e1e8dc;
    border-radius: 10px;

    background:
        linear-gradient(180deg, #fafcf8, #f3f7ef);

    box-shadow:
        inset 0 1px 3px rgba(60,80,40,.06);

    scrollbar-width: thin;
    scrollbar-color: #b9c9ae transparent;

}

.marker-icon-chooser > .marker-icon-options::-webkit-scrollbar {

    width: 6px;

}

.marker-icon-chooser > .marker-icon-options::-webkit-scrollbar-thumb {

    border-radius: 6px;

    background: #b9c9ae;

}

/* extra icons that sit in a nested options grid */
.marker-icon-chooser .marker-icon-options .marker-icon-options {

    grid-column: 1 / -1;

    display: grid;

    grid-template-columns:
        repeat(auto-fill, minmax(58px, 1fr));

    gap: 5px;

}

.marker-icon-chooser .marker-icon-option {

    position: relative;

    min-width: 0;

}

.marker-icon-chooser .marker-icon-option input {

    position: absolute;

    opacity: 0;

    pointer-events: none;

}

.marker-icon-chooser .marker-icon-option label {

    position: relative;

    box-sizing: border-box;

    min-height: 56px;

    margin: 0;
    padding: 5px 3px 4px;

    border: 1.5px solid #e1e8dc;
    border-radius: 9px;

    background: #ffffff;

    cursor: pointer;

    display: flex;

    flex-direction: column;

    align-items: center;
    justify-content: center;

    gap: 3px;

    transition:
        border-color .18s ease,
        background .18s ease,
        box-shadow .18s ease,
        transform .18s ease;

}

.marker-icon-chooser .marker-icon-option label:hover {

    border-color: #a8bd9e;

    transform: translateY(-1px);

    box-shadow:
        0 6px 14px rgba(70,95,45,.14);

}

.marker-icon-chooser .marker-icon-option input:focus-visible + label {

    outline: 2px solid #6b8e23;
    outline-offset: 2px;

}

.marker-icon-chooser .marker-icon-option input:checked + label {

    border-color: #6b8e23;

    background:
        linear-gradient(180deg, #f6faf0, #e8f1dc);

    box-shadow:
        0 0 0 3px rgba(107,142,35,.16),
        0 6px 14px rgba(70,95,45,.16);

}

/* check badge on the selected icon */
.marker-icon-chooser .marker-icon-option input:checked + label::after {

    content: "\2713";

    position: absolute;
    top: -5px;
    right: -5px;

    width: 14px;
    height: 14px;

    border: 2px solid #ffffff;
    border-radius: 50%;

    background: #6b8e23;
    color: #ffffff;

    font-size: 8px;
    font-weight: 700;
    line-height: 10px;
    text-align: center;

    box-shadow: 0 2px 5px rgba(0,0,0,.2);

}

.marker-icon-chooser .marker-icon-option input:disabled + label {

    opacity: .5;

    cursor: not-allowed;

    transform: none;

    box-shadow: none;

}

.marker-icon-chooser .marker-icon-option img {

    width: 28px;
    height: 28px;

    padding: 2px;

    box-sizing: content-box;

    border-radius: 50%;

    background: #f1f6ed;

    object-fit: contain;

    display: block;

    transition:
        transform .18s ease,
        background .18s ease;

}

.marker-icon-chooser .marker-icon-option label:hover img {

    transform: scale(1.08);

}

.marker-icon-chooser .marker-icon-option input:checked + label img {

    background: #ffffff;

    transform: scale(1.08);

}

.marker-icon-chooser .marker-icon-option span {

    display: block;

    max-width: 100%;

    overflow: hidden;

    color: #52604d;

    font-size: 8.5px;
    font-weight: 600;

    text-align: center;
    text-overflow: ellipsis;
    white-space: nowrap;

}

.marker-icon-chooser .marker-icon-option input:checked + label span {

    color: #3d5a12;

    font-weight: 700;

}

.marker-position-row {

    grid-column: 1 / -1;

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0,1fr));

    gap: 11px;

}


@media (max-width: 600px) {

    .marker-icon-chooser > .marker-icon-options,
    .marker-icon-chooser .marker-icon-options .marker-icon-options {

        grid-template-columns:
            repeat(4, minmax(0,1fr));

    }

}

.bed-plus-marker {
    position: absolute;
    transform: translate(-50%, -50%);
    width: 42px;
    height: 42px;
    padding: 0;
    border: 2px dashed #6b8e23;
    border-radius: 50%;
    background: rgba(255,255,255,.92);
    color: #6b8e23;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 19;
    box-sizing: border-box;
    transition:
        transform .15s ease,
        background .15s ease,
        border-color .15s ease,
        color .15s ease;
}

.bed-plus-marker:hover {
    transform: translate(-50%, -50%) scale(1.08);
    background: #f1f6ed;
    border-color: #527b42;
    color: #527b42;
}

.bed-plus-marker i {
    font-size: 17px;
    pointer-events: none;
}

.bed-plus-marker span {
    position: absolute;
    top: calc(100% + 5px);
    left: 50%;
    transform: translateX(-50%);
    white-space: nowrap;
    padding: 3px 6px;
    border-radius: 5px;
    background: #315d2e;
    color: #fff;
    font-size: 8px;
    font-weight: 600;
    opacity: 0;
    pointer-events: none;
    transition: opacity .15s ease;
}

.bed-plus-marker:hover span {
    opacity: 1;
}

.bed-marker {
    position: absolute;

    transform: translate(-50%, -50%);

    width: 40px;
    height: 40px;

    font-family: 'Poppins', sans-serif;

    font-size: 11px;
    font-weight: 700;

    display: flex;
    align-items: center;
    justify-content: center;

    cursor: pointer;

    z-index: 20;

    transition:
        transform .15s ease,
        background-color .15s ease;
}

.bed-marker:hover {
    transform:
        translate(-50%, -50%)
        scale(1.1);
}

.bed-marker.editing {
    cursor: grab;
    background: #8b6f35;
}

.bed-marker.editing:active {
    cursor: grabbing;
}

.bed-marker.selected {
    outline: 3px solid rgba(95,143,69,.3);
}


/* =========================================================
   BED EDIT TOOLBAR
========================================================= */

.bed-edit-toolbar {
    display: flex;

    align-items: right;
    justify-content: right;

    flex-wrap: wrap;

    gap: 8px;

    margin-top: 0px;
}

.bed-edit-toolbar button {
    border: none;

    padding: 8px 13px;

    border-radius: 7px;

    background: #6b8e23;

    color: white;

    font-family: 'Poppins', sans-serif;

    font-size: 12px;

    font-weight: 600;

    cursor: pointer;

    transition: .2s ease;
}

.bed-edit-toolbar button:hover {
    transform: translateY(-1px);
    opacity: .9;
}

.bed-edit-toolbar .cancel-bed-edit {
    background: #777;
}

.bed-edit-toolbar .delete-bed {
    background: #b84a4a;
}

.bed-edit-status {
    width: 100%;

    text-align: center;

    font-size: 11px;

    color: #666;

    margin-top: 2px;
}


/* =========================================================
   BED EDITING
========================================================= */

.plot-popup.bed-editing .bed-marker {
    cursor: grab;
}

.plot-popup.bed-editing .bed-marker:active {
    cursor: grabbing;
}

.plot-popup.bed-editing .popup-map {
    outline: 2px dashed rgba(95,143,69,.45);
}


/* =========================================================
   BED COUNT
========================================================= */

.bed-count-display {
    margin-top: 10px;

    font-size: 12px;

    color: #777;

    text-align: center;
}


/* =========================================================
   CROP PROFILE OVERLAY
========================================================= */

.crop-profile-overlay {
    position: fixed;

    inset: 0;

    background: rgba(0,0,0,.55);

    display: none;

    align-items: center;
    justify-content: center;

    padding: 20px;

    z-index: 110000 !important;

    animation: cropOverlayFade .2s ease;
}

.crop-profile-overlay.show {
    display: flex;
}

@keyframes cropOverlayFade {

    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}


/* =========================================================
   CROP PROFILE POPUP
========================================================= */

.crop-profile-popup {
    width: min(720px, 100%);

    max-height: 90vh;

    overflow-y: auto;

    background: white;

    border-radius: 16px;

    box-shadow:
        0 20px 60px rgba(0,0,0,.25);

    position: relative;

    animation: cropPopupIn .2s ease;
}

@keyframes cropPopupIn {

    from {
        transform: translateY(15px) scale(.98);
        opacity: 0;
    }

    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}

/* =========================================================
   CROP PROFILE POPUP
========================================================= */

.crop-profile-overlay {
    position: fixed;
    inset: 0;
    z-index: 110000 !important;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 20px;

    background: rgba(0, 0, 0, 0.45);

    opacity: 0;
    visibility: hidden;

    transition:
        opacity 0.2s ease,
        visibility 0.2s ease;
}

.crop-profile-overlay.show {
    opacity: 1;
    visibility: visible;
}


/* =========================================================
   POPUP CONTAINER
========================================================= */

.crop-profile-popup {
    position: relative;

    width: min(520px, 100%);
    max-height: 90vh;

    overflow-y: auto;
    overflow-x: hidden;

    background: #ffffff;

    border-radius: 16px;

    box-shadow:
        0 18px 50px rgba(0, 0, 0, 0.18);

    animation: cropProfilePopupIn 0.22s ease;
}

@keyframes cropProfilePopupIn {

    from {
        opacity: 0;
        transform: translateY(10px) scale(0.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }

}


/* =========================================================
   SCROLLBAR
========================================================= */

.crop-profile-popup::-webkit-scrollbar {
    width: 6px;
}

.crop-profile-popup::-webkit-scrollbar-track {
    background: transparent;
}

.crop-profile-popup::-webkit-scrollbar-thumb {
    background: #cfd8c9;
    border-radius: 10px;
}

.crop-profile-popup::-webkit-scrollbar-thumb:hover {
    background: #b8c4b2;
}


/* =========================================================
   CROP PROFILE HEADER
========================================================= */

.crop-profile-title {
    position: relative;

    padding: 20px 60px 16px 24px;

    border-bottom: 1px solid #edf1eb;

    text-align: left;
}

.crop-profile-title h2 {
    margin: 0;

    display: flex;
    align-items: center;
    gap: 8px;

    color: #3e5d2d;

    font-size: 20px;
    font-weight: 700;

    line-height: 1.3;
}

.crop-profile-title h2 i {
    color: #6b8e23;
    font-size: 17px;
}

.crop-profile-title p {
    margin: 4px 0 0;

    color: #888;

    font-size: 12px;
    font-weight: 400;

    line-height: 1.4;
}


/* =========================================================
   CLOSE BUTTON
========================================================= */

.crop-profile-close {
    position: absolute;

    top: 50%;
    right: 18px;

    transform: translateY(-50%);

    width: 34px;
    height: 34px;

    padding: 0;

    border: 1px solid #e5e9e2;
    border-radius: 50%;

    background: #f6f8f5;
    color: #666;

    cursor: pointer;

    display: flex;
    align-items: center;
    justify-content: center;

    transition:
        background 0.2s ease,
        color 0.2s ease,
        border-color 0.2s ease;
}

.crop-profile-close:hover {
    background: #edf2e9;
    border-color: #d8e1d3;
    color: #3e5d2d;
}

.crop-profile-close i {
    font-size: 14px;
}


/* =========================================================
   IMAGE SECTION
========================================================= */

.crop-profile-image-section {
    padding: 18px 24px 4px;

    display: flex;
    justify-content: center;
}

.crop-profile-image-section > div {
    width: 100%;

    display: flex;
    flex-direction: column;
    align-items: center;
}


/* Image wrapper */

.crop-profile-image-wrapper {
    position: relative;

    width: 210px;
    height: 160px;

    overflow: hidden;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #f1f5ee;

    border: 1px solid #dce5d5;
    border-radius: 12px;
}


/* Image */

.crop-profile-image {
    width: 100%;
    height: 100%;

    display: block;

    object-fit: cover;
}


/* Placeholder */

.crop-image-placeholder {
    width: 100%;
    height: 100%;

    display: flex;
    flex-direction: column;

    align-items: center;
    justify-content: center;

    gap: 7px;

    color: #8b9d80;

    text-align: center;
}

.crop-image-placeholder i {
    font-size: 34px;
}

.crop-image-placeholder span {
    font-size: 10px;
    color: #8a9584;
}


/* =========================================================
   IMAGE ACTIONS
========================================================= */

.crop-image-actions {
    display: flex;
    align-items: center;
    justify-content: center;

    gap: 8px;

    margin-top: 10px;
}

.crop-image-btn {
    border: none;
    border-radius: 7px;

    padding: 8px 12px;

    background: #6b8e23;
    color: #ffffff;

    font-family: 'Poppins', sans-serif;
    font-size: 10px;
    font-weight: 600;

    cursor: pointer;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 6px;

    transition:
        background 0.2s ease,
        transform 0.15s ease;
}

.crop-image-btn:hover {
    background: #5e7e1f;
}

.crop-image-btn:active {
    transform: scale(0.97);
}

.crop-image-btn i {
    font-size: 10px;
}


/* Remove */

.crop-image-btn.remove {
    background: #b84a4a;
}

.crop-image-btn.remove:hover {
    background: #a63f3f;
}


/* =========================================================
   PROFILE CONTENT
========================================================= */

.crop-profile-content {
    padding: 18px 24px 24px;
}


/* =========================================================
   DETAILS GRID
========================================================= */

.crop-profile-grid {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 9px;
}


/* Individual field */

.crop-profile-field {
    min-width: 0;

    padding: 10px 12px;

    background: #f8faf7;

    border: 1px solid #e7ede3;
    border-radius: 9px;
}

.crop-profile-field span {
    display: block;

    margin-bottom: 4px;

    color: #858b81;

    font-size: 9px;
    font-weight: 500;

    line-height: 1.3;
}

.crop-profile-field strong {
    display: block;

    color: #35472e;

    font-size: 12px;
    font-weight: 600;

    line-height: 1.4;

    word-break: break-word;
}


/* =========================================================
   STATUS
========================================================= */

.crop-status {
    width: fit-content;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 4px 9px;

    border-radius: 20px;

    background: #e9f1e4;
    color: #4f7039;

    font-size: 9px;
    font-weight: 700;

    white-space: nowrap;
}

.crop-status.planted {
    background: #e8f0df;
    color: #55783b;
}

.crop-status.growing {
    background: #e5f0e9;
    color: #427153;
}

.crop-status.ready {
    background: #f6edcf;
    color: #856a20;
}

.crop-status.harvested {
    background: #e5e5e5;
    color: #666;
}

.crop-status.failed {
    background: #f3dfdf;
    color: #a34d4d;
}


/* =========================================================
   CROP TIMELINE
========================================================= */

.crop-timeline-box {
    margin-top: 14px;

    padding: 14px;

    background: #f5f8f2;

    border: 1px solid #e1e9db;
    border-radius: 10px;
}


/* Timeline heading */

.crop-timeline-title {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 10px;

    margin-bottom: 10px;
}

.crop-timeline-title strong {
    color: #3e5d2d;

    font-size: 12px;
    font-weight: 700;
}

.crop-timeline-title span {
    color: #7c8577;

    font-size: 10px;

    white-space: nowrap;
}


/* Progress */

.crop-progress-track {
    width: 100%;
    height: 8px;

    overflow: hidden;

    background: #dfe7d9;

    border-radius: 20px;
}

.crop-progress-bar {
    width: 0%;
    height: 100%;

    background: #6b8e23;

    border-radius: 20px;

    transition: width 0.3s ease;
}


/* Timeline statistics */

.crop-timeline-stats {
    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 8px;

    margin-top: 11px;
}

.crop-time-stat {
    text-align: center;
}

.crop-time-stat span {
    display: block;

    margin-bottom: 2px;

    color: #7c8577;

    font-size: 9px;
}

.crop-time-stat strong {
    display: block;

    color: #3e5d2d;

    font-size: 12px;
    font-weight: 700;
}


/* =========================================================
   EDIT FORM
========================================================= */



.crop-transplant-btn { background: #3c7d8f; color: #fff; }
.crop-transplant-btn:hover { background: #2f6675; }
.farm-map-transfer-overlay { position: fixed; inset: 0; z-index: 120000; display: none; align-items: center; justify-content: center; padding: 18px; background: rgba(0,0,0,.58); }
.farm-map-transfer-overlay.show { display: flex; }
.farm-map-transfer-popup { position: relative; width: min(620px, 100%); height: min(760px, 94vh); overflow: hidden; background: #fff; border-radius: 14px; box-shadow: 0 18px 55px rgba(0,0,0,.28); }
.farm-map-transfer-popup iframe { width: 100%; height: 100%; border: 0; display: block; }
.farm-map-transfer-close { position: absolute; top: 10px; right: 12px; z-index: 2; width: 32px; height: 32px; border: 0; border-radius: 50%; background: rgba(255,255,255,.92); color: #38513a; font-size: 20px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,.12); }

.crop-harvest-form h3 { margin: 0 0 12px; color: #3e5d2d; font-size: 14px; }
.crop-harvest-btn { background: #d28a2d; color: #fff; }
.crop-harvest-btn:hover { background: #b87521; }
.crop-harvest-save-btn { background: #527b42; color: #fff; }

.crop-edit-form {
    display: none;

    margin-top: 14px;

    padding: 14px;

    background: #fafbf9;

    border: 1px solid #e3e9df;
    border-radius: 10px;
}

.crop-edit-form.show {
    display: block;
}


/* Edit grid */

.crop-edit-grid {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 11px;
}


/* Input group */

.crop-input-group {
    min-width: 0;
}

.crop-input-group label {
    display: block;

    margin-bottom: 5px;

    color: #666;

    font-size: 9px;
    font-weight: 600;
}

.crop-input-group input,
.crop-input-group select {
    width: 100%;

    box-sizing: border-box;

    padding: 8px 9px;

    border: 1px solid #d9e0d5;
    border-radius: 7px;

    outline: none;

    background: #ffffff;
    color: #333;

    font-family: 'Poppins', sans-serif;
    font-size: 11px;

    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.crop-input-group input:focus,
.crop-input-group select:focus {
    border-color: #6b8e23;

    box-shadow:
        0 0 0 2px rgba(107, 142, 35, 0.1);
}


/* =========================================================
   PROFILE ACTION BUTTONS
========================================================= */

.crop-profile-actions {
    display: flex;

    justify-content: flex-end;
    align-items: center;

    gap: 8px;

    margin-top: 16px;
}

.crop-profile-actions button {
    border: none;
    border-radius: 7px;

    padding: 9px 14px;

    font-family: 'Poppins', sans-serif;
    font-size: 10px;
    font-weight: 600;

    cursor: pointer;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 6px;

    transition:
        background 0.2s ease,
        transform 0.15s ease;
}

.crop-profile-actions button:hover {
    opacity: 0.92;
}

.crop-profile-actions button:active {
    transform: scale(0.97);
}


/* Edit */

.crop-edit-btn {
    background: #6b8e23;
    color: #ffffff;
}

.crop-edit-btn:hover {
    background: #5e7e1f;
}


/* Save */

.crop-save-btn {
    background: #527b42;
    color: #ffffff;
}

.crop-save-btn:hover {
    background: #456b37;
}


/* Cancel */

.crop-cancel-btn {
    background: #777;
    color: #ffffff;
}

.crop-cancel-btn:hover {
    background: #666;
}



/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 600px) {

    .crop-profile-overlay {
        padding: 12px;
    }

    .crop-profile-popup {
        width: 100%;
        max-height: 94vh;

        border-radius: 14px;
    }

    .crop-profile-title {
        padding: 18px 55px 14px 18px;
    }

    .crop-profile-close {
        top: 15px;
        right: 15px;
    }

    .crop-profile-image-section {
        padding: 16px 18px 4px;
    }

    .crop-profile-content {
        padding: 16px 18px 20px;
    }

    .crop-profile-grid {
        grid-template-columns: 1fr;
    }

    .crop-edit-grid {
        grid-template-columns: 1fr;
    }

    .crop-timeline-stats {
        grid-template-columns: 1fr 1fr 1fr;
    }

    .crop-profile-actions {
        justify-content: stretch;
    }

    .crop-profile-actions button {
        flex: 1;
    }

}


@media (max-width: 400px) {

    .crop-profile-image-wrapper {
        width: 190px;
        height: 145px;
    }

    .crop-image-actions {
        flex-wrap: wrap;
    }

    .crop-image-btn {
        flex: 1;
    }

}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 600px) {

    .crop-profile-grid,
    .crop-edit-grid {
        grid-template-columns: 1fr;
    }

    .crop-profile-header {
        padding: 16px;
    }

    .crop-profile-content {
        padding: 16px;
    }

    .crop-profile-image-section {
        padding-left: 16px;
        padding-right: 16px;
    }

    .crop-profile-popup {
        max-height: 94vh;
    }

    .crop-timeline-stats {
        grid-template-columns: 1fr;
    }
}



/* =========================================================
   LIVESTOCK HOUSE POPUP
========================================================= */

.livestock-house-map .house-image-empty-message {
    display: none;
    position: absolute;
    inset: 0;
    align-items: center;
    justify-content: center;
    padding: 20px;
    text-align: center;
    color: #687562;
    font-size: 13px;
    background: #edf2e9;
}
.livestock-house-map.house-image-unavailable .house-image-empty-message { display: flex; }
.livestock-house-map.house-image-unavailable #livestockHouseImage { display: none; }

.livestock-overlay {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0,0,0,.45);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity .2s ease, visibility .2s ease;
}

.livestock-overlay.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.livestock-popup {
    position: relative;
    width: min(850px, 95vw);
    max-height: 92vh;
    overflow-y: auto;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,.28);
    animation: livestockPopupIn .22s ease;
}

@keyframes livestockPopupIn {
    from { opacity: 0; transform: translateY(12px) scale(.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.livestock-house-map {
    position: relative;
    width: 100%;
    height: 460px;
    overflow: hidden;
    background: #edf2e9;
}

.livestock-house-map > img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    pointer-events: none;
    user-select: none;
    -webkit-user-drag: none;
}

.livestock-house-marker {
    position: absolute;
    transform: translate(-50%,-50%);
    width: 38px;
    height: 38px;
    border: 3px solid #fff;
    border-radius: 50%;
    background: #315d2e;
    color: #fff;
    box-shadow: 0 3px 10px rgba(0,0,0,.32);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Poppins', sans-serif;
    font-size: 11px;
    font-weight: 700;
    z-index: 20;
    transition: transform .15s ease, background .15s ease;
}

.livestock-house-marker:hover {
    transform: translate(-50%,-50%) scale(1.12);
    background: #4f7d3a;
}

.livestock-house-marker::after {
    content: attr(data-name);
    position: absolute;
    left: 50%;
    top: -32px;
    transform: translateX(-50%);
    white-space: nowrap;
    background: #315d2e;
    color: #fff;
    padding: 4px 8px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 600;
    pointer-events: none;
}

.livestock-house-info {
    padding: 18px 22px 22px;
}

.livestock-house-info h2 {
    margin: 0;
    color: #315d2e;
    font-size: 23px;
}

.livestock-house-info p {
    margin: 4px 0 0;
    color: #777;
    font-size: 12px;
}

.livestock-count {
    display: inline-flex;
    margin-top: 10px;
    padding: 5px 10px;
    border-radius: 20px;
    background: #e8f0e3;
    color: #4f7039;
    font-size: 10px;
    font-weight: 700;
}

/* =========================================================
   LIVESTOCK PROFILE
========================================================= */

.livestock-profile-overlay {
    position: fixed;
    inset: 0;
    z-index: 11000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0,0,0,.55);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity .2s ease, visibility .2s ease;
}

.livestock-profile-overlay.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.livestock-profile-popup {
    position: relative;
    width: min(520px, 100%);
    max-height: 90vh;
    overflow-y: auto;
    overflow-x: hidden;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 18px 50px rgba(0,0,0,.22);
    animation: livestockProfileIn .22s ease;
}

@keyframes livestockProfileIn {
    from { opacity: 0; transform: translateY(10px) scale(.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.livestock-profile-title {
    position: relative;
    padding: 20px 60px 16px 24px;
    border-bottom: 1px solid #edf1eb;
}

.livestock-profile-title h2 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #3e5d2d;
    font-size: 20px;
    font-weight: 700;
}

.livestock-profile-title h2 i {
    color: #6b8e23;
    font-size: 17px;
}

.livestock-profile-title p {
    margin: 4px 0 0;
    color: #888;
    font-size: 12px;
}

.livestock-profile-close {
    position: absolute;
    top: 50%;
    right: 18px;
    transform: translateY(-50%);
    width: 34px;
    height: 34px;
    padding: 0;
    border: 1px solid #e5e9e2;
    border-radius: 50%;
    background: #f6f8f5;
    color: #666;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.livestock-profile-close:hover {
    background: #edf2e9;
    color: #3e5d2d;
}

.livestock-profile-image-section {
    padding: 18px 24px 4px;
    display: flex;
    justify-content: center;
}

.livestock-profile-image-wrapper {
    position: relative;
    width: 210px;
    height: 160px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5ee;
    border: 1px solid #dce5d5;
    border-radius: 12px;
}

.livestock-profile-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.livestock-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 7px;
    color: #8b9d80;
    text-align: center;
}

.livestock-image-placeholder i { font-size: 34px; }
.livestock-image-placeholder span { font-size: 10px; color: #8a9584; }

.livestock-image-actions {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 10px;
}

.livestock-image-btn {
    border: none;
    border-radius: 7px;
    padding: 8px 12px;
    background: #6b8e23;
    color: #fff;
    font-family: 'Poppins', sans-serif;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
}

.livestock-image-btn.remove { background: #b84a4a; }

.livestock-profile-content { padding: 18px 24px 24px; }

.livestock-profile-grid {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 9px;
}

.livestock-profile-field {
    min-width: 0;
    padding: 10px 12px;
    background: #f8faf7;
    border: 1px solid #e7ede3;
    border-radius: 9px;
}

.livestock-profile-field.full { grid-column: 1 / -1; }

.livestock-profile-field span {
    display: block;
    margin-bottom: 4px;
    color: #858b81;
    font-size: 9px;
    font-weight: 500;
}

.livestock-profile-field strong {
    display: block;
    color: #35472e;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.4;
    word-break: break-word;
}

.livestock-profile-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 16px;
}

.livestock-profile-actions button {
    border: none;
    border-radius: 7px;
    padding: 9px 14px;
    font-family: 'Poppins', sans-serif;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
}

.livestock-edit-btn { background: #6b8e23; color: #fff; }
.livestock-save-btn { background: #527b42; color: #fff; }
.livestock-cancel-btn { background: #777; color: #fff; }

.livestock-edit-form {
    display: none;
    margin-top: 14px;
    padding: 14px;
    background: #fafbf9;
    border: 1px solid #e3e9df;
    border-radius: 10px;
}

.livestock-edit-form.show { display: block; }

.livestock-edit-grid {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 11px;
}

.livestock-input-group { min-width: 0; }
.livestock-input-group.full { grid-column: 1 / -1; }

.livestock-input-group label {
    display: block;
    margin-bottom: 5px;
    color: #666;
    font-size: 9px;
    font-weight: 600;
}

.livestock-input-group input,
.livestock-input-group textarea,
.livestock-input-group select {
    width: 100%;
    box-sizing: border-box;
    padding: 8px 9px;
    border: 1px solid #d9e0d5;
    border-radius: 7px;
    outline: none;
    background: #fff;
    color: #333;
    font-family: 'Poppins', sans-serif;
    font-size: 11px;
}

.livestock-input-group textarea { min-height: 70px; resize: vertical; }

@media (max-width: 600px) {
    .livestock-overlay,
    .livestock-profile-overlay { padding: 12px; }
    .livestock-house-map { height: 350px; }
    .livestock-profile-grid,
    .livestock-edit-grid { grid-template-columns: 1fr; }
    .livestock-profile-field.full,
    .livestock-input-group.full { grid-column: auto; }
    .livestock-profile-content { padding: 16px 18px 20px; }
    .livestock-profile-title { padding: 18px 55px 14px 18px; }
    .livestock-profile-image-section { padding: 16px 18px 4px; }
}



/* =========================================================
   CLEAN FARM MAP BOARD UX
========================================================= */
.farm-map-toast {
    position: fixed;
    left: 50%;
    bottom: 24px;
    transform: translate(-50%, 16px);
    z-index: 99999;
    padding: 10px 16px;
    border-radius: 999px;
    background: rgba(32, 58, 42, .96);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    box-shadow: 0 8px 24px rgba(0,0,0,.18);
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s ease, transform .2s ease;
}
.farm-map-toast.show {
    opacity: 1;
    transform: translate(-50%, 0);
}
.bed-edit-toolbar.is-editing {
    gap: 8px;
}
#startEditBedsBtn.is-done {
    background: #527b42 !important;
    color: #fff !important;
    border-color: #527b42 !important;
    box-shadow: 0 4px 12px rgba(82,123,66,.20);
}

#startEditBedsBtn.is-done:hover {
    background: #436735 !important;
    color: #fff !important;
}

#startEditBedsBtn,
#editPositionBtn,
#deleteBedBtn,
#editSelectedCropBtn,
#saveBedsBtn,
#cancelBedsBtn {
    min-height: 36px;
    border-radius: 9px;
    font-family: inherit;
}

.bed-edit-toolbar.is-editing {
    flex-wrap: wrap;
    align-items: center;
}



/* =========================================================
   FARM MAP - FINAL LAYOUT
========================================================= */

.farm-map-layout {
    display: block !important;
    width: 100% !important;
}

.map-card {
    width: 100% !important;
    max-width: none !important;
    min-height: 0 !important;
}

.map-container {
    position: relative !important;
    width: 100% !important;
    height: auto !important;
    aspect-ratio: 1706 / 600 !important;
    min-height: 0 !important;
    overflow: hidden !important;
    cursor: grab !important;
    touch-action: none !important;
}

.main-map-layer {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    width: auto !important;
    height: auto !important;
    min-width: 0 !important;
    min-height: 0 !important;
    transform-origin: top left !important;
    will-change: transform !important;
    cursor: grab !important;
}

.farm-map-image {
    position: static !important;
    display: block !important;
    width: 100% !important;
    height: auto !important;
    max-width: none !important;
    min-width: 0 !important;
    min-height: 0 !important;
    aspect-ratio: auto !important;
    object-fit: contain !important;
    object-position: left top !important;
}

/* One setting controls all main map marker sizes. */
.main-map-layer > .main-marker {
    width: var(--main-map-marker-size, 6%) !important;
    height: var(--main-map-marker-size, 6%) !important;
    box-sizing: border-box !important;
}
.main-map-layer > .main-marker { overflow: visible !important; }
.plot-harvest-alert {
    position: absolute !important;
    top: -3px !important;
    right: -5px !important;
    transform: translateX(5px) !important;
    z-index: 30 !important;
    width: 13px !important;
    height: 13px !important;
    min-width: 13px !important;
    min-height: 13px !important;
    display: block !important;
    padding: 0 !important;
    opacity: 1 !important;
    visibility: visible !important;
    pointer-events: none !important;
    border: 2px solid #fff !important;
    border-radius: 50% !important;
    background: #d52f36 !important;
    color: transparent !important;
    font-size: 0 !important;
    line-height: 0 !important;
    box-shadow: 0 2px 6px rgba(80, 18, 18, .4) !important;
}
.plot-harvest-alert i { display: none !important; }
.main-map-layer > .main-marker > .plot-harvest-alert {
    position: absolute !important;
    top: -3px !important;
    right: -5px !important;
    transform: translateX(5px) !important;
    width: 13px !important;
    height: 13px !important;
    display: block !important;
    padding: 0 !important;
    opacity: 1 !important;
    visibility: visible !important;
    background: #d52f36 !important;
    color: transparent !important;
    border: 2px solid #fff !important;
    border-radius: 50% !important;
    box-shadow: 0 2px 6px rgba(80, 18, 18, .4) !important;
    font-size: 0 !important;
    line-height: 0 !important;
}
@keyframes plotHarvestAlertPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.14); box-shadow: 0 0 0 6px rgba(213, 47, 54, .18), 0 2px 8px rgba(80, 18, 18, .45); }
}

.main-map-layer > .main-marker .new-map-icon {
    width: 100% !important;
    height: 100% !important;
    box-sizing: border-box !important;
}

.farm-map-zoom-controls {
    position: absolute !important;
    top: 12px !important;
    right: 12px !important;
    z-index: 5000 !important;
    pointer-events: auto !important;
}

.farm-map-zoom-controls button {
    pointer-events: auto !important;
}

.map-container.is-dragging,
.main-map-layer.is-dragging {
    cursor: grabbing !important;
}

/* =========================================================
   DATABASE RECORD SUMMARY IN PLOT POPUP
========================================================= */
.plot-record-summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 9px;
    margin: 12px 0 14px;
}
.plot-record-item {
    min-width: 0;
    padding: 10px 11px;
    border: 1px solid #e4ecdf;
    border-radius: 10px;
    background: #f8faf7;
}
.plot-record-item.full {
    grid-column: 1 / -1;
}
.plot-record-item span {
    display: block;
    margin-bottom: 3px;
    color: #7b877d;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .45px;
    text-transform: uppercase;
}
.plot-record-item strong {
    display: block;
    color: #365a3c;
    font-size: 13px;
    line-height: 1.25;
}
@media (max-width: 560px) {
    .plot-record-summary {
        grid-template-columns: 1fr;
    }
    .plot-record-item.full {
        grid-column: auto;
    }
}

/* Popup is hidden until a plot marker is actually opened. */
.plot-overlay {
    display: none !important;
    position: fixed !important;
    inset: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    max-width: none !important;
    max-height: none !important;
    overflow: auto !important;
    padding: 18px !important;
    box-sizing: border-box !important;
    align-items: center !important;
    justify-content: center !important;
    z-index: 99999 !important;
}

.plot-overlay.show {
    display: flex !important;
}

.plot-overlay .plot-popup {
    width: min(850px, 96vw) !important;
    max-width: 850px !important;
    max-height: calc(100vh - 36px) !important;
    margin: auto !important;
    flex: 0 1 auto !important;
}

/* Keep the popup readable after it is moved to <body>. */
.plot-overlay .plot-popup {
    max-width: min(850px, 95vw) !important;
    max-height: 92vh !important;
    margin: auto !important;
}

@media (max-width: 600px) {
    .plot-overlay {
        padding: 10px !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .plot-overlay .plot-popup {
        width: 100% !important;
        max-width: 100% !important;
        max-height: calc(100vh - 20px) !important;
        overflow-y: auto !important;
    }
}


/* =========================================================
   READABLE FARM MAP TYPOGRAPHY
   Slightly enlarged for older users without changing layout.
========================================================= */
.farm-map-title-area h1 { font-size: 30px; }
.farm-map-title-area p { font-size: 15px; }
.summary-label { font-size: 15px; }
.summary-number { font-size: 36px; }
.map-edit-btn, .map-edit-controls button { font-size: 14px; }
.map-edit-status { font-size: 13px; }
.main-marker span { font-size: 13px; padding: 5px 9px; }
.map-hint { font-size: 14px; }
.plot-info h2 { font-size: 26px; }
.plot-info p { font-size: 15px; }
.plot-stat span { font-size: 13px; }
.plot-stat strong { font-size: 25px; }
.plant-marker { font-size: 12px; }
.plant-marker::after { font-size: 12px; padding: 4px 8px; }
.crop-profile-title h2, .livestock-profile-title h2 { font-size: 25px; }
.crop-profile-title p, .livestock-profile-title p { font-size: 14px; }
.crop-profile-field span, .livestock-profile-field span { font-size: 12px; }
.crop-profile-field strong, .livestock-profile-field strong { font-size: 14px; }
.crop-profile-actions button, .livestock-profile-actions button { font-size: 14px; }
.nursery-popup h2 { font-size: 26px; }
.nursery-popup p { font-size: 15px; }


/* Plot title and lifecycle rows stay compact and readable on one line. */
.plot-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-width: 0;
    margin-bottom: 10px;
}
.plot-title-row h3 {
    min-width: 0;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.plot-title-row #startEditBedsBtn {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    min-height: 36px;
    padding: 8px 13px;
    border: 0;
    border-radius: 9px;
    background: #6b8e23;
    color: #fff;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: .2s ease;
}
.plot-title-row #startEditBedsBtn:hover {
    transform: translateY(-1px);
    opacity: .9;
}
.plot-title-row #startEditBedsBtn.is-done {
    background: #527b42 !important;
}
@media (max-width: 520px) {
    .plot-title-row #startEditBedsBtn {
        padding: 7px 9px;
        font-size: 11px;
    }
}

/* Keep plot editing controls beside Done Editing; preserve existing button styles. */
.plot-title-row .plot-edit-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
    min-width: 0;
}
.plot-title-row .plot-edit-actions #editPositionBtn,
.plot-title-row .plot-edit-actions #deleteBedBtn,
.plot-title-row .plot-edit-actions #editSelectedCropBtn {
    border: none;
    padding: 8px 13px;
    border-radius: 9px;
    background: #6b8e23;
    color: white;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: .2s ease;
    min-height: 36px;
    align-items: center;
    justify-content: center;
    gap: 5px;
}
.plot-title-row .plot-edit-actions #deleteBedBtn { background: #b84a4a; }
.plot-title-row .plot-edit-actions #editPositionBtn:hover,
.plot-title-row .plot-edit-actions #deleteBedBtn:hover,
.plot-title-row .plot-edit-actions #editSelectedCropBtn:hover { transform: translateY(-1px); opacity: .9; }

/* Popup content scrolls independently without moving the farm map. */
.plot-popup, .nursery-popup, .crop-profile-popup, .livestock-profile-popup { overscroll-behavior: contain; }
.plot-popup .plot-info, .nursery-popup { max-height: min(42vh, 520px); overflow-y: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
@media (max-width: 700px) { .plot-popup .plot-info, .nursery-popup { max-height: 48vh; } }
</style>

<style id="sanctuario-responsive-refinement">
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

/* Farm map: fill tablet width; preserve map interaction, proportions and icon circles. */
@media (min-width: 601px) and (max-width: 1199px) {
  .farm-map-layout { grid-template-columns: minmax(0, 1fr) !important; gap: 12px; }
  .farm-map-page { padding-top: 12px; }
  .farm-summary { margin-bottom: 12px; }
  .summary-cards { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 12px; }
  .summary-card { padding: 10px 14px; min-height: 84px; }
  .summary-icon { width: clamp(42px, 5vw, 58px); height: clamp(42px, 5vw, 58px); min-width: clamp(42px, 5vw, 58px); flex: 0 0 auto; aspect-ratio: 1; }
  .summary-label { font-size: clamp(12px, 1.45vw, 15px); }
  .summary-number { font-size: clamp(25px, 3vw, 36px); }
  .map-card { width: 100%; }
  .map-container#mapContainer { width: 100% !important; }
}
@media (max-width: 600px) {
  .summary-cards { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 8px !important; }
  .summary-card { min-width: 0; padding: 10px 8px; display: flex; flex-direction: column; justify-content: center; text-align: center; gap: 4px; }
  .summary-icon { width: 38px !important; height: 38px !important; min-width: 38px !important; min-height: 38px; max-width: 38px; max-height: 38px; flex: 0 0 38px; aspect-ratio: 1 / 1; border-radius: 50%; margin: 0 auto 4px; line-height: 1; }
  .summary-icon i { display: inline-flex; align-items: center; justify-content: center; width: 1em; height: 1em; line-height: 1; font-size: 20px; transform: none; flex-shrink: 0; }
  .summary-label { display: block; font-size: clamp(10px, 2.7vw, 13px); line-height: 1.2; overflow-wrap: anywhere; }
  .summary-number { display: block; font-size: clamp(23px, 6vw, 30px); }
  .farm-map-layout { grid-template-columns: minmax(0, 1fr) !important; gap: 10px; }
  .farm-summary { margin-bottom: 12px; }
  .map-card { width: 100%; }
}

</style>
<style id="sanctuario-farm-map-permissions">
<?php if (!$mapCanEditAll): ?>
#editMapBtn, #mapEditControls { display:none !important; }
<?php endif; ?>
<?php if (!$mapCanEditCrops): ?>
#startEditBedsBtn, #bedEditToolbar, #editPositionBtn, #deleteBedBtn, #editSelectedCropBtn,
#editCropProfileBtn, #saveCropProfileBtn, #harvestCropBtn, #transplantCropBtn,
#saveCropHarvestBtn, .bed-plus-marker, .crop-image-actions { display:none !important; }
<?php endif; ?>
<?php if (!$mapCanEditLivestock): ?>
#livestockHouseToolbar, #editLivestockProfileBtn, #saveLivestockProfileBtn,
.livestock-image-actions { display:none !important; }
<?php endif; ?>
</style>
</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<?php include '../sidebar.php'; ?>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<main class="farm-map-page">


    <!-- TOP RIGHT BAR -->

    <?php include '../topRightBar.php'; ?>

    <!-- Farm Map page-only mobile alignment. Keep the shared topRightBar unchanged. -->
    <style>
    @media (max-width: 700px) {
        .farm-map-page > .dashboard-topbar {
            height: 68px !important;
            min-height: 68px !important;
            width: 100% !important;
            padding: 0 8px 0 58px !important;
            margin: 0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
            gap: 8px !important;
        }

        .farm-map-page > .dashboard-topbar .notification-btn,
        .farm-map-page > .dashboard-topbar .profile-btn {
            width: 48px !important;
            height: 48px !important;
            min-width: 48px !important;
            margin: 0 !important;
        }

        .farm-map-page > .dashboard-topbar .profile-btn {
            padding: 4px !important;
        }

        .farm-map-page > .dashboard-topbar .profile-info,
        .farm-map-page > .dashboard-topbar .profile-arrow {
            display: none !important;
        }
    }

    /* Phone map interaction hint. */
    @media (max-width: 600px) {
        .farm-map-page {
            padding-top: 0 !important;
        }

        .farm-map-page .map-container#mapContainer {
            height: clamp(360px, 52vh, 430px) !important;
            min-height: 360px !important;
            max-height: 430px !important;
            aspect-ratio: auto !important;
            overflow: hidden !important;
            touch-action: none !important;
            overscroll-behavior: contain !important;
        }

        .farm-map-page .main-map-layer {
            margin: 0 !important;
            padding: 0 !important;
            left: 0 !important;
            top: 0 !important;
        }

        .farm-map-page .farm-map-image {
            display: block !important;
            width: 100% !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            max-width: none !important;
            min-width: 0 !important;
            min-height: 0 !important;
            aspect-ratio: auto !important;
            object-fit: contain !important;
            object-position: left top !important;
        }

        .farm-map-page .map-hint {
            display: block !important;
            left: 50% !important;
            bottom: 14px !important;
            max-width: calc(100% - 24px) !important;
            padding: 8px 13px !important;
            font-size: 11px !important;
            line-height: 1.35 !important;
            text-align: center !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
    }
    </style>


    <!-- =====================================================
         SUMMARY
    ====================================================== -->

    <section class="farm-summary">

        <div class="farm-map-title-area">

            <div>

                <h1>
                    FARM MAP
                </h1>

                <p>
                    View and monitor farm plots and planted crops.
                </p>

            </div>


            <!-- MAP EDIT AREA -->

            <div class="map-edit-toolbar-wrapper">

                <button
                    type="button"
                    id="editMapBtn"
                    class="map-edit-btn"
                >

                    <i class="fa-solid fa-pen-to-square"></i>

                    Edit Map

                </button>


                <div
                    id="mapEditControls"
                    class="map-edit-controls"
                >

            

                    <button
                        type="button"
                        data-tool="move"
                    >

                        <i class="fa-solid fa-up-down-left-right"></i>

                        Move

                    </button>


                    <button
                        type="button"
                        id="saveMapBtn"
                    >

                        <i class="fa-solid fa-floppy-disk"></i>

                        Save

                    </button>


                    <button
                        type="button"
                        id="cancelMapBtn"
                    >

                        <i class="fa-solid fa-xmark"></i>

                        Cancel

                    </button>

                </div>


                <span
                    id="mapEditStatus"
                    class="map-edit-status"
                >
                    View Mode
                </span>

            </div>

        </div>


        <!-- =================================================
             SUMMARY CARDS
        ================================================== -->

        <div class="summary-cards">

            <div class="summary-card">

                <div class="summary-icon summary-green">

                    <i class="fa-solid fa-seedling"></i>

                </div>

                <div>

                    <span class="summary-label">
                        Total Crops
                    </span>

                    <strong class="summary-number">
                        <?= $totalActiveCrops ?>
                    </strong>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-icon summary-yellow">

                    <i class="fa-solid fa-cow"></i>

                </div>

                <div>

                    <span class="summary-label">
                        Total Livestocks
                    </span>

                    <strong class="summary-number" id="totalLivestockSummary">
                        <?= $totalActiveLivestock ?>
                    </strong>

                </div>

            </div>


        </div>

    </section>


    <!-- =====================================================
         MAP
    ====================================================== -->

    <section class="farm-map-layout">


        <div class="map-card">

            <div
                class="map-container"
                id="mapContainer"
            >

                <!-- FARM MAP ZOOM CONTROLS -->
                <div
                    class="farm-map-zoom-controls"
                    id="farmMapZoomControls"
                    aria-label="Farm map zoom controls"
                >
                    <button
                        type="button"
                        id="farmMapZoomIn"
                        title="Zoom in"
                        aria-label="Zoom in"
                    >+</button>

                    <button
                        type="button"
                        id="farmMapZoomReset"
                        class="zoom-reset"
                        title="Reset zoom"
                        aria-label="Reset zoom"
                    >⛶</button>

                    <button
                        type="button"
                        id="farmMapZoomOut"
                        title="Zoom out"
                        aria-label="Zoom out"
                    >−</button>
                </div>

                <div class="map-hint" aria-hidden="true">
                    Drag and Zoom the Map to interact
                </div>

                <div
                    class="main-map-layer"
                    id="mainMapLayer"
                >

                    <img
                        src="<?= $basePath ?>farm_map.jpg?v=20261013-map-cleanup"
                        id="farmMap"
                        class="farm-map-image"
                        draggable="false"
                        alt="Sanctuario Nature Farm"
                    >


                    <!-- =================================================
                         PLOT A
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker plot-marker editable-marker<?= (($plotHarvestReady['A'] ?? 0) > 0 ? ' has-harvest-alert' : '') ?>"
                        id="plot-A"
                        data-marker="plot"
                        data-id="A"
                        data-name="Plot A"
                        style="left:82.2%; top:53.4%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/plot_a.png"
                            alt="Plot A"
                        >

                        <?php if (($plotHarvestReady['A'] ?? 0) > 0): ?>
                            <span class="plot-harvest-alert" title="<?= (int)$plotHarvestReady['A'] ?> crop(s) ready for harvest" aria-label="<?= (int)$plotHarvestReady['A'] ?> crop(s) ready for harvest"></span>
                        <?php endif; ?>
                        <span>
                            Plot A
                        </span>

                    </button>


                    <!-- =================================================
                         PLOT B
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker plot-marker editable-marker<?= (($plotHarvestReady['B'] ?? 0) > 0 ? ' has-harvest-alert' : '') ?>"
                        id="plot-B"
                        data-marker="plot"
                        data-id="B"
                        data-name="Plot B"
                        style="left:72.4%; top:52%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/plot_b.png"
                            alt="Plot B"
                        >

                        <?php if (($plotHarvestReady['B'] ?? 0) > 0): ?>
                            <span class="plot-harvest-alert" title="<?= (int)$plotHarvestReady['B'] ?> crop(s) ready for harvest" aria-label="<?= (int)$plotHarvestReady['B'] ?> crop(s) ready for harvest"></span>
                        <?php endif; ?>
                        <span>
                            Plot B
                        </span>

                    </button>


                    <!-- =================================================
                         PLOT C
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker plot-marker editable-marker<?= (($plotHarvestReady['C'] ?? 0) > 0 ? ' has-harvest-alert' : '') ?>"
                        id="plot-C"
                        data-marker="plot"
                        data-id="C"
                        data-name="Plot C"
                        style="left:63.3%; top:52.8%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/plot_c.png"
                            alt="Plot C"
                        >

                        <?php if (($plotHarvestReady['C'] ?? 0) > 0): ?>
                            <span class="plot-harvest-alert" title="<?= (int)$plotHarvestReady['C'] ?> crop(s) ready for harvest" aria-label="<?= (int)$plotHarvestReady['C'] ?> crop(s) ready for harvest"></span>
                        <?php endif; ?>
                        <span>
                            Plot C
                        </span>

                    </button>


                    <!-- =================================================
                         MNO
                    ================================================== -->
                    <button
                        type="button"
                        class="main-marker plot-marker editable-marker<?= (($plotHarvestReady['MNO'] ?? 0) > 0 ? ' has-harvest-alert' : '') ?>"
                        id="plot-MNO"
                        data-marker="plot"
                        data-id="MNO"
                        data-name="MNO"
                        style="left:88%; top:36%;"
                    >
                        <img src="<?= $basePath ?>markers/mno.png" alt="MNO">
                        <?php if (($plotHarvestReady['MNO'] ?? 0) > 0): ?>
                            <span class="plot-harvest-alert" title="<?= (int)$plotHarvestReady['MNO'] ?> crop(s) ready for harvest" aria-label="<?= (int)$plotHarvestReady['MNO'] ?> crop(s) ready for harvest"></span>
                        <?php endif; ?>
                        <span>MNO</span>
                    </button>


                    <!-- =================================================
                         HYDRO
                    ================================================== -->
                    <button
                        type="button"
                        class="main-marker plot-marker editable-marker<?= (($plotHarvestReady['HYDRO'] ?? 0) > 0 ? ' has-harvest-alert' : '') ?>"
                        id="plot-HYDRO"
                        data-marker="plot"
                        data-id="HYDRO"
                        data-name="Hydro"
                        style="left:50%; top:28%;"
                    >
                        <img src="<?= $basePath ?>markers/hydro.png" alt="Hydro">
                        <?php if (($plotHarvestReady['HYDRO'] ?? 0) > 0): ?>
                            <span class="plot-harvest-alert" title="<?= (int)$plotHarvestReady['HYDRO'] ?> crop(s) ready for harvest" aria-label="<?= (int)$plotHarvestReady['HYDRO'] ?> crop(s) ready for harvest"></span>
                        <?php endif; ?>
                        <span>Hydro</span>
                    </button>


                    <!-- =================================================
                         TENT HOUSE
                    ================================================== -->
                    <button
                        type="button"
                        class="main-marker plot-marker editable-marker<?= (($plotHarvestReady['TENT_HOUSE'] ?? 0) > 0 ? ' has-harvest-alert' : '') ?>"
                        id="plot-TENT_HOUSE"
                        data-marker="plot"
                        data-id="TENT_HOUSE"
                        data-name="Tent House"
                        style="left:38%; top:28%;"
                    >
                        <img src="<?= $basePath ?>markers/tent_house.png" alt="Tent House">
                        <?php if (($plotHarvestReady['TENT_HOUSE'] ?? 0) > 0): ?>
                            <span class="plot-harvest-alert" title="<?= (int)$plotHarvestReady['TENT_HOUSE'] ?> crop(s) ready for harvest" aria-label="<?= (int)$plotHarvestReady['TENT_HOUSE'] ?> crop(s) ready for harvest"></span>
                        <?php endif; ?>
                        <span>Tent House</span>
                    </button>


                    <!-- =================================================
                         NURSERY
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker nursery-marker editable-marker"
                        id="marker-nursery"
                        data-marker="nursery"
                        data-id="nursery"
                        data-name="Nursery"
                        style="left:85.4%; top:15.8%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/nursery.png"
                            alt="Nursery"
                        >

                        <span>
                            Nursery
                        </span>

                    </button>


                    <!-- =================================================
                         RABBIT HOUSE
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker livestock-marker editable-marker"
                        id="marker-rabbit-house"
                        data-marker="livestock"
                        data-id="rabbit-house"
                        data-name="Rabbit House"
                        style="left:23.7%; top:7%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/rabbit_house.png"
                            alt="Rabbit House"
                        >

                        <span>
                            Rabbit House
                        </span>

                    </button>


                    <!-- =================================================
                         GOAT HOUSE
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker livestock-marker editable-marker"
                        id="marker-goat-house"
                        data-marker="livestock"
                        data-id="goat-house"
                        data-name="Goat House"
                        style="left:15%; top:16%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/goat_house.png"
                            alt="Goat House"
                        >

                        <span>
                            Goat House
                        </span>

                    </button>


                    <!-- =================================================
                         GOAT HOUSE 2
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker livestock-marker editable-marker"
                        id="marker-goat-house-2"
                        data-marker="livestock"
                        data-id="goat-house-2"
                        data-name="Goat House 2"
                        style="left:9.1%; top:8%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/goat_house.png"
                            alt="Goat House 2"
                        >

                        <span>
                            Goat House 2
                        </span>

                    </button>


                    <!-- =================================================
                         POULTRY HOUSE
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker livestock-marker editable-marker"
                        id="marker-poultry-house"
                        data-marker="livestock"
                        data-id="poultry-house"
                        data-name="Poultry House"
                        style="left:6.5%; top:19.5%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/poultry_house.png"
                            alt="Poultry House"
                        >

                        <span>
                            Poultry House
                        </span>

                    </button>


                    <!-- =================================================
                         SWINE HOUSE
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker livestock-marker editable-marker"
                        id="marker-swine-house"
                        data-marker="livestock"
                        data-id="swine-house"
                        data-name="Swine House"
                        style="left:12%; top:74.6%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/swine_house.png"
                            alt="Swine House"
                        >

                        <span>
                            Swine House
                        </span>

                    </button>

                </div>



                <!-- =================================================
                     PLOT POPUP
                ================================================== -->

                <div
                    class="plot-overlay"
                    id="plotOverlay"
                >

                    <div
                        class="plot-popup"
                        id="plotPopup"
                    >

                        <button
                            type="button"
                            class="popup-close"
                            id="closePlotBtn"
                        >

                            <i class="fa-solid fa-xmark"></i>

                        </button>


                        <div class="popup-map">

                            <img
                                id="plotImage"
                                src=""
                                draggable="false"
                                alt="Plot"
                            >

                            <div
                                id="bedMarkersContainer"
                            ></div>

                        </div>


                        <div class="plot-info">

                            <div class="plot-title-row">
                                <h3 id="plotTitle">
                                    Plot A
                                </h3>
                                <div class="plot-edit-actions">
<button
                                    type="button"
                                    id="editPositionBtn"
                                    style="display:none;"
                                >
                                    <i class="fa-solid fa-up-down-left-right"></i>
                                    Edit Position
                                </button>

                                <button
                                    type="button"
                                    id="deleteBedBtn"
                                    class="delete-bed"
                                    style="display:none;"
                                >
                                    <i class="fa-solid fa-trash"></i>
                                    Delete Selected
                                </button>

                                <button
                                    type="button"
                                    id="editSelectedCropBtn"
                                    style="display:none;"
                                >
                                    <i class="fa-solid fa-seedling"></i>
                                    Edit Crop
                                </button>



                                <button type="button" id="startEditBedsBtn" title="Edit this plot" aria-label="Edit this plot">
                                    <i class="fa-solid fa-pen"></i>
                                    Edit Plot
                                </button>
                                </div>
                            </div>

                            <div
                                class="plot-record-summary"
                                id="plotRecordSummary"
                            >
                                <div class="plot-record-item">
                                    <span>PLANTS PLANTED:</span>
                                    <strong id="plotSummaryPlants">0</strong>
                                </div>
                                <div class="plot-record-item">
                                    <span>BED AVAILABLE:</span>
                                    <strong id="plotSummaryBeds">0</strong>
                                </div>
                            </div>

                            <!-- DATABASE-DRIVEN MULTI-CROP GANTT: all crop records in this plot -->
                            <div id="plotCropLifecycleGantt" class="plot-crop-lifecycle-gantt" aria-live="polite"></div>

                              <!-- =================================================
                                 BED EDIT CONTROLS
                            ================================================== -->

                            <div
                                class="bed-edit-toolbar"
                                id="bedEditToolbar"
                            >
                                <button
                                    type="button"
                                    id="saveBedsBtn"
                                    style="display:none;"
                                >

                                    <i class="fa-solid fa-floppy-disk"></i>

                                    Save Beds

                                </button>


                                <button
                                    type="button"
                                    id="cancelBedsBtn"
                                    class="cancel-bed-edit"
                                    style="display:none;"
                                >

                                    <i class="fa-solid fa-xmark"></i>

                                    Cancel

                                </button>



                            </div>

                        </div>

                    </div>

                </div>

                            </div>

                          


                <!-- =================================================
                     NURSERY POPUP
                ================================================== -->

                <div
                    class="nursery-overlay"
                    id="nurseryOverlay"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="nurseryTitle"
                    tabindex="-1"
                >

                    <div class="nursery-popup">

                        <button
                            type="button"
                            class="popup-close"
                            id="closeNurseryBtn"
                        >

                            <i class="fa-solid fa-xmark"></i>

                        </button>


                        <h3 id="nurseryTitle">

                            <i class="fa-solid fa-seedling"></i>

                            Nursery

                        </h3>

                        <p>
                            Seedlings and nursery crop timeline
                        </p>


                        <div class="nursery-gantt">

                            <div class="nursery-row">

                                <span>
                                    Lettuce
                                </span>

                                <div class="nursery-track">

                                    <div
                                        class="nursery-bar sowing"
                                        style="left:0%; width:35%;"
                                    ></div>

                                    <div
                                        class="nursery-bar transplant"
                                        style="left:35%; width:8%;"
                                    ></div>

                                    <div
                                        class="nursery-bar harvest"
                                        style="left:43%; width:35%;"
                                    ></div>

                                </div>

                            </div>


                            <div class="nursery-row">

                                <span>
                                    Pechay
                                </span>

                                <div class="nursery-track">

                                    <div
                                        class="nursery-bar sowing"
                                        style="left:5%; width:32%;"
                                    ></div>

                                    <div
                                        class="nursery-bar transplant"
                                        style="left:37%; width:8%;"
                                    ></div>

                                    <div
                                        class="nursery-bar harvest"
                                        style="left:45%; width:30%;"
                                    ></div>

                                </div>

                            </div>


                            <div class="nursery-row">

                                <span>
                                    Okra
                                </span>

                                <div class="nursery-track">

                                    <div
                                        class="nursery-bar sowing"
                                        style="left:10%; width:35%;"
                                    ></div>

                                    <div
                                        class="nursery-bar transplant"
                                        style="left:45%; width:8%;"
                                    ></div>

                                    <div
                                        class="nursery-bar harvest"
                                        style="left:53%; width:30%;"
                                    ></div>

                                </div>

                            </div>

                        </div>


                        <div class="nursery-legend">

                            <span>
                                <i class="sowing"></i>
                                Sowing
                            </span>

                            <span>
                                <i class="transplant"></i>
                                Transplant
                            </span>

                            <span>
                                <i class="harvest"></i>
                                Harvest
                            </span>

                        </div>

                    </div>

                </div>
                <!-- =================================================
     LIVESTOCK HOUSE POPUP
================================================== -->

<div
    class="livestock-overlay"
    id="livestockOverlay"
>

    <div
        class="livestock-popup"
        id="livestockPopup"
    >

        <!-- CLOSE BUTTON -->
        <button
            type="button"
            class="popup-close"
            id="closeLivestockBtn"
        >
            <i class="fa-solid fa-xmark"></i>
        </button>


        <!-- =========================
             LIVESTOCK HOUSE IMAGE
        ========================== -->

        <div class="livestock-house-map">
            <div class="house-image-empty-message">House image is not available. Check the saved image file or path.</div>

            <img
                id="livestockHouseImage"
                src=""
                alt="Livestock House"
                draggable="false"
            >

            <!-- Animal markers will be inserted here by JavaScript -->
            <div
                id="livestockMarkersContainer"
            ></div>

        </div>


        <!-- =========================
             HOUSE INFORMATION
        ========================== -->

        <div class="livestock-house-info">

            <h2 id="livestockHouseTitle">
                Livestock House
            </h2>

            <p id="livestockHouseDescription">
                Livestock housing area.
            </p>

            <span
                class="livestock-count"
                id="livestockCount"
            >
                0 animals
            </span>

            <div class="livestock-house-toolbar" id="livestockHouseToolbar">
                <button type="button" id="editLivestockHouseBtn">
                    <i class="fa-solid fa-pen"></i> Edit House
                </button>
                <div class="livestock-house-edit-actions" id="livestockHouseEditActions">
                    <button type="button" id="editLivestockPositionBtn">
                        <i class="fa-solid fa-up-down-left-right"></i> Edit Position
                    </button>
                    <button type="button" class="delete-bed" id="deleteLivestockBtn">
                        <i class="fa-solid fa-trash"></i> Delete Selected
                    </button>
                    <button type="button" id="editSelectedLivestockBtn">
                        <i class="fa-solid fa-pen"></i> Edit Livestock
                    </button>
                    <button type="button" id="doneLivestockHouseBtn">
                        <i class="fa-solid fa-check"></i> Done
                    </button>
                </div>
            </div>

        </div>

    </div>

</div>


<!-- =================================================
     LIVESTOCK PROFILE POPUP
================================================== -->

<div class="livestock-profile-overlay" id="livestockProfileOverlay">

    <div class="livestock-profile-popup" id="livestockProfilePopup">

        <!-- =========================
             PROFILE HEADER
        ========================== -->

        <div class="livestock-profile-title">

            <h2>
                <i class="fa-solid fa-cow"></i>
                <span id="profileLivestockName">GINO</span>
            </h2>

            <p id="profileLivestockType">Goat</p>

            <button
                type="button"
                class="livestock-profile-close"
                id="closeLivestockProfileBtn"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>


        <!-- =========================
             LIVESTOCK IMAGE
        ========================== -->

        <div class="livestock-profile-image-section">

            <div>

                <div class="livestock-profile-image-wrapper">

                    <img
                        id="livestockProfileImage"
                        class="livestock-profile-image"
                        src=""
                        alt="Livestock"
                        style="display:none;"
                    >

                    <div
                        class="livestock-image-placeholder"
                        id="livestockImagePlaceholder"
                    >
                        <i class="fa-solid fa-cow"></i>
                        <span>No livestock picture uploaded</span>
                    </div>

                </div>

                <div class="livestock-image-actions" id="livestockImageActions" style="display:none;">

                    <button
                        type="button"
                        class="livestock-image-btn"
                        id="uploadLivestockImageBtn"
                    >
                        <i class="fa-solid fa-camera"></i>
                        Upload / Change Picture
                    </button>

                    <button
                        type="button"
                        class="livestock-image-btn remove"
                        id="removeLivestockImageBtn"
                    >
                        <i class="fa-solid fa-trash"></i>
                        Remove
                    </button>

                    <input
                        type="file"
                        id="livestockImageInput"
                        accept="image/*"
                        hidden
                    >

                </div>

            </div>

        </div>


        <!-- =========================
             LIVESTOCK PROFILE CONTENT
        ========================== -->

        <div class="livestock-profile-content">

            <!-- =========================
                 DISPLAY INFORMATION
            ========================== -->

            <div class="livestock-profile-grid">

                <div class="livestock-profile-field">
                    <span>Type</span>
                    <strong id="profileLivestockTypeDisplay">-</strong>
                </div>

                <div class="livestock-profile-field">
                    <span>Sex</span>
                    <strong id="profileLivestockSex">-</strong>
                </div>

                <div class="livestock-profile-field">
                    <span>Date of Birth</span>
                    <strong id="profileLivestockDOB">-</strong>
                </div>

                <div class="livestock-profile-field">
                    <span>Breed</span>
                    <strong id="profileLivestockBreed">-</strong>
                </div>

                <div class="livestock-profile-field">
                    <span>Age</span>
                    <strong id="profileLivestockAge">-</strong>
                </div>

                <div class="livestock-profile-field">
                    <span>Estimated Weight</span>
                    <strong id="profileLivestockWeight">-</strong>
                </div>

                <div class="livestock-profile-field">
                    <span>For Sale</span>
                    <strong id="profileLivestockForSale">-</strong>
                </div>

                <div class="livestock-profile-field full">
                    <span>Feeding Schedule</span>
                    <strong id="profileLivestockFeedingSchedule">-</strong>
                </div>

                <div class="livestock-profile-field full">
                    <span>Notes</span>
                    <strong id="profileLivestockNotes">-</strong>
                </div>

            </div>


            <!-- =========================
                 EDIT FORM
            ========================== -->

            <div
                class="livestock-edit-form"
                id="livestockEditForm"
            >

                <div class="livestock-edit-grid">

                    <!-- Animal Name -->

                    <div class="livestock-input-group">

                        <label for="editLivestockName">
                            Animal Name
                        </label>

                        <input
                            type="text"
                            id="editLivestockName"
                            placeholder="Enter animal name"
                        >

                    </div>


                    <!-- Animal Type -->

                    <div class="livestock-input-group">

                        <label for="editLivestockType">
                            Animal Type
                        </label>

                        <input
                            type="text"
                            id="editLivestockType"
                            placeholder="e.g. Goat, Cow, Pig"
                        >

                    </div>

                    <!-- =================================================
     LIVESTOCK MARKER ICON
================================================== -->

<div class="livestock-input-group full marker-icon-chooser" id="livestockMarkerIconChooser">

    <span class="marker-icon-chooser-label">
        Choose Livestock Icon
    </span>


    <div class="marker-icon-options">

        <div class="marker-icon-option">

            <input
                type="radio"
                name="livestockMarkerIcon"
                id="livestockIconGoat"
                value="goat"
            >

            <label for="livestockIconGoat">

                <img
                    src="<?= $basePath ?>farmMap/icons/livestocks/goat.png"
                    alt="Goat"
                >

                <span>
                    Goat
                </span>

            </label>

        </div>


        <div class="marker-icon-option">

            <input
                type="radio"
                name="livestockMarkerIcon"
                id="livestockIconRabbit"
                value="rabbit"
            >

            <label for="livestockIconRabbit">

                <img
                    src="<?= $basePath ?>farmMap/icons/livestocks/rabbit.png"
                    alt="Rabbit"
                >

                <span>
                    Rabbit
                </span>

            </label>

        </div>


        <div class="marker-icon-option">

            <input
                type="radio"
                name="livestockMarkerIcon"
                id="livestockIconChicken"
                value="chicken"
            >

            <label for="livestockIconChicken">

                <img
                    src="<?= $basePath ?>farmMap/icons/livestocks/chicken.png"
                    alt="Chicken"
                >

                <span>
                    Chicken
                </span>

            </label>

        </div>


        <div class="marker-icon-option">

            <input
                type="radio"
                name="livestockMarkerIcon"
                id="livestockIconPig"
                value="pig"
            >

            <label for="livestockIconPig">

                <img
                    src="<?= $basePath ?>farmMap/icons/livestocks/pig.png"
                    alt="Pig"
                >

                <span>
                    Pig
                </span>

            </label>

        </div>

    </div>

</div>

<!-- =================================================
     LIVESTOCK MARKER POSITION
================================================== -->

<div class="livestock-input-group">

    <label for="editLivestockMarkerX">
        Position X (%)
    </label>

    <input
        type="number"
        id="editLivestockMarkerX"
        min="0"
        max="100"
        step="0.1"
    >

</div>


<div class="livestock-input-group">

    <label for="editLivestockMarkerY">
        Position Y (%)
    </label>

    <input
        type="number"
        id="editLivestockMarkerY"
        min="0"
        max="100"
        step="0.1"
    >

</div>


                    <!-- Sex -->

                    <div class="livestock-input-group">

                        <label for="editLivestockSex">
                            Sex
                        </label>

                        <select id="editLivestockSex">

                            <option value="">
                                Select sex
                            </option>

                            <option value="male">
                                Male
                            </option>

                            <option value="female">
                                Female
                            </option>

                            <option value="mixed">
                                Mixed
                            </option>

                        </select>

                    </div>


                    <!-- Date of Birth -->

                    <div class="livestock-input-group">

                        <label for="editLivestockDOB">
                            Date of Birth
                        </label>

                        <input
                            type="date"
                            id="editLivestockDOB"
                        >

                    </div>


                    <!-- Breed -->

                    <div class="livestock-input-group">

                        <label for="editLivestockBreed">
                            Breed
                        </label>

                        <input
                            type="text"
                            id="editLivestockBreed"
                            placeholder="Enter breed"
                        >

                    </div>


                    <!-- Estimated Weight -->

                    <div class="livestock-input-group">

                        <label for="editLivestockWeight">
                            Estimated Weight (kg)
                        </label>

                        <input
                            type="number"
                            id="editLivestockWeight"
                            min="0"
                            step="0.01"
                            placeholder="e.g. 25"
                        >

                    </div>


                    <!-- Number -->

                    <div class="livestock-input-group">

                        <label for="editLivestockNumber">
                            Number of Animals
                        </label>

                        <input
                            type="number"
                            id="editLivestockNumber"
                            min="1"
                            step="1"
                            placeholder="e.g. 1"
                        >

                    </div>


                    <!-- For Sale -->

                    <div class="livestock-input-group">

                        <label for="editLivestockForSale">
                            For Sale
                        </label>

                        <select id="editLivestockForSale">

                            <option value="0">
                                No
                            </option>

                            <option value="1">
                                Yes
                            </option>

                        </select>

                    </div>


                    <!-- Feeding Schedule -->

                    <div class="livestock-input-group full">

                        <label for="editLivestockFeedingSchedule">
                            Feeding Schedule
                        </label>

                        <textarea
                            id="editLivestockFeedingSchedule"
                            placeholder="Enter feeding schedule"
                            rows="3"
                        ></textarea>

                    </div>

                </div>

            </div>

            <div class="livestock-input-group full">

    <label for="editLivestockNotes">
        Notes
    </label>

    <textarea
        id="editLivestockNotes"
        placeholder="Enter additional notes"
        rows="4"
    ></textarea>

</div>


            <!-- =========================
                 PROFILE ACTIONS
            ========================== -->

            <div class="livestock-profile-actions">

                <button
                    type="button"
                    class="livestock-edit-btn"
                    id="editLivestockProfileBtn"
                >
                    <i class="fa-solid fa-pen"></i>
                    Edit Profile
                </button>


                <button
                    type="button"
                    class="livestock-save-btn"
                    id="saveLivestockProfileBtn"
                    style="display:none;"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Profile
                </button>


                <button
                    type="button"
                    class="livestock-cancel-btn"
                    id="cancelLivestockProfileBtn"
                    style="display:none;"
                >
                    <i class="fa-solid fa-xmark"></i>
                    Cancel
                </button>

            </div>

        </div>

    </div>

</div>

                <div class="farm-map-transfer-overlay" id="farmMapTransferOverlay" role="dialog" aria-modal="true" aria-label="Transfer crop to plot">
                    <div class="farm-map-transfer-popup">
                        <button type="button" class="farm-map-transfer-close" id="farmMapTransferClose" aria-label="Close transfer form">&times;</button>
                        <iframe id="farmMapTransferFrame" title="Transfer crop to plot"></iframe>
                    </div>
                </div>

                <!-- =================================================
                     CROP PROFILE POPUP
                ================================================== -->

                <div
                    class="crop-profile-overlay"
                    id="cropProfileOverlay"
                >

                    <div
                        class="crop-profile-popup"
                        id="cropProfilePopup"
                    >

                        <!-- HEADER -->

                        <div class="crop-profile-title">

                            <div>

                                <h2>
                                    <i class="fa-solid fa-seedling"></i>
                                    <span id="profileCropName">LETTUCE</span>
                                </h2>

                                <p id="profileCropVariety">
                                     Majesta Variety
                                </p>

                                <button
                                type="button"
                                class="crop-profile-close"
                                id="closeCropProfileBtn"
                            >

                                <i class="fa-solid fa-xmark"></i>

                            </button>

                            </div>


                            

                        </div>


                        <!-- IMAGE -->

                        <div class="crop-profile-image-section">

                            <div>

                                <div
                                    class="crop-profile-image-wrapper"
                                    id="cropImageWrapper"
                                >

                                    <img
                                        id="cropProfileImage"
                                        class="crop-profile-image"
                                        src=""
                                        alt="Crop"
                                        style="display:none;"
                                    >


                                    <div
                                        class="crop-image-placeholder"
                                        id="cropImagePlaceholder"
                                    >

                                        <i class="fa-solid fa-seedling"></i>

                                        <span>
                                            No crop picture uploaded
                                        </span>

                                    </div>

                                </div>


                                <div
                                    class="crop-image-actions"
                                    id="cropImageActions"
                                    style="display:none;"
                                >

                                    <button
                                        type="button"
                                        class="crop-image-btn"
                                        id="uploadCropImageBtn"
                                    >

                                        <i class="fa-solid fa-camera"></i>

                                        Upload / Change Picture

                                    </button>


                                    <button
                                        type="button"
                                        class="crop-image-btn remove"
                                        id="removeCropImageBtn"
                                    >

                                        <i class="fa-solid fa-trash"></i>

                                        Remove

                                    </button>


                                    <input
                                        type="file"
                                        id="cropImageInput"
                                        accept="image/*"
                                        hidden
                                    >

                                </div>

                            </div>

                        </div>


                        <!-- PROFILE CONTENT -->

                        <div class="crop-profile-content">


                            <!-- BASIC DETAILS -->

                            <div class="crop-profile-grid">

                                <div class="crop-profile-field">

                                    <span>
                                        Staff
                                    </span>

                                    <strong id="profileCropStaff">
                                        Mr. Villa
                                    </strong>

                                </div>


                                <div class="crop-profile-field">

                                    <span>
                                        Quantity
                                    </span>

                                    <strong id="profileCropPCS">
                                        5 Trays
                                    </strong>

                                </div>


                                <div class="crop-profile-field">

                                    <span>
                                        Germination Rate
                                    </span>

                                    <strong id="profileCropGermination">
                                        79%
                                    </strong>

                                </div>


                                <div class="crop-profile-field">

                                    <span>
                                        Bed Number
                                    </span>

                                    <strong id="profileCropArea">
                                        A1
                                    </strong>

                                </div>


                                <div class="crop-profile-field" id="profileCropTargetAreaGroup" style="display:none;">
                                    <span>Target Transplant Area</span>
                                    <strong id="profileCropTargetArea">--</strong>
                                </div>

                                <div class="crop-profile-field">

                                    <span id="profileCropDateLabel">
                                        Planted Date
                                    </span>

                                    <strong id="profileCropPlantedDate">
                                        --
                                    </strong>

                                </div>


                                <div class="crop-profile-field">

                                    <span id="profileCropLifecycleDateLabel">Target Transplant Date</span>

                                    <strong id="profileCropTargetTransplantDate">
                                        --
                                    </strong>

                                </div>


                                <div class="crop-profile-field">

                                    <span>
                                        Status
                                    </span>

                                    <strong>
                                        <span
                                            class="crop-status"
                                            id="profileCropStatus"
                                        >
                                            Growing
                                        </span>
                                    </strong>

                                </div>

                            </div>


                            <!-- =================================================
                                 CROP TIMELINE
                            ================================================== -->

                            <div class="crop-timeline-box">

                                <div class="crop-timeline-title">

                                    <strong>
                                        Crop Growth Timeline
                                    </strong>

                                    <span id="cropTimelineText">
                                        0 days planted
                                    </span>

                                </div>


                                <div class="crop-progress-track">

                                    <div
                                        class="crop-progress-bar"
                                        id="cropProgressBar"
                                    ></div>

                                </div>


                                <div class="crop-timeline-stats">

                                    <div class="crop-time-stat">

                                        <span>
                                            Days Planted
                                        </span>

                                        <strong id="profileDaysPlanted">
                                            0 days
                                        </strong>

                                    </div>


                                    <div class="crop-time-stat">

                                        <span>
                                            Weeks Planted
                                        </span>

                                        <strong id="profileWeeksPlanted">
                                            0 weeks
                                        </strong>

                                    </div>


                                    <div class="crop-time-stat" id="profileCropHarvestStat">

                                        <span>
                                            Expected Harvest
                                        </span>

                                        <strong id="profileCropHarvest">
                                            --
                                        </strong>

                                    </div>

                                </div>

                            </div>


                            <!-- =================================================
                                 EDIT FORM
                            ================================================== -->

                            <div
                                class="crop-edit-form"
                                id="cropEditForm"
                            >

                                

 <!-- =================================================
     CROP MARKER ICON
================================================== -->

<div class="crop-input-group marker-icon-chooser">

    <span class="marker-icon-chooser-label">
        Choose Crop Icon
    </span>


    <div class="marker-icon-options">

        <div class="marker-icon-option">

            <input
                type="radio"
                name="cropMarkerIcon"
                id="cropIconLettuce"
                value="lettuce"
            >

            <label for="cropIconLettuce">

                <img
                    src="<?= $basePath ?>farmMap/icons/crops/lettuce.png"
                    alt="Lettuce"
                >

                <span>
                    Lettuce
                </span>

            </label>

        </div>



        <div class="marker-icon-option">

            <input
                type="radio"
                name="cropMarkerIcon"
                id="cropIconTomato"
                value="tomato"
            >

            <label for="cropIconTomato">

                <img
                    src="<?= $basePath ?>farmMap/icons/crops/tomato.png"
                    alt="Tomato"
                >

                <span>
                    Tomato
                </span>

            </label>

        </div>


        <div class="marker-icon-option">

            <input
                type="radio"
                name="cropMarkerIcon"
                id="cropIconCarrot"
                value="carrot"
            >

            <label for="cropIconCarrot">

                <img
                    src="<?= $basePath ?>farmMap/icons/crops/carrot.png"
                    alt="Carrot"
                >

                <span>
                    Carrot
                </span>

            </label>

        </div>



        <div class="marker-icon-option">

            <input
                type="radio"
                name="cropMarkerIcon"
                id="cropIconPepper"
                value="pepper"
            >

            <label for="cropIconPepper">

                <img
                    src="<?= $basePath ?>farmMap/icons/crops/pepper.png"
                    alt="Pepper"
                >

                <span>
                    Pepper
                </span>

            </label>

        </div>


        <div class="marker-icon-option">

            <input
                type="radio"
                name="cropMarkerIcon"
                id="cropIconCorn"
                value="corn"
            >

            <label for="cropIconCorn">

                <img
                    src="<?= $basePath ?>farmMap/icons/crops/corn.png"
                    alt="Corn"
                >

                <span>
                    Corn
                </span>

            </label>

        </div>


        <div class="marker-icon-option">

            <input
                type="radio"
                name="cropMarkerIcon"
                id="cropIconEggplant"
                value="eggplant"
            >

            <label for="cropIconEggplant">

                <img
                    src="<?= $basePath ?>farmMap/icons/crops/eggplant.png"
                    alt="Eggplant"
                >

                <span>
                    Eggplant
                </span>

            </label>

        </div>

        <div class="marker-icon-option">

            <input
                type="radio"
                name="cropMarkerIcon"
                id="cropIconBeans"
                value="beans"
            >

            <label for="cropIconBeans">

                <img
                    src="<?= $basePath ?>farmMap/icons/crops/beans.png"
                    alt="Beans"
                >

                <span>Beans</span>

            </label>

        </div>

        <div class="marker-icon-option">

            <input
                type="radio"
                name="cropMarkerIcon"
                id="cropIconCoriander"
                value="coriander"
            >

            <label for="cropIconCoriander">

                <img
                    src="<?= $basePath ?>farmMap/icons/crops/coriander.png"
                    alt="Coriander"
                >

                <span>Coriander</span>

            </label>

        </div>

        <div class="marker-icon-option">

            <input
                type="radio"
                name="cropMarkerIcon"
                id="cropIconCucumber"
                value="cucumber"
            >

            <label for="cropIconCucumber">

                <img
                    src="<?= $basePath ?>farmMap/icons/crops/cucumber.png"
                    alt="Cucumber"
                >

                <span>Cucumber</span>

            </label>

        </div>

        <div class="marker-icon-option">

            <input
                type="radio"
                name="cropMarkerIcon"
                id="cropIconKale"
                value="kale"
            >

            <label for="cropIconKale">

                <img
                    src="<?= $basePath ?>farmMap/icons/crops/kale.png"
                    alt="Kale"
                >

                <span>Kale</span>

            </label>

        </div>

        <div class="marker-icon-option">

            <input
                type="radio"
                name="cropMarkerIcon"
                id="cropIconOkra"
                value="okra"
            >

            <label for="cropIconOkra">

                <img
                    src="<?= $basePath ?>farmMap/icons/crops/okra.png"
                    alt="Okra"
                >

                <span>Okra</span>

            </label>

        </div>

        <div class="marker-icon-option">

            <input
                type="radio"
                name="cropMarkerIcon"
                id="cropIconOnion"
                value="onion"
            >

            <label for="cropIconOnion">

                <img
                    src="<?= $basePath ?>farmMap/icons/crops/onion.png"
                    alt="Onion"
                >

                <span>Onion</span>

            </label>

        </div>

        <div class="marker-icon-option">

            <input
                type="radio"
                name="cropMarkerIcon"
                id="cropIconRadish"
                value="radish"
            >

            <label for="cropIconRadish">

                <img
                    src="<?= $basePath ?>farmMap/icons/crops/radish.png"
                    alt="Radish"
                >

                <span>Radish</span>

            </label>

        </div>

        <div class="marker-icon-option">

            <input
                type="radio"
                name="cropMarkerIcon"
                id="cropIconSpinach"
                value="spinach"
            >

            <label for="cropIconSpinach">

                <img
                    src="<?= $basePath ?>farmMap/icons/crops/spinach.png"
                    alt="Spinach"
                >

                <span>Spinach</span>

            </label>

        </div>

    <div class="marker-icon-options">
        <div class="marker-icon-option">
            <input type="radio" name="cropMarkerIcon" id="cropIconBasil" value="basil">
            <label for="cropIconBasil"><img src="<?= $basePath ?>farmMap/icons/crops/basil.png" alt="Basil"><span>Basil</span></label>
        </div>
        <div class="marker-icon-option">
            <input type="radio" name="cropMarkerIcon" id="cropIconBrocolli" value="brocolli">
            <label for="cropIconBrocolli"><img src="<?= $basePath ?>farmMap/icons/crops/brocolli.png" alt="Brocolli"><span>Brocolli</span></label>
        </div>
        <div class="marker-icon-option">
            <input type="radio" name="cropMarkerIcon" id="cropIconDill" value="dill">
            <label for="cropIconDill"><img src="<?= $basePath ?>farmMap/icons/crops/dill.png" alt="Dill"><span>Dill</span></label>
        </div>
        <div class="marker-icon-option">
            <input type="radio" name="cropMarkerIcon" id="cropIconMelon" value="melon">
            <label for="cropIconMelon"><img src="<?= $basePath ?>farmMap/icons/crops/melon.png" alt="Melon"><span>Melon</span></label>
        </div>
    </div>
    </div>

</div>


<div class="crop-edit-grid">


                                    <div class="crop-input-group">

                                        <label>
                                            Crop Name
                                        </label>

                                        <input
                                            type="text"
                                            id="editCropName"
                                        >

                                    </div>

                                    <div class="crop-input-group">

                                        <label>
                                            Variety
                                        </label>

                                        <input
                                            type="text"
                                            id="editCropVariety"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Staff
                                        </label>

                                        <input
                                            type="text"
                                            id="editCropStaff"
                                        >





<div class="crop-input-group">

    <label>
        Seedlings Received
    </label>

    <input
        type="number"
        id="editCropSeedlings"
        min="0"
        step="1"
    >

</div>

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Quantity / PCS
                                        </label>

                                        <input
                                            type="text"
                                            id="editCropPCS"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Germination Rate
                                        </label>

                                        <input
                                            type="text"
                                            id="editCropGermination"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Bed Number
                                        </label>

                                        <input
                                            type="text"
                                            id="editCropArea"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label id="editCropDateLabel">
                                            Planted Date
                                        </label>

                                        <input
                                            type="date"
                                            id="editCropPlantedDate"
                                        >

                                    </div>


                                    <div class="crop-input-group" id="editCropTargetAreaGroup" style="display:none;">
                                        <label for="editCropTargetArea">Target Transplant Area</label>
                                        <select id="editCropTargetArea">
                                            <option value="">Select target area</option>
                                            <option value="Plot A">Plot A</option>
                                            <option value="Plot B">Plot B</option>
                                            <option value="Plot C">Plot C</option>
                                            <option value="MNO">MNO</option>
                                            <option value="Hydro">Hydro</option>
                                            <option value="Tent House">Tent House</option>
                                        </select>
                                    </div>

                                    <div class="crop-input-group" id="editCropTargetTransplantGroup">

                                        <label>
                                            Target Transplant Date
                                        </label>

                                        <input
                                            type="date"
                                            id="editCropTargetTransplantDate"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Expected Harvest
                                        </label>

                                        <input
                                            type="date"
                                            id="editCropHarvest"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Crop Status
                                        </label>

                                        <select
                                            id="editCropStatus"
                                        >

                                            <option value="Planted">
                                                Planted
                                            </option>

                                            <option value="Growing">
                                                Growing
                                            </option>

                                            <option value="Ready for Harvest">
                                                Ready for Harvest
                                            </option>

                                            <option value="Harvested">
                                                Harvested
                                            </option>

                                        </select>

                                    </div>

                                </div>

                            </div>


                            <div class="crop-edit-form crop-harvest-form" id="cropHarvestForm">
                                <h3>Harvest Record</h3>
                                <div class="crop-edit-grid">
                                    <div class="crop-input-group"><label for="harvestCount">Total Number of Harvest</label><input type="number" id="harvestCount" min="1" step="1" value="1" required></div>
                                    <div class="crop-input-group"><label for="harvestDate">Date of Pull / Harvest</label><input type="date" id="harvestDate" required></div>
                                    <div class="crop-input-group"><label for="harvestTotalQuantity">Total Quantity</label><input type="number" id="harvestTotalQuantity" min="0" step="0.001"></div>
                                    <div class="crop-input-group"><label for="harvestTotalUnit">Total Quantity Unit</label><input type="text" id="harvestTotalUnit" placeholder="kg, g, pcs"></div>
                                    <div class="crop-input-group"><label for="harvestSalesQuantity">Sales Quantity</label><input type="number" id="harvestSalesQuantity" min="0" step="0.001"></div>
                                    <div class="crop-input-group"><label for="harvestSalesUnit">Sales Unit</label><input type="text" id="harvestSalesUnit" placeholder="kg, g, pcs"></div>
                                    <div class="crop-input-group"><label for="harvestSalesAmount">Sales Amount (PHP)</label><input type="number" id="harvestSalesAmount" min="0" step="0.01"></div>
                                    <div class="crop-input-group"><label for="harvestFarmUseQuantity">Farm Use Quantity</label><input type="number" id="harvestFarmUseQuantity" min="0" step="0.001"></div>
                                    <div class="crop-input-group"><label for="harvestFarmUseUnit">Farm Use Unit</label><input type="text" id="harvestFarmUseUnit" placeholder="kg, g, pcs"></div>
                                    <div class="crop-input-group"><label for="harvestAnimalFeedQuantity">Animal Feed Quantity</label><input type="number" id="harvestAnimalFeedQuantity" min="0" step="0.001"></div>
                                    <div class="crop-input-group"><label for="harvestAnimalFeedUnit">Animal Feed Unit</label><input type="text" id="harvestAnimalFeedUnit" placeholder="kg, g, pcs"></div>
                                    <div class="crop-input-group"><label for="harvestRejectedQuantity">Reject Quantity</label><input type="number" id="harvestRejectedQuantity" min="0" step="0.001"></div>
                                    <div class="crop-input-group"><label for="harvestRejectedUnit">Reject Unit</label><input type="text" id="harvestRejectedUnit" placeholder="kg, g, pcs"></div>
                                    <div class="crop-input-group"><label for="harvestOtherQuantity">Other Purposes Quantity</label><input type="number" id="harvestOtherQuantity" min="0" step="0.001"></div>
                                    <div class="crop-input-group"><label for="harvestOtherUnit">Other Purposes Unit</label><input type="text" id="harvestOtherUnit" placeholder="kg, g, pcs"></div>
                                </div>
                                <div class="crop-profile-actions">
                                    <button type="button" class="crop-harvest-save-btn" id="saveCropHarvestBtn"><i class="fa-solid fa-floppy-disk"></i> Save Harvest</button>
                                    <button type="button" class="crop-cancel-btn" id="cancelCropHarvestBtn"><i class="fa-solid fa-xmark"></i> Cancel</button>
                                </div>
                            </div>

                            <!-- ACTIONS -->

                            <div class="crop-profile-actions">

                                <button type="button" class="crop-transplant-btn" id="transplantCropBtn" style="display:none;">
                                    <i class="fa-solid fa-up-long"></i> Transplant
                                </button>
                                <button type="button" class="crop-harvest-btn" id="harvestCropBtn" style="display:none;">
                                    <i class="fa-solid fa-basket-shopping"></i> Harvest
                                </button>
                                <button
                                    type="button"
                                    class="crop-edit-btn"
                                    id="editCropProfileBtn"
                                >

                                    <i class="fa-solid fa-pen"></i>

                                    Edit Profile

                                </button>


                                <button
                                    type="button"
                                    class="crop-save-btn"
                                    id="saveCropProfileBtn"
                                    style="display:none;"
                                >

                                    <i class="fa-solid fa-floppy-disk"></i>

                                    Save Profile

                                </button>


                                <button
                                    type="button"
                                    class="crop-cancel-btn"
                                    id="cancelCropProfileBtn"
                                    style="display:none;"
                                >

                                    <i class="fa-solid fa-xmark"></i>

                                    Cancel

                                </button>

                            </div>

                        </div>

                    </div>

                </div>


            </div>

        </div>




<script>
const MAP_CAN_EDIT_CROPS = <?= $mapCanEditCrops ? "true" : "false" ?>;
const MAP_CAN_EDIT_LIVESTOCK = <?= $mapCanEditLivestock ? "true" : "false" ?>;
const MAP_CAN_EDIT_ALL = <?= $mapCanEditAll ? "true" : "false" ?>;

/* =========================================================
   SANCTUARIO FARM MAP
   CROP PROFILES + BED EDITING + MAP EDITING
========================================================= */


/* =========================================================
   BASIC MAP REFERENCES
========================================================= */

const mapContainer =
    document.getElementById("mapContainer");

const mainMapLayer =
    document.getElementById("mainMapLayer");

const farmMap =
    document.getElementById("farmMap");


/* =========================================================
   FARM MAP ZOOM + PAN
   Zooms the map itself, never the browser page.
   When zoomed in, the map can be dragged like the Admin
   Dashboard map.
========================================================= */

let mapZoom = 1;
let translateX = 0;
let translateY = 0;
let draggingMap = false;
let mapStartX = 0;
let mapStartY = 0;
let mapStartTranslateX = 0;
let mapStartTranslateY = 0;

/* Touch/pinch state. These pointers belong to the map itself so phone
   gestures never move the page while the map is being inspected. */
const activeMapPointers = new Map();
let pinchStartDistance = 0;
let pinchStartZoom = 1;
let pinchStartCenterX = 0;
let pinchStartCenterY = 0;
let pinchStartTranslateX = 0;
let pinchStartTranslateY = 0;

/* Base map dimensions are calculated from the image's real aspect ratio. */
let mapBaseWidth = 0;
let mapBaseHeight = 0;

const MAP_MIN_ZOOM = 1;
const MAP_MAX_ZOOM = 6;
const MAP_ZOOM_STEP = 0.25;

function getPhoneFitZoom() {
    if (
        !mapContainer ||
        !window.matchMedia ||
        !window.matchMedia("(max-width: 600px)").matches
    ) {
        return 1;
    }

    const base = getMapBaseSize();
    if (!base.height) {
        return 1;
    }

    /* Cover the taller phone viewport with a proportional transform. */
    return Math.max(
        MAP_MIN_ZOOM,
        Math.min(MAP_MAX_ZOOM, mapContainer.clientHeight / base.height)
    );
}

function getMapBaseSize() {
    if (!mainMapLayer || !mapContainer || !farmMap) {
        return { width: 0, height: 0 };
    }

    /*
     * IMPORTANT:
     * Always use the dimensions that were actually assigned to the
     * map layer. Do NOT recalculate the width from the phone container
     * width here. Doing that was causing the clamp/centering math to
     * disagree with the real map size and produced the blank gap and
     * distorted-looking viewport.
     */
    if (mapBaseWidth > 0 && mapBaseHeight > 0) {
        return {
            width: mapBaseWidth,
            height: mapBaseHeight
        };
    }

    const naturalWidth = Number(farmMap.naturalWidth) || 0;
    const naturalHeight = Number(farmMap.naturalHeight) || 0;

    if (naturalWidth > 0 && naturalHeight > 0) {
        const width = mapContainer.clientWidth;
        const height = width * (naturalHeight / naturalWidth);

        mapBaseWidth = width;
        mapBaseHeight = height;

        return { width, height };
    }

    return {
        width: mainMapLayer.offsetWidth || 0,
        height: mainMapLayer.offsetHeight || 0
    };
}

function sizeMapLayerToNaturalRatio() {
    if (!mainMapLayer || !farmMap || !mapContainer) return;

    const naturalWidth = Number(farmMap.naturalWidth) || 0;
    const naturalHeight = Number(farmMap.naturalHeight) || 0;
    if (!naturalWidth || !naturalHeight) return;

    const isPhone =
        window.matchMedia &&
        window.matchMedia("(max-width: 600px)").matches;

    let baseWidth;
    let baseHeight;

    /*
     * IMPORTANT:
     * The map artwork is ALWAYS sized from its natural image ratio.
     * The phone container is taller, but the IMAGE IS NOT made taller.
     * The extra vertical coverage is achieved by the normal map zoom,
     * exactly like the dashboard map.
     */
    baseWidth = Math.max(1, mapContainer.clientWidth);
    baseHeight =
        baseWidth *
        (naturalHeight / naturalWidth);

    mapBaseWidth = baseWidth;
    mapBaseHeight = baseHeight;

    /*
     * The layer owns the complete farm artwork + markers.
     * Its dimensions always follow the original image ratio.
     */
    mainMapLayer.style.width = `${baseWidth}px`;
    mainMapLayer.style.height = `${baseHeight}px`;
    mainMapLayer.style.aspectRatio = `${naturalWidth} / ${naturalHeight}`;

    farmMap.style.width = '100%';
    farmMap.style.height = 'auto';
    farmMap.style.maxWidth = 'none';
    farmMap.style.minWidth = '0';
    farmMap.style.aspectRatio = 'auto';
    farmMap.style.objectFit = 'contain';
    farmMap.style.objectPosition = 'left top';
}

function clampMap() {
    if (!mapContainer || !mainMapLayer) {
        return;
    }

    const containerWidth = mapContainer.clientWidth;
    const containerHeight = mapContainer.clientHeight;
    const base = getMapBaseSize();

    const scaledWidth = base.width * mapZoom;
    const scaledHeight = base.height * mapZoom;

    if (scaledWidth <= containerWidth) {
        translateX = (containerWidth - scaledWidth) / 2;
    } else {
        const minX = containerWidth - scaledWidth;
        translateX = Math.max(minX, Math.min(0, translateX));
    }

    if (scaledHeight <= containerHeight) {
        translateY = (containerHeight - scaledHeight) / 2;
    } else {
        const minY = containerHeight - scaledHeight;
        translateY = Math.max(minY, Math.min(0, translateY));
    }
}

function updateMainMap() {
    if (!mainMapLayer) {
        return;
    }

    mainMapLayer.style.transform =
        `translate3d(${translateX}px, ${translateY}px, 0) scale(${mapZoom})`;

    mainMapLayer.style.transformOrigin = "top left";
}

function setFarmMapZoom(newZoom, focusX, focusY) {
    if (!mapContainer || !mainMapLayer) {
        return;
    }

    const oldZoom = mapZoom;
    const nextZoom = Math.max(
        MAP_MIN_ZOOM,
        Math.min(MAP_MAX_ZOOM, Number(newZoom))
    );

    if (nextZoom === oldZoom) {
        return;
    }

    const rect = mapContainer.getBoundingClientRect();

    const fx =
        Number.isFinite(focusX)
            ? focusX
            : rect.width / 2;

    const fy =
        Number.isFinite(focusY)
            ? focusY
            : rect.height / 2;

    /*
     * Keep the point under the cursor/focus point in the same
     * screen position while changing zoom.
     */
    const mapPointX =
        (fx - translateX) / oldZoom;

    const mapPointY =
        (fy - translateY) / oldZoom;

    mapZoom = nextZoom;

    translateX =
        fx - (mapPointX * mapZoom);

    translateY =
        fy - (mapPointY * mapZoom);

    clampMap();
    updateMainMap();
}

function resetFarmMapZoom() {
    if (!mapContainer || !mainMapLayer) {
        return;
    }

    mapZoom = getPhoneFitZoom();
    translateX = 0;
    translateY = 0;

    clampMap();
    updateMainMap();
}

function initializeMap() {
    const isPhone =
        window.matchMedia &&
        window.matchMedia("(max-width: 600px)").matches;

    /* Size the map from the source image ratio before applying zoom. */
    sizeMapLayerToNaturalRatio();

    /*
     * PHONE:
     * Keep the image at its natural aspect ratio and use NORMAL MAP ZOOM
     * to make the taller phone viewport useful. We do NOT change the
     * image's width/height ratio.
     */
    if (isPhone) {
        mapZoom = getPhoneFitZoom();
    } else {
        mapZoom = 1;
    }

    draggingMap = false;
    activeMapPointers.clear();

    /* Start the phone view centered on the farm instead of pinned to the
       upper-left corner. The map and every percentage-positioned marker
       remain inside the same transformed layer. */
    const base = getMapBaseSize();
    if (isPhone && mapContainer && base.width && base.height) {
        /*
         * At the initial phone zoom, center the farm artwork in the
         * viewport. Because baseHeight == viewportHeight, this produces
         * no artificial blank strip above or below the map.
         */
        translateX =
            (mapContainer.clientWidth - (base.width * mapZoom)) / 2;

        translateY =
            (mapContainer.clientHeight - (base.height * mapZoom)) / 2;
    } else {
        translateX = 0;
        translateY = 0;
    }

    clampMap();
    updateMainMap();
    syncMainMapMarkers();
}

/*
 * Main markers are percentage-sized in CSS, so they naturally
 * scale together with the map. This function remains available
 * for existing code that calls it.
 */
function syncMainMapMarkers() {
    return;
}

function mapPointerDistance(a, b) {
    return Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY);
}

function mapPointerCenter(a, b) {
    return {
        x: ((a.clientX + b.clientX) / 2),
        y: ((a.clientY + b.clientY) / 2)
    };
}

function beginMapPinch() {
    const points = Array.from(activeMapPointers.values());
    if (points.length < 2 || !mapContainer) return;

    const rect = mapContainer.getBoundingClientRect();
    const center = mapPointerCenter(points[0], points[1]);

    pinchStartDistance = Math.max(1, mapPointerDistance(points[0], points[1]));
    pinchStartZoom = mapZoom;
    pinchStartCenterX = center.x - rect.left;
    pinchStartCenterY = center.y - rect.top;
    pinchStartTranslateX = translateX;
    pinchStartTranslateY = translateY;

    draggingMap = false;
    mapContainer.classList.remove("is-dragging");
    mainMapLayer?.classList.remove("is-dragging");
}

function moveMapPinch() {
    const points = Array.from(activeMapPointers.values());
    if (points.length < 2 || !mapContainer) return;

    const rect = mapContainer.getBoundingClientRect();
    const distance = Math.max(1, mapPointerDistance(points[0], points[1]));
    const center = mapPointerCenter(points[0], points[1]);
    const centerX = center.x - rect.left;
    const centerY = center.y - rect.top;

    const nextZoom = Math.max(
        MAP_MIN_ZOOM,
        Math.min(
            MAP_MAX_ZOOM,
            pinchStartZoom * (distance / pinchStartDistance)
        )
    );

    /* Keep the original pinch midpoint anchored while zooming, then allow
       the midpoint itself to pan with the two fingers. */
    const mapPointX =
        (pinchStartCenterX - pinchStartTranslateX) / pinchStartZoom;
    const mapPointY =
        (pinchStartCenterY - pinchStartTranslateY) / pinchStartZoom;

    mapZoom = nextZoom;
    translateX = centerX - (mapPointX * mapZoom);
    translateY = centerY - (mapPointY * mapZoom);

    clampMap();
    updateMainMap();
}

function mapCanPan() {
    if (!mapContainer || !mainMapLayer) return false;
    const base = getMapBaseSize();
    return (base.width * mapZoom) > mapContainer.clientWidth + 1 ||
           (base.height * mapZoom) > mapContainer.clientHeight + 1;
}

function beginMapDrag(event) {
    if (
        !mapContainer ||
        !mainMapLayer ||
        mapEditMode ||
        !mapCanPan() ||
        isOverlayTarget(event.target) ||
        event.target.closest(".farm-map-zoom-controls") ||
        event.target.closest(".main-marker")
    ) {
        return;
    }

    if (event.pointerType === "mouse" && event.button !== 0) {
        return;
    }
    draggingMap = true;
    if (event.pointerId !== undefined && mapContainer.setPointerCapture) {
        mapContainer.setPointerCapture(event.pointerId);
    }

    mapStartX = event.clientX;
    mapStartY = event.clientY;

    mapStartTranslateX = translateX;
    mapStartTranslateY = translateY;

    mapContainer.classList.add("is-dragging");
    mainMapLayer.classList.add("is-dragging");

    event.preventDefault();
}

function moveMap(event) {
    if (!draggingMap) {
        return;
    }

    translateX =
        mapStartTranslateX +
        (event.clientX - mapStartX);

    translateY =
        mapStartTranslateY +
        (event.clientY - mapStartY);

    clampMap();
    updateMainMap();

    event.preventDefault();
}

function endMapDrag() {
    draggingMap = false;

    if (mapContainer) {
        mapContainer.classList.remove("is-dragging");
    }

    if (mainMapLayer) {
        mainMapLayer.classList.remove("is-dragging");
    }
}

/* Buttons */
const farmMapZoomIn =
    document.getElementById("farmMapZoomIn");

const farmMapZoomOut =
    document.getElementById("farmMapZoomOut");

const farmMapZoomReset =
    document.getElementById("farmMapZoomReset");

if (farmMapZoomIn) {
    farmMapZoomIn.addEventListener("click", function(event) {
        event.preventDefault();
        event.stopPropagation();
        setFarmMapZoom(mapZoom + MAP_ZOOM_STEP);
    });
}

if (farmMapZoomOut) {
    farmMapZoomOut.addEventListener("click", function(event) {
        event.preventDefault();
        event.stopPropagation();
        setFarmMapZoom(mapZoom - MAP_ZOOM_STEP);
    });
}

if (farmMapZoomReset) {
    farmMapZoomReset.addEventListener("click", function(event) {
        event.preventDefault();
        event.stopPropagation();
        resetFarmMapZoom();
    });
}

/* Mouse wheel + touch/pinch zoom. */
if (mapContainer) {
    mapContainer.addEventListener(
        "wheel",
        function(event) {
            event.preventDefault();
            event.stopPropagation();

            const rect = mapContainer.getBoundingClientRect();
            const focusX = event.clientX - rect.left;
            const focusY = event.clientY - rect.top;
            const direction = event.deltaY < 0 ? 1 : -1;

            setFarmMapZoom(
                mapZoom + (direction * MAP_ZOOM_STEP),
                focusX,
                focusY
            );
        },
        { passive: false }
    );

    mapContainer.addEventListener("pointerdown", function(event) {
        if (event.pointerType === "touch" || event.pointerType === "pen") {
            if (isOverlayTarget(event.target) ||
                event.target.closest(".farm-map-zoom-controls") ||
                event.target.closest(".main-marker") ||
                mapEditMode) {
                return;
            }

            activeMapPointers.set(event.pointerId, event);

            if (activeMapPointers.size === 2) {
                beginMapPinch();
            } else if (activeMapPointers.size === 1) {
                beginMapDrag(event);
            }

            event.preventDefault();
            return;
        }

        beginMapDrag(event);
    }, { passive: false });

    mapContainer.addEventListener("pointermove", function(event) {
        if (activeMapPointers.has(event.pointerId)) {
            activeMapPointers.set(event.pointerId, event);
        }

        if (activeMapPointers.size >= 2) {
            moveMapPinch();
            event.preventDefault();
            return;
        }

        moveMap(event);
    }, { passive: false });

    function finishMapPointer(event) {
        if (activeMapPointers.has(event.pointerId)) {
            activeMapPointers.delete(event.pointerId);
        }

        if (activeMapPointers.size === 1) {
            const remaining = Array.from(activeMapPointers.values())[0];
            beginMapDrag(remaining);
        } else if (activeMapPointers.size === 0) {
            endMapDrag();
        }
    }

    mapContainer.addEventListener("pointerup", finishMapPointer);
    mapContainer.addEventListener("pointercancel", finishMapPointer);
    mapContainer.addEventListener("pointerleave", function(event) {
        if (event.pointerType === "mouse") endMapDrag();
    });
}

document.addEventListener("pointerup", endMapDrag);
document.addEventListener("pointercancel", endMapDrag);

window.addEventListener(
    "resize",
    function() {
        const oldWidth = mapBaseWidth || 1;
        const oldHeight = mapBaseHeight || 1;

        sizeMapLayerToNaturalRatio();

        if (oldWidth && oldHeight && mapBaseWidth && mapBaseHeight) {
            translateX *= mapBaseWidth / oldWidth;
            translateY *= mapBaseHeight / oldHeight;
        }

        clampMap();
        updateMainMap();
    }
);

if (farmMap.complete) {
    requestAnimationFrame(initializeMap);
} else {
    farmMap.addEventListener(
        "load",
        function() {
            requestAnimationFrame(initializeMap);
        },
        { once: true }
    );
}

function isOverlayTarget(target) {
    return !!target.closest(
        ".plot-overlay, .plant-overlay, .nursery-overlay, .crop-profile-overlay, .livestock-overlay, .livestock-profile-overlay"
    );
}

/* =========================================================
   PLOT DATA
========================================================= */

const plotData = {

    A: {

        image:
            "<?= $basePath ?>farmMap/plot_a.jpg?v=2",

        title:
            "Plot A",

        description:
            "Main Vegetable Production Area",

        beds:
            17,

        area:
            "500m²"

    },


    B: {

        image:
            "<?= $basePath ?>farmMap/plot_b.jpg?v=3",

        title:
            "Plot B",

        description:
            "Secondary Vegetable Production Area",

        beds:
            15,

        area:
            "400m²"

    },


    C: {

        image:
            "<?= $basePath ?>farmMap/plot_c.jpg?v=2",

        title:
            "Plot C",

        description:
            "Crop Production Area",

        beds:
            19,

        area:
            "300m²"

    }

};

/* Fixed crop-marker slots per plot. These are permanent positions;
 * deleting a marker only turns its slot into a + button. */
const FIXED_PLOT_MARKER_COUNTS = {
    A: 17,
    B: 15,
    C: 19,
    NURSERY: 20,
    MNO: 20,
    HYDRO: 16,
    TENT_HOUSE: 9
};

plotData.NURSERY = {
    image: "",
    title: "Nursery",
    description: "Nursery Bed Area",
    beds: 20,
    area: "Nursery"
};

plotData.MNO = {
    image: "<?= $basePath ?>farmMap/mno.jpg",
    title: "MNO",
    description: "MNO Crop Area",
    beds: 20,
    area: "MNO"
};

plotData.HYDRO = {
    image: "<?= $basePath ?>farmMap/hydro.jpg",
    title: "Hydro",
    description: "Hydroponic Crop Production Area",
    beds: 16,
    area: "Hydro"
};

plotData.TENT_HOUSE = {
    image: "<?= $basePath ?>farmMap/tent_house.jpg",
    title: "Tent House",
    description: "Tent House Crop Production Area",
    beds: 9,
    area: "Tent House"
};


/* =========================================================
   DATABASE-EMBEDDED DATA (PHP -> JS)
========================================================= */

const basePath = "<?= $basePath ?>";

/* =========================================================
   FARM MARKER ICON CONFIGURATION
========================================================= */

const FARM_MARKER_ICONS = {

    crops: {

        lettuce:
            `${basePath}farmMap/icons/crops/lettuce.png`,

        
        tomato:
            `${basePath}farmMap/icons/crops/tomato.png`,

        carrot:
            `${basePath}farmMap/icons/crops/carrot.png`,


        
        pepper:
            `${basePath}farmMap/icons/crops/pepper.png`,

        corn:
            `${basePath}farmMap/icons/crops/corn.png`,

        eggplant:
            `${basePath}farmMap/icons/crops/eggplant.png`,

        beans:
            `${basePath}farmMap/icons/crops/beans.png`,

        coriander:
            `${basePath}farmMap/icons/crops/coriander.png`,

        cucumber:
            `${basePath}farmMap/icons/crops/cucumber.png`,

        kale:
            `${basePath}farmMap/icons/crops/kale.png`,

        okra:
            `${basePath}farmMap/icons/crops/okra.png`,

        onion:
            `${basePath}farmMap/icons/crops/onion.png`,

        radish:
            `${basePath}farmMap/icons/crops/radish.png`,

        spinach:
            `${basePath}farmMap/icons/crops/spinach.png`,

        basil:
            `${basePath}farmMap/icons/crops/basil.png`,

        brocolli:
            `${basePath}farmMap/icons/crops/brocolli.png`,

        dill:
            `${basePath}farmMap/icons/crops/dill.png`,

        melon:
            `${basePath}farmMap/icons/crops/melon.png`

    },

    livestock: {

        goat:
            `${basePath}farmMap/icons/livestocks/goat.png`,

        rabbit:
            `${basePath}farmMap/icons/livestocks/rabbit.png`,

        chicken:
            `${basePath}farmMap/icons/livestocks/chicken.png`,

        pig:
            `${basePath}farmMap/icons/livestocks/pig.png`

    }

};


/*
 * These two values are intentionally kept in localStorage.
 * The existing bed positions and main map editing system
 * already use localStorage.
 */

const CROP_ICON_STORAGE_KEY =
    "sanctuarioCropMarkerIcons";

const LIVESTOCK_MARKER_STORAGE_KEY =
    "sanctuarioLivestockMarkerData";


function normalizeMarkerIconKey(value) {

    return String(value || "")
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, "");

}


function guessCropIcon(cropName) {

    const name =
        normalizeMarkerIconKey(cropName);

    const cropNames = Object.keys(
        FARM_MARKER_ICONS.crops
    );

    const match =
        cropNames.find(
            key =>
                name.includes(
                    normalizeMarkerIconKey(key)
                )
        );

    return match || "lettuce";

}


function guessLivestockIcon(type) {

    const value =
        normalizeMarkerIconKey(type);

    if (
        value.includes("goat")
    ) {
        return "goat";
    }

    if (
        value.includes("rabbit")
    ) {
        return "rabbit";
    }

    if (
        value.includes("chicken") ||
        value.includes("poultry") ||
        value.includes("hen")
    ) {
        return "chicken";
    }

    if (
        value.includes("pig") ||
        value.includes("swine")
    ) {
        return "pig";
    }

    return "goat";

}


function getStoredCropMarkerIcons() {

    try {

        const value =
            JSON.parse(
                localStorage.getItem(
                    CROP_ICON_STORAGE_KEY
                )
            );

        return (
            value &&
            typeof value === "object" &&
            !Array.isArray(value)
        )
            ? value
            : {};

    } catch (error) {

        console.error(
            "Unable to load crop marker icons:",
            error
        );

        return {};

    }

}


function saveStoredCropMarkerIcons(data) {

    localStorage.setItem(
        CROP_ICON_STORAGE_KEY,
        JSON.stringify(data)
    );

}


function getStoredLivestockMarkerData() {

    try {

        const value =
            JSON.parse(
                localStorage.getItem(
                    LIVESTOCK_MARKER_STORAGE_KEY
                )
            );

        return (
            value &&
            typeof value === "object" &&
            !Array.isArray(value)
        )
            ? value
            : {};

    } catch (error) {

        console.error(
            "Unable to load livestock marker data:",
            error
        );

        return {};

    }

}


function saveStoredLivestockMarkerData(data) {

    localStorage.setItem(
        LIVESTOCK_MARKER_STORAGE_KEY,
        JSON.stringify(data)
    );

}

const userRole = "<?= htmlspecialchars($role, ENT_QUOTES) ?>";

const dbCropsByPlot = <?= json_encode($dbCropsByPlot, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
// Keep the complete crop history for the lifecycle Gantt. Active map controls
// must still ignore harvested crops so harvested beds remain visually empty.
const dbActiveCropsByPlot = Object.fromEntries(
    Object.entries(dbCropsByPlot).map(([location, rows]) => [
        location,
        Array.isArray(rows)
            ? rows.filter(row => String(row?.status || '').trim().toLowerCase() !== 'harvested')
            : []
    ])
);
const dbBeds = <?= json_encode($dbBeds, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
window.dbBeds = dbBeds;
const dbLivestockHouses = <?= json_encode($dbLivestockHouses, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;

function mapCropIsHarvestReady(row) {
    const status = String(row?.status || "").trim().toLowerCase();
    if (status.includes("harvested")) return false;
    if (status === "ready" || status.includes("ready for harvest") || status.includes("ready")) return true;
    if (!row?.expected_harvest) return false;
    const date = new Date(`${row.expected_harvest}T23:59:59`);
    return !Number.isNaN(date.getTime()) && date <= new Date();
}
function applyMapPlotHarvestAlerts() {
    ["A", "B", "C", "MNO", "HYDRO", "TENT_HOUSE"].forEach((plot) => {
        const marker = document.querySelector(`.main-marker[data-marker="plot"][data-id="${plot}"]`);
        if (!marker) return;

        const rows = Array.isArray(dbActiveCropsByPlot[plot]) ? dbActiveCropsByPlot[plot] : [];
        const ready = rows.some(mapCropIsHarvestReady);
        let badge = marker.querySelector(".plot-harvest-alert");

        marker.classList.toggle("has-harvest-alert", ready);

        if (ready && !badge) {
            badge = document.createElement("span");
            badge.className = "plot-harvest-alert";
            badge.setAttribute("title", "Crops ready for harvest");
            badge.setAttribute("aria-label", `${plot} has crops ready for harvest`);
            marker.appendChild(badge);
        } else if (!ready && badge) {
            badge.remove();
        }
    });
}




/* =========================================================
   BED STORAGE
========================================================= */

const BED_STORAGE_KEY =
    "sanctuarioPlotBeds";

const DELETED_BED_SLOTS_STORAGE_KEY =
    "sanctuarioDeletedBedSlots";


let currentPlot = null;

let deletedBedSlots = {};

let deletedBedSlotsSnapshot = null;

let bedEditMode = false;
let bedPositionEditMode = false;

let selectedBed = null;

let draggingBed = null;

let bedSnapshot = null;


function getStoredBeds() {

    try {

        const value =
            JSON.parse(
                localStorage.getItem(
                    BED_STORAGE_KEY
                )
            );


        return value &&
               typeof value === "object" &&
               !Array.isArray(value)
            ? value
            : {};

    } catch (error) {

        console.error(
            "Unable to load bed positions:",
            error
        );

        return {};

    }

}


function saveStoredBeds(data) {

    localStorage.setItem(
        BED_STORAGE_KEY,
        JSON.stringify(data)
    );

}


/* =========================================================
   DELETED BED SLOT STORAGE
========================================================= */

function getStoredDeletedBedSlots() {

    try {

        const value =
            JSON.parse(
                localStorage.getItem(
                    DELETED_BED_SLOTS_STORAGE_KEY
                )
            );

        return value &&
               typeof value === "object" &&
               !Array.isArray(value)
            ? value
            : {};

    } catch (error) {

        console.error(
            "Unable to load deleted bed slots:",
            error
        );

        return {};

    }

}


function saveStoredDeletedBedSlots(data) {

    localStorage.setItem(
        DELETED_BED_SLOTS_STORAGE_KEY,
        JSON.stringify(data)
    );

}


deletedBedSlots =
    getStoredDeletedBedSlots();


/* =========================================================
   DEFAULT BED POSITIONS
========================================================= */

function createDefaultBedPositions(count) {

    const positions = [];

    count =
        Math.max(
            0,
            Number(count) || 0
        );


    if (!count) return positions;


    const columns =
        Math.ceil(
            Math.sqrt(count)
        );


    const rows =
        Math.ceil(
            count / columns
        );


    for (
        let i = 0;
        i < count;
        i++
    ) {

        const row =
            Math.floor(
                i / columns
            );


        const column =
            i % columns;


        const x =
            columns === 1
                ? 50
                : 15 +
                  column *
                  (70 / (columns - 1));


        const y =
            rows === 1
                ? 50
                : 15 +
                  row *
                  (70 / (rows - 1));


        positions.push({

            number:
                i + 1,

            x:
                Math.min(85, x),

            y:
                Math.min(85, y)

        });

    }


    return positions;

}


function getBedsForPlot(plot) {

    const fixedCount =
        FIXED_PLOT_MARKER_COUNTS[plot] ||
        Number(plotData[plot]?.beds) ||
        0;

    const stored = getStoredBeds();
    const saved = Array.isArray(stored[plot]) ? stored[plot] : [];
    const byNumber = new Map();
    const dbBeds = Array.isArray(window.dbBeds) ? window.dbBeds : [];
    const plotPrefix = String(plot || "").toUpperCase();
    const dbPrefixByPlot = { A: "A", B: "B", C: "C", NURSERY: "N", HYDRO: "H", TENT_HOUSE: "T", MNO: "M" };
    const dbPrefix = dbPrefixByPlot[plotPrefix] || plotPrefix;
    const dbBedByNumber = new Map(dbBeds.filter(b => String(b.bed_code || "").toUpperCase().startsWith(dbPrefix)).map(b => [Number(b.bed_number), b]));

    saved.forEach(bed => {
        if (!bed || bed.number == null) return;
        const number = Number(bed.number);
        if (number >= 1 && number <= fixedCount) {
            byNumber.set(number, {
                number,
                code: bed.code || `${plot}${number}`,
                x: Number(bed.x) || 0,
                y: Number(bed.y) || 0
            });
        }
    });

    const defaults = createDefaultBedPositions(fixedCount);

    return defaults.map(defaultBed => {
        const bed = byNumber.get(defaultBed.number) || defaultBed;
        const dbBed = dbBedByNumber.get(defaultBed.number);
        return { ...bed, code: dbBed?.bed_code || bed.code || `${plot}${defaultBed.number}` };
    });
}



/* =========================================================
   CREATE BED MARKER
========================================================= */

function getCropMarkerIconKey(
    bedNumber,
    crop
) {

    const stored =
        getStoredCropMarkerIcons();


    const key =
        `${currentPlot}-${bedNumber}`;


    if (
        stored[key] &&
        FARM_MARKER_ICONS.crops[
            stored[key]
        ]
    ) {

        return stored[key];

    }


    return guessCropIcon(
        crop &&
        crop.name
            ? crop.name
            : ""
    );

}

function cropNeedsHarvestAlert(crop) {
    if (!crop) return false;
    const status = String(crop.status || '').toLowerCase();
    if (status.includes('harvested')) return false;
    if (status.includes('ready')) return true;
    const harvestValue = crop.expected_harvest || crop.harvest || "";
    if (!harvestValue) return false;
    const harvestDate = new Date(`${harvestValue}T00:00:00`);
    return !Number.isNaN(harvestDate.getTime()) && harvestDate <= new Date();
}

function createBedMarker(bed) {

    const marker =
        document.createElement("button");


    marker.type =
        "button";


    marker.className =
        "bed-marker crop-image-marker";


    marker.dataset.bed =
        String(bed.number);


    marker.style.left =
        `${Number(bed.x) || 0}%`;


    marker.style.top =
        `${Number(bed.y) || 0}%`;


    /*
     * Find the crop represented by this bed.
     */
    const crop = bed.cropRow
        ? dbCropToProfile(bed.cropRow)
        : getCropProfile(Number(bed.number));
    if (bed.cropRow && bed.cropRow.crop_id != null) {
        marker.dataset.cropId = String(bed.cropRow.crop_id);
    }


    const cropKey =
        getCropMarkerIconKey(
            Number(bed.number),
            crop
        );


    const iconPath =
        FARM_MARKER_ICONS.crops[cropKey] ||
        FARM_MARKER_ICONS.crops.lettuce;


    const cropName =
        crop &&
        crop.name
            ? crop.name
            : `Bed ${bed.number}`;


    const harvestReady = cropNeedsHarvestAlert(crop);
    const harvestNotice = harvestReady
        ? " Harvest ready — click to view crop details."
        : "";
    marker.title = cropName + harvestNotice;
    marker.setAttribute("aria-label", cropName + harvestNotice);


    marker.innerHTML =
        `
            ${harvestReady ? '<span class="harvest-alert-badge" aria-label="Harvest ready" title="Ready to harvest"></span>' : ""}
            <img
                src="${iconPath}"
                alt="${escapeHTML(cropName)}"
                draggable="false"
            >

            <span class="crop-bed-number">
                ${escapeHTML(bed.code || `${currentPlot || ""}${bed.number}`)}
            </span>
        `;


    /*
     * CLICK
     *
     * Preserve the existing behavior:
     *
     * - Edit Beds mode = select bed
     * - Normal mode = open crop profile
     */
    marker.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();


            if (bedEditMode) {

                selectBed(marker);

                return;

            }


            openCropProfile(
                Number(marker.dataset.bed),
                marker.dataset.cropId || null
            );

        }
    );


    /*
     * DRAGGING
     *
     * This is intentionally unchanged in behavior.
     */
    marker.addEventListener(
        "mousedown",
        function(event) {

            if (!bedEditMode) {
                return;
            }


            event.preventDefault();

            event.stopPropagation();


            draggingBed =
                marker;


            selectBed(
                marker
            );

        }
    );


    return marker;

}


/* =========================================================
   CREATE DELETED BED PLUS MARKER
========================================================= */

function createDeletedBedSlotMarker(slot) {

    const marker = document.createElement("button");

    marker.type = "button";
    marker.className = "bed-plus-marker";
    marker.dataset.bed = String(slot.number);
    marker.dataset.slotX = String(slot.x);
    marker.dataset.slotY = String(slot.y);
    marker.style.left = `${Number(slot.x) || 0}%`;
    marker.style.top = `${Number(slot.y) || 0}%`;
    marker.title = `Add Crop to Bed ${slot.number}`;

    marker.innerHTML = `
        <i class="fa-solid fa-plus"></i>
        <span>Bed ${escapeHTML(slot.number)} — Add Crop</span>
    `;

    marker.addEventListener("click", function(event) {
        event.preventDefault();
        event.stopPropagation();
        openCropEditorForEmptySlot(slot);
    });

    return marker;
}



/* =========================================================
   RENDER BEDS
========================================================= */

function renderBedMarkers(plot) {

    const container = document.getElementById("bedMarkersContainer");
    if (!container) return;

    container.innerHTML = "";

    const fixedCount = FIXED_PLOT_MARKER_COUNTS[plot] || 0;
    const rows = Array.isArray(dbActiveCropsByPlot[plot]) ? dbActiveCropsByPlot[plot] : [];
    const slots = Array.isArray(deletedBedSlots[plot])
        ? deletedBedSlots[plot].filter(slot => Number(slot.number) >= 1 && Number(slot.number) <= fixedCount)
        : [];
    const deletedNumbers = new Set(slots.map(slot => Number(slot.number)));

    // The database is authoritative: every active crop row gets one marker.
    // If a location has more records than its configured bed slots, extend the
    // visual positions instead of silently dropping crop records.
    let beds = getBedsForPlot(plot);
    if (beds.length < rows.length) {
        const generated = createDefaultBedPositions(rows.length);
        const stored = getStoredBeds();
        const saved = Array.isArray(stored[plot]) ? stored[plot] : [];
        const savedByNumber = new Map(saved.map(bed => [Number(bed.number), bed]));
        beds = generated.map(bed => {
            const savedBed = savedByNumber.get(Number(bed.number));
            return savedBed ? { number: Number(bed.number), x: Number(savedBed.x) || bed.x, y: Number(savedBed.y) || bed.y } : bed;
        });
    }

    /* Assign each authoritative record to exactly one bed. Prefer the
       selected bed code, then fill the next free bed for incomplete legacy
       records. This prevents duplicate codes or missing bed_id values from
       hiding records on the map. */
    const bedByNumber = new Map(beds.map(bed => [Number(bed.number), bed]));
    const usedNumbers = new Set();
    const occupiedBeds = [];
    const plotKey = String(currentPlot || "").toUpperCase();
    const expectedPrefix = ({ A: "A", B: "B", C: "C", NURSERY: "N", HYDRO: "H", TENT_HOUSE: "T", MNO: "M" })[plotKey] || plotKey;
    // Reserve all explicitly selected beds before assigning visual positions
    // to older records that do not yet have a saved bed code.
    const reservedNumbers = new Set(rows.map(row => {
        const code = String(row.bed_code || row.transplanted_area || "").toUpperCase().replace(/\s+/g, "");
        const match = code.match(/^[A-Z]+([0-9]+)$/);
        if (match && code.startsWith(expectedPrefix)) return Number(match[1]);
        if (plotKey === "NURSERY" && row.bed_id != null) {
            const linked = (Array.isArray(window.dbBeds) ? window.dbBeds : []).find(item =>
                String(item.bed_id) === String(row.bed_id) && /^N[0-9]+$/i.test(String(item.bed_code || "")));
            return linked ? Number(linked.bed_number) : 0;
        }
        return 0;
    }).filter(number => number > 0));
    const nextFreeBed = () => beds.find(bed => !usedNumbers.has(Number(bed.number)) && !reservedNumbers.has(Number(bed.number)));
    rows.forEach(row => {
        const code = String(row.bed_code || row.transplanted_area || "")
            .toUpperCase().replace(/\s+/g, "");
        const match = code.match(/^[A-Z]+([0-9]+)$/);
        let bed = null;
        // Nursery records retain their selected bed through the database bed_id.
        // Use it when a legacy Nursery row has no bed_code.
        const linkedBed = plotKey === "NURSERY" && row.bed_id != null
            ? (Array.isArray(window.dbBeds) ? window.dbBeds : []).find(item =>
                String(item.bed_id) === String(row.bed_id) &&
                /^N[0-9]+$/i.test(String(item.bed_code || "")))
            : null;
        const preferredNumber = match && code.startsWith(expectedPrefix)
            ? Number(match[1])
            : (linkedBed ? Number(linkedBed.bed_number) : 0);
        if (preferredNumber > 0 && !usedNumbers.has(preferredNumber)) {
            bed = bedByNumber.get(preferredNumber) || null;
        }
        if (!bed) bed = nextFreeBed();
        if (!bed) return;
        usedNumbers.add(Number(bed.number));
        occupiedBeds.push({ ...bed, cropRow: row });
    });
    occupiedBeds.forEach(bed => container.appendChild(createBedMarker(bed)));
    // Keep the existing +/empty-bed editing behavior for unused slots.
    if (bedEditMode) {
        beds.forEach(bed => {
            if (usedNumbers.has(Number(bed.number))) return;
            if (deletedNumbers.has(Number(bed.number))) return;
            container.appendChild(createDeletedBedSlotMarker(bed));
        });
        slots.forEach(slot => {
            if (!usedNumbers.has(Number(slot.number))) {
                container.appendChild(createDeletedBedSlotMarker(slot));
            }
        });
    }
    updateBedCount();
}



/* =========================================================
   BED COUNT
========================================================= */

function updateBedCount() {

    const count = currentPlot
        ? (FIXED_PLOT_MARKER_COUNTS[currentPlot] || 0)
        : document.querySelectorAll("#bedMarkersContainer .bed-marker").length;

    const sideBeds = document.getElementById("sidePlotBeds");
    if (sideBeds) sideBeds.textContent = count;
}



/* =========================================================
   COLLECT BED POSITIONS
========================================================= */

function collectCurrentBedPositions() {

    if (!currentPlot) return [];

    const fixedCount = FIXED_PLOT_MARKER_COUNTS[currentPlot] || 0;
    const visible = new Map();

    document.querySelectorAll("#bedMarkersContainer .bed-marker").forEach(marker => {
        const number = Number(marker.dataset.bed);
        if (!number) return;
        visible.set(number, {
            number,
            x: parseFloat(marker.style.left) || 0,
            y: parseFloat(marker.style.top) || 0
        });
    });

    const result = getBedsForPlot(currentPlot).map(bed => {
        return visible.get(Number(bed.number)) || {
            number: Number(bed.number),
            x: Number(bed.x) || 0,
            y: Number(bed.y) || 0
        };
    });

    return result
        .filter(bed => bed.number >= 1 && bed.number <= fixedCount)
        .sort((a, b) => a.number - b.number);
}

function saveCurrentBedState() {
    if (!currentPlot) return;

    const stored = getStoredBeds();
    stored[currentPlot] = collectCurrentBedPositions();
    saveStoredBeds(stored);

    const fixedCount = FIXED_PLOT_MARKER_COUNTS[currentPlot] || 0;
    deletedBedSlots[currentPlot] = (deletedBedSlots[currentPlot] || [])
        .filter(slot => Number(slot.number) >= 1 && Number(slot.number) <= fixedCount);
    saveStoredDeletedBedSlots(deletedBedSlots);
}



/* =========================================================
   DATABASE-CONNECTED PLOT SUMMARY
========================================================= */
function updatePlotRecordSummary(plot) {
    const rows = Array.isArray(dbActiveCropsByPlot[plot])
        ? dbActiveCropsByPlot[plot]
        : [];

    const fallbackBeds = Number(FIXED_PLOT_MARKER_COUNTS[plot] || plotData[plot]?.beds || 0);
    const occupiedBeds = new Set();
    let plants = rows.length;
    let soon = 0;
    let ready = 0;

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const soonLimit = new Date(today);
    soonLimit.setDate(soonLimit.getDate() + 14);

    rows.forEach(row => {
        if (row.bed_id !== null && row.bed_id !== undefined && row.bed_id !== "") {
            occupiedBeds.add(String(row.bed_id));
        }

        const status = String(row.status || "").toLowerCase();
        if (status === "ready") {
            ready += 1;
        }

        if (row.expected_harvest && status !== "ready" && status !== "harvested") {
            const harvestDate = new Date(`${row.expected_harvest}T00:00:00`);
            if (!Number.isNaN(harvestDate.getTime()) && harvestDate >= today && harvestDate <= soonLimit) {
                soon += 1;
            }
        }
    });

    const totalBeds = fallbackBeds;
    const occupiedCount = occupiedBeds.size > 0 ? occupiedBeds.size : rows.length;
    const availableBeds = Math.max(0, totalBeds - occupiedCount);
    const plotName = plotData[plot]?.title || `Plot ${plot}`;

    const set = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = Number.isFinite(Number(value))
            ? Number(value).toLocaleString()
            : String(value ?? "--");
    };

    const nameEl = document.getElementById("plotSummaryName");
    if (nameEl) nameEl.textContent = plotName;
    set("plotSummaryPlants", plants);
    set("plotSummaryBeds", availableBeds);
    set("plotSummarySoon", soon);
    set("plotSummaryReady", ready);
}

/* =========================================================
   OPEN PLOT
========================================================= */

function openPlot(plot) {

    const data =
        plotData[plot];


    if (!data) {

        alert(
            "This plot does not have plot information yet."
        );

        return;

    }


    currentPlot =
        plot;


    bedEditMode =
        false;
    bedPositionEditMode = false;


    selectedBed =
        null;


    draggingBed =
        null;


    bedSnapshot =
        null;


    document.getElementById(
        "plotPopup"
    ).classList.remove(
        "bed-editing",
        "nursery-bed-popup"
    );

    const plotSummary = document.getElementById("plotRecordSummary");
    if (plotSummary) plotSummary.style.display = "";

    document.getElementById(
        "plotImage"
    ).src =
        data.image;


    document.getElementById(
        "plotTitle"
    ).textContent =
        data.title;


    updatePlotRecordSummary(plot);

    renderBedMarkers(plot);

    // The plot popup owns the lifecycle Gantt. It uses the same crop records
    // already loaded from the crops table for this plot.
    if (typeof window.renderPlotLifecycleGantt === "function") {
        window.renderPlotLifecycleGantt(plot);
    }


    const plotOverlay = document.getElementById("plotOverlay");
    if (plotOverlay && plotOverlay.parentElement !== document.body) {
        document.body.appendChild(plotOverlay);
    }
    plotOverlay.classList.add("show");


    updateBedEditUI();

    updateSidePlotInfo(plot);

}


/* =========================================================
   CLOSE PLOT
========================================================= */

function closePlot() {

    if (currentPlot === "NURSERY") {
        closeNursery();
        return;
    }

    if (bedEditMode) {

        const confirmed =
            confirm(
                "You are currently editing bed positions. Close without saving?"
            );


        if (!confirmed) return;


        restoreBedSnapshot();

    }


    bedEditMode =
        false;
    bedPositionEditMode = false;


    selectedBed =
        null;


    draggingBed =
        null;


    bedSnapshot =
        null;


    currentPlot =
        null;


    document.getElementById(
        "plotPopup"
    ).classList.remove(
        "bed-editing"
    );


    document.getElementById(
        "plotOverlay"
    ).classList.remove(
        "show"
    );

}


/* =========================================================
   BED SNAPSHOT
========================================================= */

function createBedSnapshot() {

    bedSnapshot =
        JSON.parse(
            JSON.stringify(
                collectCurrentBedPositions()
            )
        );

    deletedBedSlotsSnapshot =
        JSON.parse(
            JSON.stringify(
                deletedBedSlots[currentPlot] || []
            )
        );

}


function restoreBedSnapshot() {

    if (
        !Array.isArray(
            bedSnapshot
        )
    ) {
        return;
    }

    const container =
        document.getElementById(
            "bedMarkersContainer"
        );

    if (!container) {
        return;
    }

    container.innerHTML = "";

    bedSnapshot.forEach(
        bed => {

            container.appendChild(
                createBedMarker(bed)
            );

        }
    );

    deletedBedSlots[currentPlot] =
        Array.isArray(deletedBedSlotsSnapshot)
            ? JSON.parse(
                JSON.stringify(
                    deletedBedSlotsSnapshot
                )
            )
            : [];

    /*
     * Cancel restores the visual state only.
     * The original saved localStorage data is left
     * untouched until the user presses Save Beds.
     */
    deletedBedSlotsSnapshot =
        null;

    updateBedCount();

}


/* =========================================================
   START BED EDITING
========================================================= */

function startBedEditing() {
    if (!MAP_CAN_EDIT_CROPS) return;

    if (!currentPlot) return;

    createBedSnapshot();
    bedEditMode = true;
    bedPositionEditMode = false;
    selectedBed = null;

    // Re-render so unused bed slots become available as + markers while editing.
    renderBedMarkers(currentPlot);

    const popup = document.getElementById("plotPopup");
    if (popup) popup.classList.add("bed-editing");

    document.querySelectorAll("#bedMarkersContainer .bed-marker").forEach(marker => {
        marker.classList.add("editing");
    });

    updateBedEditUI();
}

function toggleBedPositionEditing() {
    if (!MAP_CAN_EDIT_CROPS) return;
    if (!bedEditMode) return;

    bedPositionEditMode = !bedPositionEditMode;
    const button = document.getElementById("editPositionBtn");

    if (button) {
        button.classList.toggle("active", bedPositionEditMode);
        button.innerHTML = bedPositionEditMode
            ? '<i class="fa-solid fa-check"></i> Position Editing'
            : '<i class="fa-solid fa-up-down-left-right"></i> Edit Position';
    }

    document.querySelectorAll("#bedMarkersContainer .bed-marker").forEach(marker => {
        marker.classList.toggle("position-editing", bedPositionEditMode);
    });

    showFarmMapToast(bedPositionEditMode ? "Drag a crop marker to move it" : "Position editing off");
    updateBedEditUI();
}



/* =========================================================
   BED EDIT UI
========================================================= */

function updateBedEditUI() {

    const editButton = document.getElementById("startEditBedsBtn");
    const addButton = document.getElementById("addBedBtn");
    const deleteButton = document.getElementById("deleteBedBtn");
    const saveButton = document.getElementById("saveBedsBtn");
    const cancelButton = document.getElementById("cancelBedsBtn");
    const cropButton = document.getElementById("editSelectedCropBtn");
    const positionButton = document.getElementById("editPositionBtn");
    const toolbar = document.getElementById("bedEditToolbar");
    const status = document.getElementById("bedEditStatus");

    if (!editButton) return;

    if (!bedEditMode) {
        editButton.style.display = "inline-flex";
        editButton.classList.remove("is-done");
        editButton.innerHTML = '<i class="fa-solid fa-pen"></i> Edit Plot';
        if (addButton) addButton.style.display = "none";
        if (deleteButton) deleteButton.style.display = "none";
        if (saveButton) saveButton.style.display = "none";
        if (cancelButton) cancelButton.style.display = "none";
        if (cropButton) cropButton.style.display = "none";
        if (positionButton) positionButton.style.display = "none";
        if (toolbar) toolbar.classList.remove("is-editing");
        if (status) status.textContent = "Click a crop marker to view its profile.";
        return;
    }

    editButton.style.display = "inline-flex";
    editButton.classList.add("is-done");
    editButton.innerHTML = '<i class="fa-solid fa-check"></i> Done Editing';
    if (addButton) addButton.style.display = "none";
    if (deleteButton) deleteButton.style.display = "inline-flex";
    if (saveButton) saveButton.style.display = "none";
    if (cancelButton) cancelButton.style.display = "none";
    if (cropButton) cropButton.style.display = "inline-flex";
    if (positionButton) positionButton.style.display = "inline-flex";
    if (toolbar) toolbar.classList.add("is-editing");

    if (status) {
        status.textContent = selectedBed
            ? `Bed ${selectedBed.dataset.bed} selected — use Edit Position to move it.`
            : "Select a crop marker to edit, move, or delete it.";
    }
}



/* =========================================================
   SELECT BED
========================================================= */

function selectBed(marker) {

    document.querySelectorAll("#bedMarkersContainer .bed-marker").forEach(item => {
        item.classList.remove("selected");
    });

    selectedBed = marker;
    marker.classList.add("selected");
    updateBedEditUI();
}

function editSelectedCrop() {
    if (!MAP_CAN_EDIT_CROPS) return;
    if (!bedEditMode || !selectedBed) {
        showFarmMapToast("Select a crop marker first");
        return;
    }

    openCropProfile(Number(selectedBed.dataset.bed));
    startCropProfileEditing();
}



/* =========================================================
   DRAG BED
========================================================= */

document.addEventListener(
    "mousemove",
    function(event) {

        if (
            !draggingBed ||
            !bedEditMode ||
            !bedPositionEditMode
        ) {
            return;
        }


        const popupMap =
            document.querySelector(
                ".popup-map"
            );


        if (!popupMap) return;


        const rect =
            popupMap.getBoundingClientRect();


        const x =
            Math.max(
                2,
                Math.min(
                    98,
                    (
                        (event.clientX - rect.left) /
                        rect.width
                    ) * 100
                )
            );


        const y =
            Math.max(
                2,
                Math.min(
                    98,
                    (
                        (event.clientY - rect.top) /
                        rect.height
                    ) * 100
                )
            );


        draggingBed.style.left =
            `${x}%`;


        draggingBed.style.top =
            `${y}%`;

    }
);


document.addEventListener(
    "mouseup",
    function() {

        if (draggingBed && bedEditMode && bedPositionEditMode) {
            saveCurrentBedState();
            showFarmMapToast("Crop position saved");
        }

        draggingBed = null;

    }
);


/* =========================================================
   FIXED BED/CROP SLOTS
========================================================= */

function addBedAtSlot(slot) {
    if (!MAP_CAN_EDIT_CROPS) return;
    /* Kept as a compatibility function for older calls.
       New slots are crop profile slots, not new beds. */
    openCropEditorForEmptySlot(slot);
}

function addBed() {
    if (!MAP_CAN_EDIT_CROPS) return;
    showFarmMapToast("Bed slots are fixed for this plot");
}

function deleteSelectedBed() {
    if (!MAP_CAN_EDIT_CROPS) return;

    if (!bedEditMode || !selectedBed) {
        showFarmMapToast("Select a crop marker first");
        return;
    }

    const number = Number(selectedBed.dataset.bed);
    const x = parseFloat(selectedBed.style.left) || 0;
    const y = parseFloat(selectedBed.style.top) || 0;

    if (!confirm(`Remove the crop from Bed ${number}?`)) return;

    // The marker is created from the authoritative database row and carries
    // its exact crop ID. Use it directly; looking up by array position can
    // archive the wrong Nursery crop when beds are sparse or out of order.
    const markerCropId = selectedBed.dataset.cropId || null;
    const crop = getCropProfile(number, markerCropId);
    const cropId = markerCropId
        ? Number(markerCropId)
        : (crop && crop.cropId ? Number(crop.cropId) : 0);

    // A planted crop must be archived in the database, not merely hidden
    // from this browser. Empty beds can still be cleared without a request.
    const archiveRequest = cropId
        ? fetch("../crops/archive_crop_from_map.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({ crop_id: String(cropId) })
        }).then(response => response.json())
        : Promise.resolve({ success: true, empty: true });

    archiveRequest.then(result => {
        if (!result || !result.success) {
            alert(result?.message || "Unable to archive this crop. The bed was not changed.");
            return;
        }

        const slots = Array.isArray(deletedBedSlots[currentPlot])
            ? deletedBedSlots[currentPlot]
            : [];

        deletedBedSlots[currentPlot] = slots.filter(
            slot => Number(slot.number) !== number
        );
        deletedBedSlots[currentPlot].push({ number, x, y });

        selectedBed = null;
        saveCurrentBedState();
        location.reload();
    }).catch(error => {
        console.error("Crop archive failed:", error);
        alert("Unable to archive this crop. Please check your connection and try again.");
    });
}



/* =========================================================
   SAVE BEDS
========================================================= */

function saveBeds() {
    if (!MAP_CAN_EDIT_CROPS) return;
    finishBedEditing();
}


/* =========================================================
   CANCEL BED EDITING
========================================================= */

function cancelBedEditing() {
    /* Bed changes are auto-saved. Keep this function as a safe
       compatibility hook for any existing code that calls it. */
    finishBedEditing();
}


/* =========================================================
   =========================================================
   CROP PROFILE SYSTEM
   =========================================================
========================================================= */


/*
|--------------------------------------------------------------------------
| CROP PROFILE STORAGE
|--------------------------------------------------------------------------
|
| Data is currently saved in localStorage.
| This allows the farmer to:
|
| - upload a crop picture
| - update crop details
| - enter planted date
| - enter weeks planted
| - change status
|
*/

const CROP_STORAGE_KEY =
    "sanctuarioCropProfiles";


let currentCropBed =
    null;
let currentCropId =
    null;


let cropProfileSnapshot =
    null;

let cropEditingEmptySlot = false;
let cropEmptySlotSnapshot = null;


function getStoredCropProfiles() {

    try {

        const value =
            JSON.parse(
                localStorage.getItem(
                    CROP_STORAGE_KEY
                )
            );


        return value &&
               typeof value === "object" &&
               !Array.isArray(value)
            ? value
            : {};

    } catch (error) {

        console.error(
            "Unable to load crop profiles:",
            error
        );

        return {};

    }

}


function saveStoredCropProfiles(data) {

    localStorage.setItem(
        CROP_STORAGE_KEY,
        JSON.stringify(data)
    );

}


/* =========================================================
   CROP DATA (FROM DATABASE)
========================================================= */

/*
| dbCropsByPlot is embedded by PHP at page load from the
| crops table (WHERE archived = 0), grouped by plot and
| ordered by crop_id. Bed marker "N" within a plot shows
| the Nth active crop record for that plot.
*/

function statusDbToLabel(status) {
    const normalized = String(status || "").trim().toLowerCase();
    if (normalized === "harvested" || normalized.includes("harvested")) return "Harvested";
    if (normalized === "ready" || normalized.includes("ready for harvest") || normalized.includes("ready")) return "Ready for Harvest";
    if (normalized === "growing" || normalized.includes("growing")) return "Growing";
    return "Planted";
}

function statusLabelToDb(label) {

    const map = {
        "Planted": "planted",
        "Growing": "growing",
        "Ready for Harvest": "ready",
        "Harvested": "harvested"
    };

    return map[label] || "planted";
}

function dbCropToProfile(row) {
    const isNurseryRow =
        String(row.transplanted_area || "").trim().toUpperCase() === "NURSERY" ||
        String(row.plot_name || "").trim().toUpperCase() === "NURSERY" ||
        String(row.plot_code || "").trim().toLowerCase() === "nursery";

    return {
    cropId: row.crop_id,

    name:
        (row.crop_name || "Unnamed Crop").toUpperCase(),

    variety:
        row.variety || "No variety",

    staff:
        row.recipient || "Not assigned",

    pcs:
        row.pcs != null
            ? String(row.pcs)
            : "0",

    dateSowing:
        row.date_sowing || "",

    germination:
        row.germination_rate != null
            ? `${row.germination_rate}%`
            : "0%",

    targetTransplantDate:
        row.target_transplant_date || "",

    dateTransplanted:
        row.date_transplanted || "",

    targetArea:
        row.target_area || "",

    seedlingsReceived:
        row.seedlings_received != null
            ? String(row.seedlings_received)
            : "0",

    area:
        row.transplanted_area ||
        row.target_area ||
        (currentPlot || ""),

    plantedDate:
        (isNurseryRow ? row.date_sowing : row.date_transplanted) ||
        (isNurseryRow ? "" : row.date_sowing) ||
        getTodayDate(),

    harvest:
        row.expected_harvest || "",

    status:
        statusDbToLabel(row.status),

    image: row.image || ""
};
}


/* =========================================================
   GET CROP PROFILE
========================================================= */

function getCropProfile(bedNumber, cropId = null) {

    const plotCrops = dbActiveCropsByPlot[currentPlot] || [];
    const requestedCode = `${String(currentPlot || '').toUpperCase()}${Number(bedNumber)}`.replace('PLOT_', '');
    const row = (cropId != null
        ? plotCrops.find(item => String(item.crop_id) === String(cropId))
        : null) || plotCrops.find(item => {
        const code = String(item.bed_code || item.transplanted_area || '').toUpperCase().replace(/\s+/g, '');
        return code === requestedCode || code.endsWith(requestedCode);
    }) || plotCrops.find(item => !item.bed_code && !/^[A-Za-z]+\s*[0-9]+$/.test(String(item.transplanted_area || ''))) || plotCrops[bedNumber - 1];

    const stored = getStoredCropProfiles();
    const key = `${currentPlot}-${bedNumber}`;

    const storedImage =
        stored[key] && stored[key].image
            ? stored[key].image
            : "";

    if (row) {
        const profile = dbCropToProfile(row);
        profile.image = row.image || storedImage;
        return profile;
    }

    return {

        cropId: null,

        name: "Unassigned Crop",

        variety: "No variety assigned",

        staff: "Not assigned",

        pcs: "0",

        germination: "0%",

        area: `${currentPlot || ""}${bedNumber}`,

        plantedDate: getTodayDate(),

        harvest: "",

        status: "Planted",

        image: storedImage

    };

}



/* =========================================================
   DATE HELPERS
========================================================= */

function getTodayDate() {

    const now =
        new Date();


    const year =
        now.getFullYear();


    const month =
        String(
            now.getMonth() + 1
        ).padStart(2, "0");


    const day =
        String(
            now.getDate()
        ).padStart(2, "0");


    return `${year}-${month}-${day}`;

}


function parseDateLocal(dateString) {

    if (!dateString) {
        return null;
    }


    const parts =
        dateString.split("-");


    if (
        parts.length !== 3
    ) {
        return null;
    }


    const date =
        new Date(
            Number(parts[0]),
            Number(parts[1]) - 1,
            Number(parts[2])
        );


    if (
        Number.isNaN(
            date.getTime()
        )
    ) {
        return null;
    }


    return date;

}


/* =========================================================
   CALCULATE DAYS PLANTED
========================================================= */

function calculateDaysPlanted(plantedDate) {

    const start =
        parseDateLocal(
            plantedDate
        );


    if (!start) {
        return 0;
    }


    const today =
        new Date();


    const todayLocal =
        new Date(
            today.getFullYear(),
            today.getMonth(),
            today.getDate()
        );


    const difference =
        todayLocal.getTime() -
        start.getTime();


    return Math.max(
        0,
        Math.floor(
            difference /
            (1000 * 60 * 60 * 24)
        )
    );

}


/* =========================================================
   FORMAT DATE
========================================================= */

function formatDate(dateString) {

    const date =
        parseDateLocal(
            dateString
        );


    if (!date) {
        return "--";
    }


    return date.toLocaleDateString(
        "en-US",
        {
            month: "short",
            day: "numeric",
            year: "numeric"
        }
    );

}


/* =========================================================
   STATUS CLASS
========================================================= */

function getStatusClass(status) {

    switch (
        String(status || "")
            .toLowerCase()
    ) {

        case "planted":
            return "planted";

        case "growing":
            return "growing";

        case "ready for harvest":
            return "ready";

        case "harvested":
            return "harvested";

        case "failed":
            return "failed";

        default:
            return "";

    }

}


/* =========================================================
   OPEN CROP PROFILE
========================================================= */

function openCropProfile(bedNumber, cropId = null) {

    if (!currentPlot) {
        return;
    }


    currentCropBed =
        bedNumber;

    cropEditingEmptySlot = false;
    cropEmptySlotSnapshot = null;

    const crop =
        getCropProfile(
            bedNumber,
            cropId
        );
    currentCropId = crop && crop.cropId ? Number(crop.cropId) : null;


    renderCropProfile(
        crop
    );


    document.getElementById(
        "cropProfileOverlay"
    ).classList.add(
        "show"
    );


    document.getElementById(
        "cropEditForm"
    ).classList.remove(
        "show"
    );


    setCropProfileEditButtons(
        false
    );

}


/* =========================================================
   RENDER CROP PROFILE
========================================================= */

function renderCropProfile(crop) {

    const isNurseryProfile = String(currentPlot || "").toUpperCase() === "NURSERY";

    const targetAreaGroup = document.getElementById("profileCropTargetAreaGroup");
    if (targetAreaGroup) targetAreaGroup.style.display = isNurseryProfile ? "" : "none";
    const targetAreaValue = document.getElementById("profileCropTargetArea");
    if (targetAreaValue) targetAreaValue.textContent = crop.targetArea || "--";

    const profileDateLabel = document.getElementById("profileCropDateLabel");
    if (profileDateLabel) {
        profileDateLabel.textContent =
            isNurseryProfile
                ? "Date of Sowing"
                : "Date Transplanted";
    }

    const lifecycleDateLabel = document.getElementById("profileCropLifecycleDateLabel");
    if (lifecycleDateLabel) {
        lifecycleDateLabel.textContent =
            isNurseryProfile ? "Target Transplant Date" : "Expected Harvest";
    }
    const harvestStat = document.getElementById("profileCropHarvestStat");
    if (harvestStat) harvestStat.style.display = isNurseryProfile ? "" : "none";

    const days =
        calculateDaysPlanted(
            crop.plantedDate
        );


    const weeks =
        Math.floor(
            days / 7
        );



    document.getElementById(
        "profileCropName"
    ).textContent =
        crop.name || "Unassigned Crop";


    document.getElementById(
        "profileCropStaff"
    ).textContent =
        crop.staff || "Not assigned";


    document.getElementById(
        "profileCropPCS"
    ).textContent =
        crop.pcs || "0";


    document.getElementById(
        "profileCropGermination"
    ).textContent =
        crop.germination || "0%";


    document.getElementById(
        "profileCropArea"
    ).textContent =
        crop.area || "--";


    document.getElementById(
        "profileCropPlantedDate"
    ).textContent =
        formatDate(
            crop.plantedDate
        );

    document.getElementById("profileCropTargetTransplantDate").textContent =
        formatDate(isNurseryProfile ? crop.targetTransplantDate : crop.harvest);


    document.getElementById(
        "profileCropHarvest"
    ).textContent =
        formatDate(
            crop.harvest
        );


    const statusElement =
        document.getElementById(
            "profileCropStatus"
        );


    statusElement.textContent =
        crop.status || "Planted";


    statusElement.className =
        "crop-status " +
        getStatusClass(
            crop.status
        );


    document.getElementById(
        "profileDaysPlanted"
    ).textContent =
        `${days} ${
            days === 1
                ? "day"
                : "days"
        }`;


    document.getElementById(
        "profileWeeksPlanted"
    ).textContent =
        `${weeks} ${
            weeks === 1
                ? "week"
                : "weeks"
        }`;


    document.getElementById(
        "cropTimelineText"
    ).textContent =
        `${days} days planted • ${weeks} weeks`;


    /*
     * Progress is based on a 12-week
     * general visualization.
     *
     * This is only a visual timeline.
     */

    const progress =
        Math.min(
            100,
            (weeks / 12) * 100
        );


    document.getElementById(
        "cropProgressBar"
    ).style.width =
        `${progress}%`;


    updateCropImage(
        crop.image
    );
    const harvestButton = document.getElementById("harvestCropBtn");
    if (harvestButton) {
        const allowed = ["A", "B", "C", "MNO", "HYDRO", "TENT_HOUSE"].includes(String(currentPlot || "").toUpperCase());
        harvestButton.style.display = allowed && !!crop.cropId ? "inline-flex" : "none";
    }
    const transplantButton = document.getElementById("transplantCropBtn");
    if (transplantButton) {
        const profileLocation = String(currentPlot || "").trim().toUpperCase().replace(/[\s-]+/g, "_");
        // Keep the action visible on the Nursery crop board. The click handler
        // still validates that the selected bed has a database crop record.
        transplantButton.style.display = profileLocation === "NURSERY" ? "inline-flex" : "none";
        transplantButton.dataset.cropId = crop && crop.cropId ? String(crop.cropId) : "";
    }
    cancelCropHarvestForm();

}


/* =========================================================
   UPDATE CROP IMAGE
========================================================= */

function updateCropImage(image) {
    const img = document.getElementById("cropProfileImage");
    const placeholder = document.getElementById("cropImagePlaceholder");
    if (!img || !placeholder) return;
    const raw = String(image || "").trim();
    if (!raw) {
        img.removeAttribute("src");
        img.style.display = "none";
        placeholder.style.display = "flex";
        return;
    }
    let imageUrl = raw;
    if (!/^(?:data:|blob:|https?:\/\/|\/)/i.test(raw)) {
        let relativePath = raw.replace(/^\.\//, "");
        if (relativePath.startsWith("uploads/crop_images/")) {
            relativePath = "crops/" + relativePath;
        }
        imageUrl = "<?= $basePath ?>" + relativePath;
    }
    img.onload = function () {
        img.style.display = "block";
        placeholder.style.display = "none";
    };
    img.onerror = function () {
        img.style.display = "none";
        placeholder.style.display = "flex";
        console.error("Crop image could not be loaded:", imageUrl);
    };
    img.src = imageUrl;
}


/* =========================================================
   TRANSPLANT NURSERY CROP
========================================================= */
function closeFarmMapTransferModal() {
    const overlay = document.getElementById("farmMapTransferOverlay");
    const frame = document.getElementById("farmMapTransferFrame");
    if (overlay) overlay.classList.remove("show");
    if (frame) frame.src = "about:blank";
}
// transfer_crop.php calls this shared name after a successful transfer.
window.closeEditModal = closeFarmMapTransferModal;
function openCropTransplantForm() {
    const transplantButton = document.getElementById("transplantCropBtn");
    const cropId = transplantButton?.dataset.cropId || null;
    const crop = getCropProfile(currentCropBed, cropId);
    const profileLocation = String(currentPlot || "").trim().toUpperCase().replace(/[\s-]+/g, "_");
    if (profileLocation !== "NURSERY") return;
    if (!crop || !crop.cropId) {
        alert("This Nursery bed does not have a database crop record yet.");
        return;
    }
    const frame = document.getElementById("farmMapTransferFrame");
    const overlay = document.getElementById("farmMapTransferOverlay");
    if (!frame || !overlay) return;
    frame.src = `../crops/transfer_crop.php?id=${encodeURIComponent(crop.cropId)}`;
    overlay.classList.add("show");
}

/* =========================================================
   HARVEST CROP
========================================================= */
function cancelCropHarvestForm() {
    const form = document.getElementById("cropHarvestForm");
    if (form) form.classList.remove("show");
}
function openCropHarvestForm() {
    const crop = getCropProfile(currentCropBed);
    if (!crop || !crop.cropId) { alert("This crop does not have a database record yet."); return; }
    const allowed = ["A", "B", "C", "MNO", "HYDRO", "TENT_HOUSE"].includes(String(currentPlot || "").toUpperCase());
    if (!allowed) { alert("Harvest records are available only for Plot A, Plot B, Plot C, Hydro, MNO, and Tent House."); return; }
    document.getElementById("harvestDate").value = getTodayDate();
    document.getElementById("harvestCount").value = "1";
    ["harvestTotalQuantity","harvestTotalUnit","harvestSalesQuantity","harvestSalesUnit","harvestSalesAmount","harvestFarmUseQuantity","harvestFarmUseUnit","harvestAnimalFeedQuantity","harvestAnimalFeedUnit","harvestRejectedQuantity","harvestRejectedUnit","harvestOtherQuantity","harvestOtherUnit"].forEach(id => { const el = document.getElementById(id); if (el) el.value = ""; });
    document.getElementById("cropHarvestForm").classList.add("show");
}
function saveCropHarvest() {
    if (!MAP_CAN_EDIT_CROPS) return;
    const crop = getCropProfile(currentCropBed);
    if (!crop || !crop.cropId) return;
    const date = document.getElementById("harvestDate").value;
    const count = document.getElementById("harvestCount").value;
    if (!date || !count || Number(count) < 1) { alert("Enter the total number of harvest and date of pull / harvest."); return; }
    const formData = new FormData();
    formData.append("crop_id", crop.cropId);
    formData.append("location", currentPlot);
    formData.append("harvest_date", date);
    formData.append("harvest_count", count);
    const fields = {
        harvestTotalQuantity: "total_quantity", harvestTotalUnit: "total_unit",
        harvestSalesQuantity: "sales_quantity", harvestSalesUnit: "sales_unit", harvestSalesAmount: "sales_amount",
        harvestFarmUseQuantity: "farm_use_quantity", harvestFarmUseUnit: "farm_use_unit",
        harvestAnimalFeedQuantity: "animal_feed_quantity", harvestAnimalFeedUnit: "animal_feed_unit",
        harvestRejectedQuantity: "rejected_quantity", harvestRejectedUnit: "rejected_unit",
        harvestOtherQuantity: "other_purposes_quantity", harvestOtherUnit: "other_purposes_unit"
    };
    Object.entries(fields).forEach(([id, key]) => formData.append(key, document.getElementById(id).value.trim()));
    const saveButton = document.getElementById("saveCropHarvestBtn");
    saveButton.disabled = true;
    fetch("save_harvest.php", { method: "POST", body: formData })
        .then(response => response.json())
        .then(result => {
            if (!result.success) throw new Error(result.message || "Unable to save harvest record.");
            alert("Harvest record saved successfully.");
            location.reload();
        })
        .catch(error => { alert(error.message || "Unable to save harvest record. Please try again."); saveButton.disabled = false; });
}

/* =========================================================
   EDIT CROP PROFILE
========================================================= */

function openCropEditorForEmptySlot(slot) {
    if (!MAP_CAN_EDIT_CROPS) return;

    if (!currentPlot || !slot) return;

    currentCropBed = Number(slot.number);
    cropEditingEmptySlot = true;
    cropEmptySlotSnapshot = {
        number: Number(slot.number),
        x: Number(slot.x) || 0,
        y: Number(slot.y) || 0
    };

    /* Make the fixed slot active visually while its profile is being filled. */
    const slots = Array.isArray(deletedBedSlots[currentPlot])
        ? deletedBedSlots[currentPlot]
        : [];

    deletedBedSlots[currentPlot] = slots.filter(
        item => Number(item.number) !== Number(slot.number)
    );

    const existingMarker = document.querySelector(
        `#bedMarkersContainer .bed-marker[data-bed="${Number(slot.number)}"]`
    );

    if (!existingMarker) {
        const marker = createBedMarker({
            number: Number(slot.number),
            x: Number(slot.x) || 0,
            y: Number(slot.y) || 0
        });
        marker.classList.add("editing", "selected", "temporary-crop-slot");
        document.getElementById("bedMarkersContainer")?.appendChild(marker);
        selectedBed = marker;
    }

    startCropProfileEditing();
    document.getElementById("cropProfileOverlay").classList.add("show");
    document.getElementById("cropEditForm").classList.add("show");
    setCropProfileEditButtons(true);
}

function startCropProfileEditing() {
    if (!MAP_CAN_EDIT_CROPS) return;

    const crop =
        getCropProfile(
            currentCropBed,
            currentCropId
        );


    /*
     * Save the current marker state so Cancel
     * can restore it.
     */
    const beds =
        getBedsForPlot(
            currentPlot
        );


    const currentBed =
        beds.find(
            bed =>
                Number(bed.number) ===
                Number(currentCropBed)
        );


    cropProfileSnapshot = {

        icon: getCropMarkerIconKey(currentCropBed, crop),

        x: cropEditingEmptySlot && cropEmptySlotSnapshot
            ? Number(cropEmptySlotSnapshot.x) || 0
            : (currentBed ? Number(currentBed.x) || 0 : 0),

        y: cropEditingEmptySlot && cropEmptySlotSnapshot
            ? Number(cropEmptySlotSnapshot.y) || 0
            : (currentBed ? Number(currentBed.y) || 0 : 0)
    };


    document.getElementById(
        "editCropName"
    ).value =
        crop.name || "";


    document.getElementById(
        "editCropVariety"
    ).value =
        crop.variety || "";


    document.getElementById(
        "editCropStaff"
    ).value =
        crop.staff || "";


    document.getElementById(
        "editCropPCS"
    ).value =
        crop.pcs || "";




    document.getElementById(
        "editCropSeedlings"
    ).value =
        crop.seedlingsReceived || "0";


    document.getElementById(
        "editCropGermination"
    ).value =
        crop.germination || "";


    document.getElementById(
        "editCropArea"
    ).value =
        crop.area || "";


    document.getElementById(
        "editCropPlantedDate"
    ).value =
        crop.plantedDate ||
        getTodayDate();

    const editDateLabel = document.getElementById("editCropDateLabel");
    if (editDateLabel) {
        editDateLabel.textContent =
            String(currentPlot || "").toUpperCase() === "NURSERY"
                ? "Date of Sowing"
                : "Date Transplanted";
    }

    document.getElementById("editCropTargetTransplantDate").value =
        crop.targetTransplantDate || "";

    const targetAreaEditGroup = document.getElementById("editCropTargetAreaGroup");
    const targetAreaSelect = document.getElementById("editCropTargetArea");
    const nurseryTargetEditor = String(currentPlot || "").toUpperCase() === "NURSERY";
    if (targetAreaEditGroup) targetAreaEditGroup.style.display = nurseryTargetEditor ? "" : "none";
    if (targetAreaSelect) {
        const savedTarget = String(crop.targetArea || "").trim();
        const match = Array.from(targetAreaSelect.options).find(o => o.value.toLowerCase() === savedTarget.toLowerCase());
        targetAreaSelect.value = match ? match.value : "";
    }

    const isNurseryEditor = String(currentPlot || "").toUpperCase() === "NURSERY";
    document.getElementById("editCropTargetTransplantGroup").style.display =
        isNurseryEditor ? "" : "none";

    const requireNurseryDates =
        cropEditingEmptySlot && isNurseryEditor;
    document.getElementById("editCropTargetTransplantDate").required = requireNurseryDates;
    document.getElementById("editCropHarvest").required = requireNurseryDates;




    document.getElementById(
        "editCropHarvest"
    ).value =
        crop.harvest || "";


    document.getElementById(
        "editCropStatus"
    ).value =
        crop.status || "Planted";


    /*
     * Select current crop icon.
     */
    const iconKey =
        cropProfileSnapshot.icon;


    const iconInput =
        document.querySelector(
            `input[name="cropMarkerIcon"][value="${iconKey}"]`
        );


    if (iconInput) {

        iconInput.checked =
            true;

    }


    /*
     * Immediately update the marker when
     * the user chooses an icon.
     */
    document
        .querySelectorAll(
            'input[name="cropMarkerIcon"]'
        )
        .forEach(
            input => {

                input.onchange =
                    function() {

                        updateCurrentCropMarkerIcon(
                            this.value
                        );

                    };

            }
        );


    /*
     * Immediately update marker X/Y while editing.
     */
    const xInput =
        document.getElementById(
            "editCropMarkerX"
        );

    const yInput =
        document.getElementById(
            "editCropMarkerY"
        );


    if (xInput) {

        xInput.oninput =
            updateCurrentCropMarkerPosition;

    }


    if (yInput) {

        yInput.oninput =
            updateCurrentCropMarkerPosition;

    }


    document.getElementById(
        "cropEditForm"
    ).classList.add(
        "show"
    );


    setCropProfileEditButtons(
        true
    );

}

function updateCurrentCropMarkerIcon(
    iconKey
) {

    if (!currentPlot || !currentCropBed) {
        return;
    }


    if (
        !FARM_MARKER_ICONS.crops[iconKey]
    ) {
        return;
    }


    const stored =
        getStoredCropMarkerIcons();


    const key =
        `${currentPlot}-${currentCropBed}`;


    stored[key] =
        iconKey;


    saveStoredCropMarkerIcons(
        stored
    );


    /*
     * Find the currently visible bed marker.
     */
    const marker =
        document.querySelector(
            `#bedMarkersContainer .bed-marker[data-bed="${currentCropBed}"]`
        );


    if (!marker) {
        return;
    }


    const image =
        marker.querySelector("img");


    if (image) {

        image.src =
            FARM_MARKER_ICONS.crops[
                iconKey
            ];

    }


    const crop =
        getCropProfile(
            currentCropBed
        );


    marker.title =
        crop.name ||
        `Bed ${currentCropBed}`;

}


function updateCurrentCropMarkerPosition() {

    if (!currentPlot || !currentCropBed) {
        return;
    }


    const xInput =
        document.getElementById(
            "editCropMarkerX"
        );

    const yInput =
        document.getElementById(
            "editCropMarkerY"
        );


    if (!xInput || !yInput) {
        return;
    }


    const x =
        Math.max(
            0,
            Math.min(
                100,
                Number(xInput.value) || 0
            )
        );


    const y =
        Math.max(
            0,
            Math.min(
                100,
                Number(yInput.value) || 0
            )
        );


    const marker =
        document.querySelector(
            `#bedMarkersContainer .bed-marker[data-bed="${currentCropBed}"]`
        );


    if (!marker) {
        return;
    }


    marker.style.left =
        `${x}%`;


    marker.style.top =
        `${y}%`;

}


/* =========================================================
   EDIT BUTTON UI
========================================================= */

function setCropProfileEditButtons(editing) {

    const cropImageActions = document.getElementById("cropImageActions");
    if (cropImageActions) {
        cropImageActions.style.display = editing ? "flex" : "none";
    }

    document.getElementById(
        "editCropProfileBtn"
    ).style.display =
        editing
            ? "none"
            : "inline-flex";


    document.getElementById(
        "saveCropProfileBtn"
    ).style.display =
        editing
            ? "inline-flex"
            : "none";


    document.getElementById(
        "cancelCropProfileBtn"
    ).style.display =
        editing
            ? "inline-flex"
            : "none";

}


/* =========================================================
   SAVE CROP PROFILE
========================================================= */

function saveCropProfile() {
    if (!MAP_CAN_EDIT_CROPS) return;

    if (!currentPlot || !currentCropBed) {
        return;
    }


    const plantedDate =
        document.getElementById(
            "editCropPlantedDate"
        ).value;


    const weeksInput = document.getElementById("editCropWeeks");
    let weeks = weeksInput
        ? Number(weeksInput.value)
        : Math.floor(calculateDaysPlanted(plantedDate) / 7);


    if (
        !Number.isFinite(weeks) ||
        weeks < 0
    ) {
        weeks = 0;
    }


    /*
     * If Weeks Planted is changed,
     * calculate the corresponding
     * Date Transplanted.
     */

    let finalPlantedDate =
        plantedDate;


    const currentWeeks =
        Math.floor(
            calculateDaysPlanted(
                plantedDate
            ) / 7
        );


    if (
        weeks !== currentWeeks
    ) {

        const today =
            new Date();


        today.setHours(
            0,
            0,
            0,
            0
        );


        today.setDate(
            today.getDate() -
            (weeks * 7)
        );


        finalPlantedDate =
            `${today.getFullYear()}-${
                String(
                    today.getMonth() + 1
                ).padStart(2, "0")
            }-${
                String(
                    today.getDate()
                ).padStart(2, "0")
            }`;
    }


    const existing =
        getCropProfile(
            currentCropBed,
            currentCropId
        );

    const isNurseryCrop = String(currentPlot).toUpperCase() === "NURSERY";
    if (cropEditingEmptySlot && isNurseryCrop) {
        const name = document.getElementById("editCropName").value.trim();
        const targetDate = document.getElementById("editCropTargetTransplantDate").value;
        const harvestDate = document.getElementById("editCropHarvest").value;
        if (!name || !targetDate || !harvestDate) {
            alert("Enter the crop name, target transplant date, and expected harvest date before saving.");
            return;
        }
    }
/*
 * SAVE CROP MARKER ICON
 */

const selectedCropIcon =
    document.querySelector(
        'input[name="cropMarkerIcon"]:checked'
    );


if (
    selectedCropIcon &&
    FARM_MARKER_ICONS.crops[
        selectedCropIcon.value
    ]
) {

    const storedIcons =
        getStoredCropMarkerIcons();


    const iconKey =
        `${currentPlot}-${currentCropBed}`;


    storedIcons[iconKey] =
        selectedCropIcon.value;


    saveStoredCropMarkerIcons(
        storedIcons
    );

}


/*
 * SAVE CROP MARKER POSITION
 */

const cropMarker =
    document.querySelector(
        `#bedMarkersContainer .bed-marker[data-bed="${currentCropBed}"]`
    );


if (cropMarker) {

    const beds =
        getBedsForPlot(
            currentPlot
        );


    const bed =
        beds.find(
            item =>
                Number(item.number) ===
                Number(currentCropBed)
        );


    if (bed) {

        bed.x =
            parseFloat(
                cropMarker.style.left
            ) || 0;


        bed.y =
            parseFloat(
                cropMarker.style.top
            ) || 0;

    }

}

    /*
     * CREATE FORM DATA
     */

    if (cropEditingEmptySlot) {
        const slots = Array.isArray(deletedBedSlots[currentPlot])
            ? deletedBedSlots[currentPlot]
            : [];
        deletedBedSlots[currentPlot] = slots.filter(
            slot => Number(slot.number) !== Number(currentCropBed)
        );
        saveCurrentBedState();
    }

    const formData =
        new FormData();


    formData.append(
        "crop_id",
        cropEditingEmptySlot ? "" : (existing.cropId || "")
    );

    // An empty Nursery bed has no crop_id yet; explicitly request creation so
    // the server can validate its real plot/bed and insert the linked records.
    if (cropEditingEmptySlot && String(currentPlot).toUpperCase() === "NURSERY") {
        formData.append("create_crop", "1");
    }


    formData.append(
        "plot_letter",
        currentPlot
    );


    formData.append(
        "recipient",
        document.getElementById(
            "editCropStaff"
        ).value.trim() ||
        "Not assigned"
    );


    formData.append(
        "crop_name",
        document.getElementById(
            "editCropName"
        ).value.trim() ||
        "Unnamed Crop"
    );


    formData.append(
        "variety",
        document.getElementById(
            "editCropVariety"
        ).value.trim() ||
        "No variety"
    );


    formData.append(
        "pcs",
        parseInt(
            document.getElementById(
                "editCropPCS"
            ).value,
            10
        ) || 0
    );


    formData.append(
        "date_sowing",
        isNurseryCrop
            ? (finalPlantedDate || getTodayDate())
            : (existing.dateSowing || "")
    );


    formData.append(
        "germination_rate",
        parseFloat(
            document.getElementById(
                "editCropGermination"
            ).value
        ) || 0
    );


    formData.append(
        "target_area",
        isNurseryCrop
            ? (document.getElementById("editCropTargetArea").value || "")
            : (existing.targetArea || "")
    );

    formData.append(
        "target_transplant_date",
        isNurseryCrop
            ? (document.getElementById("editCropTargetTransplantDate").value || "")
            : (existing.targetTransplantDate || "")
    );



    formData.append(
        "seedlings_received",
        parseInt(
            document.getElementById(
                "editCropSeedlings"
            ).value,
            10
        ) || 0
    );


    formData.append(
        "transplanted_area",
        cropEditingEmptySlot && String(currentPlot).toUpperCase() === "NURSERY"
            ? `N${Number(currentCropBed)}`
            : (document.getElementById(
                "editCropArea"
            ).value.trim() || `${currentPlot}${currentCropBed}`)
    );


    formData.append(
        "date_transplanted",
        isNurseryCrop ? "" : (finalPlantedDate || "")
    );


    formData.append(
        "expected_harvest",
        document.getElementById(
            "editCropHarvest"
        ).value || ""
    );


    formData.append(
        "status",
        statusLabelToDb(
            document.getElementById(
                "editCropStatus"
            ).value
        )
    );


    /*
     * ADD CROP IMAGE IF SELECTED
     */

    const cropImageInput =
        document.getElementById(
            "cropImageInput"
        );


    if (
        cropImageInput &&
        cropImageInput.files.length > 0
    ) {

        formData.append(
            "crop_image",
            cropImageInput.files[0]
        );
    }


    console.log(
        "Saving crop with image:",
        cropImageInput &&
        cropImageInput.files.length > 0
            ? cropImageInput.files[0]
            : "No new image"
    );


    const saveBtn =
        document.getElementById(
            "saveCropProfileBtn"
        );


    saveBtn.disabled = true;


    fetch(
        "../crops/farmMap_save_crop.php",
        {
            method: "POST",

            /*
             * IMPORTANT:
             * Do NOT set Content-Type manually.
             * Browser automatically sets multipart/form-data.
             */

            body: formData
        }
    )

    .then(
        response =>
            response.json()
    )

    .then(
        result => {

            if (
                !result.success
            ) {

                alert(
                    result.message ||
                    "Unable to save crop profile."
                );

                saveBtn.disabled =
                    false;

                return;
            }


            alert(
                "Crop profile saved successfully."
            );

            cropEditingEmptySlot = false;
            cropEmptySlotSnapshot = null;

            location.reload();

        }
    )

    .catch(
        error => {

            console.error(
                "Save crop profile failed:",
                error
            );


            alert(
                "Unable to save crop profile. Please try again."
            );


            saveBtn.disabled =
                false;

        }
    );

}

/* =========================================================
   CANCEL CROP PROFILE EDITING
========================================================= */

function cancelCropProfileEditing() {

    /*
     * Restore marker state.
     */
    if (
        cropProfileSnapshot &&
        currentPlot &&
        currentCropBed
    ) {

        const beds =
            getBedsForPlot(
                currentPlot
            );


        const bed =
            beds.find(
                item =>
                    Number(item.number) ===
                    Number(currentCropBed)
            );


        if (bed) {

            bed.x =
                cropProfileSnapshot.x;

            bed.y =
                cropProfileSnapshot.y;

        }


        /*
         * Restore stored icon.
         */
        const stored =
            getStoredCropMarkerIcons();


        const key =
            `${currentPlot}-${currentCropBed}`;


        if (
            cropProfileSnapshot.icon
        ) {

            stored[key] =
                cropProfileSnapshot.icon;

        } else {

            delete stored[key];

        }


        saveStoredCropMarkerIcons(
            stored
        );


        /*
         * Re-render the bed markers.
         */
        renderBedMarkers(
            currentPlot
        );

    }


    document.getElementById(
        "cropEditForm"
    ).classList.remove(
        "show"
    );


    setCropProfileEditButtons(
        false
    );


    renderCropProfile(
        getCropProfile(
            currentCropBed
        )
    );


    if (cropEditingEmptySlot && cropEmptySlotSnapshot && currentPlot) {
        const slots = Array.isArray(deletedBedSlots[currentPlot])
            ? deletedBedSlots[currentPlot]
            : [];
        if (!slots.some(slot => Number(slot.number) === Number(cropEmptySlotSnapshot.number))) {
            slots.push({ ...cropEmptySlotSnapshot });
        }
        deletedBedSlots[currentPlot] = slots;
        renderBedMarkers(currentPlot);
    }

    cropEditingEmptySlot = false;
    cropEmptySlotSnapshot = null;
    cropProfileSnapshot = null;

}


/* =========================================================
   CLOSE CROP PROFILE
========================================================= */

function closeCropProfile() {

    if (cropEditingEmptySlot) {
        cancelCropProfileEditing();
    }

    cancelCropHarvestForm();
    closeFarmMapTransferModal();
    document.getElementById("cropProfileOverlay").classList.remove("show");
    currentCropBed = null;
}



/* =========================================================
   IMAGE UPLOAD
========================================================= */

document.getElementById(
    "uploadCropImageBtn"
).addEventListener(
    "click",
    function() {

        document.getElementById(
            "cropImageInput"
        ).click();

    }
);


document.getElementById(
    "cropImageInput"
).addEventListener(
    "change",
    function(event) {

        const file =
            event.target.files[0];


        if (!file) return;


        if (
            !file.type.startsWith(
                "image/"
            )
        ) {

            alert(
                "Please select an image file."
            );

            return;

        }


        /*
         * Limit to 5 MB because
         * localStorage has limited capacity.
         */

        if (
            file.size >
            5 * 1024 * 1024
        ) {

            alert(
                "Please choose an image smaller than 5 MB."
            );

            event.target.value = "";

            return;

        }


        const reader =
            new FileReader();


        reader.onload =
    function(e) {

        if (
            !currentPlot ||
            !currentCropBed
        ) {
            return;
        }


        const crop =
            getCropProfile(
                currentCropBed
            );


        const cropId =
            crop.cropId;


        if (!cropId) {
            // A new Nursery sowing does not have an ID until Save creates it.
            // Keep the selected file in cropImageInput for the normal save request.
            updateCropImage(e.target.result);
            return;
        }


        /*
         * Show the selected image immediately
         */

        updateCropImage(
            e.target.result
        );


        /*
         * Upload image directly to PHP
         */

        const formData =
            new FormData();


        formData.append(
            "image_only",
            "1"
        );


        formData.append(
            "crop_id",
            cropId
        );


        formData.append(
            "crop_image",
            file
        );


        fetch(
            "../crops/farmMap_save_crop.php",
            {
                method: "POST",
                body: formData
            }
        )

        .then(
            response =>
                response.json()
        )

        .then(
            result => {

                if (!result.success) {

                    alert(
                        result.message ||
                        "Unable to upload crop picture."
                    );

                    return;
                }


                /*
                 * Save the database image path
                 * into the local crop data.
                 */

                crop.image =
                    result.image;
                event.target.value = "";


                const stored =
                    getStoredCropProfiles();


                const key =
                    `${currentPlot}-${currentCropBed}`;


                stored[key] =
                    crop;


                saveStoredCropProfiles(
                    stored
                );


                /*
                 * Refresh using the actual
                 * server image path.
                 */

                updateCropImage(
                    result.image
                );


                alert(
                    "Crop picture uploaded successfully."
                );

            }
        )

        .catch(
            error => {

                console.error(
                    "Crop image upload failed:",
                    error
                );


                alert(
                    "Unable to upload crop picture. Please try again."
                );

            }
        );

    };


        reader.readAsDataURL(
            file
        );
    }
);

const uploadLivestockImageBtn =
    document.getElementById("uploadLivestockImageBtn");

const removeLivestockImageBtn =
    document.getElementById("removeLivestockImageBtn");

const livestockImageInput =
    document.getElementById("livestockImageInput");

let livestockImageRemovalRequested = false;


/* ================================
   UPLOAD / CHANGE LIVESTOCK IMAGE
================================ */

if (uploadLivestockImageBtn && livestockImageInput) {

    uploadLivestockImageBtn.addEventListener("click", function () {

        livestockImageInput.click();

    });

}


/* ================================
   IMAGE SELECTED
================================ */

if (livestockImageInput) {

    livestockImageInput.addEventListener("change", function () {

        const file = this.files[0];

        if (!file) return;
        livestockImageRemovalRequested = false;

        if (!file.type.startsWith("image/")) {

            alert("Please select an image file.");

            this.value = "";

            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {

            const image =
                document.getElementById("livestockProfileImage");

            const placeholder =
                document.getElementById("livestockImagePlaceholder");

            if (image) {

                image.src = event.target.result;

                image.style.display = "block";

            }

            if (placeholder) {

                placeholder.style.display = "none";

            }

        };

        reader.readAsDataURL(file);

    });

}


/* ================================
   REMOVE LIVESTOCK IMAGE
================================ */

if (removeLivestockImageBtn) {

    removeLivestockImageBtn.addEventListener("click", function () {
        livestockImageRemovalRequested = true;

        const image =
            document.getElementById("livestockProfileImage");

        const placeholder =
            document.getElementById("livestockImagePlaceholder");

        const input =
            document.getElementById("livestockImageInput");


        if (image) {

            image.src = "";

            image.style.display = "none";

        }

        if (placeholder) {

            placeholder.style.display = "flex";

        }

        if (input) {

            input.value = "";

        }

    });

}

/* =========================================================
   REMOVE CROP IMAGE
========================================================= */

document.getElementById(
    "removeCropImageBtn"
).addEventListener(
    "click",
    function() {

        if (
            !currentPlot ||
            !currentCropBed
        ) {
            return;
        }


        if (
            !confirm(
                "Remove this crop picture?"
            )
        ) {
            return;
        }


        const stored =
            getStoredCropProfiles();


        const key =
            `${currentPlot}-${currentCropBed}`;


        const crop =
            getCropProfile(
                currentCropBed
            );


        crop.image =
            "";


        stored[key] =
            crop;


        saveStoredCropProfiles(
            stored
        );


        updateCropImage(
            ""
        );

    }
);


/* =========================================================
   LIVE WEEKS CALCULATION
========================================================= */

const editCropPlantedDateInput = document.getElementById("editCropPlantedDate");
const editCropWeeksInput = document.getElementById("editCropWeeks");
if (editCropPlantedDateInput && editCropWeeksInput) {
    editCropPlantedDateInput.addEventListener("change", function() {
        editCropWeeksInput.value = Math.floor(
            calculateDaysPlanted(this.value) / 7
        );
    });
}


function openPlant(id) {

    openCropProfile(
        Number(id)
    );

}


function closePlant() {

    closeCropProfile();

}


/* =========================================================
   NURSERY
========================================================= */

function openNursery() {

    const legacyNurseryOverlay = document.getElementById("nurseryOverlay");
    if (legacyNurseryOverlay) {
        legacyNurseryOverlay.classList.remove("show");
    }

    /*
     * Nursery uses the same bed-board concept as the crop plots,
     * but is completely isolated to the Nursery marker.
     *
     * It has exactly 20 permanent bed slots.
     */
    currentPlot = "NURSERY";

    bedEditMode = false;
    bedPositionEditMode = false;
    selectedBed = null;
    draggingBed = null;
    bedSnapshot = null;

    const plotPopup = document.getElementById("plotPopup");
    const plotOverlay = document.getElementById("plotOverlay");
    const plotImage = document.getElementById("plotImage");
    const plotTitle = document.getElementById("plotTitle");
    const plotSummary = document.getElementById("plotRecordSummary");
    const bedToolbar = document.getElementById("bedEditToolbar");

    if (!plotPopup || !plotOverlay) {
        return;
    }

    /*
     * Mark the existing plot popup as Nursery-only.
     * No Plot A/B/C data is changed.
     */
    plotPopup.classList.remove("bed-editing");
    plotPopup.classList.add("nursery-bed-popup");

    if (plotImage) {
        plotImage.src = "<?= $basePath ?>farmMap/nursery.jpg";
        plotImage.alt = "Nursery";
        plotImage.style.display = "block";
    }

    if (plotTitle) {
        plotTitle.textContent = "Nursery";
    }

    /*
     * The existing plot summary is not needed for Nursery.
     * Keep the existing plot design untouched by simply hiding it
     * while Nursery is open.
     */
    if (plotSummary) {
        plotSummary.style.display = "";
        updatePlotRecordSummary("NURSERY");
    }

    if (bedToolbar) {
        bedToolbar.style.display = "flex";
    }

    renderBedMarkers("NURSERY");
    if (typeof window.renderPlotLifecycleGantt === "function") {
        window.renderPlotLifecycleGantt("NURSERY");
    }
    updateBedEditUI();

    /*
     * Move the same popup to body, exactly like the existing plot popup,
     * so it cannot be clipped by the map container.
     */
    if (plotOverlay.parentElement !== document.body) {
        document.body.appendChild(plotOverlay);
    }

    plotOverlay.classList.add("show");

    /*
     * Make sure the Nursery marker itself does not remain focused.
     */
    const nurseryMarker = document.getElementById("marker-nursery");
    if (nurseryMarker && typeof nurseryMarker.blur === "function") {
        nurseryMarker.blur();
    }
}



function closeNursery() {

    /*
     * Nursery now uses the existing plot popup/bed board.
     * Close only when the current board belongs to Nursery.
     */
    if (currentPlot === "NURSERY") {

        bedEditMode = false;
        bedPositionEditMode = false;
        selectedBed = null;
        draggingBed = null;
        bedSnapshot = null;

        const plotPopup = document.getElementById("plotPopup");
        const plotOverlay = document.getElementById("plotOverlay");
        const plotImage = document.getElementById("plotImage");
        const plotSummary = document.getElementById("plotRecordSummary");

        if (plotPopup) {
            plotPopup.classList.remove(
                "bed-editing",
                "nursery-bed-popup"
            );
        }

        if (plotImage) {
            plotImage.style.display = "";
            plotImage.removeAttribute("alt");
        }

        if (plotSummary) {
            plotSummary.style.display = "";
        }

        if (plotOverlay) {
            plotOverlay.classList.remove("show");
        }

        currentPlot = null;

        updateBedEditUI();
    }

}



/* =========================================================
   LIVESTOCK
========================================================= */

/* =========================================================
   LIVESTOCK DATA + STORAGE
========================================================= */

let currentLivestockHouse = null;
let currentLivestockId = null;
let livestockHouseEditMode = false;
let livestockPositionEditMode = false;
let selectedLivestockMarker = null;
let livestockAddMode = false;
let livestockDeletedIds = {};
let draggingLivestock = null;

let livestockProfileSnapshot = null;

/*
|--------------------------------------------------------------------------
| LIVESTOCK DATA (FROM DATABASE)
|--------------------------------------------------------------------------
|
| dbLivestockHouses is embedded by PHP at page load from the
| livestock_houses + livestocks tables. Each house_code (e.g.
| "goat-house") maps to { house: {...}, animals: [...] }.
|
*/

function sexDbToLabel(sex) {

    if (sex === "male") return "Male";
    if (sex === "female") return "Female";
    if (sex === "mixed") return "Mixed";

    return "--";
}

function assignAnimalPosition(index, total) {

    const columns = Math.max(1, Math.min(4, total));
    const col = index % columns;
    const row = Math.floor(index / columns);

    const spread = columns > 1 ? 70 / (columns - 1) : 0;

    return {
        x: 15 + col * spread,
        y: 20 + row * 22
    };
}

function dbLivestockToAnimal(row, index, total) {

    const pos = assignAnimalPosition(index, total);

    const displayName =
        row.name ||
        `${row.type || "Livestock"}${
            row.number && row.number > 1 ? " (" + row.number + " pcs)" : ""
        }`;

    return {
        id: row.livestock_id,
        name: displayName,
        type: row.type || "",
        sex: row.sex || "",
        dob: row.date_of_birth || "",
        age: row.age || "",
        breed: row.breed || "",
        weight: row.estimated_weight ?? "",
        number: row.number ?? 0,
        forSale: Number(row.for_sale) === 1,
        feedingSchedule: row.feeding_schedule || "",
        notes: row.notes || "",
        image: row.image ? `${basePath}${row.image}` : "",
        x: pos.x,
        y: pos.y
    };
}

// Normalize paths saved as relative URLs, /SanctuarioSystem/... URLs,
// or absolute local-server URLs without duplicating the application folder.
function resolveFarmMapImagePath(value) {
    const raw = String(value || "").trim().replace(/\\/g, "/");
    if (!raw) return "";
    if (/^(?:data:image\/|blob:)/i.test(raw)) return raw;
    if (/^https?:\/\//i.test(raw)) return raw;
    const appRoot = basePath.replace(/\/+$/, "") + "/";
    const clean = raw.replace(/^\.\//, "");
    if (clean.startsWith(appRoot)) return clean;
    if (clean.startsWith("/")) return clean;
    if (/^SanctuarioSystem\//i.test(clean)) return "/" + clean;
    return appRoot + clean;
}

function getLivestockHouseData(houseId) {

    const entry = dbLivestockHouses[houseId];

    if (!entry) return null;

    const total = entry.animals.length;

    const animals = entry.animals.map(
        (row, index) => dbLivestockToAnimal(row, index, total)
    );

    // Goat House 2 shares the same photo as the original Goat House.
    // Prefer the first goat house's saved image; otherwise use goat_house.jpg.
    let houseImage = entry.house.image || "";
    if (/^goat house\s*2$/i.test(String(entry.house.house_name || "").trim())) {
        const originalGoatHouse = Object.values(dbLivestockHouses).find(item =>
            /^goat house$/i.test(String(item.house.house_name || "").trim())
        );
        houseImage = (originalGoatHouse && originalGoatHouse.house.image) || "goat_house.jpg";
    }
    return {
        title: entry.house.house_name,
        image: resolveFarmMapImagePath(houseImage),
        description: entry.house.description || "",
        animals
    };
}


function calculateLivestockAge(dob) {

    if (!dob) return "--";

    const birth = parseDateLocal(dob);
    if (!birth) return "--";

    const today = new Date();
    const todayLocal = new Date(
        today.getFullYear(),
        today.getMonth(),
        today.getDate()
    );

    let months =
        (todayLocal.getFullYear() - birth.getFullYear()) * 12 +
        (todayLocal.getMonth() - birth.getMonth());

    if (todayLocal.getDate() < birth.getDate()) {
        months--;
    }

    months = Math.max(0, months);

    if (months < 12) {
        return `${months} ${months === 1 ? "month" : "months"}`;
    }

    const years = Math.floor(months / 12);
    const remainingMonths = months % 12;

    if (!remainingMonths) {
        return `${years} ${years === 1 ? "year" : "years"}`;
    }

    return `${years} ${years === 1 ? "year" : "years"} ${remainingMonths} ${remainingMonths === 1 ? "month" : "months"}`;
}

function formatLivestockDate(dateString) {

    if (!dateString) return "--";

    const date = parseDateLocal(dateString);
    if (!date) return dateString;

    return date.toLocaleDateString("en-US", {
        month: "long",
        day: "numeric",
        year: "numeric"
    });
}

const LIVESTOCK_DELETED_STORAGE_KEY = "sanctuarioDeletedLivestock";

function getDeletedLivestock() {
    try {
        const value = JSON.parse(localStorage.getItem(LIVESTOCK_DELETED_STORAGE_KEY));
        return value && typeof value === "object" ? value : {};
    } catch (e) { return {}; }
}

function saveDeletedLivestock(data) {
    localStorage.setItem(LIVESTOCK_DELETED_STORAGE_KEY, JSON.stringify(data));
}

livestockDeletedIds = getDeletedLivestock();

function createLivestockMarker(animal, unitIndex = 0, unitTotal = 1) {

    const marker =
        document.createElement("button");


    marker.type =
        "button";


    marker.className =
        "livestock-house-marker livestock-image-marker";


    marker.dataset.id =
        animal.id;


    marker.dataset.name =
        animal.name ||
        "Livestock";


    /*
     * Load saved livestock marker information.
     */
    const stored =
        getStoredLivestockMarkerData();


    const saved =
        stored[
            animal.id
        ] || {};


    const iconKey =
        saved.icon &&
        FARM_MARKER_ICONS.livestock[
            saved.icon
        ]
            ? saved.icon
            : guessLivestockIcon(
                animal.type
            );


    const iconPath =
        FARM_MARKER_ICONS.livestock[
            iconKey
        ] ||
        FARM_MARKER_ICONS.livestock.goat;


    /*
     * Preserve existing automatically generated
     * positions unless the user has manually saved one.
     */
    // One database livestock record = one map marker.
    // The `number` field is informational and must NOT duplicate markers.
    // Always prefer a manually saved position so dragging survives redraws/reloads.
    const hasSavedPosition =
        saved.x !== undefined && saved.x !== null &&
        saved.y !== undefined && saved.y !== null;

    const x = hasSavedPosition
        ? Number(saved.x)
        : Number(animal.x) || 0;

    const y = hasSavedPosition
        ? Number(saved.y)
        : Number(animal.y) || 0;


    marker.style.left =
        `${Math.max(0, Math.min(100, x))}%`;


    marker.style.top =
        `${Math.max(0, Math.min(100, y))}%`;


    marker.title =
        animal.name ||
        "Livestock";


    marker.innerHTML =
        `
            <img
                src="${iconPath}"
                alt="${escapeHTML(animal.name || "Livestock")}"
                draggable="false"
            >
        `;


    marker.addEventListener("click", function(event) {
        event.stopPropagation();
        if (livestockHouseEditMode) {
            selectLivestockMarker(marker);
            return;
        }
        openLivestockProfile(animal.id);
    });

    marker.addEventListener("mousedown", function(event) {
        if (!livestockHouseEditMode || !livestockPositionEditMode) return;
        event.preventDefault();
        event.stopPropagation();
        draggingLivestock = marker;
        selectLivestockMarker(marker);
    });


    return marker;

}

function renderLivestockMarkers(houseId) {
    const container = document.getElementById("livestockMarkersContainer");
    if (!container) return;
    container.innerHTML = "";

    const house = getLivestockHouseData(houseId);
    if (!house) return;

    // One marker = one actual livestock.php record.
    // Never expand the `number` field into multiple markers.
    house.animals.forEach((animal, index) => {
        container.appendChild(createLivestockMarker(animal, index, house.animals.length));
    });

    const count = document.getElementById("livestockCount");
    if (count) {
        const totalRecords = house.animals.length;
        count.textContent = `${totalRecords} ${totalRecords === 1 ? "record" : "records"}`;
    }
}

function selectLivestockMarker(marker) {
    document.querySelectorAll("#livestockMarkersContainer .livestock-house-marker").forEach(item => item.classList.remove("selected"));
    selectedLivestockMarker = marker;
    currentLivestockId = marker?.dataset.id || null;
    if (marker) marker.classList.add("selected");
}


function updateLivestockHouseEditUI() {
    const toolbar = document.getElementById("livestockHouseToolbar");
    const editBtn = document.getElementById("editLivestockHouseBtn");
    const posBtn = document.getElementById("editLivestockPositionBtn");
    if (!toolbar || !editBtn) return;
    toolbar.classList.toggle("is-editing", livestockHouseEditMode);
    editBtn.innerHTML = livestockHouseEditMode
        ? '<i class="fa-solid fa-check"></i> Done'
        : '<i class="fa-solid fa-pen"></i> Edit House';
    if (posBtn) {
        posBtn.classList.toggle("position-active", livestockPositionEditMode);
        posBtn.innerHTML = livestockPositionEditMode
            ? '<i class="fa-solid fa-check"></i> Position Editing'
            : '<i class="fa-solid fa-up-down-left-right"></i> Edit Position';
    }
}

function toggleLivestockHouseEditing() {
    if (!MAP_CAN_EDIT_LIVESTOCK) return;
    livestockHouseEditMode = !livestockHouseEditMode;
    livestockPositionEditMode = false;
    selectedLivestockMarker = null;
    updateLivestockHouseEditUI();
    renderLivestockMarkers(currentLivestockHouse);
}

function toggleLivestockPositionEditing() {
    if (!MAP_CAN_EDIT_LIVESTOCK) return;
    if (!livestockHouseEditMode) return;
    livestockPositionEditMode = !livestockPositionEditMode;
    document.querySelectorAll("#livestockMarkersContainer .livestock-house-marker").forEach(marker => marker.classList.toggle("position-editing", livestockPositionEditMode));
    updateLivestockHouseEditUI();
}

function deleteSelectedLivestock() {
    if (!MAP_CAN_EDIT_LIVESTOCK) return;
    if (!livestockHouseEditMode || !selectedLivestockMarker) {
        showFarmMapToast("Select a livestock marker first");
        return;
    }
    const id = String(selectedLivestockMarker.dataset.id);
    const animal = getCurrentLivestock();
    if (!animal) return;
    if (!confirm(`Remove ${animal.name || "this livestock"} from the house?`)) return;
    if (!livestockDeletedIds[currentLivestockHouse]) livestockDeletedIds[currentLivestockHouse] = {};
    livestockDeletedIds[currentLivestockHouse][id] = true;
    saveDeletedLivestock(livestockDeletedIds);
    selectedLivestockMarker = null;
    currentLivestockId = null;
    renderLivestockMarkers(currentLivestockHouse);
    showFarmMapToast("Livestock removed — + is ready");
}

function editSelectedLivestock() {
    if (!MAP_CAN_EDIT_LIVESTOCK) return;
    if (!livestockHouseEditMode || !selectedLivestockMarker) {
        showFarmMapToast("Select a livestock marker first");
        return;
    }
    openLivestockProfile(selectedLivestockMarker.dataset.id);
    startLivestockProfileEditing();
}

function getLivestockHouseDefaults(houseId) {
    const entry = dbLivestockHouses[houseId];
    if (!entry || !entry.house) {
        return { type: "", icon: "" };
    }

    const raw = String(entry.house.house_code || entry.house.house_name || "")
        .toLowerCase()
        .replace(/[_\s]+/g, "-");

    if (raw.includes("rabbit")) {
        return { type: "Rabbit", icon: "rabbit" };
    }

    if (raw.includes("goat")) {
        return { type: "Goat", icon: "goat" };
    }

    if (raw.includes("poultry") || raw.includes("chicken")) {
        return { type: "Chicken", icon: "chicken" };
    }

    if (raw.includes("swine") || raw.includes("pig")) {
        return { type: "Pig", icon: "pig" };
    }

    return { type: "", icon: "" };
}

function setLivestockAddHouseDefaults() {
    const defaults = getLivestockHouseDefaults(currentLivestockHouse);
    const typeInput = document.getElementById("editLivestockType");
    const iconChooser = document.getElementById("livestockMarkerIconChooser");

    if (typeInput) {
        typeInput.value = defaults.type;
        typeInput.readOnly = !!defaults.type;
        typeInput.title = defaults.type
            ? `Automatically set to ${defaults.type} for this livestock house.`
            : "";
    }

    document.querySelectorAll('input[name="livestockMarkerIcon"]').forEach(input => {
        input.disabled = !!defaults.icon;
        input.checked = defaults.icon ? input.value === defaults.icon : false;
    });

    if (iconChooser) {
        iconChooser.style.display = defaults.icon ? "none" : "";
    }
}

function restoreLivestockTypeAndIconChooser() {
    const typeInput = document.getElementById("editLivestockType");
    const iconChooser = document.getElementById("livestockMarkerIconChooser");

    if (typeInput) {
        typeInput.readOnly = false;
        typeInput.title = "";
    }

    document.querySelectorAll('input[name="livestockMarkerIcon"]').forEach(input => {
        input.disabled = false;
    });

    if (iconChooser) {
        iconChooser.style.display = "";
    }
}

function openNewLivestockForm(index, x, y) {
    livestockAddMode = true;
    currentLivestockId = null;
    livestockProfileSnapshot = null;
    const profile = document.getElementById("livestockProfileOverlay");
    if (!profile) return;
    document.getElementById("profileLivestockName").textContent = "New Livestock";
    document.getElementById("profileLivestockType").textContent = "Add livestock";
    document.getElementById("livestockEditForm").classList.add("show");
    setLivestockProfileEditButtons(true);
    [
        ["editLivestockName", ""], ["editLivestockType", ""], ["editLivestockBreed", ""],
        ["editLivestockWeight", ""], ["editLivestockFeedingSchedule", ""], ["editLivestockNotes", ""]
    ].forEach(([id, value]) => { const el = document.getElementById(id); if (el) el.value = value; });
    document.getElementById("editLivestockSex").value = "";
    document.getElementById("editLivestockDOB").value = "";
    document.getElementById("editLivestockNumber").value = "1";
    document.getElementById("editLivestockForSale").value = "0";

    // The house determines the livestock type and icon.
    setLivestockAddHouseDefaults();

    document.getElementById("editLivestockMarkerX").value = x;
    document.getElementById("editLivestockMarkerY").value = y;
    profile.classList.add("show");
    setLivestockAddHouseDefaults();
}

// Livestock position dragging
document.addEventListener("mousemove", function(event) {
    if (!draggingLivestock || !livestockPositionEditMode) return;
    const map = document.querySelector(".livestock-house-map");
    if (!map) return;
    const rect = map.getBoundingClientRect();
    const x = Math.max(2, Math.min(98, ((event.clientX - rect.left) / rect.width) * 100));
    const y = Math.max(2, Math.min(98, ((event.clientY - rect.top) / rect.height) * 100));
    draggingLivestock.style.left = `${x}%`;
    draggingLivestock.style.top = `${y}%`;
});

document.addEventListener("mouseup", function() {
    if (draggingLivestock && livestockPositionEditMode && currentLivestockId) {
        const stored = getStoredLivestockMarkerData();
        stored[currentLivestockId] = { ...(stored[currentLivestockId] || {}), x: parseFloat(draggingLivestock.style.left) || 0, y: parseFloat(draggingLivestock.style.top) || 0 };
        saveStoredLivestockMarkerData(stored);
        showFarmMapToast("Livestock position saved");
    }
    draggingLivestock = null;
});

function openLivestock(houseId) {

    const house = getLivestockHouseData(houseId);

    if (!house) {
        alert("This livestock house does not have information yet.");
        return;
    }

    currentLivestockHouse = houseId;
    livestockHouseEditMode = false;
    livestockPositionEditMode = false;
    selectedLivestockMarker = null;
    draggingLivestock = null;
    updateLivestockHouseEditUI();

    const houseImage = document.getElementById("livestockHouseImage");
    const houseImageArea = houseImage.closest(".livestock-house-map");
    houseImageArea.classList.remove("house-image-unavailable");
    houseImage.alt = house.title || "Livestock House";
    houseImage.onerror = function () {
        this.removeAttribute("src");
        houseImageArea.classList.add("house-image-unavailable");
    };
    if (house.image) {
        houseImage.src = house.image;
    } else {
        houseImage.removeAttribute("src");
        houseImageArea.classList.add("house-image-unavailable");
    }
    document.getElementById("livestockHouseTitle").textContent = house.title;
    document.getElementById("livestockHouseDescription").textContent = house.description;

    renderLivestockMarkers(houseId);

    document.getElementById("livestockOverlay").classList.add("show");
}

function closeLivestock() {

    closeLivestockProfile();
    livestockHouseEditMode = false;
    livestockPositionEditMode = false;
    selectedLivestockMarker = null;
    draggingLivestock = null;
    livestockAddMode = false;

    currentLivestockHouse = null;

    document.getElementById("livestockOverlay").classList.remove("show");
}

function getCurrentLivestock() {

    if (!currentLivestockHouse || !currentLivestockId) {
        return null;
    }

    const house = getLivestockHouseData(currentLivestockHouse);

    if (!house) return null;

    return house.animals.find(
        animal => animal.id === currentLivestockId
    ) || null;
}

function updateLivestockImage(image) {

    const img = document.getElementById("livestockProfileImage");
    const placeholder = document.getElementById("livestockImagePlaceholder");

    if (image) {
        img.src = image;
        img.style.display = "block";
        placeholder.style.display = "none";
    } else {
        img.src = "";
        img.style.display = "none";
        placeholder.style.display = "flex";
    }
}

function renderLivestockProfile(animal) {

    if (!animal) return;

    document.getElementById("profileLivestockName").textContent =
        animal.name || "Unnamed Livestock";

    document.getElementById("profileLivestockType").textContent =
        animal.type || "--";

    document.getElementById("profileLivestockTypeDisplay").textContent =
        animal.type || "--";

    document.getElementById("profileLivestockSex").textContent =
        sexDbToLabel(animal.sex);

    document.getElementById("profileLivestockDOB").textContent =
        formatLivestockDate(animal.dob);

    document.getElementById("profileLivestockBreed").textContent =
        animal.breed || "--";

    document.getElementById("profileLivestockAge").textContent =
        animal.age || "--";

    document.getElementById("profileLivestockWeight").textContent =
        animal.weight
            ? `${animal.weight} kg`
            : "--";



    document.getElementById("profileLivestockForSale").textContent =
        animal.forSale ? "Yes" : "No";

    document.getElementById("profileLivestockFeedingSchedule").textContent =
        animal.feedingSchedule || "--";

    document.getElementById("profileLivestockNotes").textContent =
        animal.notes || "No notes";

    updateLivestockImage(animal.image || "");
}

function openLivestockProfile(animalId) {

    currentLivestockId = animalId;

    const animal = getCurrentLivestock();

    if (!animal) {
        alert("Livestock profile not found.");
        return;
    }

    renderLivestockProfile(animal);

    document.getElementById("livestockEditForm").classList.remove("show");
    setLivestockProfileEditButtons(false);

    document.getElementById("livestockProfileOverlay").classList.add("show");
}

function closeLivestockProfile() {

    livestockImageRemovalRequested = false;
    if (livestockImageInput) livestockImageInput.value = "";
    restoreLivestockTypeAndIconChooser();

    document.getElementById("livestockProfileOverlay").classList.remove("show");
    document.getElementById("livestockEditForm").classList.remove("show");
    setLivestockProfileEditButtons(false);

    currentLivestockId = null;
    livestockProfileSnapshot = null;
    livestockAddMode = false;
}

function setLivestockProfileEditButtons(editing) {
    const imageActions = document.getElementById("livestockImageActions");
    if (imageActions) imageActions.style.display = editing ? "flex" : "none";

    document.getElementById("editLivestockProfileBtn").style.display =
        editing ? "none" : "inline-flex";

    document.getElementById("saveLivestockProfileBtn").style.display =
        editing ? "inline-flex" : "none";

    document.getElementById("cancelLivestockProfileBtn").style.display =
        editing ? "inline-flex" : "none";
}

function startLivestockProfileEditing() {
    if (!MAP_CAN_EDIT_LIVESTOCK) return;

    livestockImageRemovalRequested = false;
    if (livestockImageInput) livestockImageInput.value = "";
    restoreLivestockTypeAndIconChooser();

    const animal =
        getCurrentLivestock();


    if (!animal) {
        return;
    }


    /*
     * Save the original livestock information.
     */
    livestockProfileSnapshot =
        JSON.parse(
            JSON.stringify(animal)
        );


    /*
     * Save the original marker information.
     *
     * IMPORTANT:
     * This code is INSIDE the function because
     * it uses animal.id.
     */
    const storedMarkerData =
        getStoredLivestockMarkerData();


    livestockProfileSnapshot.markerData =
        JSON.parse(
            JSON.stringify(
                storedMarkerData[
                    animal.id
                ] || {}
            )
        );


    document.getElementById(
        "editLivestockName"
    ).value =
        animal.name || "";


    document.getElementById(
        "editLivestockType"
    ).value =
        animal.type || "";


    document.getElementById(
        "editLivestockSex"
    ).value =
        animal.sex || "male";


    document.getElementById(
        "editLivestockDOB"
    ).value =
        animal.dob || "";


    document.getElementById(
        "editLivestockBreed"
    ).value =
        animal.breed || "";


    document.getElementById(
        "editLivestockWeight"
    ).value =
        animal.weight || "";


    document.getElementById(
        "editLivestockNumber"
    ).value =
        animal.number ?? 1;


    document.getElementById(
        "editLivestockForSale"
    ).value =
        animal.forSale
            ? "1"
            : "0";


    document.getElementById(
        "editLivestockFeedingSchedule"
    ).value =
        animal.feedingSchedule || "";


    document.getElementById(
        "editLivestockNotes"
    ).value =
        animal.notes || "";


    /*
     * Load the currently saved icon.
     */
    const savedMarker =
        storedMarkerData[
            animal.id
        ] || {};


    const iconKey =
        savedMarker.icon &&
        FARM_MARKER_ICONS.livestock[
            savedMarker.icon
        ]
            ? savedMarker.icon
            : guessLivestockIcon(
                animal.type
            );


    const iconInput =
        document.querySelector(
            `input[name="livestockMarkerIcon"][value="${iconKey}"]`
        );


    if (iconInput) {

        iconInput.checked =
            true;

    }


    /*
     * Load the current marker position.
     */
    const currentMarker =
        document.querySelector(
            `#livestockMarkersContainer .livestock-house-marker[data-id="${animal.id}"]`
        );


    const currentX =
        savedMarker.x != null
            ? Number(savedMarker.x)
            : currentMarker
                ? parseFloat(
                    currentMarker.style.left
                )
                : Number(animal.x) || 0;


    const currentY =
        savedMarker.y != null
            ? Number(savedMarker.y)
            : currentMarker
                ? parseFloat(
                    currentMarker.style.top
                )
                : Number(animal.y) || 0;


    document.getElementById(
        "editLivestockMarkerX"
    ).value =
        Number.isFinite(currentX)
            ? currentX
            : 0;


    document.getElementById(
        "editLivestockMarkerY"
    ).value =
        Number.isFinite(currentY)
            ? currentY
            : 0;


    /*
     * Change icon immediately when selected.
     */
    document
        .querySelectorAll(
            'input[name="livestockMarkerIcon"]'
        )
        .forEach(
            input => {

                input.onchange =
                    function() {

                        updateCurrentLivestockMarkerIcon(
                            this.value
                        );

                    };

            }
        );


    /*
     * Change position immediately when X/Y changes.
     */
    const xInput =
        document.getElementById(
            "editLivestockMarkerX"
        );


    const yInput =
        document.getElementById(
            "editLivestockMarkerY"
        );


    if (xInput) {

        xInput.oninput =
            updateCurrentLivestockMarkerPosition;

    }


    if (yInput) {

        yInput.oninput =
            updateCurrentLivestockMarkerPosition;

    }


    document.getElementById(
        "livestockEditForm"
    ).classList.add(
        "show"
    );


    setLivestockProfileEditButtons(
        true
    );

}

function updateCurrentLivestockMarkerIcon(
    iconKey
) {

    if (!currentLivestockId) {
        return;
    }


    if (
        !FARM_MARKER_ICONS.livestock[
            iconKey
        ]
    ) {
        return;
    }


    const stored =
        getStoredLivestockMarkerData();


    const existing =
        stored[
            currentLivestockId
        ] || {};


    existing.icon =
        iconKey;


    stored[
        currentLivestockId
    ] =
        existing;


    saveStoredLivestockMarkerData(
        stored
    );


    /*
     * Immediately change the marker.
     */
    const marker =
        document.querySelector(
            `#livestockMarkersContainer .livestock-house-marker[data-id="${currentLivestockId}"]`
        );


    if (!marker) {
        return;
    }


    const image =
        marker.querySelector("img");


    if (image) {

        image.src =
            FARM_MARKER_ICONS.livestock[
                iconKey
            ];

    }

}


function updateCurrentLivestockMarkerPosition() {

    if (!currentLivestockId) {
        return;
    }


    const xInput =
        document.getElementById(
            "editLivestockMarkerX"
        );


    const yInput =
        document.getElementById(
            "editLivestockMarkerY"
        );


    if (!xInput || !yInput) {
        return;
    }


    const x =
        Math.max(
            0,
            Math.min(
                100,
                Number(xInput.value) || 0
            )
        );


    const y =
        Math.max(
            0,
            Math.min(
                100,
                Number(yInput.value) || 0
            )
        );


    const stored =
        getStoredLivestockMarkerData();


    const existing =
        stored[
            currentLivestockId
        ] || {};


    existing.x =
        x;


    existing.y =
        y;


    stored[
        currentLivestockId
    ] =
        existing;


    saveStoredLivestockMarkerData(
        stored
    );


    const marker =
        document.querySelector(
            `#livestockMarkersContainer .livestock-house-marker[data-id="${currentLivestockId}"]`
        );


    if (!marker) {
        return;
    }


    marker.style.left =
        `${x}%`;


    marker.style.top =
        `${y}%`;

}

document.getElementById("editLivestockProfileBtn").addEventListener(
    "click",
    function () {
        startLivestockProfileEditing();
    }
);

const storedMarkerData =
    getStoredLivestockMarkerData();



document.getElementById("saveLivestockProfileBtn").addEventListener(
    "click",
    function () {
        saveLivestockProfile();
    }
);

document.getElementById("cancelLivestockProfileBtn").addEventListener(
    "click",
    function () {
        cancelLivestockProfileEditing();
    }
);

function saveLivestockProfile() {
    if (!MAP_CAN_EDIT_LIVESTOCK) return;

    const animal = getCurrentLivestock();

    if ((!animal && !livestockAddMode) || !currentLivestockHouse) return;

    /*
|--------------------------------------------------------------------------
| SAVE LIVESTOCK MARKER ICON + POSITION
|--------------------------------------------------------------------------
*/

const selectedLivestockIcon =
    document.querySelector(
        'input[name="livestockMarkerIcon"]:checked'
    );


const storedMarkerData =
    getStoredLivestockMarkerData();

const markerKey = livestockAddMode
    ? null
    : String(animal.id);

const livestockMarkerData = markerKey
    ? (storedMarkerData[markerKey] || {})
    : {};

if (
    selectedLivestockIcon &&
    FARM_MARKER_ICONS.livestock[
        selectedLivestockIcon.value
    ]
) {
    livestockMarkerData.icon = selectedLivestockIcon.value;
}


const livestockX =
    document.getElementById(
        "editLivestockMarkerX"
    );


const livestockY =
    document.getElementById(
        "editLivestockMarkerY"
    );


if (livestockX) {

    livestockMarkerData.x =
        Math.max(
            0,
            Math.min(
                100,
                Number(
                    livestockX.value
                ) || 0
            )
        );

}


if (livestockY) {

    livestockMarkerData.y =
        Math.max(
            0,
            Math.min(
                100,
                Number(
                    livestockY.value
                ) || 0
            )
        );

}


if (markerKey) {
    storedMarkerData[markerKey] = livestockMarkerData;
    saveStoredLivestockMarkerData(storedMarkerData);
}


    /*
    |--------------------------------------------------------------------------
    | CREATE FORM DATA
    |--------------------------------------------------------------------------
    */

    const formData = new FormData();


    formData.append(
        "livestock_id",
        livestockAddMode ? "" : animal.id
    );

    // currentLivestockHouse is the house_code used by the Farm Map.
    // The database endpoint needs the numeric livestock_houses.house_id.
    const currentHouseEntry = dbLivestockHouses[currentLivestockHouse];
    const currentHouseId = currentHouseEntry?.house?.house_id;

    if (!currentHouseId) {
        alert("Invalid livestock house. Please close and reopen the livestock house.");
        return;
    }

    formData.append(
        "house_id",
        currentHouseId
    );

    formData.append(
        "name",
        document.getElementById("editLivestockName").value.trim()
    );

    formData.append(
        "type",
        document.getElementById("editLivestockType").value.trim()
    );

    formData.append(
        "sex",
        document.getElementById("editLivestockSex").value
    );

    formData.append(
        "date_of_birth",
        document.getElementById("editLivestockDOB").value
    );

    formData.append(
        "breed",
        document.getElementById("editLivestockBreed").value.trim()
    );

    formData.append(
        "estimated_weight",
        document.getElementById("editLivestockWeight").value
    );

    formData.append(
        "number",
        document.getElementById("editLivestockNumber").value
    );

    formData.append(
        "for_sale",
        document.getElementById("editLivestockForSale").value
    );

    formData.append(
        "feeding_schedule",
        document.getElementById("editLivestockFeedingSchedule").value.trim()
    );

    formData.append(
        "notes",
        document.getElementById("editLivestockNotes").value.trim()
    );


    /*
    |--------------------------------------------------------------------------
    | ADD IMAGE IF ONE WAS SELECTED
    |--------------------------------------------------------------------------
    */

    const imageInput =
        document.getElementById("livestockImageInput");

    formData.append("remove_livestock_image", livestockImageRemovalRequested ? "1" : "0");

    if (
        imageInput &&
        imageInput.files &&
        imageInput.files.length > 0
    ) {

        formData.append(
            "livestock_image",
            imageInput.files[0]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE BUTTON
    |--------------------------------------------------------------------------
    */

    const saveBtn =
        document.getElementById("saveLivestockProfileBtn");

    saveBtn.disabled = true;


    /*
    |--------------------------------------------------------------------------
    | SEND TO PHP
    |--------------------------------------------------------------------------
    */

    fetch("../livestock/farmMap_save_livestock.php", {

        method: "POST",

        body: formData

    })

        .then(response => response.json())

        .then(result => {

            if (!result.success) {

                alert(
                    result.message ||
                    "Unable to save livestock profile."
                );

                saveBtn.disabled = false;

                return;
            }


            /*
            |------------------------------------------------------------------
            | UPDATE CURRENT ANIMAL IMAGE
            |------------------------------------------------------------------
            */

            if (result.image && animal) {

                animal.image =
                    `${basePath}${result.image}`;

                updateLivestockImage(
                    animal.image
                );
            }

            if (livestockAddMode && result.livestock_id) {
                const saved = getStoredLivestockMarkerData();
                const key = String(result.livestock_id);
                saved[key] = {
                    ...(saved[key] || {}),
                    icon: selectedLivestockIcon ? selectedLivestockIcon.value : "goat",
                    x: Number(livestockX?.value) || 50,
                    y: Number(livestockY?.value) || 50
                };
                saveStoredLivestockMarkerData(saved);
            }


            alert(
                livestockAddMode
                    ? "Livestock added successfully."
                    : "Livestock profile saved successfully."
            );

            livestockAddMode = false;
            location.reload();

        })

        .catch(error => {

            console.error(
                "Save livestock profile failed:",
                error
            );

            alert(
                "Unable to save livestock profile. Please try again."
            );

            saveBtn.disabled = false;

        });
}

function cancelLivestockProfileEditing() {
    livestockImageRemovalRequested = false;
    if (livestockImageInput) livestockImageInput.value = "";

    if (livestockAddMode) {
        livestockAddMode = false;
        restoreLivestockTypeAndIconChooser();
        document.getElementById("livestockEditForm")?.classList.remove("show");
        setLivestockProfileEditButtons(false);
        return;
    }

    if (
        livestockProfileSnapshot &&
        currentLivestockId
    ) {

        const stored =
            getStoredLivestockMarkerData();


        const snapshot =
            livestockProfileSnapshot;


        const current =
            stored[
                currentLivestockId
            ] || {};


        /*
         * The profile snapshot contains the animal's
         * original information. Marker settings are
         * restored from the marker currently represented
         * in localStorage if available.
         *
         * We intentionally do not alter the livestock
         * database record here.
         */
        if (
            current.icon
        ) {

            current.icon =
                current.icon;

        }


        stored[
            currentLivestockId
        ] =
            current;


        saveStoredLivestockMarkerData(
            stored
        );

    }


    document.getElementById(
        "livestockEditForm"
    ).classList.remove(
        "show"
    );


    setLivestockProfileEditButtons(
        false
    );


    /*
     * Re-render the house markers so the
     * current stored marker settings are shown.
     */
    if (currentLivestockHouse) {

        renderLivestockMarkers(
            currentLivestockHouse
        );

    }


    const animal =
        getCurrentLivestock();


    if (animal) {

        renderLivestockProfile(
            animal
        );

    }


    livestockProfileSnapshot =
        null;

}




/* =========================================================
   LIVESTOCK HOUSE EDIT BUTTONS
========================================================= */

const editLivestockHouseBtn = document.getElementById("editLivestockHouseBtn");
if (editLivestockHouseBtn) editLivestockHouseBtn.addEventListener("click", toggleLivestockHouseEditing);

const editLivestockPositionBtn = document.getElementById("editLivestockPositionBtn");
if (editLivestockPositionBtn) editLivestockPositionBtn.addEventListener("click", toggleLivestockPositionEditing);

const deleteLivestockBtn = document.getElementById("deleteLivestockBtn");
if (deleteLivestockBtn) deleteLivestockBtn.addEventListener("click", deleteSelectedLivestock);

const editSelectedLivestockBtn = document.getElementById("editSelectedLivestockBtn");
if (editSelectedLivestockBtn) editSelectedLivestockBtn.addEventListener("click", editSelectedLivestock);

const doneLivestockHouseBtn = document.getElementById("doneLivestockHouseBtn");
if (doneLivestockHouseBtn) doneLivestockHouseBtn.addEventListener("click", function() {
    livestockHouseEditMode = false;
    livestockPositionEditMode = false;
    selectedLivestockMarker = null;
    updateLivestockHouseEditUI();
    renderLivestockMarkers(currentLivestockHouse);
});


/* =========================================================
   RIGHT SIDE PLOT INFO
========================================================= */

function updateSidePlotInfo(plot) {

    const data =
        plotData[plot];


    if (!data) return;


    const title =
        document.getElementById(
            "sidePlotTitle"
        );


    const description =
        document.getElementById(
            "sidePlotDescription"
        );


    const area =
        document.getElementById(
            "sidePlotArea"
        );


    const beds =
        document.getElementById(
            "sidePlotBeds"
        );


    if (title) {

        title.textContent =
            data.title;

    }


    if (description) {

        description.textContent =
            data.description;

    }


    if (area) {

        area.textContent =
            data.area;

    }


    if (beds) {

        const bedCount =
            getBedsForPlot(
                plot
            ).length;


        beds.textContent =
            bedCount;

    }

}


/* =========================================================
   MAP MARKER CLICK
========================================================= */

function handleMapMarkerClick(marker) {

    if (mapEditMode) {
        return;
    }


    const type =
        marker.dataset.marker;


    const id =
        marker.dataset.id;


    if (
        type === "plot"
    ) {

        openPlot(id);

        return;

    }


    if (
        type === "nursery"
    ) {

        /*
         * Nursery opens only its own overlay. Prevent the click
         * from affecting/focusing the top-right bar or other page UI.
         */
        if (typeof marker.blur === "function") {
            marker.blur();
        }

        openNursery();

        return;

    }


    if (
        type === "livestock"
    ) {

        openLivestock(
            id
        );

        return;

    }


    alert(
        `${marker.dataset.name || "Marker"} selected.`
    );

}


/* =========================================================
   MAP EDITING
========================================================= */

let mapEditMode =
    false;


let mapEditTool =
    "move";


let draggingMarker =
    null;


let editSnapshot =
    null;


const MAP_STORAGE_KEY =
    "sanctuarioFarmMap";


/* =========================================================
   SNAPSHOT
========================================================= */

function createEditSnapshot() {

    editSnapshot =
        [
            ...document.querySelectorAll(
                ".editable-marker"
            )
        ].map(
            marker => ({

                id:
                    marker.id,

                left:
                    marker.style.left,

                top:
                    marker.style.top,

                name:
                    marker.dataset.name || "",

                marker:
                    marker.dataset.marker || "",

                idData:
                    marker.dataset.id || "",

                description:
                    marker.dataset.description || "",

                area:
                    marker.dataset.area || "",

                isNew:
                    marker.classList.contains(
                        "new-marker"
                    ),

                plotData:
                    marker.dataset.marker === "plot" &&
                    plotData[
                        marker.dataset.id
                    ]
                        ? {
                            ...plotData[
                                marker.dataset.id
                            ]
                        }
                        : null

            })
        );

}


/* =========================================================
   TOGGLE MAP EDIT
========================================================= */

function toggleMapEditMode() {
    if (!MAP_CAN_EDIT_ALL) return;

    mapEditMode
        ? leaveMapEditMode()
        : enterMapEditMode();

}


function enterMapEditMode() {
    if (!MAP_CAN_EDIT_ALL) return;

    mapEditMode =
        true;


    mapEditTool =
        "move";


    createEditSnapshot();


    const button =
        document.getElementById(
            "editMapBtn"
        );


    document.getElementById(
        "mapEditControls"
    ).classList.add(
        "show"
    );


    button.classList.add(
        "editing"
    );


    button.innerHTML =
        '<i class="fa-solid fa-eye"></i> View Map';


    document.getElementById(
        "mapEditStatus"
    ).textContent =
        "Move — Drag a marker";


    mapContainer.classList.add(
        "edit-mode"
    );


    enableEditableMarkers();

}


function leaveMapEditMode() {

    mapEditMode =
        false;


    mapEditTool =
        "move";


    draggingMarker =
        null;


    document.getElementById(
        "mapEditControls"
    ).classList.remove(
        "show"
    );


    const button =
        document.getElementById(
            "editMapBtn"
        );


    button.classList.remove(
        "editing"
    );


    button.innerHTML =
        '<i class="fa-solid fa-pen-to-square"></i> Edit Map';


    document.getElementById(
        "mapEditStatus"
    ).textContent =
        "View Mode";


    mapContainer.classList.remove(
        "edit-mode"
    );


    editSnapshot =
        null;

}


/* =========================================================
   MAP EDIT TOOL
========================================================= */

function setMapEditTool(tool) {
    if (!MAP_CAN_EDIT_ALL) return;

    if (!mapEditMode) {
        return;
    }


    mapEditTool =
        [
            "plot",
            "marker",
            "move"
        ].includes(tool)
            ? tool
            : "move";


    const status =
        document.getElementById(
            "mapEditStatus"
        );


    if (
        mapEditTool === "plot"
    ) {

        status.textContent =
            "Add Plot — Click the map";

    } else if (
        mapEditTool === "marker"
    ) {

        status.textContent =
            "Add Marker — Click the map";

    } else {

        status.textContent =
            "Move — Drag a marker";

    }

}


/* =========================================================
   MAP POSITION
========================================================= */

function getMapPosition(event) {

    const rect =
        mainMapLayer.getBoundingClientRect();


    if (
        !rect.width ||
        !rect.height
    ) {

        return {
            x: 0,
            y: 0
        };

    }


    return {

        x:
            Math.max(
                0,
                Math.min(
                    100,
                    (
                        (event.clientX - rect.left) /
                        rect.width
                    ) * 100
                )
            ),


        y:
            Math.max(
                0,
                Math.min(
                    100,
                    (
                        (event.clientY - rect.top) /
                        rect.height
                    ) * 100
                )
            )

    };

}


/* =========================================================
   ADD NEW PLOT
========================================================= */

function addNewPlot(event) {
    if (!MAP_CAN_EDIT_ALL) return;

    const position =
        getMapPosition(
            event
        );


    const name =
        prompt(
            "Enter plot name:",
            "Plot D"
        );


    if (
        !name ||
        !name.trim()
    ) {
        return;
    }


    const description =
        prompt(
            "Enter plot description:",
            "Crop Production Area"
        ) || "";


    const area =
        prompt(
            "Enter plot area:",
            "300m²"
        ) || "";


    const bedsInput =
        prompt(
            "How many beds does this plot have?",
            "10"
        );


    const beds =
        Math.max(
            0,
            Number.parseInt(
                bedsInput,
                10
            ) || 0
        );


    const id =
        `plot-${Date.now()}`;


    plotData[id] = {

        image:
            "<?= $basePath ?>farmMap/plot_a.jpg",

        title:
            name.trim(),

        description,

        beds,

        area

    };


    const marker =
        document.createElement(
            "button"
        );


    marker.type =
        "button";


    marker.className =
        "main-marker plot-marker editable-marker new-marker";


    marker.id =
        id;


    marker.dataset.marker =
        "plot";


    marker.dataset.id =
        id;


    marker.dataset.name =
        name.trim();


    marker.dataset.description =
        description;


    marker.dataset.area =
        area;


    marker.style.left =
        `${position.x}%`;


    marker.style.top =
        `${position.y}%`;


    marker.innerHTML =
        `
            <img
                src="<?= $basePath ?>markers/plot_a.png"
                alt="${escapeHTML(name.trim())}"
            >

            <span>
                ${escapeHTML(name.trim())}
            </span>
        `;


    marker.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();

            handleMapMarkerClick(
                marker
            );

        }
    );


    mainMapLayer.appendChild(
        marker
    );


    enableMarkerDragging(
        marker
    );

}


/* =========================================================
   ADD NEW MARKER
========================================================= */

function addNewMarker(event) {
    if (!MAP_CAN_EDIT_ALL) return;

    const position =
        getMapPosition(
            event
        );


    const name =
        prompt(
            "Enter marker name:",
            "New Marker"
        );


    if (
        !name ||
        !name.trim()
    ) {
        return;
    }


    let type =
        (
            prompt(
                "Enter marker type:\n\nlivestock\nnursery\nother",
                "other"
            ) ||
            "other"
        )
        .toLowerCase()
        .trim();


    if (
        ![
            "livestock",
            "nursery",
            "other"
        ].includes(type)
    ) {

        type =
            "other";

    }


    const id =
        `marker-${Date.now()}`;


    const marker =
        document.createElement(
            "button"
        );


    marker.type =
        "button";


    marker.className =
        `main-marker editable-marker new-marker${
            type === "livestock"
                ? " livestock-marker"
                : ""
        }`;


    marker.id =
        id;


    marker.dataset.marker =
        type;


    marker.dataset.id =
        id;


    marker.dataset.name =
        name.trim();


    marker.style.left =
        `${position.x}%`;


    marker.style.top =
        `${position.y}%`;


    const icon =
        type === "livestock"
            ? "fa-cow"
            : type === "nursery"
                ? "fa-seedling"
                : "fa-location-dot";


    marker.innerHTML =
        `
            <div class="new-map-icon">
                <i class="fa-solid ${icon}"></i>
            </div>

            <span>
                ${escapeHTML(name.trim())}
            </span>
        `;


    marker.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();

            handleMapMarkerClick(
                marker
            );

        }
    );


    mainMapLayer.appendChild(
        marker
    );


    enableMarkerDragging(
        marker
    );

}


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHTML(value) {

    const div =
        document.createElement(
            "div"
        );


    div.textContent =
        String(
            value ?? ""
        );


    return div.innerHTML;

}


/* =========================================================
   ENABLE EDITABLE MARKERS
========================================================= */

function enableEditableMarkers() {
    if (!MAP_CAN_EDIT_ALL) return;

    document.querySelectorAll(
        ".editable-marker"
    ).forEach(
        enableMarkerDragging
    );

}


/* =========================================================
   ENABLE MARKER DRAGGING
========================================================= */

function enableMarkerDragging(marker) {

    if (
        marker.dataset.dragReady === "true"
    ) {

        return;

    }


    marker.dataset.dragReady =
        "true";


    marker.addEventListener(
        "mousedown",
        function(event) {

            if (
                !mapEditMode ||
                mapEditTool !== "move"
            ) {
                return;
            }


            draggingMarker =
                marker;


            event.preventDefault();

            event.stopPropagation();

        }
    );

}


/* =========================================================
   DRAG MARKER
========================================================= */

document.addEventListener(
    "mousemove",
    function(event) {

        if (
            !draggingMarker ||
            !mapEditMode ||
            mapEditTool !== "move"
        ) {
            return;
        }


        const rect =
            mainMapLayer.getBoundingClientRect();


        if (
            !rect.width ||
            !rect.height
        ) {
            return;
        }


        draggingMarker.style.left =
            `${Math.max(
                0,
                Math.min(
                    100,
                    (
                        (event.clientX - rect.left) /
                        rect.width
                    ) * 100
                )
            )}%`;


        draggingMarker.style.top =
            `${Math.max(
                0,
                Math.min(
                    100,
                    (
                        (event.clientY - rect.top) /
                        rect.height
                    ) * 100
                )
            )}%`;

    }
);


document.addEventListener(
    "mouseup",
    function() {

        draggingMarker =
            null;

    }
);


/* =========================================================
   MAP STORAGE
========================================================= */

function getStoredMapData() {

    try {

        const value =
            JSON.parse(
                localStorage.getItem(
                    MAP_STORAGE_KEY
                )
            );


        const markers = Array.isArray(value) ? value : [];
        const filtered = markers.filter(marker => {
            const name = String(marker?.name || "").trim().toLowerCase();
            const id = String(marker?.id || "").trim().toLowerCase();
            return name !== "plot 9" && name !== "plot9" && id !== "plot-9" && id !== "plot_9";
        });

        // One-time cleanup for the old manually-added Plot 9 marker.
        if (filtered.length !== markers.length) {
            localStorage.setItem(MAP_STORAGE_KEY, JSON.stringify(filtered));
        }

        return filtered;

    } catch (error) {

        console.error(
            "Unable to load map positions:",
            error
        );

        return [];

    }

}


function saveAllMapData() {
    if (!MAP_CAN_EDIT_ALL) return;

    const mapData =
        [
            ...document.querySelectorAll(
                ".editable-marker"
            )
        ].map(
            marker => ({

                id:
                    marker.id,

                type:
                    marker.dataset.marker ||
                    "other",

                name:
                    marker.dataset.name ||
                    "Marker",

                left:
                    marker.style.left,

                top:
                    marker.style.top,

                description:
                    marker.dataset.description ||
                    "",

                area:
                    marker.dataset.area ||
                    "",

                plot:
                    marker.dataset.marker === "plot" &&
                    plotData[
                        marker.dataset.id
                    ]
                        ? {
                            ...plotData[
                                marker.dataset.id
                            ]
                        }
                        : null

            })
        );


    localStorage.setItem(
        MAP_STORAGE_KEY,
        JSON.stringify(
            mapData
        )
    );

}


/* =========================================================
   RESTORE MAP SNAPSHOT
========================================================= */

function restoreEditSnapshot() {

    if (
        !Array.isArray(
            editSnapshot
        )
    ) {
        return;
    }


    document.querySelectorAll(
        ".new-marker"
    ).forEach(
        marker => marker.remove()
    );


    editSnapshot.forEach(
        data => {

            const marker =
                document.getElementById(
                    data.id
                );


            if (!marker) return;


            marker.style.left =
                data.left;


            marker.style.top =
                data.top;


            marker.dataset.name =
                data.name;


            marker.dataset.marker =
                data.marker;


            marker.dataset.id =
                data.idData;


            marker.dataset.description =
                data.description;


            marker.dataset.area =
                data.area;


            const label =
                marker.querySelector(
                    "span"
                );


            if (label) {

                label.textContent =
                    data.name;

            }


            if (
                data.plotData
            ) {

                plotData[
                    data.idData
                ] = {
                    ...data.plotData
                };

            }

        }
    );


    editSnapshot =
        null;

}


/* =========================================================
   SAVE MAP
========================================================= */

function saveEditableMap() {
    if (!MAP_CAN_EDIT_ALL) return;

    saveAllMapData();


    alert(
        "Farm map changes saved successfully."
    );


    leaveMapEditMode();

}


/* =========================================================
   CANCEL MAP
========================================================= */

function cancelMapEdit() {

    if (
        !confirm(
            "Discard your current changes?"
        )
    ) {
        return;
    }


    restoreEditSnapshot();


    leaveMapEditMode();

}


/* =========================================================
   LOAD SAVED MARKER
========================================================= */

function createLoadedMarker(data) {

    const marker =
        document.createElement(
            "button"
        );


    marker.type =
        "button";


    marker.id =
        data.id;


    marker.dataset.marker =
        data.type ||
        "other";


    marker.dataset.id =
        data.id;


    marker.dataset.name =
        data.name ||
        "Marker";


    marker.dataset.description =
        data.description ||
        "";


    marker.dataset.area =
        data.area ||
        "";


    marker.style.left =
        data.left ||
        "50%";


    marker.style.top =
        data.top ||
        "50%";


    if (
        data.type === "plot"
    ) {

        marker.className =
            "main-marker plot-marker editable-marker new-marker";


        marker.innerHTML =
            `
                <img
                    src="<?= $basePath ?>markers/plot_a.png"
                    alt="${escapeHTML(data.name || "Plot")}"
                >

                <span>
                    ${escapeHTML(data.name || "Plot")}
                </span>
            `;

    } else {

        marker.className =
            `main-marker editable-marker new-marker${
                data.type === "livestock"
                    ? " livestock-marker"
                    : ""
            }`;


        const icon =
            data.type === "livestock"
                ? "fa-cow"
                : data.type === "nursery"
                    ? "fa-seedling"
                    : "fa-location-dot";


        marker.innerHTML =
            `
                <div class="new-map-icon">
                    <i class="fa-solid ${icon}"></i>
                </div>

                <span>
                    ${escapeHTML(data.name || "Marker")}
                </span>
            `;

    }


    marker.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();

            handleMapMarkerClick(
                marker
            );

        }
    );


    mainMapLayer.appendChild(
        marker
    );


    enableMarkerDragging(
        marker
    );


    return marker;

}


/* =========================================================
   LOAD SAVED MARKERS
========================================================= */

function loadEditableMarkers() {

    getStoredMapData()
        .forEach(
            data => {

                if (
                    !data ||
                    !data.id
                ) {
                    return;
                }


                if (
                    data.type === "plot" &&
                    data.plot
                ) {

                    plotData[
                        data.id
                    ] = {
                        ...data.plot
                    };

                }


                const existing =
                    document.getElementById(
                        data.id
                    );


                if (existing) {

                    existing.style.left =
                        data.left ||
                        existing.style.left;


                    existing.style.top =
                        data.top ||
                        existing.style.top;


                    existing.dataset.name =
                        data.name ||
                        existing.dataset.name;


                    existing.dataset.description =
                        data.description ||
                        existing.dataset.description ||
                        "";


                    existing.dataset.area =
                        data.area ||
                        existing.dataset.area ||
                        "";


                    const label =
                        existing.querySelector(
                            "span"
                        );


                    if (
                        label &&
                        data.name
                    ) {

                        label.textContent =
                            data.name;

                    }


                    return;

                }


                createLoadedMarker(
                    data
                );

            }
        );

}


/* =========================================================
   MAP ADD CLICK
========================================================= */

mapContainer.addEventListener(
    "click",
    function(event) {

        if (!mapEditMode) {
            return;
        }


        if (
            event.target.closest(
                ".main-marker"
            ) ||
            isOverlayTarget(
                event.target
            )
        ) {

            return;

        }


        if (
            !event.target.closest(
                "#mainMapLayer"
            )
        ) {

            return;

        }


        if (
            mapEditTool === "plot"
        ) {

            addNewPlot(
                event
            );

        } else if (
            mapEditTool === "marker"
        ) {

            addNewMarker(
                event
            );

        }

    }
);



/* =========================================================
   CLEAN FARM MAP BOARD UX HELPERS
========================================================= */
let farmMapToastTimer = null;

function showFarmMapToast(message) {
    const toast = document.getElementById("farmMapToast");
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add("show");
    clearTimeout(farmMapToastTimer);
    farmMapToastTimer = setTimeout(() => {
        toast.classList.remove("show");
    }, 1600);
}

function autoSaveBedBoard() {
    if (!MAP_CAN_EDIT_CROPS) return;
    saveCurrentBedState();
}


function finishBedEditing() {
    if (!MAP_CAN_EDIT_CROPS) return;
    if (!bedEditMode) return;

    autoSaveBedBoard();

    bedEditMode = false;
    bedPositionEditMode = false;
    selectedBed = null;
    draggingBed = null;
    bedSnapshot = null;
    deletedBedSlotsSnapshot = null;

    const popup = document.getElementById("plotPopup");
    if (popup) popup.classList.remove("bed-editing");

    document.querySelectorAll("#bedMarkersContainer .bed-marker").forEach(marker => {
        marker.classList.remove("editing", "selected");
    });

    updateBedEditUI();
    showFarmMapToast("Bed board saved automatically");
}

/* =========================================================
   PLOT EDIT BUTTONS
========================================================= */

const editPositionBtn = document.getElementById("editPositionBtn");
if (editPositionBtn) editPositionBtn.addEventListener("click", toggleBedPositionEditing);

const editSelectedCropBtn = document.getElementById("editSelectedCropBtn");
if (editSelectedCropBtn) editSelectedCropBtn.addEventListener("click", editSelectedCrop);


/* =========================================================
   BUTTON EVENTS
========================================================= */

document.getElementById(
    "editMapBtn"
).addEventListener(
    "click",
    toggleMapEditMode
);


document.querySelectorAll(
    "#mapEditControls button[data-tool]"
).forEach(
    button => {

        button.addEventListener(
            "click",
            () =>
                setMapEditTool(
                    button.dataset.tool
                )
        );

    }
);


document.getElementById(
    "saveMapBtn"
).addEventListener(
    "click",
    saveEditableMap
);


document.getElementById(
    "cancelMapBtn"
).addEventListener(
    "click",
    cancelMapEdit
);


/* =========================================================
   BED BUTTONS
========================================================= */

document.getElementById(
    "startEditBedsBtn"
).addEventListener(
    "click",
    function() {
        if (bedEditMode) {
            finishBedEditing();
        } else {
            startBedEditing();
        }
    }
);


document.getElementById(
    "deleteBedBtn"
).addEventListener(
    "click",
    deleteSelectedBed
);


document.getElementById(
    "saveBedsBtn"
).addEventListener(
    "click",
    saveBeds
);


document.getElementById(
    "cancelBedsBtn"
).addEventListener(
    "click",
    cancelBedEditing
);


/* =========================================================
   RELIABLE MAP MARKER CLICK HANDLING
========================================================= */
if (mainMapLayer) {
    mainMapLayer.addEventListener("click", function(event) {
        const marker = event.target.closest(".main-marker");

        if (!marker || !mainMapLayer.contains(marker)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        handleMapMarkerClick(marker);
    });
}


/* =========================================================
   EXISTING MAP MARKERS
========================================================= */

/* Existing markers are handled by the delegated listener above. */


/* =========================================================
   POPUP CLOSE BUTTONS
========================================================= */

document.getElementById(
    "closePlotBtn"
).addEventListener(
    "click",
    closePlot
);


document.getElementById(
    "closeNurseryBtn"
).addEventListener(
    "click",
    closeNursery
);


document.getElementById(
    "closeCropProfileBtn"
).addEventListener(
    "click",
    closeCropProfile
);

const closeLivestockBtn =
    document.getElementById("closeLivestockBtn");

if (closeLivestockBtn) {

    closeLivestockBtn.addEventListener("click", function () {

        const overlay =
            document.getElementById("livestockOverlay");

        if (overlay) {
            overlay.classList.remove("show");
        }

    });

}

const closeLivestockProfileBtn =
    document.getElementById("closeLivestockProfileBtn");

if (closeLivestockProfileBtn) {

    closeLivestockProfileBtn.addEventListener("click", function () {

        const profileOverlay =
            document.getElementById("livestockProfileOverlay");

        if (profileOverlay) {
            profileOverlay.classList.remove("show");
        }

    });
}


/* =========================================================
   CROP PROFILE BUTTONS
========================================================= */

document.getElementById("transplantCropBtn")?.addEventListener("click", openCropTransplantForm);
document.getElementById("farmMapTransferClose")?.addEventListener("click", closeFarmMapTransferModal);
document.getElementById("farmMapTransferOverlay")?.addEventListener("click", event => {
    if (event.target.id === "farmMapTransferOverlay") closeFarmMapTransferModal();
});

document.getElementById("harvestCropBtn")?.addEventListener("click", openCropHarvestForm);
document.getElementById("saveCropHarvestBtn")?.addEventListener("click", saveCropHarvest);
document.getElementById("cancelCropHarvestBtn")?.addEventListener("click", cancelCropHarvestForm);

document.getElementById(
    "editCropProfileBtn"
).addEventListener(
    "click",
    startCropProfileEditing
);


document.getElementById(
    "saveCropProfileBtn"
).addEventListener(
    "click",
    saveCropProfile
);


document.getElementById(
    "cancelCropProfileBtn"
).addEventListener(
    "click",
    cancelCropProfileEditing
);


/* =========================================================
   PLOT SELECT
========================================================= */

const plotSelect =
    document.getElementById(
        "plotSelect"
    );


if (plotSelect) {

    plotSelect.addEventListener(
        "change",
        function() {

            const selected =
                this.value;


            updateSidePlotInfo(
                selected
            );

        }
    );

}


/* =========================================================
   OVERLAY CLICK
========================================================= */

document.addEventListener(
    "click",
    function(event) {

        if (
            event.target.id ===
            "plotOverlay"
        ) {

            closePlot();

        }


        if (
            event.target.id ===
            "nurseryOverlay"
        ) {

            closeNursery();

        }


        if (
            event.target.id ===
            "cropProfileOverlay"
        ) {

            closeCropProfile();

        }


        if (
            event.target.id ===
            "livestockOverlay"
        ) {

            closeLivestock();

        }


        if (
            event.target.id ===
            "livestockProfileOverlay"
        ) {

            closeLivestockProfile();

        }

    }
);


/* =========================================================
   ESC KEY
========================================================= */

document.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key !== "Escape"
        ) {
            return;
        }


        if (
            document.getElementById("livestockProfileOverlay")
                .classList.contains("show")
        ) {

            closeLivestockProfile();
            return;

        }


        if (
            document.getElementById("livestockOverlay")
                .classList.contains("show")
        ) {

            closeLivestock();
            return;

        }


        if (
            document
                .getElementById(
                    "cropProfileOverlay"
                )
                .classList.contains(
                    "show"
                )
        ) {

            closeCropProfile();

            return;

        }


        if (
            document
                .getElementById(
                    "plotOverlay"
                )
                .classList.contains(
                    "show"
                )
        ) {

            closePlot();

            return;

        }


        closeNursery();

    }
);


/* =========================================================
   INITIAL LOAD
========================================================= */

loadEditableMarkers();


requestAnimationFrame(
    function() {

        clampMap();

        updateMainMap();

    }
);


updateSidePlotInfo(
    "A"
);


updateBedEditUI();

</script>

<!-- DATABASE-DRIVEN CROP LIFECYCLE GANTT (ADD-ON) -->
<script src="<?= $basePath ?>farmMap/farmMap_lifecycle.js?v=20261016-nursery-target-transplant"></script>

<div id="farmMapToast" class="farm-map-toast" role="status" aria-live="polite"></div>
</body>

</html>