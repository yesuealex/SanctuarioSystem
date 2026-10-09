<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../db.php';

/* Add the profile-picture column automatically for existing installations. */
$profileColumnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
if ($profileColumnCheck && $profileColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER role");
}

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

        if ($action === 'upload_profile_picture') {
            if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Please select a profile picture.');
            }

            $file = $_FILES['profile_picture'];

            if ($file['size'] > 2 * 1024 * 1024) {
                throw new RuntimeException('Profile picture must be 2MB or smaller.');
            }

            $imageInfo = @getimagesize($file['tmp_name']);
            if (!$imageInfo) {
                throw new RuntimeException('The selected file is not a valid image.');
            }

            $allowedMime = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];
            $mime = $imageInfo['mime'] ?? '';

            if (!isset($allowedMime[$mime])) {
                throw new RuntimeException('Only JPG, PNG, and WEBP images are allowed.');
            }

            $uploadDir = __DIR__ . '/uploads/profile/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                throw new RuntimeException('Could not create the profile picture folder.');
            }

            $extension = $allowedMime[$mime];
            $filename = 'profile_' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
            $destination = $uploadDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new RuntimeException('Could not save the profile picture.');
            }

            $relativePath = 'accountSettings/uploads/profile/' . $filename;

            $oldStmt = $conn->prepare('SELECT profile_picture FROM users WHERE user_id = ? LIMIT 1');
            $oldStmt->bind_param('i', $userId);
            $oldStmt->execute();
            $oldUser = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();

            $stmt = $conn->prepare('UPDATE users SET profile_picture = ? WHERE user_id = ? LIMIT 1');
            $stmt->bind_param('si', $relativePath, $userId);
            $stmt->execute();
            $stmt->close();

            if (!empty($oldUser['profile_picture'])) {
                $oldRelative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $oldUser['profile_picture']);
                $oldFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . $oldRelative;
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }

            $message = 'Your profile picture was updated successfully.';
            $messageType = 'success';
        }

        if ($action === 'remove_profile_picture') {
            $stmt = $conn->prepare('SELECT profile_picture FROM users WHERE user_id = ? LIMIT 1');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $oldUser = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $conn->prepare('UPDATE users SET profile_picture = NULL WHERE user_id = ? LIMIT 1');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            if (!empty($oldUser['profile_picture'])) {
                $oldRelative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $oldUser['profile_picture']);
                $oldFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . $oldRelative;
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }

            $message = 'Your profile picture was removed.';
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

            $storedPassword = (string)($account['password'] ?? '');
            $currentValid = $storedPassword !== '' && (password_get_info($storedPassword)['algo'] !== 0 ? password_verify($currentPassword, $storedPassword) : hash_equals($storedPassword, $currentPassword));
            if (!$account || !$currentValid) {
                throw new RuntimeException('The current password is incorrect.');
            }

            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ? LIMIT 1');
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt->bind_param('si', $newPasswordHash, $userId);
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
    'SELECT user_id, name, username, role, profile_picture FROM users WHERE user_id = ? LIMIT 1'
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
$initial = strtoupper(substr($userName, 0, 1));
$profileImageUrl = !empty($user['profile_picture']) ? '../' . e($user['profile_picture']) : '';
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
            <div class="account-intro">
                <div>
                    <h1>Account settings</h1>
                    <p>Keep your profile details, photo, and security information up to date.</p>
                </div>
                <div class="account-intro-badge"><span class="material-symbols-rounded">verified_user</span> Private &amp; secure</div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="account-alert <?= e($messageType) ?>" role="status"><span class="material-symbols-rounded">check_circle</span><?= e($message) ?></div>
            <?php elseif ($error !== ''): ?>
                <div class="account-alert <?= e($messageType) ?>" role="alert"><span class="material-symbols-rounded">error</span><?= e($error) ?></div>
            <?php endif; ?>

            <section class="account-board profile-board">
                <div class="profile-section">
                    <div class="avatar" id="profile-avatar">
                        <?php if ($profileImageUrl !== ''): ?>
                            <img class="account-profile-image" src="<?= $profileImageUrl ?>" alt="Profile picture">
                        <?php else: ?>
                            <span aria-hidden="true"><?= e($initial) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-details">
                        <span class="profile-kicker">SIGNED IN AS</span>
                        <h2><?= e($userName) ?></h2>
                        <div class="profile-meta"><span class="profile-role"><?= e($roleLabel) ?></span><span class="profile-username">@<?= e($user['username']) ?></span></div>
                    </div>
                    <div class="profile-status"><span class="status-dot"></span> Active account</div>
                </div>
            </section>

            <section class="account-board">
                <div class="board-header"><div class="board-icon"><span class="material-symbols-rounded">photo_camera</span></div><div><h2>Profile picture</h2><p>Choose a clear photo so your team can recognize you.</p></div></div>
                <form class="profile-picture-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_profile_picture">
                    <div class="profile-picture-preview" id="profile-preview">
                        <?php if ($profileImageUrl !== ''): ?><img src="<?= $profileImageUrl ?>" alt="Current profile picture">
                        <?php else: ?><span class="material-symbols-rounded" aria-hidden="true">person</span><?php endif; ?>
                    </div>
                    <div class="profile-picture-controls">
                        <label class="upload-picture-btn"><span class="material-symbols-rounded">upload</span><span id="file-label">Choose picture</span><input id="profile-picture-input" type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp" required></label>
                        <button type="submit" class="save-btn"><span class="material-symbols-rounded">save</span>Upload picture</button>
                        <?php if ($profileImageUrl !== ''): ?><button type="submit" name="action" value="remove_profile_picture" class="remove-picture-btn" formnovalidate onclick="return confirm('Remove your profile picture?');"><span class="material-symbols-rounded">delete</span>Remove</button><?php endif; ?>
                    </div>
                    <small class="profile-picture-note">JPG, PNG, or WEBP · Maximum 2MB</small>
                </form>
            </section>

            <section class="account-board">
                <div class="board-header"><div class="board-icon"><span class="material-symbols-rounded">person</span></div><div><h2>Personal information</h2><p>Update the information used by your Sanctuario account.</p></div></div>
                <form class="account-form" method="post" autocomplete="on">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group form-group-wide"><label for="name">Full name</label><input id="name" type="text" name="name" value="<?= e($user['name']) ?>" maxlength="100" autocomplete="name" required></div>
                    <div class="form-group form-group-wide"><label for="username">Username</label><input id="username" type="text" name="username" value="<?= e($user['username']) ?>" maxlength="100" autocomplete="username" required><small>Your username is used when signing in.</small></div>
                    <div class="account-actions form-group-wide"><button type="submit" class="save-btn"><span class="material-symbols-rounded">save</span>Save profile</button></div>
                </form>
            </section>

            <section class="account-board security-board">
                <div class="board-header"><div class="board-icon security-icon"><span class="material-symbols-rounded">lock</span></div><div><h2>Security</h2><p>Change your password without leaving this page.</p></div></div>
                <form class="password-form" method="post" autocomplete="off">
                    <input type="hidden" name="action" value="change_password">
                    <div class="password-grid">
                        <?php foreach ([['current_password','Current password','current-password'],['new_password','New password','new-password'],['confirm_password','Confirm new password','new-password']] as $passwordField): ?>
                            <div class="form-group"><label for="<?= $passwordField[0] ?>"><?= $passwordField[1] ?></label><div class="password-input"><input id="<?= $passwordField[0] ?>" type="password" name="<?= $passwordField[0] ?>" autocomplete="<?= $passwordField[2] ?>" <?= $passwordField[0] !== 'current_password' ? 'minlength="8"' : '' ?> required><button type="button" class="show-password" aria-label="Show password"><span class="material-symbols-rounded">visibility</span></button></div></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="password-footer"><p class="password-hint"><span class="material-symbols-rounded">info</span> Use at least 8 characters for a stronger password.</p><button type="submit" class="save-btn"><span class="material-symbols-rounded">lock_reset</span>Change password</button></div>
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

    const pictureInput = document.getElementById('profile-picture-input');
    const picturePreview = document.getElementById('profile-preview');
    const fileLabel = document.getElementById('file-label');
    const profileAvatar = document.getElementById('profile-avatar');

    pictureInput?.addEventListener('change', () => {
        const file = pictureInput.files?.[0];
        if (!file) return;
        fileLabel.textContent = file.name.length > 22 ? `${file.name.slice(0, 19)}…` : file.name;
        const reader = new FileReader();
        reader.onload = event => {
            const image = `<img src="${event.target.result}" alt="Selected profile picture">`;
            picturePreview.innerHTML = image;
            profileAvatar.innerHTML = image;
        };
        reader.readAsDataURL(file);
    });
</script>
</body>
</html>