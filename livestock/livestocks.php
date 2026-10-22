
<?php
require_once __DIR__ . '/../staff_access.php';
sanctuario_staff_guard('livestock');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

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
$canManageLivestock = sanctuario_staff_can_access('livestock');

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

    if (!$canManageLivestock) {
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

        header("Location: livestocks.php?type=" . urlencode($_POST['return_type'] ?? '') . "&livestock_id=" . $livestockId . "&tab=" . urlencode($recordType));
        exit();
    }

    if ($action === 'archive') {
        $recordId = (int)($_POST['record_id'] ?? 0);

        // Map the active monitoring tab to its archive-page livestock type and section.
        $archiveMap = [
            'chicken_weight'   => ['table' => 'chicken_weight_records',       'type' => 'Chicken', 'section' => 'chicken_weight_records'],
            'chicken_eggs'     => ['table' => 'chicken_egg_records',          'type' => 'Chicken', 'section' => 'chicken_egg_records'],
            'chicken_mortality'=> ['table' => 'chicken_mortality_records',    'type' => 'Chicken', 'section' => 'chicken_mortality_records'],
            'chicken_feed'     => ['table' => 'chicken_feed_ingredients',     'type' => 'Chicken', 'section' => 'chicken_feed_ingredients'],
            'chicken_water'    => ['table' => 'chicken_drinking_water',      'type' => 'Chicken', 'section' => 'chicken_drinking_water'],
            'chicken_feeding'  => ['table' => 'chicken_feeding_schedule',    'type' => 'Chicken', 'section' => 'chicken_feeding_schedule'],
            'goat_daily'       => ['table' => 'goat_monitoring_records',      'type' => 'Goat',    'section' => 'goat_monitoring_records'],
            'goat_forage'      => ['table' => 'goat_forage_records',          'type' => 'Goat',    'section' => 'goat_forage_records']
        ];

        if ($recordId > 0 && isset($archiveMap[$recordType])) {
            $archiveConfig = $archiveMap[$recordType];
            archiveRecord($conn, $archiveConfig['table'], 'id', $recordId, (int)$_SESSION['user_id']);

            // An individual monitoring row belongs in its matching archive section;
            // do not archive the entire livestock profile or return to the active page.
            $archiveUrl = 'archived_livestocks.php?' . http_build_query([
                'type' => $archiveConfig['type'],
                'section' => $archiveConfig['section'],
                'archived_record' => 1
            ]);
            header('Location: livestocks.php?' . http_build_query([
                'type' => $_POST['return_type'] ?? $archiveConfig['type'],
                'livestock_id' => $livestockId,
                'tab' => $recordType
            ]));
            exit();
        }

        $returnType = trim((string)($_POST['return_type'] ?? ''));
        header('Location: livestocks.php?type=' . urlencode($returnType) . '&livestock_id=' . $livestockId . '&tab=' . urlencode($recordType));
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

        header("Location: livestocks.php?type=" . urlencode($_POST['return_type'] ?? '') . "&livestock_id=" . $livestockId . "&tab=" . urlencode($recordType));
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

        header("Location: livestocks.php?type=" . urlencode($_POST['return_type'] ?? '') . "&livestock_id=" . $livestockId . "&tab=" . urlencode($recordType));
        exit();
    }
}



// =========================================================
// ARCHIVE LIVESTOCK
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_livestock_id'])) {
    if (!$canManageLivestock) {
        http_response_code(403);
        exit('Unauthorized');
    }

    $archiveId = (int)$_POST['archive_livestock_id'];
    $returnType = trim((string)($_POST['return_type'] ?? $_POST['type'] ?? ''));
    $returnSection = trim((string)($_POST['return_section'] ?? ''));
    $returnLivestockId = (int)($_POST['return_livestock_id'] ?? $_POST['livestock_id'] ?? 0);

    // Stay on the active livestock page after archiving. Do not redirect to
    // archived_livestocks.php or add an archive-mode query flag.
    $returnParams = [];
    if ($returnType !== '') $returnParams['type'] = $returnType;
    if ($returnLivestockId > 0) $returnParams['livestock_id'] = $returnLivestockId;
    if ($returnSection !== '') $returnParams['section'] = $returnSection;
    $returnUrl = 'livestocks.php' . ($returnParams ? '?' . http_build_query($returnParams) : '');

    if ($archiveId > 0) {
        try {
            $conn->begin_transaction();

            /*
             * Explicitly guarantee that the profile is inserted into
             * archived_livestocks before removing it from livestocks.
             * Do not rely only on a helper's soft-archive flag.
             */
            $archiveTable = 'archived_livestocks';
            $tableCheck = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($archiveTable) . "'");
            if (!$tableCheck || $tableCheck->num_rows === 0) {
                if (!$conn->query("CREATE TABLE `{$archiveTable}` LIKE `livestocks`")) {
                    throw new Exception('Could not create archived_livestocks: ' . $conn->error);
                }
            }

            $sourceColumns = [];
            $sourceResult = $conn->query("SHOW COLUMNS FROM `livestocks`");
            if (!$sourceResult) throw new Exception('Could not inspect livestocks columns: ' . $conn->error);
            while ($column = $sourceResult->fetch_assoc()) $sourceColumns[$column['Field']] = $column;

            $archiveColumns = [];
            $archiveResult = $conn->query("SHOW COLUMNS FROM `{$archiveTable}`");
            if (!$archiveResult) throw new Exception('Could not inspect archived_livestocks columns: ' . $conn->error);
            while ($column = $archiveResult->fetch_assoc()) $archiveColumns[$column['Field']] = $column;

            foreach (['archived_at' => "DATETIME NULL", 'archived_by' => "INT NULL"] as $column => $definition) {
                if (!isset($archiveColumns[$column])) {
                    if (!$conn->query("ALTER TABLE `{$archiveTable}` ADD COLUMN `{$column}` {$definition}")) {
                        throw new Exception("Could not add {$column} to archived_livestocks: " . $conn->error);
                    }
                    $archiveColumns[$column] = true;
                }
            }

            $copyColumns = [];
            foreach ($sourceColumns as $columnName => $columnInfo) {
                if (!in_array($columnName, ['archived_at', 'archived_by'], true)
                    && isset($archiveColumns[$columnName])
                    && ($columnInfo['Extra'] ?? '') !== 'VIRTUAL GENERATED'
                    && ($columnInfo['Extra'] ?? '') !== 'STORED GENERATED') {
                    $copyColumns[] = $columnName;
                }
            }
            if (!in_array('livestock_id', $copyColumns, true)) {
                throw new Exception('The livestock_id column is missing from the archive table.');
            }

            $quotedColumns = array_map(fn($c) => '`' . str_replace('`', '', $c) . '`', $copyColumns);
            $updateParts = [];
            foreach ($quotedColumns as $quotedColumn) {
                if ($quotedColumn !== '`livestock_id`') $updateParts[] = "{$quotedColumn}=VALUES({$quotedColumn})";
            }
            $insertSql = "INSERT INTO `{$archiveTable}` (" . implode(',', $quotedColumns) . ",`archived_at`,`archived_by`) " .
                "SELECT " . implode(',', $quotedColumns) . ",NOW(),? FROM `livestocks` WHERE `livestock_id`=? " .
                "ON DUPLICATE KEY UPDATE " . implode(',', array_merge($updateParts, ['`archived_at`=NOW()', '`archived_by`=VALUES(`archived_by`)']));

            $ins = $conn->prepare($insertSql);
            if (!$ins) throw new Exception('Could not prepare archive insert: ' . $conn->error);
            $uid = (int)($_SESSION['user_id'] ?? 0);
            $ins->bind_param('ii', $uid, $archiveId);
            if (!$ins->execute()) {
                $err = $ins->error;
                $ins->close();
                throw new Exception('Could not insert into archived_livestocks: ' . $err);
            }
            $ins->close();

            /* Verify the archive copy exists before deleting the active row. */
            $verify = $conn->prepare("SELECT COUNT(*) FROM `{$archiveTable}` WHERE `livestock_id`=?");
            if (!$verify) throw new Exception('Could not verify archived record: ' . $conn->error);
            $verify->bind_param('i', $archiveId);
            $verify->execute();
            $verify->bind_result($archivedCount);
            $verify->fetch();
            $verify->close();
            if ((int)$archivedCount < 1) throw new Exception('The record was not found in archived_livestocks after insertion; the active record was kept.');

            $del = $conn->prepare("DELETE FROM `livestocks` WHERE `livestock_id`=? LIMIT 1");
            if (!$del) throw new Exception('Could not prepare active record removal: ' . $conn->error);
            $del->bind_param('i', $archiveId);
            if (!$del->execute() || $del->affected_rows < 1) {
                $err = $del->error ?: 'No active livestock row was removed.';
                $del->close();
                throw new Exception('Could not remove active livestock: ' . $err);
            }
            $del->close();

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
            header('Location: ' . $returnUrl);
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            http_response_code(500);
            exit('Could not archive livestock: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
    }
}

