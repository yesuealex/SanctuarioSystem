<?php
require_once __DIR__ . "/../staff_access.php";
if (in_array(sanctuario_staff_role(), ["crops staff","livestock staff"], true)) { http_response_code(403); exit("Access denied."); }
?>
<?php
session_start();

/* ============================================================
   ADMIN INVENTORY
   Uses the existing sidebar.php, topRightBar.php and inventory.css.
   Database: existing ../db.php
============================================================ */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../adminDashboard.php");
    exit();
}

require_once '../db.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage($message = '', $error = '') {
    $params = [];
    if ($message !== '') $params['message'] = $message;
    if ($error !== '') $params['error'] = $error;

    $url = 'adminInventory.php';
    if ($params) $url .= '?' . http_build_query($params);

    header("Location: " . $url);
    exit();
}

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

/* ============================================================
   DATABASE TABLE CHECK
============================================================ */
$tableReady = true;

try {
    $check = $conn->query("SELECT 1 FROM inventory_items LIMIT 1");
    if (!$check) {
        $tableReady = false;
        $error = $error ?: 'Inventory table is not available. Run inventory_tables.sql first.';
    }

    $check2 = $conn->query("SELECT 1 FROM inventory_requests LIMIT 1");
    if (!$check2) {
        $tableReady = false;
        $error = $error ?: 'Inventory request table is not available. Run inventory_tables.sql first.';
    }
} catch (Throwable $ex) {
    $tableReady = false;
    $error = $error ?: 'Could not connect to the inventory tables. Run inventory_tables.sql first.';
}

/* ============================================================
   STOCK TRANSACTION TABLE
   Stores every IN / OUT movement without replacing the
   existing inventory_items structure.
============================================================ */
$transactionTableReady = false;

