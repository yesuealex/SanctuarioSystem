<?php
function logActivity($conn, $user_id, $action, $details = '') {
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, details)
        VALUES (?, ?, ?)
    ");

    $stmt->bind_param("iss", $user_id, $action, $details);
    $stmt->execute();
}
?>