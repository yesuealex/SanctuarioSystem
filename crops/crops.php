<?php
session_start();

include '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'staff';

/*
|--------------------------------------------------------------------------
| Get active crop records
|--------------------------------------------------------------------------
*/

$cropsResult = $conn->query("
    SELECT *
    FROM crops
    WHERE archived = 0
    ORDER BY crop_id DESC
");
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Crops Records</title>


    <!-- MAIN CSS -->

    <link
        rel="stylesheet"
        href="../style.css"
    >

    <link
        rel="stylesheet"
        href="../sidebar.css"
    >

    <!-- CROPS CSS -->

    <link
        rel="stylesheet"
        href="crops.css"
    >


    <!-- GOOGLE FONT -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- MATERIAL ICONS -->

    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:wght@400"
        rel="stylesheet"
    >

</head>


<body>


<div class="layout">


    <!-- =====================================================
         SIDEBAR
         ===================================================== -->

    <?php include '../sidebar.php'; ?>


    <!-- =====================================================
         MAIN CONTENT
         ===================================================== -->

    <main class="dashboard">


        <!-- TOP RIGHT BAR -->

        <?php include '../topRightBar.php'; ?>


        <!-- =================================================
             PAGE TITLE
             ================================================= -->

        <h2>Farm Records</h2>


        <!-- =================================================
             CROPS SECTION
             ================================================= -->

        <section class="records-section">


            <!-- =================================================
                 HEADER
                 ================================================= -->

            <div class="records-header">


                <div>

                    <h3>
                        Crops
                    </h3>

                    <p class="records-subtitle">
                        Manage active crop records
                    </p>

                </div>


                <!-- =================================================
                     HEADER ACTIONS
                     ================================================= -->

                <div class="records-actions">


                    <?php if ($role === 'admin'): ?>


                        <!-- ARCHIVED CROPS -->

                        <button
                            type="button"
                            class="archive-view-btn"
                            onclick="location.href='archived_crops.php'"
                        >

                            <span class="material-symbols-rounded">
                                inventory_2
                            </span>

                            Archived Crops

                        </button>


                        <!-- ADD CROP -->

                        <button
                            type="button"
                            class="add-btn"
                            onclick="openEditModal(
                                'add_crop.php',
                                'Add Crop'
                            )"
                        >

                            <span class="material-symbols-rounded">
                                add
                            </span>

                            Add Crop

                        </button>


                    <?php endif; ?>


                </div>

            </div>


            <!-- =================================================
                 FILTER / SEARCH
                 ================================================= -->

            <div class="records-toolbar">


                <!-- PLOT DROPDOWN -->

                <select
                    id="cropPlotFilter"
                    class="records-select"
                    onchange="filterCropRecords()"
                >

                    <option value="all">
                        All Locations
                    </option>

                    <option value="nursery">
                        Nursery
                    </option>

                    <option value="plot a">
                        Plot A
                    </option>

                    <option value="plot b">
                        Plot B
                    </option>

                    <option value="plot c">
                        Plot C
                    </option>

                </select>


                <!-- SEARCH -->

                <div class="search-box">

                    <span class="material-symbols-rounded">
                        search
                    </span>

                    <input
                        type="text"
                        id="cropSearch"
                        placeholder="Search crops..."
                        onkeyup="filterCropRecords()"
                    >

                </div>


            </div>


            <!-- =================================================
                 TABLE
                 ================================================= -->

            <div class="table-container">


                <table id="cropTable">


                    <thead>

                        <tr>

                            <th>
                                Crop Name
                            </th>

                            <th>
                                Plot
                            </th>

                            <th>
                                Staff
                            </th>

                            <th>
                                Variety
                            </th>

                            <th>
                                Pieces
                            </th>

                            <th>
                                Germination
                            </th>

                            <th>
                                Area
                            </th>

                            <th>
                                Transplanted
                            </th>

                            <th>
                                Harvest
                            </th>


                            <?php if ($role === 'admin'): ?>

                                <th>
                                    Action
                                </th>

                            <?php endif; ?>


                        </tr>

                    </thead>


                    <tbody>


                        <?php if ($cropsResult && $cropsResult->num_rows > 0): ?>


                            <?php while ($row = $cropsResult->fetch_assoc()): ?>


                                <tr class="crop-row">


                                    <!-- CROP NAME -->

                                    <td>
                                        <?= htmlspecialchars(
                                            $row['crop_name']
                                        ) ?>
                                    </td>


                                    <!-- PLOT -->

                                    <td>
                                        <?= htmlspecialchars(
                                            $row['plot_code']
                                        ) ?>
                                    </td>


                                    <!-- STAFF -->

                                    <td>
                                        <?= htmlspecialchars(
                                            $row['staff_name']
                                        ) ?>
                                    </td>


                                    <!-- VARIETY -->

                                    <td>
                                        <?= htmlspecialchars(
                                            $row['variety']
                                        ) ?>
                                    </td>


                                    <!-- PIECES -->

                                    <td>
                                        <?= htmlspecialchars(
                                            $row['pcs']
                                        ) ?>
                                    </td>


                                    <!-- GERMINATION -->

                                    <td>
                                        <?= htmlspecialchars(
                                            $row['germination_rate']
                                        ) ?>%
                                    </td>


                                    <!-- AREA -->

                                    <td>
                                        <?= htmlspecialchars(
                                            $row['transplanted_area']
                                        ) ?>
                                    </td>


                                    <!-- DATE TRANSPLANTED -->

                                    <td>
                                        <?= htmlspecialchars(
                                            $row['date_transplanted']
                                        ) ?>
                                    </td>


                                    <!-- EXPECTED HARVEST -->

                                    <td>
                                        <?= htmlspecialchars(
                                            $row['expected_harvest']
                                        ) ?>
                                    </td>


                                    <!-- ACTION -->

                                    <?php if ($role === 'admin'): ?>


                                        <td>

                                            <div class="action-buttons">


                                                <!-- EDIT -->

                                                <button
                                                    type="button"
                                                    class="action-icon edit-icon"
                                                    title="Edit Crop"
                                                    onclick="openEditModal(
                                                        'edit_crop.php?id=<?= intval($row['crop_id']) ?>',
                                                        'Edit Crop'
                                                    )"
                                                >

                                                    <span class="material-symbols-rounded">
                                                        edit
                                                    </span>

                                                </button>


                                                <!-- ARCHIVE -->

                                                <button
                                                    type="button"
                                                    class="action-icon archive-icon"
                                                    title="Archive Crop"
                                                    onclick="openArchiveModal(
                                                        'archive_crop.php?id=<?= intval($row['crop_id']) ?>',
                                                        '<?= htmlspecialchars(
                                                            $row['crop_name'],
                                                            ENT_QUOTES
                                                        ) ?>'
                                                    )"
                                                >

                                                    <span class="material-symbols-rounded">
                                                        archive
                                                    </span>

                                                </button>


                                            </div>

                                        </td>


                                    <?php endif; ?>


                                </tr>


                            <?php endwhile; ?>


                        <?php else: ?>


                            <!-- NO RECORDS -->

                            <tr>

                                <td
                                    colspan="<?= $role === 'admin' ? '10' : '9' ?>"
                                    class="no-records"
                                >

                                    No active crop records found.

                                </td>

                            </tr>


                        <?php endif; ?>


                    </tbody>


                </table>


            </div>


        </section>


    </main>


