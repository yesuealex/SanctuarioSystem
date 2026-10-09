<?php
session_start();
require_once '../db.php';
require_once '../archive/archive_functions.php';
require_once '../logfunc.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit(); }
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Unauthorized'); }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { http_response_code(400); exit('Invalid crop ID'); }
try {
    archiveRecord($conn, 'crops', 'crop_id', (int)$id, (int)$_SESSION['user_id']);
    logActivity($conn, (int)$_SESSION['user_id'], 'Archived Crop', 'Crop record archived: ID ' . (int)$id);
    header('Location: ../records.php?type=crops&archived=1');
    exit();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Could not archive crop record.');
}
?>