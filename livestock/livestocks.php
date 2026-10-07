
<?php

session_start();

include '../db.php';
require_once '../archive/archive_functions.php';


// =========================================================
// LOGIN CHECK
// =========================================================

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login.php");

    exit();

}

$role = $_SESSION['role'] ?? 'staff';

/* Keep livestock archive physical: migrate any legacy soft-archived rows once. */
try { ensureArchiveTable($conn, 'livestocks'); } catch (Throwable $e) { /* page remains usable */ }

/* Ensure monitoring records support archiving even on an existing database. */
$monitoringTables = [
    'chicken_weight_records',
    'chicken_egg_records',
    'chicken_mortality_records',
    'chicken_feed_ingredients',
    'chicken_drinking_water',
    'chicken_feeding_schedule',
    'goat_monitoring_records',
    'goat_forage_records'
];

foreach ($monitoringTables as $monitoringTable) {
    try {
        $conn->query("ALTER TABLE `{$monitoringTable}` ADD COLUMN `archived` TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Throwable $e) {
        // Column already exists or table is unavailable; normal page loading continues.
    }
}

/* =========================================================
   LIVESTOCK MONITORING CRUD
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['monitoring_action'])) {

    if ($role !== 'admin') {
        http_response_code(403);
        exit('Unauthorized');
    }

    $action = $_POST['monitoring_action'];
    $livestockId = (int)($_POST['livestock_id'] ?? 0);
    $recordType = $_POST['record_type'] ?? '';

    if ($action === 'delete') {
        $recordId = (int)($_POST['record_id'] ?? 0);

        $deleteMap = [
            'chicken_weight' => 'chicken_weight_records',
            'chicken_eggs' => 'chicken_egg_records',
            'chicken_mortality' => 'chicken_mortality_records',
            'chicken_feed' => 'chicken_feed_ingredients',
            'chicken_water' => 'chicken_drinking_water',
            'chicken_feeding' => 'chicken_feeding_schedule',
            'goat_daily' => 'goat_monitoring_records',
            'goat_forage' => 'goat_forage_records'
        ];

        if ($recordId > 0 && isset($deleteMap[$recordType])) {
            $table = $deleteMap[$recordType];
            $stmt = $conn->prepare("DELETE FROM {$table} WHERE id = ? AND (livestock_id = ? OR livestock_id = 0)");
            $stmt->bind_param("ii", $recordId, $livestockId);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: livestocks.php?type=" . urlencode($_POST['return_type'] ?? '') . "&livestock_id=" . $livestockId);
        exit();
    }

    if ($action === 'archive') {
        $recordId = (int)($_POST['record_id'] ?? 0);

        $archiveMap = [
            'chicken_weight' => 'chicken_weight_records',
            'chicken_eggs' => 'chicken_egg_records',
            'chicken_mortality' => 'chicken_mortality_records',
            'chicken_feed' => 'chicken_feed_ingredients',
            'chicken_water' => 'chicken_drinking_water',
            'chicken_feeding' => 'chicken_feeding_schedule',
            'goat_daily' => 'goat_monitoring_records',
            'goat_forage' => 'goat_forage_records'
        ];

        if ($recordId > 0 && isset($archiveMap[$recordType])) {
            $table = $archiveMap[$recordType];
            archiveRecord($conn, $table, 'id', $recordId, (int)$_SESSION['user_id']);
        }

        header("Location: livestocks.php?type=" . urlencode($_POST['return_type'] ?? '') . "&livestock_id=" . $livestockId);
        exit();
    }

    if ($action === 'update') {
        $recordId = (int)($_POST['record_id'] ?? 0);

        $updateMap = [
            'chicken_weight' => [
                'table' => 'chicken_weight_records',
                'fields' => ['record_date','rooster_banaba','rooster_paraokan','hen_banaba','hen_paraokan','average_weight']
            ],
            'chicken_eggs' => [
                'table' => 'chicken_egg_records',
                'fields' => ['record_date','days','feed_formula','egg_count','lightest_weight','heaviest_weight','hatching']
            ],
            'chicken_mortality' => [
                'table' => 'chicken_mortality_records',
                'fields' => ['record_date','quantity','chicken_type','breed','cause_of_death']
            ],
            'chicken_feed' => [
                'table' => 'chicken_feed_ingredients',
                'fields' => ['formulation_code','feed_ingredients','quantity']
            ],
            'chicken_water' => [
                'table' => 'chicken_drinking_water',
                'fields' => ['record_date','water_code','number_of_waterers','ml_per_16_liter']
            ],
            'chicken_feeding' => [
                'table' => 'chicken_feeding_schedule',
                'fields' => ['month_year','feeding_code','morning','afternoon','snacks','evening','night_visit','eggs_collected','average_egg_weight','average_layer_weight','average_rooster_weight','housing_nest_disinfection','general_cleaning','chicken_bath']
            ],
            'goat_daily' => [
                'table' => 'goat_monitoring_records',
                'fields' => ['record_date','status','free_range_schedule','drink_type','disinfect_cleaning','notes']
            ],
            'goat_forage' => [
                'table' => 'goat_forage_records',
                'fields' => ['record_date','forage_name','quantity','notes']
            ]
        ];

        if ($recordId > 0 && isset($updateMap[$recordType])) {
            $cfg = $updateMap[$recordType];
            $sets = [];
            $values = [];
            foreach ($cfg['fields'] as $field) {
                $sets[] = "`{$field}` = ?";
                $values[] = $_POST[$field] ?? '';
            }

            // Records imported from the Excel sheets can have livestock_id = 0.
            // In that case, allow editing the selected record by ID; otherwise keep
            // the selected livestock ownership check.
            $sql = "UPDATE `{$cfg['table']}` SET " . implode(', ', $sets) . " WHERE id = ? AND (livestock_id = ? OR livestock_id = 0)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $types = str_repeat('s', count($values)) . 'ii';
                $values[] = $recordId;
                $values[] = $livestockId;
                $bind = [$types];
                foreach ($values as $k => $v) $bind[] = &$values[$k];
                call_user_func_array([$stmt, 'bind_param'], $bind);
                $stmt->execute();
                $stmt->close();
            }
        }

        header("Location: livestocks.php?type=" . urlencode($_POST['return_type'] ?? '') . "&livestock_id=" . $livestockId);
        exit();
    }

    if ($action === 'add' && $livestockId > 0) {

        if ($recordType === 'chicken_weight') {
            $stmt = $conn->prepare("INSERT INTO chicken_weight_records (livestock_id, record_date, rooster_banaba, rooster_paraokan, hen_banaba, hen_paraokan, average_weight) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", $livestockId, $_POST['record_date'], $_POST['rooster_banaba'], $_POST['rooster_paraokan'], $_POST['hen_banaba'], $_POST['hen_paraokan'], $_POST['average_weight']);
        } elseif ($recordType === 'chicken_eggs') {
            $stmt = $conn->prepare("INSERT INTO chicken_egg_records (livestock_id, record_date, days, feed_formula, egg_count, lightest_weight, heaviest_weight, hatching) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssss", $livestockId, $_POST['record_date'], $_POST['days'], $_POST['feed_formula'], $_POST['egg_count'], $_POST['lightest_weight'], $_POST['heaviest_weight'], $_POST['hatching']);
        } elseif ($recordType === 'chicken_mortality') {
            $stmt = $conn->prepare("INSERT INTO chicken_mortality_records (livestock_id, record_date, quantity, chicken_type, breed, cause_of_death) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isisss", $livestockId, $_POST['record_date'], $_POST['quantity'], $_POST['chicken_type'], $_POST['breed'], $_POST['cause_of_death']);
        } elseif ($recordType === 'chicken_feed') {
            $stmt = $conn->prepare("INSERT INTO chicken_feed_ingredients (livestock_id, formulation_code, feed_ingredients, quantity) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $livestockId, $_POST['formulation_code'], $_POST['feed_ingredients'], $_POST['quantity']);
        } elseif ($recordType === 'chicken_water') {
            $stmt = $conn->prepare("INSERT INTO chicken_drinking_water (livestock_id, record_date, water_code, number_of_waterers, ml_per_16_liter) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issis", $livestockId, $_POST['record_date'], $_POST['water_code'], $_POST['number_of_waterers'], $_POST['ml_per_16_liter']);
        } elseif ($recordType === 'chicken_feeding') {
            $stmt = $conn->prepare("INSERT INTO chicken_feeding_schedule (livestock_id, month_year, feeding_code, morning, afternoon, snacks, evening, night_visit, eggs_collected, average_egg_weight, average_layer_weight, average_rooster_weight, housing_nest_disinfection, general_cleaning, chicken_bath) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssssssssss", $livestockId, $_POST['month_year'], $_POST['feeding_code'], $_POST['morning'], $_POST['afternoon'], $_POST['snacks'], $_POST['evening'], $_POST['night_visit'], $_POST['eggs_collected'], $_POST['average_egg_weight'], $_POST['average_layer_weight'], $_POST['average_rooster_weight'], $_POST['housing_nest_disinfection'], $_POST['general_cleaning'], $_POST['chicken_bath']);
        } elseif ($recordType === 'goat_daily') {
            $stmt = $conn->prepare("INSERT INTO goat_monitoring_records (livestock_id, record_date, status, free_range_schedule, drink_type, disinfect_cleaning, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", $livestockId, $_POST['record_date'], $_POST['status'], $_POST['free_range_schedule'], $_POST['drink_type'], $_POST['disinfect_cleaning'], $_POST['notes']);
        } elseif ($recordType === 'goat_forage') {
            $stmt = $conn->prepare("INSERT INTO goat_forage_records (livestock_id, record_date, forage_name, quantity, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $livestockId, $_POST['record_date'], $_POST['forage_name'], $_POST['quantity'], $_POST['notes']);
        } else {
            $stmt = null;
        }

        if ($stmt) {
            $stmt->execute();
            $stmt->close();
        }

        header("Location: livestocks.php?type=" . urlencode($_POST['return_type'] ?? '') . "&livestock_id=" . $livestockId);
        exit();
    }
}



// =========================================================
// ARCHIVE LIVESTOCK
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_livestock_id'])) {
    if ($role !== 'admin') {
        http_response_code(403);
        exit('Unauthorized');
    }

    $archiveId = (int)$_POST['archive_livestock_id'];

    if ($archiveId > 0) {
        try {
            $conn->begin_transaction();

            /*
             * Physical archive: copy the current row into archived_livestocks,
             * then DELETE it from livestocks. If it was already copied by an
             * older archive attempt, remove the active copy instead of leaving
             * it visible in the active list.
             */
            $sourceCols = archiveColumns($conn, 'livestocks');
            $archiveTable = ensureArchiveTable($conn, 'livestocks', false);

            $check = $conn->prepare("SELECT COUNT(*) FROM `livestocks` WHERE `livestock_id` = ?");
            $check->bind_param('i', $archiveId);
            $check->execute();
            $check->bind_result($exists);
            $check->fetch();
            $check->close();

            if (!$exists) {
                throw new Exception('Livestock record was not found in the active records.');
            }

            $insertSql = "INSERT INTO `{$archiveTable}` (`" . implode('`,`', $sourceCols) . "`,`archived_at`,`archived_by`) SELECT `" . implode('`,`', $sourceCols) . "`,NOW(),? FROM `livestocks` WHERE `livestock_id`=? LIMIT 1";
            $ins = $conn->prepare($insertSql);
            if (!$ins) throw new Exception('Could not prepare livestock archive: ' . $conn->error);
            $uid = (int)$_SESSION['user_id'];
            $ins->bind_param('ii', $uid, $archiveId);
            if (!$ins->execute()) {
                /* If an older archive copy already exists, treat it as archived. */
                $duplicate = stripos($ins->error, 'duplicate') !== false;
                $err = $ins->error;
                $ins->close();
                if (!$duplicate) throw new Exception('Could not archive livestock: ' . $err);
            } else {
                $ins->close();
            }

            /* Always remove the active copy after a successful archive or a duplicate archive copy. */
            $del = $conn->prepare("DELETE FROM `livestocks` WHERE `livestock_id` = ? LIMIT 1");
            if (!$del) throw new Exception('Could not prepare active livestock removal: ' . $conn->error);
            $del->bind_param('i', $archiveId);
            if (!$del->execute()) {
                $err = $del->error;
                $del->close();
                throw new Exception('Could not remove active livestock: ' . $err);
            }
            $del->close();

            /* Archive all related monitoring records too. */
            $monitoringTables = [
                'chicken_weight_records','chicken_egg_records','chicken_mortality_records',
                'chicken_feed_ingredients','chicken_drinking_water','chicken_feeding_schedule',
                'goat_monitoring_records','goat_forage_records'
            ];
            foreach ($monitoringTables as $monitoringTable) {
                if (archiveTableExists($conn, $monitoringTable)) {
                    archiveTableRowsForLivestock($conn, $monitoringTable, $archiveId, $uid);
                }
            }

            $conn->commit();
            header("Location: livestocks.php?archived=1");
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            http_response_code(500);
            exit('Could not archive livestock: ' . htmlspecialchars($e->getMessage()));
        }
    }
}

// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['archive_livestock_id'])
) {

    // Only admin can archive livestock
    if ($role !== 'admin') {

        http_response_code(403);

        echo 'Unauthorized';

        exit();

    }


    $archiveId =
        intval($_POST['archive_livestock_id']);


    if ($archiveId > 0) {
        $conn->begin_transaction();
        try {
            archiveRecordInTransaction($conn, 'livestocks', 'livestock_id', $archiveId, (int)$_SESSION['user_id']);
            $monitoringTables = [
                'chicken_weight_records','chicken_egg_records','chicken_mortality_records',
                'chicken_feed_ingredients','chicken_drinking_water','chicken_feeding_schedule',
                'goat_monitoring_records','goat_forage_records'
            ];
            foreach ($monitoringTables as $monitoringTable) {
                if (archiveTableExists($conn, $monitoringTable)) {
                    archiveTableRowsForLivestock($conn, $monitoringTable, $archiveId, (int)$_SESSION['user_id']);
                }
            }
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            http_response_code(500);
            exit('Could not archive livestock: ' . htmlspecialchars($e->getMessage()));
        }
    }


    // Return to active livestock records
    header("Location: livestocks.php");

    exit();

}


// =========================================================
// GET SELECTED TYPE
// =========================================================

$selectedType = $_GET['type'] ?? '';


// =========================================================
// GET SELECTED LIVESTOCK
// =========================================================

$selectedLivestockId =
    isset($_GET['livestock_id'])
        ? intval($_GET['livestock_id'])
        : 0;


// =========================================================
// GET LIVESTOCK TYPES
// =========================================================

