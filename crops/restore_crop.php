<?php
require_once __DIR__ . '/../staff_access.php';
sanctuario_staff_guard('crops');

if (session_status() !== PHP_SESSION_ACTIVE) session_start(); include '../db.php'; require_once '../archive/archive_functions.php';
if(!isset($_SESSION['user_id'])){header('Location: ../login.php');exit();}
if(($_SESSION['role']??'')!=='admin'){header('Location: ../records.php?type=crops');exit();}
$id=(int)($_GET['id']??0);
try{restoreRecord($conn,'crops','crop_id',$id);header('Location: archived_crops.php?restored=1');exit();}catch(Throwable $e){http_response_code(500);exit('Could not restore crop: '.htmlspecialchars($e->getMessage()));}
?>