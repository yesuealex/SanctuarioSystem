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

    <!-- SIDEBAR -->
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


    <!-- SIDEBAR -->

    <?php include '../sidebar.php'; ?>


    <!-- MAIN CONTENT -->

    <main class="dashboard">


        <!-- TOP RIGHT BAR -->

        <?php include '../topRightBar.php'; ?>


        <!-- PAGE TITLE -->

        <h2 class="page-title">
            CROPS RECORDS
        </h2>


        <!-- CROPS RECORDS -->

        <section class="crop-records">


            <!-- =====================================================
                 TOP CONTROLS
                 ===================================================== -->

            <div class="crop-topbar">


                <!-- LEFT SIDE -->

                <div class="crop-filters">


                    <!-- CATEGORY -->

                    <select
                        id="cropPlotFilter"
                        class="crop-select"
                        onchange="filterCropRecords()"
                    >

                        <option value="all">
                            All
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

                    <div class="crop-search">

                        <span class="material-symbols-rounded">
                            search
                        </span>

                        <input
                            type="text"
                            id="cropSearch"
                            placeholder="Search here..."
                            onkeyup="filterCropRecords()"
                        >

                    </div>


                </div>


                <!-- RIGHT SIDE -->

                <div class="crop-actions">


                    <?php if ($role === 'admin'): ?>

                        <!-- ADD CROP -->

                        <button
                            type="button"
                            class="add-crop-btn"
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


                        <!-- ARCHIVED -->

                        <button
                            type="button"
                            class="archived-crops-btn"
                            onclick="location.href='archived_crops.php'"
                        >

                            View Archived Crops

                            <span class="material-symbols-rounded">
                                chevron_right
                            </span>

                        </button>

                    <?php endif; ?>


                </div>

            </div>


            <!-- =====================================================
                 TABLE
                 ===================================================== -->

            <div class="crop-table-wrapper">


                <table id="cropTable">


                    <thead>

                        <tr>

                            <th>
                                RECIPIENT
                            </th>

                            <th>
                                CROP
                            </th>

                            <th>
                                VARIETY
                            </th>

                            <th>
                                NO. OF<br>PLANTS
                            </th>

                            <th>
                                DATE OF<br>SOWING
                            </th>

                            <th>
                                GERMINATION<br>RATE
                            </th>

                            <th>
                                TARGET<br>TRANSPLANT DATE
                            </th>

                            <th>
                                TARGET<br>AREA
                            </th>

                            <th>
                                SEEDLINGS<br>RECEIVED
                            </th>

                            <th>
                                DATE<br>TRANSPLANTED
                            </th>

                            <?php if ($role === 'admin'): ?>

                                <th>
                                    ACTION
                                </th>

                            <?php endif; ?>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if ($cropsResult && $cropsResult->num_rows > 0): ?>


                        <?php while ($row = $cropsResult->fetch_assoc()): ?>


                            <tr class="crop-row">


                                <!-- RECIPIENT -->

                                <td>
                                    <?= htmlspecialchars(
                                        $row['recipient'] ?? ''
                                    ) ?>
                                </td>


                                <!-- CROP -->

                                <td>
                                    <?= htmlspecialchars(
                                        $row['crop_name'] ?? ''
                                    ) ?>
                                </td>


                                <!-- VARIETY -->

                                <td>
                                    <?= htmlspecialchars(
                                        $row['variety'] ?? ''
                                    ) ?>
                                </td>


                                <!-- NO. OF PLANTS -->

                                <td>
                                    <?= htmlspecialchars(
                                        $row['pcs'] ?? ''
                                    ) ?>
                                </td>


                                <!-- DATE OF SOWING -->

                                <td>
                                    <?= !empty($row['date_sowing'])
                                        ? date(
                                            'm/d/y',
                                            strtotime($row['date_sowing'])
                                        )
                                        : ''
                                    ?>
                                </td>


                                <!-- GERMINATION -->

                                <td>
                                    <?= htmlspecialchars(
                                        $row['germination_rate'] ?? ''
                                    ) ?>%
                                </td>


                                <!-- TARGET TRANSPLANT DATE -->

                                <td>
                                    <?= !empty($row['target_transplant_date'])
                                        ? date(
                                            'm/d/y',
                                            strtotime($row['target_transplant_date'])
                                        )
                                        : ''
                                    ?>
                                </td>


                                <!-- TARGET AREA -->

                                <td>
                                    <?= htmlspecialchars(
                                        $row['target_area'] ?? ''
                                    ) ?>
                                </td>


                                <!-- SEEDLINGS RECEIVED -->

                                <td>
                                    <?= htmlspecialchars(
                                        $row['seedlings_received'] ?? ''
                                    ) ?>PCS
                                </td>


                                <!-- DATE TRANSPLANTED -->

                                <td>
                                    <?= !empty($row['date_transplanted'])
                                        ? date(
                                            'm/d/y',
                                            strtotime($row['date_transplanted'])
                                        )
                                        : ''
                                    ?>
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
                                                        $row['crop_name'] ?? '',
                                                        ENT_QUOTES
                                                    ) ?>'
                                                )"
                                            >

                                                <span class="material-symbols-rounded">
                                                    delete
                                                </span>

                                            </button>


                                        </div>

                                    </td>

                                <?php endif; ?>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="<?= $role === 'admin' ? '11' : '10' ?>"
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

    document.getElementById(
        'editModalTitle'
    ).textContent = title;

    document.getElementById(
        'editFrame'
    ).src = url;

    document
        .getElementById('editModal')
        .classList.add('active');

    document.body.style.overflow = 'hidden';

}


function closeEditModal() {

    document
        .getElementById('editModal')
        .classList.remove('active');

    document.getElementById(
        'editFrame'
    ).src = '';

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

        /*
        * Plot/location is not currently visible
        * in the screenshot, so use the recipient/crop
        * row text for search.
        *
        * The filter can be adjusted later if needed.
        */

        let matchesPlot = true;

        if (plot !== 'all') {

            matchesPlot =
                text.includes(plot);

        }


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
   CLOSE MODALS
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