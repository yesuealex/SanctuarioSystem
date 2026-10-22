<?php
require_once __DIR__ . '/security.php';
secure_session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/login_rate_limit.php';
function login_reject(string $code='invalid_credentials'): void { header('Location: login.php?error='.$code); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') login_reject('invalid_request');
if (!csrf_verify()) login_reject('invalid_request');
$identifier=trim((string)($_POST['username']??''));
$password=(string)($_POST['password']??'');
if ($identifier==='' || $password==='' || strlen($identifier)>150 || strlen($password)>1024) login_reject();
try {
    // Keep IP-wide throttling, but permit a correctly authenticated, admin-unlocked
    // account to clear its old identifier-specific rate-limit entry.
    if (!login_rate_ip_allowed($conn)) login_reject('rate_limited');
    $identifierRateAllowed = login_rate_check($conn, $identifier);
    $s=$conn->prepare('SELECT user_id,name,username,email,email_verified_at,password,role,failed_login_attempts,account_locked,locked_at FROM users WHERE username=? OR email=? LIMIT 1');
    $s->bind_param('ss',$identifier,$identifier);$s->execute();$user=$s->get_result()->fetch_assoc();$s->close();
    $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $valid=password_verify($password,$user['password']??$dummy);
    $locked=false;
    if ($user && (int)$user['account_locked']===1) {
        $lockedAt=strtotime((string)($user['locked_at']??''));
        $locked=!$lockedAt || time()-$lockedAt<900;
        if (!$locked) {
            $s=$conn->prepare('UPDATE users SET account_locked=0,failed_login_attempts=0,locked_at=NULL WHERE user_id=?');
            $s->bind_param('i',$user['user_id']);$s->execute();$s->close();
        }
    }
    if (!$user || !$valid || $locked) {
        // Avoid extending an existing identifier cooldown on every blocked request.
        if (!$identifierRateAllowed) login_reject('rate_limited');
        if ($locked && $valid) login_reject('account_locked');
        login_rate_failure($conn,$identifier);
        if ($user && !$locked) {
            $s=$conn->prepare('UPDATE users SET failed_login_attempts=failed_login_attempts+1,account_locked=IF(failed_login_attempts+1>=5,1,0),locked_at=IF(failed_login_attempts+1>=5,NOW(),NULL) WHERE user_id=?');
            $s->bind_param('i',$user['user_id']);$s->execute();$s->close();
        }
        // Small progressive delay, in addition to persistent database rate limiting.
        $failures=(int)($user['failed_login_attempts']??0);
        usleep(min(1500000, 250000 + $failures*250000));
        // Only show the lock message after a valid password, to avoid account enumeration.
        login_reject('invalid_credentials');
    }
    if (!in_array($user['role'],['admin','super admin','staff','crops staff','livestock staff','maintenance'],true)) login_reject();
    login_rate_success($conn,$identifier);
    $s=$conn->prepare('UPDATE users SET failed_login_attempts=0,account_locked=0,locked_at=NULL WHERE user_id=?');
    $s->bind_param('i',$user['user_id']);$s->execute();$s->close();
    if (password_needs_rehash($user['password'],PASSWORD_DEFAULT)) {
        $hash=password_hash($password,PASSWORD_DEFAULT);
        $s=$conn->prepare('UPDATE users SET password=? WHERE user_id=?');$s->bind_param('si',$hash,$user['user_id']);$s->execute();$s->close();
    }
    session_regenerate_id(true);
    // Email code verification is no longer required during login.
    // Keep the verified-email database field unchanged for other account workflows.
    unset(
        $_SESSION['pending_verification_user_id'],
        $_SESSION['pending_verification_at'],
        $_SESSION['verification_code_attempts'],
        $_SESSION['verification_code_token_id']
    );
    $_SESSION['user_id']=(int)$user['user_id'];$_SESSION['name']=$user['name'];
    $_SESSION['username']=$user['username'];$_SESSION['role']=$user['role'];
    $_SESSION['authenticated_at']=time();$_SESSION['last_activity']=time();
    unset($_SESSION['csrf_token']);
    $dest=in_array($user['role'],['admin','super admin'],true)?'adminDashboard.php':(in_array($user['role'],['crops staff','livestock staff'],true)?'staff/staffDashboard.php':($user['role']==='staff'?'staff_role_pending.php':'maintenanceDashboard.php'));
    header('Location: '.$dest);exit;
} catch (Throwable $e) {
    error_log('Login security error: '.$e->getMessage());login_reject('system_error');
}