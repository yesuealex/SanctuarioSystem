<?php
require_once '../security.php';
secure_session_start();

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
    if (!csrf_verify()) { http_response_code(400); $error = 'Invalid or expired request. Refresh and try again.'; $action = ''; }

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

        if ($action === 'request_email_verification') {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $currentPassword = (string)($_POST['email_current_password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
                throw new RuntimeException('Enter a valid email address.');
            }
            $stmt = $conn->prepare('SELECT password, email FROM users WHERE user_id = ? LIMIT 1');
            $stmt->bind_param('i', $userId); $stmt->execute(); $own = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$own || !password_verify($currentPassword, $own['password'])) {
                throw new RuntimeException('Your current password is incorrect.');
            }
            if (strcasecmp((string)$own['email'], $email) === 0) {
                throw new RuntimeException('That email address is already saved.');
            }
            $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1');
            $stmt->bind_param('si', $email, $userId); $stmt->execute(); $taken = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if ($taken) throw new RuntimeException('That email address is already in use.');
            $stmt = $conn->prepare('SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds FROM email_verification_tokens WHERE user_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->bind_param('i', $userId); $stmt->execute(); $last = $stmt->get_result()->fetch_assoc(); $stmt->close();
            $cooldownSeconds = 10; // Local testing only. Change to 120 before deployment.
            if ($last && (int)$last['age_seconds'] < $cooldownSeconds) {
                throw new RuntimeException('Please wait a few seconds before requesting another verification email.');
            }
            $token = bin2hex(random_bytes(32)); $hash = hash('sha256', $token);
            $stmt = $conn->prepare('INSERT INTO email_verification_tokens (user_id, pending_email, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))');
            $stmt->bind_param('iss', $userId, $email, $hash); $stmt->execute(); $stmt->close();
            // This page lives in accountSettings/, so ../ reaches the project root.
            $link = app_url('../verify_email.php') . '?token=' . urlencode($token);
            error_log('Sanctuario verify: Token inserted; attempting email.');
            if (!send_security_email($email, 'Verify your Sanctuario email', "Open this link within 60 minutes to verify your email address:\n\n$link\n\nIf you did not request this, ignore this message.")) {
                error_log('Sanctuario verify: SMTP send failed; inspect SMTP error log.');
                throw new RuntimeException('Could not send verification email. Please try again.');
            }
            error_log('Sanctuario verify: SMTP accepted the verification email.');
            $message = 'A verification link was sent. Your email will be saved after you open it.';
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
            if (!password_is_strong($newPassword)) {
                throw new RuntimeException('The new password must contain 12–72 characters.');
            }

            $stmt = $conn->prepare('SELECT password FROM users WHERE user_id = ? LIMIT 1');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $storedPassword = (string)($account['password'] ?? '');
            $currentValid = $storedPassword !== '' && password_verify($currentPassword, $storedPassword);
            if (!$account || !$currentValid) {
                throw new RuntimeException('The current password is incorrect.');
            }

            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ? LIMIT 1');
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt->bind_param('si', $newPasswordHash, $userId);
            $stmt->execute();
            $stmt->close();

            $_SESSION = [];
            session_regenerate_id(true);
            header('Location: ../login.php?password_changed=1');
            exit;
        }
    } catch (Throwable $exception) {
        error_log('Sanctuario account settings: ' . $exception->getMessage());
        $error = $exception->getMessage();
        $messageType = 'error';
    }
}