$typeResult = $conn->query("
    SELECT DISTINCT type
    FROM livestocks
    WHERE archived = 0
      AND type IS NOT NULL
      AND TRIM(type) <> ''
    ORDER BY type ASC
");

/* Always show the standard livestock types, even when there are currently
   no active records of that type. */
$livestockTypes = ['Chicken', 'Goat', 'Pig', 'Rabbit'];

if ($typeResult) {
    while ($typeRow = $typeResult->fetch_assoc()) {
        $dbType = trim((string)($typeRow['type'] ?? ''));
        if ($dbType === '') {
            continue;
        }

        $alreadyExists = false;
        foreach ($livestockTypes as $existingType) {
            if (strcasecmp($existingType, $dbType) === 0) {
                $alreadyExists = true;
                break;
            }
        }

        if (!$alreadyExists) {
            $livestockTypes[] = $dbType;
        }
    }
}

$preferredOrder = ['Chicken', 'Goat', 'Pig', 'Rabbit'];
usort($livestockTypes, function ($a, $b) use ($preferredOrder) {
    $preferred = array_map('strtolower', $preferredOrder);
    $ia = array_search(strtolower($a), $preferred, true);
    $ib = array_search(strtolower($b), $preferred, true);
    $ia = ($ia === false) ? PHP_INT_MAX : $ia;
    $ib = ($ib === false) ? PHP_INT_MAX : $ib;

    return $ia === $ib ? strcasecmp($a, $b) : ($ia <=> $ib);
});


// =========================================================
// GET LIVESTOCK NAMES
// =========================================================

/* Include the separate pig_records table in the existing livestock selector.
   Pig page IDs are negative so they cannot collide with livestocks.livestock_id. */
$baseLivestockSql = "
    SELECT CAST(livestock_id AS SIGNED) AS page_id, livestock_id, name, type
    FROM livestocks
    WHERE archived = 0
";

$pigSql = "
    SELECT (0 - CAST(pig_id AS SIGNED)) AS page_id, NULL AS livestock_id, name, 'Pig' AS type
    FROM pig_records
";

if ($selectedType !== '') {

    if (strcasecmp(trim($selectedType), 'Pig') === 0) {
        $nameResult = $conn->query($pigSql . " ORDER BY name ASC");
    } else {
        $stmtType = $conn->prepare($baseLivestockSql . "
            AND LOWER(type) = LOWER(?)
            ORDER BY name ASC
        ");
        $stmtType->bind_param("s", $selectedType);
        $stmtType->execute();
        $nameResult = $stmtType->get_result();
    }

} else {

    $nameSql = "
        (" . $baseLivestockSql . ")
        UNION ALL
        (" . $pigSql . ")
        ORDER BY name ASC
    ";
    $nameResult = $conn->query($nameSql);

}


// =========================================================
// AUTOMATICALLY SELECT FIRST LIVESTOCK
// =========================================================

if (
    $selectedLivestockId === 0
    &&
    $nameResult
    &&
    $nameResult->num_rows > 0
) {

    $firstLivestock =
        $nameResult->fetch_assoc();


    // Use page_id because Pig records do not have a livestocks.livestock_id.
    // Pig page IDs are negative; regular livestock IDs remain positive.
    $selectedLivestockId =
        intval(
            $firstLivestock['page_id']
        );


    $nameResult->data_seek(0);

}


// =========================================================
// GET SELECTED LIVESTOCK PROFILE
// =========================================================

$livestockProfile = null;

if ($selectedLivestockId < 0) {

    $pigId = abs($selectedLivestockId);
    $stmt = $conn->prepare("SELECT * FROM pig_records WHERE pig_id = ? LIMIT 1");
    $stmt->bind_param("i", $pigId);
    $stmt->execute();
    $profileResult = $stmt->get_result();

    if ($profileResult && $profileResult->num_rows > 0) {
        $pig = $profileResult->fetch_assoc();
        $ageText = '-';

        if (!empty($pig['date_of_birth'])) {
            try {
                $dob = new DateTime($pig['date_of_birth']);
                $today = new DateTime('today');
                $age = $dob->diff($today);
                $ageText = $age->y . ' yr' . ($age->y === 1 ? '' : 's');
                if ($age->m > 0) {
                    $ageText .= ' ' . $age->m . ' mo' . ($age->m === 1 ? '' : 's');
                }
            } catch (Throwable $e) {
                $ageText = '-';
            }
        }

        $livestockProfile = [
            'livestock_id' => $selectedLivestockId,
            'pig_id' => $pig['pig_id'],
            'tag_number' => $pig['tag_number'] ?? '',
            'name' => $pig['name'] ?? '-',
            'type' => 'Pig',
            'sex' => $pig['sex'] ?? '-',
            'breed' => $pig['breed'] ?? '-',
            'date_of_birth' => $pig['date_of_birth'] ?? null,
            'age' => $ageText,
            'estimated_weight' => $pig['weight'] ?? null,
            'weight_unit' => $pig['weight_unit'] ?? 'kg',
            'purpose' => $pig['purpose'] ?? '-',
            'health_status' => $pig['health_status'] ?? 'Healthy',
            'notes' => $pig['notes'] ?? '',
            'for_sale' => '0'
        ];
    }
    $stmt->close();

} elseif ($selectedLivestockId > 0) {

    $stmt = $conn->prepare("
        SELECT *
        FROM livestocks
        WHERE livestock_id = ?
        AND archived = 0
        LIMIT 1
    ");
    $stmt->bind_param("i", $selectedLivestockId);
    $stmt->execute();
    $profileResult = $stmt->get_result();

    if ($profileResult && $profileResult->num_rows > 0) {
        $livestockProfile = $profileResult->fetch_assoc();
    }
    $stmt->close();
}


// =========================================================
// GET ALL ACTIVE LIVESTOCK
// =========================================================

$livestockResult = $conn->query("
    SELECT *
    FROM livestocks
    WHERE archived = 0
    ORDER BY livestock_id DESC
");

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Livestock Records
</title>


<!-- =====================================================
     MAIN CSS
====================================================== -->

<link
    rel="stylesheet"
    href="../style.css"
>


<!-- =====================================================
     SIDEBAR CSS
====================================================== -->

<link
    rel="stylesheet"
    href="../sidebar.css"
>


<!-- =====================================================
     LIVESTOCK CSS
====================================================== -->

<link
    rel="stylesheet"
    href="livestocks.css?v=4"
>


<!-- =====================================================
     GOOGLE FONT
====================================================== -->

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
    rel="stylesheet"
>


<!-- =====================================================
     MATERIAL ICONS
====================================================== -->

<link
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:wght@400"
    rel="stylesheet"
>



<!-- Monitoring page CSS -->
<style>
.livestock-monitoring-section{width:100%;margin-top:4px;margin-bottom:40px}
.monitoring-heading{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.monitoring-heading h2{margin:0;color:#2f6b2f;font-size:1.2rem;font-weight:700}
.monitoring-heading p{margin:3px 0 0;color:#777;font-size:.82rem}
.monitoring-add-btn{border:0;border-radius:8px;background:#2f6b2f;color:#fff;padding:9px 14px;font-family:Poppins,sans-serif;font-size:.75rem;font-weight:600;display:flex;gap:6px;align-items:center;cursor:pointer}
.monitoring-tabs{display:flex;gap:7px;overflow-x:auto;padding-bottom:8px}
.monitoring-tab{border:1px solid #d8e2d3;background:#fff;color:#55704f;border-radius:8px;padding:9px 13px;font-family:Poppins,sans-serif;font-size:.72rem;font-weight:600;white-space:nowrap;cursor:pointer}
.monitoring-tab.active{background:#4f7d3a;color:#fff;border-color:#4f7d3a}
.monitoring-table-card{background:#fff;border-radius:10px;box-shadow:0 2px 7px rgba(0,0,0,.06);overflow:hidden}
.monitoring-panel{display:none}
.monitoring-panel.active{display:block}
.monitoring-panel-title{padding:16px 18px;border-bottom:1px solid #edf1eb;display:flex;align-items:center;justify-content:space-between}
.monitoring-panel-title h3{margin:0;color:#2f6b2f;font-size:1.02rem}
.monitoring-panel-title span{display:block;margin-top:3px;color:#888;font-size:.78rem}
.monitoring-scroll{width:100%;overflow-x:auto}
.monitoring-table{width:100%;border-collapse:collapse;min-width:700px;font-family:Poppins,sans-serif}
.monitoring-table.wide{min-width:1450px}
.monitoring-table th{background:#f4f7f2;color:#4b6246;font-size:.78rem;font-weight:700;padding:12px 13px;text-align:left;white-space:nowrap}
.monitoring-table td{padding:12px 13px;border-top:1px solid #eef1ed;color:#555;font-size:.76rem;vertical-align:top}
.monitoring-number{width:54px;text-align:center!important;font-weight:700;color:#496442!important}
.monitoring-tools{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:14px 18px;background:#fbfcfa;border-bottom:1px solid #edf1eb}
.monitoring-search-wrap{position:relative;flex:1;min-width:220px}
.monitoring-search-wrap .material-symbols-rounded{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:20px;color:#8a9885}
.monitoring-search{width:100%;box-sizing:border-box;border:1px solid #d8e2d3;border-radius:9px;padding:10px 12px 10px 40px;font-family:Poppins,sans-serif;font-size:.78rem;background:#fff;outline:none}
.monitoring-month{border:1px solid #d8e2d3;border-radius:9px;padding:10px 34px 10px 12px;font-family:Poppins,sans-serif;font-size:.78rem;background:#fff;color:#4b6246;min-width:165px;outline:none}
.monitoring-count{font-size:.76rem;font-weight:600;color:#64705f;white-space:nowrap}
.monitor-actions{display:flex;gap:6px;align-items:center}
.monitor-action-cell{white-space:nowrap}.monitor-action-cell .monitor-action{margin-right:4px}.monitor-action-cell .monitor-action:last-child{margin-right:0}
.monitor-action{width:34px;height:34px;border:0;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-family:Poppins,sans-serif;transition:.15s}
.monitor-action .material-symbols-rounded{font-size:19px}
.monitor-action.edit{background:#eef3ff;color:#4669a8}.monitor-action.delete{background:#fff0f0;color:#b44848}
.monitor-action.archive{background:#eef6eb;color:#4d743f}
.monitor-action:hover{transform:translateY(-1px);filter:brightness(.97)}
.monitoring-table tbody tr:hover{background:#fafcf9}
.monitor-empty{text-align:center!important;padding:30px!important;color:#999!important}
.monitor-delete{border:0;background:#fff0f0;color:#b44848;border-radius:6px;padding:5px 8px;font-family:Poppins,sans-serif;font-size:.62rem;cursor:pointer}
.monitoring-empty-state{text-align:center;background:#fff;border-radius:10px;padding:45px 20px;color:#888;box-shadow:0 2px 7px rgba(0,0,0,.05)}
.monitoring-empty-state .material-symbols-rounded{font-size:38px;color:#7b9871}
.monitoring-empty-state h3{margin:8px 0 5px;color:#45643f;font-size:.95rem}
.monitoring-empty-state p{margin:0 auto;max-width:620px;font-size:.72rem;line-height:1.6}
.monitoring-modal-overlay{display:none;position:fixed;inset:0;background:rgba(20,35,20,.48);z-index:9999;align-items:center;justify-content:center;padding:20px}
.monitoring-modal-overlay.active{display:flex}
.monitoring-modal{width:min(720px,100%);max-height:90vh;overflow-y:auto;background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.monitoring-modal-header{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #eee}
.monitoring-modal-header h3{margin:0;color:#2f6b2f;font-size:1rem}
.monitoring-modal-header span{font-size:.68rem;color:#888}
.monitoring-close{border:0;width:34px;height:34px;border-radius:50%;background:#f1f3f0;color:#55704f;cursor:pointer}
#monitoringForm{padding:18px 20px}
.monitor-form-group{margin-bottom:13px}
.monitor-form-group label,.monitor-field label{display:block;margin-bottom:5px;font-size:.7rem;font-weight:600;color:#45643f}
.monitor-form-group select,.monitor-field input,.monitor-field textarea,.monitor-field select{width:100%;box-sizing:border-box;border:1px solid #d9e1d6;border-radius:7px;padding:9px 10px;font-family:Poppins,sans-serif;font-size:.72rem;outline:none;background:#fff}
.monitor-fields-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
.monitor-field.full{grid-column:1/-1}
.monitor-field textarea{min-height:80px;resize:vertical}
.monitor-form-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:18px}
.monitor-cancel,.monitor-save{border:0;border-radius:7px;padding:9px 16px;font-family:Poppins,sans-serif;font-size:.72rem;font-weight:600;cursor:pointer}
.monitor-cancel{background:#eee;color:#555}.monitor-save{background:#2f6b2f;color:#fff}
@media(max-width:650px){.monitoring-heading{align-items:flex-start;gap:10px;flex-direction:column}.monitoring-add-btn{width:100%;justify-content:center}.monitor-fields-grid{grid-template-columns:1fr}.monitor-field.full{grid-column:auto}}
</style>
</head>


<body>


<div class="layout">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <?php include '../sidebar.php'; ?>


    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="dashboard">


        <!-- =================================================
             TOP RIGHT BAR
        ================================================== -->

        <?php include '../topRightBar.php'; ?>


        <!-- =================================================
             PAGE HEADER
        ================================================== -->

        <div class="livestock-page-header">

            <h1>
                LIVESTOCK RECORDS
            </h1>

        </div>


        <!-- =================================================
             TOP CONTROLS
        ================================================== -->

        <div class="livestock-top-controls">


            <!-- =================================================
                 TYPE
            ================================================== -->

            <select
                class="livestock-type-select"
                onchange="changeLivestockType(this.value)"
            >

                <option value="">
                    All Livestock
                </option>


                <?php foreach ($livestockTypes as $livestockType): ?>

                    <option
                        value="<?= htmlspecialchars($livestockType, ENT_QUOTES) ?>"
                        <?= strtolower($selectedType) === strtolower($livestockType) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($livestockType) ?>
                    </option>

                <?php endforeach; ?>

            </select>


            <!-- =================================================
                 LIVESTOCK NAME
            ================================================== -->

            <select
                class="livestock-name-select"
                onchange="changeLivestock(this.value)"
            >

                <?php if (
                    $nameResult
                    &&
                    $nameResult->num_rows > 0
                ): ?>

                    <?php while (
                        $nameRow =
                        $nameResult->fetch_assoc()
                    ): ?>

                        <option
                            value="<?= intval(
                                $nameRow['page_id']
                            ) ?>"
                            <?= intval(
                                $nameRow['page_id']
                            ) === $selectedLivestockId
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $nameRow['name']
                            ) ?>

                        </option>

                    <?php endwhile; ?>

                <?php else: ?>

                    <option value="">
                        No livestock found
                    </option>

                <?php endif; ?>

            </select>


            <!-- =================================================
                 RIGHT SIDE
            ================================================== -->

            <div class="livestock-header-right">


                <!-- SEARCH -->

                <div class="livestock-search">

                    <span class="material-symbols-rounded">
                        search
                    </span>

                    <input
                        type="text"
                        id="livestockSearch"
                        placeholder="Search here..."
                        onkeyup="searchLivestock()"
                    >

                </div>


                <!-- ARCHIVED -->

                <?php if ($role === 'admin'): ?>

                    <button
                        type="button"
                        class="archived-livestock-btn"
                        onclick="location.href='archived_livestocks.php'"
                    >

                        View Archived Livestock

                        <span class="material-symbols-rounded">
                            chevron_right
                        </span>

                    </button>

                <?php endif; ?>


            </div>

        </div>


        <!-- =================================================
             PROFILE
        ================================================== -->

        <?php if ($livestockProfile): ?>

            <div class="livestock-profile-card">


                <!-- PROFILE HEADER -->

                <div class="profile-header">

                    <h2>
                        PROFILE
                    </h2>


                    <?php if ($role === 'admin' && ($livestockProfile['type'] ?? '') !== 'Pig'): ?>

                        <button
                            type="button"
                            class="edit-profile-btn"
                            onclick="openEditModal(
                                'edit_livestock.php?id=<?= intval(
                                    $livestockProfile['livestock_id']
                                ) ?>',
                                'Edit Livestock Profile'
                            )"
                        >

                            <span class="material-symbols-rounded">
                                edit
                            </span>

                            Edit Profile

                        </button>

                    <?php endif; ?>

                </div>


                <!-- PROFILE INFORMATION -->

                <div class="profile-grid">


                    <!-- DOB -->

                    <div class="profile-item">

                        <span class="profile-label">
                            Date of Birth
                        </span>

                        <strong>

                            <?php

                            if (
                                !empty(
                                    $livestockProfile['date_of_birth']
                                )
                            ) {

                                echo date(
                                    'F d, Y',
                                    strtotime(
                                        $livestockProfile['date_of_birth']
                                    )
                                );

                            } else {

                                echo '-';

                            }

                            ?>

                        </strong>

                    </div>


                    <!-- NAME -->

                    <div class="profile-item">

                        <span class="profile-label">
                            Name
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $livestockProfile['name']
                                ?? '-'
                            ) ?>

                        </strong>

                    </div>


                    <!-- BREED -->

                    <div class="profile-item">

                        <span class="profile-label">
                            Breed
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $livestockProfile['breed']
                                ?? '-'
                            ) ?>

                        </strong>

                    </div>


                    <!-- AGE -->

                    <div class="profile-item">

                        <span class="profile-label">
                            Age
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $livestockProfile['age']
                                ?? '-'
                            ) ?>

                        </strong>

                    </div>


                    <!-- WEIGHT -->

                    <div class="profile-item">

                        <span class="profile-label">
                            Estimated Weight
                        </span>

                        <strong>

                            <?php

                            if (
                                isset(
                                    $livestockProfile[
                                        'estimated_weight'
                                    ]
                                )
                                &&
                                $livestockProfile[
                                    'estimated_weight'
                                ] !== ''
                            ) {

                                echo htmlspecialchars(
                                    $livestockProfile[
                                        'estimated_weight'
                                    ]
                                );

                                echo ' kg';

                            } else {

                                echo '-';

                            }

                            ?>

                        </strong>

                    </div>


                    <!-- FOR SALE -->

                    <div class="profile-item">

                        <span class="profile-label">
                            For sale
                        </span>

                        <strong>

                            <?php

                            if (
                                isset(
                                    $livestockProfile[
                                        'for_sale'
                                    ]
                                )
                                &&
                                (
                                    $livestockProfile[
                                        'for_sale'
                                    ] === '1'
                                    ||
                                    strtolower(
                                        $livestockProfile[
                                            'for_sale'
                                        ]
                                    ) === 'yes'
                                )
                            ) {

                                echo 'Yes';

                            } else {

                                echo '-';

                            }

                            ?>

                        </strong>

                    </div>


                </div>

            </div>


        <?php else: ?>


            <div class="empty-profile">

                <span class="material-symbols-rounded">
                    pets
                </span>

                <p>
                    No livestock profile available.
                </p>

            </div>


        <?php endif; ?>


        <!-- =================================================
             MONITORING RECORDS
             EXISTING PROFILE ABOVE IS PRESERVED UNCHANGED
        ================================================== -->

        <div class="livestock-monitoring-section">

            <div class="monitoring-heading">
                <div>
                    <h2>MONITORING RECORDS</h2>
                    <p>
                        Monitoring sheets for the selected livestock.
                    </p>
                </div>

                <?php if ($role === 'admin' && $selectedLivestockId > 0): ?>
                    <button type="button"
                            class="monitoring-add-btn"
                            onclick="openMonitoringModal()">
                        <span class="material-symbols-rounded">add</span>
                        Add Record
                    </button>
                <?php endif; ?>
            </div>

            <?php
                $profileType = strtolower(trim($livestockProfile['type'] ?? $selectedType ?? ''));
                $isChicken = in_array($profileType, ['chicken', 'poultry', 'poultry (chicken)', 'poultry/chicken'], true);
                $isGoat = ($profileType === 'goat');

                $monitoringTabs = [];

                if ($isChicken) {
                    $monitoringTabs = [
                        'weight' => 'Weight',
                        'eggs' => 'Eggs',
                        'mortality' => 'Mortality',
                        'feed' => 'Feed Ingredients',
                        'water' => 'Drinking Water / DW',
                        'feeding' => 'Feeding Schedule'
                    ];
                } elseif ($isGoat) {
                    $monitoringTabs = [
                        'daily' => 'Daily Monitoring',
                        'forage' => 'Forages'
                    ];
                }
            ?>

            <?php if (!empty($monitoringTabs)): ?>

                <div class="monitoring-tabs">
                    <?php $firstTab = array_key_first($monitoringTabs); ?>
                    <?php foreach ($monitoringTabs as $tabKey => $tabLabel): ?>
                        <button type="button"
                                class="monitoring-tab <?= $tabKey === $firstTab ? 'active' : '' ?>"
                                data-monitoring-tab="<?= htmlspecialchars($tabKey, ENT_QUOTES) ?>"
                                onclick="switchMonitoringTab('<?= htmlspecialchars($tabKey, ENT_QUOTES) ?>')">
                            <?= htmlspecialchars($tabLabel) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="monitoring-table-card">

                    <?php if ($isChicken): ?>

                        <!-- CHICKEN WEIGHT -->
                        <div class="monitoring-panel <?= $firstTab === 'weight' ? 'active' : '' ?>" data-monitoring-panel="weight">
                            <div class="monitoring-panel-title">
                                <div>
                                    <h3>Chicken Weight Monitoring</h3>
                                    <span>Record the weights of the chicken groups shown in the monitoring sheet.</span>
                                </div>
                            </div>
                            <div class="monitoring-tools">
                                <div class="monitoring-search-wrap"><span class="material-symbols-rounded">search</span><input class="monitoring-search" type="search" placeholder="Search records..." oninput="filterMonitoringPanel(this)"></div>
                                <select class="monitoring-month" onchange="filterMonitoringPanel(this)"><option value="">All Months</option></select>
                                <span class="monitoring-count" data-monitor-count>0 records</span>
                            </div>

                            <?php
                            $weightRows = [];
                            if ($selectedLivestockId > 0) {
                                $stmt = $conn->prepare("SELECT * FROM chicken_weight_records WHERE livestock_id = ? AND archived = 0 ORDER BY record_date DESC");
                                $stmt->bind_param("i", $selectedLivestockId);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                while ($r = $res->fetch_assoc()) $weightRows[] = $r;
                                $stmt->close();
                            }
                            ?>

                            <div class="monitoring-scroll">
                                <table class="monitoring-table">
                                    <thead><tr>
                                        <th>Date</th><th>Rooster Banaba</th><th>Rooster Paraokan</th>
                                        <th>Hen Banaba</th><th>Hen Paraokan</th><th>Average</th>
                                        <?php if ($role === 'admin'): ?><th>Action</th><?php endif; ?>
                                    </tr></thead>
                                    <tbody>
                                                                        <?php if ($weightRows): foreach ($weightRows as $r): ?>                                        <tr data-record-row="1" data-search="<?= htmlspecialchars(strtolower(implode(' ', array_map('strval', $r))), ENT_QUOTES) ?>" data-month="<?= htmlspecialchars(substr((string)($r['record_date'] ?? $r['month_year'] ?? ''),0,7), ENT_QUOTES) ?>" data-month-label="<?= htmlspecialchars((string)($r['record_date'] ?? $r['month_year'] ?? ''), ENT_QUOTES) ?>">
                                            <td><?= htmlspecialchars($r['record_date']) ?></td>
                                            <td><?= htmlspecialchars($r['rooster_banaba'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['rooster_paraokan'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['hen_banaba'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['hen_paraokan'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['average_weight'] ?? '-') ?></td>
                                            <?php if ($role === 'admin'): ?>
                                                <td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("chicken_weight", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('chicken_weight', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="<?= $role === 'admin' ? 7 : 6 ?>" class="monitor-empty">No weight records yet.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- CHICKEN EGGS -->
                        <div class="monitoring-panel" data-monitoring-panel="eggs">
                            <div class="monitoring-panel-title">
                                <div><h3>Egg Production</h3><span>Based on the Chicken Monitoring Sheet.</span></div>
                            </div>
                            <div class="monitoring-tools"><div class="monitoring-search-wrap"><span class="material-symbols-rounded">search</span><input class="monitoring-search" type="search" placeholder="Search records..." oninput="filterMonitoringPanel(this)"></div><select class="monitoring-month" onchange="filterMonitoringPanel(this)"><option value="">All Months</option></select><span class="monitoring-count" data-monitor-count>0 records</span></div>
                            <?php
                            $eggRows = [];
                            if ($selectedLivestockId > 0) {
                                $stmt = $conn->prepare("SELECT * FROM chicken_egg_records WHERE (livestock_id = ? OR livestock_id = 0) AND archived = 0 ORDER BY record_date DESC");
                                $stmt->bind_param("i", $selectedLivestockId); $stmt->execute(); $res = $stmt->get_result();
                                while ($r = $res->fetch_assoc()) $eggRows[] = $r; $stmt->close();
                            }
                            ?>
                            <div class="monitoring-scroll">
                                <table class="monitoring-table">
                                    <thead><tr>
                                        <th>Date</th><th>Days</th><th>Feed Formula</th><th># of Eggs</th>
                                        <th>Lightest Weight</th><th>Heaviest Weight</th><th>Hatching</th>
                                        <?php if ($role === 'admin'): ?><th>Action</th><?php endif; ?>
                                    </tr></thead>
                                    <tbody>
                                                                        <?php if ($eggRows): foreach ($eggRows as $r): ?>                                        <tr data-record-row="1" data-search="<?= htmlspecialchars(strtolower(implode(' ', array_map('strval', $r))), ENT_QUOTES) ?>" data-month="<?= htmlspecialchars(substr((string)($r['record_date'] ?? $r['month_year'] ?? ''),0,7), ENT_QUOTES) ?>" data-month-label="<?= htmlspecialchars((string)($r['record_date'] ?? $r['month_year'] ?? ''), ENT_QUOTES) ?>">
                                            <td><?= htmlspecialchars($r['record_date']) ?></td><td><?= htmlspecialchars($r['days'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['feed_formula'] ?? '-') ?></td><td><?= htmlspecialchars($r['egg_count'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['lightest_weight'] ?? '-') ?></td><td><?= htmlspecialchars($r['heaviest_weight'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['hatching'] ?? '-') ?></td>
                                            <?php if ($role === 'admin'): ?><td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("chicken_eggs", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('chicken_eggs', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td><?php endif; ?>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="<?= $role === 'admin' ? 8 : 7 ?>" class="monitor-empty">No egg records yet.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- CHICKEN MORTALITY -->
                        <div class="monitoring-panel" data-monitoring-panel="mortality">
                            <div class="monitoring-panel-title">
                                <div><h3>Mortality</h3><span>Record mortality according to the monitoring sheet.</span></div>
                            </div>
                            <div class="monitoring-tools"><div class="monitoring-search-wrap"><span class="material-symbols-rounded">search</span><input class="monitoring-search" type="search" placeholder="Search records..." oninput="filterMonitoringPanel(this)"></div><select class="monitoring-month" onchange="filterMonitoringPanel(this)"><option value="">All Months</option></select><span class="monitoring-count" data-monitor-count>0 records</span></div>
                            <?php
                            $mortalityRows = [];
                            if ($selectedLivestockId > 0) {
                                $stmt = $conn->prepare("SELECT * FROM chicken_mortality_records WHERE (livestock_id = ? OR livestock_id = 0) AND archived = 0 ORDER BY record_date DESC");
                                $stmt->bind_param("i", $selectedLivestockId); $stmt->execute(); $res = $stmt->get_result();
                                while ($r = $res->fetch_assoc()) $mortalityRows[] = $r; $stmt->close();
                            }
                            ?>
                            <div class="monitoring-scroll">
                                <table class="monitoring-table">
                                    <thead><tr><th>Date</th><th>No.</th><th>Type Chicken</th><th>Breed</th><th>Cause of Death</th><?php if ($role === 'admin'): ?><th>Action</th><?php endif; ?></tr></thead>
                                    <tbody>
                                                                        <?php if ($mortalityRows): foreach ($mortalityRows as $r): ?>                                        <tr data-record-row="1" data-search="<?= htmlspecialchars(strtolower(implode(' ', array_map('strval', $r))), ENT_QUOTES) ?>" data-month="<?= htmlspecialchars(substr((string)($r['record_date'] ?? $r['month_year'] ?? ''),0,7), ENT_QUOTES) ?>" data-month-label="<?= htmlspecialchars((string)($r['record_date'] ?? $r['month_year'] ?? ''), ENT_QUOTES) ?>">
                                            <td><?= htmlspecialchars($r['record_date']) ?></td><td><?= htmlspecialchars($r['quantity'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['chicken_type'] ?? '-') ?></td><td><?= htmlspecialchars($r['breed'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['cause_of_death'] ?? '-') ?></td>
                                            <?php if ($role === 'admin'): ?><td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("chicken_mortality", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('chicken_mortality', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td><?php endif; ?>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="<?= $role === 'admin' ? 6 : 5 ?>" class="monitor-empty">No mortality records yet.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- CHICKEN FEED -->
                        <div class="monitoring-panel" data-monitoring-panel="feed">
                            <div class="monitoring-panel-title">
                                <div><h3>Feed Ingredients</h3><span>Record formulation codes and feed ingredients.</span></div>
                            </div>
                            <div class="monitoring-tools"><div class="monitoring-search-wrap"><span class="material-symbols-rounded">search</span><input class="monitoring-search" type="search" placeholder="Search records..." oninput="filterMonitoringPanel(this)"></div><select class="monitoring-month" onchange="filterMonitoringPanel(this)"><option value="">All Months</option></select><span class="monitoring-count" data-monitor-count>0 records</span></div>
                            <?php
                            $feedRows = [];
                            if ($selectedLivestockId > 0) {
                                $stmt = $conn->prepare("SELECT * FROM chicken_feed_ingredients WHERE (livestock_id = ? OR livestock_id = 0) AND archived = 0 ORDER BY created_at DESC");
                                $stmt->bind_param("i", $selectedLivestockId); $stmt->execute(); $res = $stmt->get_result();
                                while ($r = $res->fetch_assoc()) $feedRows[] = $r; $stmt->close();
                            }
                            ?>
                            <div class="monitoring-scroll">
                                <table class="monitoring-table">
                                    <thead><tr><th>Formulation Code</th><th>Feed Ingredients</th><th>Quantity</th><?php if ($role === 'admin'): ?><th>Action</th><?php endif; ?></tr></thead>
                                    <tbody>
                                                                        <?php if ($feedRows): foreach ($feedRows as $r): ?>                                        <tr><td><?= htmlspecialchars($r['formulation_code'] ?? '-') ?></td><td><?= htmlspecialchars($r['feed_ingredients'] ?? '-') ?></td><td><?= htmlspecialchars($r['quantity'] ?? '-') ?></td>
                                        <?php if ($role === 'admin'): ?><td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("chicken_feed", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('chicken_feed', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td><?php endif; ?></tr>
                                    <?php endforeach; else: ?><tr><td colspan="<?= $role === 'admin' ? 4 : 3 ?>" class="monitor-empty">No feed ingredient records yet.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- CHICKEN WATER -->
                        <div class="monitoring-panel" data-monitoring-panel="water">
                            <div class="monitoring-panel-title">
                                <div><h3>Drinking Water / DW</h3><span>Water Code: PW = Pure Water MW = MOLASSES & WATER FW = FFJ & WATER SW = SALT & WATER OW = OREGANO & WATER GW = Garlic & Water OH = OTHER HERBS     </span></div>
                            </div>
                            <div class="monitoring-tools"><div class="monitoring-search-wrap"><span class="material-symbols-rounded">search</span><input class="monitoring-search" type="search" placeholder="Search records..." oninput="filterMonitoringPanel(this)"></div><select class="monitoring-month" onchange="filterMonitoringPanel(this)"><option value="">All Months</option></select><span class="monitoring-count" data-monitor-count>0 records</span></div>
                            <?php
                            $waterRows = [];
                            if ($selectedLivestockId > 0) {
                                $stmt = $conn->prepare("SELECT * FROM chicken_drinking_water WHERE (livestock_id = ? OR livestock_id = 0) AND archived = 0 ORDER BY record_date DESC");
                                $stmt->bind_param("i", $selectedLivestockId); $stmt->execute(); $res = $stmt->get_result();
                                while ($r = $res->fetch_assoc()) $waterRows[] = $r; $stmt->close();
                            }
                            ?>
                            <div class="monitoring-scroll">
                                <table class="monitoring-table">
                                    <thead><tr><th>Date</th><th>Water Code</th><th>Number of Waterers</th><th>ML per 16-liter Water</th><?php if ($role === 'admin'): ?><th>Action</th><?php endif; ?></tr></thead>
                                    <tbody>
                                                                        <?php if ($waterRows): foreach ($waterRows as $r): ?>                                        <tr data-record-row="1" data-search="<?= htmlspecialchars(strtolower(implode(' ', array_map('strval', $r))), ENT_QUOTES) ?>" data-month="<?= htmlspecialchars(substr((string)($r['record_date'] ?? $r['month_year'] ?? ''),0,7), ENT_QUOTES) ?>" data-month-label="<?= htmlspecialchars((string)($r['record_date'] ?? $r['month_year'] ?? ''), ENT_QUOTES) ?>"><td><?= htmlspecialchars($r['record_date']) ?></td><td><?= htmlspecialchars($r['water_code'] ?? '-') ?></td><td><?= htmlspecialchars($r['number_of_waterers'] ?? '-') ?></td><td><?= htmlspecialchars($r['ml_per_16_liter'] ?? '-') ?></td>
                                        <?php if ($role === 'admin'): ?><td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("chicken_water", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('chicken_water', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td><?php endif; ?></tr>
                                    <?php endforeach; else: ?><tr><td colspan="<?= $role === 'admin' ? 5 : 4 ?>" class="monitor-empty">No drinking-water records yet.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- CHICKEN FEEDING SCHEDULE -->
                        <div class="monitoring-panel" data-monitoring-panel="feeding">
                            <div class="monitoring-panel-title">
                                <div><h3>Feeding Schedule</h3><span>Monthly feeding and sanitation monitoring.</span></div>
                            </div>
                            <div class="monitoring-tools"><div class="monitoring-search-wrap"><span class="material-symbols-rounded">search</span><input class="monitoring-search" type="search" placeholder="Search records..." oninput="filterMonitoringPanel(this)"></div><select class="monitoring-month" onchange="filterMonitoringPanel(this)"><option value="">All Months</option></select><span class="monitoring-count" data-monitor-count>0 records</span></div>
                            <?php
                            $feedingRows = [];
                            if ($selectedLivestockId > 0) {
                                $stmt = $conn->prepare("SELECT * FROM chicken_feeding_schedule WHERE (livestock_id = ? OR livestock_id = 0) AND archived = 0 ORDER BY month_year DESC");
                                $stmt->bind_param("i", $selectedLivestockId); $stmt->execute(); $res = $stmt->get_result();
                                while ($r = $res->fetch_assoc()) $feedingRows[] = $r; $stmt->close();
                            }
                            ?>
                            <div class="monitoring-scroll">
                                <table class="monitoring-table wide">
                                    <thead><tr>
                                        <th>Month</th><th>Feeding Code</th><th>Morning</th><th>Afternoon</th><th>Snacks</th><th>Evening</th><th>Night Visit</th>
                                        <th>Eggs Collected</th><th>Avg Egg Weight</th><th>Avg Layer Weight</th><th>Avg Rooster Weight</th>
                                        <th>Housing/Nest Disinfection</th><th>General Cleaning</th><th>Chicken Bath</th>
                                        <?php if ($role === 'admin'): ?><th>Action</th><?php endif; ?>
                                    </tr></thead>
                                    <tbody>
                                                                        <?php if ($feedingRows): foreach ($feedingRows as $r): ?>                                        <tr data-record-row="1" data-search="<?= htmlspecialchars(strtolower(implode(' ', array_map('strval', $r))), ENT_QUOTES) ?>" data-month="<?= htmlspecialchars(substr((string)($r['record_date'] ?? $r['month_year'] ?? ''),0,7), ENT_QUOTES) ?>" data-month-label="<?= htmlspecialchars((string)($r['record_date'] ?? $r['month_year'] ?? ''), ENT_QUOTES) ?>">
                                            <td><?= htmlspecialchars($r['month_year']) ?></td><td><?= htmlspecialchars($r['feeding_code'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['morning'] ?? '-') ?></td><td><?= htmlspecialchars($r['afternoon'] ?? '-') ?></td><td><?= htmlspecialchars($r['snacks'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['evening'] ?? '-') ?></td><td><?= htmlspecialchars($r['night_visit'] ?? '-') ?></td><td><?= htmlspecialchars($r['eggs_collected'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['average_egg_weight'] ?? '-') ?></td><td><?= htmlspecialchars($r['average_layer_weight'] ?? '-') ?></td><td><?= htmlspecialchars($r['average_rooster_weight'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['housing_nest_disinfection'] ?? '-') ?></td><td><?= htmlspecialchars($r['general_cleaning'] ?? '-') ?></td><td><?= htmlspecialchars($r['chicken_bath'] ?? '-') ?></td>
                                            <?php if ($role === 'admin'): ?><td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("chicken_feeding", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('chicken_feeding', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td><?php endif; ?>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="<?= $role === 'admin' ? 15 : 14 ?>" class="monitor-empty">No feeding schedule records yet.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php elseif ($isGoat): ?>

                        <!-- GOAT DAILY MONITORING -->
                        <div class="monitoring-panel active" data-monitoring-panel="daily">
                            <div class="monitoring-panel-title">
                                <div><h3>Goat Daily Monitoring</h3><span>Individual goat monitoring from the Goat Monitoring Sheet.</span></div>
                            </div>
                            <div class="monitoring-tools"><div class="monitoring-search-wrap"><span class="material-symbols-rounded">search</span><input class="monitoring-search" type="search" placeholder="Search records..." oninput="filterMonitoringPanel(this)"></div><select class="monitoring-month" onchange="filterMonitoringPanel(this)"><option value="">All Months</option></select><span class="monitoring-count" data-monitor-count>0 records</span></div>
                            <?php
                            $goatRows = [];
                            if ($selectedLivestockId > 0) {
                                $stmt = $conn->prepare("SELECT * FROM goat_monitoring_records WHERE livestock_id = ? AND archived = 0 ORDER BY record_date DESC");
                                $stmt->bind_param("i", $selectedLivestockId); $stmt->execute(); $res = $stmt->get_result();
                                while ($r = $res->fetch_assoc()) $goatRows[] = $r; $stmt->close();
                            }
                            ?>
                            <div class="monitoring-scroll">
                                <table class="monitoring-table">
                                    <thead><tr><th>Date</th><th>Status</th><th>Free Range Schedule</th><th>Type of Drink</th><th>Disinfect / Cleaning</th><th>Notes / Observation</th><?php if ($role === 'admin'): ?><th>Action</th><?php endif; ?></tr></thead>
                                    <tbody>
                                                                        <?php if ($goatRows): foreach ($goatRows as $r): ?>                                        <tr data-record-row="1" data-search="<?= htmlspecialchars(strtolower(implode(' ', array_map('strval', $r))), ENT_QUOTES) ?>" data-month="<?= htmlspecialchars(substr((string)($r['record_date'] ?? $r['month_year'] ?? ''),0,7), ENT_QUOTES) ?>" data-month-label="<?= htmlspecialchars((string)($r['record_date'] ?? $r['month_year'] ?? ''), ENT_QUOTES) ?>">
                                            <td><?= htmlspecialchars($r['record_date']) ?></td><td><?= htmlspecialchars($r['status'] ?? '-') ?></td><td><?= htmlspecialchars($r['free_range_schedule'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['drink_type'] ?? '-') ?></td><td><?= htmlspecialchars($r['disinfect_cleaning'] ?? '-') ?></td><td><?= htmlspecialchars($r['notes'] ?? '-') ?></td>
                                            <?php if ($role === 'admin'): ?><td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("goat_daily", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('goat_daily', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td><?php endif; ?>
                                        </tr>
                                    <?php endforeach; else: ?><tr><td colspan="<?= $role === 'admin' ? 7 : 6 ?>" class="monitor-empty">No goat monitoring records yet.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- GOAT FORAGES -->
                        <div class="monitoring-panel" data-monitoring-panel="forage">
                            <div class="monitoring-panel-title">
                                <div><h3>Goat Forages</h3><span>Forage records from the Goat Monitoring Sheet.</span></div>
                            </div>
                            <div class="monitoring-tools"><div class="monitoring-search-wrap"><span class="material-symbols-rounded">search</span><input class="monitoring-search" type="search" placeholder="Search records..." oninput="filterMonitoringPanel(this)"></div><select class="monitoring-month" onchange="filterMonitoringPanel(this)"><option value="">All Months</option></select><span class="monitoring-count" data-monitor-count>0 records</span></div>
                            <?php
                            $forageRows = [];
                            if ($selectedLivestockId > 0) {
                                $stmt = $conn->prepare("SELECT * FROM goat_forage_records WHERE (livestock_id = ? OR livestock_id = 0) AND archived = 0 ORDER BY record_date DESC");
                                $stmt->bind_param("i", $selectedLivestockId); $stmt->execute(); $res = $stmt->get_result();
                                while ($r = $res->fetch_assoc()) $forageRows[] = $r; $stmt->close();
                            }
                            ?>
                            <div class="monitoring-scroll">
                                <table class="monitoring-table">
                                    <thead><tr><th>Date</th><th>Forage</th><th>Quantity</th><th>Notes</th><?php if ($role === 'admin'): ?><th>Action</th><?php endif; ?></tr></thead>
                                    <tbody>
                                                                        <?php if ($forageRows): foreach ($forageRows as $r): ?>                                        <tr data-record-row="1" data-search="<?= htmlspecialchars(strtolower(implode(' ', array_map('strval', $r))), ENT_QUOTES) ?>" data-month="<?= htmlspecialchars(substr((string)($r['record_date'] ?? $r['month_year'] ?? ''),0,7), ENT_QUOTES) ?>" data-month-label="<?= htmlspecialchars((string)($r['record_date'] ?? $r['month_year'] ?? ''), ENT_QUOTES) ?>"><td><?= htmlspecialchars($r['record_date']) ?></td><td><?= htmlspecialchars($r['forage_name'] ?? '-') ?></td><td><?= htmlspecialchars($r['quantity'] ?? '-') ?></td><td><?= htmlspecialchars($r['notes'] ?? '-') ?></td>
                                        <?php if ($role === 'admin'): ?><td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("goat_forage", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('goat_forage', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td><?php endif; ?></tr>
                                    <?php endforeach; else: ?><tr><td colspan="<?= $role === 'admin' ? 5 : 4 ?>" class="monitor-empty">No forage records yet.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php else: ?>

                        <div class="monitoring-empty-state">
                            <span class="material-symbols-rounded">description</span>
                            <h3>Monitoring sheet not configured yet</h3>
                            <p>The existing livestock profile is preserved. Add the Rabbit or Swine monitoring sheet when its actual fields are available, and its monitoring tabs can be added without changing this profile.</p>
                        </div>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="monitoring-empty-state">
                    <span class="material-symbols-rounded">pets</span>
                    <h3>Select a livestock</h3>
                    <p>Select a livestock above to view its monitoring records.</p>
                </div>

            <?php endif; ?>

        </div>


    </main>

</div>


<!-- =========================================================
     EDIT MODAL
========================================================= -->

<div
    class="modal-overlay"
    id="editModal"
>

    <div class="edit-modal">


        <div class="modal-header">

            <h3 id="editModalTitle">
                Edit Livestock
            </h3>


            <button
                type="button"
                class="close-modal"
                onclick="closeEditModal()"
            >

                <span class="material-symbols-rounded">
                    close
                </span>

            </button>

        </div>


        <iframe
            id="editFrame"
            title="Livestock Form"
        ></iframe>


    </div>

</div>


<!-- =========================================================
     ARCHIVE MODAL
========================================================= -->

<div
    class="modal-overlay"
    id="archiveModal"
>

    <div class="archive-modal">


        <div class="archive-icon-large">

            <span class="material-symbols-rounded">
                archive
            </span>

        </div>


        <h3>
            Archive Livestock?
        </h3>


        <p id="archiveMessage">
            Are you sure you want to archive this livestock?
        </p>


        <div class="archive-actions">


            <button
                type="button"
                class="cancel-btn"
                onclick="closeArchiveModal()"
            >

                Cancel

            </button>


            <button
                type="button"
                class="confirm-archive-btn"
                id="confirmArchiveBtn"
            >

                Archive

            </button>


        </div>


    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>


// =========================================================
// ARCHIVE STATE
// =========================================================

let archiveLivestockId = 0;


// =========================================================
// CHANGE LIVESTOCK TYPE
// =========================================================

function changeLivestockType(type) {

    if (type === '') {

        window.location.href =
            'livestocks.php';

        return;

    }


    window.location.href =
        'livestocks.php?type=' +
        encodeURIComponent(type);

}


// =========================================================
// CHANGE LIVESTOCK
// =========================================================

function changeLivestock(id) {

    if (!id) {

        return;

    }


    const params =
        new URLSearchParams(
            window.location.search
        );


    const type =
        params.get('type');


    let url =
        'livestocks.php?livestock_id=' +
        encodeURIComponent(id);


    if (type) {

        url +=
            '&type=' +
            encodeURIComponent(type);

    }


    window.location.href =
        url;

}


// =========================================================
// SEARCH LIVESTOCK
// =========================================================

function searchLivestock() {

    const input =
        document.getElementById(
            'livestockSearch'
        );


    if (!input) {

        return;

    }


    const search =
        input.value
            .toLowerCase()
            .trim();


    const rows =
        document.querySelectorAll(
            '.livestock-row'
        );


    rows.forEach(function(row) {

        const text =
            row.textContent
                .toLowerCase();


        if (
            text.includes(search)
        ) {

            row.style.display = '';

        } else {

            row.style.display = 'none';

        }

    });

}


// =========================================================
// SEARCH RECORDS
// =========================================================

function searchRecords() {

    filterRecords();

}


// =========================================================
// FILTER RECORDS
// =========================================================

function filterRecords() {

    const searchInput =
        document.getElementById(
            'recordSearch'
        );


    const monthFilter =
        document.getElementById(
            'monthFilter'
        );


    if (!searchInput) {

        return;

    }


    const search =
        searchInput.value
            .toLowerCase()
            .trim();


    const month =
        monthFilter
            ? monthFilter.value
            : 'all';


    const rows =
        document.querySelectorAll(
            '.livestock-row'
        );


    rows.forEach(function(row) {

        const text =
            row.textContent
                .toLowerCase();


        const matchesSearch =
            text.includes(search);


        /*
         * Month filtering can be connected
         * to a date column when the livestock
         * record date field is finalized.
         */

        const matchesMonth =
            month === 'all'
                ? true
                : true;


        row.style.display =
            matchesSearch &&
            matchesMonth
                ? ''
                : 'none';

    });

}


// =========================================================
// EDIT MODAL
// =========================================================

function openEditModal(
    url,
    title
) {

    const modal =
        document.getElementById(
            'editModal'
        );


    const frame =
        document.getElementById(
            'editFrame'
        );


    const modalTitle =
        document.getElementById(
            'editModalTitle'
        );


    if (
        !modal ||
        !frame ||
        !modalTitle
    ) {

        console.error(
            'Edit modal elements not found.'
        );

        return;

    }


    modalTitle.textContent =
        title;


    frame.src =
        url;


    modal.classList.add(
        'active'
    );


    document.body.style.overflow =
        'hidden';

}


// =========================================================
// CLOSE EDIT MODAL
// =========================================================

function closeEditModal() {

    const modal =
        document.getElementById(
            'editModal'
        );


    const frame =
        document.getElementById(
            'editFrame'
        );


    if (modal) {

        modal.classList.remove(
            'active'
        );

    }


    if (frame) {

        frame.src = '';

    }


    document.body.style.overflow =
        '';

}


// =========================================================
// OPEN ARCHIVE MODAL
// =========================================================

function openArchiveModal(
    livestockId,
    livestockName
) {

    archiveLivestockId =
        parseInt(
            livestockId,
            10
        );


    const modal =
        document.getElementById(
            'archiveModal'
        );


    const message =
        document.getElementById(
            'archiveMessage'
        );


    if (!modal) {

        console.error(
            'Archive modal not found.'
        );

        return;

    }


    if (message) {

        message.textContent =
            'Are you sure you want to archive "' +
            livestockName +
            '"?';

    }


    modal.classList.add(
        'active'
    );


    document.body.style.overflow =
        'hidden';

}


// =========================================================
// CLOSE ARCHIVE MODAL
// =========================================================

function closeArchiveModal() {

    const modal =
        document.getElementById(
            'archiveModal'
        );


    if (modal) {

        modal.classList.remove(
            'active'
        );

    }


    archiveLivestockId = 0;


    document.body.style.overflow =
        '';

}


// =========================================================
// CONFIRM ARCHIVE
// =========================================================

function confirmArchive() {

    if (!archiveLivestockId) {

        console.error(
            'No livestock selected for archiving.'
        );

        return;

    }


    /*
     * Create a POST form dynamically.
     *
     * This sends the livestock ID to
     * livestocks.php, where PHP performs:
     *
     * UPDATE livestocks
     * SET archived = 1
     */

    const form =
        document.createElement(
            'form'
        );


    form.method =
        'POST';


    form.action =
        'livestocks.php';


    const input =
        document.createElement(
            'input'
        );


    input.type =
        'hidden';


    input.name =
        'archive_livestock_id';


    input.value =
        archiveLivestockId;


    form.appendChild(
        input
    );


    document.body.appendChild(
        form
    );


    form.submit();

}


// =========================================================
// EDIT BUTTON EVENT LISTENERS
// =========================================================

document
    .querySelectorAll(
        '.edit-icon'
    )
    .forEach(function(button) {

        button.addEventListener(
            'click',
            function() {

                const url =
                    this.dataset.editUrl;


                const title =
                    this.dataset.editTitle
                    || 'Edit Livestock';


                if (url) {

                    openEditModal(
                        url,
                        title
                    );

                }

            }
        );

    });


// =========================================================
// ARCHIVE BUTTON EVENT LISTENERS
// =========================================================

document
    .querySelectorAll(
        '.livestock-archive-btn'
    )
    .forEach(function(button) {

        button.addEventListener(
            'click',
            function() {

                const livestockId =
                    this.dataset.livestockId;


                const name =
                    this.dataset.livestockName
                    || 'this livestock';


                if (!livestockId) {

                    console.error(
                        'Livestock ID is missing.'
                    );

                    return;

                }


                openArchiveModal(
                    livestockId,
                    name
                );

            }
        );

    });


// =========================================================
// CONFIRM ARCHIVE BUTTON
// =========================================================

const confirmArchiveButton =
    document.getElementById(
        'confirmArchiveBtn'
    );


if (confirmArchiveButton) {

    confirmArchiveButton.addEventListener(
        'click',
        function() {

            confirmArchive();

        }
    );

}


// =========================================================
// CLICK OUTSIDE EDIT MODAL
// =========================================================

const editModal =
    document.getElementById(
        'editModal'
    );


if (editModal) {

    editModal.addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                closeEditModal();

            }

        }
    );

}


// =========================================================
// CLICK OUTSIDE ARCHIVE MODAL
// =========================================================

const archiveModal =
    document.getElementById(
        'archiveModal'
    );


if (archiveModal) {

    archiveModal.addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                closeArchiveModal();

            }

        }
    );

}


// =========================================================
// ESC KEY
// =========================================================

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeEditModal();

            closeArchiveModal();

        }

    }
);


</script>



<!-- =========================================================
     MONITORING ADD MODAL
========================================================= -->
<div class="monitoring-modal-overlay" id="monitoringModal">
    <div class="monitoring-modal">
        <div class="monitoring-modal-header">
            <div>
                <h3 id="monitorModalTitle">Add Monitoring Record</h3>
                <span><?= htmlspecialchars($livestockProfile['name'] ?? 'Selected livestock') ?></span>
            </div>
            <button type="button" class="monitoring-close" onclick="closeMonitoringModal()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>

        <form method="POST" id="monitoringForm">
            <input type="hidden" name="monitoring_action" id="monitoringAction" value="add">
            <input type="hidden" name="livestock_id" value="<?= (int)$selectedLivestockId ?>">
            <input type="hidden" name="record_type" id="monitorRecordType">
            <input type="hidden" name="record_id" id="monitorRecordId" value="0">
            <input type="hidden" name="return_type" value="<?= htmlspecialchars($selectedType, ENT_QUOTES) ?>">

            <div class="monitor-form-group">
                <label>Monitoring Sheet</label>
                <select id="monitorTypeSelector" onchange="buildMonitoringForm(this.value)">
                    <?php foreach ($monitoringTabs as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="monitorDynamicFields"></div>

            <div class="monitor-form-actions">
                <button type="button" class="monitor-cancel" onclick="closeMonitoringModal()">Cancel</button>
                <button type="submit" class="monitor-save" id="monitorSaveButton">Save Record</button>
            </div>
        </form>
    </div>
</div>



<script>
function switchMonitoringTab(tabKey){
    document.querySelectorAll('.monitoring-tab').forEach(function(tab){
        tab.classList.toggle('active', tab.dataset.monitoringTab === tabKey);
    });
    document.querySelectorAll('.monitoring-panel').forEach(function(panel){
        panel.classList.toggle('active', panel.dataset.monitoringPanel === tabKey);
    });
    const activePanel=document.querySelector('.monitoring-panel.active');
    if(activePanel){
        const input=activePanel.querySelector('.monitoring-search');
        setupMonitoringTable(activePanel);
        if(input) filterMonitoringPanel(input);
    }
}

function monitoringTypeFromTab(tab){
    const map={
        weight:'chicken_weight', eggs:'chicken_eggs', mortality:'chicken_mortality',
        feed:'chicken_feed', water:'chicken_water', feeding:'chicken_feeding',
        daily:'goat_daily', forage:'goat_forage'
    };
    return map[tab] || tab;
}

function openMonitoringModal(type){
    const modal=document.getElementById('monitoringModal');
    const action=document.getElementById('monitoringAction');
    const recordId=document.getElementById('monitorRecordId');
    const recordType=document.getElementById('monitorRecordType');
    const selector=document.getElementById('monitorTypeSelector');
    const title=document.getElementById('monitorModalTitle');
    if(!modal) return;

    let currentType=type;
    if(!currentType){
        const active=document.querySelector('.monitoring-tab.active');
        currentType=active ? active.dataset.monitoringTab : Object.keys({weight:1,eggs:1,mortality:1,feed:1,water:1,feeding:1,daily:1,forage:1})[0];
    }
    const actualType=monitoringTypeFromTab(currentType);
    if(action) action.value='add';
    if(recordId) recordId.value='0';
    if(recordType) recordType.value=actualType;
    if(selector) selector.value=currentType;
    if(title) title.textContent='Add Monitoring Record';
    buildMonitoringForm(currentType, {});
    modal.classList.add('active');
    document.body.style.overflow='hidden';
}

function closeMonitoringModal(){
    const modal=document.getElementById('monitoringModal');
    if(modal) modal.classList.remove('active');
    document.body.style.overflow='';
}

function escHtml(value){
    return String(value ?? '').replace(/[&<>'"]/g,function(ch){
        return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch];
    });
}

function fieldInput(name,label,value,type='text',full=false){
    return `<div class="monitor-field ${full?'full':''}"><label>${escHtml(label)}</label><input type="${type}" name="${escHtml(name)}" value="${escHtml(value)}"></div>`;
}
function fieldTextarea(name,label,value,full=true){
    return `<div class="monitor-field ${full?'full':''}"><label>${escHtml(label)}</label><textarea name="${escHtml(name)}">${escHtml(value)}</textarea></div>`;
}
function fieldSelect(name,label,value,options,full=false){
    return `<div class="monitor-field ${full?'full':''}"><label>${escHtml(label)}</label><select name="${escHtml(name)}">${options.map(o=>`<option value="${escHtml(o)}" ${String(value??'')===String(o)?'selected':''}>${escHtml(o)}</option>`).join('')}</select></div>`;
}

function buildMonitoringForm(tab, data){
    data = data || {};
    const type = monitoringTypeFromTab(tab);
    const container = document.getElementById('monitorDynamicFields');
    const recordType = document.getElementById('monitorRecordType');
    if(recordType) recordType.value = type;
    if(!container) return;

    const v = (name) => data[name] ?? '';
    const wrap = (fields) => '<div class="monitor-fields-grid">' + fields.join('') + '</div>';

    let fields = [];

    if(type === 'chicken_weight'){
        fields = [
            fieldInput('record_date','Date',normalizeDateForInput(v('record_date')),'date'),
            fieldInput('rooster_banaba','Rooster Banaba',v('rooster_banaba')),
            fieldInput('rooster_paraokan','Rooster Paraokan',v('rooster_paraokan')),
            fieldInput('hen_banaba','Hen Banaba',v('hen_banaba')),
            fieldInput('hen_paraokan','Hen Paraokan',v('hen_paraokan')),
            fieldInput('average_weight','Average',v('average_weight'))
        ];
    } else if(type === 'chicken_eggs'){
        fields = [
            fieldInput('record_date','Date',normalizeDateForInput(v('record_date')),'date'),
            fieldInput('days','Days',v('days')),
            fieldInput('feed_formula','Feed Formula',v('feed_formula')),
            fieldInput('egg_count','# of Eggs',v('egg_count')),
            fieldInput('lightest_weight','Lightest Weight',v('lightest_weight')),
            fieldInput('heaviest_weight','Heaviest Weight',v('heaviest_weight')),
            fieldInput('hatching','Hatching',v('hatching'))
        ];
    } else if(type === 'chicken_mortality'){
        fields = [
            fieldInput('record_date','Date',normalizeDateForInput(v('record_date')),'date'),
            fieldInput('quantity','No.',v('quantity')),
            fieldInput('chicken_type','Type Chicken',v('chicken_type')),
            fieldInput('breed','Breed',v('breed')),
            fieldTextarea('cause_of_death','Cause of Death',v('cause_of_death'),true)
        ];
    } else if(type === 'chicken_feed'){
        fields = [
            fieldInput('formulation_code','Formulation Code',v('formulation_code')),
            fieldInput('feed_ingredients','Feed Ingredients',v('feed_ingredients')),
            fieldInput('quantity','Quantity',v('quantity'))
        ];
    } else if(type === 'chicken_water'){
        fields = [
            fieldInput('record_date','Date',normalizeDateForInput(v('record_date')),'date'),
            fieldInput('water_code','Water Code',v('water_code')),
            fieldInput('number_of_waterers','Number of Waterers',v('number_of_waterers')),
            fieldInput('ml_per_16_liter','ML per 16-liter water',v('ml_per_16_liter'))
        ];
    } else if(type === 'chicken_feeding'){
        fields = [
            fieldInput('month_year','Month / Year',v('month_year')),
            fieldInput('feeding_code','Feeding Code',v('feeding_code')),
            fieldInput('morning','Morning',v('morning')),
            fieldInput('afternoon','Afternoon',v('afternoon')),
            fieldInput('snacks','Snacks',v('snacks')),
            fieldInput('evening','Evening',v('evening')),
            fieldInput('night_visit','Night Visit',v('night_visit')),
            fieldInput('eggs_collected','Eggs Collected',v('eggs_collected')),
            fieldInput('average_egg_weight','Average Egg Weight',v('average_egg_weight')),
            fieldInput('average_layer_weight','Average Chicken Layer Weight',v('average_layer_weight')),
            fieldInput('average_rooster_weight','Average Chicken Rooster Weight',v('average_rooster_weight')),
            fieldInput('housing_nest_disinfection','Housing/Laying Nest Disinfection',v('housing_nest_disinfection')),
            fieldInput('general_cleaning','General Cleaning',v('general_cleaning')),
            fieldInput('chicken_bath','Chicken Bath',v('chicken_bath'))
        ];
    } else if(type === 'goat_daily'){
        fields = [
            fieldInput('record_date','Date',normalizeDateForInput(v('record_date')),'date'),
            fieldSelect('status','Status',v('status'),['Healthy','Sick','Pregnant','Lactating']),
            fieldInput('free_range_schedule','Free Range Schedule',v('free_range_schedule')),
            fieldInput('drink_type','Type of Drink',v('drink_type')),
            fieldInput('disinfect_cleaning','Disinfect Cleaning',v('disinfect_cleaning')),
            fieldTextarea('notes','Notes / Observation',v('notes'),true)
        ];
    } else if(type === 'goat_forage'){
        fields = [
            fieldInput('record_date','Date',normalizeDateForInput(v('record_date')),'date'),
            fieldInput('forage_name','Forage',v('forage_name')),
            fieldInput('quantity','Quantity',v('quantity')),
            fieldTextarea('notes','Notes',v('notes'),true)
        ];
    }

    container.innerHTML = wrap(fields);
}

function normalizeDateForInput(value){
    if(value === null || value === undefined) return '';
    const s = String(value).trim();
    if(!s) return '';
    if(/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;

    let m = s.match(/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/);
    if(m){
        return `${m[3]}-${String(m[1]).padStart(2,'0')}-${String(m[2]).padStart(2,'0')}`;
    }

    const d = new Date(s);
    if(!Number.isNaN(d.getTime())){
        return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    }
    return '';
}

function editMonitoringRecord(type,data){
    const modal=document.getElementById('monitoringModal');
    if(!modal) return;
    const tabMap={chicken_weight:'weight',chicken_eggs:'eggs',chicken_mortality:'mortality',chicken_feed:'feed',chicken_water:'water',chicken_feeding:'feeding',goat_daily:'daily',goat_forage:'forage'};
    const tab=tabMap[type]||type;
    document.getElementById('monitoringAction').value='update';
    document.getElementById('monitorRecordId').value=data.id||0;
    document.getElementById('monitorRecordType').value=type;
    const selector=document.getElementById('monitorTypeSelector');
    if(selector) selector.value=tab;
    const title=document.getElementById('monitorModalTitle');
    if(title) title.textContent='Edit Monitoring Record';
    buildMonitoringForm(tab,data);
    modal.classList.add('active');
    document.body.style.overflow='hidden';
}

function submitMonitoringAction(action,type,id){
    const form=document.createElement('form');
    form.method='POST';
    form.action='livestocks.php';
    const fields={monitoring_action:action,record_type:type,record_id:id,livestock_id:'<?= (int)$selectedLivestockId ?>',return_type:'<?= htmlspecialchars($selectedType, ENT_QUOTES) ?>'};
    Object.keys(fields).forEach(function(key){
        const input=document.createElement('input'); input.type='hidden'; input.name=key; input.value=fields[key]; form.appendChild(input);
    });
    document.body.appendChild(form); form.submit();
}

function normalizeMonthValue(raw){
    const value=(raw||'').trim();
    if(!value) return '';

    const monthNumbers={
        january:'01', february:'02', march:'03', april:'04', may:'05', june:'06',
        july:'07', august:'08', september:'09', october:'10', november:'11', december:'12'
    };

    // ISO dates: 2026-01-15 -> 01
    const iso=value.match(/^\d{4}-(\d{2})/);
    if(iso) return iso[1];

    // Dates such as 01/15/2026, 01-15-2026, etc.
    const numeric=value.match(/^(\d{1,2})[\/-](\d{1,2})[\/-]\d{4}/);
    if(numeric){
        const first=Number(numeric[1]);
        const second=Number(numeric[2]);
        // Prefer the month position for common MM/DD/YYYY or MM-DD-YYYY.
        if(first>=1 && first<=12) return String(first).padStart(2,'0');
        if(second>=1 && second<=12) return String(second).padStart(2,'0');
    }

    // Excel/PHP values such as "January 2026", "January", etc.
    const lower=value.toLowerCase();
    for(const [name,number] of Object.entries(monthNumbers)){
        if(lower.includes(name)) return number;
    }

    const parsed=new Date(value+' 1');
    if(!Number.isNaN(parsed.getTime())){
        return String(parsed.getMonth()+1).padStart(2,'0');
    }

    return '';
}

function monthLabel(monthNumber){
    const labels={
        '01':'January','02':'February','03':'March','04':'April','05':'May','06':'June',
        '07':'July','08':'August','09':'September','10':'October','11':'November','12':'December'
    };
    return labels[String(monthNumber).padStart(2,'0')] || '';
}

function setupMonitoringTable(panel){
    const table=panel.querySelector('.monitoring-table');
    if(!table) return;
    const headRow=table.querySelector('thead tr');
    const bodyRows=[...table.querySelectorAll('tbody tr')].filter(row=>!row.classList.contains('monitor-empty'));

    if(headRow && !headRow.querySelector('.monitoring-number')){
        const th=document.createElement('th');
        th.className='monitoring-number'; th.textContent='#';
        headRow.insertBefore(th,headRow.firstElementChild);
    }

    bodyRows.forEach((row,index)=>{
        row.dataset.recordRow='1';
        row.dataset.search=(row.innerText||'').toLowerCase().replace(/\s+/g,' ').trim();
        const firstCell=row.cells[0];
        const rawDate=firstCell ? firstCell.innerText.trim() : '';
        row.dataset.month=normalizeMonthValue(rawDate);
        row.dataset.monthLabel=monthLabel(row.dataset.month);
        if(!row.querySelector('.monitoring-number')){
            const td=document.createElement('td');
            td.className='monitoring-number'; td.textContent=String(index+1);
            row.insertBefore(td,row.firstElementChild);
        }
    });

    populateMonitoringMonths(panel);
}

function populateMonitoringMonths(panel){
    const select=panel.querySelector('.monitoring-month');
    if(!select) return;

    const current=select.value;
    const months=[
        ['01','January'],['02','February'],['03','March'],['04','April'],
        ['05','May'],['06','June'],['07','July'],['08','August'],
        ['09','September'],['10','October'],['11','November'],['12','December']
    ];

    select.innerHTML='<option value="">All Months</option>';
    months.forEach(([value,label])=>{
        const option=document.createElement('option');
        option.value=value;
        option.textContent=label;
        select.appendChild(option);
    });

    if(months.some(([value])=>value===current)) select.value=current;
}

function filterMonitoringPanel(source){
    const panel=source.closest('.monitoring-panel');
    if(!panel) return;
    setupMonitoringTable(panel);
    const search=(panel.querySelector('.monitoring-search')?.value||'').trim().toLowerCase();
    const month=panel.querySelector('.monitoring-month')?.value||'';
    let visible=0;
    panel.querySelectorAll('tbody tr[data-record-row="1"]').forEach(row=>{
        const show=(!search || (row.dataset.search||'').includes(search)) && (!month || row.dataset.month===month);
        row.style.display=show?'':'none';
        if(show) visible++;
    });
    const count=panel.querySelector('[data-monitor-count]');
    if(count) count.textContent=visible+(visible===1?' record':' records');
}

function initializeMonitoringFilters(){
    document.querySelectorAll('.monitoring-panel').forEach(panel=>{
        setupMonitoringTable(panel);
        const input=panel.querySelector('.monitoring-search');
        if(input) filterMonitoringPanel(input);
    });
}

function archiveMonitoring(type,id){
    if(!confirm('Archive this monitoring record? It will be removed from the active records list.')) return;
    submitMonitoringAction('archive',type,id);
}

document.addEventListener('click',function(e){
    const modal=document.getElementById('monitoringModal');
    if(e.target===modal) closeMonitoringModal();
});

document.addEventListener('DOMContentLoaded', function(){
    initializeMonitoringFilters();
    const first=document.querySelector('.monitoring-tab.active');
    if(first) switchMonitoringTab(first.dataset.monitoringTab);
});
</script>


</body>

</html>