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

<nav class="sidebar">


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

            <?php if ($role === 'admin'): ?>

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
                    onclick="location.href='<?= $basePath ?>staffDashboard.php'"
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

                    <li
                        class="<?= $cropsActive ? 'sub-active' : '' ?>"
                        onclick="location.href='<?= $basePath ?>crops/crops.php'"
                    >

                        <span class="material-symbols-rounded">
                            agriculture
                        </span>

                        <span>
                            Crops
                        </span>

                    </li>


                    <!-- =========================================
                         LIVESTOCK
                    ========================================== -->

                    <li
                        class="<?= $livestockActive ? 'sub-active' : '' ?>"
                        onclick="location.href='<?= $basePath ?>livestock/livestocks.php'"
                    >

                        <span class="material-symbols-rounded">
                            pets
                        </span>

                        <span>
                            Livestock
                        </span>

                    </li>


                </ul>

            </li>


            <!-- =================================================
                 FARM MAP
            ================================================== -->

            <?php if ($role === 'admin'): ?>

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


            <!-- =================================================
                 INVENTORY
            ================================================== -->

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

                        <span>
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

                        <span>
                            Sowing Material
                        </span>

                    </li>


                </ul>

            </li>


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


            <!-- =================================================
                 LOGOUT
            ================================================== -->

            <li
                class="logout-menu-item"
                onclick="location.href='<?= $basePath ?>logout.php'"
            >

                <span class="material-symbols-rounded">
                    logout
                </span>

                <span class="menu-text">
                    Logout
                </span>

            </li>


        </ul>

    </div>


</nav>


<!-- ============================================================
     SIDEBAR FIX
============================================================ -->

<style>

/*
|--------------------------------------------------------------------------
| Keep the sidebar menu flexible
|--------------------------------------------------------------------------
*/

.sidebar-top {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
}


/*
|--------------------------------------------------------------------------
| Main menu fills available sidebar height
|--------------------------------------------------------------------------
*/

.sidebar-top .menu {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
}


/*
|--------------------------------------------------------------------------
| Logout stays at the bottom
|--------------------------------------------------------------------------
*/

.sidebar-top .logout-menu-item {
    margin-top: auto;
    flex-shrink: 0;
    cursor: pointer;
}


/*
|--------------------------------------------------------------------------
| Prevent text/icon from being squeezed when zooming
|--------------------------------------------------------------------------
*/

.sidebar-top .logout-menu-item span {
    flex-shrink: 0;
}

</style>


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


</script>