<?php
require_once __DIR__ . '/../staff_access.php';
sanctuario_staff_guard('crops');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include '../db.php';
require_once '../archive/archive_functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!in_array(($_SESSION['role'] ?? ''), ['admin','super admin','crops staff'], true)) {
    header('Location: ../records.php?type=crops');
    exit();
}

// Only use known crop sections for the return URL (prevents unsafe redirects).
$returnLocation = trim((string)($_GET['location'] ?? 'all'));
$allowedReturnLocations = ['all', 'Nursery', 'Plot A', 'Plot B', 'Plot C', 'MNO', 'Hydro', 'Tent House'];
$matchedLocation = 'all';
foreach ($allowedReturnLocations as $allowedLocation) {
    if (strcasecmp($returnLocation, $allowedLocation) === 0) {
        $matchedLocation = $allowedLocation;
        break;
    }
}
$returnUrl = 'crops.php?location=' . rawurlencode($matchedLocation);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . $returnUrl);
    exit();
}

try {
    /* Migrate any old soft-archived crop rows before using physical archive. */
    ensureArchiveTable($conn, 'crops');

    $conn->begin_transaction();

    $cols = archiveColumns($conn, 'crops');
    $archive = ensureArchiveTable($conn, 'crops', false);

    $check = $conn->prepare("SELECT COUNT(*) FROM `crops` WHERE `crop_id` = ?");
    if (!$check) throw new Exception('Could not check crop record.');
    $check->bind_param('i', $id);
    $check->execute();
    $check->bind_result($exists);
    $check->fetch();
    $check->close();

    if (!$exists) {
        throw new Exception('Crop record was not found in the active records.');
    }

    $uid = (int)$_SESSION['user_id'];
    $insert = "INSERT INTO `{$archive}` (`" . implode('`,`', $cols) . "`,`archived_at`,`archived_by`) SELECT `" . implode('`,`', $cols) . "`,NOW(),? FROM `crops` WHERE `crop_id`=? LIMIT 1";
    $stmt = $conn->prepare($insert);
    if (!$stmt) throw new Exception('Could not prepare crop archive: ' . $conn->error);
    $stmt->bind_param('ii', $uid, $id);

    if (!$stmt->execute()) {
        $duplicate = stripos($stmt->error, 'duplicate') !== false;
        $error = $stmt->error;
        $stmt->close();
        if (!$duplicate) throw new Exception('Could not archive crop: ' . $error);
    } else {
        $stmt->close();
    }

    // Remove FK-dependent active records before physically deleting the crop.
    removeCropDependentRecords($conn, $id);

    /* This is the important part: remove the active copy. */
    $delete = $conn->prepare("DELETE FROM `crops` WHERE `crop_id` = ? LIMIT 1");
    if (!$delete) throw new Exception('Could not prepare active crop removal: ' . $conn->error);
    $delete->bind_param('i', $id);
    if (!$delete->execute()) {
        $error = $delete->error;
        $delete->close();
        throw new Exception('Could not remove active crop: ' . $error);
    }
    $delete->close();

    $conn->commit();
    header('Location: ' . $returnUrl . '&archived=1');
    exit();

} catch (Throwable $e) {
    @$conn->rollback();
    http_response_code(500);
    exit('Could not archive crop: ' . htmlspecialchars($e->getMessage()));
}
?>