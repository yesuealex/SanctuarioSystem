
<?php

session_start();

include '../db.php';


// =========================================================
// LOGIN CHECK
// =========================================================

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login.php");

    exit();

}

$role = $_SESSION['role'] ?? 'staff';


// =========================================================
// ARCHIVE LIVESTOCK
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['archive_livestock_id'])
) {

    // Only admin can archive livestock
    if ($role !== 'admin') {

        http_response_code(403);

        echo 'Unauthorized';

        exit();

    }


    $archiveId =
        intval($_POST['archive_livestock_id']);


    if ($archiveId > 0) {

        $archiveStmt = $conn->prepare("
            UPDATE livestocks
            SET archived = 1
            WHERE livestock_id = ?
            AND archived = 0
        ");


        if ($archiveStmt) {

            $archiveStmt->bind_param(
                "i",
                $archiveId
            );

            $archiveStmt->execute();

            $archiveStmt->close();

        }

    }


    // Return to active livestock records
    header("Location: livestocks.php");

    exit();

}


// =========================================================
// GET SELECTED TYPE
// =========================================================

$selectedType = $_GET['type'] ?? '';


// =========================================================
// GET SELECTED LIVESTOCK
// =========================================================

$selectedLivestockId =
    isset($_GET['livestock_id'])
        ? intval($_GET['livestock_id'])
        : 0;


// =========================================================
// GET LIVESTOCK TYPES
// =========================================================

$typeResult = $conn->query("
    SELECT DISTINCT type
    FROM livestocks
    WHERE archived = 0
    ORDER BY type ASC
");


// =========================================================
// GET LIVESTOCK NAMES
// =========================================================

if ($selectedType !== '') {

    $stmtType = $conn->prepare("
        SELECT livestock_id, name, type
        FROM livestocks
        WHERE archived = 0
        AND LOWER(type) = LOWER(?)
        ORDER BY name ASC
    ");


    $stmtType->bind_param(
        "s",
        $selectedType
    );


    $stmtType->execute();


    $nameResult =
        $stmtType->get_result();

} else {

    $nameSql = "
        SELECT livestock_id, name, type
        FROM livestocks
        WHERE archived = 0
        ORDER BY name ASC
    ";


    $nameResult =
        $conn->query($nameSql);

}


// =========================================================
// AUTOMATICALLY SELECT FIRST LIVESTOCK
// =========================================================

if (
    $selectedLivestockId === 0
    &&
    $nameResult
    &&
    $nameResult->num_rows > 0
) {

    $firstLivestock =
        $nameResult->fetch_assoc();


    $selectedLivestockId =
        intval(
            $firstLivestock['livestock_id']
        );


    $nameResult->data_seek(0);

}


// =========================================================
// GET SELECTED LIVESTOCK PROFILE
// =========================================================

$livestockProfile = null;


if ($selectedLivestockId > 0) {

    $stmt = $conn->prepare("
        SELECT *
        FROM livestocks
        WHERE livestock_id = ?
        AND archived = 0
        LIMIT 1
    ");


    $stmt->bind_param(
        "i",
        $selectedLivestockId
    );


    $stmt->execute();


    $profileResult =
        $stmt->get_result();


    if (
        $profileResult
        &&
        $profileResult->num_rows > 0
    ) {

        $livestockProfile =
            $profileResult->fetch_assoc();

    }


    $stmt->close();

}


// =========================================================
// GET ALL ACTIVE LIVESTOCK
// =========================================================

$livestockResult = $conn->query("
    SELECT *
    FROM livestocks
    WHERE archived = 0
    ORDER BY livestock_id DESC
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
    Livestock Records
</title>


<!-- =====================================================
     MAIN CSS
====================================================== -->

<link
    rel="stylesheet"
    href="../style.css"
>


<!-- =====================================================
     SIDEBAR CSS
====================================================== -->

<link
    rel="stylesheet"
    href="../sidebar.css"
>


<!-- =====================================================
     LIVESTOCK CSS
====================================================== -->

<link
    rel="stylesheet"
    href="livestocks.css"
>


<!-- =====================================================
     GOOGLE FONT
====================================================== -->

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


<!-- =====================================================
     MATERIAL ICONS
====================================================== -->

<link
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:wght@400"
    rel="stylesheet"
>

</head>


<body>


<div class="layout">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <?php include '../sidebar.php'; ?>


    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="dashboard">


        <!-- =================================================
             TOP RIGHT BAR
        ================================================== -->

        <?php include '../topRightBar.php'; ?>


        <!-- =================================================
             PAGE HEADER
        ================================================== -->

        <div class="livestock-page-header">

            <h1>
                LIVESTOCK RECORDS
            </h1>

        </div>


        <!-- =================================================
             TOP CONTROLS
        ================================================== -->

        <div class="livestock-top-controls">


            <!-- =================================================
                 TYPE
            ================================================== -->

            <select
                class="livestock-type-select"
                onchange="changeLivestockType(this.value)"
            >

                <option value="">
                    All Livestock
                </option>


                <?php if ($typeResult): ?>

                    <?php while (
                        $typeRow =
                        $typeResult->fetch_assoc()
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $typeRow['type'],
                                ENT_QUOTES
                            ) ?>"
                            <?= strtolower($selectedType) ===
                                strtolower($typeRow['type'])
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $typeRow['type']
                            ) ?>

                        </option>

                    <?php endwhile; ?>

                <?php endif; ?>

            </select>


            <!-- =================================================
                 LIVESTOCK NAME
            ================================================== -->

            <select
                class="livestock-name-select"
                onchange="changeLivestock(this.value)"
            >

                <?php if (
                    $nameResult
                    &&
                    $nameResult->num_rows > 0
                ): ?>

                    <?php while (
                        $nameRow =
                        $nameResult->fetch_assoc()
                    ): ?>

                        <option
                            value="<?= intval(
                                $nameRow['livestock_id']
                            ) ?>"
                            <?= intval(
                                $nameRow['livestock_id']
                            ) === $selectedLivestockId
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $nameRow['name']
                            ) ?>

                        </option>

                    <?php endwhile; ?>

                <?php else: ?>

                    <option value="">
                        No livestock found
                    </option>

                <?php endif; ?>

            </select>


            <!-- =================================================
                 RIGHT SIDE
            ================================================== -->

            <div class="livestock-header-right">


                <!-- SEARCH -->

                <div class="livestock-search">

                    <span class="material-symbols-rounded">
                        search
                    </span>

                    <input
                        type="text"
                        id="livestockSearch"
                        placeholder="Search here..."
                        onkeyup="searchLivestock()"
                    >

                </div>


                <!-- ARCHIVED -->

                <?php if ($role === 'admin'): ?>

                    <button
                        type="button"
                        class="archived-livestock-btn"
                        onclick="location.href='archived_livestocks.php'"
                    >

                        View Archived Livestock

                        <span class="material-symbols-rounded">
                            chevron_right
                        </span>

                    </button>

                <?php endif; ?>


            </div>

        </div>


        <!-- =================================================
             PROFILE
        ================================================== -->

        <?php if ($livestockProfile): ?>

            <div class="livestock-profile-card">


                <!-- PROFILE HEADER -->

                <div class="profile-header">

                    <h2>
                        PROFILE
                    </h2>


                    <?php if ($role === 'admin'): ?>

                        <button
                            type="button"
                            class="edit-profile-btn"
                            onclick="openEditModal(
                                'edit_livestock.php?id=<?= intval(
                                    $livestockProfile['livestock_id']
                                ) ?>',
                                'Edit Livestock Profile'
                            )"
                        >

                            <span class="material-symbols-rounded">
                                edit
                            </span>

                            Edit Profile

                        </button>

                    <?php endif; ?>

                </div>


                <!-- PROFILE INFORMATION -->

                <div class="profile-grid">


                    <!-- DOB -->

                    <div class="profile-item">

                        <span class="profile-label">
                            Date of Birth
                        </span>

                        <strong>

                            <?php

                            if (
                                !empty(
                                    $livestockProfile['date_of_birth']
                                )
                            ) {

                                echo date(
                                    'F d, Y',
                                    strtotime(
                                        $livestockProfile['date_of_birth']
                                    )
                                );

                            } else {

                                echo '-';

                            }

                            ?>

                        </strong>

                    </div>


                    <!-- NAME -->

                    <div class="profile-item">

                        <span class="profile-label">
                            Name
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $livestockProfile['name']
                                ?? '-'
                            ) ?>

                        </strong>

                    </div>


                    <!-- BREED -->

                    <div class="profile-item">

                        <span class="profile-label">
                            Breed
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $livestockProfile['breed']
                                ?? '-'
                            ) ?>

                        </strong>

                    </div>


                    <!-- AGE -->

                    <div class="profile-item">

                        <span class="profile-label">
                            Age
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $livestockProfile['age']
                                ?? '-'
                            ) ?>

                        </strong>

                    </div>


                    <!-- WEIGHT -->

                    <div class="profile-item">

                        <span class="profile-label">
                            Estimated Weight
                        </span>

                        <strong>

                            <?php

                            if (
                                isset(
                                    $livestockProfile[
                                        'estimated_weight'
                                    ]
                                )
                                &&
                                $livestockProfile[
                                    'estimated_weight'
                                ] !== ''
                            ) {

                                echo htmlspecialchars(
                                    $livestockProfile[
                                        'estimated_weight'
                                    ]
                                );

                                echo ' kg';

                            } else {

                                echo '-';

                            }

                            ?>

                        </strong>

                    </div>


                    <!-- FOR SALE -->

                    <div class="profile-item">

                        <span class="profile-label">
                            For sale
                        </span>

                        <strong>

                            <?php

                            if (
                                isset(
                                    $livestockProfile[
                                        'for_sale'
                                    ]
                                )
                                &&
                                (
                                    $livestockProfile[
                                        'for_sale'
                                    ] === '1'
                                    ||
                                    strtolower(
                                        $livestockProfile[
                                            'for_sale'
                                        ]
                                    ) === 'yes'
                                )
                            ) {

                                echo 'Yes';

                            } else {

                                echo '-';

                            }

                            ?>

                        </strong>

                    </div>


                </div>

            </div>


        <?php else: ?>


            <div class="empty-profile">

                <span class="material-symbols-rounded">
                    pets
                </span>

                <p>
                    No livestock profile available.
                </p>

            </div>


        <?php endif; ?>


        <!-- =================================================
             RECORDS SECTION
        ================================================== -->

        <div class="livestock-records-section">


            <!-- RECORD HEADER -->

            <div class="livestock-record-header">


                <?php if ($role === 'admin'): ?>

                    <button
                        type="button"
                        class="add-record-btn"
                        onclick="openEditModal(
                            'add_livestock.php?livestock_id=<?= intval(
                                $selectedLivestockId
                            ) ?>',
                            'Add Livestock Record'
                        )"
                    >

                        <span class="material-symbols-rounded">
                            add
                        </span>

                        Add Livestock

                    </button>

                <?php endif; ?>


                <div class="record-controls">


                    <!-- SEARCH -->

                    <div class="record-search">

                        <span class="material-symbols-rounded">
                            search
                        </span>

                        <input
                            type="text"
                            id="recordSearch"
                            placeholder="Search here..."
                            onkeyup="searchRecords()"
                        >

                    </div>


                    <!-- MONTH -->

                    <select
                        class="month-select"
                        id="monthFilter"
                        onchange="filterRecords()"
                    >

                        <option value="all">
                            All Months
                        </option>

                        <option value="01">
                            January
                        </option>

                        <option value="02">
                            February
                        </option>

                        <option value="03">
                            March
                        </option>

                        <option value="04">
                            April
                        </option>

                        <option value="05">
                            May
                        </option>

                        <option value="06">
                            June
                        </option>

                        <option value="07">
                            July
                        </option>

                        <option value="08">
                            August
                        </option>

                        <option value="09">
                            September
                        </option>

                        <option value="10">
                            October
                        </option>

                        <option value="11">
                            November
                        </option>

                        <option value="12">
                            December
                        </option>

                    </select>


                </div>

            </div>


            <!-- =================================================
                 TABLE
            ================================================== -->

            <div class="livestock-table-container">

                <table id="livestockTable">

                    <thead>

                        <tr>

                            <th class="row-number-col">
                                #
                            </th>

                            <th>
                                TYPE
                            </th>

                            <th>
                                NAME
                            </th>

                            <th>
                                AGE
                            </th>

                            <th>
                                ESTIMATED WEIGHT
                            </th>

                            <th>
                                SEX
                            </th>

                            <th>
                                FEEDING SCHEDULE
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
                        $livestockResult
                        &&
                        $livestockResult->num_rows > 0
                    ): ?>


                        <?php $livestockRowNumber = 1; ?>


                        <?php while (
                            $row =
                            $livestockResult->fetch_assoc()
                        ): ?>


                            <tr class="livestock-row">


                                <!-- ROW NUMBER -->

                                <td class="row-number">
                                    <?= $livestockRowNumber++ ?>
                                </td>


                                <!-- TYPE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['type'] ?? '-'
                                    ) ?>

                                </td>


                                <!-- NAME -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['name'] ?? '-'
                                    ) ?>

                                </td>


                                <!-- AGE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['age'] ?? '-'
                                    ) ?>

                                </td>


                                <!-- WEIGHT -->

                                <td>

                                    <?php

                                    if (
                                        isset(
                                            $row[
                                                'estimated_weight'
                                            ]
                                        )
                                        &&
                                        $row[
                                            'estimated_weight'
                                        ] !== ''
                                    ) {

                                        echo htmlspecialchars(
                                            $row[
                                                'estimated_weight'
                                            ]
                                        );

                                        echo ' kg';

                                    } else {

                                        echo '-';

                                    }

                                    ?>

                                </td>


                                <!-- SEX -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['sex'] ?? '-'
                                    ) ?>

                                </td>


                                <!-- FEEDING -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row[
                                            'feeding_schedule'
                                        ] ?? '-'
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
                                                title="Edit"
                                                data-edit-url="edit_livestock.php?id=<?= intval(
                                                    $row['livestock_id']
                                                ) ?>"
                                                data-edit-title="Edit Livestock"
                                            >

                                                <span class="material-symbols-rounded">
                                                    edit
                                                </span>

                                            </button>


                                            <!-- ARCHIVE -->

                                            <button
                                                type="button"
                                                class="action-icon archive-icon livestock-archive-btn"
                                                title="Archive"
                                                data-livestock-id="<?= intval(
                                                    $row['livestock_id']
                                                ) ?>"
                                                data-livestock-name="<?= htmlspecialchars(
                                                    $row['name']
                                                        ?? 'this livestock',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
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


                        <tr>

                            <td
                                colspan="<?= $role === 'admin'
                                    ? '8'
                                    : '7' ?>"
                                class="no-records"
                            >

                                No livestock records found.

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>


        </div>


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
                Edit Livestock
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
            title="Livestock Form"
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

    <div class="archive-modal">


        <div class="archive-icon-large">

            <span class="material-symbols-rounded">
                archive
            </span>

        </div>


        <h3>
            Archive Livestock?
        </h3>


        <p id="archiveMessage">
            Are you sure you want to archive this livestock?
        </p>


        <div class="archive-actions">


            <button
                type="button"
                class="cancel-btn"
                onclick="closeArchiveModal()"
            >

                Cancel

            </button>


            <button
                type="button"
                class="confirm-archive-btn"
                id="confirmArchiveBtn"
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


