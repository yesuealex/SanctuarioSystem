<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$plot = strtolower(trim((string)($_GET['plot'] ?? '')));
if ($plot === '') {
    echo json_encode(['error' => 'No plot provided']);
    exit;
}

// Crop locations are stored in crops.transplanted_area (A4, plot_a, etc.).
// There is no plot_code column in crops; plot_code belongs to plots.
$plotLetter = preg_replace('/^plot_/', '', $plot);
$plotLetter = preg_replace('/[^a-c].*$/', '', $plotLetter);
if (!in_array($plotLetter, ['a', 'b', 'c'], true)) {
    echo json_encode([]);
    exit;
}

$locationPattern = '^(plot_)?' . $plotLetter . '[0-9]*$';
$plotCode = 'plot_' . $plotLetter;

$stmt = $conn->prepare("
    SELECT
        c.crop_id,
        c.crop_name,
        c.date_sowing,
        c.target_transplant_date,
        c.date_transplanted,
        c.expected_harvest,
        c.status,
        c.bed_id,
        c.transplanted_area
    FROM crops AS c
    LEFT JOIN plots AS p ON p.plot_id = c.plot_id
    WHERE COALESCE(c.archived, 0) = 0
      AND (
          LOWER(REPLACE(TRIM(COALESCE(c.transplanted_area, '')), ' ', '')) REGEXP ?
          OR (
              TRIM(COALESCE(c.transplanted_area, '')) = ''
              AND p.plot_code = ?
          )
      )
    ORDER BY c.crop_id ASC
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
    exit;
}

$stmt->bind_param('ss', $locationPattern, $plotCode);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => $stmt->error]);
    $stmt->close();
    exit;
}

$result = $stmt->get_result();
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

$stmt->close();
echo json_encode($data);
