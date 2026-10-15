<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/map_special_plots.php';

$plot = strtolower(trim((string)($_GET['plot'] ?? '')));
if ($plot === '') {
    echo json_encode(['error' => 'Missing plot']);
    exit;
}

function safeRows($result): array
{
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function locationGroup(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[_-]+/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    $compact = str_replace(' ', '', $value);

    if ($compact === 'nursery' || str_starts_with($compact, 'nursery')) return 'nursery';
    if ($compact === 'mno' || str_starts_with($compact, 'mno')) return 'mno';
    if ($compact === 'hydro' || str_starts_with($compact, 'hydro')) return 'hydro';
    if ($compact === 'tenthouse' || str_starts_with($compact, 'tenthouse')) return 'tent_house';
    if (preg_match('/^(?:plot)?([abc])\d*$/', $compact, $match)) return 'plot_' . $match[1];
    if (preg_match('/^plot\d+$/', $compact)) return $compact;
    return $value;
}

function canonicalStatus(string $value): string
{
    $key = strtolower(trim($value));
    $key = preg_replace('/[_-]+/', ' ', $key);
    $key = preg_replace('/\s+/', ' ', $key);

    if ($key === 'ready' || $key === 'ready for harvest' || $key === 'ready to harvest') return 'ready';
    if ($key === 'harvested') return 'harvested';
    if ($key === 'growing') return 'growing';
    return 'planted';
}

$isLivestock = in_array($plot, ['rabbit_house', 'goat_house', 'goat_house_2', 'poultry_house', 'swine_house'], true);
if ($isLivestock) {
    $houses = [];
    if ($result = $conn->query('SELECT * FROM livestock_houses WHERE COALESCE(archived,0)=0')) {
        $houses = safeRows($result);
    }
    $house = null;
    foreach ($houses as $candidate) {
        if (strtolower((string)($candidate['house_code'] ?? '')) === $plot) {
            $house = $candidate;
            break;
        }
    }

    $animals = [];
    if ($house && isset($house['house_id'])) {
        $stmt = $conn->prepare('SELECT * FROM livestocks WHERE COALESCE(archived,0)=0 AND house_id=? ORDER BY livestock_id ASC');
        $houseId = (int)$house['house_id'];
        $stmt->bind_param('i', $houseId);
        $stmt->execute();
        $animals = safeRows($stmt->get_result());
        $stmt->close();
    }

    echo json_encode([
        'type' => 'livestock',
        'plot_house_name' => $house['house_name'] ?? ucwords(str_replace('_', ' ', $plot)),
        'number_of_livestock' => count($animals),
        'livestock_records' => $animals
    ]);
    exit;
}

$wantedGroup = locationGroup($plot);
$plotRow = null;
$stmt = $conn->prepare('SELECT plot_id, plot_code, plot_name FROM plots WHERE LOWER(plot_code)=LOWER(?) OR LOWER(plot_name)=LOWER(?) LIMIT 1');
$stmt->bind_param('ss', $plot, $plot);
$stmt->execute();
$plotRow = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();
$plotId = (int)($plotRow['plot_id'] ?? 0);

// Fetch active crops once, then resolve each crop to its parent plot. This combines
// all beds (and legacy area formats such as A1, PLOT_A, or PLOT A) under one plot.
$rows = [];
$result = $conn->query("SELECT c.*, b.bed_code, b.plot_id AS bed_plot_id
    FROM crops c
    LEFT JOIN plot_beds b ON b.bed_id=c.bed_id
    WHERE COALESCE(c.archived,0)=0
      AND COALESCE(c.transferred_out,0)=0
    ORDER BY c.crop_id ASC");
foreach (safeRows($result) as $crop) {
    $cropPlotId = (int)($crop['plot_id'] ?? 0);
    $bedPlotId = (int)($crop['bed_plot_id'] ?? 0);
    $cropGroup = locationGroup((string)($crop['transplanted_area'] ?? ''));
    $plotNameGroup = locationGroup((string)($crop['plot_name'] ?? ''));

    if ($plotId > 0 && ($cropPlotId === $plotId || $bedPlotId === $plotId || $cropGroup === $wantedGroup || $plotNameGroup === $wantedGroup)) {
        $rows[] = $crop;
    } elseif ($plotId === 0 && ($cropGroup === $wantedGroup || $plotNameGroup === $wantedGroup)) {
        $rows[] = $crop;
    }
}

$ready = 0;
$soon = 0;
$growing = 0;
$today = new DateTime('today');
$limit = (clone $today)->modify('+14 days');
foreach ($rows as $crop) {
    $status = canonicalStatus((string)($crop['status'] ?? ''));
    $dateReady = false;
    if ($status !== 'harvested' && !empty($crop['expected_harvest'])) {
        try {
            $dateReady = new DateTime($crop['expected_harvest']) <= $today;
        } catch (Throwable $e) {
            $dateReady = false;
        }
    }
    if ($status === 'ready' || $dateReady) {
        $ready++;
    } elseif ($status !== 'harvested') {
        $growing++;
        if (!empty($crop['expected_harvest'])) {
            try {
                $harvestDate = new DateTime($crop['expected_harvest']);
                if ($harvestDate >= $today && $harvestDate <= $limit) $soon++;
            } catch (Throwable $e) {
                // Ignore malformed optional dates.
            }
        }
    }
}

$bedTotal = 0;
if ($plotId > 0) {
    $bedStmt = $conn->prepare('SELECT COUNT(*) AS n FROM plot_beds WHERE plot_id=?');
    $bedStmt->bind_param('i', $plotId);
    $bedStmt->execute();
    $bedTotal = (int)($bedStmt->get_result()->fetch_assoc()['n'] ?? 0);
    $bedStmt->close();
}
$occupied = [];
foreach ($rows as $crop) {
    if (($crop['bed_id'] ?? '') !== '' && $crop['bed_id'] !== null) $occupied[(string)$crop['bed_id']] = true;
}
$available = max(0, $bedTotal - count($occupied));
$name = $plotRow['plot_name'] ?? ucwords(str_replace('_', ' ', $plot));

// Keep the response contract used by the existing dashboard popup unchanged.
echo json_encode([
    'type' => 'crop',
    'plot_name' => $name,
    'plot_id' => $plotId,
    'plants_planted' => count($rows),
    'bed_available' => $available,
    'soon_to_harvest' => $soon,
    'ready_to_harvest' => $ready,
    'growing' => $growing,
    'crop_records' => $rows
]);