</div>


<!-- =========================================================
     EDIT MODAL
     ========================================================= -->

<div
    class="modal-overlay"
    id="editModal"
>


    <div class="edit-modal">


        <div class="modal-header">


            <h3 id="editModalTitle">
                Record
            </h3>


            <button
                type="button"
                class="close-modal"
                onclick="closeEditModal()"
            >

                <span class="material-symbols-rounded">
                    close
                </span>

            </button>


        </div>


        <iframe
            id="editFrame"
            title="Crop form"
        ></iframe>


    </div>


</div>


<!-- =========================================================
     ARCHIVE MODAL
     ========================================================= -->

<div
    class="modal-overlay"
    id="archiveModal"
>


    <div class="delete-modal">


        <div class="delete-icon-large">


            <span class="material-symbols-rounded">
                archive
            </span>


        </div>


        <h3>
            Archive Crop?
        </h3>


        <p id="archiveMessage">
            Are you sure you want to archive this crop?
        </p>


        <div class="delete-actions">


            <button
                type="button"
                class="cancel-btn"
                onclick="closeArchiveModal()"
            >

                Cancel

            </button>


            <button
                type="button"
                class="confirm-delete-btn"
                onclick="confirmArchive()"
            >

                Archive

            </button>


        </div>


    </div>


