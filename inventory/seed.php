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

$items = [];
$totalItems = 0;
$lowStockItems = 0;
$noStockItems = 0;
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$selectedMonth = (int)($_GET['month'] ?? 0);
if ($selectedMonth < 0 || $selectedMonth > 12) {
    $selectedMonth = 0;
}
$monthClause = $selectedMonth > 0
    ? " AND MONTH(date_received) = {$selectedMonth}"
    : '';

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
    }

    $totalItems = count($items);
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
    <style>
        .inventory-page-alert { margin-bottom: 14px; padding: 10px 14px; border-radius: 8px; font-size: 12px; }
        .inventory-page-alert.success { background: #e1f4e7; color: #2f7b4b; }
        .inventory-page-alert.error { background: #f9dddd; color: #b34343; }
        .inventory-page-actions { display: flex; gap: 8px; align-items: center; }
        .inventory-manage-button { height: 32px; padding: 0 14px; border: none; border-radius: 18px; background: #326735; color: #fff; text-decoration: none; display: inline-flex; align-items: center; gap: 7px; font-size: 13px; }
        .inventory-manage-button:hover { background: #28582a; }
        .inventory-empty { padding: 22px 18px; color: #8b8b8b; font-size: 12px; }
        .inventory-table td.item-name { text-align: left; }
        .inventory-layout { display: block !important; }
        .inventory-main { width: 100%; }
        .inventory-table-wrapper { min-height: 420px; }
        @media (max-width: 750px) { .inventory-page-actions { width: 100%; justify-content: flex-end; } }
    </style>
</head>
<body>
<?php include '../sidebar.php'; ?>

<main class="inventory-page">
    <?php include '../topRightBar.php'; ?>

    <section class="inventory-summary">
        <div class="inventory-header">
            <h1>SEED INVENTORY</h1>
            <div class="inventory-page-actions">
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <a class="inventory-manage-button" href="adminInventory.php">
                        <i class="fa-solid fa-gear"></i> Manage Inventory
                    </a>
                <?php endif; ?>
            </div>
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

    <section class="inventory-layout">
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
                <div class="toolbar-space"></div>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <a class="archive-items-button" href="archivedInventory.php">View Archived Items <i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>

            <div class="inventory-table-wrapper">
                <table class="inventory-table" id="inventoryTable">
                    <thead><tr><th>DATE RECEIVED</th><th>SEEDS</th><th>VARIETY</th><th>BRAND</th><th>PIECES</th><th>NO. OF SEEDS</th><th>PRICE</th><th>STATUS</th><?php if (($_SESSION['role'] ?? '') === 'admin'): ?><th>ACTIONS</th><?php endif; ?></tr></thead>
                    <tbody id="inventoryTableBody">
                    <?php if (!$items): ?>
                        <tr><td colspan="<?= ($_SESSION['role'] ?? '') === 'admin' ? 9 : 8 ?>" class="inventory-empty">No seed inventory items yet.</td></tr>
                    <?php else: foreach ($items as $item):
                        $statusClass = $item['status'] === 'Low Stock' ? 'low-stock' : ($item['status'] === 'No Stock' ? 'no-stock' : 'in-stock');
                    ?>
                        <tr data-searchable="<?= e(implode(' ', $item)) ?>">
                            <td><?= e(date('m/d/Y', strtotime($item['date_received']))) ?></td>
                            <td class="item-name"><?= e($item['item_name']) ?></td>
                            <td><?= e($item['variety'] ?: '—') ?></td>
                            <td><?= e($item['brand'] ?: '—') ?></td>
                            <td><?= e($item['pieces'] ?: '—') ?></td>
                            <td><?= e($item['quantity'] ?: '—') ?></td>
                            <td><?= number_format((float)$item['price'], 2) ?></td>
                            <td><span class="stock-status <?= e($statusClass) ?>"><?= e($item['status']) ?></span></td>
                            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><td><div class="table-actions"><a class="edit-button" href="adminInventory.php?edit=<?= (int)$item['inventory_id'] ?>" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a></div></td><?php endif; ?>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
<script>
const categoryButton = document.getElementById('categoryButton');
const categoryDropdown = document.getElementById('categoryDropdown');
categoryButton.addEventListener('click', event => { event.stopPropagation(); categoryDropdown.classList.toggle('show'); });
document.addEventListener('click', () => categoryDropdown.classList.remove('show'));
document.getElementById('inventorySearch').addEventListener('input', function () {
    const value = this.value.toLowerCase().trim();
    document.querySelectorAll('#inventoryTableBody tr[data-searchable]').forEach(row => {
        row.style.display = row.dataset.searchable.toLowerCase().includes(value) ? '' : 'none';
    });
});
</script>
</body>
</html>
