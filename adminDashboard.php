<?php 
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

/* TOTAL CROPS */
$totalCrops = $conn->query("SELECT COUNT(*) AS total FROM crops WHERE COALESCE(archived,0) = 0")->fetch_assoc()['total'];

/* TOTAL LIVESTOCKS */
$totalLivestock = $conn->query("SELECT COALESCE(SUM(number),0) AS total FROM livestocks WHERE COALESCE(archived,0) = 0")->fetch_assoc()['total'];

/* ACTIVITY LOGS (FIXED) */
$logs = $conn->query("
    SELECT activity_logs.*, users.name
    FROM activity_logs
    JOIN users ON users.user_id = activity_logs.user_id
    ORDER BY activity_logs.created_at DESC
    LIMIT 10
");

/* SEEDS INVENTORY BY MONTH (ACTIVE RECORDS) */
$seedInventoryByMonth = array_fill(1, 12, 0);
$seedRes = $conn->query("
    SELECT MONTH(date_received) AS month_no,
           COALESCE(SUM(CAST(NULLIF(pieces, '') AS DECIMAL(18,2))), 0) AS total
    FROM inventory_items
    WHERE category = 'Seeds'
      AND COALESCE(is_archived,0) = 0
    GROUP BY MONTH(date_received)
");
if ($seedRes) {
    while ($seedRow = $seedRes->fetch_assoc()) {
        $monthNo = (int)$seedRow['month_no'];
        if ($monthNo >= 1 && $monthNo <= 12) {
            $seedInventoryByMonth[$monthNo] = (float)$seedRow['total'];
        }
    }
}
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
    position: fixed;
    z-index: 99999;
    width: min(360px, calc(100vw - 24px));
    max-width: 360px;
    max-height: min(520px, calc(100vh - 24px));
    overflow-y: auto;
    padding: 0;
    border: 1px solid rgba(82, 123, 66, .16);
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 18px 50px rgba(32, 58, 42, .28);
    color: #334337;
    font-family: 'Poppins', sans-serif;
    box-sizing: border-box;
}

.map-popup::-webkit-scrollbar { width: 6px; }
.map-popup::-webkit-scrollbar-thumb {
    background: #b8c9b0;
    border-radius: 10px;
}
.map-popup::-webkit-scrollbar-track { background: transparent; }
.map-popup.hidden { display: none; }
.map-popup-head {
    padding: 16px 18px 15px;
    background: linear-gradient(135deg, #527b42, #6b8e5a);
    color: #fff;
}
.map-popup-head .popup-kicker {
    display: block;
    margin-bottom: 3px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    opacity: .78;
}
.map-popup-head h4 {
    margin: 0;
    font-size: 18px;
    line-height: 1.3;
}
.map-popup-body { padding: 14px; }
.map-popup-section-title {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 0 2px 9px;
    color: #365a3c;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .7px;
    text-transform: uppercase;
}
.map-popup-section-title i { font-size: 12px; }
.map-popup-card {
    padding: 12px 13px;
    margin-bottom: 9px;
    border: 1px solid #e1ebdd;
    border-radius: 11px;
    background: #f9fbf8;
}
.map-popup-card:last-child { margin-bottom: 0; }
.map-popup-card-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 7px;
    color: #2f4f35;
    font-size: 13px;
    font-weight: 700;
}
.map-popup-card-title i {
    color: #527b42;
    font-size: 12px;
}
.map-popup-detail {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 7px 0;
    border-bottom: 1px solid #edf2ea;
    font-size: 11px;
    line-height: 1.35;
}
.map-popup-detail:last-child { border-bottom: 0; padding-bottom: 0; }
.map-popup-detail span:first-child { color: #718075; }
.map-popup-detail strong { color: #3f4d42; text-align: right; }
.map-popup-empty {
    padding: 14px;
    border: 1px dashed #cbd9c5;
    border-radius: 10px;
    color: #7a867b;
    background: #fbfcfa;
    text-align: center;
    font-size: 11px;
}
.map-popup-error {
    padding: 14px;
    color: #8a4d4d;
    background: #fff8f8;
    font-size: 11px;
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
                <option value="plot_a">Plot A</option>
                <option value="plot_b">Plot B</option>
                <option value="plot_c">Plot C</option>
            </select>

        </div>

        <div class="pie-content">

            <div class="pie-legend">

                <div>
                    <span class="legend growing"></span>
                    Growing
                    <b id="plotGrowingCount">0</b>
                </div>

                <div>
                    <span class="legend soon"></span>
                    Soon
                    <b id="plotSoonCount">0</b>
                </div>

                <div>
                    <span class="legend ready"></span>
                    Ready
                    <b id="plotReadyCount">0</b>
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

            popup.style.left = `${screen.x + 14}px`;
            popup.style.top = `${screen.y + 14}px`;

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


                const safe = (value) => String(value ?? "--")
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");

                const formatNumber = (value) =>
                    Number(value || 0).toLocaleString();

                let html = "";

                if (data.type === "livestock") {
                    html = `
                        <div class="map-popup-head">
                            <span class="popup-kicker">Farm map location</span>
                            <h4>${safe(data.plot_house_name || plot)}</h4>
                        </div>
                        <div class="map-popup-body">
                            <div class="map-popup-section-title">
                                <i class="fa-solid fa-house-chimney"></i> Livestock house records
                            </div>
                            <div class="map-popup-card">
                                <div class="map-popup-card-title">
                                    <span>Livestock house</span>
                                    <i class="fa-solid fa-paw"></i>
                                </div>
                                <div class="map-popup-detail">
                                    <span>PLOT HOUSE NAME:</span>
                                    <strong>${safe(data.plot_house_name)}</strong>
                                </div>
                                <div class="map-popup-detail">
                                    <span>NUMBER OF LIVESTOCKS:</span>
                                    <strong>${formatNumber(data.number_of_livestock)}</strong>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    html = `
                        <div class="map-popup-head">
                            <span class="popup-kicker">Farm map location</span>
                            <h4>${safe(data.plot_name || plot)}</h4>
                        </div>
                        <div class="map-popup-body">
                            <div class="map-popup-section-title">
                                <i class="fa-solid fa-seedling"></i> Crop records
                            </div>
                            <div class="map-popup-card">
                                <div class="map-popup-card-title">
                                    <span>Plot crop summary</span>
                                    <i class="fa-solid fa-leaf"></i>
                                </div>
                                <div class="map-popup-detail">
                                    <span>PLOT NAME:</span>
                                    <strong>${safe(data.plot_name)}</strong>
                                </div>
                                <div class="map-popup-detail">
                                    <span>PLANTS PLANTED:</span>
                                    <strong>${formatNumber(data.plants_planted)}</strong>
                                </div>
                                <div class="map-popup-detail">
                                    <span>BED AVAILABLE:</span>
                                    <strong>${formatNumber(data.bed_available)}</strong>
                                </div>
                                <div class="map-popup-detail">
                                    <span>SOON TO HARVEST:</span>
                                    <strong>${formatNumber(data.soon_to_harvest)}</strong>
                                </div>
                                <div class="map-popup-detail">
                                    <span>READY TO HARVEST:</span>
                                    <strong>${formatNumber(data.ready_to_harvest)}</strong>
                                </div>
                            </div>
                        </div>
                    `;
                }

                popup.innerHTML = html;

                /* The popup is viewport-level now, so it can never be clipped
                   by the dashboard map/card. Keep it beside the clicked marker
                   and automatically flip it above/left when near an edge. */
                requestAnimationFrame(() => {
                    const margin = 12;
                    const gap = 14;
                    const popupRect = popup.getBoundingClientRect();
                    let left = screen.x + gap;
                    let top = screen.y + gap;

                    if (left + popupRect.width > window.innerWidth - margin) {
                        left = screen.x - popupRect.width - gap;
                    }
                    if (top + popupRect.height > window.innerHeight - margin) {
                        top = screen.y - popupRect.height - gap;
                    }

                    left = Math.max(margin, Math.min(left, window.innerWidth - popupRect.width - margin));
                    top = Math.max(margin, Math.min(top, window.innerHeight - popupRect.height - margin));

                    popup.style.left = `${left}px`;
                    popup.style.top = `${top}px`;
                });

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

                data: <?= json_encode(array_values($seedInventoryByMonth), JSON_NUMERIC_CHECK) ?>,

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

const plotSelector = document.getElementById('plotSelector');
const growingCountEl = document.getElementById('plotGrowingCount');
const soonCountEl = document.getElementById('plotSoonCount');
const readyCountEl = document.getElementById('plotReadyCount');

const cropStatusChartInstance = new Chart(cropStatusChart, {
    type: 'pie',
    data: {
        labels: ['Growing', 'Soon', 'Ready'],
        datasets: [{
            data: [0, 0, 0],
            backgroundColor: [
                '#f1d778',
                '#ed9f69',
                '#95bd87'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        }
    }
});

async function loadPlotStatusChart(plotCode) {
    if (!plotCode) return;

    try {
        const response = await fetch(
            `get_plot_data.php?plot=${encodeURIComponent(plotCode)}`,
            { cache: 'no-store' }
        );

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        const growing = Number(data.growing || 0);
        const soon = Number(data.soon_to_harvest || 0);
        const ready = Number(data.ready_to_harvest || 0);

        cropStatusChartInstance.data.datasets[0].data = [growing, soon, ready];
        cropStatusChartInstance.update();

        if (growingCountEl) growingCountEl.textContent = growing.toLocaleString();
        if (soonCountEl) soonCountEl.textContent = soon.toLocaleString();
        if (readyCountEl) readyCountEl.textContent = ready.toLocaleString();
    } catch (error) {
        console.error('Unable to load crop status chart:', error);
        cropStatusChartInstance.data.datasets[0].data = [0, 0, 0];
        cropStatusChartInstance.update();
        if (growingCountEl) growingCountEl.textContent = '0';
        if (soonCountEl) soonCountEl.textContent = '0';
        if (readyCountEl) readyCountEl.textContent = '0';
    }
}

if (plotSelector) {
    plotSelector.addEventListener('change', () => {
        loadPlotStatusChart(plotSelector.value);
    });

    loadPlotStatusChart(plotSelector.value || 'plot_a');
}

</script>
</body>
</html>