<?php
if ($_SESSION['role'] !== 'staff') {
    header("Location: adminDashboard.php");
    exit();
}
?>