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

$dateSowing =
    !empty($data['date_sowing'])
        ? $data['date_sowing']
        : null;

$targetTransplantDate =
    !empty($data['target_transplant_date'])
        ? $data['target_transplant_date']
        : null;

$expectedHarvest =
    !empty($data['expected_harvest'])
        ? $data['expected_harvest']
        : null;

$isValidDate = static function ($value) {
    if ($value === null || $value === '') return true;
    $date = DateTime::createFromFormat('!Y-m-d', (string)$value);
    return $date !== false && $date->format('Y-m-d') === $value;
};
if (!$isValidDate($dateSowing) || !$isValidDate($targetTransplantDate)
    || !$isValidDate($dateTransplanted) || !$isValidDate($expectedHarvest)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dates must be valid calendar dates.']);
    exit;
}

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
	$createCrop = (string)($data['create_crop'] ?? '') === '1';
	$location = strtoupper(trim((string)($data['plot_letter'] ?? '')));
	$nurseryBedLabel = strtoupper(preg_replace('/\s+/', '', $area));

	if ($createCrop && $location === 'NURSERY') {
		$dateSowing = $dateSowing ?: date('Y-m-d');
		$dateTransplanted = null;
		if ($cropName === '' || $recipient === '') {
			http_response_code(400);
			echo json_encode(['success' => false, 'message' => 'Crop name and recipient are required.']);
			exit;
		}
		if (!$targetTransplantDate || !$expectedHarvest) {
			http_response_code(400);
			echo json_encode(['success' => false, 'message' => 'Target transplant date and expected harvest are required for Nursery crops.']);
			exit;
		}
		if (!preg_match('/^N(?:[1-9]|1[0-9]|20)$/', $nurseryBedLabel)) {
			http_response_code(400);
			echo json_encode(['success' => false, 'message' => 'Please select a valid Nursery bed (N1-N20).']);
			exit;
		}

		$plotStmt = $conn->prepare("SELECT plot_id FROM plots WHERE LOWER(TRIM(plot_code))='nursery' OR UPPER(TRIM(plot_name))='NURSERY' ORDER BY (LOWER(TRIM(plot_code))='nursery') DESC LIMIT 1");
		if (!$plotStmt || !$plotStmt->execute()) {
			http_response_code(500);
			echo json_encode(['success' => false, 'message' => 'Unable to validate the Nursery plot.']);
			exit;
		}
		$nurseryPlot = $plotStmt->get_result()->fetch_assoc();
		$plotStmt->close();
		if (!$nurseryPlot) {
			http_response_code(400);
			echo json_encode(['success' => false, 'message' => 'The Nursery plot does not exist.']);
			exit;
		}
		$nurseryPlotId = (int)$nurseryPlot['plot_id'];

		$bedStmt = $conn->prepare('SELECT bed_id, bed_code FROM plot_beds WHERE plot_id=? AND UPPER(TRIM(bed_code))=? LIMIT 1');
		if (!$bedStmt) {
			http_response_code(500);
			echo json_encode(['success' => false, 'message' => 'Unable to validate the Nursery bed.']);
			exit;
		}
		$bedStmt->bind_param('is', $nurseryPlotId, $nurseryBedLabel);
		$bedStmt->execute();
		$nurseryBed = $bedStmt->get_result()->fetch_assoc();
		$bedStmt->close();
		if (!$nurseryBed) {
			http_response_code(400);
			echo json_encode(['success' => false, 'message' => 'The selected Nursery bed does not exist.']);
			exit;
		}
		$bedId = (int)$nurseryBed['bed_id'];
		$nurseryBedLabel = (string)$nurseryBed['bed_code'];

		$occupied = $conn->prepare("SELECT c.crop_id FROM crops c LEFT JOIN nursery_crops nc ON nc.crop_id=c.crop_id AND COALESCE(nc.archived,0)=0 WHERE COALESCE(c.archived,0)=0 AND COALESCE(c.transferred_out,0)=0 AND (c.bed_id=? OR UPPER(TRIM(COALESCE(nc.nursery_bed_label,'')))=?) LIMIT 1");
		if (!$occupied) {
			http_response_code(500);
			echo json_encode(['success' => false, 'message' => 'Unable to validate Nursery bed availability.']);
			exit;
		}
		$occupied->bind_param('is', $bedId, $nurseryBedLabel);
		$occupied->execute();
		$isOccupied = $occupied->get_result()->num_rows > 0;
		$occupied->close();
		if ($isOccupied) {
			http_response_code(409);
			echo json_encode(['success' => false, 'message' => 'That Nursery bed is already occupied by an active crop.']);
			exit;
		}

		$conn->begin_transaction();
		$insert = $conn->prepare("INSERT INTO crops (crop_name, recipient, variety, pcs, germination_rate, date_sowing, target_transplant_date, transplanted_area, date_transplanted, expected_harvest, plot_id, bed_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'NURSERY', ?, ?, ?, ?, ?)");
		if (!$insert) {
			$conn->rollback();
			http_response_code(500);
			echo json_encode(['success' => false, 'message' => 'Unable to prepare the crop record.']);
			exit;
		}
		$insert->bind_param('sssidssssiis', $cropName, $recipient, $variety, $pcs, $germination, $dateSowing, $targetTransplantDate, $dateTransplanted, $expectedHarvest, $nurseryPlotId, $bedId, $status);
		if (!$insert->execute()) {
			$message = $insert->error;
			$insert->close();
			$conn->rollback();
			http_response_code(500);
			echo json_encode(['success' => false, 'message' => $message]);
			exit;
		}
		$newCropId = (int)$conn->insert_id;
		$insert->close();
		$nurseryInsert = $conn->prepare('INSERT INTO nursery_crops (crop_id, nursery_bed_label) VALUES (?, ?)');
		if (!$nurseryInsert) {
			$conn->rollback();
			http_response_code(500);
			echo json_encode(['success' => false, 'message' => 'Unable to prepare the Nursery record.']);
			exit;
		}
		$nurseryInsert->bind_param('is', $newCropId, $nurseryBedLabel);
		if (!$nurseryInsert->execute()) {
			$message = $nurseryInsert->error;
			$nurseryInsert->close();
			$conn->rollback();
			http_response_code(500);
			echo json_encode(['success' => false, 'message' => $message]);
			exit;
		}
		$nurseryInsert->close();
		$conn->commit();

		echo json_encode(['success' => true, 'message' => 'Crop saved successfully.', 'crop_id' => $newCropId]);
		exit;
	}

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
            date_sowing = ?,
            target_transplant_date = ?,
            transplanted_area = ?,
            date_transplanted = ?,
            expected_harvest = ?,
            status = ?
        WHERE crop_id = ?
    ");

    $stmt->bind_param(
        "isssidssssssi",
        $bedId,
        $cropName,
        $variety,
        $recipient,
        $pcs,
        $germination,
        $dateSowing,
        $targetTransplantDate,
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
            date_sowing = ?,
            target_transplant_date = ?,
            transplanted_area = ?,
            date_transplanted = ?,
            expected_harvest = ?,
            status = ?
        WHERE crop_id = ?
    ");

    $stmt->bind_param(
        "sssidssssssi",
        $cropName,
        $variety,
        $recipient,
        $pcs,
        $germination,
        $dateSowing,
        $targetTransplantDate,
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
