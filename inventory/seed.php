<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../db.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

/* Keep dedicated archive storage available even on older installations. */
if ($isAdmin) {
    $conn->query("CREATE TABLE IF NOT EXISTS `archived_seeds` (
        archive_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        original_inventory_id INT UNSIGNED NOT NULL,
        date_received DATE NOT NULL,
        category VARCHAR(50) NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        variety VARCHAR(150) DEFAULT NULL,
        brand VARCHAR(150) DEFAULT NULL,
        pieces VARCHAR(100) DEFAULT NULL,
        quantity VARCHAR(100) DEFAULT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status VARCHAR(30) NOT NULL DEFAULT 'In Stock',
        archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (archive_id),
        KEY idx_archived_seeds_original (original_inventory_id),
        KEY idx_archived_seeds_archived_at (archived_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/* Allow staff/admin users to submit a stock request from this page. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_item') {
    try {
        $itemName = trim($_POST['item_name'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $note = trim($_POST['request_note'] ?? '');

        if ($itemName === '' || $quantity === '') {
            throw new Exception('Item name and requested quantity are required.');
        }

        $stmt = $conn->prepare(
            "INSERT INTO inventory_requests (item_name, quantity, requested_by, request_note)
             VALUES (?, ?, ?, ?)"
        );

        if (!$stmt) {
            throw new Exception('Could not prepare the inventory request.');
        }

        $userId = (int)$_SESSION['user_id'];
        $stmt->bind_param('ssis', $itemName, $quantity, $userId, $note);
        $stmt->execute();
        $stmt->close();

        $message = 'Seed request submitted successfully.';
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

/* ------------------------------------------------------------
   Admin only: edit (modal on this page) and delete (= archive).
   Deleted records are moved to the dedicated archived_seeds table
   and displayed in seedsArchived.php.
------------------------------------------------------------ */
if (empty($_SESSION['inventory_csrf'])) {
    $_SESSION['inventory_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($_POST['action'] ?? '', ['update_item', 'archive_item'], true)) {

    $postAction = $_POST['action'];
    $returnMonth = (int)($_POST['month'] ?? 0);
    $redirectUrl = $postAction === 'archive_item'
        ? 'seedsArchived.php'
        : 'seed.php' . (($returnMonth >= 1 && $returnMonth <= 12) ? '?month=' . $returnMonth : '');

    try {
        if (!$isAdmin) {
            throw new Exception('Only admins can edit or archive inventory items.');
        }

        if (!hash_equals($_SESSION['inventory_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
            throw new Exception('Invalid request. Please refresh the page and try again.');
        }

        $inventoryId = (int)($_POST['inventory_id'] ?? 0);
        if ($inventoryId <= 0) {
            throw new Exception('Invalid inventory item.');
        }

        if ($postAction === 'update_item') {
            $category = trim($_POST['category'] ?? '');
            $itemName = trim($_POST['item_name'] ?? '');
            $variety = trim($_POST['variety'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $pieces = trim($_POST['pieces'] ?? '');
            $quantity = trim($_POST['quantity'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $status = trim($_POST['status'] ?? 'In Stock');

            if (!in_array($category, ['Seeds', 'Fertilizer', 'Tools', 'Feeds', 'Others'], true)) {
                throw new Exception('Please select a valid category.');
            }
            if ($itemName === '') {
                throw new Exception('Item name is required.');
            }
            if (!in_array($status, ['In Stock', 'Low Stock', 'No Stock'], true)) {
                throw new Exception('Please select a valid stock status.');
            }
            if ($price < 0) {
                throw new Exception('Price cannot be negative.');
            }

            $stmt = $conn->prepare(
                "UPDATE inventory_items
                 SET category = ?, item_name = ?, variety = ?, brand = ?,
                     pieces = ?, quantity = ?, price = ?, status = ?
                 WHERE inventory_id = ?
                   AND category = 'Seeds'
                   AND is_archived = 0"
            );

            if (!$stmt) {
                throw new Exception('Could not prepare item update.');
            }

            $stmt->bind_param('ssssssdsi', $category, $itemName, $variety, $brand, $pieces, $quantity, $price, $status, $inventoryId);
            $stmt->execute();
            $stmt->close();

            $_SESSION['inventory_flash'] = ['type' => 'success', 'text' => 'Inventory item updated successfully.'];
        } else {
            /*
             * Move the seed record to the dedicated archive table.
             * The active record is removed only after the archive insert succeeds.
             */
            $conn->begin_transaction();

            try {
                $stmt = $conn->prepare(
                    "INSERT INTO archived_seeds
                        (original_inventory_id, date_received, category, item_name,
                         variety, brand, pieces, quantity, price, status)
                     SELECT inventory_id, date_received, category, item_name,
                            variety, brand, pieces, quantity, price, status
                     FROM inventory_items
                     WHERE inventory_id = ?
                       AND category = 'Seeds'
                       AND is_archived = 0"
                );

                if (!$stmt) {
                    throw new Exception('Could not prepare the seed archive request.');
                }

                $stmt->bind_param('i', $inventoryId);
                $stmt->execute();
                $archivedRows = $stmt->affected_rows;
                $stmt->close();

                if ($archivedRows < 1) {
                    throw new Exception('That seed was not found or is already archived.');
                }

                $stmt = $conn->prepare(
                    "DELETE FROM inventory_items
                     WHERE inventory_id = ?
                       AND category = 'Seeds'
                       AND is_archived = 0"
                );

                if (!$stmt) {
                    throw new Exception('Could not prepare removal of the active seed record.');
                }

                $stmt->bind_param('i', $inventoryId);
                $stmt->execute();
                $deletedRows = $stmt->affected_rows;
                $stmt->close();

                if ($deletedRows < 1) {
                    throw new Exception('The seed could not be removed from active inventory.');
                }

                $conn->commit();
                $_SESSION['inventory_flash'] = [
                    'type' => 'success',
                    'text' => 'Seed moved to archived seeds.'
                ];
            } catch (Throwable $archiveError) {
                $conn->rollback();
                throw $archiveError;
            }
        }
    } catch (Throwable $ex) {
        $_SESSION['inventory_flash'] = ['type' => 'error', 'text' => $ex->getMessage()];
    }

    header('Location: ' . $redirectUrl);
    exit();
}

if (!empty($_SESSION['inventory_flash'])) {
    if (($_SESSION['inventory_flash']['type'] ?? '') === 'success') {
        $message = (string)$_SESSION['inventory_flash']['text'];
    } else {
        $error = (string)($_SESSION['inventory_flash']['text'] ?? '');
    }
    unset($_SESSION['inventory_flash']);
}

$items = [];
$totalItems = 0;
$lowStockItems = 0;
$noStockItems = 0;
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$statusFilters = [
    'In Stock' => 'In Stock',
    'Low Stock' => 'Low Stock',
    'No Stock' => 'No Stock'
];
$selectedMonth = (int)($_GET['month'] ?? 0);
if ($selectedMonth < 0 || $selectedMonth > 12) {
    $selectedMonth = 0;
}
/* Status is now filtered in the browser; a ?status= link only pre-selects the dropdown. */
$selectedStatus = $_GET['status'] ?? '';
if (!array_key_exists($selectedStatus, $statusFilters)) {
    $selectedStatus = '';
}
$monthClause = $selectedMonth > 0
    ? " AND MONTH(date_received) = {$selectedMonth}"
    : '';

$varietyOptions = [];
$brandOptions = [];

try {
    $result = $conn->query(
        "SELECT inventory_id, date_received, category, item_name, variety,
                brand, pieces, quantity, price, status
         FROM inventory_items
         WHERE category = 'Seeds'
           AND is_archived = 0
           {$monthClause}
         ORDER BY date_received DESC"
    );

    if (!$result) {
        throw new Exception('Could not load seed inventory.');
    }

    while ($row = $result->fetch_assoc()) {
        $items[] = $row;

        if ($row['status'] === 'Low Stock') {
            $lowStockItems++;
        } elseif ($row['status'] === 'No Stock') {
            $noStockItems++;
        }

        $varietyValue = trim((string)$row['variety']);
        if ($varietyValue !== '') {
            $varietyOptions[$varietyValue] = $varietyValue;
        }
        $brandValue = trim((string)$row['brand']);
        if ($brandValue !== '') {
            $brandOptions[$brandValue] = $brandValue;
        }
    }

    $totalItems = count($items);
    natcasesort($varietyOptions);
    natcasesort($brandOptions);
} catch (Throwable $ex) {
    $error = $ex->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seed Inventory | Sanctuario Nature Farm</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="inventory.css">
</head>
<body>
<?php include '../sidebar.php'; ?>

<main class="inventory-page">
    <?php include '../topRightBar.php'; ?>

    <section class="inventory-summary">
        <div class="inventory-header">
            <h1>SEED INVENTORY</h1>
        </div>
        <div class="summary-message">here's what's happening on farm today...</div>

        <?php if ($message): ?><div class="inventory-page-alert success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="inventory-page-alert error"><?= e($error) ?></div><?php endif; ?>

        <div class="summary-cards">
            <div class="summary-card"><div class="summary-icon summary-green"><i class="fa-solid fa-seedling"></i></div><div class="summary-info"><span>Total Seeds</span><strong><?= $totalItems ?></strong></div></div>
            <div class="summary-card"><div class="summary-icon summary-orange"><i class="fa-solid fa-triangle-exclamation"></i></div><div class="summary-info"><span>Low Stock</span><strong><?= $lowStockItems ?></strong></div></div>
            <div class="summary-card"><div class="summary-icon summary-red"><i class="fa-solid fa-box-open"></i></div><div class="summary-info"><span>No Stock</span><strong><?= $noStockItems ?></strong></div></div>
        </div>
    </section>

    <section class="inventory-layout inventory-layout-single">
        <div class="inventory-main">
            <div class="inventory-toolbar">
                <div class="inventory-category">
                    <button type="button" class="category-button" id="categoryButton"><span><?= e($selectedMonth > 0 ? $months[$selectedMonth] : 'All Months') ?></span><i class="fa-solid fa-caret-down"></i></button>
                    <div class="category-dropdown" id="categoryDropdown">
                        <button type="button" onclick="location.href='seed.php'">All Months</button>
                        <?php foreach ($months as $monthNumber => $monthName): ?>
                            <button type="button" onclick="location.href='seed.php?month=<?= $monthNumber ?>'"><?= e($monthName) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="inventory-search"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="inventorySearch" placeholder="Search here..."></div>
                <div class="inventory-filters">
                    <select class="inventory-filter-select" data-filter="status" aria-label="Filter by status">
                        <option value="">All Status</option>
                        <?php foreach ($statusFilters as $statusValue => $statusName): ?>
                            <option value="<?= e($statusValue) ?>"<?= $selectedStatus === $statusValue ? ' selected' : '' ?>><?= e($statusName) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="inventory-filter-select" data-filter="variety" aria-label="Filter by variety">
                        <option value="">All Varieties</option>
                        <?php foreach ($varietyOptions as $varietyName): ?>
                            <option value="<?= e($varietyName) ?>"><?= e($varietyName) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="inventory-filter-select" data-filter="brand" aria-label="Filter by brand">
                        <option value="">All Brands</option>
                        <?php foreach ($brandOptions as $brandName): ?>
                            <option value="<?= e($brandName) ?>"><?= e($brandName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="toolbar-space"></div>
                <?php if ($isAdmin): ?>
                    <a class="archive-items-button" href="seedsArchived.php">View Archived Items <i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>

            <div class="inventory-table-wrapper">
                <table class="inventory-table" id="inventoryTable">
                    <thead><tr><th class="row-number-col">#</th><th>DATE RECEIVED</th><th>SEEDS</th><th>VARIETY</th><th>BRAND</th><th>PIECES</th><th>NO. OF SEEDS</th><th>PRICE</th><th>STATUS</th><?php if ($isAdmin): ?><th>ACTIONS</th><?php endif; ?></tr></thead>
                    <tbody id="inventoryTableBody">
                    <?php if (!$items): ?>
                        <tr><td colspan="<?= $isAdmin ? 10 : 9 ?>" class="inventory-empty">No seed inventory items yet.</td></tr>
                    <?php else: $inventoryRowNumber = 1; foreach ($items as $item):
                        $statusClass = $item['status'] === 'Low Stock' ? 'low-stock' : ($item['status'] === 'No Stock' ? 'no-stock' : 'in-stock');
                        $editPayload = json_encode([
                            'inventory_id' => (int)$item['inventory_id'],
                            'category'     => $item['category'],
                            'item_name'    => $item['item_name'],
                            'variety'      => $item['variety'],
                            'brand'        => $item['brand'],
                            'pieces'       => $item['pieces'],
                            'quantity'     => $item['quantity'],
                            'price'        => $item['price'],
                            'status'       => $item['status'],
                        ], JSON_UNESCAPED_UNICODE);
                    ?>
                        <tr data-searchable="<?= e(implode(' ', $item)) ?>"
                            data-status="<?= e($item['status']) ?>"
                            data-variety="<?= e(trim((string)$item['variety'])) ?>"
                            data-brand="<?= e(trim((string)$item['brand'])) ?>">
                            <td class="row-number"><?= $inventoryRowNumber++ ?></td>
                            <td><?= e(date('m/d/Y', strtotime($item['date_received']))) ?></td>
                            <td class="item-name"><?= e($item['item_name']) ?></td>
                            <td><?= e($item['variety'] ?: '—') ?></td>
                            <td><?= e($item['brand'] ?: '—') ?></td>
                            <td><?= e($item['pieces'] ?: '—') ?></td>
                            <td><?= e($item['quantity'] ?: '—') ?></td>
                            <td><?= number_format((float)$item['price'], 2) ?></td>
                            <td><span class="stock-status <?= e($statusClass) ?>"><?= e($item['status']) ?></span></td>
                            <?php if ($isAdmin): ?><td><div class="table-actions"><button type="button" class="edit-button" data-item="<?= e($editPayload) ?>" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button><button type="button" class="delete-button" data-id="<?= (int)$item['inventory_id'] ?>" data-name="<?= e($item['item_name']) ?>" title="Delete"><i class="fa-solid fa-trash"></i></button></div></td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                        <tr id="noMatchRow" style="display:none;"><td colspan="<?= $isAdmin ? 10 : 9 ?>" class="inventory-empty">No seed items match the current filters.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<?php if ($isAdmin): ?>
<!-- Edit item modal (opens on this page) -->
<div class="inventory-modal-overlay" id="inventoryModal">
    <div class="inventory-modal">
        <div class="modal-header">
            <h2>Edit Inventory Item</h2>
            <button type="button" class="modal-close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="inventoryForm" method="post">
            <input type="hidden" name="action" value="update_item">
            <input type="hidden" name="inventory_id" id="inventoryId" value="">
            <input type="hidden" name="month" value="<?= $selectedMonth ?>">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['inventory_csrf']) ?>">

            <div class="modal-form-grid">
                <div class="form-group">
                    <label for="inventoryCategory">Category</label>
                    <select name="category" id="inventoryCategory" required>
                        <option value="">Select Category</option>
                        <?php foreach (['Seeds', 'Fertilizer', 'Tools', 'Feeds', 'Others'] as $modalCategory): ?>
                            <option value="<?= e($modalCategory) ?>"><?= e($modalCategory) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="inventoryItemName">Item Name</label>
                    <input type="text" name="item_name" id="inventoryItemName" placeholder="e.g. Tomato Seeds" required>
                </div>
                <div class="form-group">
                    <label for="inventoryVariety">Variety</label>
                    <input type="text" name="variety" id="inventoryVariety" placeholder="e.g. Purple Gem F1">
                </div>
                <div class="form-group">
                    <label for="inventoryBrand">Brand</label>
                    <input type="text" name="brand" id="inventoryBrand" placeholder="e.g. Condor">
                </div>
                <div class="form-group">
                    <label for="inventoryPieces">Pieces</label>
                    <input type="text" name="pieces" id="inventoryPieces" placeholder="e.g. 2 Packs">
                </div>
                <div class="form-group">
                    <label for="inventoryQuantity">Quantity</label>
                    <input type="text" name="quantity" id="inventoryQuantity" placeholder="e.g. 10 Grams">
                </div>
                <div class="form-group">
                    <label for="inventoryPrice">Price</label>
                    <input type="number" name="price" id="inventoryPrice" placeholder="0.00" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label for="inventoryStatus">Status</label>
                    <select name="status" id="inventoryStatus">
                        <?php foreach (['In Stock', 'Low Stock', 'No Stock'] as $modalStatus): ?>
                            <option value="<?= e($modalStatus) ?>"><?= e($modalStatus) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="modal-cancel" data-close-modal>Cancel</button>
                <button type="submit" class="modal-save">Update Item</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete = move to archived seeds -->
<form method="post" id="archiveForm" hidden>
    <input type="hidden" name="action" value="archive_item">
    <input type="hidden" name="inventory_id" id="archiveItemId" value="">
    <input type="hidden" name="month" value="<?= $selectedMonth ?>">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['inventory_csrf']) ?>">
</form>
<?php endif; ?>
<script>
const categoryButton = document.getElementById('categoryButton');
const categoryDropdown = document.getElementById('categoryDropdown');
categoryButton.addEventListener('click', event => { event.stopPropagation(); categoryDropdown.classList.toggle('show'); });
document.addEventListener('click', () => categoryDropdown.classList.remove('show'));

const inventorySearch = document.getElementById('inventorySearch');
const filterSelects = document.querySelectorAll('.inventory-filter-select');
const inventoryRows = document.querySelectorAll('#inventoryTableBody tr[data-searchable]');
const noMatchRow = document.getElementById('noMatchRow');

function applyInventoryFilters() {
    const term = inventorySearch.value.toLowerCase().trim();
    let visibleCount = 0;

    inventoryRows.forEach(row => {
        let match = row.dataset.searchable.toLowerCase().includes(term);
        filterSelects.forEach(select => {
            if (match && select.value !== '' && row.dataset[select.dataset.filter] !== select.value) {
                match = false;
            }
        });
        row.style.display = match ? '' : 'none';
        if (match) visibleCount++;
    });

    if (noMatchRow) {
        noMatchRow.style.display = visibleCount === 0 ? '' : 'none';
    }
}

inventorySearch.addEventListener('input', applyInventoryFilters);
filterSelects.forEach(select => select.addEventListener('change', applyInventoryFilters));
applyInventoryFilters();

/* Admin only: edit modal + delete (archive). Elements exist only for admins. */
const inventoryModal = document.getElementById('inventoryModal');
if (inventoryModal) {
    const modalFields = {
        category: 'inventoryCategory',
        item_name: 'inventoryItemName',
        variety: 'inventoryVariety',
        brand: 'inventoryBrand',
        pieces: 'inventoryPieces',
        quantity: 'inventoryQuantity',
        price: 'inventoryPrice',
        status: 'inventoryStatus'
    };

    const openEditModal = item => {
        document.getElementById('inventoryId').value = item.inventory_id;
        Object.keys(modalFields).forEach(key => {
            document.getElementById(modalFields[key]).value = item[key] ?? '';
        });
        inventoryModal.classList.add('show');
    };
    const closeEditModal = () => inventoryModal.classList.remove('show');

    document.getElementById('inventoryTableBody').addEventListener('click', event => {
        const editButton = event.target.closest('.edit-button');
        if (editButton) {
            openEditModal(JSON.parse(editButton.dataset.item));
            return;
        }

        const deleteButton = event.target.closest('.delete-button');
        if (deleteButton && confirm('Move "' + deleteButton.dataset.name + '" to archived inventory?')) {
            document.getElementById('archiveItemId').value = deleteButton.dataset.id;
            document.getElementById('archiveForm').submit();
        }
    });

    document.querySelectorAll('[data-close-modal]').forEach(button => button.addEventListener('click', closeEditModal));
    inventoryModal.addEventListener('click', event => { if (event.target === inventoryModal) closeEditModal(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeEditModal(); });
}
</script>
</body>
</html>