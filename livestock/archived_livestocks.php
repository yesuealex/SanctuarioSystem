<?php
session_start();
include '../db.php';
require_once '../archive/archive_functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../records.php?type=livestock");
    exit();
}

$monitoringTables = [
    'Chicken' => [
        'Weight' => 'chicken_weight_records',
        'Eggs' => 'chicken_egg_records',
        'Mortality' => 'chicken_mortality_records',
        'Feed Ingredients' => 'chicken_feed_ingredients',
        'Drinking Water / DW' => 'chicken_drinking_water',
        'Feeding Schedule' => 'chicken_feeding_schedule'
    ],
    'Goat' => [
        'Daily Monitoring' => 'goat_monitoring_records',
        'Forages' => 'goat_forage_records'
    ],
    'Rabbit' => [],
    'Swine' => []
];

$selectedType = trim((string)($_GET['type'] ?? ''));
$selectedSection = trim((string)($_GET['section'] ?? 'profile'));
$error = '';
$success = '';

try {
    ensureArchiveTable($conn, 'livestocks');
} catch (Throwable $e) {
    $error = 'Archive setup failed: ' . $e->getMessage();
}

function archiveTableHasColumn(mysqli $conn, string $table, string $column): bool {
    $safe = str_replace('`', '', $table);
    $result = $conn->query("SHOW COLUMNS FROM `{$safe}` LIKE '" . $conn->real_escape_string($column) . "'");
    return $result && $result->num_rows > 0;
}

