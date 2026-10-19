<?php
require_once __DIR__ . '/../staff_access.php';
sanctuario_staff_guard('livestock');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include '../db.php';
include '../logfunc.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) exit("Invalid ID");

$stmt = $conn->prepare("SELECT * FROM livestocks WHERE livestock_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) exit("Livestock not found");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $number = intval($_POST['number'] ?? 0);
    $sex = trim($_POST['sex'] ?? '');
    $feeding_schedule = trim($_POST['feeding_schedule'] ?? '');

    if ($name === '') die("Livestock name is required.");

    $stmt = $conn->prepare("UPDATE livestocks SET name=?, type=?, age=?, number=?, sex=?, feeding_schedule=? WHERE livestock_id=?");
    if (!$stmt) die("Prepare failed: " . $conn->error);

    $stmt->bind_param("sssissi", $name, $type, $age, $number, $sex, $feeding_schedule, $id);

    if (!$stmt->execute()) die("Update failed: " . $stmt->error);
    $stmt->close();

    logActivity($conn, $_SESSION['user_id'], "Edited Livestock", "Livestock ID: $id updated");

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
<title>Edit Livestock</title>
<link rel="stylesheet" href="../style.css">
<style>
* { box-sizing: border-box; }
html, body { margin:0; padding:0; background:#fff; font-family:'Poppins',Arial,sans-serif; }
body { min-height:100vh; }
.form-wrapper { width:100%; padding:25px; }
.form-card { width:100%; max-width:560px; margin:0 auto; background:#fff; border-radius:14px; padding:25px; border-top:5px solid #2f6b2f; box-shadow:0 6px 18px rgba(0,0,0,.08); }
.form-card h2 { margin:0 0 20px; color:#2f6b2f; text-align:center; font-size:1.35rem; }
.form-group { margin-bottom:12px; }
.form-card label { display:block; margin-bottom:5px; font-size:.85rem; color:#555; }
.form-card input,.form-card select { width:100%; padding:11px 12px; border-radius:8px; border:1px solid #ddd; outline:none; font-family:inherit; font-size:.9rem; background:#fff; }
.form-card input:focus,.form-card select:focus { border-color:#2f6b2f; box-shadow:0 0 0 2px rgba(47,107,47,.15); }
.form-card button { width:100%; padding:12px; margin-top:8px; background:#2f6b2f; color:#fff; border:none; border-radius:8px; font-family:inherit; font-weight:600; cursor:pointer; }
.form-card button:hover { background:#245224; }
@media(max-width:600px){.form-wrapper{padding:15px}.form-card{padding:20px}}
</style>
</head>
<body>
<div class="form-wrapper">
<div class="form-card">
<h2>Edit Livestock</h2>
<form method="POST">
<div class="form-group"><label>Name</label><input name="name" value="<?= htmlspecialchars($data['name'] ?? '') ?>" required></div>
<div class="form-group"><label>Type / Breed</label><input name="type" value="<?= htmlspecialchars($data['type']) ?>" required></div>
<div class="form-group"><label>Age</label><input name="age" value="<?= htmlspecialchars($data['age']) ?>" required></div>
<div class="form-group"><label>Number of Animals</label><input name="number" type="number" min="0" value="<?= htmlspecialchars($data['number']) ?>" required></div>
<div class="form-group"><label>Sex</label><input name="sex" value="<?= htmlspecialchars($data['sex']) ?>" required></div>
<div class="form-group"><label>Feeding Schedule</label><input name="feeding_schedule" value="<?= htmlspecialchars($data['feeding_schedule'] ?? '') ?>" required></div>
<button type="submit">Update Livestock</button>
</form>
</div>
</div>
</body>
</html>