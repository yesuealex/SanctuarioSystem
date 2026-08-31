<?php 
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

/* TOTAL CROPS */
$totalCrops = $conn->query("SELECT COUNT(*) AS total FROM crops")->fetch_assoc()['total'];

/* TOTAL PLOTS */
$totalPlots = $conn->query("SELECT COUNT(*) AS total FROM plots")->fetch_assoc()['total'];

/* HARVEST READY */
$harvestReady = $conn->query("SELECT COUNT(*) AS total FROM crops WHERE status = 'ready'")->fetch_assoc()['total'];

/* ACTIVITY LOGS (FIXED) */
$logs = $conn->query("
    SELECT activity_logs.*, users.name
    FROM activity_logs
    JOIN users ON users.user_id = activity_logs.user_id
    ORDER BY activity_logs.created_at DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="sidebar.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:wght@400" rel="stylesheet">

<style>
/* ===========================
   FARM MAP FIX
   =========================== */

.map-box {
    min-width: 0;
    overflow: hidden;
}

.svg-wrapper {
    position: relative;
    width: 100%;
    height: auto;
    min-height: 0;
    overflow: hidden;
    cursor: grab;
    user-select: none;
    -webkit-user-select: none;
    touch-action: pan-y;
}

.svg-wrapper:active {
    cursor: grabbing;
}

#farmMap {
    display: block;
    width: 100%;
    height: auto;
    max-width: none;
    margin: 0;
    padding: 0;
    transform-origin: top left;
    will-change: transform;
}

#farmMap > image:first-child {
    display: block;
}

#farmMap .marker {
    cursor: pointer;
    pointer-events: auto;
}

.map-popup {
    position: absolute;
    z-index: 1000;
    max-width: 260px;
    max-height: 280px;
    overflow-y: auto;
    padding: 12px 14px;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .18);
}

