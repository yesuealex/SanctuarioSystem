<?php

session_start();

include '../db.php';


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login.php");

    exit();

}


/*
|--------------------------------------------------------------------------
| USER ROLE
|--------------------------------------------------------------------------
*/

$role = $_SESSION['role'] ?? 'staff';


/*
|--------------------------------------------------------------------------
| GET ACTIVE CROP RECORDS
|--------------------------------------------------------------------------
*/

$cropsResult = $conn->query("
    SELECT 
        crops.*,
        plots.plot_name,
        plots.plot_code
    FROM crops
    LEFT JOIN plots
        ON crops.plot_id = plots.plot_id
    WHERE crops.archived = 0
    ORDER BY crops.crop_id DESC
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

<title>
    Crops Records
</title>


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


        <!-- PAGE TITLE -->

        <h2 class="page-title">
            CROPS RECORDS
        </h2>


        <!-- =====================================================
             CROP RECORDS
             ===================================================== -->

        <section class="crop-records">


            <!-- =================================================
                 TOP CONTROLS
                 ================================================= -->

            <div class="crop-topbar">


                <!-- LEFT -->

                <div class="crop-filters">


                    <!-- LOCATION BUTTONS -->

                    <div class="crop-location-buttons" id="cropLocationButtons">

                        <button type="button" class="crop-location-btn active" data-filter="all">
                            All
                        </button>

                        <button type="button" class="crop-location-btn" data-filter="nursery">
                            Nursery
                        </button>

                        <button type="button" class="crop-location-btn" data-filter="plot a">
                            Plot A
                        </button>

                        <button type="button" class="crop-location-btn" data-filter="plot b">
                            Plot B
                        </button>

                        <button type="button" class="crop-location-btn" data-filter="plot c">
                            Plot C
                        </button>

                    </div>


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

                    <div class="crop-extra-filters">

                        <input
                            type="date"
                            id="cropDateFilter"
                            class="crop-date-filter"
                            title="Filter by date"
                            onchange="filterCropRecords()"
                        >

                        <input
                            type="text"
                            id="cropStaffFilter"
                            class="crop-staff-filter"
                            placeholder="Staff / recipient"
                            onkeyup="filterCropRecords()"
                        >

                    </div>


                </div>


                <!-- RIGHT -->

                <div class="crop-actions">


                    <?php if ($role === 'admin'): ?>


                        <!-- ADD CROP -->

                        <button
                            type="button"
                            class="add-crop-btn"
                            onclick="openEditModal('add_crop.php', 'Add Crop')"
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


            <!-- =================================================
                 TABLE
                 ================================================= -->

            <div class="crop-table-wrapper">


                <table id="cropTable">


                    <thead>

                        <tr>

                            <th class="row-number-col">#</th>

                            <th>
                                RECIPIENT
                            </th>

                            <th>
                                PLOT
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

                            <th>
                                EXPECTED<br>HARVEST
                            </th>

                            <th>
                                STATUS
                            </th>

                            <th>
                                TRANSPLANTED<br>AREA
                            </th>

                            <?php if ($role === 'admin'): ?>

                                <th>
                                    ACTION
                                </th>

                            <?php endif; ?>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        $cropsResult &&
                        $cropsResult->num_rows > 0
                    ): ?>


                        <?php $cropRowNumber = 1; ?>
                        <?php while (
                            $row = $cropsResult->fetch_assoc()
                        ): ?>


                            <tr
                                class="crop-row"
                                data-sowing="<?= htmlspecialchars($row['date_sowing'] ?? '', ENT_QUOTES) ?>"
                                data-transplanted="<?= htmlspecialchars($row['date_transplanted'] ?? '', ENT_QUOTES) ?>"
                                data-harvest="<?= htmlspecialchars($row['expected_harvest'] ?? '', ENT_QUOTES) ?>"
                                data-recipient="<?= htmlspecialchars($row['recipient'] ?? '', ENT_QUOTES) ?>"
                            >

                                <td class="row-number"><?= $cropRowNumber++ ?></td>

                                <!-- RECIPIENT -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['recipient'] ?? ''
                                    ) ?>

                                </td>


                                <!-- PLOT -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['plot_name'] ?? ''
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


                                <!-- PLANTS -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['pcs'] ?? ''
                                    ) ?>

                                </td>


                                <!-- DATE SOWING -->

                                <td>

                                    <?= !empty(
                                        $row['date_sowing']
                                    )
                                        ? date(
                                            'm/d/y',
                                            strtotime(
                                                $row['date_sowing']
                                            )
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


                                <!-- TARGET TRANSPLANT -->

                                <td>

                                    <?= !empty(
                                        $row['target_transplant_date']
                                    )
                                        ? date(
                                            'm/d/y',
                                            strtotime(
                                                $row['target_transplant_date']
                                            )
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

                                    <?= !empty(
                                        $row['date_transplanted']
                                    )
                                        ? date(
                                            'm/d/y',
                                            strtotime(
                                                $row['date_transplanted']
                                            )
                                        )
                                        : ''
                                    ?>

                                </td>


                                <!-- EXPECTED HARVEST -->

                                <td>

                                    <?= !empty(
                                        $row['expected_harvest']
                                    )
                                        ? date(
                                            'm/d/y',
                                            strtotime(
                                                $row['expected_harvest']
                                            )
                                        )
                                        : ''
                                    ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['status'] ?? ''
                                    ) ?>

                                </td>


                                <!-- TRANSPLANTED AREA -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['transplanted_area'] ?? ''
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
                                colspan="<?= $role === 'admin' ? '16' : '15' ?>"
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
     EDIT / ADD MODAL
     ========================================================= -->

<div
    class="modal-overlay"
    id="editModal"
>


    <div class="edit-modal">


        <!-- HEADER -->

        <div class="modal-header">


            <h3 id="editModalTitle">
                Record
            </h3>


            <button
                type="button"
                class="close-modal"
                onclick="closeEditModal()"
                title="Close"
            >

                <span class="material-symbols-rounded">
                    close
                </span>

            </button>


        </div>


        <!-- IFRAME -->

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


<!-- =========================================================
     JAVASCRIPT
     ========================================================= -->

<script>


/* =========================================================
   EDIT / ADD MODAL
   ========================================================= */

function openEditModal(url, title) {

    const modal =
        document.getElementById('editModal');

    const frame =
        document.getElementById('editFrame');

    const modalTitle =
        document.getElementById('editModalTitle');


    if (
        !modal ||
        !frame ||
        !modalTitle
    ) {

        console.error(
            'Edit modal elements were not found.'
        );

        return;

    }


    modalTitle.textContent = title;

    frame.src = url;

    modal.classList.add('active');

    document.body.style.overflow = 'hidden';

}


/* =========================================================
   CLOSE EDIT / ADD MODAL
   ========================================================= */

function closeEditModal() {

    const modal =
        document.getElementById('editModal');

    const frame =
        document.getElementById('editFrame');


    if (modal) {

        modal.classList.remove('active');

    }


    if (frame) {

        frame.src = '';

    }


    document.body.style.overflow = '';

}


/* =========================================================
   ARCHIVE MODAL
   ========================================================= */

let archiveUrl = '';


function openArchiveModal(
    url,
    cropName
) {

    archiveUrl = url;


    const message =
        document.getElementById(
            'archiveMessage'
        );


    const modal =
        document.getElementById(
            'archiveModal'
        );


    if (message) {

        message.textContent =
            'Are you sure you want to archive "' +
            cropName +
            '"?';

    }


    if (modal) {

        modal.classList.add('active');

    }


    document.body.style.overflow = 'hidden';

}


/* =========================================================
   CLOSE ARCHIVE MODAL
   ========================================================= */

function closeArchiveModal() {

    const modal =
        document.getElementById(
            'archiveModal'
        );


    if (modal) {

        modal.classList.remove('active');

    }


    archiveUrl = '';

    document.body.style.overflow = '';

}


/* =========================================================
   CONFIRM ARCHIVE
   ========================================================= */

function confirmArchive() {

    if (archiveUrl) {

        window.location.href =
            archiveUrl;

    }

}


/* =========================================================
   SEARCH + FILTER
   ========================================================= */

function filterCropRecords() {

    const searchInput = document.getElementById('cropSearch');
    const dateFilter = document.getElementById('cropDateFilter');
    const staffFilter = document.getElementById('cropStaffFilter');
    const activeButton = document.querySelector('.crop-location-btn.active');

    const search = (searchInput?.value || '').toLowerCase().trim();
    const date = dateFilter?.value || '';
    const staff = (staffFilter?.value || '').toLowerCase().trim();
    const location = activeButton?.dataset.filter || 'all';

    document.querySelectorAll('.crop-row').forEach(function(row) {

        const text = row.textContent.toLowerCase();
        const recipient = (row.dataset.recipient || '').toLowerCase();

        const matchesSearch = !search || text.includes(search);
        const matchesStaff = !staff || recipient.includes(staff);

        const matchesLocation =
            location === 'all' ||
            text.includes(location);

        const rowDates = [
            row.dataset.sowing || '',
            row.dataset.transplanted || '',
            row.dataset.harvest || ''
        ];

        const matchesDate =
            !date || rowDates.includes(date);

        row.style.display =
            matchesSearch &&
            matchesStaff &&
            matchesLocation &&
            matchesDate
                ? ''
                : 'none';
    });
}


/* =========================================================
   LOCATION BUTTONS
   ========================================================= */

document.querySelectorAll('.crop-location-btn').forEach(function(button) {

    button.addEventListener('click', function() {

        document.querySelectorAll('.crop-location-btn').forEach(function(item) {
            item.classList.remove('active');
        });

        this.classList.add('active');
        filterCropRecords();
    });
});


/* =========================================================
   CLOSE EDIT MODAL WHEN CLICKING OUTSIDE
   ========================================================= */

const editModal =
    document.getElementById(
        'editModal'
    );


if (editModal) {

    editModal.addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                closeEditModal();

            }

        }
    );

}


/* =========================================================
   CLOSE ARCHIVE MODAL WHEN CLICKING OUTSIDE
   ========================================================= */

const archiveModal =
    document.getElementById(
        'archiveModal'
    );


if (archiveModal) {

    archiveModal.addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                closeArchiveModal();

            }

        }
    );

}


/* =========================================================
   ESC KEY
   ========================================================= */

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeEditModal();

            closeArchiveModal();

        }

    }
);


</script>


</body>

</html>