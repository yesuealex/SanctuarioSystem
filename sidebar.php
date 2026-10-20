<?php
/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| USER ROLE
|--------------------------------------------------------------------------
*/

$role = $_SESSION['role'] ?? 'staff';


/*
|--------------------------------------------------------------------------
| PROJECT ROOT PATH
|--------------------------------------------------------------------------
*/

$basePath = '/SanctuarioSystem/';


/*
|--------------------------------------------------------------------------
| CURRENT PAGE
|--------------------------------------------------------------------------
*/

$page = basename($_SERVER['PHP_SELF']);
$currentPath = $_SERVER['PHP_SELF'];


/*
|--------------------------------------------------------------------------
| FARM RECORDS ACTIVE STATE
|--------------------------------------------------------------------------
*/

$recordsPages = [
    'records.php',
    'crops.php',
    'livestocks.php',
    'add_crop.php',
    'edit_crop.php',
    'delete_crop.php',
    'add_livestock.php',
    'edit_livestock.php',
    'delete_livestock.php'
];

$recordsActive = in_array($page, $recordsPages);


/*
|--------------------------------------------------------------------------
| CROPS ACTIVE STATE
|--------------------------------------------------------------------------
*/

$cropsActive = (
    strpos($currentPath, '/crops/') !== false
);


/*
|--------------------------------------------------------------------------
| LIVESTOCK ACTIVE STATE
|--------------------------------------------------------------------------
*/

$livestockActive = (
    strpos($currentPath, '/livestock/') !== false
);


/*
|--------------------------------------------------------------------------
| INVENTORY ACTIVE STATE
|--------------------------------------------------------------------------
*/

$inventoryActive = (
    strpos($currentPath, '/inventory/') !== false
);


/*
|--------------------------------------------------------------------------
| ACCOUNT SETTINGS ACTIVE STATE
|--------------------------------------------------------------------------
*/

$accountSettingsActive = (
    strpos($currentPath, '/accountSettings/') !== false
);

?>


<!-- ============================================================
     SIDEBAR CSS
============================================================ -->

<link
    rel="stylesheet"
    href="<?= $basePath ?>sidebar.css"
>


<!-- ============================================================
     MATERIAL SYMBOLS
============================================================ -->

<link
    rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0,0"
>


<!-- ============================================================
     SIDEBAR
============================================================ -->