.map-popup.hidden {
    display: none;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>

<div class="layout">

<?php include 'sidebar.php'; ?>

<main class="dashboard">

<?php include 'topRightBar.php'; ?>

<!-- ================= HERO SECTION ================= -->

<div class="hero-container">

    <div class="hero-text">

        <div>
            <h2>
                <span class="material-symbols-rounded">wb_sunny</span>
                Welcome back, <?= ucfirst(htmlspecialchars($_SESSION['name'])); ?>!
            </h2>

            <p>Here's what's happening around Sanctuario Nature Farm today.</p>
        </div>

        <div class="hero-date">
            <span class="material-symbols-rounded">calendar_month</span>
            <?= date("F d, Y"); ?>
        </div>

    </div>



    <div class="hero-cards">

        <!-- Active Crops -->
        <div class="hero-card">

            <div class="hero-icon green">
                <span class="material-symbols-rounded">eco</span>
            </div>

            <div class="hero-info">
                <span>Active Crops</span>
                <h3><?= $totalCrops ?></h3>
            </div>

        </div>


        <!-- Livestock -->
        <div class="hero-card">

            <div class="hero-icon yellow">
                <span class="material-symbols-rounded">pets</span>
            </div>

            <div class="hero-info">
                <span>Total Livestock</span>
                <h3><?= $totalLivestock ?? 0 ?></h3>
            </div>

        </div>


        <!-- Inventory -->
        <div class="hero-card">

            <div class="hero-icon lime">
                <span class="material-symbols-rounded">inventory_2</span>
            </div>

            <div class="hero-info">
                <span>Low Stock Items</span>
                <h3><?= $Inventory ?? '--' ?></h3>
            </div>

        </div>



    </div>

</div>
<!-- MAP -->
<section class="top-grid">

<div class="map-box">
<h3>Farm Map</h3>

<div class="svg-wrapper" id="mapContainer">

<svg id="farmMap"
     viewBox="0 0 1706 600"
     preserveAspectRatio="xMidYMid meet">

    <image
        href="farm_map.jpg"
        x="0"
        y="0"
        width="1706"
        height="600" />

    <!-- Nursery -->
    <image class="marker"
        href="markers/nursery.png"
        x="1438"
        y="96"
        width="35"
        height="35"
        data-plot="nursery"/>

    <!-- Plot A -->
    <image class="marker"
        href="markers/plot_a.png"
        x="1385"
        y="301"
        width="35"
        height="36"
        data-plot="plot_a"/>

    <!-- Plot B -->
    <image class="marker"
        href="markers/plot_b.png"
        x="1218"
        y="294"
        width="35"
        height="35"
        data-plot="plot_b"/>

    <!-- Plot C -->
    <image class="marker"
        href="markers/plot_c.png"
        x="1061 "
        y="295"
        width="35"
        height="35"
        data-plot="plot_c"/>

    <!-- Rabbit -->
    <image class="marker"
        href="markers/rabbit_house.png"
        x="387"
        y="47"
        width="35"
        height="35"
        data-plot="rabbit_house"/>

    <!-- Goat -->
    <image class="marker"
        href="markers/goat_house.png"
        x="240"
        y="98"
        width="35"
        height="35"
        data-plot="goat_house"/>

    <!-- Goat 2 -->
    <image class="marker"
        href="markers/goat_house.png"
        x="139"
        y="55"
        width="35"
        height="35"
        data-plot="goat_house_2"/>

    <!-- Poultry -->
    <image class="marker"
        href="markers/poultry_house.png"
        x="91"
        y="116"
        width="35"
        height="35"
        data-plot="poultry_house"/>

    <!-- Swine -->
    <image class="marker"
        href="markers/swine_house.png"
        x="190"
        y="416"
        width="35"
        height="35"
        data-plot="swine_house"/>

</svg>

<div id="mapPopup" class="map-popup hidden"></div>

</div>

</div>

<div class="dashboard-charts">

    <!-- BAR GRAPH -->
    <div class="chart-box">

        <h3>SEEDS INVENTORY</h3>

        <div class="bar-chart">
            <canvas id="seedInventoryChart"></canvas>
        </div>

    </div>


    <!-- PIE CHART -->
    <div class="chart-box">

        <div class="pie-header">

            <select id="plotSelector">
                <option>Plot A</option>
                <option>Plot B</option>
                <option>Plot C</option>
            </select>

        </div>

        <div class="pie-content">

            <div class="pie-legend">

                <div>
                    <span class="legend growing"></span>
                    Growing
                    <b>18</b>
                </div>

                <div>
                    <span class="legend soon"></span>
                    Soon
                    <b>5</b>
                </div>

                <div>
                    <span class="legend ready"></span>
                    Ready
                    <b>2</b>
                </div>

            </div>

            <div class="pie-chart">
                <canvas id="cropStatusChart"></canvas>
            </div>

        </div>

    </div>

</div>

</section>

</main>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {

    const markers = document.querySelectorAll(".marker");
    const popup = document.getElementById("mapPopup");
    const svg = document.getElementById("farmMap");
    const container = document.getElementById("mapContainer");

    if (!svg || !container) return;

    /* ===========================
       MAP SETTINGS
       =========================== */

    let scale = 1.5;
    let translateX = 0;

    let dragging = false;
    let startX = 0;

    function getMapWidth() {
        /*
         * SVG viewBox is 1706 x 600.
         * getBoundingClientRect() gives the rendered width
         * before/after transform, so divide by scale.
         */
        const renderedWidth = svg.getBoundingClientRect().width;

        return renderedWidth / scale;
    }

    function clampMapPosition() {

        const containerWidth = container.clientWidth;

        /*
         * The SVG's unscaled width is based on its actual
         * rendered size. Multiply it by the current zoom.
         */
        const baseWidth = getMapWidth();
        const scaledWidth = baseWidth * scale;

        const minX = Math.min(0, containerWidth - scaledWidth);

        if (scaledWidth <= containerWidth) {
            translateX = (containerWidth - scaledWidth) / 2;
        } else {
            translateX = Math.max(minX, Math.min(0, translateX));
        }
    }

    function updateContainerHeight() {
        /*
         * CSS transforms do not increase the element's layout height.
         * Because the map is zoomed to 1.5x, the wrapper must be made
         * tall enough for the transformed 1706 x 600 map.
         */
        const baseHeight =
            (container.clientWidth / 1706) * 600;

        const requiredHeight = baseHeight * scale;

        container.style.height =
            `${Math.ceil(requiredHeight)}px`;
    }

    function updateTransform() {
        svg.style.transformOrigin = "top left";
        svg.style.transform =
            `translate3d(${translateX}px, 0, 0) scale(${scale})`;
    }

    function refreshMapLayout() {
        updateContainerHeight();
        clampMapPosition();
        updateTransform();
    }

    /*
     * Apply the initial zoom.
     * The map will NOT automatically zoom again on refresh.
     */
    requestAnimationFrame(() => {
        refreshMapLayout();
    });


    /* ===========================
       DRAG MAP LEFT / RIGHT ONLY
       =========================== */

    container.addEventListener("mousedown", (e) => {

        /*
         * Do not start dragging when clicking a marker.
         */
        if (e.target.closest(".marker")) return;

        dragging = true;
        startX = e.clientX;

        container.style.cursor = "grabbing";

        e.preventDefault();
    });


    document.addEventListener("mousemove", (e) => {

        if (!dragging) return;

        const dx = e.clientX - startX;

        startX = e.clientX;

        translateX += dx;

        clampMapPosition();
        updateTransform();
    });


    document.addEventListener("mouseup", () => {

        if (!dragging) return;

        dragging = false;
        container.style.cursor = "grab";
    });


    /*
     * Prevent the browser from dragging the image itself.
     */
    svg.addEventListener("dragstart", (e) => {
        e.preventDefault();
    });


    /* ===========================
       KEEP MAP CORRECT ON RESIZE
       =========================== */

    window.addEventListener("resize", () => {

        refreshMapLayout();
    });


    /* ===========================
       MARKER POPUPS
       =========================== */

    markers.forEach(marker => {

        marker.addEventListener("click", async (e) => {

            e.stopPropagation();

            const plot = marker.dataset.plot;

            const pt = svg.createSVGPoint();

            const x = parseFloat(marker.getAttribute("x")) || 0;
            const y = parseFloat(marker.getAttribute("y")) || 0;
            const width =
                parseFloat(marker.getAttribute("width")) || 0;
            const height =
                parseFloat(marker.getAttribute("height")) || 0;

            pt.x = x + (width / 2);
            pt.y = y + (height / 2);

            const screen = pt.matrixTransform(
                svg.getScreenCTM()
            );

            const rect = container.getBoundingClientRect();

            popup.style.left =
                `${screen.x - rect.left + 12}px`;

            popup.style.top =
                `${screen.y - rect.top + 12}px`;

            popup.classList.remove("hidden");
            popup.innerHTML = "Loading...";


            try {

                const res = await fetch(
                    `get_plot_data.php?plot=${encodeURIComponent(plot)}`
                );

                if (!res.ok) {
                    throw new Error(
                        `HTTP ${res.status}`
                    );
                }

                const data = await res.json();

                if (!data || data.error) {

                    popup.innerHTML =
                        `<b>Error:</b> ${
                            data?.error || "Invalid response"
                        }`;

                    return;
                }


                let html =
                    `<strong>${
                        data.plot || plot
                    }</strong><br><br>`;


                /* CROPS */

                if (
                    Array.isArray(data.crops) &&
                    data.crops.length
                ) {

                    data.crops.forEach(c => {

                        html += `
                            <div style="margin-bottom:8px;">
                                🌾 <b>${
                                    c.crop_name ?? "--"
                                }</b><br>
                                👤 ${
                                    c.staff_name ?? "--"
                                }<br>
                                🌱 ${
                                    c.pcs ?? "--"
                                } pcs<br>
                                📅 ${
                                    c.date_transplanted ?? "--"
                                }
                            </div>
                        `;
                    });
                }


                /* LIVESTOCK */

                if (
                    Array.isArray(data.livestock) &&
                    data.livestock.length
                ) {

                    data.livestock.forEach(l => {

                        html += `
                            <div style="margin-bottom:8px;">
                                🐄 <b>${
                                    l.type ?? "--"
                                }</b><br>
                                📦 ${
                                    l.number ?? "--"
                                }<br>
                                🍽 ${
                                    l.feeding_schedule ?? "--"
                                }
                            </div>
                        `;
                    });
                }


                /* NO DATA */

                if (
                    (!Array.isArray(data.crops) ||
                        !data.crops.length) &&
                    (!Array.isArray(data.livestock) ||
                        !data.livestock.length)
                ) {

                    html += `
                        <span style="color:#888;">
                            No data found
                        </span>
                    `;
                }

                popup.innerHTML = html;

            } catch (err) {

                popup.innerHTML =
                    "Failed to load data";

                console.error(
                    "Map popup error:",
                    err
                );
            }
        });
    });


    /* ===========================
       CLOSE POPUP
       =========================== */

    document.addEventListener("click", (e) => {

        if (
            !popup.contains(e.target) &&
            !e.target.closest(".marker")
        ) {
            popup.classList.add("hidden");
        }
    });

});