function archivedRowsFor(mysqli $conn, string $sourceTable, int $livestockId = 0): array {
    // Monitoring records are archived individually. Many existing records use
    // livestock_id = 0, so filtering by an archived animal ID hides them.
    // The matching archived_<table> contains only records from that section.
    try {
        if (!archiveTableExists($conn, $sourceTable)) return [];
        $archiveTable = archiveTableName($sourceTable);
        $columnsResult = $conn->query("SHOW COLUMNS FROM `{$archiveTable}`");
        if (!$columnsResult) return [];
        $columnNames = [];
        while ($column = $columnsResult->fetch_assoc()) $columnNames[] = $column['Field'];
        if (!$columnNames) return [];
        $orderColumn = in_array('archived_at', $columnNames, true) ? 'archived_at' : (in_array('id', $columnNames, true) ? 'id' : $columnNames[0]);
        $orderDirection = $orderColumn === 'archived_at' ? 'DESC' : 'DESC';
        $stmt = $conn->prepare("SELECT * FROM `{$archiveTable}` ORDER BY `{$orderColumn}` {$orderDirection}");
        if (!$stmt) return [];
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['archive_action'] ?? '');
    $livestockId = (int)($_POST['livestock_id'] ?? 0);
    $returnType = trim((string)($_POST['return_type'] ?? ''));
    $returnSection = trim((string)($_POST['return_section'] ?? 'profile'));

    // Restore a single monitoring row (for example, one Goat > Forages record).
    // This is separate from restoring an entire livestock profile and also supports livestock_id = 0.
    if ($action === 'restore_monitoring') {
        $recordId = (int)($_POST['record_id'] ?? 0);
        $monitoringTable = (string)($_POST['monitoring_table'] ?? '');
        $allowedMonitoringTables = [];
        foreach ($monitoringTables as $typeSections) {
            foreach ($typeSections as $tableName) $allowedMonitoringTables[] = $tableName;
        }
        if ($recordId > 0 && in_array($monitoringTable, $allowedMonitoringTables, true)) {
            try {
                $conn->begin_transaction();
                $archiveTable = archiveTableName($monitoringTable);
                $select = $conn->prepare("SELECT * FROM `{$archiveTable}` WHERE `id`=? LIMIT 1");
                if (!$select) throw new RuntimeException('Could not prepare archived record lookup.');
                $select->bind_param('i', $recordId);
                $select->execute();
                $archivedResult = $select->get_result();
                $archivedRow = $archivedResult ? $archivedResult->fetch_assoc() : null;
                $select->close();
                if (!$archivedRow) throw new RuntimeException('This archived record could not be found. Refresh the page and try again.');

                $activeColumnResult = $conn->query("SHOW COLUMNS FROM `{$monitoringTable}`");
                if (!$activeColumnResult) throw new RuntimeException('Could not read the active monitoring table.');
                $activeColumns = [];
                while ($column = $activeColumnResult->fetch_assoc()) $activeColumns[] = $column['Field'];
                $insertColumns = [];
                $insertValues = [];
                foreach ($activeColumns as $columnName) {
                    if (!array_key_exists($columnName, $archivedRow)) continue;
                    if (in_array(strtolower($columnName), ['archived_at', 'archived_by'], true)) continue;
                    $insertColumns[] = $columnName;
                    $insertValues[] = strtolower($columnName) === 'archived' ? 0 : $archivedRow[$columnName];
                }
                if (!$insertColumns || !in_array('id', $insertColumns, true)) {
                    throw new RuntimeException('The active table does not have the expected record ID column.');
                }
                $quotedColumns = array_map(static fn($column) => '`' . str_replace('`', '', $column) . '`', $insertColumns);
                $placeholders = implode(',', array_fill(0, count($insertValues), '?'));
                $insert = $conn->prepare("INSERT INTO `{$monitoringTable}` (" . implode(',', $quotedColumns) . ") VALUES ({$placeholders})");
                if (!$insert) throw new RuntimeException('Could not prepare record restore. The record may already exist in the active table.');
                $bindTypes = str_repeat('s', count($insertValues));
                $insert->bind_param($bindTypes, ...$insertValues);
                if (!$insert->execute()) throw new RuntimeException('Could not restore record. It may already exist in the active table.');
                $insert->close();

                $delete = $conn->prepare("DELETE FROM `{$archiveTable}` WHERE `id`=?");
                if (!$delete) throw new RuntimeException('The record was restored, but the archive copy could not be removed.');
                $delete->bind_param('i', $recordId);
                if (!$delete->execute() || $delete->affected_rows !== 1) throw new RuntimeException('Could not remove the restored archive copy.');
                $delete->close();
                $conn->commit();
                header('Location: archived_livestocks.php?restored_record=1&type=' . urlencode($returnType) . '&section=' . urlencode($returnSection));
                exit();
            } catch (Throwable $e) {
                $conn->rollback();
                $error = 'Restore failed: ' . $e->getMessage();
            }
        } else {
            $error = 'Invalid monitoring record selected for restore.';
        }
    }

    if ($livestockId > 0) {
        try {
            $conn->begin_transaction();
            if ($action === 'restore') {
                restoreRecord($conn, 'livestocks', 'livestock_id', $livestockId);
                foreach (['chicken_weight_records','chicken_egg_records','chicken_mortality_records','chicken_feed_ingredients','chicken_drinking_water','chicken_feeding_schedule','goat_monitoring_records','goat_forage_records'] as $table) {
                    if (archiveTableExists($conn, $table)) restoreLivestockRelated($conn, $table, $livestockId);
                }
                $conn->commit();
                header('Location: archived_livestocks.php?restored=1&type=' . urlencode($returnType) . '&section=' . urlencode($returnSection));
                exit();
            } elseif ($action === 'delete') {
                permanentlyDeleteArchived($conn, 'livestocks', 'livestock_id', $livestockId);
                foreach (['chicken_weight_records','chicken_egg_records','chicken_mortality_records','chicken_feed_ingredients','chicken_drinking_water','chicken_feeding_schedule','goat_monitoring_records','goat_forage_records'] as $table) {
                    if (archiveTableExists($conn, $table)) {
                        $archive = archiveTableName($table);
                        $stmt = $conn->prepare("DELETE FROM `{$archive}` WHERE livestock_id=?");
                        if ($stmt) { $stmt->bind_param('i', $livestockId); $stmt->execute(); $stmt->close(); }
                    }
                }
                $conn->commit();
                header('Location: archived_livestocks.php?deleted=1&type=' . urlencode($returnType) . '&section=' . urlencode($returnSection));
                exit();
            }
            $conn->rollback();
        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

if (isset($_GET['archived_record'])) $success = 'Monitoring record archived successfully.';
if (isset($_GET['restored'])) $success = 'Livestock record and its archived monitoring records were restored.';
if (isset($_GET['restored_record'])) $success = 'Monitoring record restored successfully and returned to its active section.';
if (isset($_GET['deleted'])) $success = 'Livestock record and its archived monitoring records were permanently deleted.';

$types = ['Chicken', 'Goat', 'Rabbit', 'Swine'];
$typeResult = $conn->query("SELECT DISTINCT type FROM archived_livestocks WHERE type IS NOT NULL AND TRIM(type)<>'' ORDER BY type");
if ($typeResult) {
    while ($row = $typeResult->fetch_assoc()) {
        $value = trim((string)$row['type']);
        $canonical = preg_match('/^(pig|swine)$/i', $value) ? 'Swine' : $value;
        if (!in_array($canonical, $types, true)) $types[] = $canonical;
    }
}
if ($selectedType === '') $selectedType = 'Chicken';
if (strcasecmp($selectedType, 'Swine') === 0 || strcasecmp($selectedType, 'Pig') === 0) {
    $livestocksResult = $conn->query("SELECT * FROM archived_livestocks WHERE LOWER(type) IN ('pig','swine') ORDER BY livestock_id DESC");
    $livestockRows = $livestocksResult ? $livestocksResult->fetch_all(MYSQLI_ASSOC) : [];
} else {
    $stmt = $conn->prepare("SELECT * FROM archived_livestocks WHERE LOWER(type)=LOWER(?) ORDER BY livestock_id DESC");
    if ($stmt) {
        $stmt->bind_param('s', $selectedType);
        $stmt->execute();
        $livestocksResult = $stmt->get_result();
        $livestockRows = $livestocksResult ? $livestocksResult->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    } else {
        $livestockRows = [];
    }
}

/* If a type has no matching animals, keep the profile section usable and empty. */
$sectionOptions = ['profile' => 'Livestock Records'];
$monitoringKey = $selectedType;
if (strcasecmp($selectedType, 'Pig') === 0) $monitoringKey = 'Swine';
if (isset($monitoringTables[$monitoringKey])) {
    foreach ($monitoringTables[$monitoringKey] as $label => $table) $sectionOptions[$table] = $label;
}
$selectedSection = isset($sectionOptions[$selectedSection]) ? $selectedSection : 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Archived Livestocks</title>
<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="../sidebar.css">
<link rel="stylesheet" href="livestocks.css?v=8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:wght@400" rel="stylesheet">
<style>
body{font-family:'Poppins',sans-serif}.layout{display:flex}.dashboard{margin-left:260px;padding:20px;width:100%;box-sizing:border-box}
.archive-page{width:100%}.page-header{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:20px}
.page-header h1{margin:0;font-size:1.7rem;color:#385f30}.page-header p{margin:5px 0 0;color:#777;font-size:.9rem}
.back-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 14px;background:#f1f5f9;color:#333;border-radius:8px;text-decoration:none;font-size:.85rem}
.archive-controls{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px}
.archive-select{height:38px;min-width:160px;border:0;border-radius:20px;padding:0 14px;font:500 .82rem Poppins,sans-serif;background:#2f6b2f;color:#fff;cursor:pointer}
.archive-select.section{background:#e2e2e2;color:#333;min-width:210px}
.archive-section-buttons{display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex:1 1 auto;min-width:220px;margin-top:0}
.archive-section-button{border:1px solid #d8e2d3;background:#fff;color:#55704f;border-radius:8px;padding:9px 13px;font:600 .76rem Poppins,sans-serif;white-space:nowrap;cursor:pointer;transition:background .15s ease,color .15s ease,border-color .15s ease}
.archive-section-button:hover{border-color:#4f7d3a;background:#f3f8f0}
.archive-section-button.active{background:#4f7d3a;color:#fff;border-color:#4f7d3a}

.archive-toolbar{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:15px}
.search-box{width:340px;max-width:100%;display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #ddd;border-radius:8px;padding:9px 12px}
.search-box input{width:100%;border:0;outline:none;font: .9rem Poppins,sans-serif}.archive-count{color:#777;font-size:.85rem}
.table-container{width:100%;max-width:100%;overflow:auto;background:#fff;border:1px solid #d6e0d1;border-radius:12px;max-height:65vh;-webkit-overflow-scrolling:touch;scrollbar-color:#8aa27d #eef2eb}
table{width:100%;min-width:1100px;border-collapse:collapse;table-layout:auto;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.05);font-family:'Poppins',sans-serif}
th,td{padding:10px 12px;border:1px solid #d6e0d1;text-align:left;line-height:1.45;white-space:normal;overflow-wrap:anywhere;word-break:normal;vertical-align:middle}
th{background:#e3ebdc;font-size:13px;font-weight:600;color:#365a31;position:sticky;top:0;z-index:2}
td{background:#fff;color:#344234;font-size:14px;font-weight:400}
#archiveTable tbody > tr > td,#archiveTable tbody > tr:hover > td{background:#fff!important;background-color:#fff!important;background-image:none!important}
.action-buttons{display:flex;justify-content:center;align-items:center;gap:8px;flex-wrap:wrap;min-width:112px}.action-buttons form{margin:0;flex:0 0 auto}.action-icon{width:35px;height:35px;border:0;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
.restore-icon{background:#e8f5e9;color:#2f6b2f}.delete-icon{background:#fdecec;color:#d32f2f}.no-records{text-align:center;color:#888;padding:35px}
.archive-message{margin-bottom:15px;padding:11px 14px;border-radius:8px;background:#e8f5e9;color:#2f6b2f;font-size:.88rem}.archive-message.error{background:#fdecec;color:#b91c1c}
.monitoring-note{padding:14px 16px;background:#f7faf5;border-radius:9px;color:#60735b;font-size:.84rem;margin-bottom:12px}
@media(max-width:700px){.dashboard{margin-left:0;padding:12px}.page-header{flex-direction:column;align-items:flex-start}.archive-toolbar{flex-direction:column;align-items:stretch}.search-box{width:auto}}
</style>
</head>
<body>
<div class="layout">
<?php include '../sidebar.php'; ?>
<main class="dashboard">
<?php include '../topRightBar.php'; ?>
<div class="archive-page">
<div class="page-header">
  <div><h1>ARCHIVED LIVESTOCKS</h1><p>View archived livestock profiles and their monitoring records.</p></div>
  <a href="livestocks.php" class="back-btn"><span class="material-symbols-rounded">arrow_back</span> Back to Livestocks</a>
</div>
<?php if ($success): ?><div class="archive-message"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="archive-message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="archive-controls">
  <label for="archiveType" class="sr-only">Livestock type</label>
  <select id="archiveType" class="archive-select" onchange="changeArchiveType(this.value)">
    <?php foreach ($types as $type): ?>
      <option value="<?= htmlspecialchars($type, ENT_QUOTES) ?>" <?= strcasecmp($selectedType,$type)===0?'selected':'' ?>><?= htmlspecialchars($type) ?></option>
    <?php endforeach; ?>
  </select>
  <div class="archive-section-buttons" role="group" aria-label="Archived record sections">
    <?php foreach ($sectionOptions as $key=>$label): ?>
      <button type="button"
              class="archive-section-button <?= $selectedSection === $key ? 'active' : '' ?>"
              aria-pressed="<?= $selectedSection === $key ? 'true' : 'false' ?>"
              onclick="changeArchiveSection('<?= htmlspecialchars($key, ENT_QUOTES) ?>')">
        <?= htmlspecialchars($label) ?>
      </button>
    <?php endforeach; ?>
  </div>
</div>
<div class="archive-toolbar">
  <div class="search-box"><span class="material-symbols-rounded">search</span><input id="archiveSearch" type="text" placeholder="Search archived records..." oninput="filterArchiveRows()"></div>
  <div class="archive-count" id="archiveCount"></div>
</div>
<?php if ($selectedSection === 'profile'): ?>
<div class="table-container">
<table id="archiveTable">
<thead><tr><th>#</th><th>ID</th><th>TYPE</th><th>NAME</th><th>BREED</th><th>DATE OF BIRTH</th><th>AGE</th><th>ESTIMATED WEIGHT</th><th>SEX</th><th>FOR SALE</th><th>NUMBER</th><th>ACTION</th></tr></thead>
<tbody>
<?php if ($livestockRows): $n=1; foreach ($livestockRows as $row): ?>
<tr class="archive-row">
<td><?= $n++ ?></td><td><?= (int)($row['livestock_id']??0) ?></td>
<td><?= htmlspecialchars((string)($row['type']??'-')) ?></td><td><?= htmlspecialchars((string)($row['name']??'-')) ?></td>
<td><?= htmlspecialchars((string)($row['breed']??'-')) ?></td>
<td><?= !empty($row['date_of_birth']) && strtotime($row['date_of_birth']) ? htmlspecialchars(date('F d, Y',strtotime($row['date_of_birth']))) : '-' ?></td>
<td><?= htmlspecialchars((string)($row['age']??'-')) ?></td>
<td><?= isset($row['estimated_weight']) && $row['estimated_weight']!=='' ? htmlspecialchars((string)$row['estimated_weight']).' kg' : '-' ?></td>
<td><?= htmlspecialchars((string)($row['sex']??'-')) ?></td>
<td><?= in_array(strtolower((string)($row['for_sale']??'0')),['1','yes','true'],true)?'Yes':'No' ?></td>
<td><?= htmlspecialchars((string)($row['number']??'-')) ?></td>
<td><div class="action-buttons">
<form method="post" onsubmit="return confirm('Restore this livestock and its archived monitoring records?')"><input type="hidden" name="archive_action" value="restore"><input type="hidden" name="livestock_id" value="<?= (int)$row['livestock_id'] ?>"><input type="hidden" name="return_type" value="<?= htmlspecialchars($selectedType,ENT_QUOTES) ?>"><input type="hidden" name="return_section" value="<?= htmlspecialchars($selectedSection,ENT_QUOTES) ?>"><button class="action-icon restore-icon" title="Restore"><span class="material-symbols-rounded">restore</span></button></form>
<form method="post" onsubmit="return confirm('Permanently delete this livestock and its archived monitoring records? This cannot be undone.')"><input type="hidden" name="archive_action" value="delete"><input type="hidden" name="livestock_id" value="<?= (int)$row['livestock_id'] ?>"><input type="hidden" name="return_type" value="<?= htmlspecialchars($selectedType,ENT_QUOTES) ?>"><input type="hidden" name="return_section" value="<?= htmlspecialchars($selectedSection,ENT_QUOTES) ?>"><button class="action-icon delete-icon" title="Delete permanently"><span class="material-symbols-rounded">delete</span></button></form>
</div></td></tr>
<?php endforeach; else: ?><tr><td colspan="12" class="no-records">No archived <?= htmlspecialchars(strtolower($selectedType)) ?> records found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php else:
  $table = $selectedSection;
  // Load archived rows for this monitoring section directly. They may not be
  // linked to a profile row (livestock_id = 0), and archiving a monitoring row
  // must not require archiving the animal itself.
  $relatedRows = archivedRowsFor($conn, $table);
?>
<div class="monitoring-note">Archived <?= htmlspecialchars($sectionOptions[$selectedSection] ?? 'monitoring') ?> records for <?= htmlspecialchars(strtolower($selectedType)) ?>. Each monitoring record is archived separately from the livestock profile.</div>
<div class="table-container"><table id="archiveTable">
<?php if ($relatedRows):
  // Hide internal timestamp/status fields from the archive table.
  $hiddenArchiveColumns = ['created_at', 'archived', 'archived_at'];
  $columns = array_values(array_filter(array_keys($relatedRows[0]), static function ($col) use ($hiddenArchiveColumns) {
      return !in_array(strtolower((string)$col), $hiddenArchiveColumns, true);
  }));
  // archived_by stores the users.user_id, so show the person's name instead of the numeric ID.
  $archiverNames = [];
  $userResult = $conn->query("SELECT user_id, name FROM users");
  if ($userResult) {
      while ($userRow = $userResult->fetch_assoc()) {
          $archiverNames[(string)$userRow['user_id']] = (string)$userRow['name'];
      }
  }
?>
<thead><tr><th>#</th><?php foreach ($columns as $col): ?><th><?= htmlspecialchars(strtoupper(str_replace('_',' ',$col))) ?></th><?php endforeach; ?><th>ACTION</th></tr></thead>
<tbody><?php foreach ($relatedRows as $i=>$row): ?><tr class="archive-row"><td><?= $i+1 ?></td><?php foreach ($columns as $col): ?><?php
  $cellValue = $row[$col] ?? '-';
  if (strtolower((string)$col) === 'archived_by') {
      $archiverId = trim((string)$cellValue);
      $cellValue = $archiverId !== '' && isset($archiverNames[$archiverId]) ? $archiverNames[$archiverId] : ($archiverId !== '' && $archiverId !== '0' ? 'User #' . $archiverId : '-');
  }
?><td><?= htmlspecialchars((string)$cellValue) ?></td><?php endforeach; ?><td><div class="action-buttons"><form method="post" onsubmit="return confirm('Restore this record to the active <?= htmlspecialchars(strtolower($sectionOptions[$selectedSection] ?? 'monitoring'), ENT_QUOTES) ?> section?')"><input type="hidden" name="archive_action" value="restore_monitoring"><input type="hidden" name="record_id" value="<?= (int)($row['id'] ?? 0) ?>"><input type="hidden" name="monitoring_table" value="<?= htmlspecialchars($table, ENT_QUOTES) ?>"><input type="hidden" name="return_type" value="<?= htmlspecialchars($selectedType, ENT_QUOTES) ?>"><input type="hidden" name="return_section" value="<?= htmlspecialchars($selectedSection, ENT_QUOTES) ?>"><button type="submit" class="action-icon restore-icon" title="Restore record" aria-label="Restore record"><span class="material-symbols-rounded">restore</span></button></form></div></td></tr><?php endforeach; ?></tbody>
<?php else: ?><thead><tr><th>ARCHIVED <?= htmlspecialchars(strtoupper($sectionOptions[$selectedSection] ?? 'MONITORING')) ?></th></tr></thead><tbody><tr><td class="no-records">No archived records in this section.</td></tr></tbody><?php endif; ?>
</table></div>
<?php endif; ?>
</div>
</main>
</div>
<script>
function changeArchiveType(type){const u=new URL(window.location.href);u.searchParams.set('type',type);u.searchParams.delete('section');window.location.href=u.toString();}
function changeArchiveSection(section){const u=new URL(window.location.href);u.searchParams.set('section',section);window.location.href=u.toString();}
function filterArchiveRows(){const q=(document.getElementById('archiveSearch')?.value||'').toLowerCase().trim();let visible=0;const rows=[...document.querySelectorAll('.archive-row')];rows.forEach(r=>{const ok=r.innerText.toLowerCase().includes(q);r.style.display=ok?'':'none';if(ok)visible++;});const c=document.getElementById('archiveCount');if(c)c.textContent=visible+' of '+rows.length+' archived record'+(rows.length===1?'':'s');}
document.addEventListener('DOMContentLoaded',filterArchiveRows);
</script>
</body>
</html>
