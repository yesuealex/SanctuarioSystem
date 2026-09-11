<?php
session_start();
header('Content-Type: application/json');

include 'db.php';

$plot = $_GET['plot'] ?? '';

if (!$plot) {
    echo json_encode(['error' => 'No plot selected']);
    exit();
}

/* CLEAN INPUT */
$plot = trim($plot);

/* RESPONSE */
$response = [
    'plot' => strtoupper(str_replace('_', ' ', $plot)),
    'crops' => [],
    'livestock' => []
];

/* =========================
   CROPS
========================= */
if ($plot !== 'livestock') {

    $stmt = $conn->prepare("
        SELECT crop_name, recipient, pcs, date_transplanted
        FROM crops
        WHERE plot_id = ?
    ");

    if (!$stmt) {
        echo json_encode(['error' => 'SQL Prepare Failed: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("s", $plot);

    if (!$stmt->execute()) {
        echo json_encode(['error' => 'SQL Execute Failed: ' . $stmt->error]);
        exit();
    }

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $response['crops'][] = $row;
    }

    $stmt->close();
}

/* =========================
   LIVESTOCK
========================= */
if ($plot === 'livestock') {

    $result = $conn->query("
        SELECT type, age, number, sex, feeding_schedule
        FROM livestocks
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $response['livestock'][] = $row;
        }
    }
}

/* OUTPUT ONLY JSON */
echo json_encode($response);
exit();
?>