<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'admin';
$adminName = $_SESSION['name'] ?? 'Admin';

$basePath = '/SanctuarioSystem/';

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

        /* =====================================================
           MAP EDIT TOOLBAR
        ====================================================== */

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
            color: rgba(255, 255, 255, .9);
        }


        /* =====================================================
           EDIT MODE
        ====================================================== */

        .map-container.edit-mode {
            cursor: crosshair;
        }

        .map-container.edit-mode .editable-marker {
            cursor: grab;
        }

        .map-container.edit-mode .editable-marker:hover {
            filter:
                drop-shadow(
                    0 0 6px rgba(95, 143, 69, .8)
                );
        }

        .map-container.edit-mode .editable-marker:active {
            cursor: grabbing;
        }


        /* =====================================================
           NEW MAP MARKER
        ====================================================== */

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
                0 3px 10px rgba(0, 0, 0, .2);
        }

        .new-map-icon i {
            font-size: 17px;
        }


        /* =====================================================
           EDITABLE FARM MARKER INDICATOR
        ====================================================== */

        .map-container.edit-mode
        .editable-marker::after {

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


        /* =====================================================
           EDITABLE BED MARKERS
        ====================================================== */

        .popup-map {
            position: relative;
        }

        .bed-marker {
            position: absolute;

            transform: translate(
                -50%,
                -50%
            );

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


        /* =====================================================
           BED EDIT TOOLBAR
        ====================================================== */

        .bed-edit-toolbar {
            display: none;

            align-items: center;
            justify-content: center;

            flex-wrap: wrap;

            gap: 8px;

            margin-top: 12px;
        }

        .bed-edit-toolbar.show {
            display: flex;
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

            transition:
                .2s ease;
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


        /* =====================================================
           BED EDIT MODE
        ====================================================== */

        .plot-popup.bed-editing .bed-marker {
            cursor: grab;
        }

        .plot-popup.bed-editing .bed-marker:active {
            cursor: grabbing;
        }

        .plot-popup.bed-editing
        .popup-map {
            outline: 2px dashed rgba(95,143,69,.45);
        }


        /* =====================================================
           BED COUNT
        ====================================================== */

        .bed-count-display {
            margin-top: 10px;

            font-size: 12px;

            color: #777;

            text-align: center;
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


        <!-- SUMMARY CARDS -->

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


            <div class="summary-card">

                <div class="summary-icon summary-olive">

                    <i class="fa-solid fa-user-tie"></i>

                </div>

                <div>

                    <span class="summary-label">
                        Active Staff
                    </span>

                    <strong class="summary-number">
                        15
                    </strong>

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         MAIN MAP
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


                    <!-- PLOT B -->

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


                    <!-- PLOT C -->

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


                    <!-- NURSERY -->

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


                    <!-- RABBIT HOUSE -->

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


                    <!-- GOAT HOUSE -->

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


                    <!-- GOAT HOUSE 2 -->

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


                    <!-- POULTRY HOUSE -->

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


                    <!-- SWINE HOUSE -->

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


                <!-- MAP HINT -->

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

                            <!--
                                BED MARKERS ARE NOW GENERATED
                                AUTOMATICALLY WITH JAVASCRIPT.
                            -->

                            <div
                                id="bedMarkersContainer"
                            ></div>

                        </div>


                        <div class="plot-info">

                            <h2 id="plotTitle">
                                Plot A
                            </h2>

                            <p id="plotDescription">
                                Main Vegetable Production Area
                            </p>


                            <div class="plot-stat-grid">

                                <div class="plot-stat">

                                    <span>
                                        Beds
                                    </span>

                                    <strong id="plotPlants">
                                        18
                                    </strong>

                                </div>


                                <div class="plot-stat">

                                    <span>
                                        Area
                                    </span>

                                    <strong id="plotArea">
                                        500m²
                                    </strong>

                                </div>

                            </div>


                            <!-- BED COUNT -->

                            <div
                                class="bed-count-display"
                                id="bedCountDisplay"
                            >
                                18 bed markers
                            </div>


                            <!-- BED EDIT CONTROLS -->

                            <div
                                class="bed-edit-toolbar"
                                id="bedEditToolbar"
                            >

                                <button
                                    type="button"
                                    id="editBedsBtn"
                                >

                                    <i class="fa-solid fa-pen"></i>

                                    Edit Beds

                                </button>


                                <button
                                    type="button"
                                    id="addBedBtn"
                                >

                                    <i class="fa-solid fa-plus"></i>

                                    Add Bed

                                </button>


                                <button
                                    type="button"
                                    id="deleteBedBtn"
                                    class="delete-bed"
                                >

                                    <i class="fa-solid fa-trash"></i>

                                    Delete Selected

                                </button>


                                <button
                                    type="button"
                                    id="saveBedsBtn"
                                >

                                    <i class="fa-solid fa-floppy-disk"></i>

                                    Save Beds

                                </button>


                                <button
                                    type="button"
                                    id="cancelBedsBtn"
                                    class="cancel-bed-edit"
                                >

                                    <i class="fa-solid fa-xmark"></i>

                                    Cancel

                                </button>


                                <div
                                    class="bed-edit-status"
                                    id="bedEditStatus"
                                >
                                    Click Edit Beds to move bed markers.
                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     PLANT DETAILS POPUP
                ================================================== -->

                <div
                    class="plant-overlay"
                    id="plantOverlay"
                >

                    <div class="plant-details">

                        <button
                            type="button"
                            class="popup-close"
                            id="closePlantBtn"
                        >

                            <i class="fa-solid fa-xmark"></i>

                        </button>


                        <div class="plant-details-icon">

                            <i class="fa-solid fa-seedling"></i>

                        </div>


                        <h2 id="cropName">
                            LETTUCE
                        </h2>

                        <p
                            class="crop-variety"
                            id="cropVariety"
                        >
                            Majesta Variety
                        </p>


                        <div class="crop-details-grid">

                            <div>
                                <span>Staff</span>
                                <strong id="cropStaff">Mr. Villa</strong>
                            </div>

                            <div>
                                <span>PCS</span>
                                <strong id="cropPCS">5 Trays</strong>
                            </div>

                            <div>
                                <span>Germination Rate</span>
                                <strong id="cropGermination">79%</strong>
                            </div>

                            <div>
                                <span>Targeted Area</span>
                                <strong id="cropArea">A1</strong>
                            </div>

                            <div>
                                <span>Date Sown</span>
                                <strong id="cropSowing">01/01/2026</strong>
                            </div>

                            <div>
                                <span>Date Transplanted</span>
                                <strong id="cropTransplant">01/20/2026</strong>
                            </div>

                            <div>
                                <span>Expected Harvest</span>
                                <strong id="cropHarvest">05/01/2026</strong>
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


                        <h2>

                            <i class="fa-solid fa-seedling"></i>

                            Nursery

                        </h2>

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

            </div>

        </div>


        <!-- =====================================================
             RIGHT SIDE
        ====================================================== -->

        <aside class="farm-side-panel">


            <!-- UPCOMING HARVEST -->

            <div class="farm-panel">

                <div class="panel-header">

                    <span class="panel-title">
                        UPCOMING HARVEST
                    </span>

                    <select class="plot-select">

                        <option>Plot A</option>
                        <option>Plot B</option>
                        <option>Plot C</option>

                    </select>

                </div>


                <div class="harvest-item">

                    <strong>
                        Lettuce
                    </strong>

                    <span>
                        June 10, 2026 - Plot A1
                    </span>

                </div>


                <div class="harvest-item">

                    <strong>
                        Corn
                    </strong>

                    <span>
                        June 10, 2026 - Plot A2
                    </span>

                </div>


                <div class="harvest-item">

                    <strong>
                        Pechay
                    </strong>

                    <span>
                        June 12, 2026 - Plot A3
                    </span>

                </div>


                <div class="harvest-item">

                    <strong>
                        Eggplant
                    </strong>

                    <span>
                        June 14, 2026 - Plot A4
                    </span>

                </div>

            </div>


            <!-- PLOT INFORMATION -->

            <div class="farm-panel">

                <div class="panel-header">

                    <span class="panel-title">
                        PLOT INFORMATION
                    </span>

                </div>


                <div class="plot-panel-content">

                    <h2>
                        Plot A
                    </h2>

                    <p>
                        Main Vegetable Production Area
                    </p>


                    <div class="plot-panel-grid">

                        <div>

                            <small>
                                Beds
                            </small>

                            <strong id="sidePlotBeds">
                                18
                            </strong>

                        </div>


                        <div>

                            <small>
                                Area
                            </small>

                            <strong>
                                500m²
                            </strong>

                        </div>

                    </div>

                </div>

            </div>

        </aside>

    </section>

</main>


<script>

/* =========================================================
   MAP ELEMENTS
========================================================= */

const mapContainer =
    document.getElementById("mapContainer");

const mainMapLayer =
    document.getElementById("mainMapLayer");

const farmMap =
    document.getElementById("farmMap");


/* =========================================================
   MAP DRAG
========================================================= */

let translateX = 0;

let draggingMap = false;

let mapStartX = 0;

const mapScale = 1;


function getMapWidth() {

    const imageWidth =
        farmMap.getBoundingClientRect().width;

    if (imageWidth > 0) {
        return imageWidth;
    }

    return mainMapLayer.getBoundingClientRect().width;

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

    const maxX = 0;


    translateX =
        Math.max(
            minX,
            Math.min(
                maxX,
                translateX
            )
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

}

else {

    farmMap.addEventListener(
        "load",
        function() {

            requestAnimationFrame(
                initializeMap
            );

        }
    );

}


/* =========================================================
   MAP MOUSE DRAG
========================================================= */

mapContainer.addEventListener(
    "mousedown",
    function(event) {

        if (
            event.target.closest(".main-marker")
        ) {
            return;
        }


        if (
            event.target.closest(".plot-overlay") ||
            event.target.closest(".plant-overlay") ||
            event.target.closest(".nursery-overlay")
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

        mapStartX =
            event.clientX;

        mapContainer.classList.add(
            "dragging"
        );

        event.preventDefault();

    }
);


document.addEventListener(
    "mousemove",
    function(event) {

        if (!draggingMap) {
            return;
        }


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

        if (!draggingMap) {
            return;
        }


        draggingMap = false;

        mapContainer.classList.remove(
            "dragging"
        );

    }
);


farmMap.addEventListener(
    "dragstart",
    function(event) {

        event.preventDefault();

    }
);


/* =========================================================
   TOUCH DRAG
========================================================= */

let touchStartX = 0;

let touchingMap = false;


mapContainer.addEventListener(
    "touchstart",
    function(event) {

        if (
            event.target.closest(".main-marker")
        ) {
            return;
        }


        if (
            event.target.closest(".plot-overlay") ||
            event.target.closest(".plant-overlay") ||
            event.target.closest(".nursery-overlay")
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

        if (!touchingMap) {
            return;
        }


        const currentX =
            event.touches[0].clientX;


        const dx =
            currentX - touchStartX;


        touchStartX =
            currentX;


        translateX += dx;


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
   =========================================================

   IMPORTANT:

   Change the "beds" value here according to your
   actual plot records.

   Example:
   Plot A = 18 beds
   Plot B = 12 beds
   Plot C = 15 beds
========================================================= */

const plotData = {

    A: {

        image:
            "<?= $basePath ?>farmMap/plot_a.jpg",

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
            "<?= $basePath ?>farmMap/plot_b.jpg",

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
            "<?= $basePath ?>farmMap/plot_c.jpg",

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
   BED MARKER STORAGE
========================================================= */

const BED_STORAGE_KEY =
    "sanctuarioPlotBeds";


let currentPlot =
    null;

let bedEditMode =
    false;

let selectedBed =
    null;

let draggingBed =
    null;

let bedSnapshot =
    null;


/* =========================================================
   GET SAVED BED DATA
========================================================= */

function getStoredBeds() {

    try {

        return JSON.parse(
            localStorage.getItem(
                BED_STORAGE_KEY
            )
        ) || {};

    }

    catch (error) {

        console.error(
            "Unable to load bed positions:",
            error
        );

        return {};

    }

}


/* =========================================================
   SAVE ALL BED DATA
========================================================= */

function saveStoredBeds(data) {

    localStorage.setItem(
        BED_STORAGE_KEY,
        JSON.stringify(data)
    );

}


/* =========================================================
   CREATE DEFAULT BED POSITIONS
========================================================= */

function createDefaultBedPositions(count) {

    const positions = [];

    /*
     * These positions are generated automatically.
     *
     * You can later manually move them using
     * Edit Beds.
     */

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
            15 +
            (
                column *
                (
                    70 /
                    Math.max(
                        columns - 1,
                        1
                    )
                )
            );


        const y =
            15 +
            (
                row *
                (
                    70 /
                    Math.max(
                        rows - 1,
                        1
                    )
                )
            );


        positions.push({

            number:
                i + 1,

            x:
                Math.min(
                    85,
                    x
                ),

            y:
                Math.min(
                    85,
                    y
                )

        });

    }


    return positions;

}


/* =========================================================
   GET BEDS FOR PLOT
========================================================= */

function getBedsForPlot(plot) {

    const stored =
        getStoredBeds();


    if (
        stored[plot] &&
        Array.isArray(
            stored[plot]
        )
    ) {

        return stored[plot];

    }


    const data =
        plotData[plot];


    if (!data) {
        return [];
    }


    return createDefaultBedPositions(
        data.beds
    );

}


/* =========================================================
   RENDER BED MARKERS
========================================================= */

function renderBedMarkers(plot) {

    const container =
        document.getElementById(
            "bedMarkersContainer"
        );


    container.innerHTML = "";


    const beds =
        getBedsForPlot(plot);


    beds.forEach(
        function(bed) {

            const marker =
                document.createElement(
                    "button"
                );


            marker.type =
                "button";


            marker.className =
                "bed-marker";


            marker.dataset.bed =
                bed.number;


            marker.textContent =
                bed.number;


            marker.style.left =
                bed.x + "%";


            marker.style.top =
                bed.y + "%";


            marker.title =
                "Bed " +
                bed.number;


            marker.addEventListener(
                "click",
                function(event) {

                    event.stopPropagation();

                    if (bedEditMode) {

                        selectBed(
                            marker
                        );

                        return;

                    }


                    openPlant(
                        Number(
                            bed.number
                        )
                    );

                }
            );


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


            container.appendChild(
                marker
            );

        }
    );


    updateBedCount();

}


/* =========================================================
   BED COUNT
========================================================= */

function updateBedCount() {

    const container =
        document.getElementById(
            "bedMarkersContainer"
        );


    const count =
        container.querySelectorAll(
            ".bed-marker"
        ).length;


    document.getElementById(
        "plotPlants"
    ).textContent =
        count;


    document.getElementById(
        "bedCountDisplay"
    ).textContent =
        count +
        (
            count === 1
                ? " bed marker"
                : " bed markers"
        );


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
   SAVE CURRENT BED POSITIONS
========================================================= */

function collectCurrentBedPositions() {

    const markers =
        document.querySelectorAll(
            "#bedMarkersContainer .bed-marker"
        );


    const beds = [];


    markers.forEach(
        function(marker) {

            beds.push({

                number:
                    Number(
                        marker.dataset.bed
                    ),

                x:
                    parseFloat(
                        marker.style.left
                    ),

                y:
                    parseFloat(
                        marker.style.top
                    )

            });

        }
    );


    return beds;

}


/* =========================================================
   OPEN PLOT
========================================================= */

function openPlot(plot) {

    const data =
        plotData[plot];


    if (!data) {

        alert(
            "This is a newly added plot."
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


    document.getElementById(
        "plotArea"
    ).textContent =
        data.area;


    renderBedMarkers(
        plot
    );


    document.getElementById(
        "plotOverlay"
    ).classList.add(
        "show"
    );


    updateBedEditUI();

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


        if (!confirmed) {
            return;
        }

    }


    bedEditMode =
        false;

    selectedBed =
        null;

    draggingBed =
        null;

    currentPlot =
        null;


    document.getElementById(
        "plotOverlay"
    ).classList.remove(
        "show"
    );

}


/* =========================================================
   BED EDIT SNAPSHOT
========================================================= */

function createBedSnapshot() {

    if (!currentPlot) {
        return;
    }


    bedSnapshot =
        JSON.parse(
            JSON.stringify(
                collectCurrentBedPositions()
            )
        );

}


/* =========================================================
   START BED EDITING
========================================================= */

function startBedEditing() {

    if (!currentPlot) {
        return;
    }


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


    document
        .querySelectorAll(
            "#bedMarkersContainer .bed-marker"
        )
        .forEach(
            function(marker) {

                marker.classList.add(
                    "editing"
                );

            }
        );


    updateBedEditUI();

}


/* =========================================================
   UPDATE BED EDIT UI
========================================================= */

function updateBedEditUI() {

    const toolbar =
        document.getElementById(
            "bedEditToolbar"
        );


    const editButton =
        document.getElementById(
            "editBedsBtn"
        );


    const status =
        document.getElementById(
            "bedEditStatus"
        );


    if (!bedEditMode) {

        toolbar.classList.remove(
            "show"
        );


        /*
         * We still show the Edit Beds
         * button normally.
         */

        toolbar.classList.add(
            "show"
        );


        editButton.style.display =
            "inline-flex";


        document.getElementById(
            "addBedBtn"
        ).style.display =
            "none";


        document.getElementById(
            "deleteBedBtn"
        ).style.display =
            "none";


        document.getElementById(
            "saveBedsBtn"
        ).style.display =
            "none";


        document.getElementById(
            "cancelBedsBtn"
        ).style.display =
            "none";


        status.textContent =
            "Click Edit Beds to move bed markers.";

        return;

    }


    toolbar.classList.add(
        "show"
    );


    editButton.style.display =
        "none";


    document.getElementById(
        "addBedBtn"
    ).style.display =
        "inline-flex";


    document.getElementById(
        "deleteBedBtn"
    ).style.display =
        "inline-flex";


    document.getElementById(
        "saveBedsBtn"
    ).style.display =
        "inline-flex";


    document.getElementById(
        "cancelBedsBtn"
    ).style.display =
        "inline-flex";


    if (selectedBed) {

        status.textContent =
            "Bed " +
            selectedBed.dataset.bed +
            " selected — drag it to move.";

    }

    else {

        status.textContent =
            "Drag a bed marker or select one.";

    }

}


/* =========================================================
   SELECT BED
========================================================= */

function selectBed(marker) {

    document
        .querySelectorAll(
            "#bedMarkersContainer .bed-marker"
        )
        .forEach(
            function(item) {

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
   MOVE BED
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


        if (!popupMap) {
            return;
        }


        const rect =
            popupMap.getBoundingClientRect();


        let x =
            (
                (
                    event.clientX -
                    rect.left
                ) /
                rect.width
            ) * 100;


        let y =
            (
                (
                    event.clientY -
                    rect.top
                ) /
                rect.height
            ) * 100;


        x =
            Math.max(
                2,
                Math.min(
                    98,
                    x
                )
            );


        y =
            Math.max(
                2,
                Math.min(
                    98,
                    y
                )
            );


        draggingBed.style.left =
            x + "%";


        draggingBed.style.top =
            y + "%";

    }
);


document.addEventListener(
    "mouseup",
    function() {

        if (!draggingBed) {
            return;
        }


        draggingBed =
            null;

    }
);


/* =========================================================
   ADD BED
========================================================= */

function addBed() {

    if (!currentPlot || !bedEditMode) {
        return;
    }


    const container =
        document.getElementById(
            "bedMarkersContainer"
        );


    const markers =
        container.querySelectorAll(
            ".bed-marker"
        );


    let highestNumber = 0;


    markers.forEach(
        function(marker) {

            highestNumber =
                Math.max(
                    highestNumber,
                    Number(
                        marker.dataset.bed
                    )
                );

        }
    );


    const newNumber =
        highestNumber + 1;


    const marker =
        document.createElement(
            "button"
        );


    marker.type =
        "button";


    marker.className =
        "bed-marker editing";


    marker.dataset.bed =
        newNumber;


    marker.textContent =
        newNumber;


    /*
     * New beds start in the center.
     * You can immediately drag them.
     */

    marker.style.left =
        "50%";


    marker.style.top =
        "50%";


    marker.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();

            if (bedEditMode) {

                selectBed(
                    marker
                );

            }

        }
    );


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


    container.appendChild(
        marker
    );


    selectBed(
        marker
    );


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


    const confirmed =
        confirm(
            "Delete Bed " +
            number +
            "?"
        );


    if (!confirmed) {
        return;
    }


    selectedBed.remove();

    selectedBed =
        null;


    /*
     * Renumber all remaining beds.
     */

    const markers =
        document.querySelectorAll(
            "#bedMarkersContainer .bed-marker"
        );


    markers.forEach(
        function(marker, index) {

            const newNumber =
                index + 1;


            marker.dataset.bed =
                newNumber;


            marker.textContent =
                newNumber;

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


    const beds =
        collectCurrentBedPositions();


    const stored =
        getStoredBeds();


    stored[currentPlot] =
        beds;


    saveStoredBeds(
        stored
    );


    bedSnapshot =
        null;


    bedEditMode =
        false;


    selectedBed =
        null;


    document
        .getElementById(
            "plotPopup"
        )
        .classList.remove(
            "bed-editing"
        );


    document
        .querySelectorAll(
            "#bedMarkersContainer .bed-marker"
        )
        .forEach(
            function(marker) {

                marker.classList.remove(
                    "editing"
                );

                marker.classList.remove(
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
   CANCEL BED EDIT
========================================================= */

function cancelBedEditing() {

    if (!bedEditMode) {
        return;
    }


    const confirmed =
        confirm(
            "Discard the current bed marker changes?"
        );


    if (!confirmed) {
        return;
    }


    const container =
        document.getElementById(
            "bedMarkersContainer"
        );


    container.innerHTML = "";


    bedSnapshot.forEach(
        function(bed) {

            const marker =
                document.createElement(
                    "button"
                );


            marker.type =
                "button";


            marker.className =
                "bed-marker";


            marker.dataset.bed =
                bed.number;


            marker.textContent =
                bed.number;


            marker.style.left =
                bed.x + "%";


            marker.style.top =
                bed.y + "%";


            marker.addEventListener(
                "click",
                function(event) {

                    event.stopPropagation();


                    if (bedEditMode) {

                        selectBed(
                            marker
                        );

                    }
                    else {

                        openPlant(
                            Number(
                                marker.dataset.bed
                            )
                        );

                    }

                }
            );


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


            container.appendChild(
                marker
            );

        }
    );


    bedEditMode =
        false;

    selectedBed =
        null;

    draggingBed =
        null;

    bedSnapshot =
        null;


    document
        .getElementById(
            "plotPopup"
        )
        .classList.remove(
            "bed-editing"
        );


    updateBedCount();

    updateBedEditUI();

}


/* =========================================================
   CROP DATA
========================================================= */

const cropData = {

    1: {

        name:
            "LETTUCE",

        variety:
            "Majesta Variety",

        staff:
            "Mr. Villa",

        pcs:
            "5 Trays",

        germination:
            "79%",

        area:
            "A1",

        sowing:
            "01/01/2026",

        transplant:
            "01/20/2026",

        harvest:
            "05/01/2026"

    },


    2: {

        name:
            "OKRA",

        variety:
            "Clemson Spineless",

        staff:
            "Ms. Liaga",

        pcs:
            "4 Trays",

        germination:
            "84%",

        area:
            "A2",

        sowing:
            "01/05/2026",

        transplant:
            "01/24/2026",

        harvest:
            "05/08/2026"

    },


    3: {

        name:
            "PECHAY",

        variety:
            "Black Behi",

        staff:
            "Mr. Manalo",

        pcs:
            "6 Trays",

        germination:
            "91%",

        area:
            "A3",

        sowing:
            "01/03/2026",

        transplant:
            "01/21/2026",

        harvest:
            "04/20/2026"

    },


    4: {

        name:
            "CORN",

        variety:
            "Purple Gem F1",

        staff:
            "Mr. Villa",

        pcs:
            "10 Trays",

        germination:
            "87%",

        area:
            "A4",

        sowing:
            "01/01/2026",

        transplant:
            "01/19/2026",

        harvest:
            "05/15/2026"

    },


    5: {

        name:
            "SPINACH",

        variety:
            "Bloomsdale",

        staff:
            "Ms. Liaga",

        pcs:
            "5 Trays",

        germination:
            "82%",

        area:
            "A5",

        sowing:
            "01/06/2026",

        transplant:
            "01/25/2026",

        harvest:
            "05/10/2026"

    }

};


/* =========================================================
   OPEN PLANT / BED
========================================================= */

function openPlant(id) {

    const crop =
        cropData[id];


    if (!crop) {

        /*
         * This happens when a bed exists
         * but there is no crop record yet.
         */

        alert(
            "Bed " +
            id +
            " does not have a crop record yet."
        );

        return;

    }


    document.getElementById(
        "cropName"
    ).textContent =
        crop.name;


    document.getElementById(
        "cropVariety"
    ).textContent =
        crop.variety;


    document.getElementById(
        "cropStaff"
    ).textContent =
        crop.staff;


    document.getElementById(
        "cropPCS"
    ).textContent =
        crop.pcs;


    document.getElementById(
        "cropGermination"
    ).textContent =
        crop.germination;


    document.getElementById(
        "cropArea"
    ).textContent =
        crop.area;


    document.getElementById(
        "cropSowing"
    ).textContent =
        crop.sowing;


    document.getElementById(
        "cropTransplant"
    ).textContent =
        crop.transplant;


    document.getElementById(
        "cropHarvest"
    ).textContent =
        crop.harvest;


    document.getElementById(
        "plantOverlay"
    ).classList.add(
        "show"
    );

}


function closePlant() {

    document.getElementById(
        "plantOverlay"
    ).classList.remove(
        "show"
    );

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

function openLivestock(name) {

    alert(
        name +
        " selected."
    );

}


/* =========================================================
   NORMAL MAP MARKER CLICK
========================================================= */

function handleMapMarkerClick(marker) {

    if (mapEditMode) {

        if (
            mapEditTool === "move"
        ) {

            return;

        }

        return;

    }


    const type =
        marker.dataset.marker;


    const id =
        marker.dataset.id;


    if (type === "plot") {

        if (
            id === "A" ||
            id === "B" ||
            id === "C"
        ) {

            openPlot(id);

        }

        else {

            alert(
                "This is a newly added plot."
            );

        }

        return;

    }


    if (type === "nursery") {

        openNursery();

        return;

    }


    openLivestock(
        marker.dataset.name ||
        "Livestock"
    );

}


/* =========================================================
   MAP EDIT VARIABLES
========================================================= */

let mapEditMode = false;

let mapEditTool = "move";

let draggingMarker = null;

let editSnapshot = null;


/* =========================================================
   LOCAL STORAGE
========================================================= */

const MAP_STORAGE_KEY =
    "sanctuarioFarmMap";


/* =========================================================
   CREATE MAP SNAPSHOT
========================================================= */

function createEditSnapshot() {

    const markers =
        document.querySelectorAll(
            ".editable-marker"
        );


    const snapshot = [];


    markers.forEach(
        function(marker) {

            snapshot.push({

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

                description:
                    marker.dataset.description || "",

                area:
                    marker.dataset.area || "",

                isNew:
                    marker.classList.contains(
                        "new-marker"
                    )

            });

        }
    );


    editSnapshot =
        snapshot;

}


/* =========================================================
   ENTER / LEAVE MAP EDIT
========================================================= */

function toggleMapEditMode() {

    if (!mapEditMode) {

        enterMapEditMode();

    }

    else {

        leaveMapEditMode();

    }

}


function enterMapEditMode() {

    mapEditMode = true;

    mapEditTool = "move";


    createEditSnapshot();


    const button =
        document.getElementById(
            "editMapBtn"
        );


    const controls =
        document.getElementById(
            "mapEditControls"
        );


    const status =
        document.getElementById(
            "mapEditStatus"
        );


    controls.classList.add(
        "show"
    );


    button.classList.add(
        "editing"
    );


    button.innerHTML =
        '<i class="fa-solid fa-eye"></i> View Map';


    status.textContent =
        "Move — Drag a marker";


    mapContainer.classList.add(
        "edit-mode"
    );


    enableEditableMarkers();

}


function leaveMapEditMode() {

    mapEditMode = false;

    draggingMarker = null;


    document
        .getElementById(
            "mapEditControls"
        )
        .classList.remove(
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

}


/* =========================================================
   SELECT MAP EDIT TOOL
========================================================= */

function setMapEditTool(tool) {

    if (!mapEditMode) {
        return;
    }


    mapEditTool =
        tool;


    const status =
        document.getElementById(
            "mapEditStatus"
        );


    if (tool === "plot") {

        status.textContent =
            "Add Plot — Click the map";

    }

    else if (tool === "marker") {

        status.textContent =
            "Add Marker — Click the map";

    }

    else {

        status.textContent =
            "Move — Drag a marker";

    }

}


/* =========================================================
   GET MAP POSITION
========================================================= */

function getMapPosition(event) {

    const rect =
        mainMapLayer.getBoundingClientRect();


    let x =
        (
            (
                event.clientX -
                rect.left
            ) /
            rect.width
        ) * 100;


    let y =
        (
            (
                event.clientY -
                rect.top
            ) /
            rect.height
        ) * 100;


    x =
        Math.max(
            0,
            Math.min(
                100,
                x
            )
        );


    y =
        Math.max(
            0,
            Math.min(
                100,
                y
            )
        );


    return {
        x,
        y
    };

}


/* =========================================================
   ADD NEW PLOT
========================================================= */

function addNewPlot(event) {

    const position =
        getMapPosition(event);


    const name =
        prompt(
            "Enter plot name:",
            "Plot D"
        );


    if (!name || !name.trim()) {
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


    const beds =
        Number(
            prompt(
                "How many beds does this plot have?",
                "10"
            )
        ) || 0;


    const id =
        "plot-" +
        Date.now();


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
        position.x + "%";


    marker.style.top =
        position.y + "%";


    marker.innerHTML = `

        <img
            src="<?= $basePath ?>markers/plot_a.png"
            alt="${escapeHTML(name)}"
        >

        <span>
            ${escapeHTML(name)}
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


    /*
     * Save newly created plot data
     * temporarily in plotData.
     */

    plotData[id] = {

        image:
            "<?= $basePath ?>farmMap/plot_a.jpg",

        title:
            name.trim(),

        description:
            description,

        beds:
            beds,

        area:
            area

    };

}


/* =========================================================
   ADD NEW MARKER
========================================================= */

function addNewMarker(event) {

    const position =
        getMapPosition(event);


    const name =
        prompt(
            "Enter marker name:",
            "New Marker"
        );


    if (!name || !name.trim()) {
        return;
    }


    const type =
        (
            prompt(
                "Enter marker type:\n\nlivestock\nnursery\nother",
                "other"
            ) || "other"
        ).toLowerCase().trim();


    const id =
        "marker-" +
        Date.now();


    const marker =
        document.createElement(
            "button"
        );


    marker.type =
        "button";


    marker.className =
        "main-marker livestock-marker editable-marker new-marker";


    marker.id =
        id;


    marker.dataset.marker =
        type;


    marker.dataset.id =
        id;


    marker.dataset.name =
        name.trim();


    marker.style.left =
        position.x + "%";


    marker.style.top =
        position.y + "%";


    let icon =
        "fa-location-dot";


    if (
        type === "livestock"
    ) {

        icon =
            "fa-cow";

    }

    else if (
        type === "nursery"
    ) {

        icon =
            "fa-seedling";

    }


    marker.innerHTML = `

        <div class="new-map-icon">

            <i class="fa-solid ${icon}"></i>

        </div>

        <span>
            ${escapeHTML(name)}
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
        value;


    return div.innerHTML;

}


/* =========================================================
   ENABLE EDITABLE MARKERS
========================================================= */

function enableEditableMarkers() {

    const markers =
        document.querySelectorAll(
            ".editable-marker"
        );


    markers.forEach(
        function(marker) {

            enableMarkerDragging(
                marker
            );

        }
    );

}


/* =========================================================
   ENABLE FARM MARKER DRAGGING
========================================================= */

function enableMarkerDragging(marker) {

    if (
        marker.dataset.dragReady ===
        "true"
    ) {

        return;

    }


    marker.dataset.dragReady =
        "true";


    marker.addEventListener(
        "mousedown",
        function(event) {

            if (!mapEditMode) {
                return;
            }


            if (
                mapEditTool !==
                "move"
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
   MOVE FARM MARKER
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


        let x =
            (
                (
                    event.clientX -
                    rect.left
                ) /
                rect.width
            ) * 100;


        let y =
            (
                (
                    event.clientY -
                    rect.top
                ) /
                rect.height
            ) * 100;


        x =
            Math.max(
                0,
                Math.min(
                    100,
                    x
                )
            );


        y =
            Math.max(
                0,
                Math.min(
                    100,
                    y
                )
            );


        draggingMarker.style.left =
            x + "%";


        draggingMarker.style.top =
            y + "%";

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
   SAVE MAP DATA
========================================================= */

function getStoredMapData() {

    try {

        return JSON.parse(
            localStorage.getItem(
                MAP_STORAGE_KEY
            )
        ) || [];

    }

    catch (error) {

        return [];

    }

}


function saveMarkerToMemory(marker) {

    let mapData =
        getStoredMapData();


    const markerData = {

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
            ""

    };


    const existingIndex =
        mapData.findIndex(
            item =>
                item.id ===
                marker.id
        );


    if (
        existingIndex >= 0
    ) {

        mapData[
            existingIndex
        ] =
            markerData;

    }

    else {

        mapData.push(
            markerData
        );

    }


    localStorage.setItem(
        MAP_STORAGE_KEY,
        JSON.stringify(
            mapData
        )
    );

}


function saveAllMapData() {

    const markers =
        document.querySelectorAll(
            ".editable-marker"
        );


    localStorage.removeItem(
        MAP_STORAGE_KEY
    );


    markers.forEach(
        function(marker) {

            saveMarkerToMemory(
                marker
            );

        }
    );

}


/* =========================================================
   RESTORE MAP SNAPSHOT
========================================================= */

function restoreEditSnapshot() {

    if (!editSnapshot) {
        return;
    }


    document
        .querySelectorAll(
            ".new-marker"
        )
        .forEach(
            function(marker) {

                marker.remove();

            }
        );


    editSnapshot.forEach(
        function(data) {

            const marker =
                document.getElementById(
                    data.id
                );


            if (!marker) {
                return;
            }


            marker.style.left =
                data.left;


            marker.style.top =
                data.top;


            marker.dataset.name =
                data.name;


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

    const confirmed =
        confirm(
            "Discard your current changes?"
        );


    if (!confirmed) {
        return;
    }


    restoreEditSnapshot();

    leaveMapEditMode();

}


/* =========================================================
   LOAD SAVED MAP MARKERS
========================================================= */

function loadEditableMarkers() {

    const mapData =
        getStoredMapData();


    if (!mapData.length) {
        return;
    }


    mapData.forEach(
        function(data) {

            const existing =
                document.getElementById(
                    data.id
                );


            if (existing) {

                existing.style.left =
                    data.left;


                existing.style.top =
                    data.top;


                if (data.name) {

                    existing.dataset.name =
                        data.name;


                    const label =
                        existing.querySelector(
                            "span"
                        );


                    if (label) {

                        label.textContent =
                            data.name;

                    }

                }


                return;

            }


            const marker =
                document.createElement(
                    "button"
                );


            marker.type =
                "button";


            marker.className =
                "main-marker livestock-marker editable-marker new-marker";


            marker.id =
                data.id;


            marker.dataset.marker =
                data.type;


            marker.dataset.id =
                data.id;


            marker.dataset.name =
                data.name;


            marker.style.left =
                data.left;


            marker.style.top =
                data.top;


            let icon =
                "fa-location-dot";


            if (
                data.type ===
                "livestock"
            ) {

                icon =
                    "fa-cow";

            }

            else if (
                data.type ===
                "nursery"
            ) {

                icon =
                    "fa-seedling";

            }


            marker.innerHTML = `

                <div class="new-map-icon">

                    <i class="fa-solid ${icon}"></i>

                </div>

                <span>
                    ${escapeHTML(data.name)}
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
    );

}


/* =========================================================
   MAP CLICK FOR ADDING
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
            )
        ) {

            return;

        }


        if (
            event.target.closest(
                ".plot-overlay"
            ) ||
            event.target.closest(
                ".plant-overlay"
            ) ||
            event.target.closest(
                ".nursery-overlay"
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

            addNewPlot(event);

        }

        else if (
            mapEditTool === "marker"
        ) {

            addNewMarker(event);

        }

    }
);


/* =========================================================
   BUTTON EVENTS
========================================================= */

document
    .getElementById(
        "editMapBtn"
    )
    .addEventListener(
        "click",
        toggleMapEditMode
    );


document
    .querySelectorAll(
        "#mapEditControls button[data-tool]"
    )
    .forEach(
        function(button) {

            button.addEventListener(
                "click",
                function() {

                    setMapEditTool(
                        button.dataset.tool
                    );

                }
            );

        }
    );


document
    .getElementById(
        "saveMapBtn"
    )
    .addEventListener(
        "click",
        saveEditableMap
    );


document
    .getElementById(
        "cancelMapBtn"
    )
    .addEventListener(
        "click",
        cancelMapEdit
    );


/* =========================================================
   BED BUTTON EVENTS
========================================================= */

document
    .getElementById(
        "editBedsBtn"
    )
    .addEventListener(
        "click",
        startBedEditing
    );


document
    .getElementById(
        "addBedBtn"
    )
    .addEventListener(
        "click",
        addBed
    );


document
    .getElementById(
        "deleteBedBtn"
    )
    .addEventListener(
        "click",
        deleteSelectedBed
    );


document
    .getElementById(
        "saveBedsBtn"
    )
    .addEventListener(
        "click",
        saveBeds
    );


document
    .getElementById(
        "cancelBedsBtn"
    )
    .addEventListener(
        "click",
        cancelBedEditing
    );


/* =========================================================
   EXISTING FARM MARKERS
========================================================= */

document
    .querySelectorAll(
        ".main-marker"
    )
    .forEach(
        function(marker) {

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
   CLOSE BUTTONS
========================================================= */

document
    .getElementById(
        "closePlotBtn"
    )
    .addEventListener(
        "click",
        closePlot
    );


document
    .getElementById(
        "closePlantBtn"
    )
    .addEventListener(
        "click",
        closePlant
    );


document
    .getElementById(
        "closeNurseryBtn"
    )
    .addEventListener(
        "click",
        closeNursery
    );


/* =========================================================
   CLOSE OVERLAY
========================================================= */

document.addEventListener(
    "click",
    function(event) {

        const plotOverlay =
            document.getElementById(
                "plotOverlay"
            );


        const plantOverlay =
            document.getElementById(
                "plantOverlay"
            );


        const nurseryOverlay =
            document.getElementById(
                "nurseryOverlay"
            );


        if (
            event.target ===
            plotOverlay
        ) {

            closePlot();

        }


        if (
            event.target ===
            plantOverlay
        ) {

            closePlant();

        }


        if (
            event.target ===
            nurseryOverlay
        ) {

            closeNursery();

        }

    }
);


/* =========================================================
   ESC
========================================================= */

document.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key !== "Escape"
        ) {

            return;

        }


        closePlot();

        closePlant();

        closeNursery();

    }
);


/* =========================================================
   LOAD SAVED DATA
========================================================= */

document.addEventListener(
    "DOMContentLoaded",
    function() {

        loadEditableMarkers();


        requestAnimationFrame(
            function() {

                clampMap();

                updateMainMap();

            }
        );

    }
);

</script>


</body>

</html>