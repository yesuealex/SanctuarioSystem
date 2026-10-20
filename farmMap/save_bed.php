<?php
require_once __DIR__ . "/../staff_access.php";
sanctuario_staff_guard('crops');
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


$plotId = intval($data['plot_id'] ?? 0);
$beds = $data['beds'] ?? [];


if ($plotId <= 0) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid plot.'
    ]);

    exit;
}


$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | Existing bed IDs
    |--------------------------------------------------------------------------
    */

    $existingIds = [];

    $stmt = $conn->prepare("
        SELECT bed_id
        FROM plot_beds
        WHERE plot_id = ?
    ");

    $stmt->bind_param(
        "i",
        $plotId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $existingIds[] =
            intval($row['bed_id']);

    }

    $stmt->close();


    $submittedIds = [];


    /*
    |--------------------------------------------------------------------------
    | INSERT / UPDATE BEDS
    |--------------------------------------------------------------------------
    */

    foreach ($beds as $index => $bed) {

        $bedId =
            intval($bed['bed_id'] ?? 0);

        $number =
            intval(
                $bed['bed_number'] ??
                ($index + 1)
            );

        $code =
            trim(
                $bed['bed_code'] ??
                $plotId . '-' . $number
            );

        $x =
            isset($bed['map_x'])
                ? floatval($bed['map_x'])
                : 50;

        $y =
            isset($bed['map_y'])
                ? floatval($bed['map_y'])
                : 50;


        if ($bedId > 0) {

            $stmt = $conn->prepare("
                UPDATE plot_beds
                SET
                    bed_number = ?,
                    bed_code = ?,
                    map_x = ?,
                    map_y = ?
                WHERE
                    bed_id = ?
                    AND plot_id = ?
            ");

            $stmt->bind_param(
                "isddii",
                $number,
                $code,
                $x,
                $y,
                $bedId,
                $plotId
            );

            $stmt->execute();

            $submittedIds[] =
                $bedId;

            $stmt->close();

        } else {

            $stmt = $conn->prepare("
                INSERT INTO plot_beds
                (
                    plot_id,
                    bed_number,
                    bed_code,
                    map_x,
                    map_y
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iisdd",
                $plotId,
                $number,
                $code,
                $x,
                $y
            );

            $stmt->execute();

            $submittedIds[] =
                $stmt->insert_id;

            $stmt->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE REMOVED BEDS
    |--------------------------------------------------------------------------
    */

    foreach ($existingIds as $existingId) {

        if (
            !in_array(
                $existingId,
                $submittedIds,
                true
            )
        ) {

            $stmt = $conn->prepare("
                DELETE FROM plot_beds
                WHERE bed_id = ?
                  AND plot_id = ?
            ");

            $stmt->bind_param(
                "ii",
                $existingId,
                $plotId
            );

            $stmt->execute();

            $stmt->close();
        }
    }


    $conn->commit();


    echo json_encode([
        'success' => true,
        'message' => 'Beds saved successfully.'
    ]);

} catch (Throwable $e) {

    $conn->rollback();

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}