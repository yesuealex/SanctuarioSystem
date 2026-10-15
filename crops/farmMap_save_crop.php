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


// The Farm Map editor sends FormData, which PHP exposes in $_POST.
// Keep JSON support for any existing callers that still send JSON.
$data = $_POST;
if (empty($data)) {
    $data = json_decode(file_get_contents('php://input'), true);
}

if (!is_array($data) || empty($data)) {

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

$rawStatus = strtolower(trim((string)($data['status'] ?? 'planted')));
$statusKey = trim(preg_replace('/[^a-z]+/', ' ', $rawStatus));
$statusAliases = [
    'planted' => 'planted',
    'growing' => 'growing',
    'soon to harvest' => 'growing',
    'ready' => 'ready',
    'ready for harvest' => 'ready',
    'ready to harvest' => 'ready',
    'harvested' => 'harvested'
];
if (!isset($statusAliases[$statusKey])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid crop status. Use planted, growing, ready, or harvested.'
    ]);
    exit;
}
$status = $statusAliases[$statusKey];


if ($cropId <= 0) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid crop ID.'
    ]);

    exit;
}


// The current Farm Map FormData does not submit bed_id. Do not overwrite
// an existing bed assignment with 0 when that field is absent.
if (array_key_exists('bed_id', $data)) {
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
} else {
    $stmt = $conn->prepare("
        UPDATE crops
        SET
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
        "sssidssssi",
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
}


if (!$stmt->execute()) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $stmt->error
    ]);

    exit;
}

// farmMap.php reads the current name, variety, dates, and germination value
// from plot_crop_records when an active record exists. Keep that read model in
// sync with the crops row that was just updated so a successful save is visible
// after the page reloads. There may be no plot record for special/legacy crops,
// so a missing synchronization statement is intentionally non-fatal.
$recordSync = $conn->prepare(
    "UPDATE plot_crop_records
     SET crop_name=?, variety=?, date_transplanted=?, expected_harvest=?, germination_rate=?
     WHERE crop_id=? AND COALESCE(archived,0)=0"
);
if ($recordSync) {
    $recordSync->bind_param(
        'ssssdi',
        $cropName,
        $variety,
        $dateTransplanted,
        $expectedHarvest,
        $germination,
        $cropId
    );
    $recordSync->execute();
    $recordSync->close();
}

$stmt->close();


echo json_encode([
    'success' => true,
    'message' => 'Crop saved successfully.'
]);
