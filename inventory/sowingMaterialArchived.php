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

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$message = '';
$error = '';

if (!$isAdmin) {
    http_response_code(403);
    $error = 'Only admins can view archived sowingMaterials.';
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['inventory_csrf'])) {
        $_SESSION['inventory_csrf'] = bin2hex(random_bytes(32));
    }

    if (!hash_equals($_SESSION['inventory_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } else {
        $archiveId = (int)($_POST['archive_id'] ?? 0);
        $action = $_POST['action'] ?? '';

        if ($archiveId <= 0) {
            $error = 'Invalid archived sowingMaterial.';
        } elseif ($action === 'restore_item') {
            try {
                $conn->begin_transaction();

                $stmt = $conn->prepare(
                    "SELECT original_inventory_id, date_received, category, item_name,
                            variety, brand, pieces, quantity, price, status
                     FROM archived_sowingMaterial
                     WHERE archive_id = ?"
                );
                if (!$stmt) {
                    throw new Exception('Could not prepare the restore request.');
                }

                $stmt->bind_param('i', $archiveId);
                $stmt->execute();
                $result = $stmt->get_result();
                $archived = $result->fetch_assoc();
                $stmt->close();

                if (!$archived) {
                    throw new Exception('Archived sowingMaterial not found.');
                }

                $stmt = $conn->prepare(
                    "SELECT inventory_id
                     FROM inventory_items
                     WHERE inventory_id = ?
                     LIMIT 1"
                );
                if (!$stmt) {
                    throw new Exception('Could not check the active inventory.');
                }

                $originalId = (int)$archived['original_inventory_id'];
                $stmt->bind_param('i', $originalId);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($existing) {
                    $stmt = $conn->prepare(
                        "INSERT INTO inventory_items
                            (date_received, category, item_name, variety, brand,
                             pieces, quantity, price, status, is_archived)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
                    );
                    if (!$stmt) {
                        throw new Exception('Could not prepare the sowingMaterial restore.');
                    }
                    $stmt->bind_param(
                        'sssssssds',
                        $archived['date_received'], $archived['category'], $archived['item_name'],
                        $archived['variety'], $archived['brand'], $archived['pieces'],
                        $archived['quantity'], $archived['price'], $archived['status']
                    );
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO inventory_items
                            (inventory_id, date_received, category, item_name, variety, brand,
                             pieces, quantity, price, status, is_archived)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
                    );
                    if (!$stmt) {
                        throw new Exception('Could not prepare the sowingMaterial restore.');
                    }
                    $stmt->bind_param(
                        'issssssds',
                        $originalId, $archived['date_received'], $archived['category'],
                        $archived['item_name'], $archived['variety'], $archived['brand'],
                        $archived['pieces'], $archived['quantity'], $archived['price'],
                        $archived['status']
                    );
                }

                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM archived_sowingMaterial WHERE archive_id = ?");
                if (!$stmt) {
                    throw new Exception('Could not finish the sowingMaterial restore.');
                }
                $stmt->bind_param('i', $archiveId);
                $stmt->execute();
                if ($stmt->affected_rows < 1) {
                    $stmt->close();
                    throw new Exception('The archived sowingMaterial could not be removed.');
                }
                $stmt->close();

                $conn->commit();
                $message = 'Sowing Material restored to active inventory.';
            } catch (Throwable $ex) {
                $conn->rollback();
                $error = $ex->getMessage();
            }
        } elseif ($action === 'delete_permanently') {
            try {
                $stmt = $conn->prepare("DELETE FROM archived_sowingMaterial WHERE archive_id = ?");
                if (!$stmt) {
                    throw new Exception('Could not prepare the permanent delete request.');
                }
                $stmt->bind_param('i', $archiveId);
                $stmt->execute();
                if ($stmt->affected_rows < 1) {
                    throw new Exception('Archived sowingMaterial not found.');
                }
                $stmt->close();
                $message = 'Archived sowingMaterial permanently deleted.';
            } catch (Throwable $ex) {
                $error = $ex->getMessage();
            }
        }
    }
}

$items = [];
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$selectedMonth = (int)($_GET['month'] ?? 0);
if ($selectedMonth < 0 || $selectedMonth > 12) {
    $selectedMonth = 0;
}
$monthClause = $selectedMonth > 0 ? " WHERE MONTH(date_received) = {$selectedMonth}" : '';

