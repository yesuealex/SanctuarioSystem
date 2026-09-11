<?php
session_start();
header('Content-Type: application/json');

include 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

/* GET plot_code from JS */
$plot = $_GET['plot'] ?? '';

if (!$plot) {
    echo json_encode(['error' => 'No plot selected']);
    exit();
}

$plot = trim($plot);

/* FIXED QUERY: uses plot_code (NOT plot_name, NOT plot_id) */
$stmt = $conn->prepare("
    SELECT 
        crop_name,
        recipient,
        variety,
        pcs,
        germination_rate,
        transplanted_area,
        date_transplanted,
        expected_harvest,
        plot_id
    FROM crops
    WHERE plot_id = ?
    ORDER BY date_transplanted DESC
");

if (!$stmt) {
    echo json_encode(['error' => $conn->error]);
    exit();
}

$stmt->bind_param("s", $plot);

if (!$stmt->execute()) {
    echo json_encode(['error' => $stmt->error]);
    exit();
}

$result = $stmt->get_result();

$crops = [];

while ($row = $result->fetch_assoc()) {
    $crops[] = $row;
}

echo json_encode([
    'plot' => strtoupper(str_replace('_', ' ', $plot)),
    'crops' => $crops
]);

$stmt->close();
?>