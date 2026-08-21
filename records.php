<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$type = $_GET['type'] ?? 'crops';

if ($type === 'livestock') {

    header("Location: livestock/livestocks.php");
    exit();

}

header("Location: crops/crops.php");
exit();
?>