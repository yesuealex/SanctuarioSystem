<?php
session_start();
require_once '../db.php';
require_once '../archive/archive_functions.php';
require_once '../logfunc.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit(); }
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Unauthorized'); }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { http_response_code(400); exit('Invalid livestock ID'); }
try {
    $conn->begin_transaction();
    archiveRecordInTransaction($conn, 'livestocks', 'livestock_id', (int)$id, (int)$_SESSION['user_id']);
    foreach (['chicken_weight_records','chicken_egg_records','chicken_mortality_records','chicken_feed_ingredients','chicken_drinking_water','chicken_feeding_schedule','goat_monitoring_records','goat_forage_records'] as $table) {
        if (archiveTableExists($conn, $table)) archiveTableRowsForLivestock($conn, $table, (int)$id, (int)$_SESSION['user_id']);
    }
    $conn->commit();
    logActivity($conn, (int)$_SESSION['user_id'], 'Archived Livestock', 'Livestock record archived: ID ' . (int)$id);
    header('Location: ../records.php?type=livestock&archived=1'); exit();
} catch (Throwable $e) {
    @$conn->rollback(); http_response_code(500); exit('Could not archive livestock record.');
}
?>