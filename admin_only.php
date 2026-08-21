<?php
if ($_SESSION['role'] !== 'admin') {
    header("Location: staffDashboard.php");
    exit();
}
?>