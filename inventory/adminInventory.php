<?php
session_start();

/*
|--------------------------------------------------------------------------
| ADMIN INVENTORY
|--------------------------------------------------------------------------
| Shared layout:
| - sidebar.php
| - topRightBar.php
|
| Change the include paths below if your files are located elsewhere.
|--------------------------------------------------------------------------
*/

$adminName = $_SESSION['name'] ?? 'Manalo, X.';
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Inventory | Sanctuario Nature Farm</title>

    <!-- Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <!-- Inventory CSS -->
    <link
        rel="stylesheet"
        href="inventory.css"
    >

</head>


<body>

<?php
include '../sidebar.php';
?>




<!-- ============================================================
     MAIN CONTENT
============================================================ -->

<main class="inventory-page">


    <!-- ========================================================
         TOP RIGHT BAR
    ========================================================= -->

    <?php include '../topRightBar.php'; ?>


    <!-- ========================================================
         SUMMARY AREA
    ========================================================= -->

    <section class="inventory-summary">

    <div class="inventory-header">

        <h1>
            INVENTORY
        </h1>

    </div>

        <div class="summary-message">
            here's what's happening on farm today...
        </div>


        <div class="summary-cards">


            <!-- TOTAL ITEMS -->

            <div class="summary-card">

                <div class="summary-icon summary-green">

                    <i class="fa-solid fa-box-open"></i>

                </div>

                <div class="summary-info">

                    <span>
                        Total Items
                    </span>

                    <strong>
                        15
                    </strong>

                </div>

            </div>


            <!-- PENDING REQUEST -->

            <div class="summary-card">

                <div class="summary-icon summary-orange">

                    <i class="fa-regular fa-clock"></i>

                </div>

                <div class="summary-info">

                    <span>
                        Pending Request
                    </span>

                    <strong>
                        5
                    </strong>

                </div>

            </div>


            <!-- LOW STOCK -->

            <div class="summary-card">

                <div class="summary-icon summary-red">

                    <i class="fa-solid fa-triangle-exclamation"></i>

                </div>

                <div class="summary-info">

                    <span>
                        Low Stocks Item
                    </span>

                    <strong>
                        5
                    </strong>

                </div>

            </div>


        </div>

    </section>



    <!-- ========================================================
         INVENTORY CONTENT
    ========================================================= -->

    <section class="inventory-layout">


        <!-- ====================================================
             LEFT SIDE
        ==================================================== -->

        <div class="inventory-main">


            <!-- TOOLBAR -->

            <div class="inventory-toolbar">


                <!-- CATEGORY -->

                <div class="inventory-category">

                    <button
                        type="button"
                        class="category-button"
                        id="categoryButton"
                    >

                        <span id="categoryText">
                            Seeds
                        </span>

                        <i class="fa-solid fa-caret-down"></i>

                    </button>


                    <div
                        class="category-dropdown"
                        id="categoryDropdown"
                    >

                        <button
                            type="button"
                            onclick="changeCategory('Seeds')"
                        >
                            Seeds
                        </button>

                        <button
                            type="button"
                            onclick="changeCategory('Fertilizer')"
                        >
                            Fertilizer
                        </button>

                        <button
                            type="button"
                            onclick="changeCategory('Tools')"
                        >
                            Tools
                        </button>

                        <button
                            type="button"
                            onclick="changeCategory('Feeds')"
                        >
                            Feeds
                        </button>

                        <button
                            type="button"
                            onclick="changeCategory('Others')"
                        >
                            Others
                        </button>

                    </div>

                </div>


                <!-- SEARCH -->

                <div class="inventory-search">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        id="inventorySearch"
                        placeholder="Search here..."
                    >

                </div>


                <div class="toolbar-space"></div>


                <!-- ADD ITEM -->

                <button
                    type="button"
                    class="add-item-button"
                    onclick="openInventoryModal()"
                >

                    <i class="fa-solid fa-plus"></i>

                    Add Item

                </button>


                <!-- ARCHIVED -->

                <button
                    type="button"
                    class="archive-items-button"
                    onclick="viewArchivedItems()"
                >

                    View Archived Items

                    <i class="fa-solid fa-chevron-right"></i>

                </button>


            </div>



            <!-- =================================================
                 INVENTORY TABLE
            ================================================= -->

            <div class="inventory-table-wrapper">

                <table
                    class="inventory-table"
                    id="inventoryTable"
                >

                    <thead>

                        <tr>

                            <th>
                                DATE RECEIVED
                            </th>

                            <th>
                                CROPS
                            </th>

                            <th>
                                VARIETY
                            </th>

                            <th>
                                BRAND
                            </th>

                            <th>
                                PIECES
                            </th>

                            <th>
                                NO. OF SEEDS
                            </th>

                            <th>
                                PRICE
                            </th>

                            <th>
                                STATUS
                            </th>

                            <th>
                                ACTION
                            </th>

                        </tr>

                    </thead>


                    <tbody id="inventoryTableBody">


                        <!-- ROW 1 -->

                        <tr>

                            <td>
                                01/01/2026
                            </td>

                            <td>
                                Corn
                            </td>

                            <td>
                                Purple Gem F1
                            </td>

                            <td>
                                Condor
                            </td>

                            <td>
                                2 Packs
                            </td>

                            <td>
                                10 Grams
                            </td>

                            <td>
                                240
                            </td>

                            <td>

                                <span class="stock-status in-stock">
                                    In Stock
                                </span>

                            </td>

                            <td>

                                <div class="table-actions">

                                    <button
                                        type="button"
                                        class="edit-button"
                                        onclick="editInventory(1)"
                                        title="Edit"
                                    >

                                        <i class="fa-solid fa-pen-to-square"></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="delete-button"
                                        onclick="archiveInventory(1)"
                                        title="Archive"
                                    >

                                        <i class="fa-solid fa-trash-can"></i>

                                    </button>

                                </div>

                            </td>

                        </tr>


                        <!-- ROW 2 -->

                        <tr>

                            <td>
                                01/01/2026
                            </td>

                            <td>
                                Corn
                            </td>

                            <td>
                                Purple Gem F1
                            </td>

                            <td>
                                Condor
                            </td>

                            <td>
                                2 Packs
                            </td>

                            <td>
                                10 Grams
                            </td>

                            <td>
                                240
                            </td>

                            <td>

                                <span class="stock-status no-stock">
                                    No Stock
                                </span>

                            </td>

                            <td>

                                <div class="table-actions">

                                    <button
                                        type="button"
                                        class="edit-button"
                                        onclick="editInventory(2)"
                                    >

                                        <i class="fa-solid fa-pen-to-square"></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="delete-button"
                                        onclick="archiveInventory(2)"
                                    >

                                        <i class="fa-solid fa-trash-can"></i>

                                    </button>

                                </div>

                            </td>

                        </tr>


                        <!-- ROW 3 -->

                        <tr>

                            <td>
                                01/01/2026
                            </td>

                            <td>
                                Corn
                            </td>

                            <td>
                                Purple Gem F1
                            </td>

                            <td>
                                Condor
                            </td>

                            <td>
                                2 Packs
                            </td>

                            <td>
                                10 Grams
                            </td>

                            <td>
                                240
                            </td>

                            <td>

                                <span class="stock-status low-stock">
                                    Low Stock
                                </span>

                            </td>

                            <td>

                                <div class="table-actions">

                                    <button
                                        type="button"
                                        class="edit-button"
                                        onclick="editInventory(3)"
                                    >

                                        <i class="fa-solid fa-pen-to-square"></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="delete-button"
                                        onclick="archiveInventory(3)"
                                    >

                                        <i class="fa-solid fa-trash-can"></i>

                                    </button>

                                </div>

                            </td>

                        </tr>


                        <!-- ROW 4 -->

                        <tr>

                            <td>
                                01/01/2026
                            </td>

                            <td>
                                Corn
                            </td>

                            <td>
                                Purple Gem F1
                            </td>

                            <td>
                                Condor
                            </td>

                            <td>
                                2 Packs
                            </td>

                            <td>
                                10 Grams
                            </td>

                            <td>
                                240
                            </td>

                            <td>

                                <span class="stock-status in-stock">
                                    In Stock
                                </span>

                            </td>

                            <td>

                                <div class="table-actions">

                                    <button
                                        type="button"
                                        class="edit-button"
                                        onclick="editInventory(4)"
                                    >

                                        <i class="fa-solid fa-pen-to-square"></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="delete-button"
                                        onclick="archiveInventory(4)"
                                    >

                                        <i class="fa-solid fa-trash-can"></i>

                                    </button>

                                </div>

                            </td>

                        </tr>


                        <!-- ROW 5 -->

                        <tr>

                            <td>
                                01/01/2026
                            </td>

                            <td>
                                Corn
                            </td>

                            <td>
                                Purple Gem F1
                            </td>

                            <td>
                                Condor
                            </td>

                            <td>
                                2 Packs
                            </td>

                            <td>
                                10 Grams
                            </td>

                            <td>
                                240
                            </td>

                            <td>

                                <span class="stock-status in-stock">
                                    In Stock
                                </span>

                            </td>

                            <td>

                                <div class="table-actions">

                                    <button
                                        type="button"
                                        class="edit-button"
                                        onclick="editInventory(5)"
                                    >

                                        <i class="fa-solid fa-pen-to-square"></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="delete-button"
                                        onclick="archiveInventory(5)"
                                    >

                                        <i class="fa-solid fa-trash-can"></i>

                                    </button>

                                </div>

                            </td>

                        </tr>


                        <!-- ROW 6 -->

                        <tr>

                            <td>
                                01/01/2026
                            </td>

                            <td>
                                Corn
                            </td>

                            <td>
                                Purple Gem F1
                            </td>

                            <td>
                                Condor
                            </td>

                            <td>
                                2 Packs
                            </td>

                            <td>
                                10 Grams
                            </td>

                            <td>
                                240
                            </td>

                            <td>

                                <span class="stock-status in-stock">
                                    In Stock
                                </span>

                            </td>

                            <td>

                                <div class="table-actions">

                                    <button
                                        type="button"
                                        class="edit-button"
                                        onclick="editInventory(6)"
                                    >

                                        <i class="fa-solid fa-pen-to-square"></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="delete-button"
                                        onclick="archiveInventory(6)"
                                    >

                                        <i class="fa-solid fa-trash-can"></i>

                                    </button>

                                </div>

                            </td>

                        </tr>


                        <!-- ROW 7 -->

                        <tr>

                            <td>
                                01/01/2026
                            </td>

                            <td>
                                Corn
                            </td>

                            <td>
                                Purple Gem F1
                            </td>

                            <td>
                                Condor
                            </td>

                            <td>
                                2 Packs
                            </td>

                            <td>
                                10 Grams
                            </td>

                            <td>
                                240
                            </td>

                            <td>

                                <span class="stock-status in-stock">
                                    In Stock
                                </span>

                            </td>

                            <td>

                                <div class="table-actions">

                                    <button
                                        type="button"
                                        class="edit-button"
                                        onclick="editInventory(7)"
                                    >

                                        <i class="fa-solid fa-pen-to-square"></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="delete-button"
                                        onclick="archiveInventory(7)"
                                    >

                                        <i class="fa-solid fa-trash-can"></i>

                                    </button>

                                </div>

                            </td>

                        </tr>


                        <!-- ROW 8 -->

                        <tr>

                            <td>
                                01/01/2026
                            </td>

                            <td>
                                Corn
                            </td>

                            <td>
                                Purple Gem F1
                            </td>

                            <td>
                                Condor
                            </td>

                            <td>
                                2 Packs
                            </td>

                            <td>
                                10 Grams
                            </td>

                            <td>
                                240
                            </td>

                            <td>

                                <span class="stock-status in-stock">
                                    In Stock
                                </span>

                            </td>

                            <td>

                                <div class="table-actions">

                                    <button
                                        type="button"
                                        class="edit-button"
                                        onclick="editInventory(8)"
                                    >

                                        <i class="fa-solid fa-pen-to-square"></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="delete-button"
                                        onclick="archiveInventory(8)"
                                    >

                                        <i class="fa-solid fa-trash-can"></i>

                                    </button>

                                </div>

                            </td>

                        </tr>


                    </tbody>

                </table>

            </div>

        </div>



        <!-- ====================================================
             RIGHT SIDE
        ==================================================== -->

        <div class="inventory-sidebar">


            <!-- =================================================
                 INVENTORY REQUESTS
            ================================================= -->

            <div class="inventory-panel">

                <div class="panel-title">
                    INVENTORY REQUESTS
                </div>


                <!-- REQUEST 1 -->

                <div class="inventory-request">

                    <div class="request-heading">

                        <span>
                            Fertilizer x10
                        </span>

                        <span class="request-status approved">
                            Approved
                        </span>

                    </div>


                    <div class="request-user">
                        Requested By: Alecxia Miradora
                    </div>


                    <div class="request-buttons">

                        <button
                            class="request-approve"
                            onclick="approveRequest(this)"
                        >
                            Approve
                        </button>

                        <button
                            class="request-reject"
                            onclick="rejectRequest(this)"
                        >
                            Reject
                        </button>

                        <button
                            class="request-view"
                            onclick="viewRequest()"
                        >
                            View
                        </button>

                    </div>

                </div>


                <!-- REQUEST 2 -->

                <div class="inventory-request">

                    <div class="request-heading">

                        <span>
                            Fertilizer x10
                        </span>

                        <span class="request-status rejected">
                            Rejected
                        </span>

                    </div>


                    <div class="request-user">
                        Requested By: Alecxia Miradora
                    </div>


                    <div class="request-buttons">

                        <button
                            class="request-approve"
                            onclick="approveRequest(this)"
                        >
                            Approve
                        </button>

                        <button
                            class="request-reject red"
                            onclick="rejectRequest(this)"
                        >
                            Reject
                        </button>

                        <button
                            class="request-view"
                            onclick="viewRequest()"
                        >
                            View
                        </button>

                    </div>

                </div>

            </div>


    </section>

