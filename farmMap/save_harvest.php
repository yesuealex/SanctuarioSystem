<?php
session_start();
require_once '../db.php';
require_once '../map_special_plots.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST is required.']);
    exit;
}

$cropId = (int)($_POST['crop_id'] ?? 0);
$location = strtoupper(trim((string)($_POST['location'] ?? '')));
$location = str_replace(['-', ' '], '_', $location);
$locationMap = [
    'A' => 'plot_a', 'PLOT_A' => 'plot_a',
    'B' => 'plot_b', 'PLOT_B' => 'plot_b',
    'C' => 'plot_c', 'PLOT_C' => 'plot_c',
    'MNO' => 'mno', 'HYDRO' => 'hydro',
    'TENT' => 'tent_house', 'TENT_HOUSE' => 'tent_house'
];
$plotCode = $locationMap[$location] ?? '';
if ($cropId <= 0 || $plotCode === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid crop and production plot are required.']);
    exit;
}

$date = trim((string)($_POST['harvest_date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid date of pull / harvest.']);
    exit;
}
$number = static function (string $key, bool $required = false) {
    $raw = trim((string)($_POST[$key] ?? ''));
    if ($raw === '') return $required ? false : null;
    return is_numeric($raw) && (float)$raw >= 0 ? (float)$raw : false;
};
$harvestCount = $number('harvest_count', true);
$totalQuantity = $number('total_quantity');
$salesQuantity = $number('sales_quantity');
$salesAmount = $number('sales_amount');
$farmUseQuantity = $number('farm_use_quantity');
$animalFeedQuantity = $number('animal_feed_quantity');
$rejectedQuantity = $number('rejected_quantity');
$otherQuantity = $number('other_purposes_quantity');
if ($harvestCount === false || in_array(false, [$totalQuantity, $salesQuantity, $salesAmount, $farmUseQuantity, $animalFeedQuantity, $rejectedQuantity, $otherQuantity], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Harvest values must be valid non-negative numbers.']);
    exit;
}
$units = [];
foreach (['total_unit', 'sales_unit', 'farm_use_unit', 'animal_feed_unit', 'rejected_unit', 'other_purposes_unit'] as $key) {
    $units[$key] = trim((string)($_POST[$key] ?? ''));
}

// Older installations already have plot_crop_harvests but not the newer
// harvest-count and other-purpose columns. Add them automatically so the
// Farm Map button works without requiring a manual SQL migration first.
$harvestColumns = [
    'harvest_count' => 'DECIMAL(10,2) NOT NULL DEFAULT 1 AFTER harvest_date',
    'other_purposes_quantity' => 'DECIMAL(12,3) DEFAULT NULL AFTER rejected_unit',
    'other_purposes_unit' => 'VARCHAR(30) DEFAULT NULL AFTER other_purposes_quantity'
];
foreach ($harvestColumns as $column => $definition) {
    $columnEscaped = $conn->real_escape_string($column);
    $columnCheck = $conn->query("SHOW COLUMNS FROM plot_crop_harvests LIKE '{$columnEscaped}'");
    if ($columnCheck && $columnCheck->num_rows === 0) {
        if (!$conn->query("ALTER TABLE plot_crop_harvests ADD COLUMN `{$column}` {$definition}")) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not prepare the harvest table: ' . $conn->error]);
            exit;
        }
    }
}

$plotStmt = $conn->prepare('SELECT plot_id, plot_name FROM plots WHERE LOWER(plot_code)=LOWER(?) AND archived=0 LIMIT 1');
$plotStmt->bind_param('s', $plotCode);
$plotStmt->execute();
$plot = $plotStmt->get_result()->fetch_assoc();
$plotStmt->close();
if (!$plot) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'The selected production plot does not exist.']);
    exit;
}
$plotId = (int)$plot['plot_id'];

