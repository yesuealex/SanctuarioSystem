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
        href="https://fonts.googleapis.com"
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


    <!-- =====================================================
         TOP BAR
    ====================================================== -->

    <?php include '../topRightBar.php'; ?>


    <!-- =====================================================
         PAGE TITLE
    ====================================================== -->

   


    <!-- =====================================================
         SUMMARY CARDS
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

    </div>
    
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
         MAIN MAP AREA
    ====================================================== -->

    <section class="farm-map-layout">


        <!-- =================================================
             MAIN MAP CARD
        ================================================== -->

        <div class="map-card">

            <div
                class="map-container"
                id="mapContainer"
            >


                <!-- =================================================
                     DRAGGABLE MAIN MAP
                ================================================== -->

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
                        class="main-marker plot-marker"
                        data-marker="plot"
                        onclick="openPlot('A')"
                        style="left:81.3%; top:54%;"
                    >

                        <img
                            src="<?= $basePath ?>farmMap/plot_a.jpg"
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
                        class="main-marker plot-marker"
                        data-marker="plot"
                        onclick="openPlot('B')"
                        style="left:71.4%; top:53%;"
                    >

                        <img
                            src="<?= $basePath ?>farmMap/plot_b.jpg"
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
                        class="main-marker plot-marker"
                        data-marker="plot"
                        onclick="openPlot('C')"
                        style="left:62.3%; top:53%;"
                    >

                        <img
                            src="<?= $basePath ?>farmMap/plot_c.jpg"
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
                        class="main-marker nursery-marker"
                        data-marker="nursery"
                        onclick="openNursery()"
                        style="left:84.5%; top:20%;"
                    >

                        <img
                            src="<?= $basePath ?>farmMap/nursery.jpg"
                            alt="Nursery"
                        >

                        <span>
                            Nursery
                        </span>

                    </button>


                    <!-- =================================================
                         LIVESTOCK MARKERS
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker livestock-marker"
                        onclick="openLivestock('Rabbit House')"
                        style="left:22.7%; top:17%;"
                    >

                        <i class="fa-solid fa-rabbit"></i>

                    </button>


                    <button
                        type="button"
                        class="main-marker livestock-marker"
                        onclick="openLivestock('Goat House')"
                        style="left:14%; top:25%;"
                    >

                        <i class="fa-solid fa-cow"></i>

                    </button>


                    <button
                        type="button"
                        class="main-marker livestock-marker"
                        onclick="openLivestock('Goat House 2')"
                        style="left:8%; top:15%;"
                    >

                        <i class="fa-solid fa-cow"></i>

                    </button>


                    <button
                        type="button"
                        class="main-marker livestock-marker"
                        onclick="openLivestock('Poultry House')"
                        style="left:5.5%; top:29%;"
                    >

                        <i class="fa-solid fa-dove"></i>

                    </button>


                    <button
                        type="button"
                        class="main-marker livestock-marker"
                        onclick="openLivestock('Swine House')"
                        style="left:11%; top:72%;"
                    >

                        <i class="fa-solid fa-pig"></i>

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
                            onclick="closePlot()"
                        >

                            <i class="fa-solid fa-xmark"></i>

                        </button>


                        <!-- POPUP MAP -->

                        <div class="popup-map">

                            <img
                                id="plotImage"
                                src=""
                                draggable="false"
                                alt="Plot"
                            >


                            <!-- PLANT MARKERS -->

                            <button
                                type="button"
                                class="plant-marker"
                                data-number="Plant #1"
                                style="left:25%; top:35%;"
                                onclick="openPlant(1)"
                            >
                                1
                            </button>


                            <button
                                type="button"
                                class="plant-marker"
                                data-number="Plant #2"
                                style="left:42%; top:50%;"
                                onclick="openPlant(2)"
                            >
                                2
                            </button>


                            <button
                                type="button"
                                class="plant-marker"
                                data-number="Plant #3"
                                style="left:58%; top:32%;"
                                onclick="openPlant(3)"
                            >
                                3
                            </button>


                            <button
                                type="button"
                                class="plant-marker"
                                data-number="Plant #4"
                                style="left:68%; top:58%;"
                                onclick="openPlant(4)"
                            >
                                4
                            </button>


                            <button
                                type="button"
                                class="plant-marker"
                                data-number="Plant #5"
                                style="left:80%; top:38%;"
                                onclick="openPlant(5)"
                            >
                                5
                            </button>

                        </div>


                        <!-- POPUP INFORMATION -->

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
                                        Plants
                                    </span>

                                    <strong id="plotPlants">
                                        5
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
                            onclick="closePlant()"
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

                                <span>
                                    Staff
                                </span>

                                <strong id="cropStaff">
                                    Mr. Villa
                                </strong>

                            </div>


                            <div>

                                <span>
                                    PCS
                                </span>

                                <strong id="cropPCS">
                                    5 Trays
                                </strong>

                            </div>


                            <div>

                                <span>
                                    Germination Rate
                                </span>

                                <strong id="cropGermination">
                                    79%
                                </strong>

                            </div>


                            <div>

                                <span>
                                    Targeted Area
                                </span>

                                <strong id="cropArea">
                                    A1
                                </strong>

                            </div>


                            <div>

                                <span>
                                    Date Sown
                                </span>

                                <strong id="cropSowing">
                                    01/01/2026
                                </strong>

                            </div>


                            <div>

                                <span>
                                    Date Transplanted
                                </span>

                                <strong id="cropTransplant">
                                    01/20/2026
                                </strong>

                            </div>


                            <div>

                                <span>
                                    Expected Harvest
                                </span>

                                <strong id="cropHarvest">
                                    05/01/2026
                                </strong>

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
                            onclick="closeNursery()"
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


            <div class="farm-panel">

                <div class="panel-header">

                    <span class="panel-title">
                        UPCOMING HARVEST
                    </span>

                    <select class="plot-select">

                        <option>
                            Plot A
                        </option>

                        <option>
                            Plot B
                        </option>

                        <option>
                            Plot C
                        </option>

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
                                Plants
                            </small>

                            <strong>
                                5
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


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

