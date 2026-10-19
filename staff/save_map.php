<?php
require_once dirname(__DIR__) . '/staff_access.php';
if (empty($_SESSION['user_id'])) { http_response_code(401); exit('Unauthorized.'); }
if (!in_array(sanctuario_staff_role(), ['crops staff', 'livestock staff'], true)) { http_response_code(403); exit('Access denied.'); }
?>

<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

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


// Staff can update positions only within their assigned areas.
$role = sanctuario_staff_role();
if ($role === 'crops staff' && !empty($data['houses'])) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Livestock houses are read-only for Crops Staff.']); exit;
}
if ($role === 'livestock staff' && !empty($data['plots'])) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Crop plots are read-only for Livestock Staff.']); exit;
}
$allowedPlots = ['plot_a','plot_b','plot_c','nursery','hydro','tent_house','mno'];
$allowedHouses = ['rabbit-house','poultry-house','chicken-house','swine-house','goat-house','goat-house-2'];
if (!empty($data['plots'])) {
    $check = $conn->prepare('SELECT plot_code FROM plots WHERE plot_id=? AND archived=0');
    foreach ($data['plots'] as $plot) {
        $id = (int)($plot['plot_id'] ?? 0);
        $check->bind_param('i', $id); $check->execute();
        $row = $check->get_result()->fetch_assoc();
        if (!$row || !in_array(strtolower($row['plot_code']), $allowedPlots, true)) {
            http_response_code(403); echo json_encode(['success'=>false,'message'=>'Plot edit not permitted.']); exit;
        }
    }
    $check->close();
}
if (!empty($data['houses'])) {
    $check = $conn->prepare('SELECT house_code FROM livestock_houses WHERE house_id=? AND archived=0');
    foreach ($data['houses'] as $house) {
        $id = (int)($house['house_id'] ?? 0);
        $check->bind_param('i', $id); $check->execute();
        $row = $check->get_result()->fetch_assoc();
        if (!$row || !in_array(strtolower($row['house_code']), $allowedHouses, true)) {
            http_response_code(403); echo json_encode(['success'=>false,'message'=>'House edit not permitted.']); exit;
        }
    }
    $check->close();
}

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | PLOT POSITIONS
    |--------------------------------------------------------------------------
    */

    if (!empty($data['plots'])) {

        $stmt = $conn->prepare("
            UPDATE plots
            SET map_x = ?, map_y = ?
            WHERE plot_id = ?
        ");

        foreach ($data['plots'] as $plot) {

            $plotId = intval($plot['plot_id']);

            $x = floatval($plot['map_x']);
            $y = floatval($plot['map_y']);

            $stmt->bind_param(
                "ddi",
                $x,
                $y,
                $plotId
            );

            $stmt->execute();
        }

        $stmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | LIVESTOCK HOUSE POSITIONS
    |--------------------------------------------------------------------------
    */

    if (!empty($data['houses'])) {

        $stmt = $conn->prepare("
            UPDATE livestock_houses
            SET map_x = ?, map_y = ?
            WHERE house_id = ?
        ");

        foreach ($data['houses'] as $house) {

            $houseId = intval($house['house_id']);

            $x = floatval($house['map_x']);
            $y = floatval($house['map_y']);

            $stmt->bind_param(
                "ddi",
                $x,
                $y,
                $houseId
            );

            $stmt->execute();
        }

        $stmt->close();
    }


    $conn->commit();


    echo json_encode([
        'success' => true,
        'message' => 'Map saved successfully.'
    ]);

} catch (Throwable $e) {

    $conn->rollback();

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}