// =========================================================
// ARCHIVE STATE
// =========================================================

let archiveLivestockId = 0;


// =========================================================
// CHANGE LIVESTOCK TYPE
// =========================================================

function changeLivestockType(type) {

    if (type === '') {

        window.location.href =
            'livestocks.php';

        return;

    }


    window.location.href =
        'livestocks.php?type=' +
        encodeURIComponent(type);

}


// =========================================================
// CHANGE LIVESTOCK
// =========================================================

function changeLivestock(id) {

    if (!id) {

        return;

    }


    const params =
        new URLSearchParams(
            window.location.search
        );


    const type =
        params.get('type');


    let url =
        'livestocks.php?livestock_id=' +
        encodeURIComponent(id);


    if (type) {

        url +=
            '&type=' +
            encodeURIComponent(type);

    }


    window.location.href =
        url;

}


// =========================================================
// SEARCH LIVESTOCK
// =========================================================

function searchLivestock() {

    const input =
        document.getElementById(
            'livestockSearch'
        );


    if (!input) {

        return;

    }


    const search =
        input.value
            .toLowerCase()
            .trim();


    const rows =
        document.querySelectorAll(
            '.livestock-row'
        );


    rows.forEach(function(row) {

        const text =
            row.textContent
                .toLowerCase();


        if (
            text.includes(search)
        ) {

            row.style.display = '';

        } else {

            row.style.display = 'none';

        }

    });

}


