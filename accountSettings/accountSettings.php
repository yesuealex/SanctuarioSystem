<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../db.php';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$userId = (int)$_SESSION['user_id'];
$message = '';
$error = '';
$messageType = '';

/* Handle profile and password updates on this page. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');

            if ($name === '' || $username === '') {
                throw new RuntimeException('Name and username are required.');
            }

            if (mb_strlen($name) > 100 || mb_strlen($username) > 100) {
                throw new RuntimeException('Name and username must not exceed 100 characters.');
            }

            $check = $conn->prepare(
                'SELECT user_id FROM users WHERE username = ? AND user_id <> ? LIMIT 1'
            );
            $check->bind_param('si', $username, $userId);
            $check->execute();
            $duplicate = $check->get_result()->fetch_assoc();
            $check->close();

            if ($duplicate) {
                throw new RuntimeException('That username is already being used.');
            }

            $stmt = $conn->prepare(
                'UPDATE users SET name = ?, username = ? WHERE user_id = ? LIMIT 1'
            );
            $stmt->bind_param('ssi', $name, $username, $userId);
            $stmt->execute();
            $stmt->close();

            $_SESSION['name'] = $name;
            $_SESSION['username'] = $username;
            $message = 'Your profile information was updated successfully.';
            $messageType = 'success';
        }

        if ($action === 'change_password') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                throw new RuntimeException('Please complete all password fields.');
            }

            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException('The new passwords do not match.');
            }

            if (strlen($newPassword) < 8) {
                throw new RuntimeException('The new password must contain at least 8 characters.');
            }

            $stmt = $conn->prepare('SELECT password FROM users WHERE user_id = ? LIMIT 1');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            /* The existing login_process.php uses the project's current plain-text format. */
            if (!$account || !hash_equals((string)$account['password'], $currentPassword)) {
                throw new RuntimeException('The current password is incorrect.');
            }

            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ? LIMIT 1');
            $stmt->bind_param('si', $newPassword, $userId);
            $stmt->execute();
            $stmt->close();

            $message = 'Your password was changed successfully.';
            $messageType = 'success';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        $messageType = 'error';
    }
}

$stmt = $conn->prepare(
    'SELECT user_id, name, username, role FROM users WHERE user_id = ? LIMIT 1'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: ../login.php');
    exit();
}

$userName = trim($user['name'] ?: $user['username']);
$roleLabel = ucwords(str_replace('_', ' ', (string)$user['role']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | Sanctuario Nature Farm</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../sidebar.css">
    <link rel="stylesheet" href="accountSettings.css">
</head>
<body>
<div class="layout">
    <?php include '../sidebar.php'; ?>

    <main class="dashboard">
        <?php include '../topRightBar.php'; ?>

        <div class="account-page">

            <?php if ($message !== ''): ?>
                <div class="account-alert <?= e($messageType) ?>" role="status">
                    <span class="material-symbols-rounded">check_circle</span>
                    <?= e($message) ?>
                </div>
            <?php elseif ($error !== ''): ?>
                <div class="account-alert <?= e($messageType) ?>" role="alert">
                    <span class="material-symbols-rounded">error</span>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <section class="account-board profile-board">
                <div class="profile-section">
                    <div class="avatar" aria-hidden="true">
                        <span class="material-symbols-rounded">person</span>
                    </div>
                    <div class="profile-details">
                        <span class="profile-kicker">SIGNED IN AS</span>
                        <h2><?= e($userName) ?></h2>
                        <div class="profile-meta">
                            <span class="profile-role"><?= e($roleLabel) ?></span>
                            <span class="profile-username">@<?= e($user['username']) ?></span>
                        </div>
                    </div>
                    <div class="profile-status">
                        <span class="status-dot"></span>
                        Active account
                    </div>
                </div>
            </section>

            <section class="account-board">
                <div class="board-header">
                    <div class="board-icon"><span class="material-symbols-rounded">person</span></div>
                    <div>
                        <h2>Personal Information</h2>
                        <p>Update the information used by your Sanctuario account.</p>
                    </div>
                </div>

                <form class="account-form" method="post" autocomplete="on">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group form-group-wide">
                        <label for="name">Full Name</label>
                        <input id="name" type="text" name="name" value="<?= e($user['name']) ?>" maxlength="100" autocomplete="name" required>
                    </div>

                    <div class="form-group form-group-wide">
                        <label for="username">Username</label>
                        <input id="username" type="text" name="username" value="<?= e($user['username']) ?>" maxlength="100" autocomplete="username" required>
                        <small>Your username is used when signing in.</small>
                    </div>

                    <div class="account-actions form-group-wide">
                        <button type="submit" class="save-btn">
                            <span class="material-symbols-rounded">save</span>
                            Save Profile
                        </button>
                    </div>
                </form>
            </section>

            <section class="account-board security-board">
                <div class="board-header">
                    <div class="board-icon security-icon"><span class="material-symbols-rounded">lock</span></div>
                    <div>
                        <h2>Security</h2>
                        <p>Change your password without leaving this page.</p>
                    </div>
                </div>

                <form class="password-form" method="post" autocomplete="off">
                    <input type="hidden" name="action" value="change_password">

                    <div class="password-grid">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <div class="password-input">
                                <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
                                <button type="button" class="show-password" aria-label="Show current password"><span class="material-symbols-rounded">visibility</span></button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="password-input">
                                <input id="new_password" type="password" name="new_password" minlength="8" autocomplete="new-password" required>
                                <button type="button" class="show-password" aria-label="Show new password"><span class="material-symbols-rounded">visibility</span></button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="password-input">
                                <input id="confirm_password" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                                <button type="button" class="show-password" aria-label="Show confirmed password"><span class="material-symbols-rounded">visibility</span></button>
                            </div>
                        </div>
                    </div>

                    <div class="password-footer">
                        <p class="password-hint"><span class="material-symbols-rounded">info</span> Use at least 8 characters for a stronger password.</p>
                        <button type="submit" class="save-btn"><span class="material-symbols-rounded">lock_reset</span> Change Password</button>
                    </div>
                </form>
            </section>
        </div>
    </main>
</div>

<script>
document.querySelectorAll('.show-password').forEach(button => {
    button.addEventListener('click', () => {
        const input = button.parentElement.querySelector('input');
        const icon = button.querySelector('.material-symbols-rounded');
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        icon.textContent = showing ? 'visibility' : 'visibility_off';
        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    });
});
</script>
</body>
</html>
