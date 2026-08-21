<?php
session_start();
include '../db.php';
include '../logfunc.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid ID");
}

$id = intval($_GET['id']);

if ($id <= 0) {
    die("Invalid ID");
}

/* ================= DELETE ================= */
$stmt = $conn->prepare("DELETE FROM crops WHERE crop_id = ?");
$stmt->bind_param("i", $id);

if (!$stmt->execute()) {
    die("Delete failed: " . $stmt->error);
}

$stmt->close();

/* ================= LOG ================= */
logActivity(
    $conn,
    $_SESSION['user_id'],
    "Deleted Crop",
    "Crop ID: $id deleted"
);

/*
 * If this file is ever loaded directly, redirect normally.
 * When called from the records dashboard, it is normally handled
 * through the confirmation modal's normal navigation.
 */
header("Location: ../records.php");
exit();
?>