document.addEventListener("DOMContentLoaded", function () {

    /* =====================================================
       MAIN MAP
    ====================================================== */

    const mapContainer =
        document.getElementById("mapContainer");

    const mainMapLayer =
        document.getElementById("mainMapLayer");

    const farmMap =
        document.getElementById("farmMap");


    if (!mapContainer || !mainMapLayer || !farmMap) {
        return;
    }


    /* =====================================================
       MAP SETTINGS

       KEEPING YOUR CURRENT MAP SIZE

       1 = current normal size

       If you later want it slightly larger:
       1.1
       1.2

       DO NOT change this unless you want
       the map itself to become larger.
    ====================================================== */

    const mapScale = 1;


    let translateX = 0;

    let dragging = false;

    let startX = 0;


    /* =====================================================
       GET ACTUAL MAP WIDTH
    ====================================================== */

    function getMapWidth() {

        /*
         * Because the layer itself is transformed,
         * divide the transformed width by the scale.
         */

        const renderedWidth =
            mainMapLayer.getBoundingClientRect().width;


        if (!renderedWidth) {
            return farmMap.offsetWidth;
        }


        return renderedWidth / mapScale;

    }


    /* =====================================================
       CLAMP MAP
       
       THIS IS THE IMPORTANT FIX.

       The map can move from the left edge of
       the farm image all the way to the right edge.

       There is no vertical movement.
    ====================================================== */

    function clampMap() {

        const containerWidth =
            mapContainer.clientWidth;


        const mapWidth =
            getMapWidth() * mapScale;


        /*
         * Extra room.

         * 0 = exact edges
         * 50 = slight extra dragging
         * 100 = more extra dragging
         */

        const extraDrag = 0;


        /*
         * If the map is smaller than the
         * container, don't allow pointless
         * horizontal movement.
         */

        if (mapWidth <= containerWidth) {

            translateX =
                (containerWidth - mapWidth) / 2;

            return;

        }


        /*
         * Furthest position to the LEFT.
         *
         * Example:
         *
         * container = 1000
         * map       = 1700
         *
         * minX = 1000 - 1700 - 100
         *      = -800
         */

        const minX =
            containerWidth -
            mapWidth -
            extraDrag;


        /*
         * Furthest position to the RIGHT.
         */

        const maxX =
            extraDrag;


        /*
         * Keep the map inside those limits.
         */

        translateX =
            Math.max(
                minX,
                Math.min(
                    maxX,
                    translateX
                )
            );

    }


    /* =====================================================
       UPDATE MAP
    ====================================================== */

    function updateMainMap() {

        mainMapLayer.style.transformOrigin =
            "top left";


        mainMapLayer.style.transform =
            `translate3d(${translateX}px, 0, 0) scale(${mapScale})`;

    }


    /* =====================================================
       INITIAL MAP POSITION
    ====================================================== */

    requestAnimationFrame(function () {

        /*
         * Start at the current position.
         */

        translateX = 0;


        clampMap();

        updateMainMap();

    });


    /* =====================================================
       MOUSE DOWN
    ====================================================== */

    mapContainer.addEventListener(
        "mousedown",
        function (event) {

            /*
             * Don't drag when clicking
             * any main map marker.
             */

            if (
                event.target.closest(".main-marker")
            ) {
                return;
            }


            /*
             * Don't drag when clicking
             * popup content.
             */

            if (
                event.target.closest(".plot-overlay") ||
                event.target.closest(".plant-overlay") ||
                event.target.closest(".nursery-overlay")
            ) {
                return;
            }


            dragging = true;

            startX =
                event.clientX;


            mapContainer.classList.add(
                "dragging"
            );


            event.preventDefault();

        }
    );


    /* =====================================================
       MOUSE MOVE
    ====================================================== */

    document.addEventListener(
        "mousemove",
        function (event) {

            if (!dragging) {
                return;
            }


            const dx =
                event.clientX - startX;


            startX =
                event.clientX;


            translateX += dx;


            clampMap();

            updateMainMap();

        }
    );


    /* =====================================================
       MOUSE UP
    ====================================================== */

    document.addEventListener(
        "mouseup",
        function () {

            if (!dragging) {
                return;
            }


            dragging = false;


            mapContainer.classList.remove(
                "dragging"
            );

        }
    );


    /* =====================================================
       PREVENT IMAGE DRAGGING
    ====================================================== */

    farmMap.addEventListener(
        "dragstart",
        function (event) {

            event.preventDefault();

        }
    );


    /* =====================================================
       TOUCH DRAGGING
    ====================================================== */

    let touchStartX = 0;


    mapContainer.addEventListener(
        "touchstart",
        function (event) {

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


            touchStartX =
                event.touches[0].clientX;

        },
        {
            passive: true
        }
    );


    mapContainer.addEventListener(
        "touchmove",
        function (event) {

            if (
                event.target.closest(".plot-overlay") ||
                event.target.closest(".plant-overlay") ||
                event.target.closest(".nursery-overlay")
            ) {
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


    /* =====================================================
       RESIZE
    ====================================================== */

    window.addEventListener(
        "resize",
        function () {

            clampMap();

            updateMainMap();

        }
    );

});


/* =========================================================
   PLOT DATA
========================================================= */

const plotData = {

    A: {

        image:
            "<?= $basePath ?>farmMap/plot_a.jpg",

        title:
            "Plot A",

        description:
            "Main Vegetable Production Area",

        plants:
            5,

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

        plants:
            4,

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

        plants:
            3,

        area:
            "300m²"

    }

};


/* =========================================================
   OPEN PLOT
========================================================= */

function openPlot(plot) {

    const data =
        plotData[plot];


    if (!data) {
        return;
    }


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
        "plotPlants"
    ).textContent =
        data.plants;


    document.getElementById(
        "plotArea"
    ).textContent =
        data.area;


    document.getElementById(
        "plotOverlay"
    ).classList.add(
        "show"
    );

}


/* =========================================================
   CLOSE PLOT
========================================================= */

function closePlot() {

    document.getElementById(
        "plotOverlay"
    ).classList.remove(
        "show"
    );

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
   OPEN CROP
========================================================= */

function openPlant(id) {

    const crop =
        cropData[id];


    if (!crop) {
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


/* =========================================================
   CLOSE CROP
========================================================= */

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
        name + " selected."
    );

}


/* =========================================================
   CLOSE OVERLAYS WHEN CLICKING BACKGROUND
========================================================= */

document.addEventListener(
    "click",
    function (event) {

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
            event.target === plotOverlay
        ) {

            closePlot();

        }


        if (
            event.target === plantOverlay
        ) {

            closePlant();

        }


        if (
            event.target === nurseryOverlay
        ) {

            closeNursery();

        }

    }
);

</script>


</body>
</html>