// =========================================================
// SEARCH RECORDS
// =========================================================

function searchRecords() {

    filterRecords();

}


// =========================================================
// FILTER RECORDS
// =========================================================

function filterRecords() {

    const searchInput =
        document.getElementById(
            'recordSearch'
        );


    const monthFilter =
        document.getElementById(
            'monthFilter'
        );


    if (!searchInput) {

        return;

    }


    const search =
        searchInput.value
            .toLowerCase()
            .trim();


    const month =
        monthFilter
            ? monthFilter.value
            : 'all';


    const rows =
        document.querySelectorAll(
            '.livestock-row'
        );


    rows.forEach(function(row) {

        const text =
            row.textContent
                .toLowerCase();


        const matchesSearch =
            text.includes(search);


        /*
         * Month filtering can be connected
         * to a date column when the livestock
         * record date field is finalized.
         */

        const matchesMonth =
            month === 'all'
                ? true
                : true;


        row.style.display =
            matchesSearch &&
            matchesMonth
                ? ''
                : 'none';

    });

}


// =========================================================
// EDIT MODAL
// =========================================================

function openEditModal(
    url,
    title
) {

    const modal =
        document.getElementById(
            'editModal'
        );


    const frame =
        document.getElementById(
            'editFrame'
        );


    const modalTitle =
        document.getElementById(
            'editModalTitle'
        );


    if (
        !modal ||
        !frame ||
        !modalTitle
    ) {

        console.error(
            'Edit modal elements not found.'
        );

        return;

    }


    modalTitle.textContent =
        title;


    frame.src =
        url;


    modal.classList.add(
        'active'
    );


    document.body.style.overflow =
        'hidden';

}