// =========================================================
// UPDATE LIVESTOCK PROFILE (same form and layout as Add Profile)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_livestock_profile'])) {
    if (!$canManageLivestock) { http_response_code(403); exit('Unauthorized'); }

    $type = trim((string)($_POST['profile_type'] ?? ''));
    $allowedTypes = ['Chicken', 'Goat', 'Pig', 'Rabbit'];
    $pageId = (int)($_POST['edit_page_id'] ?? 0);
    $pigId = (int)($_POST['edit_pig_id'] ?? 0);
    $mapId = (int)($_POST['edit_map_livestock_id'] ?? 0);
    $name = trim((string)($_POST['profile_name'] ?? ''));
    $breed = trim((string)($_POST['profile_breed'] ?? ''));
    $dob = trim((string)($_POST['profile_date_of_birth'] ?? ''));
    $sex = trim((string)($_POST['profile_sex'] ?? ''));
    $weightRaw = trim((string)($_POST['profile_weight'] ?? ''));
    $weight = $weightRaw === '' ? null : (float)$weightRaw;
    $notes = trim((string)($_POST['profile_notes'] ?? ''));
    $houseId = (int)($_POST['profile_house_id'] ?? 0);
    if (!in_array($type, $allowedTypes, true) || $name === '' || !in_array($sex, ['Male','Female','Mixed'], true)) {
        $_SESSION['livestock_profile_error'] = 'Please complete the required profile fields.';
        header('Location: livestocks.php?type=' . urlencode($type) . '&livestock_id=' . $pageId); exit();
    }
    if ($type === 'Pig' && !in_array($sex, ['Male','Female'], true)) {
        $_SESSION['livestock_profile_error'] = 'Pig sex must be Male or Female.';
        header('Location: livestocks.php?type=Pig&livestock_id=' . $pageId); exit();
    }
    $houseCheck = $conn->prepare("SELECT house_id FROM livestock_houses WHERE house_id = ? AND archived = 0 LIMIT 1");
    $houseCheck->bind_param('i', $houseId); $houseCheck->execute();
    $validHouse = $houseId > 0 && $houseCheck->get_result()->num_rows > 0; $houseCheck->close();
    if (!$validHouse) {
        $_SESSION['livestock_profile_error'] = 'Please select a valid active livestock house.';
        header('Location: livestocks.php?type=' . urlencode($type) . '&livestock_id=' . $pageId); exit();
    }
    $age = '';
    if ($dob !== '') { try { $diff = (new DateTime($dob))->diff(new DateTime('today')); $age = $diff->y . ' years ' . $diff->m . ' months'; } catch (Throwable $e) {} }
    $sexDb = strtolower($sex);
    $number = max(1, (int)($_POST['profile_number'] ?? 1));
    $feeding = trim((string)($_POST['profile_feeding_schedule'] ?? ''));
    if ($feeding === '') $feeding = 'Morning and evening';
    $forSale = (($_POST['profile_for_sale'] ?? '0') === '1') ? 1 : 0;

    $conn->begin_transaction();
    try {
        if ($type === 'Pig') {
            $tag = trim((string)($_POST['profile_tag_number'] ?? ''));
            if ($tag === '') throw new Exception('Tag Number is required for pigs.');
            $purpose = trim((string)($_POST['profile_purpose'] ?? ''));
            $health = trim((string)($_POST['profile_health_status'] ?? 'Healthy'));
            $forSale = strcasecmp($purpose, 'Sale') === 0 ? 1 : 0;
            if ($mapId > 0) {
                $stmt = $conn->prepare("UPDATE livestocks SET type='Pig', name=?, breed=NULLIF(?,''), date_of_birth=NULLIF(?,''), age=?, estimated_weight=?, for_sale=?, number=1, sex=?, feeding_schedule=?, house_id=?, notes=NULLIF(?, '') WHERE livestock_id=? AND archived=0");
                $stmt->bind_param('ssssdissisi', $name,$breed,$dob,$age,$weight,$forSale,$sexDb,$feeding,$houseId,$notes,$mapId);
                if (!$stmt->execute()) throw new Exception($stmt->error); $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO livestocks (type,name,breed,date_of_birth,age,estimated_weight,for_sale,number,sex,feeding_schedule,archived,house_id,notes) VALUES ('Pig',?,?,NULLIF(?,''),?,?,?,1,?,?,0,?,NULLIF(?,''))");
                $stmt->bind_param('ssssdissis', $name,$breed,$dob,$age,$weight,$forSale,$sexDb,$feeding,$houseId,$notes);
                if (!$stmt->execute()) throw new Exception($stmt->error); $mapId=(int)$conn->insert_id; $stmt->close();
            }
            $stmt = $conn->prepare("UPDATE pig_records SET livestock_id=?, tag_number=?, name=?, sex=?, breed=NULLIF(?,''), date_of_birth=NULLIF(?,''), weight=?, weight_unit='kg', purpose=NULLIF(?,''), health_status=?, notes=NULLIF(?, '') WHERE pig_id=?");
            $stmt->bind_param('isssssdsssi', $mapId,$tag,$name,$sex,$breed,$dob,$weight,$purpose,$health,$notes,$pigId);
            if (!$stmt->execute()) throw new Exception($stmt->error); $stmt->close();
            $pageId = -$pigId;
        } else {
            $stmt = $conn->prepare("UPDATE livestocks SET type=?, name=?, breed=NULLIF(?,''), date_of_birth=NULLIF(?,''), age=?, estimated_weight=?, for_sale=?, number=?, sex=?, feeding_schedule=?, house_id=?, notes=NULLIF(?, '') WHERE livestock_id=? AND archived=0");
            $stmt->bind_param('sssssdiissisi', $type,$name,$breed,$dob,$age,$weight,$forSale,$number,$sexDb,$feeding,$houseId,$notes,$pageId);
            if (!$stmt->execute()) throw new Exception($stmt->error); $stmt->close();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback(); $_SESSION['livestock_profile_error'] = 'Could not update livestock profile: ' . $e->getMessage();
        header('Location: livestocks.php?type=' . urlencode($type) . '&livestock_id=' . $pageId); exit();
    }
    header('Location: livestocks.php?type=' . urlencode($type) . '&livestock_id=' . $pageId . '&profile_updated=1'); exit();
}