$stmt = $conn->prepare(
    'SELECT user_id, name, username, email, role, profile_picture FROM users WHERE user_id = ? LIMIT 1'
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
    <link rel="stylesheet" href="accountSettings.css?v=3">
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
                <div class="account-intro-badge"><span class="material-symbols-rounded">verified_user</span> Account protection enabled</div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="account-alert <?= e($messageType) ?>" role="status"><span class="material-symbols-rounded">check_circle</span><?= e($message) ?></div>
            <?php elseif ($error !== ''): ?>
                <div class="account-alert <?= e($messageType) ?>" role="alert"><span class="material-symbols-rounded">error</span><?= e($error) ?></div>
            <?php endif; ?>

            <section class="account-board profile-board" aria-label="Your profile">
                <div class="profile-section">
                    <div class="avatar-area">
                        <div class="avatar" id="profile-avatar">
                            <?php if ($profileImageUrl !== ''): ?>
                                <img class="account-profile-image" src="<?= $profileImageUrl ?>" alt="Your current profile picture">
                            <?php else: ?>
                                <span aria-hidden="true"><?= e($initial) ?></span>
                            <?php endif; ?>
                        </div>
                        <form id="avatar-upload-form" class="avatar-upload-form" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="upload_profile_picture">
                            <input id="profile-picture-input" class="visually-hidden" type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp" aria-label="Choose profile picture">
                            <div class="avatar-actions" role="group" aria-label="Profile picture actions">
                                <label class="avatar-action" for="profile-picture-input" title="Choose picture" aria-label="Choose picture" tabindex="0"><span class="material-symbols-rounded" aria-hidden="true">add_photo_alternate</span></label>
                                <button class="avatar-action avatar-upload" type="submit" title="Upload selected picture" aria-label="Upload selected picture" disabled><span class="material-symbols-rounded" aria-hidden="true">upload</span></button>
                                <?php if ($profileImageUrl !== ''): ?>
                                <button class="avatar-action avatar-remove" type="submit" name="action" value="remove_profile_picture" formnovalidate title="Remove profile picture" aria-label="Remove profile picture" onclick="return confirm('Remove your profile picture?');"><span class="material-symbols-rounded" aria-hidden="true">delete_outline</span></button>
                                <?php endif; ?>
                            </div>
                        </form>
                        <small class="avatar-file-hint" id="picture-hint" aria-live="polite">JPG, PNG or WEBP · Max 2 MB</small>
                    </div>
                    <div class="profile-details">
                        <span class="profile-kicker">YOUR PROFILE</span>
                        <h2><?= e($userName) ?></h2>
                        <div class="profile-meta"><span class="profile-role"><?= e($roleLabel) ?></span><span class="profile-username">@<?= e($user['username']) ?></span></div>
                        <p class="profile-helper">Manage your details and keep your account secure.</p>
                    </div>
                    <div class="profile-status"><span class="status-dot"></span> Active account</div>
                </div>
            </section>

            <section class="account-board">
                <div class="board-header"><div class="board-icon"><span class="material-symbols-rounded">person</span></div><div><h2>Personal information</h2><p>Update the information used by your Sanctuario account.</p></div></div>
                <form class="account-form" method="post" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group form-group-wide"><label for="name">Full name</label><input id="name" type="text" name="name" value="<?= e($user['name']) ?>" maxlength="100" autocomplete="name" required></div>
                    <div class="form-group form-group-wide"><label for="username">Username</label><input id="username" type="text" name="username" value="<?= e($user['username']) ?>" maxlength="100" autocomplete="username" required><small>Your username is used when signing in.</small></div>
                    <div class="account-actions form-group-wide"><button type="submit" class="save-btn"><span class="material-symbols-rounded">save</span>Save profile</button></div>
                </form>
            </section>

            <section class="account-board">
                <div class="board-header"><div class="board-icon"><span class="material-symbols-rounded">mail</span></div><div><h2>Recovery email</h2><p>Add a verified email to enable Forgot Password.</p></div></div>
                <p>Current email: <strong><?= e($user['email'] ?: 'Not set') ?></strong></p>
                <form class="account-form" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="request_email_verification">
                    <div class="form-group form-group-wide"><label for="recovery_email">New recovery email</label><input id="recovery_email" name="email" type="email" maxlength="150" autocomplete="email" required></div>
                    <div class="form-group form-group-wide"><label for="email_current_password">Current password (to confirm)</label><input id="email_current_password" name="email_current_password" type="password" autocomplete="current-password" required></div>
                    <div class="account-actions form-group-wide"><button type="submit" class="save-btn"><span class="material-symbols-rounded">mark_email_read</span>Send verification email</button></div>
                </form>
            </section>

            <section class="account-board security-board">
                <div class="board-header"><div class="board-icon security-icon"><span class="material-symbols-rounded">lock</span></div><div><h2>Security</h2><p>Change your password without leaving this page.</p></div></div>
                <form class="password-form" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div class="password-grid">
                        <?php foreach ([['current_password','Current password','current-password'],['new_password','New password','new-password'],['confirm_password','Confirm new password','new-password']] as $passwordField): ?>
                            <div class="form-group"><label for="<?= $passwordField[0] ?>"><?= $passwordField[1] ?></label><div class="password-input"><input id="<?= $passwordField[0] ?>" type="password" name="<?= $passwordField[0] ?>" autocomplete="<?= $passwordField[2] ?>" <?= $passwordField[0] !== 'current_password' ? 'minlength="12" maxlength="72"' : '' ?> required><button type="button" class="show-password" aria-label="Show password"><span class="material-symbols-rounded">visibility</span></button></div></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="password-footer"><p class="password-hint"><span class="material-symbols-rounded">info</span> Use 12–72 characters for a stronger password.</p><button type="submit" class="save-btn"><span class="material-symbols-rounded">lock_reset</span>Change password</button></div>
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
    const profileAvatar = document.getElementById('profile-avatar');
    const pictureHint = document.getElementById('picture-hint');
    const uploadButton = document.querySelector('.avatar-upload');
    document.querySelector('.avatar-action[for="profile-picture-input"]')?.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            pictureInput.click();
        }
    });
    pictureInput?.addEventListener('change', () => {
        const file = pictureInput.files?.[0];
        uploadButton.disabled = true;
        if (!file) return;
        if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            pictureHint.textContent = 'Choose a JPG, PNG or WEBP image under 2 MB.';
            pictureInput.value = '';
            return;
        }
        pictureHint.textContent = file.name + ' · Ready to upload';
        uploadButton.disabled = false;
        const reader = new FileReader();
        reader.onload = event => {
            const img = document.createElement('img');
            img.src = event.target.result;
            img.alt = 'Selected profile picture preview';
            img.className = 'account-profile-image';
            profileAvatar.replaceChildren(img);
        };
        reader.readAsDataURL(file);
    });
</script>
</body>
</html>