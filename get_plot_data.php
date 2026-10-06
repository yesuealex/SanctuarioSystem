<?php
session_start();
header('Content-Type: application/json');

include 'db.php';

$plot = trim($_GET['plot'] ?? '');

if ($plot === '') {
    echo json_encode(['error' => 'No plot selected']);
    exit();
}

/* Dashboard markers use underscores while database house codes may use
   hyphens. Canonicalize both forms before resolving records. */
$canonicalCode = static function ($value) {
    return preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string)$value)));
};

$plotCode = $canonicalCode($plot);

$response = [
    'type' => 'unknown',
    'plot_name' => '',
    'plot_house_name' => '',
    'plants_planted' => 0,
    'bed_available' => 0,
    'soon_to_harvest' => 0,
    'ready_to_harvest' => 0,
    'growing' => 0,
    'record_count' => 0,
    'number_of_livestock' => 0,
    'records_found' => false,
    'location_name' => ucwords(str_replace('_', ' ', $plotCode))
];

/* -------------------------
   CROP PLOT
-------------------------- */
$plotStmt = $conn->prepare("SELECT plot_id, plot_name FROM plots WHERE LOWER(REPLACE(plot_code, '-', '_')) = ? AND archived = 0 LIMIT 1");
if ($plotStmt) {
    $plotStmt->bind_param('s', $plotCode);
    $plotStmt->execute();
    $plotRow = $plotStmt->get_result()->fetch_assoc();
    $plotStmt->close();

    if ($plotRow) {
        $plotId = (int)$plotRow['plot_id'];
        $response['type'] = 'crop';
        $response['plot_name'] = $plotRow['plot_name'] ?: strtoupper(str_replace('_', ' ', $plotCode));
        $response['location_name'] = $response['plot_name'];

        $plotLetter = strtolower(substr($plotCode, -1));
        $cropStmt = $conn->prepare("
            SELECT expected_harvest, status, bed_id
            FROM crops
            WHERE COALESCE(archived,0) = 0
              AND (
                  LOWER(TRIM(COALESCE(transplanted_area,''))) = ?
                  OR LOWER(TRIM(COALESCE(transplanted_area,''))) REGEXP CONCAT('^', ?, '[0-9]+$')
              )
        ");
        if ($cropStmt) {
            $cropStmt->bind_param('ss', $plotLetter, $plotLetter);
            $cropStmt->execute();
            $result = $cropStmt->get_result();

            $plants = 0;
            $soon = 0;
            $ready = 0;
            $occupiedBeds = [];
            $today = new DateTime('today');
            $soonLimit = (clone $today)->modify('+14 days');

            while ($row = $result->fetch_assoc()) {
                // One active crop row represents one planted crop record.
                $plants += 1;

                if (!empty($row['bed_id'])) {
                    $occupiedBeds[(int)$row['bed_id']] = true;
                }

                $status = strtolower(trim((string)($row['status'] ?? '')));
                if ($status === 'ready') {
                    $ready += 1;
                }

                if (!empty($row['expected_harvest']) && $status !== 'ready' && $status !== 'harvested') {
                    try {
                        $harvest = new DateTime($row['expected_harvest']);
                        if ($harvest >= $today && $harvest <= $soonLimit) {
                            $soon += 1;
                        }
                    } catch (Exception $e) {}
                }
            }
            $cropStmt->close();

            $growing = max(0, $plants - $soon - $ready);

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
            $response['record_count'] = $plants;
            $response['soon_to_harvest'] = $soon;
            $response['ready_to_harvest'] = $ready;
            $response['growing'] = $growing;
            $response['records_found'] = ($plants > 0 || $soon > 0 || $ready > 0 || count($occupiedBeds) > 0);

            echo json_encode($response);
            exit();
        }
    }
}

/* -------------------------
   NURSERY
-------------------------- */
if ($plotCode === 'nursery') {
    $response['type'] = 'crop';
    $response['plot_name'] = 'Nursery';
    $response['location_name'] = 'Nursery';

    $nurseryStmt = $conn->prepare(
        "SELECT pcs, expected_harvest, status, bed_id
         FROM crops
         WHERE COALESCE(archived, 0) = 0
           AND LOWER(TRIM(COALESCE(transplanted_area, ''))) = 'nursery'"
    );

    $occupiedBeds = [];
    if ($nurseryStmt) {
        $nurseryStmt->execute();
        $result = $nurseryStmt->get_result();
        $today = new DateTime('today');
        $soonLimit = (clone $today)->modify('+14 days');

        while ($row = $result->fetch_assoc()) {
            // One active nursery row represents one planted nursery record.
            $response['plants_planted'] += 1;
            if (!empty($row['bed_id'])) {
                $occupiedBeds[(int)$row['bed_id']] = true;
            }

            $status = strtolower(trim((string)($row['status'] ?? '')));
            if ($status === 'ready') {
                $response['ready_to_harvest'] += 1;
            }
            if (!empty($row['expected_harvest']) && $status !== 'ready' && $status !== 'harvested') {
                try {
                    $harvest = new DateTime($row['expected_harvest']);
                    if ($harvest >= $today && $harvest <= $soonLimit) {
                        $response['soon_to_harvest'] += 1;
                    }
                } catch (Exception $e) {}
            }
        }
        $nurseryStmt->close();
    }

    $response['record_count'] = $response['plants_planted'];
    $response['growing'] = max(0, $response['plants_planted'] - $response['soon_to_harvest'] - $response['ready_to_harvest']);
    $occupiedCount = count($occupiedBeds) > 0 ? count($occupiedBeds) : $response['plants_planted'];
    $response['bed_available'] = max(0, 20 - $occupiedCount);
    $response['records_found'] = ($response['plants_planted'] > 0 || count($occupiedBeds) > 0);
    echo json_encode($response);
    exit();
}

/* -------------------------
   MNO CROP AREA
-------------------------- */
if ($plotCode === 'mno') {
    $response['type'] = 'crop';
    $response['plot_name'] = 'MNO';
    $response['location_name'] = 'MNO';

    $mnoStmt = $conn->prepare(
        "SELECT expected_harvest, status, bed_id
         FROM crops
         WHERE COALESCE(archived, 0) = 0
           AND LOWER(TRIM(COALESCE(transplanted_area, ''))) = 'mno'"
    );

    $occupiedBeds = [];
    if ($mnoStmt) {
        $mnoStmt->execute();
        $result = $mnoStmt->get_result();
        $today = new DateTime('today');
        $soonLimit = (clone $today)->modify('+14 days');

        while ($row = $result->fetch_assoc()) {
            $response['plants_planted'] += 1;
            if (!empty($row['bed_id'])) {
                $occupiedBeds[(int)$row['bed_id']] = true;
            }

            $status = strtolower(trim((string)($row['status'] ?? '')));
            if ($status === 'ready') {
                $response['ready_to_harvest'] += 1;
            }
            if (!empty($row['expected_harvest']) && $status !== 'ready' && $status !== 'harvested') {
                try {
                    $harvest = new DateTime($row['expected_harvest']);
                    if ($harvest >= $today && $harvest <= $soonLimit) {
                        $response['soon_to_harvest'] += 1;
                    }
                } catch (Exception $e) {}
            }
        }
        $mnoStmt->close();
    }

    $response['record_count'] = $response['plants_planted'];
    $response['growing'] = max(0, $response['plants_planted'] - $response['soon_to_harvest'] - $response['ready_to_harvest']);
    $occupiedCount = count($occupiedBeds) > 0 ? count($occupiedBeds) : $response['plants_planted'];
    $response['bed_available'] = max(0, 20 - $occupiedCount);
    $response['records_found'] = ($response['plants_planted'] > 0 || count($occupiedBeds) > 0);
    echo json_encode($response);
    exit();
}

/* -------------------------
   LIVESTOCK HOUSE
-------------------------- */
$houseStmt = $conn->prepare("SELECT house_id, house_name FROM livestock_houses WHERE LOWER(REPLACE(house_code, '-', '_')) = ? AND archived = 0 LIMIT 1");
if ($houseStmt) {
    $houseStmt->bind_param('s', $plotCode);
    $houseStmt->execute();
    $houseRow = $houseStmt->get_result()->fetch_assoc();
    $houseStmt->close();

    if ($houseRow) {
        $houseId = (int)$houseRow['house_id'];
        $response['type'] = 'livestock';
        $response['plot_house_name'] = $houseRow['house_name'];
        $response['location_name'] = $houseRow['house_name'];

        $liveStmt = $conn->prepare("SELECT COALESCE(SUM(number),0) AS total FROM livestocks WHERE house_id = ? AND COALESCE(archived,0) = 0");
        $directTotal = 0;
        if ($liveStmt) {
            $liveStmt->bind_param('i', $houseId);
            $liveStmt->execute();
            $directTotal = (int)($liveStmt->get_result()->fetch_assoc()['total'] ?? 0);
            $liveStmt->close();
        }

        $response['number_of_livestock'] = $directTotal;

        // Some older records have no house_id. For those records, use the
        // first matching house for their livestock type as the fallback.
        $houseCode = strtolower((string)($plotCode ?? ''));
        $fallbackType = '';
        if (strpos($houseCode, 'rabbit') !== false) $fallbackType = 'rabbit';
        elseif (strpos($houseCode, 'poultry') !== false || strpos($houseCode, 'chicken') !== false) $fallbackType = 'chicken';
        elseif (strpos($houseCode, 'swine') !== false || strpos($houseCode, 'pig') !== false) $fallbackType = 'pig';
        elseif (strpos($houseCode, 'goat') !== false) $fallbackType = 'goat';

        if ($directTotal === 0 && $fallbackType !== '') {
            $firstHouseStmt = $conn->prepare("
                SELECT MIN(house_id) AS first_house_id
                FROM livestock_houses
                WHERE archived = 0
                  AND (
                      LOWER(house_code) LIKE CONCAT('%', ?, '%')
                      OR LOWER(house_name) LIKE CONCAT('%', ?, '%')
                  )
            ");
            if ($firstHouseStmt) {
                $firstHouseStmt->bind_param('ss', $fallbackType, $fallbackType);
                $firstHouseStmt->execute();
                $firstHouseId = (int)($firstHouseStmt->get_result()->fetch_assoc()['first_house_id'] ?? 0);
                $firstHouseStmt->close();

                if ($firstHouseId === $houseId) {
                    $fallbackStmt = $conn->prepare("
                        SELECT COALESCE(SUM(number),0) AS total
                        FROM livestocks
                        WHERE house_id IS NULL
                          AND COALESCE(archived,0) = 0
                          AND (
                              LOWER(type) LIKE CONCAT('%', ?, '%')
                              OR (? = 'chicken' AND LOWER(type) LIKE '%poultry%')
                          )
                    ");
                    if ($fallbackStmt) {
                        $fallbackStmt->bind_param('ss', $fallbackType, $fallbackType);
                        $fallbackStmt->execute();
                        $response['number_of_livestock'] = (int)($fallbackStmt->get_result()->fetch_assoc()['total'] ?? 0);
                        $fallbackStmt->close();
                    }
                }
            }
        }

        $response['records_found'] = true;
        echo json_encode($response);
        exit();
    }
}

/* Keep valid dashboard markers usable even when they have no rows yet. */
$response['records_found'] = false;
echo json_encode($response);
exit();