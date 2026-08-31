<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Top Right Bar</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Material+Symbols+Rounded:wght@400" rel="stylesheet">

<style>
*{box-sizing:border-box}
body{margin:0;font-family:Poppins,sans-serif;background:#f8f9ff}
.dashboard-topbar{width:100%;height:70px;display:flex;align-items:center;justify-content:flex-end;gap:18px;padding:0 24px;position:relative;z-index:1000}

/* Notification */
.topbar-notification,.topbar-profile{position:relative}
.notification-btn{width:52px;height:52px;border:1px solid #e1e4dc;border-radius:14px;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;transition:.2s}
.notification-btn:hover{background:#f5f8f1;border-color:#cfdac6}
.notification-btn .material-symbols-rounded{font-size:28px;color:#333}
.notification-badge{position:absolute;top:-5px;right:-4px;min-width:20px;height:20px;padding:0 5px;border-radius:20px;background:#e53935;color:#fff;border:2px solid #fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center}

.notification-dropdown{position:absolute;top:calc(100% + 12px);right:0;width:350px;background:#fff;border:1px solid #e5e8e1;border-radius:14px;box-shadow:0 12px 35px rgba(0,0,0,.12);overflow:hidden;display:none;z-index:2000}
.notification-dropdown.show{display:block}
.dropdown-header{height:58px;padding:0 18px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #eee}
.dropdown-header strong{font-size:16px;color:#24351d}
.dropdown-header span{font-size:11px;color:#46752d;background:#eef6e7;padding:4px 9px;border-radius:20px}
.notification-item{display:flex;gap:12px;padding:14px 18px;border-bottom:1px solid #f0f0f0}
.notification-item:hover{background:#f8faf6}
.notification-icon{width:36px;height:36px;min-width:36px;border-radius:10px;display:flex;align-items:center;justify-content:center}
.notification-icon .material-symbols-rounded{font-size:20px}
.warning{background:#fff4d7;color:#d99a00}.info{background:#eef4ff;color:#5278bd}.success{background:#eaf7e5;color:#4c9136}
.notification-item strong{display:block;font-size:13px;color:#252525;margin-bottom:3px}
.notification-item p{margin:0;font-size:11px;color:#777;line-height:1.4}
.notification-item small{display:block;margin-top:4px;font-size:10px;color:#aaa}
.notification-footer{padding:12px;text-align:center}
.notification-footer a{color:#386328;font-size:12px;font-weight:600;text-decoration:none}

/* Profile */
.profile-btn{min-width:235px;height:58px;padding:6px 12px 6px 18px;border:1px solid #e1e4dc;border-radius:16px;background:#ffffff;display:flex;align-items:center;justify-content:flex-end;gap:12px;cursor:pointer;transition:.2s}
.profile-btn:hover,.profile-btn.active{background:#f2f5ef;border-color:#cfdac6}
.profile-info{display:flex;flex-direction:column;align-items:flex-end;line-height:1.15}
.profile-info strong{font-size:15px;color:#214c25;font-weight:700}
.profile-info span{margin-top:3px;font-size:13px;color:#888}
.profile-avatar{width:43px;height:43px;display:flex;align-items:center;justify-content:center}
.profile-avatar .material-symbols-rounded{font-size:43px;color:#202020}
.profile-arrow{font-size:23px;color:#333;transition:transform .2s}
.profile-btn.active .profile-arrow{transform:rotate(180deg)}

.profile-dropdown{position:absolute;top:calc(100% + 12px);right:0;width:245px;background:#fff;border:1px solid #e5e8e1;border-radius:14px;box-shadow:0 12px 35px rgba(0,0,0,.12);overflow:hidden;display:none;z-index:2000}
.profile-dropdown.show{display:block}
.profile-dropdown-user{padding:16px;display:flex;align-items:center;gap:11px}
.profile-dropdown-avatar{width:42px;height:42px;display:flex;align-items:center;justify-content:center}
.profile-dropdown-avatar .material-symbols-rounded{font-size:42px;color:#333}
.profile-dropdown-user strong{display:block;font-size:13px;color:#222}
.profile-dropdown-user span{display:block;margin-top:3px;font-size:11px;color:#888}
.dropdown-divider{height:1px;background:#eee}
.profile-dropdown a{display:flex;align-items:center;gap:12px;padding:12px 16px;color:#444;font-size:13px;text-decoration:none}
.profile-dropdown a:hover{background:#f5f8f1;color:#315c25}
.profile-dropdown a .material-symbols-rounded{font-size:20px}
.profile-dropdown .logout-link{color:#c0392b}
.profile-dropdown .logout-link:hover{background:#fff4f2;color:#b52b20}

@media(max-width:700px){
.dashboard-topbar{height:65px;padding:0 12px;gap:8px}
.notification-btn{width:48px;height:48px}
.profile-btn{min-width:52px;width:52px;padding:4px;justify-content:center;border-radius:14px}
.profile-info,.profile-arrow{display:none}
.notification-dropdown{right:-55px;width:min(350px,calc(100vw - 24px))}
}
</style>
</head>

<body>

<div class="dashboard-topbar">

    <div class="topbar-notification">
        <button type="button" class="notification-btn" id="notificationBtn">
            <span class="material-symbols-rounded">notifications</span>
            <span class="notification-badge">3</span>
        </button>

        <div class="notification-dropdown" id="notificationDropdown">
            <div class="dropdown-header">
                <strong>Notifications</strong>
                <span>3 new</span>
            </div>

            <div class="notification-item">
                <span class="notification-icon warning">
                    <span class="material-symbols-rounded">warning</span>
                </span>
                <div>
                    <strong>Low Stock</strong>
                    <p>Chicken Feed is running low.</p>
                    <small>5 minutes ago</small>
                </div>
            </div>

            <div class="notification-item">
                <span class="notification-icon info">
                    <span class="material-symbols-rounded">pets</span>
                </span>
                <div>
                    <strong>Livestock Reminder</strong>
                    <p>3 animals are due for vaccination.</p>
                    <small>1 hour ago</small>
                </div>
            </div>

            <div class="notification-item">
                <span class="notification-icon success">
                    <span class="material-symbols-rounded">check_circle</span>
                </span>
                <div>
                    <strong>Irrigation Schedule</strong>
                    <p>Plot B irrigation is scheduled today.</p>
                    <small>2 hours ago</small>
                </div>
            </div>

            <div class="notification-footer">
                <a href="notifications.php">View all notifications</a>
            </div>
        </div>
    </div>

    <div class="topbar-profile">
        <button type="button" class="profile-btn" id="profileBtn">
            <div class="profile-info">
                <strong>Manalo, X.</strong>
                <span>Admin</span>
            </div>
            <div class="profile-avatar">
                <span class="material-symbols-rounded">account_circle</span>
            </div>
            <span class="material-symbols-rounded profile-arrow">keyboard_arrow_down</span>
        </button>

        <div class="profile-dropdown" id="profileDropdown">
            <div class="profile-dropdown-user">
                <div class="profile-dropdown-avatar">
                    <span class="material-symbols-rounded">account_circle</span>
                </div>
                <div>
                    <strong>Manalo, X.</strong>
                    <span>Admin</span>
                </div>
            </div>

            <div class="dropdown-divider"></div>

            <a href="accountSettings/accountSettings.php"><span class="material-symbols-rounded">settings</span>Account Settings</a>

            <div class="dropdown-divider"></div>

            <a href="logout.php" class="logout-link"><span class="material-symbols-rounded">logout</span>Logout</a>
        </div>
    </div>

</div>

<script>
const notificationBtn=document.getElementById("notificationBtn");
const notificationDropdown=document.getElementById("notificationDropdown");
const profileBtn=document.getElementById("profileBtn");
const profileDropdown=document.getElementById("profileDropdown");

notificationBtn.addEventListener("click",e=>{
    e.stopPropagation();
    notificationDropdown.classList.toggle("show");
    profileDropdown.classList.remove("show");
    profileBtn.classList.remove("active");
});

profileBtn.addEventListener("click",e=>{
    e.stopPropagation();
    profileDropdown.classList.toggle("show");
    profileBtn.classList.toggle("active");
    notificationDropdown.classList.remove("show");
});

notificationDropdown.addEventListener("click",e=>e.stopPropagation());
profileDropdown.addEventListener("click",e=>e.stopPropagation());

document.addEventListener("click",()=>{
    notificationDropdown.classList.remove("show");
    profileDropdown.classList.remove("show");
    profileBtn.classList.remove("active");
});
</script>

</body>
</html>