if ($tableReady) {
    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS inventory_transactions (
                transaction_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                inventory_id INT UNSIGNED NOT NULL,
                transaction_type ENUM('IN','OUT') NOT NULL,
                quantity DECIMAL(12,2) NOT NULL,
                note VARCHAR(255) DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (transaction_id),
                KEY idx_inventory_transaction_item (inventory_id),
                KEY idx_inventory_transaction_created (created_at),
                CONSTRAINT fk_inventory_transaction_item
                    FOREIGN KEY (inventory_id)
                    REFERENCES inventory_items(inventory_id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $transactionTableReady = true;
    } catch (Throwable $ex) {
        $transactionTableReady = false;
        $error = $error ?: 'Could not prepare the inventory IN/OUT table: ' . $ex->getMessage();
    }
}

/* ============================================================
   POST ACTIONS
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableReady) {

    $action = $_POST['action'] ?? '';

    try {

        /* ----------------------------------------------------
           STOCK IN / OUT
        ---------------------------------------------------- */
        if ($action === 'stock_transaction') {

            if (!$transactionTableReady) {
                throw new Exception('Inventory transaction table is not available.');
            }

            $inventoryId = (int)($_POST['inventory_id'] ?? 0);
            $transactionType = strtoupper(trim($_POST['transaction_type'] ?? ''));
            $transactionQty = (float)($_POST['transaction_quantity'] ?? 0);
            $note = trim($_POST['transaction_note'] ?? '');

            if ($inventoryId <= 0) {
                throw new Exception('Invalid inventory item.');
            }

            if (!in_array($transactionType, ['IN', 'OUT'], true)) {
                throw new Exception('Invalid transaction type.');
            }

            if ($transactionQty <= 0) {
                throw new Exception('Transaction quantity must be greater than zero.');
            }

            $conn->begin_transaction();

            $stmt = $conn->prepare("
                SELECT quantity
                FROM inventory_items
                WHERE inventory_id = ?
                  AND is_archived = 0
                LIMIT 1
                FOR UPDATE
            ");

            if (!$stmt) {
                throw new Exception('Could not prepare inventory lookup.');
            }

            $stmt->bind_param('i', $inventoryId);
            $stmt->execute();
            $currentItem = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$currentItem) {
                throw new Exception('Inventory item was not found.');
            }

            /*
             * Existing quantity is VARCHAR because the project allows
             * values such as "10 Grams". The first numeric value is used
             * for stock movement while the original field remains usable.
             */
            $rawQuantity = (string)($currentItem['quantity'] ?? '');
            $currentQuantity = 0.0;

            if (preg_match('/-?\d+(?:\.\d+)?/', $rawQuantity, $matches)) {
                $currentQuantity = (float)$matches[0];
            }

            if ($transactionType === 'OUT' && $transactionQty > $currentQuantity) {
                throw new Exception(
                    'Cannot stock OUT more than the current recorded quantity (' .
                    rtrim(rtrim(number_format($currentQuantity, 2, '.', ''), '0'), '.') .
                    ').'
                );
            }

            $newQuantity = $transactionType === 'IN'
                ? $currentQuantity + $transactionQty
                : $currentQuantity - $transactionQty;

            $newStatus =
                $newQuantity <= 0
                    ? 'No Stock'
                    : ($newQuantity <= 5 ? 'Low Stock' : 'In Stock');

            $quantityText = rtrim(
                rtrim(number_format($newQuantity, 2, '.', ''), '0'),
                '.'
            );

            $stmt = $conn->prepare("
                UPDATE inventory_items
                SET quantity = ?, status = ?
                WHERE inventory_id = ?
                LIMIT 1
            ");

            if (!$stmt) {
                throw new Exception('Could not update inventory quantity.');
            }

            $stmt->bind_param(
                'ssi',
                $quantityText,
                $newStatus,
                $inventoryId
            );
            $stmt->execute();
            $stmt->close();

            $adminId = (int)$_SESSION['user_id'];

            $stmt = $conn->prepare("
                INSERT INTO inventory_transactions
                (
                    inventory_id,
                    transaction_type,
                    quantity,
                    note,
                    created_by
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception('Could not save the inventory transaction.');
            }

            $stmt->bind_param(
                'isdsi',
                $inventoryId,
                $transactionType,
                $transactionQty,
                $note,
                $adminId
            );
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            redirectWithMessage(
                'Stock ' . $transactionType . ' completed successfully.'
            );
        }


        /* ----------------------------------------------------
           ADD ITEM
        ---------------------------------------------------- */
        if ($action === 'add_item') {

            $category = trim($_POST['category'] ?? '');
            $itemName = trim($_POST['item_name'] ?? '');
            $variety = trim($_POST['variety'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $pieces = trim($_POST['pieces'] ?? '');
            $quantity = trim($_POST['quantity'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $status = trim($_POST['status'] ?? 'In Stock');

            $allowedCategories = [
                'Seeds',
                'Fertilizer',
                'Tools',
                'Feeds',
                'Others'
            ];

            $allowedStatuses = [
                'In Stock',
                'Low Stock',
                'No Stock'
            ];

            if (!in_array($category, $allowedCategories, true)) {
                throw new Exception('Please select a valid category.');
            }

            if ($itemName === '') {
                throw new Exception('Item name is required.');
            }

            if (!in_array($status, $allowedStatuses, true)) {
                throw new Exception('Please select a valid stock status.');
            }

            if ($price < 0) {
                throw new Exception('Price cannot be negative.');
            }

            $stmt = $conn->prepare("
                INSERT INTO inventory_items
                (
                    date_received,
                    category,
                    item_name,
                    variety,
                    brand,
                    pieces,
                    quantity,
                    price,
                    status,
                    created_by
                )
                VALUES
                (
                    CURDATE(),
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            if (!$stmt) {
                throw new Exception('Could not prepare item insertion: ' . $conn->error);
            }

            $adminId = (int)$_SESSION['user_id'];

            $stmt->bind_param(
                "ssssssdsi",
                $category,
                $itemName,
                $variety,
                $brand,
                $pieces,
                $quantity,
                $price,
                $status,
                $adminId
            );

            $stmt->execute();
            $stmt->close();

            redirectWithMessage('Inventory item added successfully.');
        }


        /* ----------------------------------------------------
           UPDATE ITEM
        ---------------------------------------------------- */
        if ($action === 'update_item') {

            $inventoryId = (int)($_POST['inventory_id'] ?? 0);
            $category = trim($_POST['category'] ?? '');
            $itemName = trim($_POST['item_name'] ?? '');
            $variety = trim($_POST['variety'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $pieces = trim($_POST['pieces'] ?? '');
            $quantity = trim($_POST['quantity'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $status = trim($_POST['status'] ?? 'In Stock');

            $allowedCategories = [
                'Seeds',
                'Fertilizer',
                'Tools',
                'Feeds',
                'Others'
            ];

            $allowedStatuses = [
                'In Stock',
                'Low Stock',
                'No Stock'
            ];

            if ($inventoryId <= 0) {
                throw new Exception('Invalid inventory item.');
            }

            if (!in_array($category, $allowedCategories, true)) {
                throw new Exception('Please select a valid category.');
            }

            if ($itemName === '') {
                throw new Exception('Item name is required.');
            }

            if (!in_array($status, $allowedStatuses, true)) {
                throw new Exception('Please select a valid stock status.');
            }

            if ($price < 0) {
                throw new Exception('Price cannot be negative.');
            }

            $stmt = $conn->prepare("
                UPDATE inventory_items
                SET
                    category = ?,
                    item_name = ?,
                    variety = ?,
                    brand = ?,
                    pieces = ?,
                    quantity = ?,
                    price = ?,
                    status = ?
                WHERE inventory_id = ?
                AND is_archived = 0
            ");

            if (!$stmt) {
                throw new Exception('Could not prepare item update: ' . $conn->error);
            }

            $stmt->bind_param(
                "ssssssdsi",
                $category,
                $itemName,
                $variety,
                $brand,
                $pieces,
                $quantity,
                $price,
                $status,
                $inventoryId
            );

            $stmt->execute();
            $stmt->close();

            redirectWithMessage('Inventory item updated successfully.');
        }


        /* ----------------------------------------------------
           ARCHIVE ITEM
        ---------------------------------------------------- */
        if ($action === 'archive_item') {

            $inventoryId = (int)($_POST['inventory_id'] ?? 0);

            if ($inventoryId <= 0) {
                throw new Exception('Invalid inventory item.');
            }

            $stmt = $conn->prepare("
                UPDATE inventory_items
                SET is_archived = 1
                WHERE inventory_id = ?
            ");

            if (!$stmt) {
                throw new Exception('Could not prepare archive action: ' . $conn->error);
            }

            $stmt->bind_param("i", $inventoryId);
            $stmt->execute();
            $stmt->close();

            redirectWithMessage('Inventory item archived successfully.');
        }


        /* ----------------------------------------------------
           APPROVE REQUEST
        ---------------------------------------------------- */
        if ($action === 'approve_request') {

            $requestId = (int)($_POST['request_id'] ?? 0);

            if ($requestId <= 0) {
                throw new Exception('Invalid inventory request.');
            }

            $stmt = $conn->prepare("
                UPDATE inventory_requests
                SET
                    status = 'Approved',
                    reviewed_at = NOW(),
                    reviewed_by = ?
                WHERE request_id = ?
                AND status = 'Pending'
            ");

            if (!$stmt) {
                throw new Exception('Could not prepare request approval: ' . $conn->error);
            }

            $adminId = (int)$_SESSION['user_id'];

            $stmt->bind_param(
                "ii",
                $adminId,
                $requestId
            );

            $stmt->execute();
            $stmt->close();

            redirectWithMessage('Inventory request approved.');
        }


        /* ----------------------------------------------------
           REJECT REQUEST
        ---------------------------------------------------- */
        if ($action === 'reject_request') {

            $requestId = (int)($_POST['request_id'] ?? 0);

            if ($requestId <= 0) {
                throw new Exception('Invalid inventory request.');
            }

            $stmt = $conn->prepare("
                UPDATE inventory_requests
                SET
                    status = 'Rejected',
                    reviewed_at = NOW(),
                    reviewed_by = ?
                WHERE request_id = ?
                AND status = 'Pending'
            ");

            if (!$stmt) {
                throw new Exception('Could not prepare request rejection: ' . $conn->error);
            }

            $adminId = (int)$_SESSION['user_id'];

            $stmt->bind_param(
                "ii",
                $adminId,
                $requestId
            );

            $stmt->execute();
            $stmt->close();

            redirectWithMessage('Inventory request rejected.');
        }
    } catch (Throwable $ex) {
        redirectWithMessage('', $ex->getMessage());
    }
}


/* ============================================================
   LOAD INVENTORY ITEMS
============================================================ */
$items = [];

if ($tableReady) {

    try {

        $result = $conn->query("
            SELECT
                inventory_id,
                date_received,
                category,
                item_name,
                variety,
                brand,
                pieces,
                quantity,
                price,
                status
            FROM inventory_items
            WHERE is_archived = 0
            ORDER BY inventory_id DESC
        ");

        if (!$result) {
            throw new Exception('Could not load inventory items: ' . $conn->error);
        }

        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

    } catch (Throwable $ex) {
        $error = $error ?: $ex->getMessage();
    }
}


/* ============================================================
   LOAD RECENT STOCK TRANSACTIONS
============================================================ */
$recentTransactions = [];

if ($transactionTableReady) {
    try {
        $result = $conn->query("
            SELECT
                it.transaction_id,
                it.transaction_type,
                it.quantity,
                it.note,
                it.created_at,
                ii.item_name,
                u.name AS created_by_name
            FROM inventory_transactions it
            INNER JOIN inventory_items ii
                ON ii.inventory_id = it.inventory_id
            LEFT JOIN users u
                ON u.user_id = it.created_by
            ORDER BY it.transaction_id DESC
            LIMIT 20
        ");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recentTransactions[] = $row;
            }
        }
    } catch (Throwable $ex) {
        $error = $error ?: $ex->getMessage();
    }
}


/* ============================================================
   LOAD REQUESTS
============================================================ */
$requests = [];

if ($tableReady) {

    try {

        $result = $conn->query("
            SELECT
                ir.request_id,
                ir.item_name,
                ir.quantity,
                ir.status,
                ir.request_note,
                ir.created_at,
                u.name AS requester_name,
                u.username AS requester_username
            FROM inventory_requests ir
            LEFT JOIN users u
                ON u.user_id = ir.requested_by
            ORDER BY
                CASE
                    WHEN ir.status = 'Pending' THEN 0
                    ELSE 1
                END,
                ir.request_id DESC
            LIMIT 10
        ");

        if (!$result) {
            throw new Exception('Could not load inventory requests: ' . $conn->error);
        }

        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }

    } catch (Throwable $ex) {
        $error = $error ?: $ex->getMessage();
    }
}


/* ============================================================
   SUMMARY COUNTS
============================================================ */
$totalItems = count($items);
$pendingRequests = 0;
$lowStockItems = 0;

foreach ($items as $item) {

    if (($item['status'] ?? '') === 'Low Stock') {
        $lowStockItems++;
    }
}

foreach ($requests as $request) {

    if (($request['status'] ?? '') === 'Pending') {
        $pendingRequests++;
    }
}


/* ============================================================
   EDIT ITEM
============================================================ */
$editItem = null;

if (isset($_GET['edit'])) {

    $editId = (int)$_GET['edit'];

    if ($editId > 0 && $tableReady) {

        try {

            $stmt = $conn->prepare("
                SELECT *
                FROM inventory_items
                WHERE inventory_id = ?
                AND is_archived = 0
                LIMIT 1
            ");

            $stmt->bind_param("i", $editId);
            $stmt->execute();

            $result = $stmt->get_result();
            $editItem = $result->fetch_assoc();

            $stmt->close();

        } catch (Throwable $ex) {
            $error = $error ?: $ex->getMessage();
        }
    }
}

$modalEditMode = ($editItem !== null);

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
        href="inventory.css?v=20261016-table-grid"
    >

    <style>

        /* Only functional-state additions.
           Existing inventory.css design is preserved. */

        .inventory-alert {
            width: 100%;
            margin-bottom: 14px;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            box-sizing: border-box;
        }

        .inventory-alert.success {
            background: #e1f4e7;
            color: #2f7b4b;
        }

        .inventory-alert.error {
            background: #f9dddd;
            color: #b34343;
        }

        .request-status.pending {
            background: #fff0b9;
            color: #b28b18;
        }

        .request-status.approved {
            background: #cff4df;
            color: #2d9660;
        }

        .request-status.rejected {
            background: #f9dada;
            color: #c54d4d;
        }

        .inventory-empty {
            padding: 22px 18px;
            color: #8b8b8b;
            font-size: 12px;
        }

        .modal-edit-id {
            display: none;
        }

    </style>

<style id="sanctuario-responsive-refinement">
/* Sanctuario responsive refinement — layout geometry only, no feature changes. */
@media (max-width: 1199px) {
  html { -webkit-text-size-adjust: 100%; }
  body { min-width: 0; }
  .dashboard, .farm-map-page, .main-content, .page-content, .dashboard-content,
  .content-wrapper, .inventory-page, .records-page, .settings-page {
    min-width: 0; max-width: 100%; box-sizing: border-box;
  }
  .dashboard, .farm-map-page { padding-left: clamp(12px, 2vw, 24px); padding-right: clamp(12px, 2vw, 24px); }
  .hero-container, .farm-summary { padding: clamp(12px, 2vw, 26px); }
  .hero-cards, .top-grid, .bottom-grid, .summary-cards, .farm-map-layout,
  [class*="-grid"], [class*="_grid"] { min-width: 0; }
  .hero-cards > *, .summary-cards > *, .farm-map-layout > *,
  [class*="-grid"] > *, [class*="_grid"] > * { min-width: 0; }
  h1 { font-size: clamp(1.4rem, 2.6vw, 2.15rem); }
  h2 { font-size: clamp(1.2rem, 2.25vw, 1.9rem); }
  h3 { font-size: clamp(1.05rem, 1.8vw, 1.5rem); }
  img, video, canvas, svg { max-width: 100%; }
  input, select, textarea { max-width: 100%; min-width: 0; }
  .modal-content, .popup-content, [class*="modal-content"], [class*="popup-content"] {
    box-sizing: border-box; max-width: min(94vw, 100%); max-height: 90dvh;
  }
  .table-wrapper, .table-container, .table-responsive, [class*="table-wrap"],
  [class*="table-container"] { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
}
@media (min-width: 701px) and (max-width: 1199px) {
  .dashboard, .farm-map-page { padding-top: 14px; padding-bottom: 16px; }
  .hero-container, .farm-summary { margin-bottom: 12px; }
  .hero-cards, .summary-cards, .top-grid, .bottom-grid { gap: clamp(9px, 1.4vw, 18px); }
}
@media (max-width: 900px) {
  :root { --sidebar-width: 0px; }
  .dashboard, .farm-map-page { margin-left: 0; width: 100%; }
}
@media (max-width: 700px) {
  .dashboard, .farm-map-page { padding: 68px 12px 16px; }
  .hero-container, .farm-summary { padding: 12px; border-radius: 12px; }
  .hero-text h2 { font-size: clamp(1.35rem, 5vw, 1.85rem); }
  .hero-text p, .farm-map-title-area p { font-size: clamp(.75rem, 3vw, .95rem); }
  .hero-cards { gap: 9px; }
  .modal-content, .popup-content, [class*="modal-content"], [class*="popup-content"] {
    max-width: calc(100vw - 20px); max-height: calc(100dvh - 20px);
  }
}
@media (max-width: 380px) {
  .dashboard, .farm-map-page { padding-left: 8px; padding-right: 8px; }
  .hero-container, .farm-summary { padding: 10px; }
}

</style>
</head>


<body>


<?php include '../sidebar.php'; ?>


<!-- ============================================================
     MAIN CONTENT
============================================================ -->

<main class="inventory-page">


    <!-- ========================================================
         TOP RIGHT BAR
    ======================================================== -->

    <?php include '../topRightBar.php'; ?>


    <!-- ========================================================
         SUMMARY AREA
    ======================================================== -->

    <section class="inventory-summary">

        <div class="inventory-header">

            <h1>
                INVENTORY
            </h1>

        </div>


        <style>
.snf-download-actions{display:inline-flex;gap:7px;align-items:center;flex-wrap:wrap}
.snf-download-actions a{display:inline-flex;align-items:center;justify-content:center;gap:5px;padding:9px 12px;border:1px solid #2b6734;border-radius:8px;background:#fff;color:#24552e;text-decoration:none;font:600 12px inherit;white-space:nowrap;min-height:34px;box-sizing:border-box}
.snf-download-actions a:hover{background:#edf5ec}
@media(max-width:700px){.snf-download-actions{gap:5px}.snf-download-actions a{padding:7px 9px;font-size:12px}}
</style>
<div class="snf-download-actions"><a href="../export_records.php?report=inventory&amp;format=xlsx" title="Download Excel">Download Excel</a><a href="../export_records.php?report=inventory&amp;format=pdf" title="Download PDF">Download PDF</a></div>
<div class="summary-message">
            here's what's happening on farm today...
        </div>


        <?php if ($message): ?>

            <div class="inventory-alert success">
                <?= e($message) ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="inventory-alert error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


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
                        <?= $totalItems ?>
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
                        <?= $pendingRequests ?>
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
                        <?= $lowStockItems ?>
                    </strong>

                </div>

            </div>


        </div>

    </section>



    <!-- ========================================================
         INVENTORY CONTENT
    ======================================================== -->

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
                            onclick="changeCategory('All')"
                        >
                            All
                        </button>

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

                            <th>DATE RECEIVED</th>
                            <th>CROPS</th>
                            <th>VARIETY</th>
                            <th>BRAND</th>
                            <th>PIECES</th>
                            <th>NO. OF SEEDS</th>
                            <th>PRICE</th>
                            <th>STATUS</th>
                            <th>ACTIONS</th>

                        </tr>

                    </thead>


                    <tbody id="inventoryTableBody">


                    <?php if (!$items): ?>

                        <tr>

                            <td
                                colspan="9"
                                class="inventory-empty"
                            >
                                No inventory items yet.
                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach ($items as $item): ?>

                            <?php
                                $statusClass = 'in-stock';

                                if ($item['status'] === 'Low Stock') {
                                    $statusClass = 'low-stock';
                                } elseif ($item['status'] === 'No Stock') {
                                    $statusClass = 'no-stock';
                                }
                            ?>

                            <tr
                                data-category="<?= e($item['category']) ?>"
                            >

                                <td>
                                    <?= date(
                                        'm/d/Y',
                                        strtotime($item['date_received'])
                                    ) ?>
                                </td>

                                <td>
                                    <?= e($item['item_name']) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $item['variety'] ?: '—'
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $item['brand'] ?: '—'
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $item['pieces'] ?: '—'
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $item['quantity'] ?: '—'
                                    ) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (float)$item['price'],
                                        2
                                    ) ?>
                                </td>

                                <td>

                                    <span
                                        class="stock-status <?= e(
                                            $statusClass
                                        ) ?>"
                                    >

                                        <?= e(
                                            $item['status']
                                        ) ?>

                                    </span>

                                </td>

                                <td>

                                    <div class="table-actions">

                                        <!-- STOCK IN / OUT -->
                                        <button
                                            type="button"
                                            class="stock-move-button"
                                            onclick="openStockMove(<?= (int)$item['inventory_id'] ?>, '<?= e($item['item_name']) ?>', '<?= e($item['quantity']) ?>')"
                                            title="Stock In / Out"
                                        >
                                            <i class="fa-solid fa-right-left"></i>
                                        </button>


                                        <!-- EDIT -->

                                        <button
                                            type="button"
                                            class="edit-button"
                                            onclick="editInventory(<?= (int)$item['inventory_id'] ?>)"
                                            title="Edit"
                                        >

                                            <i class="fa-solid fa-pen-to-square"></i>

                                        </button>


                                        <!-- ARCHIVE -->

                                        <button
                                            type="button"
                                            class="delete-button"
                                            onclick="archiveInventory(<?= (int)$item['inventory_id'] ?>)"
                                            title="Archive"
                                        >

                                            <i class="fa-solid fa-trash-can"></i>

                                        </button>


                                    </div>

                                </td>

                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


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


                <?php if (!$requests): ?>

                    <div class="inventory-empty">
                        No inventory requests yet.
                    </div>

                <?php else: ?>


                    <?php foreach ($requests as $request): ?>

                        <?php
                            $requestStatus = $request['status'];
                            $requestStatusClass = strtolower(
                                $requestStatus
                            );
                        ?>

                        <div
                            class="inventory-request"
                            data-request-id="<?= (int)$request['request_id'] ?>"
                        >

                            <div class="request-heading">

                                <span>

                                    <?= e(
                                        $request['item_name']
                                    ) ?>

                                    x<?= e(
                                        $request['quantity']
                                    ) ?>

                                </span>


                                <span
                                    class="request-status <?= e(
                                        $requestStatusClass
                                    ) ?>"
                                >

                                    <?= e(
                                        $requestStatus
                                    ) ?>

                                </span>

                            </div>


                            <div class="request-user">

                                Requested By:

                                <?= e(
                                    $request['requester_name']
                                    ?: $request['requester_username']
                                    ?: 'Unknown User'
                                ) ?>

                            </div>


                            <?php if (
                                !empty($request['request_note'])
                            ): ?>

                                <div class="request-user">

                                    <?= e(
                                        $request['request_note']
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <div class="request-buttons">


                                <!-- APPROVE -->

                                <form
                                    method="post"
                                    style="display:inline"
                                    onsubmit="return confirm('Approve this inventory request?');"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="approve_request"
                                    >

                                    <input
                                        type="hidden"
                                        name="request_id"
                                        value="<?= (int)$request['request_id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="request-approve"
                                        <?= $requestStatus !== 'Pending'
                                            ? 'disabled'
                                            : '' ?>
                                    >
                                        Approve
                                    </button>

                                </form>


                                <!-- REJECT -->

                                <form
                                    method="post"
                                    style="display:inline"
                                    onsubmit="return confirm('Reject this inventory request?');"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="reject_request"
                                    >

                                    <input
                                        type="hidden"
                                        name="request_id"
                                        value="<?= (int)$request['request_id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="request-reject <?= $requestStatus === 'Rejected'
                                            ? 'red'
                                            : '' ?>"
                                        <?= $requestStatus !== 'Pending'
                                            ? 'disabled'
                                            : '' ?>
                                    >
                                        Reject
                                    </button>

                                </form>


                                <!-- VIEW -->

                                <button
                                    type="button"
                                    class="request-view"
                                    onclick="viewRequest(
                                        <?= (int)$request['request_id'] ?>
                                    )"
                                >
                                    View
                                </button>


                            </div>

                        </div>

                    <?php endforeach; ?>


                <?php endif; ?>


            </div>


        </div>


    </section>

</main>



<!-- ============================================================
     ADD / EDIT ITEM MODAL
============================================================ -->

<div
    class="inventory-modal-overlay <?= $modalEditMode ? 'show' : '' ?>"
    id="inventoryModal"
>


    <div class="inventory-modal">


        <div class="modal-header">

            <h2 id="inventoryModalTitle">

                <?= $modalEditMode
                    ? 'Edit Inventory Item'
                    : 'Add Inventory Item'
                ?>

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
            method="post"
        >

            <input
                type="hidden"
                name="action"
                id="inventoryAction"
                value="<?= $modalEditMode
                    ? 'update_item'
                    : 'add_item'
                ?>"
            >


            <input
                type="hidden"
                name="inventory_id"
                id="inventoryId"
                value="<?= $modalEditMode
                    ? (int)$editItem['inventory_id']
                    : ''
                ?>"
            >


            <div class="modal-form-grid">


                <div class="form-group">

                    <label>
                        Category
                    </label>

                    <select
                        name="category"
                        id="inventoryCategory"
                        required
                    >

                        <option value="">
                            Select Category
                        </option>

                        <?php
                        $categories = [
                            'Seeds',
                            'Fertilizer',
                            'Tools',
                            'Feeds',
                            'Others'
                        ];
                        ?>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?= e($category) ?>"
                                <?= (
                                    $modalEditMode &&
                                    $editItem['category'] === $category
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >

                                <?= e($category) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Item Name
                    </label>

                    <input
                        type="text"
                        name="item_name"
                        id="inventoryItemName"
                        placeholder="e.g. Tomato Seeds"
                        value="<?= $modalEditMode
                            ? e($editItem['item_name'])
                            : ''
                        ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Variety
                    </label>

                    <input
                        type="text"
                        name="variety"
                        id="inventoryVariety"
                        placeholder="e.g. Purple Gem F1"
                        value="<?= $modalEditMode
                            ? e($editItem['variety'])
                            : ''
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Brand
                    </label>

                    <input
                        type="text"
                        name="brand"
                        id="inventoryBrand"
                        placeholder="e.g. Condor"
                        value="<?= $modalEditMode
                            ? e($editItem['brand'])
                            : ''
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Pieces
                    </label>

                    <input
                        type="text"
                        name="pieces"
                        id="inventoryPieces"
                        placeholder="e.g. 2 Packs"
                        value="<?= $modalEditMode
                            ? e($editItem['pieces'])
                            : ''
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Quantity
                    </label>

                    <input
                        type="text"
                        name="quantity"
                        id="inventoryQuantity"
                        placeholder="e.g. 10 Grams"
                        value="<?= $modalEditMode
                            ? e($editItem['quantity'])
                            : ''
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Price
                    </label>

                    <input
                        type="number"
                        name="price"
                        id="inventoryPrice"
                        placeholder="0.00"
                        min="0"
                        step="0.01"
                        value="<?= $modalEditMode
                            ? e($editItem['price'])
                            : ''
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select
                        name="status"
                        id="inventoryStatus"
                    >

                        <?php
                        $statuses = [
                            'In Stock',
                            'Low Stock',
                            'No Stock'
                        ];
                        ?>

                        <?php foreach ($statuses as $status): ?>

                            <option
                                value="<?= e($status) ?>"
                                <?= (
                                    (
                                        $modalEditMode
                                        && $editItem['status'] === $status
                                    )
                                    ||
                                    (
                                        !$modalEditMode
                                        && $status === 'In Stock'
                                    )
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >

                                <?= e($status) ?>

                            </option>

                        <?php endforeach; ?>

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

                    <?= $modalEditMode
                        ? 'Update Item'
                        : 'Save Item'
                    ?>

                </button>

            </div>

        </form>

    </div>

</div>



<!-- ============================================================
     STOCK IN / OUT MODAL
============================================================ -->

<div class="inventory-modal-overlay" id="stockMoveModal">

    <div class="inventory-modal">

        <div class="inventory-modal-header">
            <h2>Stock Movement</h2>
            <button type="button" class="modal-close" onclick="closeStockMove()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="post" id="stockMoveForm">

            <input type="hidden" name="action" value="stock_transaction">
            <input type="hidden" name="inventory_id" id="stockMoveInventoryId">

            <div class="stock-move-item">
                <strong id="stockMoveItemName">Inventory Item</strong>
                <span>Current quantity: <b id="stockMoveCurrentQty">0</b></span>
            </div>

            <div class="stock-move-type">
                <label>
                    <input type="radio" name="transaction_type" value="IN" checked>
                    <span><i class="fa-solid fa-arrow-down"></i> Stock IN</span>
                </label>

                <label>
                    <input type="radio" name="transaction_type" value="OUT">
                    <span><i class="fa-solid fa-arrow-up"></i> Stock OUT</span>
                </label>
            </div>

            <div class="form-group">
                <label for="transactionQuantity">Quantity</label>
                <input
                    type="number"
                    id="transactionQuantity"
                    name="transaction_quantity"
                    min="0.01"
                    step="0.01"
                    required
                >
            </div>

            <div class="form-group">
                <label for="transactionNote">Note</label>
                <input
                    type="text"
                    id="transactionNote"
                    name="transaction_note"
                    maxlength="255"
                    placeholder="e.g. New delivery / used for sowing"
                >
            </div>

            <div class="modal-actions">
                <button type="button" class="modal-cancel" onclick="closeStockMove()">
                    Cancel
                </button>

                <button type="submit" class="modal-save">
                    Save Movement
                </button>
            </div>

        </form>

    </div>

</div>


<!-- ============================================================
     RECENT STOCK MOVEMENTS
============================================================ -->

<div class="inventory-panel stock-history-panel">

    <div class="panel-title">
        RECENT STOCK MOVEMENTS
    </div>

    <?php if (!$recentTransactions): ?>

        <div class="inventory-empty">
            No stock IN/OUT transactions yet.
        </div>

    <?php else: ?>

        <?php foreach ($recentTransactions as $transaction): ?>

            <div class="stock-history-row">

                <div>
                    <strong><?= e($transaction['item_name']) ?></strong>
                    <small>
                        <?= date('M d, Y h:i A', strtotime($transaction['created_at'])) ?>
                        <?php if (!empty($transaction['created_by_name'])): ?>
                            • <?= e($transaction['created_by_name']) ?>
                        <?php endif; ?>
                    </small>
                </div>

                <span class="stock-history-type <?= strtolower($transaction['transaction_type']) ?>">
                    <?= e($transaction['transaction_type']) ?>
                    <?= e($transaction['quantity']) ?>
                </span>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

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


/*
|--------------------------------------------------------------------------
| CATEGORY FILTER
|--------------------------------------------------------------------------
*/

let currentCategory = 'All';

function changeCategory(category) {

    currentCategory = category;

    document.getElementById('categoryText').textContent =
        category;

    categoryDropdown.classList.remove('show');

    filterInventory();

}


/*
|--------------------------------------------------------------------------
| SEARCH + CATEGORY FILTER
|--------------------------------------------------------------------------
*/

document
    .getElementById('inventorySearch')
    .addEventListener('keyup', filterInventory);


function filterInventory() {

    const search =
        document
            .getElementById('inventorySearch')
            .value
            .toLowerCase()
            .trim();

    const rows =
        document.querySelectorAll(
            '#inventoryTableBody tr[data-category]'
        );


    rows.forEach(function(row) {

        const rowText =
            row.textContent.toLowerCase();

        const rowCategory =
            row.dataset.category;


        const matchesSearch =
            rowText.includes(search);


        const matchesCategory =
            currentCategory === 'All' ||
            rowCategory === currentCategory;


        row.style.display =
            matchesSearch && matchesCategory
                ? ''
                : 'none';

    });

}


/*
|--------------------------------------------------------------------------
| MODAL
|--------------------------------------------------------------------------
*/

function openInventoryModal() {

    const modal =
        document.getElementById('inventoryModal');

    const form =
        document.getElementById('inventoryForm');


    /*
     * If the page was opened normally,
     * make the modal an ADD form.
     */

    document.getElementById(
        'inventoryModalTitle'
    ).textContent =
        'Add Inventory Item';


    document.getElementById(
        'inventoryAction'
    ).value =
        'add_item';


    document.getElementById(
        'inventoryId'
    ).value =
        '';


    form.reset();


    document.getElementById(
        'inventoryStatus'
    ).value =
        'In Stock';


    modal.classList.add('show');

}


function closeInventoryModal() {

    document
        .getElementById('inventoryModal')
        .classList.remove('show');

}


document
    .getElementById('inventoryModal')
    .addEventListener(
        'click',
        function(event) {

            if (event.target === this) {

                closeInventoryModal();

            }

        }
    );


/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
|
| PHP loads the item into the same modal.
|--------------------------------------------------------------------------
*/

function editInventory(id) {

    window.location.href =
        'adminInventory.php?edit=' +
        encodeURIComponent(id);

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


    const form =
        document.createElement('form');

    form.method =
        'POST';

    form.action =
        'adminInventory.php';


    const action =
        document.createElement('input');

    action.type =
        'hidden';

    action.name =
        'action';

    action.value =
        'archive_item';


    const itemId =
        document.createElement('input');

    itemId.type =
        'hidden';

    itemId.name =
        'inventory_id';

    itemId.value =
        id;


    form.appendChild(action);

    form.appendChild(itemId);

    document.body.appendChild(form);

    form.submit();

}


/*
|--------------------------------------------------------------------------
| ARCHIVED ITEMS
|--------------------------------------------------------------------------
*/

function viewArchivedItems() {

    window.location.href =
        'archivedInventory.php';

}


/*
|--------------------------------------------------------------------------
| REQUEST VIEW
|--------------------------------------------------------------------------
*/

function viewRequest(id) {

    window.location.href =
        'adminInventory.php?view_request=' +
        encodeURIComponent(id);

}


function openStockMove(id, name, currentQty) {
    document.getElementById('stockMoveInventoryId').value = id;
    document.getElementById('stockMoveItemName').textContent = name;
    document.getElementById('stockMoveCurrentQty').textContent = currentQty || '0';
    document.getElementById('transactionQuantity').value = '';
    document.getElementById('transactionNote').value = '';
    document.getElementById('stockMoveModal').classList.add('show');
}

function closeStockMove() {
    document.getElementById('stockMoveModal').classList.remove('show');
}

document.getElementById('stockMoveModal')?.addEventListener('click', function(event) {
    if (event.target === this) {
        closeStockMove();
    }
});

</script>


<?php

/*
|--------------------------------------------------------------------------
| REQUEST VIEW MODAL
|--------------------------------------------------------------------------
|
| Kept simple so the existing design is not changed.
|--------------------------------------------------------------------------
*/

if (isset($_GET['view_request']) && $tableReady):

    $viewRequestId = (int)$_GET['view_request'];

    if ($viewRequestId > 0):

        $viewRequest = null;

        try {

            $stmt = $conn->prepare("
                SELECT
                    ir.*,
                    u.name AS requester_name,
                    u.username AS requester_username
                FROM inventory_requests ir
                LEFT JOIN users u
                    ON u.user_id = ir.requested_by
                WHERE ir.request_id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "i",
                $viewRequestId
            );

            $stmt->execute();

            $viewRequest =
                $stmt
                    ->get_result()
                    ->fetch_assoc();

            $stmt->close();

        } catch (Throwable $ex) {

            $viewRequest = null;

        }

        if ($viewRequest):

?>

<style>

.inventory-request-view-overlay {

    position: fixed;
    inset: 0;
    z-index: 6000;

    background: rgba(0,0,0,.42);

    display: flex;
    align-items: center;
    justify-content: center;

}

.inventory-request-view {

    width: 430px;
    max-width: calc(100% - 30px);

    background: #fff;

    border-radius: 15px;

    padding: 25px;

    box-shadow:
        0 15px 50px rgba(0,0,0,.25);

}

.inventory-request-view h2 {

    color: #315d31;
    font-size: 21px;
    margin-bottom: 18px;

}

.inventory-request-view-row {

    margin-bottom: 12px;

}

.inventory-request-view-row span {

    display: block;

    color: #888;

    font-size: 11px;

    margin-bottom: 3px;

}

.inventory-request-view-row strong {

    color: #333;

    font-size: 13px;

}

.inventory-request-view-close {

    margin-top: 15px;

    height: 38px;

    padding: 0 20px;

    border: none;

    border-radius: 8px;

    background: #326735;

    color: #fff;

    font-size: 12px;

}

</style>


<div class="inventory-request-view-overlay">

    <div class="inventory-request-view">

        <h2>
            Inventory Request
        </h2>


        <div class="inventory-request-view-row">

            <span>
                Item
            </span>

            <strong>
                <?= e($viewRequest['item_name']) ?>
            </strong>

        </div>


        <div class="inventory-request-view-row">

            <span>
                Quantity
            </span>

            <strong>
                <?= e($viewRequest['quantity']) ?>
            </strong>

        </div>


        <div class="inventory-request-view-row">

            <span>
                Requested By
            </span>

            <strong>
                <?= e(
                    $viewRequest['requester_name']
                    ?: $viewRequest['requester_username']
                    ?: 'Unknown User'
                ) ?>
            </strong>

        </div>


        <div class="inventory-request-view-row">

            <span>
                Status
            </span>

            <strong>
                <?= e($viewRequest['status']) ?>
            </strong>

        </div>


        <div class="inventory-request-view-row">

            <span>
                Request Note
            </span>

            <strong>
                <?= e(
                    $viewRequest['request_note']
                    ?: 'No note provided.'
                ) ?>
            </strong>

        </div>


        <div class="inventory-request-view-row">

            <span>
                Requested On
            </span>

            <strong>
                <?= e($viewRequest['created_at']) ?>
            </strong>

        </div>


        <button
            type="button"
            class="inventory-request-view-close"
            onclick="window.location.href='adminInventory.php'"
        >

            Close

        </button>

    </div>

</div>

<?php

        endif;

    endif;

endif;

?>

</body>

</html>