</div>


<script>


/* =========================================================
   EDIT MODAL
   ========================================================= */

function openEditModal(url, title) {

    const modal =
        document.getElementById('editModal');

    document.getElementById(
        'editModalTitle'
    ).textContent = title;

    document.getElementById(
        'editFrame'
    ).src = url;

    modal.classList.add('active');

    document.body.style.overflow = 'hidden';

}


function closeEditModal() {

    document
        .getElementById('editModal')
        .classList.remove('active');

    document
        .getElementById('editFrame')
        .src = '';

    document.body.style.overflow = '';

}


/* =========================================================
   ARCHIVE MODAL
   ========================================================= */

let archiveUrl = '';


function openArchiveModal(url, cropName) {

    archiveUrl = url;


    document.getElementById(
        'archiveMessage'
    ).textContent =
        'Are you sure you want to archive "' +
        cropName +
        '"?';


    document
        .getElementById('archiveModal')
        .classList.add('active');

    document.body.style.overflow = 'hidden';

}


function closeArchiveModal() {

    document
        .getElementById('archiveModal')
        .classList.remove('active');

    archiveUrl = '';

    document.body.style.overflow = '';

}


function confirmArchive() {

    if (archiveUrl) {

        window.location.href = archiveUrl;

    }

}


/* =========================================================
   SEARCH + FILTER
   ========================================================= */

function filterCropRecords() {


    const search =
        document
            .getElementById('cropSearch')
            .value
            .toLowerCase()
            .trim();


    const plot =
        document
            .getElementById('cropPlotFilter')
            .value
            .toLowerCase();


    const rows =
        document.querySelectorAll('.crop-row');


    rows.forEach(function(row) {


        const text =
            row.textContent
                .toLowerCase();


        const matchesSearch =
            text.includes(search);


        const plotCell =
            row.children[1]
                .textContent
                .trim()
                .toLowerCase();


        const matchesPlot =
            plot === 'all' ||
            plotCell === plot;


        if (
            matchesSearch &&
            matchesPlot
        ) {

            row.style.display = '';

        } else {

            row.style.display = 'none';

        }


    });


}


/* =========================================================
   CLOSE EDIT MODAL OUTSIDE
   ========================================================= */

document
    .getElementById('editModal')
    .addEventListener(
        'click',
        function(e) {

            if (e.target === this) {

                closeEditModal();

            }

        }
    );


/* =========================================================
   CLOSE ARCHIVE MODAL OUTSIDE
   ========================================================= */

document
    .getElementById('archiveModal')
    .addEventListener(
        'click',
        function(e) {

            if (e.target === this) {

                closeArchiveModal();

            }

        }
    );


/* =========================================================
   ESC KEY
   ========================================================= */

document.addEventListener(
    'keydown',
    function(e) {

        if (e.key === 'Escape') {

            closeEditModal();

            closeArchiveModal();

        }

    }
);


</script>


</body>

</html>