<?php
session_start();

include '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'staff';


// =========================================================
// GET SELECTED TYPE
// =========================================================

$selectedType = $_GET['type'] ?? '';


// =========================================================
// GET SELECTED LIVESTOCK
// =========================================================

$selectedLivestockId = isset($_GET['livestock_id'])
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

$nameSql = "
    SELECT livestock_id, name, type
    FROM livestocks
    WHERE archived = 0
";

if ($selectedType !== '') {

    $safeType = $conn->real_escape_string($selectedType);

    $nameSql .= "
        AND LOWER(type) = LOWER('$safeType')
    ";
}

$nameSql .= "
    ORDER BY name ASC
";

$nameResult = $conn->query($nameSql);


// =========================================================
// AUTOMATICALLY SELECT FIRST LIVESTOCK
// =========================================================

if ($selectedLivestockId === 0 && $nameResult && $nameResult->num_rows > 0) {

    $firstLivestock = $nameResult->fetch_assoc();

    $selectedLivestockId = intval(
        $firstLivestock['livestock_id']
    );

    // Reset pointer
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

    $profileResult = $stmt->get_result();

    if ($profileResult && $profileResult->num_rows > 0) {

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

<title>Livestock Records</title>


<!-- MAIN CSS -->

<link
    rel="stylesheet"
    href="../style.css"
>

<link
    rel="stylesheet"
    href="../sidebar.css"
>

<link
    rel="stylesheet"
    href="livestocks.css"
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


    <!-- MAIN -->

    <main class="dashboard">


        <!-- TOP RIGHT BAR -->

        <?php include '../topRightBar.php'; ?>


        <!-- =================================================
             PAGE HEADER
             ================================================= -->

        <div class="livestock-page-header">

            <h1>
                LIVESTOCK RECORDS
            </h1>

        </div>


        <!-- =================================================
             TOP CONTROLS
             ================================================= -->

        <div class="livestock-top-controls">


            <!-- TYPE DROPDOWN -->

            <select
                class="livestock-type-select"
                onchange="changeLivestockType(this.value)"
            >

                <option value="">
                    All Livestock
                </option>

                <?php if ($typeResult): ?>

                    <?php while ($typeRow = $typeResult->fetch_assoc()): ?>

                        <option
                            value="<?= htmlspecialchars($typeRow['type']) ?>"
                            <?= strtolower($selectedType) === strtolower($typeRow['type']) ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars($typeRow['type']) ?>

                        </option>

                    <?php endwhile; ?>

                <?php endif; ?>

            </select>


            <!-- LIVESTOCK NAME -->

            <select
                class="livestock-name-select"
                onchange="changeLivestock(this.value)"
            >

                <?php if ($nameResult && $nameResult->num_rows > 0): ?>

                    <?php while ($nameRow = $nameResult->fetch_assoc()): ?>

                        <option
                            value="<?= intval($nameRow['livestock_id']) ?>"
                            <?= intval($nameRow['livestock_id']) === $selectedLivestockId ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars($nameRow['name']) ?>

                        </option>

                    <?php endwhile; ?>

                <?php else: ?>

                    <option value="">
                        No livestock found
                    </option>

                <?php endif; ?>

            </select>


            <!-- RIGHT SIDE -->

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
             ================================================= -->

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
                                'edit_livestock.php?id=<?= intval($livestockProfile['livestock_id']) ?>',
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


                    <!-- DATE OF BIRTH -->

                    <div class="profile-item">

                        <span class="profile-label">
                            Date of Birth
                        </span>

                        <strong>

                            <?php

                            if (!empty($livestockProfile['dob'])) {

                                echo date(
                                    'F d, Y',
                                    strtotime($livestockProfile['dob'])
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
                                $livestockProfile['name'] ?? '-'
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
                                $livestockProfile['breed'] ?? '-'
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
                                $livestockProfile['age'] ?? '-'
                            ) ?>

                        </strong>

                    </div>


                    <!-- ESTIMATED WEIGHT -->

                    <div class="profile-item">

                        <span class="profile-label">
                            Estimated Weight
                        </span>

                        <strong>

                            <?php

                            if (
                                isset(
                                    $livestockProfile['estimated_weight']
                                )
                                &&
                                $livestockProfile['estimated_weight'] !== ''
                            ) {

                                echo htmlspecialchars(
                                    $livestockProfile['estimated_weight']
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
                                isset($livestockProfile['for_sale'])
                                &&
                                (
                                    $livestockProfile['for_sale'] === '1'
                                    ||
                                    strtolower(
                                        $livestockProfile['for_sale']
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
             ================================================= -->

        <div class="livestock-records-section">


            <!-- RECORD HEADER -->

            <div class="livestock-record-header">


                <?php if ($role === 'admin'): ?>

                    <button
                        type="button"
                        class="add-record-btn"
                        onclick="openEditModal(
                            'add_livestock_record.php?livestock_id=<?= intval($selectedLivestockId) ?>',
                            'Add Livestock Record'
                        )"
                    >

                        <span class="material-symbols-rounded">
                            add
                        </span>

                        Add Record

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
                 LIVESTOCK TABLE
                 ================================================= -->

            <div class="livestock-table-container">

                <table id="livestockTable">

                    <thead>

                        <tr>

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


                            <?php while (
                                $row =
                                $livestockResult->fetch_assoc()
                            ): ?>


                                <tr class="livestock-row">


                                    <td>
                                        <?= htmlspecialchars(
                                            $row['type'] ?? '-'
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= htmlspecialchars(
                                            $row['name'] ?? '-'
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= htmlspecialchars(
                                            $row['age'] ?? '-'
                                        ) ?>
                                    </td>


                                    <td>

                                        <?php

                                        if (
                                            isset(
                                                $row['estimated_weight']
                                            )
                                            &&
                                            $row['estimated_weight'] !== ''
                                        ) {

                                            echo htmlspecialchars(
                                                $row['estimated_weight']
                                            );

                                            echo ' kg';

                                        } else {

                                            echo '-';

                                        }

                                        ?>

                                    </td>


                                    <td>
                                        <?= htmlspecialchars(
                                            $row['sex'] ?? '-'
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= htmlspecialchars(
                                            $row['feeding_schedule'] ?? '-'
                                        ) ?>
                                    </td>


                                    <?php if ($role === 'admin'): ?>

                                        <td>

                                            <div class="action-buttons">


                                                <!-- EDIT -->

                                                <button
                                                    type="button"
                                                    class="action-icon edit-icon"
                                                    title="Edit"
                                                    onclick="openEditModal(
                                                        'edit_livestock.php?id=<?= intval($row['livestock_id']) ?>',
                                                        'Edit Livestock'
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
                                                    title="Archive"
                                                    onclick="openArchiveModal(
                                                        'archive_livestock.php?id=<?= intval($row['livestock_id']) ?>',
                                                        '<?= htmlspecialchars(
                                                            $row['name'] ?? 'this livestock',
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


                            <tr>

                                <td
                                    colspan="<?= $role === 'admin' ? '7' : '6' ?>"
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
                onclick="confirmArchive()"
            >
                Archive
            </button>

        </div>

    </div>

</div>


<script>

let archiveUrl = '';


// =========================================================
// CHANGE LIVESTOCK TYPE
// =========================================================

function changeLivestockType(type) {

    if (type === '') {

        window.location.href =
            'livestocks.php';

    } else {

        window.location.href =
            'livestocks.php?type=' +
            encodeURIComponent(type);

    }

}


// =========================================================
// CHANGE LIVESTOCK
// =========================================================

function changeLivestock(id) {

    if (!id) {
        return;
    }

    const type =
        new URLSearchParams(
            window.location.search
        ).get('type');

    let url =
        'livestocks.php?livestock_id=' +
        encodeURIComponent(id);

    if (type) {

        url +=
            '&type=' +
            encodeURIComponent(type);

    }

    window.location.href = url;

}


// =========================================================
// SEARCH LIVESTOCK
// =========================================================

function searchLivestock() {

    const search =
        document
            .getElementById('livestockSearch')
            .value
            .toLowerCase();

    const rows =
        document.querySelectorAll(
            '.livestock-row'
        );

    rows.forEach(function(row) {

        const text =
            row.textContent.toLowerCase();

        row.style.display =
            text.includes(search)
                ? ''
                : 'none';

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

    const search =
        document
            .getElementById('recordSearch')
            .value
            .toLowerCase();

    const rows =
        document.querySelectorAll(
            '.livestock-row'
        );

    rows.forEach(function(row) {

        const text =
            row.textContent.toLowerCase();

        const matchesSearch =
            text.includes(search);

        row.style.display =
            matchesSearch
                ? ''
                : 'none';

    });

}


// =========================================================
// EDIT MODAL
// =========================================================

function openEditModal(url, title) {

    document
        .getElementById('editModalTitle')
        .textContent = title;

    document
        .getElementById('editFrame')
        .src = url;

    document
        .getElementById('editModal')
        .classList.add('active');

    document.body.style.overflow =
        'hidden';

}


function closeEditModal() {

    document
        .getElementById('editModal')
        .classList.remove('active');

    document
        .getElementById('editFrame')
        .src = '';

    document.body.style.overflow =
        '';

}


// =========================================================
// ARCHIVE MODAL
// =========================================================

function openArchiveModal(url, name) {

    archiveUrl = url;

    document
        .getElementById('archiveMessage')
        .textContent =
        'Are you sure you want to archive "' +
        name +
        '"?';

    document
        .getElementById('archiveModal')
        .classList.add('active');

    document.body.style.overflow =
        'hidden';

}


function closeArchiveModal() {

    document
        .getElementById('archiveModal')
        .classList.remove('active');

    archiveUrl = '';

    document.body.style.overflow =
        '';

}


function confirmArchive() {

    if (archiveUrl) {

        window.location.href =
            archiveUrl;

    }

}


// =========================================================
// CLICK OUTSIDE MODALS
// =========================================================

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


// =========================================================
// ESC
// =========================================================

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