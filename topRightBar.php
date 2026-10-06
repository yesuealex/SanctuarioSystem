<?php
/* =========================================================
   SANCTUARIO SYSTEM - TOP RIGHT BAR
   ========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   DATABASE CONNECTION
   ========================================================= */

require_once __DIR__ . '/db.php';


/* =========================================================
   UNIQUE HELPER FUNCTIONS
   Prefix: trb_
   This prevents conflicts with functions from other PHP files.
   ========================================================= */

if (!function_exists('trb_escape')) {

    function trb_escape($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


if (!function_exists('trb_format_role')) {

    function trb_format_role($role)
    {
        return ucwords(
            str_replace(
                '_',
                ' ',
                trim((string)$role)
            )
        );
    }
}


if (!function_exists('trb_relative_time')) {

    function trb_relative_time($datetime)
    {
        if (empty($datetime)) {
            return '';
        }

        try {

            $time = new DateTime($datetime);
            $now  = new DateTime();

            $seconds =
                $now->getTimestamp()
                -
                $time->getTimestamp();

            if ($seconds < 0) {
                $seconds = 0;
            }


            if ($seconds < 60) {
                return 'Just now';
            }


            $minutes = floor(
                $seconds / 60
            );


            if ($minutes < 60) {

                return $minutes .
                    ' minute' .
                    ($minutes == 1 ? '' : 's') .
                    ' ago';
            }


            $hours = floor(
                $minutes / 60
            );


            if ($hours < 24) {

                return $hours .
                    ' hour' .
                    ($hours == 1 ? '' : 's') .
                    ' ago';
            }


            $days = floor(
                $hours / 24
            );


            if ($days == 1) {
                return 'Yesterday';
            }


            if ($days < 7) {

                return $days .
                    ' days ago';
            }


            return $time->format(
                'M d, Y'
            );


        } catch (Exception $exception) {

            return '';
        }
    }
}


if (!function_exists('trb_activity_icon')) {

    function trb_activity_icon($icon)
    {
        $allowedIcons = [

            'add',
            'edit',
            'delete',

            'login',
            'logout',

            'check_circle',
            'cancel',

            'warning',
            'error',

            'pets',
            'eco',
            'grass',
            'agriculture',

            'build',
            'construction',

            'inventory',
            'vaccines',

            'water_drop',
            'event',

            'person',
            'settings',

            'info',
            'circle'
        ];


        if (
            in_array(
                $icon,
                $allowedIcons,
                true
            )
        ) {

            return $icon;
        }


        return 'info';
    }
}


if (!function_exists('trb_activity_class')) {

    function trb_activity_class($type)
    {
        switch (
            strtolower(
                trim(
                    (string)$type
                )
            )
        ) {

            case 'warning':
                return 'warning';

            case 'danger':
                return 'danger';

            case 'success':
                return 'success';

            case 'info':
            default:
                return 'info';
        }
    }
}


/* =========================================================
   GET CURRENT LOGGED-IN USER
   ========================================================= */

$trb_loggedInUserId = isset(
    $_SESSION['user_id']
)
    ? (int)$_SESSION['user_id']
    : 0;


$trb_loggedInUser = null;


/*
|--------------------------------------------------------------------------
| Actual users table:
|
| user_id
| name
| username
| password
| role
|
| NO EMAIL COLUMN
|--------------------------------------------------------------------------
*/

if ($trb_loggedInUserId > 0) {

    $trb_hasProfileColumn = false;
    $trb_profileColumnResult = mysqli_query(
        $conn,
        "SHOW COLUMNS FROM users LIKE 'profile_picture'"
    );

    if ($trb_profileColumnResult) {
        $trb_hasProfileColumn = mysqli_num_rows($trb_profileColumnResult) > 0;
        mysqli_free_result($trb_profileColumnResult);
    }

    $trb_userSelect = $trb_hasProfileColumn
        ? "user_id, name, username, role, profile_picture"
        : "user_id, name, username, role";

    $trb_userStmt = mysqli_prepare(
        $conn,
        "
        SELECT {$trb_userSelect}
        FROM users
        WHERE user_id = ?
        LIMIT 1
        "
    );


    if ($trb_userStmt) {

        mysqli_stmt_bind_param(
            $trb_userStmt,
            "i",
            $trb_loggedInUserId
        );


        mysqli_stmt_execute(
            $trb_userStmt
        );


        $trb_userResult =
            mysqli_stmt_get_result(
                $trb_userStmt
            );


        if ($trb_userResult) {

            $trb_loggedInUser =
                mysqli_fetch_assoc(
                    $trb_userResult
                );
        }


        mysqli_stmt_close(
            $trb_userStmt
        );
    }
}


/* =========================================================
   FALLBACK USER
   ========================================================= */

if (!$trb_loggedInUser) {

    $trb_loggedInUser = [

        'user_id'  => 0,

        'name'     => 'User',

        'username' => 'User',

        'role'     => 'staff',

        'profile_picture' => ''
    ];
}


/* =========================================================
   PROFILE DATA
   ========================================================= */

$trb_userName =
    trim(
        $trb_loggedInUser['name']
        ?? ''
    );


if ($trb_userName === '') {

    $trb_userName =
        $trb_loggedInUser['username']
        ?? 'User';
}


$trb_userUsername =
    $trb_loggedInUser['username']
    ?? '';


$trb_userRole =
    trb_format_role(
        $trb_loggedInUser['role']
        ?? 'staff'
    );

$trb_profilePicture =
    trim((string)($trb_loggedInUser['profile_picture'] ?? ''));


/* =========================================================
   RECENT STAFF ACTIVITIES
   ========================================================= */

$trb_activities = [];


$trb_activityQuery = "

    SELECT

        sa.activity_id,

        sa.user_id,

        sa.action,

        sa.description,

        sa.activity_type,

        sa.icon,

        sa.created_at,

        u.name,

        u.username,

        u.role

    FROM staff_activities sa

    INNER JOIN users u

        ON sa.user_id = u.user_id

    ORDER BY sa.created_at DESC

    LIMIT 8

";


try {

    $trb_activityResult =
        mysqli_query(
            $conn,
            $trb_activityQuery
        );


    if ($trb_activityResult) {

        while (
            $trb_row =
            mysqli_fetch_assoc(
                $trb_activityResult
            )
        ) {

            $trb_activities[] =
                $trb_row;
        }
    }


} catch (Throwable $trb_exception) {

    /*
     * If the activity table has a problem,
     * don't break the whole dashboard.
     */

    $trb_activities = [];
}


/* =========================================================
   NOTIFICATION COUNT
   ========================================================= */

$trb_notificationCount = 0;


$trb_countQuery = "

    SELECT COUNT(*) AS total

    FROM staff_activities

    WHERE created_at >=
        DATE_SUB(
            NOW(),
            INTERVAL 24 HOUR
        )

";


try {

    $trb_countResult =
        mysqli_query(
            $conn,
            $trb_countQuery
        );


    if ($trb_countResult) {

        $trb_countRow =
            mysqli_fetch_assoc(
                $trb_countResult
            );


        $trb_notificationCount =
            (int)(
                $trb_countRow['total']
                ?? 0
            );
    }


} catch (Throwable $trb_exception) {

    $trb_notificationCount = 0;
}


/* =========================================================
   BADGE TEXT
   ========================================================= */

$trb_badgeText =
    $trb_notificationCount > 99
        ? '99+'
        : (string)$trb_notificationCount;

?>

<style>

/* =========================================================
   TOP RIGHT BAR
   ========================================================= */

.dashboard-topbar {

    width: 100%;
    height: 70px;

    display: flex;
    align-items: center;
    justify-content: flex-end;

    gap: 18px;

    padding: 0 24px;

    position: relative;

    z-index: 1000;
}


/* =========================================================
   NOTIFICATION
   ========================================================= */

.topbar-notification,
.topbar-profile {

    position: relative;
}


.notification-btn {

    width: 52px;
    height: 52px;

    border: 1px solid #e1e4dc;

    border-radius: 14px;

    background: #fff;

    display: flex;
    align-items: center;
    justify-content: center;

    cursor: pointer;

    position: relative;

    transition: .2s;
}


.notification-btn:hover {

    background: #f5f8f1;

    border-color: #cfdac6;
}


.notification-btn .material-symbols-rounded {

    font-size: 28px;

    color: #333;
}


/* =========================================================
   NOTIFICATION BADGE
   ========================================================= */

.notification-badge {

    position: absolute;

    top: -5px;
    right: -4px;

    min-width: 20px;
    height: 20px;

    padding: 0 5px;

    border-radius: 20px;

    background: #e53935;

    color: #fff;

    border: 2px solid #fff;

    font-size: 10px;

    font-weight: 700;

    display: flex;
    align-items: center;
    justify-content: center;
}


/* =========================================================
   NOTIFICATION DROPDOWN
   ========================================================= */

.notification-dropdown {

    position: absolute;

    top: calc(100% + 12px);

    right: 0;

    width: 390px;

    max-height: 520px;

    background: #fff;

    border: 1px solid #e5e8e1;

    border-radius: 14px;

    box-shadow:
        0 12px 35px rgba(0,0,0,.12);

    overflow: hidden;

    display: none;

    z-index: 2000;
}


.notification-dropdown.show {

    display: block;
}


/* =========================================================
   DROPDOWN HEADER
   ========================================================= */

.dropdown-header {

    min-height: 58px;

    padding: 10px 18px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    border-bottom: 1px solid #eee;
}


.dropdown-header strong {

    font-size: 16px;

    color: #24351d;
}


.dropdown-header span {

    font-size: 11px;

    color: #46752d;

    background: #eef6e7;

    padding: 4px 9px;

    border-radius: 20px;
}


/* =========================================================
   NOTIFICATION LIST
   ========================================================= */

.notification-list {

    max-height: 390px;

    overflow-y: auto;
}


/* =========================================================
   NOTIFICATION ITEM
   ========================================================= */

.notification-item {

    display: flex;

    gap: 12px;

    padding: 14px 18px;

    border-bottom: 1px solid #f0f0f0;

    transition: background .2s;
}


.notification-item:hover {

    background: #f8faf6;
}


/* =========================================================
   ACTIVITY ICON
   ========================================================= */

.notification-icon {

    width: 36px;
    height: 36px;

    min-width: 36px;

    border-radius: 10px;

    display: flex;

    align-items: center;
    justify-content: center;
}


.notification-icon .material-symbols-rounded {

    font-size: 20px;
}


/* =========================================================
   ACTIVITY COLORS
   ========================================================= */

.warning {

    background: #fff4d7;

    color: #d99a00;
}


.info {

    background: #eef4ff;

    color: #5278bd;
}


.success {

    background: #eaf7e5;

    color: #4c9136;
}


.danger {

    background: #fff0ee;

    color: #d64535;
}


/* =========================================================
   ACTIVITY CONTENT
   ========================================================= */

.notification-content {

    min-width: 0;

    flex: 1;
}


.notification-item strong {

    display: block;

    font-size: 13px;

    color: #252525;

    margin-bottom: 3px;
}


.notification-item p {

    margin: 0;

    font-size: 11px;

    color: #777;

    line-height: 1.45;

    word-break: break-word;
}


.notification-item small {

    display: block;

    margin-top: 5px;

    font-size: 10px;

    color: #aaa;
}


.activity-user {

    color: #46752d;

    font-weight: 600;
}


/* =========================================================
   NO NOTIFICATIONS
   ========================================================= */

.no-notifications {

    padding: 35px 20px;

    text-align: center;

    color: #999;
}


.no-notifications
.material-symbols-rounded {

    display: block;

    font-size: 38px;

    margin-bottom: 8px;

    color: #c9cec5;
}


.no-notifications strong {

    display: block;

    color: #666;

    font-size: 13px;

    margin-bottom: 4px;
}


.no-notifications p {

    margin: 0;

    font-size: 11px;

    color: #999;
}


/* =========================================================
   NOTIFICATION FOOTER
   ========================================================= */

.notification-footer {

    padding: 12px;

    text-align: center;

    border-top: 1px solid #eee;

    background: #fff;
}


.notification-footer a {

    color: #386328;

    font-size: 12px;

    font-weight: 600;

    text-decoration: none;
}


.notification-footer a:hover {

    text-decoration: underline;
}


/* =========================================================
   PROFILE BUTTON
   ========================================================= */

.profile-btn {

    min-width: 235px;

    height: 58px;

    padding: 6px 12px 6px 18px;

    border: 1px solid #e1e4dc;

    border-radius: 16px;

    background: #ffffff;

    display: flex;

    align-items: center;

    justify-content: flex-end;

    gap: 12px;

    cursor: pointer;

    transition: .2s;
}


.profile-btn:hover,
.profile-btn.active {

    background: #f2f5ef;

    border-color: #cfdac6;
}


/* =========================================================
   PROFILE INFO
   ========================================================= */

.profile-info {

    display: flex;

    flex-direction: column;

    align-items: flex-end;

    line-height: 1.15;

    max-width: 135px;
}


.profile-info strong {

    font-size: 15px;

    color: #214c25;

    font-weight: 700;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

    max-width: 135px;
}


.profile-info span {

    margin-top: 3px;

    font-size: 13px;

    color: #888;
}


/* =========================================================
   PROFILE AVATAR
   ========================================================= */

.profile-avatar {

    width: 43px;
    height: 43px;

    display: flex;

    align-items: center;
    justify-content: center;
}


.profile-avatar .material-symbols-rounded {

    font-size: 43px;

    color: #202020;
}


/* =========================================================
   PROFILE ARROW
   ========================================================= */

.profile-arrow {

    font-size: 23px;

    color: #333;

    transition: transform .2s;
}


.profile-btn.active
.profile-arrow {

    transform: rotate(180deg);
}


/* =========================================================
   PROFILE DROPDOWN
   ========================================================= */

.profile-dropdown {

    position: absolute;

    top: calc(100% + 12px);

    right: 0;

    width: 245px;

    background: #fff;

    border: 1px solid #e5e8e1;

    border-radius: 14px;

    box-shadow:
        0 12px 35px rgba(0,0,0,.12);

    overflow: hidden;

    display: none;

    z-index: 2000;
}


.profile-dropdown.show {

    display: block;
}


/* =========================================================
   PROFILE DROPDOWN USER
   ========================================================= */

.profile-dropdown-user {

    padding: 16px;

    display: flex;

    align-items: center;

    gap: 11px;
}


.profile-dropdown-avatar {

    width: 42px;
    height: 42px;

    display: flex;

    align-items: center;
    justify-content: center;
}


.profile-dropdown-avatar
.material-symbols-rounded {

    font-size: 42px;

    color: #333;
}


.profile-dropdown-user strong {

    display: block;

    font-size: 13px;

    color: #222;
}


.profile-dropdown-user span {

    display: block;

    margin-top: 3px;

    font-size: 11px;

    color: #888;
}


/* =========================================================
   DIVIDER
   ========================================================= */

.dropdown-divider {

    height: 1px;

    background: #eee;
}


/* =========================================================
   PROFILE LINKS
   ========================================================= */

.profile-dropdown a {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 12px 16px;

    color: #444;

    font-size: 13px;

    text-decoration: none;
}


.profile-dropdown a:hover {

    background: #f5f8f1;

    color: #315c25;
}


.profile-dropdown a
.material-symbols-rounded {

    font-size: 20px;
}


/* =========================================================
   LOGOUT
   ========================================================= */

.profile-dropdown .logout-link {

    color: #c0392b;
}


.profile-dropdown
.logout-link:hover {

    background: #fff4f2;

    color: #b52b20;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media(max-width:700px) {

    .dashboard-topbar {

        height: 65px;

        padding: 0 12px;

        gap: 8px;
    }


    .notification-btn {

        width: 48px;
        height: 48px;
    }


    .profile-btn {

        min-width: 52px;

        width: 52px;

        padding: 4px;

        justify-content: center;

        border-radius: 14px;
    }


    .profile-info,
    .profile-arrow {

        display: none;
    }


    .notification-dropdown {

        right: 0;

        width: min(
            390px,
            calc(100vw - 24px)
        );
    }


    .profile-dropdown {

        right: 0;
    }

}

</style>


<!-- =========================================================
     TOP RIGHT BAR
     ========================================================= -->

<div class="dashboard-topbar">


    <!-- =====================================================
         NOTIFICATIONS
         ===================================================== -->

    <div class="topbar-notification">


        <button
            type="button"
            class="notification-btn"
            id="notificationBtn"
            aria-label="Notifications"
        >

            <span class="material-symbols-rounded">
                notifications
            </span>


            <?php if ($trb_notificationCount > 0): ?>

                <span class="notification-badge">

                    <?= trb_escape(
                        $trb_badgeText
                    ) ?>

                </span>

            <?php endif; ?>

        </button>



        <!-- =================================================
             NOTIFICATION DROPDOWN
             ================================================= -->

        <div
            class="notification-dropdown"
            id="notificationDropdown"
        >


            <div class="dropdown-header">

                <strong>
                    Recent Staff Activities
                </strong>


                <span>

                    <?= trb_escape(
                        $trb_notificationCount
                    ) ?>

                    new

                </span>

            </div>



            <div class="notification-list">


                <?php if (!empty($trb_activities)): ?>


                    <?php foreach (
                        $trb_activities
                        as $trb_activity
                    ): ?>


                        <?php

                        $trb_activityClass =
                            trb_activity_class(
                                $trb_activity[
                                    'activity_type'
                                ] ?? 'info'
                            );


                        $trb_activityIcon =
                            trb_activity_icon(
                                $trb_activity[
                                    'icon'
                                ] ?? 'info'
                            );


                        $trb_staffName =
                            trim(
                                $trb_activity[
                                    'name'
                                ] ?? ''
                            );


                        if (
                            $trb_staffName === ''
                        ) {

                            $trb_staffName =
                                $trb_activity[
                                    'username'
                                ]
                                ?? 'Staff';
                        }


                        $trb_activityRole =
                            trb_format_role(
                                $trb_activity[
                                    'role'
                                ] ?? 'staff'
                            );

                        ?>


                        <div
                            class="notification-item"
                        >


                            <span
                                class="
                                    notification-icon
                                    <?= trb_escape(
                                        $trb_activityClass
                                    ) ?>
                                "
                            >

                                <span
                                    class="
                                        material-symbols-rounded
                                    "
                                >

                                    <?= trb_escape(
                                        $trb_activityIcon
                                    ) ?>

                                </span>

                            </span>



                            <div
                                class="
                                    notification-content
                                "
                            >


                                <strong>

                                    <?= trb_escape(
                                        $trb_activity[
                                            'action'
                                        ]
                                        ??
                                        'Staff Activity'
                                    ) ?>

                                </strong>


                                <p>

                                    <?= trb_escape(
                                        $trb_activity[
                                            'description'
                                        ] ?? ''
                                    ) ?>

                                </p>


                                <small>

                                    <span
                                        class="
                                            activity-user
                                        "
                                    >

                                        <?= trb_escape(
                                            $trb_staffName
                                        ) ?>

                                    </span>


                                    ·


                                    <?= trb_escape(
                                        $trb_activityRole
                                    ) ?>


                                    ·


                                    <?= trb_escape(
                                        trb_relative_time(
                                            $trb_activity[
                                                'created_at'
                                            ] ?? ''
                                        )
                                    ) ?>

                                </small>


                            </div>


                        </div>


                    <?php endforeach; ?>


                <?php else: ?>


                    <div
                        class="no-notifications"
                    >

                        <span
                            class="
                                material-symbols-rounded
                            "
                        >
                            notifications_none
                        </span>


                        <strong>
                            No recent staff activity
                        </strong>


                        <p>
                            Staff activities will appear here.
                        </p>

                    </div>


                <?php endif; ?>


            </div>



            <!-- =================================================
                 FOOTER
                 ================================================= -->

            <div
                class="notification-footer"
            >

                <a href="notifications.php">

                    View all notifications

                </a>

            </div>


        </div>

    </div>



    <!-- =====================================================
         PROFILE
         ===================================================== -->

    <div class="topbar-profile">


        <button
            type="button"
            class="profile-btn"
            id="profileBtn"
            aria-label="User Profile"
        >


            <div class="profile-info">

                <strong>

                    <?= trb_escape(
                        $trb_userName
                    ) ?>

                </strong>


                <span>

                    <?= trb_escape(
                        $trb_userRole
                    ) ?>

                </span>

            </div>



            <div class="profile-avatar">

                <?php if ($trb_profilePicture !== ''): ?>

                    <img
                        src="<?= trb_escape($trb_profilePicture) ?>"
                        alt="Profile"
                        class="topbar-profile-image"
                    >

                <?php else: ?>

                    <span
                        class="
                            material-symbols-rounded
                        "
                    >
                        account_circle
                    </span>

                <?php endif; ?>

            </div>



            <span
                class="
                    material-symbols-rounded
                    profile-arrow
                "
            >
                keyboard_arrow_down
            </span>


        </button>



        <!-- =================================================
             PROFILE DROPDOWN
             ================================================= -->

        <div
            class="profile-dropdown"
            id="profileDropdown"
        >


            <div
                class="
                    profile-dropdown-user
                "
            >


                <div
                    class="
                        profile-dropdown-avatar
                    "
                >

                    <?php if ($trb_profilePicture !== ''): ?>

                        <img
                            src="<?= trb_escape($trb_profilePicture) ?>"
                            alt="Profile"
                            class="topbar-profile-image"
                        >

                    <?php else: ?>

                        <span
                            class="
                                material-symbols-rounded
                            "
                        >
                            account_circle
                        </span>

                    <?php endif; ?>

                </div>


                <div>

                    <strong>

                        <?= trb_escape(
                            $trb_userName
                        ) ?>

                    </strong>


                    <span>

                        <?= trb_escape(
                            $trb_userRole
                        ) ?>

                    </span>

                </div>


            </div>



            <div
                class="dropdown-divider"
            ></div>



            <a
                href="accountSettings/accountSettings.php"
            >

                <span
                    class="
                        material-symbols-rounded
                    "
                >
                    settings
                </span>

                Account Settings

            </a>



            <div
                class="dropdown-divider"
            ></div>



            <a
                href="logout.php"
                class="logout-link"
            >

                <span
                    class="
                        material-symbols-rounded
                    "
                >
                    logout
                </span>

                Logout

            </a>


        </div>

    </div>

</div>



<script>

/* =========================================================
   TOP RIGHT BAR JAVASCRIPT
   ========================================================= */

(function () {


    const notificationBtn =
        document.getElementById(
            "notificationBtn"
        );


    const notificationDropdown =
        document.getElementById(
            "notificationDropdown"
        );


    const profileBtn =
        document.getElementById(
            "profileBtn"
        );


    const profileDropdown =
        document.getElementById(
            "profileDropdown"
        );


    /* =====================================================
       NOTIFICATION BUTTON
       ===================================================== */

    if (
        notificationBtn &&
        notificationDropdown
    ) {

        notificationBtn.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();


                notificationDropdown.classList.toggle(
                    "show"
                );


                if (profileDropdown) {

                    profileDropdown.classList.remove(
                        "show"
                    );
                }


                if (profileBtn) {

                    profileBtn.classList.remove(
                        "active"
                    );
                }

            }
        );

    }


    /* =====================================================
       PROFILE BUTTON
       ===================================================== */

    if (
        profileBtn &&
        profileDropdown
    ) {

        profileBtn.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();


                profileDropdown.classList.toggle(
                    "show"
                );


                profileBtn.classList.toggle(
                    "active"
                );


                if (notificationDropdown) {

                    notificationDropdown.classList.remove(
                        "show"
                    );
                }

            }
        );

    }


    /* =====================================================
       KEEP DROPDOWNS OPEN WHEN CLICKED
       ===================================================== */

    if (notificationDropdown) {

        notificationDropdown.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();

            }
        );

    }


    if (profileDropdown) {

        profileDropdown.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();

            }
        );

    }


    /* =====================================================
       CLICK OUTSIDE
       ===================================================== */

    document.addEventListener(
        "click",
        function () {


            if (notificationDropdown) {

                notificationDropdown.classList.remove(
                    "show"
                );
            }


            if (profileDropdown) {

                profileDropdown.classList.remove(
                    "show"
                );
            }


            if (profileBtn) {

                profileBtn.classList.remove(
                    "active"
                );
            }

        }
    );


    /* =====================================================
       ESCAPE KEY
       ===================================================== */

    document.addEventListener(
        "keydown",
        function (event) {

            if (event.key === "Escape") {


                if (notificationDropdown) {

                    notificationDropdown.classList.remove(
                        "show"
                    );
                }


                if (profileDropdown) {

                    profileDropdown.classList.remove(
                        "show"
                    );
                }


                if (profileBtn) {

                    profileBtn.classList.remove(
                        "active"
                    );
                }

            }

        }
    );


})();

</script>
<style>
.topbar-profile-image {
    width: 43px;
    height: 43px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
}
.profile-dropdown-avatar .topbar-profile-image {
    width: 42px;
    height: 42px;
}
</style>