// =========================================================
// ADD LIVESTOCK PROFILE (ALL LIVESTOCK TYPES)
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_livestock_profile'])) {
    if (!$canManageLivestock) {
        http_response_code(403);
        exit('Unauthorized');
    }

    $type = trim((string)($_POST['profile_type'] ?? ''));
    $allowedTypes = ['Chicken', 'Goat', 'Pig', 'Rabbit'];
    if (!in_array($type, $allowedTypes, true)) {
        $_SESSION['livestock_profile_error'] = 'Please choose a valid livestock type.';
        header('Location: livestocks.php');
        exit();
    }

    $name = trim((string)($_POST['profile_name'] ?? ''));
    $breed = trim((string)($_POST['profile_breed'] ?? ''));
    $dob = trim((string)($_POST['profile_date_of_birth'] ?? ''));
    $sex = trim((string)($_POST['profile_sex'] ?? ''));
    $weightRaw = trim((string)($_POST['profile_weight'] ?? ''));
    $notes = trim((string)($_POST['profile_notes'] ?? ''));

    if ($name === '' || !in_array($sex, ['Male', 'Female', 'Mixed'], true)) {
        $_SESSION['livestock_profile_error'] = 'Name and sex are required.';
        header('Location: livestocks.php?type=' . urlencode($type));
        exit();
    }

    if ($type === 'Pig') {
        if (!in_array($sex, ['Male', 'Female'], true)) {
            $_SESSION['livestock_profile_error'] = 'Pig sex must be Male or Female.';
            header('Location: livestocks.php?type=Pig');
            exit();
        }

        $tagNumber = trim((string)($_POST['profile_tag_number'] ?? ''));
        if ($tagNumber === '') {
            $_SESSION['livestock_profile_error'] = 'Tag Number is required for pigs.';
            header('Location: livestocks.php?type=Pig');
            exit();
        }

        // Farm map reads active animals from livestocks and groups them by house_id.
        // Save the pig in BOTH tables and link pig_records to its map-visible profile.
        $houseIdRaw = trim((string)($_POST['profile_house_id'] ?? ''));
        $houseId = $houseIdRaw === '' ? 0 : (int)$houseIdRaw;
        if ($houseId <= 0) {
            $_SESSION['livestock_profile_error'] = 'Please select the livestock house so the pig appears on the farm map.';
            header('Location: livestocks.php?type=Pig');
            exit();
        }
        $houseCheck = $conn->prepare("SELECT house_id FROM livestock_houses WHERE house_id = ? AND archived = 0 LIMIT 1");
        $houseCheck->bind_param('i', $houseId);
        $houseCheck->execute();
        $houseExists = $houseCheck->get_result()->num_rows > 0;
        $houseCheck->close();
        if (!$houseExists) {
            $_SESSION['livestock_profile_error'] = 'Please select a valid active livestock house.';
            header('Location: livestocks.php?type=Pig');
            exit();
        }

        $weight = $weightRaw === '' ? null : (float)$weightRaw;
        $purpose = trim((string)($_POST['profile_purpose'] ?? ''));
        $healthStatus = trim((string)($_POST['profile_health_status'] ?? 'Healthy'));
        $pigSex = $sex;
        $sexDb = strtolower($sex);
        $age = '';
        if ($dob !== '') {
            try {
                $birthDate = new DateTime($dob);
                $today = new DateTime('today');
                $diff = $birthDate->diff($today);
                $age = $diff->y . ' years ' . $diff->m . ' months';
            } catch (Throwable $e) { $age = ''; }
        }
        $feedingSchedule = trim((string)($_POST['profile_feeding_schedule'] ?? ''));
        if ($feedingSchedule === '') $feedingSchedule = 'Morning and evening';
        $forSale = strcasecmp($purpose, 'Sale') === 0 ? 1 : 0;

        $conn->begin_transaction();
        try {
            $mapStmt = $conn->prepare("INSERT INTO livestocks (type, name, breed, date_of_birth, age, estimated_weight, for_sale, number, sex, feeding_schedule, archived, house_id, notes) VALUES ('Pig', ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, 1, ?, ?, 0, ?, NULLIF(?, ''))");
            if (!$mapStmt) throw new Exception('Could not prepare the map livestock profile: ' . $conn->error);
            $mapStmt->bind_param('ssssdissis', $name, $breed, $dob, $age, $weight, $forSale, $sexDb, $feedingSchedule, $houseId, $notes);
            if (!$mapStmt->execute()) throw new Exception('Could not save the farm-map livestock profile: ' . $mapStmt->error);
            $mapLivestockId = (int)$conn->insert_id;
            $mapStmt->close();

            $stmt = $conn->prepare("INSERT INTO pig_records (livestock_id, tag_number, name, sex, breed, date_of_birth, weight, weight_unit, purpose, health_status, notes) VALUES (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, 'kg', NULLIF(?, ''), ?, NULLIF(?, ''))");
            if (!$stmt) throw new Exception('Could not prepare the pig profile: ' . $conn->error);
            $stmt->bind_param('isssssdsss', $mapLivestockId, $tagNumber, $name, $pigSex, $breed, $dob, $weight, $purpose, $healthStatus, $notes);
            if (!$stmt->execute()) throw new Exception('Could not save the pig profile: ' . $stmt->error);
            $newId = (int)$conn->insert_id;
            $stmt->close();
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            $_SESSION['livestock_profile_error'] = $e->getMessage();
            header('Location: livestocks.php?type=Pig');
            exit();
        }
        header('Location: livestocks.php?type=Pig&livestock_id=' . (-$newId) . '&profile_added=1');
        exit();
    }

    $number = max(1, (int)($_POST['profile_number'] ?? 1));
    $feedingSchedule = trim((string)($_POST['profile_feeding_schedule'] ?? ''));
    $forSale = (($_POST['profile_for_sale'] ?? '0') === '1') ? 1 : 0;
    $houseIdRaw = trim((string)($_POST['profile_house_id'] ?? ''));
    $houseId = $houseIdRaw === '' ? null : (int)$houseIdRaw;
    $weight = $weightRaw === '' ? null : (float)$weightRaw;
    $sexDb = strtolower($sex);

    if ($feedingSchedule === '') {
        $_SESSION['livestock_profile_error'] = 'Feeding schedule is required.';
        header('Location: livestocks.php?type=' . urlencode($type));
        exit();
    }

    if ($houseId === null || $houseId <= 0) {
        $_SESSION['livestock_profile_error'] = 'Please select the livestock house so the record appears on the farm map.';
        header('Location: livestocks.php?type=' . urlencode($type));
        exit();
    }
    $houseCheck = $conn->prepare("SELECT house_id FROM livestock_houses WHERE house_id = ? AND archived = 0 LIMIT 1");
    $houseCheck->bind_param('i', $houseId);
    $houseCheck->execute();
    $houseExists = $houseCheck->get_result()->num_rows > 0;
    $houseCheck->close();
    if (!$houseExists) {
        $_SESSION['livestock_profile_error'] = 'Please select a valid active livestock house.';
        header('Location: livestocks.php?type=' . urlencode($type));
        exit();
    }

    $age = '';
    if ($dob !== '') {
        try {
            $birthDate = new DateTime($dob);
            $today = new DateTime('today');
            $diff = $birthDate->diff($today);
            $age = $diff->y . ' years ' . $diff->m . ' months';
        } catch (Throwable $e) {
            $age = '';
        }
    }

    $stmt = $conn->prepare("INSERT INTO livestocks (type, name, breed, date_of_birth, age, estimated_weight, for_sale, number, sex, feeding_schedule, archived, house_id, notes) VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?, ?, 0, ?, NULLIF(?, ''))");
    if (!$stmt) {
        $_SESSION['livestock_profile_error'] = 'Could not prepare the livestock profile: ' . $conn->error;
        header('Location: livestocks.php?type=' . urlencode($type));
        exit();
    }
    $stmt->bind_param('sssssdiissis', $type, $name, $breed, $dob, $age, $weight, $forSale, $number, $sexDb, $feedingSchedule, $houseId, $notes);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        $_SESSION['livestock_profile_error'] = 'Could not save livestock profile: ' . $error;
        header('Location: livestocks.php?type=' . urlencode($type));
        exit();
    }
    $newId = (int)$conn->insert_id;
    $stmt->close();
    header('Location: livestocks.php?type=' . urlencode($type) . '&livestock_id=' . $newId . '&profile_added=1');
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
// GET SELECTED LIVESTOCK PROFILE
// =========================================================

$livestockProfile = null;