// =========================================================
// CLOSE EDIT MODAL
// =========================================================

function closeEditModal() {

    const modal =
        document.getElementById(
            'editModal'
        );


    const frame =
        document.getElementById(
            'editFrame'
        );


    if (modal) {

        modal.classList.remove(
            'active'
        );

    }


    if (frame) {

        frame.src = '';

    }


    document.body.style.overflow =
        '';

}


// =========================================================
// OPEN ARCHIVE MODAL
// =========================================================

function openArchiveModal(
    livestockId,
    livestockName
) {

    archiveLivestockId =
        parseInt(
            livestockId,
            10
        );


    const modal =
        document.getElementById(
            'archiveModal'
        );


    const message =
        document.getElementById(
            'archiveMessage'
        );


    if (!modal) {

        console.error(
            'Archive modal not found.'
        );

        return;

    }


    if (message) {

        message.textContent =
            'Are you sure you want to archive "' +
            livestockName +
            '"?';

    }


    modal.classList.add(
        'active'
    );


    document.body.style.overflow =
        'hidden';

}


// =========================================================
// CLOSE ARCHIVE MODAL
// =========================================================

function closeArchiveModal() {

    const modal =
        document.getElementById(
            'archiveModal'
        );


    if (modal) {

        modal.classList.remove(
            'active'
        );

    }


    archiveLivestockId = 0;


    document.body.style.overflow =
        '';

}