if ($isAdmin) {
    try {
        $result = $conn->query(
            "SELECT archive_id, original_inventory_id, date_received, category,
                    item_name, variety, brand, pieces, quantity, price, status, archived_at
             FROM archived_sowingMaterial
             {$monthClause}
             ORDER BY archived_at DESC, archive_id DESC"
        );
        if (!$result) {
            throw new Exception('Could not load archived sowingMaterials.');
        }
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

if (empty($_SESSION['inventory_csrf'])) {
    $_SESSION['inventory_csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Sowing Materials | Sanctuario Nature Farm</title>
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
            <h1>ARCHIVED SOWING MATERIAL</h1>
        </div>
        <div class="summary-message">archived sowingMaterial inventory records...</div>

        <?php if ($message): ?><div class="inventory-page-alert success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="inventory-page-alert error"><?= e($error) ?></div><?php endif; ?>
    </section>

    <?php if ($isAdmin): ?>
    <section class="inventory-layout inventory-layout-single">
        <div class="inventory-main">
            <div class="inventory-toolbar">
                <div class="inventory-category">
                    <button type="button" class="category-button" id="categoryButton">
                        <span><?= e($selectedMonth > 0 ? $months[$selectedMonth] : 'All Months') ?></span>
                        <i class="fa-solid fa-caret-down"></i>
                    </button>
                    <div class="category-dropdown" id="categoryDropdown">
                        <button type="button" onclick="location.href='sowingMaterialsArchived.php'">All Months</button>
                        <?php foreach ($months as $monthNumber => $monthName): ?>
                            <button type="button" onclick="location.href='sowingMaterialsArchived.php?month=<?= $monthNumber ?>'"><?= e($monthName) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="inventory-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="inventorySearch" placeholder="Search here...">
                </div>

                <div class="toolbar-space"></div>
                <a class="archive-items-button" href="sowingMaterial.php">
                    Back to Sowing Material Inventory <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>

            <div class="inventory-table-wrapper">
                <table class="inventory-table has-actions" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>DATE RECEIVED</th><th>SOWING MATERIAL</th><th>VARIETY</th><th>BRAND</th>
                            <th>PIECES</th><th>NO. OF SOWING MATERIAL</th><th>PRICE</th><th>STATUS</th>
                            <th>ARCHIVED DATE</th><th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody">
                    <?php if (!$items): ?>
                        <tr><td colspan="10" class="inventory-empty">No archived sowingMaterial inventory items yet.</td></tr>
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
                            <td><?= e(date('m/d/Y H:i', strtotime($item['archived_at']))) ?></td>
                            <td>
                                <div class="table-actions">
                                    <form method="post" onsubmit="return confirm('Restore &quot;<?= e($item['item_name']) ?>&quot; to active sowingMaterial inventory?');">
                                        <input type="hidden" name="action" value="restore_item">
                                        <input type="hidden" name="archive_id" value="<?= (int)$item['archive_id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['inventory_csrf']) ?>">
                                        <button type="submit" class="edit-button" title="Restore"><i class="fa-solid fa-rotate-left"></i></button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Permanently delete &quot;<?= e($item['item_name']) ?>&quot; from the archive? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_permanently">
                                        <input type="hidden" name="archive_id" value="<?= (int)$item['archive_id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['inventory_csrf']) ?>">
                                        <button type="submit" class="delete-button" title="Delete Permanently"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                        <tr id="noMatchRow" style="display:none;"><td colspan="10" class="inventory-empty">No archived sowingMaterial items match the search.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>

<script>
const categoryButton = document.getElementById('categoryButton');
const categoryDropdown = document.getElementById('categoryDropdown');
if (categoryButton && categoryDropdown) {
    categoryButton.addEventListener('click', event => {
        event.stopPropagation();
        categoryDropdown.classList.toggle('show');
    });
    document.addEventListener('click', () => categoryDropdown.classList.remove('show'));
}

const inventorySearch = document.getElementById('inventorySearch');
const inventoryRows = document.querySelectorAll('#inventoryTableBody tr[data-searchable]');
const noMatchRow = document.getElementById('noMatchRow');

if (inventorySearch) {
    inventorySearch.addEventListener('input', () => {
        const term = inventorySearch.value.toLowerCase().trim();
        let visibleCount = 0;
        inventoryRows.forEach(row => {
            const match = row.dataset.searchable.toLowerCase().includes(term);
            row.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });
        if (noMatchRow) noMatchRow.style.display = visibleCount === 0 ? '' : 'none';
    });
}
</script>
</body>
</html>
