<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db.php';

header("Content-Type: application/json");

$plot = $_GET['plot'] ?? '';

if (!$plot) {
    echo json_encode(["error" => "No plot provided"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        crop_id,
        crop_name,
        date_transplanted,
        expected_harvest
    FROM crops
    WHERE plot_code = ?
");

if (!$stmt) {
    echo json_encode(["error" => $conn->error]);
    exit;
}

$stmt->bind_param("s", $plot);

if (!$stmt->execute()) {
    echo json_encode(["error" => $stmt->error]);
    exit;
}

$result = $stmt->get_result();

if (!$result) {
    echo json_encode(["error" => "get_result() failed - enable mysqlnd"]);
    exit;
}

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);