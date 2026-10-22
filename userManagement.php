<?php
require_once __DIR__ . '/staff_access.php';
if (empty($_SESSION['user_id'])) { header('Location: /SanctuarioSystem/login.php'); exit; }
if (!sanctuario_staff_is_admin()) { http_response_code(403); exit('Access denied.'); }
require_once __DIR__ . '/db.php';
header('Cache-Control: no-store, private');
function um_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function um_redirect(string $message, bool $ok=true): void {
    $_SESSION['um_flash']=['message'=>$message,'ok'=>$ok];
    header('Location: userManagement.php', true, 303); exit;
}
$roles=['crops staff'=>'Crops Staff','livestock staff'=>'Livestock Staff'];
// Confirm the database ENUM supports both roles before offering account creation.
$roleColumn=$conn->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch_assoc();
$enumType=(string)($roleColumn['Type']??'');
$allowedRoles=array_filter($roles, static fn($label,$role)=>str_contains($enumType,"'".$role."'"),ARRAY_FILTER_USE_BOTH);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_verify()) { http_response_code(403); exit('Invalid security token. Refresh and try again.'); }
    $action=(string)($_POST['action']??'');
    $id=(int)($_POST['user_id']??0);
    $name=trim((string)($_POST['name']??''));
    $username=trim((string)($_POST['username']??''));
    $email=trim((string)($_POST['email']??''));
    $role=(string)($_POST['role']??'');
    $password=(string)($_POST['password']??'');
    try {
        if ($action==='create' || $action==='edit') {
            if ($name==='' || mb_strlen($name)>100 || !preg_match('/^[A-Za-z0-9_.-]{3,100}$/D',$username)
                || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>150
                || !array_key_exists($role,$allowedRoles)) um_redirect('Check name, username, email, and staff role.',false);
            if ($action==='create' && !password_is_strong($password)) um_redirect('Use a stronger password (at least 12 characters).',false);
            $check=$conn->prepare('SELECT user_id FROM users WHERE (username=? OR email=?) AND user_id<>? LIMIT 1');
            $check->bind_param('ssi',$username,$email,$id); $check->execute();
            $duplicate=$check->get_result()->fetch_assoc(); $check->close();
            if ($duplicate) um_redirect('Username or email already belongs to another account.',false);
            if ($action==='create') {
                $hash=password_hash($password,PASSWORD_DEFAULT);
                $stmt=$conn->prepare('INSERT INTO users (name,username,email,password,role,email_verified_at,failed_login_attempts,account_locked,locked_at) VALUES (?,?,?,?,?,NULL,0,0,NULL)');
                $stmt->bind_param('sssss',$name,$username,$email,$hash,$role);
                $stmt->execute(); $stmt->close();
                um_redirect('Staff account created. The user can now sign in with their username and password.');
            }
            if ($id<1 || $id===(int)$_SESSION['user_id']) um_redirect('This account cannot be edited here.',false);
            $stmt=$conn->prepare("UPDATE users SET name=?,username=?,email=?,role=? WHERE user_id=? AND role IN ('crops staff','livestock staff')");
            $stmt->bind_param('ssssi',$name,$username,$email,$role,$id);
            $stmt->execute(); $affected=$stmt->affected_rows; $stmt->close();
            um_redirect('Staff details saved.');
        }
        if ($action==='reset_password') {
            if ($id<1 || $id===(int)$_SESSION['user_id'] || !password_is_strong($password)) um_redirect('Select a staff account and enter a stronger password.',false);
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $stmt=$conn->prepare("UPDATE users SET password=?,failed_login_attempts=0,account_locked=0,locked_at=NULL WHERE user_id=? AND role IN ('crops staff','livestock staff')");
            $stmt->bind_param('si',$hash,$id); $stmt->execute(); $changed=$stmt->affected_rows; $stmt->close();
            um_redirect($changed?'Password reset and stored securely.':'Staff account not found.',$changed>0);
        }
        if ($action==='unlock') {
            $stmt=$conn->prepare("UPDATE users SET account_locked=0,failed_login_attempts=0,locked_at=NULL WHERE user_id=? AND role IN ('crops staff','livestock staff')");
            $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close();
            // Identifier-specific throttling is cleared on the staff member's next
            // successful password verification, regardless of the staff member's IP.
            // IP-wide protection is deliberately not bypassed.
            um_redirect('Account unlocked. The staff member can sign in with the correct password. An IP-wide security cooldown, if active, may still take up to 15 minutes.');
        }
        um_redirect('Unsupported action.',false);
    } catch (Throwable $e) {
        error_log('User management: '.$e->getMessage());
        um_redirect('Unable to save. Check the database and server error log.',false);
    }
}
$users=[];
$res=$conn->query("SELECT user_id,name,username,email,role,failed_login_attempts,account_locked FROM users WHERE role IN ('crops staff','livestock staff') ORDER BY user_id DESC");
if ($res) while ($row=$res->fetch_assoc()) $users[]=$row;
$flash=$_SESSION['um_flash']??null; unset($_SESSION['um_flash']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>User Management | Sanctuario</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="style.css">

<style>
.um-topbar-clearance{height:18px;clear:both;pointer-events:none}
@media(max-width:900px){.um-topbar-clearance{height:14px}}
/* User Management visual refresh; existing PHP actions and JavaScript are preserved. */
.um-main{margin-left:var(--sidebar-width,250px);padding:35px clamp(18px,3vw,48px);min-height:100vh;background:#f5f6f8;box-sizing:border-box;font-family:Poppins,sans-serif;color:#273d2e}
.um-header{margin-top:0;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:26px;padding:6px 0 10px}
.um-header h1{font-size:clamp(28px,3vw,36px);font-weight:700;letter-spacing:-.7px;margin:0;color:#243b2b}
.um-header p{margin:7px 0 0;color:#758076;font-size:15px}
.um-card{background:#fff;border:1px solid #e5e9e5;border-radius:20px;padding:24px;box-shadow:0 8px 32px rgba(30,52,34,.055)}
.um-table-wrap{overflow-x:auto}
.um-table{border-collapse:separate;border-spacing:0;width:100%;min-width:780px}
.um-table th,.um-table td{text-align:left;padding:18px 14px;border-bottom:1px solid #edf0ec;font-size:14px}
.um-table th{background:#f7f9f7;color:#627466;font-weight:700;font-size:13px;letter-spacing:.25px}
.um-table th:first-child{border-radius:10px 0 0 10px}
.um-table th:last-child{border-radius:0 10px 10px 0}
.um-table td{color:#334b3a;font-weight:500}
.um-table tbody tr{transition:background .18s}
.um-table tbody tr:hover{background:#fafcf9}
.um-table tbody tr:last-child td{border-bottom:0}
.um-table td:first-child{font-weight:600;color:#28422f}
.um-btn{border:1px solid #ccd9cd;background:#fff;color:#2e5129;padding:11px 17px;border-radius:11px;font:600 14px Poppins,sans-serif;cursor:pointer;transition:background .18s,transform .18s,box-shadow .18s}
.um-btn:hover{background:#f1f6ee;transform:translateY(-1px)}
.um-primary{background:#2e5129;color:#fff;border-color:#2e5129;box-shadow:0 4px 12px rgba(46,81,41,.15)}
.um-primary:hover{background:#416b38;color:#fff}
.um-actions{display:flex;gap:8px;flex-wrap:nowrap;align-items:center}
.um-icon-btn{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;padding:0;border:1px solid #d9e2d7;border-radius:11px;background:#fff;color:#2e5129;cursor:pointer;transition:background .15s,border-color .15s,transform .15s}
.um-icon-btn:hover{background:#edf5e9;border-color:#97b58e;transform:translateY(-1px)}
.um-icon-btn:focus-visible,.um-btn:focus-visible{outline:3px solid #8caf83;outline-offset:2px}
.um-icon-btn svg{width:19px;height:19px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.um-actions form{margin:0}
.um-status{display:inline-block;border-radius:999px;padding:6px 13px;background:#eaf4eb;color:#347145;font-size:12px;font-weight:600}
.um-status.warn{background:#fff4df;color:#8a651b}
.um-flash{padding:14px 18px;border-radius:12px;margin-bottom:18px;background:#eaf5e6;color:#2e5129;font-size:14px}
.um-flash.bad{background:#fff0ee;color:#9b3c32}
.um-modal{position:fixed;inset:0;z-index:2000;background:rgba(22,40,25,.55);display:none;align-items:center;justify-content:center;padding:15px}
.um-modal.open{display:flex}
.um-dialog{background:#fff;width:min(540px,100%);max-height:92dvh;overflow:auto;padding:30px;border:1px solid #e5ebe4;border-radius:20px;box-shadow:0 24px 75px rgba(0,0,0,.22)}
.um-dialog h2{margin:0 0 22px;font-size:24px;color:#263f2e}
.um-form{display:grid;gap:15px}
.um-form label{font-size:14px;font-weight:600;display:grid;gap:7px;color:#3b5340}
.um-form input,.um-form select{padding:13px 14px;border:1px solid #cdd9c8;border-radius:11px;font:15px Poppins,sans-serif;width:100%;box-sizing:border-box;background:#fff}
.um-form input:focus,.um-form select:focus{outline:2px solid #b3cbb0;border-color:#638b5e}
.um-form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:15px}
.um-note{font-size:13px;color:#667766;margin:0}
.um-empty{padding:36px;color:#6e7b70;text-align:center;font-size:15px}
@media(max-width:900px){.um-header{margin-top:0}.um-main{margin-left:0;padding:78px 14px 25px}.um-card{padding:14px}.um-header h1{font-size:27px}.um-header p{font-size:14px}}
@media(max-width:480px){.um-header{align-items:flex-start}.um-header .um-primary{width:100%}.um-dialog{padding:20px}.um-table th,.um-table td{padding:14px 12px}}
</style>
</head><body>
<?php include __DIR__.'/sidebar.php'; ?>
<main class="um-main">
<?php
// Preserve the shared top bar and correct this page's breadcrumb label only.
ob_start();
include __DIR__ . '/topRightBar.php';
$umTopBar = ob_get_clean();
$umTopBar = str_replace(['Usermanagement', 'Usermanagement', 'Usermanagement'], 'User Management', $umTopBar);
echo $umTopBar;
?>
<div class="um-topbar-clearance" aria-hidden="true"></div>
<div class="um-header"><div><h1>User Management</h1><p>Manage Crops Staff and Livestock Staff accounts.</p></div><button class="um-btn um-primary" type="button" onclick="umOpen('create')">+ Add User</button></div>
<?php if ($flash): ?><div class="um-flash <?= empty($flash['ok'])?'bad':'' ?>"><?=um_e($flash['message'])?></div><?php endif; ?>
<?php if (count($allowedRoles)<2): ?><div class="um-flash bad">The users.role ENUM does not contain both staff roles. Update the database ENUM before creating accounts.</div><?php endif; ?>
<div class="um-card"><div class="um-table-wrap"><table class="um-table"><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Account</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($users as $u): ?><tr><td><?=um_e($u['name'])?></td><td><?=um_e($u['username'])?></td><td><?=um_e($u['email'])?></td><td><?=um_e($roles[$u['role']]??$u['role'])?></td><td><span class="um-status <?=!empty($u['account_locked'])?'warn':''?>"><?=!empty($u['account_locked'])?'Locked':'Active'?></span></td><td><div class="um-actions"><button class="um-icon-btn" type="button" onclick='umEdit(<?=json_encode(['id'=>(int)$u['user_id'],'name'=>$u['name'],'username'=>$u['username'],'email'=>$u['email'],'role'=>$u['role']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>)' title="Edit account" aria-label="Edit account"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L9 17l-4 1 1-4Z"/></svg></button><button class="um-icon-btn" type="button" title="Reset password" aria-label="Reset password" onclick="umReset(<?=(int)$u['user_id']?>)"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="m11 12 9-9 2 2-2 2 2 2-3 3-2-2-3 3"/></svg></button><?php if ($u['account_locked']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=um_e(csrf_token())?>"><input type="hidden" name="action" value="unlock"><input type="hidden" name="user_id" value="<?=(int)$u['user_id']?>"><button class="um-icon-btn" type="submit" title="Unlock account" aria-label="Unlock account"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 7.5-2"/></svg></button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
<?php if (!$users): ?><tr><td colspan="6" class="um-empty">No staff accounts yet.</td></tr><?php endif; ?></tbody></table></div></div></main>
<div class="um-modal" id="umModal" role="dialog" aria-modal="true" aria-labelledby="umTitle"><div class="um-dialog"><h2 id="umTitle">Add User</h2><form method="post" class="um-form" id="umForm" autocomplete="off"><input type="hidden" name="csrf_token" value="<?=um_e(csrf_token())?>"><input type="hidden" name="action" id="umAction" value="create"><input type="hidden" name="user_id" id="umId" value="0"><div id="umFields"><label>Full Name<input name="name" id="umName" maxlength="100" required></label><label>Username<input name="username" id="umUsername" minlength="3" maxlength="100" pattern="[A-Za-z0-9_.-]+" required></label><label>Email<input name="email" id="umEmail" type="email" maxlength="150" required></label><label>Role<select name="role" id="umRole" required><?php foreach ($allowedRoles as $key=>$label): ?><option value="<?=um_e($key)?>"><?=um_e($label)?></option><?php endforeach; ?></select></label></div><div id="umPasswordGroup"><label>New Password<input type="password" name="password" id="umPassword" minlength="12" autocomplete="new-password"></label><p class="um-note">Use at least 12 characters. Passwords are securely hashed before saving.</p></div><div class="um-form-actions"><button type="button" class="um-btn" onclick="umClose()">Cancel</button><button type="submit" class="um-btn um-primary" id="umSubmit">Save User</button></div></form></div></div>
<script>
const umModal=document.getElementById('umModal');const umForm=document.getElementById('umForm');
function umOpen(action){umForm.reset();document.getElementById('umAction').value=action;document.getElementById('umId').value='0';document.getElementById('umFields').hidden=false;document.querySelectorAll('#umFields input, #umFields select').forEach(el=>el.disabled=false);document.getElementById('umPasswordGroup').hidden=action==='edit';document.getElementById('umPassword').required=action!=='edit';document.getElementById('umTitle').textContent='Add User';document.getElementById('umSubmit').textContent='Create Account';umModal.classList.add('open');document.getElementById('umName').focus()}
function umEdit(user){umOpen('edit');document.getElementById('umAction').value='edit';document.getElementById('umId').value=user.id;for(const [field,key] of [['umName','name'],['umUsername','username'],['umEmail','email'],['umRole','role']])document.getElementById(field).value=user[key]??'';document.getElementById('umTitle').textContent='Edit Staff Account';document.getElementById('umSubmit').textContent='Save Changes'}
function umReset(id){umOpen('reset_password');document.getElementById('umId').value=id;document.getElementById('umFields').hidden=true;document.querySelectorAll('#umFields input, #umFields select').forEach(el=>el.disabled=true);document.getElementById('umTitle').textContent='Reset Staff Password';document.getElementById('umSubmit').textContent='Reset Password'}
function umClose(){umModal.classList.remove('open')}umModal.addEventListener('click',e=>{if(e.target===umModal)umClose()});document.addEventListener('keydown',e=>{if(e.key==='Escape')umClose()});
</script></body></html>