<button type="button" class="mobile-menu-toggle" aria-label="Open navigation menu" aria-controls="sanctuarioSidebar" aria-expanded="false"><span class="material-symbols-rounded">menu</span></button>
<div class="sidebar-backdrop" aria-hidden="true"></div>
<nav class="sidebar" id="sanctuarioSidebar">


    <!-- ========================================================
         SIDEBAR TOP
    ========================================================= -->

    <div class="sidebar-top">


        <!-- ====================================================
             LOGO
        ===================================================== -->

        <div class="sidebar-logo-container">

            <img
                src="<?= $basePath ?>logo2.png"
                alt="Sanctuario Nature Farm"
                class="sidebar-logo"
            >

        </div>


        <!-- ====================================================
             MENU
        ===================================================== -->

        <ul class="menu">


            <!-- =================================================
                 DASHBOARD
            ================================================== -->

            <?php if (in_array($role, ['admin','super admin'], true)): ?>

                <li
                    class="<?= $page === 'adminDashboard.php' ? 'active' : '' ?>"
                    onclick="location.href='<?= $basePath ?>adminDashboard.php'"
                >

                    <span class="material-symbols-rounded">
                        dashboard
                    </span>

                    <span class="menu-text">
                        Dashboard
                    </span>

                </li>

            <?php else: ?>

                <li
                    class="<?= $page === 'staffDashboard.php' ? 'active' : '' ?>"
                    onclick="location.href='<?= $basePath ?>staff/staffDashboard.php'"
                >

                    <span class="material-symbols-rounded">
                        dashboard
                    </span>

                    <span class="menu-text">
                        Dashboard
                    </span>

                </li>

            <?php endif; ?>


            <!-- =================================================
                 FARM RECORDS
            ================================================== -->

            <li
                class="menu-dropdown <?= $recordsActive ? 'active-parent open' : '' ?>"
            >

                <div
                    class="sidebar-records-header"
                    onclick="toggleRecordsDropdown(event)"
                >

                    <span class="sidebar-records-left">

                        <span class="material-symbols-rounded">
                            grass
                        </span>

                        <span class="menu-text">
                            Farm Records
                        </span>

                    </span>


                    <span class="material-symbols-rounded sidebar-records-arrow">
                        expand_more
                    </span>

                </div>


                <!-- =============================================
                     FARM RECORDS SUBMENU
                ============================================== -->

                <ul class="sidebar-records-submenu">


                    <!-- =========================================
                         CROPS
                    ========================================== -->

                    <?php if ($role !== 'livestock staff'): ?><li
                        class="<?= $cropsActive ? 'sub-active' : '' ?>"
                        onclick="location.href='<?= $basePath ?><?= in_array($role, ['crops staff','livestock staff'], true) ? 'staff/crops/index.php' : 'crops/crops.php' ?>'"
                    >

                        <span class="material-symbols-rounded">
                            agriculture
                        </span>

                        <span class="menu-text">
                            Crops
                        </span>

                    </li>


                    <?php endif; ?>
                    <!-- =========================================
                         LIVESTOCK
                    ========================================== -->

                    <?php if ($role !== 'crops staff'): ?><li
                        class="<?= $livestockActive ? 'sub-active' : '' ?>"
                        onclick="location.href='<?= $basePath ?><?= in_array($role, ['crops staff','livestock staff'], true) ? 'staff/livestock/index.php' : 'livestock/livestocks.php' ?>'"
                    >

                        <span class="material-symbols-rounded">
                            pets
                        </span>

                        <span class="menu-text">
                            Livestock
                        </span>

                    </li>


                    <?php endif; ?>
                </ul>

            </li>


            <!-- =================================================
                 FARM MAP
            ================================================== -->

            <?php if (in_array($role, ['admin','super admin'], true)): ?>

                <li
                    class="<?= $page === 'farmMap.php' ? 'active' : '' ?>"
                    onclick="location.href='<?= $basePath ?>farmMap/farmMap.php'"
                >

                    <span class="material-symbols-rounded">
                        map
                    </span>

                    <span class="menu-text">
                        Farm Map
                    </span>

                </li>

            <?php endif; ?>

            <?php if (in_array($role, ['crops staff','livestock staff'], true)): ?>
                <li class="<?= $page === 'farmMap.php' && strpos($currentPath, '/staff/') !== false ? 'active' : '' ?>"
                    onclick="location.href='<?= $basePath ?>staff/farmMap.php'">
                    <span class="material-symbols-rounded">map</span>
                    <span class="menu-text">Farm Map</span>
                </li>
            <?php endif; ?>

            <!-- =================================================
                 INVENTORY
            ================================================== -->
            <?php if (in_array($role, ['admin','super admin'], true)): ?>

            <li
                class="menu-dropdown <?= $inventoryActive ? 'active-parent open' : '' ?>"
            >

                <div
                    class="sidebar-records-header"
                    onclick="toggleInventoryDropdown(event)"
                >

                    <span class="sidebar-records-left">

                        <span class="material-symbols-rounded">
                            inventory_2
                        </span>

                        <span class="menu-text">
                            Inventory
                        </span>

                    </span>


                    <span class="material-symbols-rounded sidebar-records-arrow">
                        expand_more
                    </span>

                </div>


                <!-- =============================================
                     INVENTORY SUBMENU
                ============================================== -->

                <ul class="sidebar-records-submenu">


                    <!-- =========================================
                         SEED
                    ========================================== -->

                    <li
                        class="<?= $page === 'seed.php' ? 'sub-active' : '' ?>"
                        onclick="location.href='<?= $basePath ?>inventory/seed.php'"
                    >

                        <span class="material-symbols-rounded">
                            grass
                        </span>

                        <span class="menu-text">
                            Seed
                        </span>

                    </li>


                    <!-- =========================================
                         SOWING MATERIAL
                    ========================================== -->

                    <li
                        class="<?= $page === 'sowingMaterial.php' ? 'sub-active' : '' ?>"
                        onclick="location.href='<?= $basePath ?>inventory/sowingMaterial.php'"
                    >

                        <span class="material-symbols-rounded">
                            construction
                        </span>

                        <span class="menu-text">
                            Sowing Material
                        </span>

                    </li>


                </ul>

            </li>


            <?php endif; ?>
            <!-- =================================================
                 ACCOUNT SETTINGS
            ================================================== -->

            <li
                class="<?= $accountSettingsActive ? 'active' : '' ?>"
                onclick="location.href='<?= $basePath ?>accountSettings/accountSettings.php'"
            >

                <span class="material-symbols-rounded">
                    account_circle
                </span>

                <span class="menu-text">
                    Account Settings
                </span>

            </li>


        </ul>

        <!-- Bottom navigation stays pinned below the scrollable menu. -->
        <div class="sidebar-bottom-navigation">
            <div class="sidebar-bottom-divider" aria-hidden="true"></div>
            <?php if (in_array($role, ['admin', 'super admin'], true)): ?>
            <a class="sidebar-bottom-link website-management-menu-item <?= $page === 'websiteManagement.php' ? 'active' : '' ?>"
               href="<?= $basePath ?>websiteManagement.php">
                <span class="material-symbols-rounded">language</span>
                <span class="menu-text">Website Management</span>
            </a>
            <?php endif; ?>
            <a class="sidebar-bottom-link logout-menu-item" href="<?= $basePath ?>logout.php">
                <span class="material-symbols-rounded">logout</span>
                <span class="menu-text">Logout</span>
            </a>
        </div>

    </div>


</nav>
<!-- ============================================================
     SIDEBAR JAVASCRIPT
============================================================ -->

<script>

function toggleRecordsDropdown(event) {

    event.stopPropagation();

    const dropdown =
        event.currentTarget.closest('.menu-dropdown');

    if (!dropdown) {
        return;
    }

    dropdown.classList.toggle('open');

}


function toggleInventoryDropdown(event) {
    event.stopPropagation();
    const dropdown = event.currentTarget.closest('.menu-dropdown');
    if (!dropdown) return;
    dropdown.classList.toggle('open');
}

(function () {
    const sidebar = document.getElementById('sanctuarioSidebar');
    const toggle = document.querySelector('.mobile-menu-toggle');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (!sidebar || !toggle || !backdrop) return;
    function setOpen(open) {
        sidebar.classList.toggle('mobile-open', open);
        backdrop.classList.toggle('visible', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
        toggle.querySelector('.material-symbols-rounded').textContent = open ? 'close' : 'menu';
        document.body.classList.toggle('sidebar-menu-open', open);
    }
    toggle.addEventListener('click', function () { setOpen(!sidebar.classList.contains('mobile-open')); });
    backdrop.addEventListener('click', function () { setOpen(false); });
    sidebar.addEventListener('click', function (event) {
        if (event.target.closest('.menu > li:not(.menu-dropdown)') || event.target.closest('.sidebar-records-submenu > li')) setOpen(false);
    });
    window.addEventListener('resize', function () { if (window.innerWidth > 900) setOpen(false); });
})();


</script>