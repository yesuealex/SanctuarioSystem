<?php
require_once __DIR__ . '/../staff_access.php';
sanctuario_staff_guard('crops');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../db.php';
require_once '../archive/archive_functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please sign in again.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$cropId = (int)($_POST['crop_id'] ?? 0);
if ($cropId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid crop ID.'
    ]);
    exit;
}

try {
    archiveRecord($conn, 'crops', 'crop_id', $cropId, (int)$_SESSION['user_id']);

    // Do not report success unless the active crop is actually gone.
    $verify = $conn->prepare('SELECT COUNT(*) AS active_count FROM crops WHERE crop_id=?');
    if (!$verify) {
        throw new Exception('Could not verify crop removal: ' . $conn->error);
    }
    $verify->bind_param('i', $cropId);
    $verify->execute();
    $activeCount = (int)($verify->get_result()->fetch_assoc()['active_count'] ?? 0);
    $verify->close();

    if ($activeCount !== 0) {
        throw new Exception('The crop is still present in active records.');
    }

    echo json_encode([
        'success' => true,
        'archived' => true,
        'deleted' => true,
        'crop_id' => $cropId,
        'message' => 'Crop archived successfully.'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}