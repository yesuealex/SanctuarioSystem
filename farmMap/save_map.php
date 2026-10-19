<?php
require_once __DIR__ . "/../staff_access.php";
if (in_array(sanctuario_staff_role(), ["crops staff","livestock staff"], true)) { http_response_code(403); exit("Access denied."); }
?>
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