</main>



<!-- ============================================================
     ADD ITEM MODAL
============================================================ -->

<div
    class="inventory-modal-overlay"
    id="inventoryModal"
>

    <div class="inventory-modal">


        <div class="modal-header">

            <h2>
                Add Inventory Item
            </h2>

            <button
                type="button"
                class="modal-close"
                onclick="closeInventoryModal()"
            >

                <i class="fa-solid fa-xmark"></i>

            </button>

        </div>


        <form
            id="inventoryForm"
            onsubmit="saveInventoryItem(event)"
        >


            <div class="modal-form-grid">


                <div class="form-group">

                    <label>
                        Category
                    </label>

                    <select required>

                        <option value="">
                            Select Category
                        </option>

                        <option>
                            Seeds
                        </option>

                        <option>
                            Fertilizer
                        </option>

                        <option>
                            Tools
                        </option>

                        <option>
                            Feeds
                        </option>

                        <option>
                            Others
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Item Name
                    </label>

                    <input
                        type="text"
                        placeholder="e.g. Tomato Seeds"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Variety
                    </label>

                    <input
                        type="text"
                        placeholder="e.g. Purple Gem F1"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Brand
                    </label>

                    <input
                        type="text"
                        placeholder="e.g. Condor"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Pieces
                    </label>

                    <input
                        type="text"
                        placeholder="e.g. 2 Packs"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Quantity
                    </label>

                    <input
                        type="text"
                        placeholder="e.g. 10 Grams"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Price
                    </label>

                    <input
                        type="number"
                        placeholder="0.00"
                        min="0"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select>

                        <option>
                            In Stock
                        </option>

                        <option>
                            Low Stock
                        </option>

                        <option>
                            No Stock
                        </option>

                    </select>

                </div>


            </div>


            <div class="modal-actions">

                <button
                    type="button"
                    class="modal-cancel"
                    onclick="closeInventoryModal()"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="modal-save"
                >
                    Save Item
                </button>

            </div>

        </form>

    </div>

