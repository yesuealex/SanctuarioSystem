<?php

session_start();

include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);

    exit;
}


$data = json_decode(
    file_get_contents('php://input'),
    true
);


if (!is_array($data)) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid data'
    ]);

    exit;
}


$cropId =
    intval(
        $data['crop_id'] ?? 0
    );

$bedId =
    intval(
        $data['bed_id'] ?? 0
    );

$cropName =
    trim(
        $data['crop_name'] ?? ''
    );

$variety =
    trim(
        $data['variety'] ?? ''
    );

$recipient =
    trim(
        $data['recipient'] ?? ''
    );

$pcs =
    intval(
        $data['pcs'] ?? 0
    );

$germination =
    floatval(
        $data['germination_rate'] ?? 0
    );

$area =
    trim(
        $data['transplanted_area'] ?? ''
    );

$dateTransplanted =
    !empty($data['date_transplanted'])
        ? $data['date_transplanted']
        : null;

$expectedHarvest =
    !empty($data['expected_harvest'])
        ? $data['expected_harvest']
        : null;

$status =
    trim(
        $data['status'] ?? 'Planted'
    );


if ($cropId <= 0) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid crop ID.'
    ]);

    exit;
}


$stmt = $conn->prepare("
    UPDATE crops
    SET
        bed_id = ?,
        crop_name = ?,
        variety = ?,
        recipient = ?,
        pcs = ?,
        germination_rate = ?,
        transplanted_area = ?,
        date_transplanted = ?,
        expected_harvest = ?,
        status = ?
    WHERE crop_id = ?
");


$stmt->bind_param(
    "isssidssssi",
    $bedId,
    $cropName,
    $variety,
    $recipient,
    $pcs,
    $germination,
    $area,
    $dateTransplanted,
    $expectedHarvest,
    $status,
    $cropId
);


if (!$stmt->execute()) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $stmt->error
    ]);

    exit;
}


echo json_encode([
    'success' => true,
    'message' => 'Crop saved successfully.'
]);