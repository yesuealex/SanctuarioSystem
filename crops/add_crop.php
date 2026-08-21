<?php
session_start();
include '../db.php';
include '../logfunc.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $crop_name = trim($_POST['crop_name'] ?? '');
    $staff_name = trim($_POST['staff_name'] ?? '');
    $variety = trim($_POST['variety'] ?? '');
    $pcs = intval($_POST['pcs'] ?? 0);
    $germination_rate = floatval($_POST['germination_rate'] ?? 0);
    $transplanted_area = trim($_POST['transplanted_area'] ?? '');

    $date_transplanted = !empty($_POST['date_transplanted'])
        ? $_POST['date_transplanted']
        : null;

    $expected_harvest = !empty($_POST['expected_harvest'])
        ? $_POST['expected_harvest']
        : null;

    $plot_code = $_POST['plot_code'] ?? '';

    $stmt = $conn->prepare("
        INSERT INTO crops
        (crop_name, staff_name, variety, pcs, germination_rate,
         transplanted_area, date_transplanted, expected_harvest, plot_code)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "sssidssss",
        $crop_name,
        $staff_name,
        $variety,
        $pcs,
        $germination_rate,
        $transplanted_area,
        $date_transplanted,
        $expected_harvest,
        $plot_code
    );

    if (!$stmt->execute()) {
        die("Insert failed: " . $stmt->error);
    }

    $stmt->close();

    logActivity(
        $conn,
        $_SESSION['user_id'],
        "Added Crop",
        "Crop: $crop_name in $plot_code"
    );

    // When opened from records.php, close the modal and refresh the dashboard.
    echo '<script>
        if (window.parent && window.parent !== window) {
            window.parent.closeEditModal();
            window.parent.location.reload();
        } else {
            window.location.href = "../records.php";
        }
    </script>';
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Crop</title>
<link rel="stylesheet" href="../style.css">

<style>
* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    background: #ffffff;
    font-family: 'Poppins', Arial, sans-serif;
}

body {
    min-height: 100vh;
}

.form-wrapper {
    width: 100%;
    padding: 25px;
}

.form-card {
    width: 100%;
    max-width: 560px;
    margin: 0 auto;
    background: #ffffff;
    border-radius: 14px;
    padding: 25px;
    border-top: 5px solid #2f6b2f;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
}

.form-card h2 {
    margin: 0 0 20px;
    color: #2f6b2f;
    text-align: center;
    font-size: 1.35rem;
}

.form-group {
    margin-bottom: 12px;
}

.form-card label {
    display: block;
    margin-bottom: 5px;
    font-size: 0.85rem;
    color: #555;
}

.form-card input,
.form-card select {
    width: 100%;
    padding: 11px 12px;
    border-radius: 8px;
    border: 1px solid #ddd;
    outline: none;
    font-family: inherit;
    font-size: 0.9rem;
    background: #fff;
}

.form-card input:focus,
.form-card select:focus {
    border-color: #2f6b2f;
    box-shadow: 0 0 0 2px rgba(47,107,47,0.15);
}

.form-card button {
    width: 100%;
    padding: 12px;
    margin-top: 8px;
    background: #2f6b2f;
    color: white;
    border: none;
    border-radius: 8px;
    font-family: inherit;
    font-weight: 600;
    cursor: pointer;
}

.form-card button:hover {
    background: #245224;
}

@media (max-width: 600px) {
    .form-wrapper {
        padding: 15px;
    }

    .form-card {
        padding: 20px;
    }
}
</style>
</head>

<body>

<div class="form-wrapper">
    <div class="form-card">

        <h2>Add Crop</h2>

        <form method="POST">

            <div class="form-group">
                <label>Crop Name</label>
                <input name="crop_name" placeholder="Crop Name" required>
            </div>

            <div class="form-group">
                <label>Staff Name</label>
                <input name="staff_name" placeholder="Staff Name" required>
            </div>

            <div class="form-group">
                <label>Variety</label>
                <input name="variety" placeholder="Variety">
            </div>

            <div class="form-group">
                <label>Pieces</label>
                <input name="pcs" type="number" min="0" placeholder="Pieces">
            </div>

            <div class="form-group">
                <label>Germination Rate (%)</label>
                <input name="germination_rate" type="number" min="0" max="100" step="0.01" placeholder="Germination Rate">
            </div>

            <div class="form-group">
                <label>Area</label>
                <input name="transplanted_area" placeholder="Area">
            </div>

            <div class="form-group">
                <label>Date Transplanted</label>
                <input type="date" name="date_transplanted">
            </div>

            <div class="form-group">
                <label>Expected Harvest</label>
                <input type="date" name="expected_harvest">
            </div>

            <div class="form-group">
                <label>Plot Location</label>
                <select name="plot_code" required>
                    <option value="">Select Plot</option>
                    <option value="plot_a">Plot A</option>
                    <option value="plot_b">Plot B</option>
                    <option value="plot_c">Plot C</option>
                </select>
            </div>

            <button type="submit">Save Crop</button>

        </form>

    </div>
</div>

</body>
</html>