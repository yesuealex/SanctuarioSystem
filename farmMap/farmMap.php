<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'admin';
$adminName = $_SESSION['name'] ?? 'Admin';

$basePath = '/SanctuarioSystem/';

include '../db.php';

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
| CROPS (grouped by plot, active only)
|--------------------------------------------------------------------------
*/

$cropsByPlotId = [];

$cropsRes2 = $conn->query("
    SELECT *
    FROM crops
    WHERE archived = 0
    ORDER BY crop_id ASC
");

if ($cropsRes2) {
    while ($c = $cropsRes2->fetch_assoc()) {
        $cropsByPlotId[$c['plot_id']][] = $c;
    }
}

$dbCropsByPlot = [];

foreach ($plotCodeByLetter as $letter => $code) {

    $pid = $plotRowsByCode[$code]['plot_id'] ?? null;

    $dbCropsByPlot[$letter] =
        ($pid !== null && isset($cropsByPlotId[$pid]))
            ? array_values($cropsByPlotId[$pid])
            : [];
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

$liveRes = $conn->query("
    SELECT *
    FROM livestocks
    WHERE archived = 0
    ORDER BY livestock_id ASC
");

if ($liveRes) {
    while ($l = $liveRes->fetch_assoc()) {
        $livestocksByHouseId[$l['house_id']][] = $l;
    }
}

$dbLivestockHouses = [];

foreach ($houseRowsByCode as $code => $h) {

    $dbLivestockHouses[$code] = [
        'house'   => $h,
        'animals' => array_values(
            $livestocksByHouseId[$h['house_id']] ?? []
        )
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
        href="<?= $basePath ?>farmMap/farmMap.css"
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

    width: 42px;
    height: 42px;

    padding: 3px;

    border-radius: 50%;

    background: rgba(255,255,255,.96);

    border: 2px solid #ffffff;

    box-shadow:
        0 2px 8px rgba(0,0,0,.25);

    overflow: visible;

}

.bed-marker.crop-image-marker:hover {

    transform:
        translate(-50%, -50%)
        scale(1.1);

}

.bed-marker.crop-image-marker img {

    width: 34px;
    height: 34px;

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

    color: #ffffff;

    border: 2px solid #ffffff;

    font-size: 8px;
    font-weight: 700;

    line-height: 12px;

    display: flex;
    align-items: center;
    justify-content: center;

}


/* =========================================================
   IMAGE LIVESTOCK MARKERS
========================================================= */

.livestock-house-marker.livestock-image-marker {

    width: 46px;
    height: 46px;

    padding: 3px;

    background: rgba(255,255,255,.96);

    border: 2px solid #ffffff;

    border-radius: 50%;

    box-shadow:
        0 3px 10px rgba(0,0,0,.28);

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
========================================================= */

.marker-icon-chooser {

    grid-column: 1 / -1;

}

.marker-icon-chooser-label {

    display: block;

    margin-bottom: 7px;

    color: #666;

    font-size: 9px;

    font-weight: 600;

}

.marker-icon-options {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 7px;

}

.marker-icon-option {

    position: relative;

    min-width: 0;

}

.marker-icon-option input {

    position: absolute;

    opacity: 0;

    pointer-events: none;

}

.marker-icon-option label {

    min-height: 72px;

    padding: 7px 5px;

    border: 1px solid #d9e0d5;

    border-radius: 9px;

    background: #ffffff;

    cursor: pointer;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    gap: 5px;

    transition:
        border-color .2s ease,
        background .2s ease,
        box-shadow .2s ease,
        transform .15s ease;

}

.marker-icon-option label:hover {

    border-color: #a8bd9e;

    transform: translateY(-1px);

}

.marker-icon-option input:checked + label {

    border-color: #6b8e23;

    background: #f1f6ed;

    box-shadow:
        0 0 0 2px rgba(107,142,35,.12);

}

.marker-icon-option img {

    width: 38px;
    height: 38px;

    object-fit: contain;

    display: block;

}

.marker-icon-option span {

    color: #52604d;

    font-size: 8px;

    font-weight: 600;

    text-align: center;

}

.marker-position-row {

    grid-column: 1 / -1;

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0,1fr));

    gap: 11px;

}


@media (max-width: 600px) {

    .marker-icon-options {

        grid-template-columns:
            repeat(2, minmax(0,1fr));

    }

}

.bed-marker {
    position: absolute;

    transform: translate(-50%, -50%);

    width: 30px;
    height: 30px;

    border-radius: 50%;

    border: 2px solid white;

    background: #5f8f45;
    color: white;

    box-shadow:
        0 2px 8px rgba(0,0,0,.25);

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

    z-index: 9999;

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
    z-index: 9999;

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
                        data-tool="plot"
                    >

                        <i class="fa-solid fa-vector-square"></i>

                        Add Plot

                    </button>


                    <button
                        type="button"
                        data-tool="marker"
                    >

                        <i class="fa-solid fa-location-dot"></i>

                        Add Marker

                    </button>


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
                        100
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

                    <strong class="summary-number">
                        100
                    </strong>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-icon summary-light-green">

                    <i class="fa-solid fa-check"></i>

                </div>

                <div>

                    <span class="summary-label">
                        Harvest Ready
                    </span>

                    <strong class="summary-number">
                        100
                    </strong>

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

                <div
                    class="main-map-layer"
                    id="mainMapLayer"
                >

                    <img
                        src="<?= $basePath ?>farm_map.jpg"
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
                        class="main-marker plot-marker editable-marker"
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

                        <span>
                            Plot A
                        </span>

                    </button>


                    <!-- =================================================
                         PLOT B
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker plot-marker editable-marker"
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

                        <span>
                            Plot B
                        </span>

                    </button>


                    <!-- =================================================
                         PLOT C
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker plot-marker editable-marker"
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

                        <span>
                            Plot C
                        </span>

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
                     MAP HINT
                ================================================== -->

                <div
                    class="map-hint"
                    id="mapHint"
                >
                    Drag the map to view other areas • Click a plot to zoom
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

                            </div>

                            <p id="plotDescription">
                                Main Vegetable Production Area
                            </p>

                              <!-- =================================================
                                 BED EDIT CONTROLS
                            ================================================== -->

                            <div
                                class="bed-edit-toolbar"
                                id="bedEditToolbar"
                            >

                                <button
                                    type="button"
                                    id="startEditBedsBtn"
                                >

                                    <i class="fa-solid fa-pen"></i>

                                    Edit Beds

                                </button>


                                <button
                                    type="button"
                                    id="addBedBtn"
                                    style="display:none;"
                                >

                                    <i class="fa-solid fa-plus"></i>

                                    Add Bed

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
                >

                    <div class="nursery-popup">

                        <button
                            type="button"
                            class="popup-close"
                            id="closeNurseryBtn"
                        >

                            <i class="fa-solid fa-xmark"></i>

                        </button>


                        <h3>

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

                <div class="livestock-image-actions">

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
                    <span>Number</span>
                    <strong id="profileLivestockNumber">-</strong>
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

<div class="livestock-input-group full marker-icon-chooser">

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
                                        Targeted Area
                                    </span>

                                    <strong id="profileCropArea">
                                        A1
                                    </strong>

                                </div>


                                <div class="crop-profile-field">

                                    <span>
                                        Planted Date
                                    </span>

                                    <strong id="profileCropPlantedDate">
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


                                    <div class="crop-time-stat">

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

    </div>

</div>

<!-- =================================================
     CROP MARKER POSITION
================================================== -->

<div class="crop-input-group">

    <label for="editCropMarkerX">
        Position X (%)
    </label>

    <input
        type="number"
        id="editCropMarkerX"
        min="0"
        max="100"
        step="0.1"
    >

</div>


<div class="crop-input-group">

    <label for="editCropMarkerY">
        Position Y (%)
    </label>

    <input
        type="number"
        id="editCropMarkerY"
        min="0"
        max="100"
        step="0.1"
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
        Date Sowing
    </label>

    <input
        type="date"
        id="editCropDateSowing"
    >

</div>


<div class="crop-input-group">

    <label>
        Target Transplant Date
    </label>

    <input
        type="date"
        id="editCropTargetTransplant"
    >

</div>


<div class="crop-input-group">

    <label>
        Target Area
    </label>

    <input
        type="text"
        id="editCropTargetArea"
        placeholder="e.g. 100m²"
    >

</div>


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
                                            Targeted Area
                                        </label>

                                        <input
                                            type="text"
                                            id="editCropArea"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Planted Date
                                        </label>

                                        <input
                                            type="date"
                                            id="editCropPlantedDate"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Weeks Planted
                                        </label>

                                        <input
                                            type="number"
                                            id="editCropWeeks"
                                            min="0"
                                            step="1"
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


                            <!-- ACTIONS -->

                            <div class="crop-profile-actions">

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
   MAP PAN / DRAG
========================================================= */

let translateX = 0;

let draggingMap = false;

let mapStartX = 0;

const mapScale = 1;


function getMapWidth() {

    return farmMap.offsetWidth ||
           mainMapLayer.scrollWidth ||
           0;

}


function clampMap() {

    const containerWidth =
        mapContainer.clientWidth;

    const mapWidth =
        getMapWidth() * mapScale;


    if (mapWidth <= containerWidth) {

        translateX =
            (containerWidth - mapWidth) / 2;

        return;

    }


    const minX =
        containerWidth - mapWidth;


    translateX =
        Math.max(
            minX,
            Math.min(0, translateX)
        );

}


function updateMainMap() {

    mainMapLayer.style.transform =
        `translate3d(${translateX}px, 0, 0) scale(${mapScale})`;

}


function initializeMap() {

    translateX = 0;

    clampMap();

    updateMainMap();

}


if (farmMap.complete) {

    requestAnimationFrame(
        initializeMap
    );

} else {

    farmMap.addEventListener(
        "load",
        () => requestAnimationFrame(initializeMap),
        { once: true }
    );

}


function isOverlayTarget(target) {

    return !!target.closest(
        ".plot-overlay, .plant-overlay, .nursery-overlay, .crop-profile-overlay, .livestock-overlay, .livestock-profile-overlay"
    );

}


mapContainer.addEventListener(
    "mousedown",
    function(event) {

        if (
            event.target.closest(".main-marker") ||
            isOverlayTarget(event.target)
        ) {
            return;
        }


        if (
            mapEditMode &&
            mapEditTool !== "move"
        ) {
            return;
        }


        draggingMap = true;

        mapStartX = event.clientX;

        mapContainer.classList.add(
            "dragging"
        );

        event.preventDefault();

    }
);


document.addEventListener(
    "mousemove",
    function(event) {

        if (!draggingMap) return;


        const dx =
            event.clientX - mapStartX;

        mapStartX =
            event.clientX;


        translateX += dx;

        clampMap();

        updateMainMap();

    }
);


document.addEventListener(
    "mouseup",
    function() {

        draggingMap = false;

        mapContainer.classList.remove(
            "dragging"
        );

    }
);


farmMap.addEventListener(
    "dragstart",
    event => event.preventDefault()
);


/* =========================================================
   TOUCH MAP DRAG
========================================================= */

let touchStartX = 0;

let touchingMap = false;


mapContainer.addEventListener(
    "touchstart",
    function(event) {

        if (
            event.target.closest(".main-marker") ||
            isOverlayTarget(event.target)
        ) {
            return;
        }


        if (
            mapEditMode &&
            mapEditTool !== "move"
        ) {
            return;
        }


        touchingMap = true;

        touchStartX =
            event.touches[0].clientX;

    },
    {
        passive: true
    }
);


mapContainer.addEventListener(
    "touchmove",
    function(event) {

        if (!touchingMap) return;


        const currentX =
            event.touches[0].clientX;


        translateX +=
            currentX - touchStartX;


        touchStartX =
            currentX;


        clampMap();

        updateMainMap();

    },
    {
        passive: true
    }
);


mapContainer.addEventListener(
    "touchend",
    function() {

        touchingMap = false;

    }
);


window.addEventListener(
    "resize",
    function() {

        clampMap();

        updateMainMap();

    }
);


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
            18,

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
            12,

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
            15,

        area:
            "300m²"

    }

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

        carrot:
            `${basePath}farmMap/icons/crops/carrot.png`,


        corn:
            `${basePath}farmMap/icons/crops/corn.png`,

        eggplant:
            `${basePath}farmMap/icons/crops/eggplant.png`

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

const dbCropsByPlot = <?= json_encode($dbCropsByPlot) ?>;

const dbLivestockHouses = <?= json_encode($dbLivestockHouses) ?>;



/* =========================================================
   BED STORAGE
========================================================= */

const BED_STORAGE_KEY =
    "sanctuarioPlotBeds";


let currentPlot = null;

let bedEditMode = false;

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

    const stored =
        getStoredBeds();


    if (
        Array.isArray(
            stored[plot]
        )
    ) {

        return stored[plot];

    }


    return plotData[plot]
        ? createDefaultBedPositions(
            plotData[plot].beds
        )
        : [];

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
    const crop =
        getCropProfile(
            Number(bed.number)
        );


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


    marker.title =
        cropName;


    marker.innerHTML =
        `
            <img
                src="${iconPath}"
                alt="${escapeHTML(cropName)}"
                draggable="false"
            >

            <span class="crop-bed-number">
                ${escapeHTML(bed.number)}
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
                Number(
                    marker.dataset.bed
                )
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
   RENDER BEDS
========================================================= */

function renderBedMarkers(plot) {

    const container =
        document.getElementById(
            "bedMarkersContainer"
        );


    container.innerHTML = "";


    getBedsForPlot(plot)
        .forEach(
            bed => {

                container.appendChild(
                    createBedMarker(bed)
                );

            }
        );


    updateBedCount();

}


/* =========================================================
   BED COUNT
========================================================= */

function updateBedCount() {

    const count =
        document.querySelectorAll(
            "#bedMarkersContainer .bed-marker"
        ).length;


    const sideBeds =
        document.getElementById(
            "sidePlotBeds"
        );


    if (sideBeds) {

        sideBeds.textContent =
            count;

    }

}


/* =========================================================
   COLLECT BED POSITIONS
========================================================= */

function collectCurrentBedPositions() {

    return [
        ...document.querySelectorAll(
            "#bedMarkersContainer .bed-marker"
        )
    ].map(marker => ({

        number:
            Number(
                marker.dataset.bed
            ),

        x:
            parseFloat(
                marker.style.left
            ) || 0,

        y:
            parseFloat(
                marker.style.top
            ) || 0

    }));

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


    selectedBed =
        null;


    draggingBed =
        null;


    bedSnapshot =
        null;


    document.getElementById(
        "plotPopup"
    ).classList.remove(
        "bed-editing"
    );


    document.getElementById(
        "plotImage"
    ).src =
        data.image;


    document.getElementById(
        "plotTitle"
    ).textContent =
        data.title;


    document.getElementById(
        "plotDescription"
    ).textContent =
        data.description;




    renderBedMarkers(plot);


    document.getElementById(
        "plotOverlay"
    ).classList.add(
        "show"
    );


    updateBedEditUI();

    updateSidePlotInfo(plot);

}


/* =========================================================
   CLOSE PLOT
========================================================= */

function closePlot() {

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


    container.innerHTML = "";


    bedSnapshot.forEach(
        bed => {

            container.appendChild(
                createBedMarker(bed)
            );

        }
    );


    updateBedCount();

}


/* =========================================================
   START BED EDITING
========================================================= */

function startBedEditing() {

    if (!currentPlot) return;


    createBedSnapshot();


    bedEditMode =
        true;


    selectedBed =
        null;


    document.getElementById(
        "plotPopup"
    ).classList.add(
        "bed-editing"
    );


    document.querySelectorAll(
        "#bedMarkersContainer .bed-marker"
    ).forEach(
        marker => {

            marker.classList.add(
                "editing"
            );

        }
    );


    updateBedEditUI();

}


/* =========================================================
   BED EDIT UI
========================================================= */

function updateBedEditUI() {

    const editButton =
        document.getElementById(
            "startEditBedsBtn"
        );


    const addButton =
        document.getElementById(
            "addBedBtn"
        );


    const deleteButton =
        document.getElementById(
            "deleteBedBtn"
        );


    const saveButton =
        document.getElementById(
            "saveBedsBtn"
        );


    const cancelButton =
        document.getElementById(
            "cancelBedsBtn"
        );


    if (!bedEditMode) {

        editButton.style.display =
            "inline-flex";


        addButton.style.display =
            "none";


        deleteButton.style.display =
            "none";


        saveButton.style.display =
            "none";


        cancelButton.style.display =
            "none";


        status.textContent =
            "Click a bed marker to view its crop profile.";


        return;

    }


    editButton.style.display =
        "none";


    addButton.style.display =
        "inline-flex";


    deleteButton.style.display =
        "inline-flex";


    saveButton.style.display =
        "inline-flex";


    cancelButton.style.display =
        "inline-flex";


    status.textContent =
        selectedBed
            ? `Bed ${selectedBed.dataset.bed} selected — drag it to move.`
            : "Drag a bed marker or select one.";

}


/* =========================================================
   SELECT BED
========================================================= */

function selectBed(marker) {

    document.querySelectorAll(
        "#bedMarkersContainer .bed-marker"
    ).forEach(
        item => {

            item.classList.remove(
                "selected"
            );

        }
    );


    selectedBed =
        marker;


    marker.classList.add(
        "selected"
    );


    updateBedEditUI();

}


/* =========================================================
   DRAG BED
========================================================= */

document.addEventListener(
    "mousemove",
    function(event) {

        if (
            !draggingBed ||
            !bedEditMode
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

        draggingBed =
            null;

    }
);


/* =========================================================
   ADD BED
========================================================= */

function addBed() {

    if (
        !currentPlot ||
        !bedEditMode
    ) {
        return;
    }


    const container =
        document.getElementById(
            "bedMarkersContainer"
        );


    const numbers =
        [
            ...container.querySelectorAll(
                ".bed-marker"
            )
        ].map(
            m =>
                Number(
                    m.dataset.bed
                ) || 0
        );


    const newNumber =
        numbers.length
            ? Math.max(...numbers) + 1
            : 1;


    const marker =
        createBedMarker({

            number:
                newNumber,

            x:
                50,

            y:
                50

        });


    marker.classList.add(
        "editing"
    );


    container.appendChild(
        marker
    );


    selectBed(marker);

    updateBedCount();

}


/* =========================================================
   DELETE BED
========================================================= */

function deleteSelectedBed() {

    if (
        !bedEditMode ||
        !selectedBed
    ) {

        alert(
            "Select a bed marker first."
        );

        return;

    }


    const number =
        selectedBed.dataset.bed;


    if (
        !confirm(
            `Delete Bed ${number}?`
        )
    ) {
        return;
    }


    selectedBed.remove();


    selectedBed =
        null;


    [
        ...document.querySelectorAll(
            "#bedMarkersContainer .bed-marker"
        )
    ].forEach(
        (marker, index) => {

            marker.dataset.bed =
                String(index + 1);

            marker.textContent =
                index + 1;

            marker.title =
                `Bed ${index + 1}`;

        }
    );


    updateBedCount();

    updateBedEditUI();

}


/* =========================================================
   SAVE BEDS
========================================================= */

function saveBeds() {

    if (
        !currentPlot ||
        !bedEditMode
    ) {
        return;
    }


    const stored =
        getStoredBeds();


    stored[currentPlot] =
        collectCurrentBedPositions();


    saveStoredBeds(
        stored
    );


    bedSnapshot =
        null;


    bedEditMode =
        false;


    selectedBed =
        null;


    draggingBed =
        null;


    document.getElementById(
        "plotPopup"
    ).classList.remove(
        "bed-editing"
    );


    document.querySelectorAll(
        "#bedMarkersContainer .bed-marker"
    ).forEach(
        marker => {

            marker.classList.remove(
                "editing",
                "selected"
            );

        }
    );


    updateBedEditUI();


    alert(
        "Bed marker positions saved successfully."
    );

}


/* =========================================================
   CANCEL BED EDITING
========================================================= */

function cancelBedEditing() {

    if (!bedEditMode) return;


    if (
        !confirm(
            "Discard the current bed marker changes?"
        )
    ) {
        return;
    }


    restoreBedSnapshot();


    bedEditMode =
        false;


    selectedBed =
        null;


    draggingBed =
        null;


    bedSnapshot =
        null;


    document.getElementById(
        "plotPopup"
    ).classList.remove(
        "bed-editing"
    );


    updateBedEditUI();

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


let cropProfileSnapshot =
    null;


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

    const map = {
        planted: "Planted",
        growing: "Growing",
        ready: "Ready for Harvest",
        harvested: "Harvested"
    };

    return map[status] || "Planted";
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
        row.date_transplanted ||
        row.date_sowing ||
        getTodayDate(),

    harvest:
        row.expected_harvest || "",

    status:
        statusDbToLabel(row.status),

    image: ""
};
}


/* =========================================================
   GET CROP PROFILE
========================================================= */

function getCropProfile(bedNumber) {

    const plotCrops = dbCropsByPlot[currentPlot] || [];
    const row = plotCrops[bedNumber - 1];

    const stored = getStoredCropProfiles();
    const key = `${currentPlot}-${bedNumber}`;

    const storedImage =
        stored[key] && stored[key].image
            ? stored[key].image
            : "";

    if (row) {
        const profile = dbCropToProfile(row);
        profile.image = storedImage;
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

function openCropProfile(bedNumber) {

    if (!currentPlot) {
        return;
    }


    currentCropBed =
        bedNumber;


    const crop =
        getCropProfile(
            bedNumber
        );


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

}


/* =========================================================
   UPDATE CROP IMAGE
========================================================= */

function updateCropImage(image) {

    const img =
        document.getElementById(
            "cropProfileImage"
        );

    const placeholder =
        document.getElementById(
            "cropImagePlaceholder"
        );

    if (image) {

        img.src =
            "<?= $basePath ?>" + image;

        img.style.display =
            "block";

        placeholder.style.display =
            "none";

    } else {

        img.src =
            "";

        img.style.display =
            "none";

        placeholder.style.display =
            "flex";

    }

}


/* =========================================================
   EDIT CROP PROFILE
========================================================= */

function startCropProfileEditing() {

    const crop =
        getCropProfile(
            currentCropBed
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

        icon:
            getCropMarkerIconKey(
                currentCropBed,
                crop
            ),

        x:
            currentBed
                ? Number(currentBed.x) || 0
                : 0,

        y:
            currentBed
                ? Number(currentBed.y) || 0
                : 0

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
        "editCropDateSowing"
    ).value =
        crop.dateSowing || "";


    document.getElementById(
        "editCropTargetTransplant"
    ).value =
        crop.targetTransplantDate || "";


    document.getElementById(
        "editCropTargetArea"
    ).value =
        crop.targetArea || "";


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


    document.getElementById(
        "editCropWeeks"
    ).value =
        Math.floor(
            calculateDaysPlanted(
                crop.plantedDate
            ) / 7
        );


    document.getElementById(
        "editCropHarvest"
    ).value =
        crop.harvest || "";


    document.getElementById(
        "editCropStatus"
    ).value =
        crop.status || "Planted";


    /*
     * Load current marker position.
     */
    document.getElementById(
        "editCropMarkerX"
    ).value =
        cropProfileSnapshot.x;


    document.getElementById(
        "editCropMarkerY"
    ).value =
        cropProfileSnapshot.y;


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

    if (!currentPlot || !currentCropBed) {
        return;
    }


    const plantedDate =
        document.getElementById(
            "editCropPlantedDate"
        ).value;


    let weeks =
        Number(
            document.getElementById(
                "editCropWeeks"
            ).value
        );


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
            currentCropBed
        );
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

    const formData =
        new FormData();


    formData.append(
        "crop_id",
        existing.cropId || ""
    );


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
        document.getElementById(
            "editCropDateSowing"
        ).value || ""
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
        "target_transplant_date",
        document.getElementById(
            "editCropTargetTransplant"
        ).value || ""
    );


    formData.append(
        "target_area",
        document.getElementById(
            "editCropTargetArea"
        ).value.trim()
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
        document.getElementById(
            "editCropArea"
        ).value.trim() ||
        `${currentPlot}${currentCropBed}`
    );


    formData.append(
        "date_transplanted",
        finalPlantedDate || ""
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
   SAVE CROP PROFILE BUTTON
========================================================= */

const saveCropProfileBtn =
    document.getElementById("saveCropProfileBtn");

if (saveCropProfileBtn) {

    saveCropProfileBtn.addEventListener(
        "click",
        function () {

            saveCropProfile();

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


    cropProfileSnapshot =
        null;

}


/* =========================================================
   CLOSE CROP PROFILE
========================================================= */

function closeCropProfile() {

    document.getElementById(
        "cropProfileOverlay"
    ).classList.remove(
        "show"
    );


    currentCropBed =
        null;

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

            alert(
                "This crop does not have a database ID yet."
            );

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

document.getElementById(
    "editCropPlantedDate"
).addEventListener(
    "change",
    function() {

        const weeks =
            Math.floor(
                calculateDaysPlanted(
                    this.value
                ) / 7
            );


        document.getElementById(
            "editCropWeeks"
        ).value =
            weeks;

    }
);


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

    document.getElementById(
        "nurseryOverlay"
    ).classList.add(
        "show"
    );

}


function closeNursery() {

    document.getElementById(
        "nurseryOverlay"
    ).classList.remove(
        "show"
    );

}


/* =========================================================
   LIVESTOCK
========================================================= */

/* =========================================================
   LIVESTOCK DATA + STORAGE
========================================================= */

let currentLivestockHouse = null;
let currentLivestockId = null;
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

function getLivestockHouseData(houseId) {

    const entry = dbLivestockHouses[houseId];

    if (!entry) return null;

    const total = entry.animals.length;

    const animals = entry.animals.map(
        (row, index) => dbLivestockToAnimal(row, index, total)
    );

    return {
        title: entry.house.house_name,
        image: entry.house.image ? `${basePath}${entry.house.image}` : "",
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

function createLivestockMarker(animal) {

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
    const x =
        saved.x != null
            ? Number(saved.x)
            : Number(animal.x) || 0;


    const y =
        saved.y != null
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


    marker.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();


            openLivestockProfile(
                animal.id
            );

        }
    );


    return marker;

}

function renderLivestockMarkers(houseId) {

    const container = document.getElementById(
        "livestockMarkersContainer"
    );

    if (!container) return;

    container.innerHTML = "";

    const house = getLivestockHouseData(houseId);

    if (!house) return;

    house.animals.forEach(animal => {
        container.appendChild(
            createLivestockMarker(animal)
        );
    });

    const count = document.getElementById("livestockCount");

    if (count) {
        count.textContent = `${house.animals.length} ${house.animals.length === 1 ? "animal" : "animals"}`;
    }
}

function openLivestock(houseId) {

    const house = getLivestockHouseData(houseId);

    if (!house) {
        alert("This livestock house does not have information yet.");
        return;
    }

    currentLivestockHouse = houseId;

    document.getElementById("livestockHouseImage").src = house.image;
    document.getElementById("livestockHouseTitle").textContent = house.title;
    document.getElementById("livestockHouseDescription").textContent = house.description;

    renderLivestockMarkers(houseId);

    document.getElementById("livestockOverlay").classList.add("show");
}

function closeLivestock() {

    closeLivestockProfile();

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

    document.getElementById("profileLivestockNumber").textContent =
        animal.number ?? "--";

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

    document.getElementById("livestockProfileOverlay").classList.remove("show");
    document.getElementById("livestockEditForm").classList.remove("show");
    setLivestockProfileEditButtons(false);

    currentLivestockId = null;
    livestockProfileSnapshot = null;
}

function setLivestockProfileEditButtons(editing) {

    document.getElementById("editLivestockProfileBtn").style.display =
        editing ? "none" : "inline-flex";

    document.getElementById("saveLivestockProfileBtn").style.display =
        editing ? "inline-flex" : "none";

    document.getElementById("cancelLivestockProfileBtn").style.display =
        editing ? "inline-flex" : "none";
}

function startLivestockProfileEditing() {

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

    const animal = getCurrentLivestock();

    if (!animal || !currentLivestockHouse) return;

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


const livestockMarkerData =
    storedMarkerData[
        animal.id
    ] || {};


if (
    selectedLivestockIcon &&
    FARM_MARKER_ICONS.livestock[
        selectedLivestockIcon.value
    ]
) {

    livestockMarkerData.icon =
        selectedLivestockIcon.value;

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


storedMarkerData[
    animal.id
] =
    livestockMarkerData;


saveStoredLivestockMarkerData(
    storedMarkerData
);


    /*
    |--------------------------------------------------------------------------
    | CREATE FORM DATA
    |--------------------------------------------------------------------------
    */

    const formData = new FormData();


    formData.append(
        "livestock_id",
        animal.id
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

            if (result.image) {

                animal.image =
                    `${basePath}${result.image}`;

                updateLivestockImage(
                    animal.image
                );
            }


            alert(
                "Livestock profile saved successfully."
            );


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

    mapEditMode
        ? leaveMapEditMode()
        : enterMapEditMode();

}


function enterMapEditMode() {

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


        return Array.isArray(value)
            ? value
            : [];

    } catch (error) {

        console.error(
            "Unable to load map positions:",
            error
        );

        return [];

    }

}


function saveAllMapData() {

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
    startBedEditing
);


document.getElementById(
    "addBedBtn"
).addEventListener(
    "click",
    addBed
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
   EXISTING MAP MARKERS
========================================================= */

document.querySelectorAll(
    ".main-marker"
).forEach(
    marker => {

        marker.addEventListener(
            "click",
            function(event) {

                event.stopPropagation();

                handleMapMarkerClick(
                    marker
                );

            }
        );

    }
);


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


</body>

</html>