$cropStmt = $conn->prepare("SELECT c.crop_id, c.crop_name, c.variety, c.bed_id, c.date_transplanted, c.date_sowing,
        c.expected_harvest, c.germination_rate, c.transplanted_area
    FROM crops c
    WHERE c.crop_id=? AND COALESCE(c.archived,0)=0 AND COALESCE(c.transferred_out,0)=0
      AND (c.plot_id=? OR LOWER(TRIM(COALESCE(c.transplanted_area,'')))=LOWER(?)
           OR LOWER(TRIM(COALESCE(c.transplanted_area,''))) LIKE CONCAT(LOWER(?), '%'))
    LIMIT 1");
$plotName = (string)$plot['plot_name'];
$plotPrefix = strtoupper(preg_replace('/^plot_/', '', $plotCode));
$cropStmt->bind_param('iiss', $cropId, $plotId, $plotName, $plotPrefix);
$cropStmt->execute();
$crop = $cropStmt->get_result()->fetch_assoc();
$cropStmt->close();
if (!$crop) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'The selected crop does not belong to this production plot.']);
    exit;
}

$conn->begin_transaction();
try {
    $recordStmt = $conn->prepare('SELECT record_id FROM plot_crop_records WHERE crop_id=? AND plot_id=? AND COALESCE(archived,0)=0 LIMIT 1');
    $recordStmt->bind_param('ii', $cropId, $plotId);
    $recordStmt->execute();
    $record = $recordStmt->get_result()->fetch_assoc();
    $recordStmt->close();
    $recordId = (int)($record['record_id'] ?? 0);

    if ($recordId <= 0) {
        $recordDate = $crop['date_transplanted'] ?: ($crop['date_sowing'] ?: $date);
        $insertRecord = $conn->prepare('INSERT INTO plot_crop_records (crop_id, plot_id, bed_id, crop_name, variety, record_date, date_transplanted, expected_harvest, germination_rate) VALUES (?,?,?,?,?,?,?,?,?)');
        $bedId = (int)($crop['bed_id'] ?? 0);
        $cropName = (string)($crop['crop_name'] ?? '');
        $variety = (string)($crop['variety'] ?? '');
        $expectedHarvest = $crop['expected_harvest'] ?: null;
        $germination = (float)($crop['germination_rate'] ?? 0);
        $insertRecord->bind_param('iiisssssd', $cropId, $plotId, $bedId, $cropName, $variety, $recordDate, $recordDate, $expectedHarvest, $germination);
        if (!$insertRecord->execute()) throw new Exception($insertRecord->error);
        $recordId = (int)$conn->insert_id;
        $insertRecord->close();
    }

    $insertHarvest = $conn->prepare('INSERT INTO plot_crop_harvests (record_id, harvest_date, harvest_count, total_quantity, total_unit, sales_quantity, sales_unit, sales_amount, farm_use_quantity, farm_use_unit, animal_feed_quantity, animal_feed_unit, rejected_quantity, rejected_unit, other_purposes_quantity, other_purposes_unit) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    if (!$insertHarvest) throw new Exception($conn->error);
    $insertHarvest->bind_param(
        'isddsdsddsdsdsds',
        $recordId, $date, $harvestCount, $totalQuantity, $units['total_unit'],
        $salesQuantity, $units['sales_unit'], $salesAmount,
        $farmUseQuantity, $units['farm_use_unit'], $animalFeedQuantity, $units['animal_feed_unit'],
        $rejectedQuantity, $units['rejected_unit'], $otherQuantity, $units['other_purposes_unit']
    );
    if (!$insertHarvest->execute()) throw new Exception($insertHarvest->error);
    $insertHarvest->close();

    $statusStmt = $conn->prepare("UPDATE crops SET status='harvested' WHERE crop_id=? AND status <> 'harvested'");
    $statusStmt->bind_param('i', $cropId);
    $statusStmt->execute();
    $statusStmt->close();
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Harvest record saved.', 'record_id' => $recordId]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save the harvest record: ' . $e->getMessage()]);
}