</div>



<!-- ============================================================
     JAVASCRIPT
============================================================ -->

<script>


/*
|--------------------------------------------------------------------------
| CATEGORY DROPDOWN
|--------------------------------------------------------------------------
*/

const categoryButton =
    document.getElementById('categoryButton');

const categoryDropdown =
    document.getElementById('categoryDropdown');


categoryButton.addEventListener('click', function(event) {

    event.stopPropagation();

    categoryDropdown.classList.toggle('show');

});


document.addEventListener('click', function() {

    categoryDropdown.classList.remove('show');

});


function changeCategory(category) {

    document.getElementById('categoryText').textContent =
        category;

    categoryDropdown.classList.remove('show');

}



/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

document
    .getElementById('inventorySearch')
    .addEventListener('keyup', function() {

        const search =
            this.value.toLowerCase().trim();

        const rows =
            document.querySelectorAll(
                '#inventoryTableBody tr'
            );


        rows.forEach(function(row) {

            const rowText =
                row.textContent.toLowerCase();

            if (rowText.includes(search)) {

                row.style.display = '';

            } else {

                row.style.display = 'none';

            }

        });

    });



/*
|--------------------------------------------------------------------------
| MODAL
|--------------------------------------------------------------------------
*/

function openInventoryModal() {

    document
        .getElementById('inventoryModal')
        .classList.add('show');

}


