<?php
session_start();
header('Content-Type: application/json');

include 'db.php';

$plot = trim($_GET['plot'] ?? '');

if ($plot === '') {
    echo json_encode(['error' => 'No plot selected']);
    exit();
}

$response = [
    'type' => 'unknown',
    'plot_name' => '',
    'plot_house_name' => '',
    'plants_planted' => 0,
    'bed_available' => 0,
    'soon_to_harvest' => 0,
    'ready_to_harvest' => 0,
    'number_of_livestock' => 0,
    'records_found' => false
];

/* -------------------------
   CROP PLOT
-------------------------- */
$plotCode = strtolower($plot);
$plotStmt = $conn->prepare("SELECT plot_id, plot_name FROM plots WHERE LOWER(plot_code) = ? AND archived = 0 LIMIT 1");
if ($plotStmt) {
    $plotStmt->bind_param('s', $plotCode);
    $plotStmt->execute();
    $plotRow = $plotStmt->get_result()->fetch_assoc();
    $plotStmt->close();

    if ($plotRow) {
        $plotId = (int)$plotRow['plot_id'];
        $response['type'] = 'crop';
        $response['plot_name'] = $plotRow['plot_name'] ?: strtoupper(str_replace('_', ' ', $plotCode));

        $cropStmt = $conn->prepare("SELECT pcs, expected_harvest, status, bed_id FROM crops WHERE plot_id = ? AND COALESCE(archived,0) = 0");
        if ($cropStmt) {
            $cropStmt->bind_param('i', $plotId);
            $cropStmt->execute();
            $result = $cropStmt->get_result();

            $plants = 0;
            $soon = 0;
            $ready = 0;
            $occupiedBeds = [];
            $today = new DateTime('today');
            $soonLimit = (clone $today)->modify('+14 days');

            while ($row = $result->fetch_assoc()) {
                $pcs = max(0, (int)($row['pcs'] ?? 0));
                $plants += $pcs;

                if (!empty($row['bed_id'])) {
                    $occupiedBeds[(int)$row['bed_id']] = true;
                }

                $status = strtolower(trim((string)($row['status'] ?? '')));
                if ($status === 'ready') {
                    $ready += $pcs;
                }

                if (!empty($row['expected_harvest']) && $status !== 'ready' && $status !== 'harvested') {
                    try {
                        $harvest = new DateTime($row['expected_harvest']);
                        if ($harvest >= $today && $harvest <= $soonLimit) {
                            $soon += $pcs;
                        }
                    } catch (Exception $e) {}
                }
            }
            $cropStmt->close();

            $totalBeds = 0;
            $bedStmt = $conn->prepare("SELECT COUNT(*) AS total FROM plot_beds WHERE plot_id = ?");
            if ($bedStmt) {
                $bedStmt->bind_param('i', $plotId);
                $bedStmt->execute();
                $totalBeds = (int)($bedStmt->get_result()->fetch_assoc()['total'] ?? 0);
                $bedStmt->close();
            }

            /* The existing map has fixed bed slots for A/B/C. Use those only
               when plot_beds has not been populated yet. */
            if ($totalBeds <= 0) {
                $fallbackBeds = [1 => 17, 2 => 15, 3 => 19];
                $totalBeds = $fallbackBeds[$plotId] ?? 0;
                $occupied = count($occupiedBeds);
                $response['bed_available'] = max(0, $totalBeds - $occupied);
            } else {
                $response['bed_available'] = max(0, $totalBeds - count($occupiedBeds));
            }

            $response['plants_planted'] = $plants;
            $response['soon_to_harvest'] = $soon;
            $response['ready_to_harvest'] = $ready;
            $response['records_found'] = ($plants > 0 || $soon > 0 || $ready > 0 || count($occupiedBeds) > 0);

            echo json_encode($response);
            exit();
        }
    }
}

/* -------------------------
   LIVESTOCK HOUSE
-------------------------- */
$houseStmt = $conn->prepare("SELECT house_id, house_name FROM livestock_houses WHERE LOWER(house_code) = ? AND archived = 0 LIMIT 1");
if ($houseStmt) {
    $houseStmt->bind_param('s', $plotCode);
    $houseStmt->execute();
    $houseRow = $houseStmt->get_result()->fetch_assoc();
    $houseStmt->close();

    if ($houseRow) {
        $houseId = (int)$houseRow['house_id'];
        $response['type'] = 'livestock';
        $response['plot_house_name'] = $houseRow['house_name'];

        $liveStmt = $conn->prepare("SELECT COALESCE(SUM(number),0) AS total FROM livestocks WHERE house_id = ? AND COALESCE(archived,0) = 0");
        if ($liveStmt) {
            $liveStmt->bind_param('i', $houseId);
            $liveStmt->execute();
            $response['number_of_livestock'] = (int)($liveStmt->get_result()->fetch_assoc()['total'] ?? 0);
            $liveStmt->close();
        }

        $response['records_found'] = true;
        echo json_encode($response);
        exit();
    }
}

echo json_encode(['error' => 'No record location found']);
exit();