// =========================================================
// CONFIRM ARCHIVE
// =========================================================

function confirmArchive() {

    if (!archiveLivestockId) {

        console.error(
            'No livestock selected for archiving.'
        );

        return;

    }


    /*
     * Create a POST form dynamically.
     *
     * This sends the livestock ID to
     * livestocks.php, where PHP performs:
     *
     * UPDATE livestocks
     * SET archived = 1
     */

    const form =
        document.createElement(
            'form'
        );


    form.method =
        'POST';


    form.action =
        'livestocks.php';


    const input =
        document.createElement(
            'input'
        );


    input.type =
        'hidden';


    input.name =
        'archive_livestock_id';


    input.value =
        archiveLivestockId;


    form.appendChild(
        input
    );


    document.body.appendChild(
        form
    );


    form.submit();

}


// =========================================================
// EDIT BUTTON EVENT LISTENERS
// =========================================================

document
    .querySelectorAll(
        '.edit-icon'
    )
    .forEach(function(button) {

        button.addEventListener(
            'click',
            function() {

                const url =
                    this.dataset.editUrl;


                const title =
                    this.dataset.editTitle
                    || 'Edit Livestock';


                if (url) {

                    openEditModal(
                        url,
                        title
                    );

                }

            }
        );

    });


// =========================================================
// ARCHIVE BUTTON EVENT LISTENERS
// =========================================================

document
    .querySelectorAll(
        '.livestock-archive-btn'
    )
    .forEach(function(button) {

        button.addEventListener(
            'click',
            function() {

                const livestockId =
                    this.dataset.livestockId;


                const name =
                    this.dataset.livestockName
                    || 'this livestock';


                if (!livestockId) {

                    console.error(
                        'Livestock ID is missing.'
                    );

                    return;

                }


                openArchiveModal(
                    livestockId,
                    name
                );

            }
        );

    });


// =========================================================
// CONFIRM ARCHIVE BUTTON
// =========================================================

const confirmArchiveButton =
    document.getElementById(
        'confirmArchiveBtn'
    );


if (confirmArchiveButton) {

    confirmArchiveButton.addEventListener(
        'click',
        function() {

            confirmArchive();

        }
    );

}


// =========================================================
// CLICK OUTSIDE EDIT MODAL
// =========================================================

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


// =========================================================
// CLICK OUTSIDE ARCHIVE MODAL
// =========================================================

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


// =========================================================
// ESC KEY
// =========================================================

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