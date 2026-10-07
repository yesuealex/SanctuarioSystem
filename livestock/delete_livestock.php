<?php
session_start();
include '../db.php';
include '../logfunc.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'admin') die("Unauthorized");

if (!isset($_GET['id']) || empty($_GET['id'])) die("Invalid ID");

$id = intval($_GET['id']);
if ($id <= 0) die("Invalid ID");

$stmt = $conn->prepare("UPDATE livestocks SET archived = 1 WHERE livestock_id = ? AND archived = 0");
if (!$stmt) die("Prepare failed: " . $conn->error);

$stmt->bind_param("i", $id);

if (!$stmt->execute()) die("Delete failed: " . $stmt->error);

$stmt->close();

logActivity($conn, $_SESSION['user_id'], "Archived Livestock", "Livestock ID: $id archived");

header("Location: ../records.php");
exit();
?>