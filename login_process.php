<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, name, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // Using plain text for now as per your setup
        if ($password === $user['password']) {
            
            // Set Session Variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // The Traffic Controller: Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header("Location: adminDashboard.php");
                    break;
                case 'staff':
                    header("Location: staffDashboard.php");
                    break;
                default:
                    header("Location: index.php?error=role_not_found");
            }
            exit();
        }
    }
    header("Location: ../index.php?error=invalid_credentials");
    exit();
}