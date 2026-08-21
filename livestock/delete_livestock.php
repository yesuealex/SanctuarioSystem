<?php
session_start();
include '../db.php';
include '../logfunc.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) die("Invalid ID");

$id = intval($_GET['id']);
if ($id <= 0) die("Invalid ID");

$stmt = $conn->prepare("DELETE FROM livestocks WHERE livestock_id = ?");
if (!$stmt) die("Prepare failed: " . $conn->error);

$stmt->bind_param("i", $id);

if (!$stmt->execute()) die("Delete failed: " . $stmt->error);

$stmt->close();

logActivity($conn, $_SESSION['user_id'], "Deleted Livestock", "Livestock ID: $id deleted");

header("Location: ../records.php");
exit();
?>