/* =========================================
   SEEDS INVENTORY BAR GRAPH
   ========================================= */

const seedInventoryChart =
    document.getElementById('seedInventoryChart');


new Chart(seedInventoryChart, {

    type: 'bar',

    data: {

        labels: [
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec'
        ],

        datasets: [

            {

                data: [
                    2650,
                    1650,
                    1950,
                    1000,
                    2300,
                    2650,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0
                ],

                backgroundColor: [
                    '#96c08b',
                    '#f36b68',
                    '#f1d778',
                    '#4d82df',
                    '#9077bf',
                    '#eda064',
                    '#96c08b',
                    '#f36b68',
                    '#f1d778',
                    '#4d82df',
                    '#9077bf',
                    '#eda064'
                ],

                borderRadius: 4,

                borderSkipped: false

            }

        ]

    },


    options: {

        responsive: true,

        maintainAspectRatio: false,

        plugins: {

            legend: {
                display: false
            }

        },


        scales: {

            y: {

                beginAtZero: true,

                suggestedMax: 3000,

                ticks: {

                    stepSize: 500

                },

                grid: {
                    display: false
                }

            },


            x: {

                grid: {
                    display: false
                }

            }

        }

    }

});


/* =========================================
   CROP STATUS PIE CHART
   ========================================= */

const cropStatusChart =
    document.getElementById('cropStatusChart');


new Chart(cropStatusChart, {

    type: 'pie',


    data: {

        labels: [
            'Growing',
            'Soon',
            'Ready'
        ],


        datasets: [

            {

                data: [
                    18,
                    5,
                    2
                ],


                backgroundColor: [
                    '#f1d778',
                    '#ed9f69',
                    '#95bd87'
                ],


                borderWidth: 0

            }

        ]

    },


    options: {

        responsive: true,

        maintainAspectRatio: false,


        plugins: {

            legend: {
                display: false
            }

        }

    }

});

</script>
</body>
</html>