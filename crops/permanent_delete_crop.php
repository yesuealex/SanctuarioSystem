<?php

session_start();

include '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../records.php?type=crops");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: archived_crops.php");
    exit();
}

$crop_id = intval($_GET['id']);

$stmt = $conn->prepare("
    DELETE FROM crops
    WHERE crop_id = ?
    AND archived = 1
");

$stmt->bind_param("i", $crop_id);

$stmt->execute();

$stmt->close();

header("Location: archived_crops.php");
exit();

?>