function closeInventoryModal() {

    document
        .getElementById('inventoryModal')
        .classList.remove('show');

}


document
    .getElementById('inventoryModal')
    .addEventListener('click', function(event) {

        if (event.target === this) {

            closeInventoryModal();

        }

    });



/*
|--------------------------------------------------------------------------
| SAVE ITEM
|--------------------------------------------------------------------------
|
| UI ONLY FOR NOW.
| Later this will be replaced with PHP/MySQL INSERT.
|--------------------------------------------------------------------------
*/

function saveInventoryItem(event) {

    event.preventDefault();

    alert(
        'Inventory item saved successfully!'
    );

    document
        .getElementById('inventoryForm')
        .reset();

    closeInventoryModal();

}



/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
*/

function editInventory(id) {

    alert(
        'Edit inventory item #' + id
    );

}



/*
|--------------------------------------------------------------------------
| ARCHIVE
|--------------------------------------------------------------------------
*/

function archiveInventory(id) {

    const confirmArchive =
        confirm(
            'Are you sure you want to archive this item?'
        );


    if (!confirmArchive) {
        return;
    }


    alert(
        'Inventory item #' + id + ' archived.'
    );

}



/*
|--------------------------------------------------------------------------
| ARCHIVED ITEMS
|--------------------------------------------------------------------------
*/

function viewArchivedItems() {

    /*
     * Change this to your actual archive page.
     */

    window.location.href =
        'archivedInventory.php';

}



/*
|--------------------------------------------------------------------------
| REQUEST ACTIONS
|--------------------------------------------------------------------------
*/

function approveRequest(button) {

    const request =
        button.closest('.inventory-request');

    const status =
        request.querySelector('.request-status');


    status.textContent =
        'Approved';

    status.className =
        'request-status approved';


    alert(
        'Request approved.'
    );

}


function rejectRequest(button) {

    const request =
        button.closest('.inventory-request');

    const status =
        request.querySelector('.request-status');


    status.textContent =
        'Rejected';

    status.className =
        'request-status rejected';


    alert(
        'Request rejected.'
    );

}


function viewRequest() {

    alert(
        'Inventory request details.'
    );

}

</script>


</body>

</html>