<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title> Farm Gantt</title>

<link rel="stylesheet" href="style.css">

<style>

/* ================= LAYOUT ================= */
.gantt-wrapper {
    margin-left: 250px;
    padding: 80px 20px;
}

/* ================= TOP BAR ================= */
.top-bar {
    position: fixed;
    top: 0;
    left: 250px;
    right: 0;
    background: #fff;
    padding: 10px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    z-index: 999;
}

.top-bar button {
    padding: 8px 12px;
    border: none;
    background: #2f6b2f;
    color: white;
    border-radius: 6px;
    cursor: pointer;
}

/* VIEW SWITCH */
.view-switch button {
    background: #e5e7eb;
    color: #111;
}

.view-switch button.active {
    background: #2f6b2f;
    color: white;
}

/* ================= MAIN LAYOUT ================= */
.container {
    display: flex;
    gap: 15px;
}

/* LEFT PANEL */
.left-panel {
    width: 260px;
    background: #fff;
    padding: 10px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    height: 80vh;
    overflow-y: auto;
}

.crop-item {
    padding: 10px;
    background: #f4f4f4;
    margin-bottom: 8px;
    border-radius: 6px;
    cursor: pointer;
}

/* MAIN PANEL */
.main-panel {
    flex: 1;
    background: #fff;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    overflow-x: auto;
}

/* ROW */
.gantt-row {
    display: grid;
    grid-template-columns: 180px 1fr 120px;
    gap: 10px;
    margin-bottom: 18px;
}

.label {
    font-weight: bold;
}

/* ================= TIMELINE ================= */
.timeline {
    position: relative;
    height: 42px;
    background: repeating-linear-gradient(
        to right,
        #eee,
        #eee 1px,
        transparent 1px,
        transparent 40px
    );
    border-radius: 8px;
    overflow: hidden;
}

