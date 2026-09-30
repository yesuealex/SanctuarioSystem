<?php
session_start(); include '../db.php'; require_once '../archive/archive_functions.php';
if(!isset($_SESSION['user_id'])){header('Location: ../login.php');exit();}
if(($_SESSION['role']??'')!=='admin'){header('Location: ../records.php?type=crops');exit();}
$id=(int)($_GET['id']??0);
try{permanentlyDeleteArchived($conn,'crops','crop_id',$id);header('Location: archived_crops.php?deleted=1');exit();}catch(Throwable $e){http_response_code(500);exit('Could not permanently delete crop: '.htmlspecialchars($e->getMessage()));}
?>