if ($selectedLivestockId < 0) {

    $pigId = abs($selectedLivestockId);
    $stmt = $conn->prepare("SELECT p.*, l.livestock_id AS map_livestock_id, l.house_id, l.number, l.for_sale, l.feeding_schedule FROM pig_records p LEFT JOIN livestocks l ON l.livestock_id = p.livestock_id WHERE p.pig_id = ? LIMIT 1");
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
            'map_livestock_id' => (int)($pig['map_livestock_id'] ?? 0),
            'house_id' => (int)($pig['house_id'] ?? 0),
            'number' => 1,
            'feeding_schedule' => $pig['feeding_schedule'] ?? 'Morning and evening',
            'for_sale' => (int)($pig['for_sale'] ?? (strcasecmp((string)($pig['purpose'] ?? ''), 'Sale') === 0 ? 1 : 0)),
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
            'notes' => $pig['notes'] ?? ''
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

$houseOptionsResult = $conn->query("SELECT house_id, house_name, house_code FROM livestock_houses WHERE archived = 0 ORDER BY house_name ASC");
$houseOptions = [];
if ($houseOptionsResult) {
    while ($houseOption = $houseOptionsResult->fetch_assoc()) $houseOptions[] = $houseOption;
}
$livestockProfileError = $_SESSION['livestock_profile_error'] ?? '';
unset($_SESSION['livestock_profile_error']);

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
    href="livestocks.css?v=8"
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
.monitoring-add-btn{border:0;border-radius:20px;background:#2f6b2f;color:#fff;padding:9px 14px;font-family:Poppins,sans-serif;font-size:.75rem;font-weight:600;display:flex;gap:6px;align-items:center;cursor:pointer}
/* Keep monitoring filters and header tools on one aligned row. */
.monitoring-tabs{display:flex;align-items:center;gap:7px;flex-wrap:wrap;padding-bottom:8px}
.monitoring-tabs .livestock-header-right{margin-left:auto;display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end}
@media(max-width:850px){.monitoring-tabs .livestock-header-right{margin-left:0;width:100%;justify-content:flex-start}}

.monitoring-tab{border:1px solid #d8e2d3;background:#fff;color:#55704f;border-radius:20px;padding:9px 13px;font-family:Poppins,sans-serif;font-size:.72rem;font-weight:600;white-space:nowrap;cursor:pointer}
.monitoring-tab.active{background:#4f7d3a;color:#fff;border-color:#4f7d3a}
.monitoring-table-card{background:#fff;border-radius:10px;box-shadow:0 2px 7px rgba(0,0,0,.06);overflow:hidden}
.monitoring-panel{display:none}
.monitoring-panel.active{display:block}
.monitoring-panel-title{padding:16px 18px;border-bottom:1px solid #edf1eb;display:flex;align-items:center;justify-content:space-between}
.monitoring-panel-title h3{margin:0;color:#2f6b2f;font-size:1.02rem}
.monitoring-panel-title span{display:block;margin-top:3px;color:#888;font-size:.78rem}
.monitoring-scroll{width:100%;max-width:100%;overflow:auto;border:1px solid #d6e0d1;border-radius:8px;-webkit-overflow-scrolling:touch;scrollbar-color:#8aa27d #eef2eb}
.monitoring-table{width:100%;border-collapse:collapse;min-width:900px;table-layout:auto;font-family:Poppins,sans-serif}
.monitoring-table.wide{min-width:1650px}
.monitoring-table th,.monitoring-table td{padding:10px 12px;border:1px solid #d6e0d1;line-height:1.45;vertical-align:middle;white-space:normal;overflow-wrap:anywhere;word-break:normal}
.monitoring-table th{background:#e3ebdc;color:#365a31;font-size:13px;font-weight:600;text-align:left;position:sticky;top:0;z-index:2}
.monitoring-table td{background:#fff;color:#344234;font-size:14px;font-weight:400}
.monitoring-table tbody > tr > td,
.monitoring-table tbody > tr:hover > td {
    background: #fff !important;
    background-color: #fff !important;
    background-image: none !important;
}
.monitoring-number{width:54px;text-align:center!important;font-weight:700;color:#496442!important}
.monitoring-tools{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:14px 18px;background:#fbfcfa;border-bottom:1px solid #edf1eb}
.monitoring-search-wrap{position:relative;flex:1;min-width:220px}
.monitoring-search-wrap .material-symbols-rounded{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:20px;color:#8a9885}
.monitoring-search{width:100%;box-sizing:border-box;border:1px solid #d8e2d3;border-radius:9px;padding:10px 12px 10px 40px;font-family:Poppins,sans-serif;font-size:.78rem;background:#fff;outline:none}
.monitoring-month{border:1px solid #d8e2d3;border-radius:9px;padding:10px 34px 10px 12px;font-family:Poppins,sans-serif;font-size:.78rem;background:#fff;color:#4b6246;min-width:165px;outline:none}
.monitoring-count{font-size:.76rem;font-weight:600;color:#64705f;white-space:nowrap}
.monitor-actions{display:flex;gap:6px;align-items:center}
.monitor-action-cell{white-space:nowrap;min-width:96px}.monitor-action-cell .monitor-action{margin-right:4px}.monitor-action-cell .monitor-action:last-child{margin-right:0}
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
.profile-header-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}
.profile-header-actions .edit-profile-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border-radius:20px;}
.add-livestock-profile-btn{background:#2f6b2f!important;color:#fff!important}
.livestock-profile-feedback{margin:0 0 12px;padding:10px 14px;border-radius:8px;font-size:.78rem;font-family:Poppins,sans-serif}
.livestock-profile-feedback.error{background:#fff0f0;color:#a33;border:1px solid #f0cccc}
.livestock-profile-feedback.success{background:#eef7eb;color:#32652b;border:1px solid #d6e8ce}
.add-profile-modal-overlay{display:none;position:fixed;inset:0;background:rgba(20,35,20,.48);z-index:10000;align-items:center;justify-content:center;padding:20px}
.add-profile-modal-overlay.active{display:flex}
.add-profile-modal{width:min(760px,100%);max-height:90vh;overflow-y:auto;background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.add-profile-form{padding:18px 20px}
.add-profile-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.add-profile-field{min-width:0}
.add-profile-field.full{grid-column:1/-1}
.add-profile-field label{display:block;margin-bottom:5px;font-size:.7rem;font-weight:600;color:#45643f}
.add-profile-field input,.add-profile-field select,.add-profile-field textarea{width:100%;box-sizing:border-box;border:1px solid #d9e1d6;border-radius:7px;padding:9px 10px;font-family:Poppins,sans-serif;font-size:.74rem;outline:none;background:#fff}
.add-profile-field textarea{min-height:76px;resize:vertical}
@media(max-width:650px){.profile-header{gap:10px;align-items:flex-start}.profile-header-actions{gap:6px}.profile-header-actions .edit-profile-btn{font-size:.68rem;padding:8px 10px}.add-profile-fields{grid-template-columns:1fr}.add-profile-field.full{grid-column:auto}}
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
<style id="sanctuario-responsive-refinement">
/* Sanctuario responsive refinement — layout geometry only, no feature changes. */
@media (max-width: 1199px) {
  html { -webkit-text-size-adjust: 100%; }
  body { min-width: 0; }
  .dashboard, .farm-map-page, .main-content, .page-content, .dashboard-content,
  .content-wrapper, .inventory-page, .records-page, .settings-page {
    min-width: 0; max-width: 100%; box-sizing: border-box;
  }
  .dashboard, .farm-map-page { padding-left: clamp(12px, 2vw, 24px); padding-right: clamp(12px, 2vw, 24px); }
  .hero-container, .farm-summary { padding: clamp(12px, 2vw, 26px); }
  .hero-cards, .top-grid, .bottom-grid, .summary-cards, .farm-map-layout,
  [class*="-grid"], [class*="_grid"] { min-width: 0; }
  .hero-cards > *, .summary-cards > *, .farm-map-layout > *,
  [class*="-grid"] > *, [class*="_grid"] > * { min-width: 0; }
  h1 { font-size: clamp(1.4rem, 2.6vw, 2.15rem); }
  h2 { font-size: clamp(1.2rem, 2.25vw, 1.9rem); }
  h3 { font-size: clamp(1.05rem, 1.8vw, 1.5rem); }
  img, video, canvas, svg { max-width: 100%; }
  input, select, textarea { max-width: 100%; min-width: 0; }
  .modal-content, .popup-content, [class*="modal-content"], [class*="popup-content"] {
    box-sizing: border-box; max-width: min(94vw, 100%); max-height: 90dvh;
  }
  .table-wrapper, .table-container, .table-responsive, [class*="table-wrap"],
  [class*="table-container"] { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
}
@media (min-width: 701px) and (max-width: 1199px) {
  .dashboard, .farm-map-page { padding-top: 14px; padding-bottom: 16px; }
  .hero-container, .farm-summary { margin-bottom: 12px; }
  .hero-cards, .summary-cards, .top-grid, .bottom-grid { gap: clamp(9px, 1.4vw, 18px); }
}
@media (max-width: 900px) {
  :root { --sidebar-width: 0px; }
  .dashboard, .farm-map-page { margin-left: 0; width: 100%; }
}
@media (max-width: 700px) {
  .dashboard, .farm-map-page { padding: 68px 12px 16px; }
  .hero-container, .farm-summary { padding: 12px; border-radius: 12px; }
  .hero-text h2 { font-size: clamp(1.35rem, 5vw, 1.85rem); }
  .hero-text p, .farm-map-title-area p { font-size: clamp(.75rem, 3vw, .95rem); }
  .hero-cards { gap: 9px; }
  .modal-content, .popup-content, [class*="modal-content"], [class*="popup-content"] {
    max-width: calc(100vw - 20px); max-height: calc(100dvh - 20px);
  }
}
@media (max-width: 380px) {
  .dashboard, .farm-map-page { padding-left: 8px; padding-right: 8px; }
  .hero-container, .farm-summary { padding: 10px; }
}

</style>
<style>
.snf-download-actions{display:inline-flex;align-items:center;justify-content:flex-end;gap:6px;flex-wrap:nowrap;vertical-align:middle}
.snf-download-actions a{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:7px 10px;border:1px solid #2b6734;border-radius:20px;background:#fff;color:#24552e;text-decoration:none;font-size:12px;font-weight:600;line-height:1.2;white-space:nowrap;box-sizing:border-box}
.snf-download-actions a:hover{background:#edf5ec}
@media(max-width:760px){.snf-download-actions{flex-wrap:wrap;gap:5px}.snf-download-actions a{min-height:34px;padding:7px 8px;font-size:11px}}
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

                <option value="" <?= $selectedLivestockId === 0 ? 'selected' : '' ?>>-</option>

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

                <?php if ($canManageLivestock): ?>

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

        <?php if ($livestockProfileError !== ''): ?>
            <div class="livestock-profile-feedback error"><?= htmlspecialchars($livestockProfileError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif (isset($_GET['profile_added'])): ?>
            <div class="livestock-profile-feedback success">Livestock profile added successfully.</div>
        <?php elseif (isset($_GET['profile_updated'])): ?>
            <div class="livestock-profile-feedback success">Livestock profile updated successfully.</div>
        <?php endif; ?>

        <div class="livestock-profile-card">


                <!-- PROFILE HEADER -->

                <div class="profile-header">

                    <h2>
                        PROFILE
                    </h2>


                    <?php if ($canManageLivestock): ?>
                        <div class="profile-header-actions">

                            <button type="button" class="edit-profile-btn add-livestock-profile-btn" onclick="openAddLivestockModal()">
                                <span class="material-symbols-rounded">add</span>
                                Add Livestock
                            </button>

                            <?php if ($livestockProfile): ?>
                            <button type="button" class="edit-profile-btn" onclick='openEditLivestockModal(<?= json_encode([
                                'page_id' => (int)$livestockProfile['livestock_id'],
                                'pig_id' => (int)($livestockProfile['pig_id'] ?? 0),
                                'map_id' => (int)($livestockProfile['map_livestock_id'] ?? $livestockProfile['livestock_id']),
                                'type' => (string)($livestockProfile['type'] ?? $selectedType),
                                'name' => (string)($livestockProfile['name'] ?? ''),
                                'tag_number' => (string)($livestockProfile['tag_number'] ?? ''),
                                'breed' => (string)($livestockProfile['breed'] ?? ''),
                                'date_of_birth' => (string)($livestockProfile['date_of_birth'] ?? ''),
                                'sex' => ucfirst(strtolower((string)($livestockProfile['sex'] ?? 'Male'))),
                                'weight' => (string)($livestockProfile['estimated_weight'] ?? ''),
                                'number' => (int)($livestockProfile['number'] ?? 1),
                                'house_id' => (int)($livestockProfile['house_id'] ?? 0),
                                'feeding_schedule' => (string)($livestockProfile['feeding_schedule'] ?? 'Morning and evening'),
                                'for_sale' => (string)($livestockProfile['for_sale'] ?? '0'),
                                'purpose' => (string)($livestockProfile['purpose'] ?? ''),
                                'health_status' => (string)($livestockProfile['health_status'] ?? 'Healthy'),
                                'notes' => (string)($livestockProfile['notes'] ?? '')
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                <span class="material-symbols-rounded">edit</span>
                                Edit Profile
                            </button>
                            <?php endif; ?>
                        </div>
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

                            $forSaleValue = $livestockProfile['for_sale'] ?? null;
                            if (!$livestockProfile) {
                                echo '-';
                            } elseif (
                                (is_numeric($forSaleValue) && (int)$forSaleValue === 1)
                                || in_array(strtolower(trim((string)$forSaleValue)), ['1', 'yes', 'true'], true)
                            ) {

                                echo 'Yes';

                            } else {

                                echo 'No';

                            }

                            ?>

                        </strong>

                    </div>


                </div>

            </div>


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

                <div class="snf-monitoring-actions" style="display:flex;align-items:center;justify-content:flex-end;gap:7px;flex-wrap:wrap;margin-left:auto;">
                <?php if (in_array($role, ['admin', 'livestock staff'], true)): ?>
                    <div class="snf-download-actions">
                        <a href="../export_records.php?report=livestock&amp;format=xlsx" title="Download Excel">Download Excel</a>
                        <a href="../export_records.php?report=livestock&amp;format=pdf" title="Download PDF">Download PDF</a>
                    </div>
                <?php endif; ?>
                <?php if ($canManageLivestock && $selectedLivestockId > 0): ?>
                    <button type="button"
                            class="monitoring-add-btn"
                            onclick="openMonitoringModal()">
                        <span class="material-symbols-rounded">add</span>
                        Add Record
                    </button>
                <?php endif; ?>
                </div>
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
                    <?php
                    $requestedMonitoringTab = trim((string)($_GET['section'] ?? ''));
                    $firstTab = array_key_first($monitoringTabs);
                    if ($requestedMonitoringTab !== '' && array_key_exists($requestedMonitoringTab, $monitoringTabs)) {
                        $firstTab = $requestedMonitoringTab;
                    }
                    ?>
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
                                        <?php if ($canManageLivestock): ?><th>Action</th><?php endif; ?>
                                    </tr></thead>
                                    <tbody>
                                                                        <?php if ($weightRows): foreach ($weightRows as $r): ?>                                        <tr data-record-row="1" data-search="<?= htmlspecialchars(strtolower(implode(' ', array_map('strval', $r))), ENT_QUOTES) ?>" data-month="<?= htmlspecialchars(substr((string)($r['record_date'] ?? $r['month_year'] ?? ''),0,7), ENT_QUOTES) ?>" data-month-label="<?= htmlspecialchars((string)($r['record_date'] ?? $r['month_year'] ?? ''), ENT_QUOTES) ?>">
                                            <td><?= htmlspecialchars($r['record_date']) ?></td>
                                            <td><?= htmlspecialchars($r['rooster_banaba'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['rooster_paraokan'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['hen_banaba'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['hen_paraokan'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['average_weight'] ?? '-') ?></td>
                                            <?php if ($canManageLivestock): ?>
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
                                        <tr><td colspan="<?= $canManageLivestock ? 7 : 6 ?>" class="monitor-empty">No weight records yet.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- CHICKEN EGGS -->
                        <div class="monitoring-panel <?= $firstTab === 'eggs' ? 'active' : '' ?>" data-monitoring-panel="eggs">
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
                                        <?php if ($canManageLivestock): ?><th>Action</th><?php endif; ?>
                                    </tr></thead>
                                    <tbody>
                                                                        <?php if ($eggRows): foreach ($eggRows as $r): ?>                                        <tr data-record-row="1" data-search="<?= htmlspecialchars(strtolower(implode(' ', array_map('strval', $r))), ENT_QUOTES) ?>" data-month="<?= htmlspecialchars(substr((string)($r['record_date'] ?? $r['month_year'] ?? ''),0,7), ENT_QUOTES) ?>" data-month-label="<?= htmlspecialchars((string)($r['record_date'] ?? $r['month_year'] ?? ''), ENT_QUOTES) ?>">
                                            <td><?= htmlspecialchars($r['record_date']) ?></td><td><?= htmlspecialchars($r['days'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['feed_formula'] ?? '-') ?></td><td><?= htmlspecialchars($r['egg_count'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['lightest_weight'] ?? '-') ?></td><td><?= htmlspecialchars($r['heaviest_weight'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['hatching'] ?? '-') ?></td>
                                            <?php if ($canManageLivestock): ?><td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("chicken_eggs", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('chicken_eggs', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td><?php endif; ?>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="<?= $canManageLivestock ? 8 : 7 ?>" class="monitor-empty">No egg records yet.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- CHICKEN MORTALITY -->
                        <div class="monitoring-panel <?= $firstTab === 'mortality' ? 'active' : '' ?>" data-monitoring-panel="mortality">
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
                                    <thead><tr><th>Date</th><th>No.</th><th>Type Chicken</th><th>Breed</th><th>Cause of Death</th><?php if ($canManageLivestock): ?><th>Action</th><?php endif; ?></tr></thead>
                                    <tbody>
                                                                        <?php if ($mortalityRows): foreach ($mortalityRows as $r): ?>                                        <tr data-record-row="1" data-search="<?= htmlspecialchars(strtolower(implode(' ', array_map('strval', $r))), ENT_QUOTES) ?>" data-month="<?= htmlspecialchars(substr((string)($r['record_date'] ?? $r['month_year'] ?? ''),0,7), ENT_QUOTES) ?>" data-month-label="<?= htmlspecialchars((string)($r['record_date'] ?? $r['month_year'] ?? ''), ENT_QUOTES) ?>">
                                            <td><?= htmlspecialchars($r['record_date']) ?></td><td><?= htmlspecialchars($r['quantity'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['chicken_type'] ?? '-') ?></td><td><?= htmlspecialchars($r['breed'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['cause_of_death'] ?? '-') ?></td>
                                            <?php if ($canManageLivestock): ?><td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("chicken_mortality", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('chicken_mortality', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td><?php endif; ?>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="<?= $canManageLivestock ? 6 : 5 ?>" class="monitor-empty">No mortality records yet.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- CHICKEN FEED -->
                        <div class="monitoring-panel <?= $firstTab === 'feed' ? 'active' : '' ?>" data-monitoring-panel="feed">
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
                                    <thead><tr><th>Formulation Code</th><th>Feed Ingredients</th><th>Quantity</th><?php if ($canManageLivestock): ?><th>Action</th><?php endif; ?></tr></thead>
                                    <tbody>
                                                                        <?php if ($feedRows): foreach ($feedRows as $r): ?>                                        <tr><td><?= htmlspecialchars($r['formulation_code'] ?? '-') ?></td><td><?= htmlspecialchars($r['feed_ingredients'] ?? '-') ?></td><td><?= htmlspecialchars($r['quantity'] ?? '-') ?></td>
                                        <?php if ($canManageLivestock): ?><td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("chicken_feed", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('chicken_feed', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td><?php endif; ?></tr>
                                    <?php endforeach; else: ?><tr><td colspan="<?= $canManageLivestock ? 4 : 3 ?>" class="monitor-empty">No feed ingredient records yet.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- CHICKEN WATER -->
                        <div class="monitoring-panel <?= $firstTab === 'water' ? 'active' : '' ?>" data-monitoring-panel="water">
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
                                    <thead><tr><th>Date</th><th>Water Code</th><th>Number of Waterers</th><th>ML per 16-liter Water</th><?php if ($canManageLivestock): ?><th>Action</th><?php endif; ?></tr></thead>
                                    <tbody>
                                                                        <?php if ($waterRows): foreach ($waterRows as $r): ?>                                        <tr data-record-row="1" data-search="<?= htmlspecialchars(strtolower(implode(' ', array_map('strval', $r))), ENT_QUOTES) ?>" data-month="<?= htmlspecialchars(substr((string)($r['record_date'] ?? $r['month_year'] ?? ''),0,7), ENT_QUOTES) ?>" data-month-label="<?= htmlspecialchars((string)($r['record_date'] ?? $r['month_year'] ?? ''), ENT_QUOTES) ?>"><td><?= htmlspecialchars($r['record_date']) ?></td><td><?= htmlspecialchars($r['water_code'] ?? '-') ?></td><td><?= htmlspecialchars($r['number_of_waterers'] ?? '-') ?></td><td><?= htmlspecialchars($r['ml_per_16_liter'] ?? '-') ?></td>
                                        <?php if ($canManageLivestock): ?><td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("chicken_water", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('chicken_water', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td><?php endif; ?></tr>
                                    <?php endforeach; else: ?><tr><td colspan="<?= $canManageLivestock ? 5 : 4 ?>" class="monitor-empty">No drinking-water records yet.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- CHICKEN FEEDING SCHEDULE -->
                        <div class="monitoring-panel <?= $firstTab === 'feeding' ? 'active' : '' ?>" data-monitoring-panel="feeding">
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
                                        <?php if ($canManageLivestock): ?><th>Action</th><?php endif; ?>
                                    </tr></thead>
                                    <tbody>
                                                                        <?php if ($feedingRows): foreach ($feedingRows as $r): ?>                                        <tr data-record-row="1" data-search="<?= htmlspecialchars(strtolower(implode(' ', array_map('strval', $r))), ENT_QUOTES) ?>" data-month="<?= htmlspecialchars(substr((string)($r['record_date'] ?? $r['month_year'] ?? ''),0,7), ENT_QUOTES) ?>" data-month-label="<?= htmlspecialchars((string)($r['record_date'] ?? $r['month_year'] ?? ''), ENT_QUOTES) ?>">
                                            <td><?= htmlspecialchars($r['month_year']) ?></td><td><?= htmlspecialchars($r['feeding_code'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['morning'] ?? '-') ?></td><td><?= htmlspecialchars($r['afternoon'] ?? '-') ?></td><td><?= htmlspecialchars($r['snacks'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['evening'] ?? '-') ?></td><td><?= htmlspecialchars($r['night_visit'] ?? '-') ?></td><td><?= htmlspecialchars($r['eggs_collected'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['average_egg_weight'] ?? '-') ?></td><td><?= htmlspecialchars($r['average_layer_weight'] ?? '-') ?></td><td><?= htmlspecialchars($r['average_rooster_weight'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['housing_nest_disinfection'] ?? '-') ?></td><td><?= htmlspecialchars($r['general_cleaning'] ?? '-') ?></td><td><?= htmlspecialchars($r['chicken_bath'] ?? '-') ?></td>
                                            <?php if ($canManageLivestock): ?><td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("chicken_feeding", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('chicken_feeding', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td><?php endif; ?>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="<?= $canManageLivestock ? 15 : 14 ?>" class="monitor-empty">No feeding schedule records yet.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php elseif ($isGoat): ?>

                        <!-- GOAT DAILY MONITORING -->
                        <div class="monitoring-panel <?= $firstTab === 'daily' ? 'active' : '' ?>" data-monitoring-panel="daily">
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
                                    <thead><tr><th>Date</th><th>Status</th><th>Free Range Schedule</th><th>Type of Drink</th><th>Disinfect / Cleaning</th><th>Notes / Observation</th><?php if ($canManageLivestock): ?><th>Action</th><?php endif; ?></tr></thead>
                                    <tbody>
                                                                        <?php if ($goatRows): foreach ($goatRows as $r): ?>                                        <tr data-record-row="1" data-search="<?= htmlspecialchars(strtolower(implode(' ', array_map('strval', $r))), ENT_QUOTES) ?>" data-month="<?= htmlspecialchars(substr((string)($r['record_date'] ?? $r['month_year'] ?? ''),0,7), ENT_QUOTES) ?>" data-month-label="<?= htmlspecialchars((string)($r['record_date'] ?? $r['month_year'] ?? ''), ENT_QUOTES) ?>">
                                            <td><?= htmlspecialchars($r['record_date']) ?></td><td><?= htmlspecialchars($r['status'] ?? '-') ?></td><td><?= htmlspecialchars($r['free_range_schedule'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['drink_type'] ?? '-') ?></td><td><?= htmlspecialchars($r['disinfect_cleaning'] ?? '-') ?></td><td><?= htmlspecialchars($r['notes'] ?? '-') ?></td>
                                            <?php if ($canManageLivestock): ?><td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("goat_daily", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('goat_daily', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td><?php endif; ?>
                                        </tr>
                                    <?php endforeach; else: ?><tr><td colspan="<?= $canManageLivestock ? 7 : 6 ?>" class="monitor-empty">No goat monitoring records yet.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- GOAT FORAGES -->
                        <div class="monitoring-panel <?= $firstTab === 'forage' ? 'active' : '' ?>" data-monitoring-panel="forage">
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
                                    <thead><tr><th>Date</th><th>Forage</th><th>Quantity</th><th>Notes</th><?php if ($canManageLivestock): ?><th>Action</th><?php endif; ?></tr></thead>
                                    <tbody>
                                                                        <?php if ($forageRows): foreach ($forageRows as $r): ?>                                        <tr data-record-row="1" data-search="<?= htmlspecialchars(strtolower(implode(' ', array_map('strval', $r))), ENT_QUOTES) ?>" data-month="<?= htmlspecialchars(substr((string)($r['record_date'] ?? $r['month_year'] ?? ''),0,7), ENT_QUOTES) ?>" data-month-label="<?= htmlspecialchars((string)($r['record_date'] ?? $r['month_year'] ?? ''), ENT_QUOTES) ?>"><td><?= htmlspecialchars($r['record_date']) ?></td><td><?= htmlspecialchars($r['forage_name'] ?? '-') ?></td><td><?= htmlspecialchars($r['quantity'] ?? '-') ?></td><td><?= htmlspecialchars($r['notes'] ?? '-') ?></td>
                                        <?php if ($canManageLivestock): ?><td class="monitor-action-cell">
                                                    <button type="button" class="monitor-action edit" title="Edit record" aria-label="Edit record" onclick='editMonitoringRecord("goat_forage", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                                        <span class="material-symbols-rounded">edit</span>
                                                    </button>
                                                    <button type="button" class="monitor-action archive" title="Archive record" aria-label="Archive record" onclick="archiveMonitoring('goat_forage', <?= (int)$r['id'] ?>)">
                                                        <span class="material-symbols-rounded">archive</span>
                                                    </button>
                                                </td><?php endif; ?></tr>
                                    <?php endforeach; else: ?><tr><td colspan="<?= $canManageLivestock ? 5 : 4 ?>" class="monitor-empty">No forage records yet.</td></tr><?php endif; ?>
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

<!-- ADD LIVESTOCK PROFILE MODAL -->
<div class="add-profile-modal-overlay" id="addLivestockProfileModal">
    <div class="add-profile-modal">
        <div class="monitoring-modal-header">
            <div>
                <h3 id="livestockProfileModalTitle">Add Livestock Profile</h3>
                <span id="livestockProfileModalSubtitle">Create a profile for Chicken, Goat, Pig, or Rabbit.</span>
            </div>
            <button type="button" class="monitoring-close" onclick="closeAddLivestockModal()" aria-label="Close">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <form method="POST" class="add-profile-form" id="addLivestockProfileForm">
            <input type="hidden" name="create_livestock_profile" id="createLivestockProfileFlag" value="1">
            <input type="hidden" name="update_livestock_profile" id="updateLivestockProfileFlag" value="1" disabled>
            <input type="hidden" name="edit_page_id" id="edit_page_id" value="">
            <input type="hidden" name="edit_pig_id" id="edit_pig_id" value="">
            <input type="hidden" name="edit_map_livestock_id" id="edit_map_livestock_id" value="">
            <div class="add-profile-fields">
                <div class="add-profile-field">
                    <label for="profile_type">Livestock Type *</label>
                    <select id="profile_type" name="profile_type" required onchange="toggleAddProfileFields()">
                        <?php foreach (['Chicken', 'Goat', 'Pig', 'Rabbit'] as $addType): ?>
                            <option value="<?= htmlspecialchars($addType, ENT_QUOTES) ?>" <?= strcasecmp((string)($livestockProfile['type'] ?? $selectedType), $addType) === 0 ? 'selected' : '' ?>><?= htmlspecialchars($addType) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="add-profile-field">
                    <label for="profile_name">Name *</label>
                    <input id="profile_name" name="profile_name" type="text" maxlength="150" required>
                </div>
                <div class="add-profile-field pig-profile-field" id="pigTagField">
                    <label for="profile_tag_number">Tag Number *</label>
                    <input id="profile_tag_number" name="profile_tag_number" type="text" maxlength="100">
                </div>
                <div class="add-profile-field">
                    <label for="profile_breed">Breed</label>
                    <input id="profile_breed" name="profile_breed" type="text" maxlength="150">
                </div>
                <div class="add-profile-field">
                    <label for="profile_date_of_birth">Date of Birth</label>
                    <input id="profile_date_of_birth" name="profile_date_of_birth" type="date">
                </div>
                <div class="add-profile-field">
                    <label for="profile_sex">Sex *</label>
                    <select id="profile_sex" name="profile_sex" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Mixed" id="profileMixedSexOption">Mixed</option>
                    </select>
                </div>
                <div class="add-profile-field">
                    <label for="profile_weight">Estimated Weight (kg)</label>
                    <input id="profile_weight" name="profile_weight" type="number" min="0" step="0.01">
                </div>
                <div class="add-profile-field regular-profile-field" id="profileNumberField">
                    <label for="profile_number">Number *</label>
                    <input id="profile_number" name="profile_number" type="number" min="1" step="1" value="1">
                </div>
                <div class="add-profile-field" id="profileHouseField">
                    <label for="profile_house_id">Livestock House</label>
                    <select id="profile_house_id" name="profile_house_id" required>
                        <option value="">Select livestock house *</option>
                        <?php foreach ($houseOptions as $houseOption): ?>
                            <option value="<?= (int)$houseOption['house_id'] ?>"><?= htmlspecialchars($houseOption['house_name'] . (!empty($houseOption['house_code']) ? ' (' . $houseOption['house_code'] . ')' : '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="add-profile-field regular-profile-field" id="profileFeedingField">
                    <label for="profile_feeding_schedule">Feeding Schedule *</label>
                    <input id="profile_feeding_schedule" name="profile_feeding_schedule" type="text" placeholder="e.g. Morning and evening">
                </div>
                <div class="add-profile-field regular-profile-field" id="profileForSaleField">
                    <label for="profile_for_sale">For Sale</label>
                    <select id="profile_for_sale" name="profile_for_sale">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="add-profile-field pig-profile-field" id="pigPurposeField">
                    <label for="profile_purpose">Purpose</label>
                    <select id="profile_purpose" name="profile_purpose">
                        <option value="">Select purpose</option>
                        <option value="Breeding">Breeding</option>
                        <option value="Meat Production">Meat Production</option>
                        <option value="Sale">Sale</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="add-profile-field pig-profile-field" id="pigHealthField">
                    <label for="profile_health_status">Health Status</label>
                    <select id="profile_health_status" name="profile_health_status">
                        <option value="Healthy">Healthy</option>
                        <option value="Under Observation">Under Observation</option>
                        <option value="Sick">Sick</option>
                    </select>
                </div>
                <div class="add-profile-field full">
                    <label for="profile_notes">Notes</label>
                    <textarea id="profile_notes" name="profile_notes" maxlength="5000"></textarea>
                </div>
            </div>
            <div class="monitor-form-actions">
                <button type="button" class="monitor-cancel" onclick="closeAddLivestockModal()">Cancel</button>
                <button type="submit" class="monitor-save" id="livestockProfileSubmitButton">Save Profile</button>
            </div>
        </form>
    </div>
</div>

<script>
// Keep the livestock type and name selectors connected to the page data.
// Changing type reloads the matching records; changing name opens that profile.
function changeLivestockType(type){
    const params = new URLSearchParams(window.location.search);
    if (type) params.set('type', type);
    else params.delete('type');
    // A profile ID belongs to the previously selected type, so clear it.
    params.delete('livestock_id');
    window.location.href = 'livestocks.php' + (params.toString() ? '?' + params.toString() : '');
}
function changeLivestock(id){
    const params = new URLSearchParams(window.location.search);
    if (id !== undefined && id !== null && String(id) !== '') params.set('livestock_id', String(id));
    else params.delete('livestock_id');
    window.location.href = 'livestocks.php' + (params.toString() ? '?' + params.toString() : '');
}

function openAddLivestockModal(){
    const modal=document.getElementById('addLivestockProfileModal');
    if(!modal) return;
    toggleAddProfileFields();
    modal.classList.add('active');
    document.body.style.overflow='hidden';
}
function closeAddLivestockModal(){
    const modal=document.getElementById('addLivestockProfileModal');
    if(modal) modal.classList.remove('active');
    document.body.style.overflow='';
    resetLivestockProfileModal();
}
function toggleAddProfileFields(){
    const type=document.getElementById('profile_type')?.value || '';
    const isPig=type.toLowerCase()==='pig';
    document.querySelectorAll('.pig-profile-field').forEach(el=>{el.style.display=isPig?'':'none';});
    document.querySelectorAll('.regular-profile-field').forEach(el=>{el.style.display=isPig?'none':'';});
    // The map requires every active livestock profile to have a house assignment.
    const houseField=document.getElementById('profileHouseField');
    if(houseField) houseField.style.display='block';
    const houseSelect=document.getElementById('profile_house_id');
    if(houseSelect) houseSelect.required=true;
    const tag=document.getElementById('profile_tag_number');
    const feeding=document.getElementById('profile_feeding_schedule');
    const number=document.getElementById('profile_number');
    if(tag) tag.required=isPig;
    if(feeding) feeding.required=!isPig;
    if(number) number.required=!isPig;
    const mixed=document.getElementById('profileMixedSexOption');
    if(mixed){mixed.hidden=isPig;if(isPig && document.getElementById('profile_sex').value==='Mixed') document.getElementById('profile_sex').value='Male';}
}
function openEditLivestockModal(profile){
    const modal=document.getElementById('addLivestockProfileModal');
    const form=document.getElementById('addLivestockProfileForm');
    if(!modal || !form) return;
    document.getElementById('createLivestockProfileFlag').disabled=true;
    document.getElementById('updateLivestockProfileFlag').disabled=false;
    document.getElementById('updateLivestockProfileFlag').value='1';
    document.getElementById('edit_page_id').value=profile.page_id || 0;
    document.getElementById('edit_pig_id').value=profile.pig_id || 0;
    document.getElementById('edit_map_livestock_id').value=profile.map_id || 0;
    document.getElementById('livestockProfileModalTitle').textContent='Edit Livestock Profile';
    document.getElementById('livestockProfileModalSubtitle').textContent='Update the selected livestock profile.';
    document.getElementById('livestockProfileSubmitButton').textContent='Update Profile';
    for(const [field,value] of Object.entries({
      profile_type:profile.type, profile_name:profile.name, profile_tag_number:profile.tag_number,
      profile_breed:profile.breed, profile_date_of_birth:profile.date_of_birth, profile_sex:profile.sex,
      profile_weight:profile.weight, profile_number:profile.number, profile_house_id:profile.house_id,
      profile_feeding_schedule:profile.feeding_schedule, profile_for_sale:profile.for_sale,
      profile_purpose:profile.purpose, profile_health_status:profile.health_status, profile_notes:profile.notes
    })){
      const el=document.getElementById(field); if(el && value !== undefined && value !== null) el.value=value;
    }
    toggleAddProfileFields();
    modal.classList.add('active'); document.body.style.overflow='hidden';
}
function resetLivestockProfileModal(){
    const form=document.getElementById('addLivestockProfileForm'); if(form) form.reset();
    document.getElementById('createLivestockProfileFlag').disabled=false;
    document.getElementById('updateLivestockProfileFlag').value='1';
    document.getElementById('updateLivestockProfileFlag').disabled=true;
    document.getElementById('edit_page_id').value='';
    document.getElementById('edit_pig_id').value='';
    document.getElementById('edit_map_livestock_id').value='';
    document.getElementById('livestockProfileModalTitle').textContent='Add Livestock Profile';
    document.getElementById('livestockProfileModalSubtitle').textContent='Create a profile for Chicken, Goat, Pig, or Rabbit.';
    document.getElementById('livestockProfileSubmitButton').textContent='Save Profile';
    toggleAddProfileFields();
}
document.addEventListener('DOMContentLoaded', function () {
    const monitoringTabs = document.querySelector('.monitoring-tabs');
    const headerTools = document.querySelector('.livestock-header-right');
    if (monitoringTabs && headerTools) {
        monitoringTabs.appendChild(headerTools);
    }
});
document.addEventListener('DOMContentLoaded',function(){
    toggleAddProfileFields();
    const addModal=document.getElementById('addLivestockProfileModal');
    if(addModal) addModal.addEventListener('click',function(event){if(event.target===addModal) closeAddLivestockModal();});
});
</script>

<script>
function switchMonitoringTab(tabKey){
    document.querySelectorAll('.monitoring-tab').forEach(function(tab){
        tab.classList.toggle('active', tab.dataset.monitoringTab === tabKey);
    });
    document.querySelectorAll('.monitoring-panel').forEach(function(panel){
        panel.classList.toggle('active', panel.dataset.monitoringPanel === tabKey);
    });
    const tabToRecord={weight:'chicken_weight',eggs:'chicken_eggs',mortality:'chicken_mortality',feed:'chicken_feed',water:'chicken_water',feeding:'chicken_feeding',daily:'goat_daily',forage:'goat_forage'};
    const url=new URL(window.location.href);
    url.searchParams.set('tab',tabToRecord[tabKey]||tabKey);
    window.history.replaceState(null,'',url.pathname+url.search+url.hash);
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
    const saved=new URLSearchParams(window.location.search).get('tab');
    const tabFromRecord={chicken_weight:'weight',chicken_eggs:'eggs',chicken_mortality:'mortality',chicken_feed:'feed',chicken_water:'water',chicken_feeding:'feeding',goat_daily:'daily',goat_forage:'forage'};
    const restored=tabFromRecord[saved]||saved;
    const matching=Array.from(document.querySelectorAll('.monitoring-tab')).find(el=>el.dataset.monitoringTab===restored);
    const first=matching||document.querySelector('.monitoring-tab.active');
    if(first) switchMonitoringTab(first.dataset.monitoringTab);
});
</script>


</body>

</html>