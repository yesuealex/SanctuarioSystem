<?php
include 'db.php';

header('Content-Type: application/json');

$crops = $conn->query("SELECT COUNT(*) as total FROM crops")->fetch_assoc()['total'];
$livestock = $conn->query("SELECT COUNT(*) as total FROM livestocks")->fetch_assoc()['total'];

echo json_encode([
    "crops" => $crops,
    "livestock" => $livestock
]);
?>