/* BAR */
.bar {
    position: absolute;
    height: 100%;
    border-radius: 6px;
    color: white;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.dop { background: #2ecc71; }
.dot {
    background: linear-gradient(to right, #2ecc71, #f39c12);
}
.doh { background: #f39c12; }

/* STATUS */
.status {
    font-size: 12px;
    padding: 5px 10px;
    border-radius: 20px;
    text-align: center;
}

.growing { background: #d1fae5; color: #065f46; }
.ready { background: #dbeafe; color: #1e40af; }

.month-header {
    display: flex;
    margin-bottom: 10px;
    font-size: 11px;
    color: #555;
}

.month-header div {
    flex: 0 0 auto;
    min-width: 40px;
    text-align: center;
    border-right: 1px solid #eee;
}

</style>
</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="gantt-wrapper">

<!-- TOP BAR -->
<div class="top-bar">

    <div>
        <button onclick="loadPlot('plot_a')">Plot A</button>
        <button onclick="loadPlot('plot_b')">Plot B</button>
        <button onclick="loadPlot('plot_c')">Plot C</button>
    </div>

    <div class="view-switch">
        <button onclick="setView('month')" id="monthBtn" class="active">Month</button>
        <button onclick="setView('week')" id="weekBtn">Week</button>
        <button onclick="setView('day')" id="dayBtn">Day</button>
    </div>

</div>

<div class="container">

    <div class="left-panel" id="leftPanel">
        <p>Select a plot</p>
    </div>

    <div class="main-panel" id="mainPanel">
        <h2>Farm Gantt Timeline</h2>
    </div>

</div>

</div>

<script>

let viewMode = "month";
let currentCrops = [];
let minDate, maxDate;

/* ================= VIEW SWITCH ================= */
function setView(mode) {
    viewMode = mode;

    document.querySelectorAll(".view-switch button")
        .forEach(b => b.classList.remove("active"));

    document.getElementById(mode + "Btn").classList.add("active");

    if (currentCrops.length > 0) {
        renderGantt(currentCrops);
    }
}

/* ================= LOAD DATA ================= */
async function loadPlot(plot) {

    try {
        const res = await fetch("api/get_crops.php?plot=" + plot);
        const data = await res.json();

        currentCrops = data;

        let left = `<h3>${plot.toUpperCase()}</h3>`;
        data.forEach(c => {
            left += `<div class="crop-item">${c.crop_name}</div>`;
        });

        document.getElementById("leftPanel").innerHTML = left;

        renderGantt(currentCrops);

    } catch (err) {
        console.error(err);
        alert("Failed to load data");
    }
}

/* ================= RENDER ================= */
function renderGantt(crops) {

    if (crops.length === 0) {
        document.getElementById("mainPanel").innerHTML = "<h3>No crops found</h3>";
        return;
    }

    // 🔥 GLOBAL RANGE
    let allDates = crops.flatMap(c => [
        c.date_transplanted,
        c.expected_harvest
    ]).map(d => new Date(d)).filter(d => !isNaN(d));

    minDate = new Date(Math.min(...allDates));
    maxDate = new Date(Math.max(...allDates));

    let html = `<h2>Crop Lifecycle Timeline</h2>`;

    html += generateHeader(); // ✅ NEW HEADER

    crops.forEach(c => {

        html += `
        <div class="gantt-row">

            <div class="label">${c.crop_name}</div>

            <div class="timeline">
                ${rangeBar(c.date_transplanted, c.expected_harvest)}
            </div>

            <div class="status ${getStatus(c)}">${getStatus(c)}</div>

        </div>
        `;
    });

    document.getElementById("mainPanel").innerHTML = html;
}

/* ================= HEADER ENGINE ================= */
function generateHeader() {

    let html = `<div class="month-header">`;

    let current = new Date(minDate);

    while (current <= maxDate) {

        let label = "";

        if (viewMode === "month") {
            label = current.toLocaleString('default', { month: 'short' });
            current.setMonth(current.getMonth() + 1);
        }

        if (viewMode === "week") {
            const week = getWeekNumber(current);
            label = "W" + week;
            current.setDate(current.getDate() + 7);
        }

        if (viewMode === "day") {
            label = current.getDate();
            current.setDate(current.getDate() + 1);
        }

        html += `<div>${label}</div>`;
    }

    html += `</div>`;
    return html;
}

/* ================= WEEK NUMBER ================= */
function getWeekNumber(d) {
    d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    const yearStart = new Date(Date.UTC(d.getFullYear(),0,1));
    const weekNo = Math.ceil((((d - yearStart) / 86400000) + 1)/7);
    return weekNo;
}

/* ================= STATUS ================= */
function getStatus(c) {
    const now = new Date();
    const start = new Date(c.date_transplanted);
    const end = new Date(c.expected_harvest);

    if (now < start) return "growing";
    if (now <= end) return "growing";
    return "ready";
}

/* ================= BAR ENGINE ================= */
function rangeBar(startDate, endDate) {

    if (!startDate || !endDate) return "";

    const start = new Date(startDate);
    const end = new Date(endDate);

    let rangeStart = new Date(minDate);
    let rangeEnd = new Date(maxDate);

    // 🔥 CHANGE RANGE BASED ON VIEW
    if (viewMode === "month") {
        rangeStart.setDate(1);
        rangeEnd.setMonth(rangeEnd.getMonth() + 1);
        rangeEnd.setDate(0);
    }

    if (viewMode === "week") {
        const day = rangeStart.getDay();
        rangeStart.setDate(rangeStart.getDate() - day);

        const endDay = rangeEnd.getDay();
        rangeEnd.setDate(rangeEnd.getDate() + (6 - endDay));
    }

    if (viewMode === "day") {
        // exact range (no adjustment needed)
    }

    const total = rangeEnd - rangeStart;

    if (total <= 0) return "";

    const left = ((start - rangeStart) / total) * 100;
    const width = ((end - start) / total) * 100;

    return `
        <div class="bar dot" style="left:${left}%; width:${width}%">
            🌱 → 🌾
        </div>
    `;
}

</script>

</body>
</html>