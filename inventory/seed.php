<?php
require_once __DIR__ . "/../staff_access.php";
if (in_array(sanctuario_staff_role(), ["crops staff","livestock staff"], true)) { http_response_code(403); exit("Access denied."); }
?>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../db.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* Stock status is based on seed packs, never entered manually. */
function seedPackCount($pieces) {
    $value = trim((string)$pieces);
    if (!preg_match('/^(\d+)\s*(?:packs?|pcs?|pieces?)?$/i', $value, $match)) {
        throw new InvalidArgumentException('Enter Pieces as a whole number of packs, e.g. 0, 1 Pack, or 2 Packs.');
    }
    return (int)$match[1];
}
function seedStockStatus($pieces, $savedStatus = null) {
    // Existing legacy values may be descriptive (e.g. "1 sachet" or "2 packets").
    // Never let a legacy value prevent the inventory table from loading.
    $value = trim((string)$pieces);
    if (preg_match('/^(\\d+)\\s*(?:packs?|pcs?|pieces?)?$/i', $value, $match)) {
        $packs = (int)$match[1];
    } elseif (preg_match('/^(\\d+)\\b/u', $value, $match)) {
        $packs = (int)$match[1];
    } else {
        // Keep the previously saved status if a numeric pack count cannot be determined.
        return in_array($savedStatus, ['In Stock', 'Low Stock', 'No Stock'], true)
            ? $savedStatus : 'In Stock';
    }
    return $packs === 0 ? 'No Stock' : ($packs === 1 ? 'Low Stock' : 'In Stock');
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
    && in_array($_POST['action'] ?? '', ['add_item', 'update_item', 'archive_item'], true)) {

    $postAction = $_POST['action'];
    $returnMonth = (int)($_POST['month'] ?? 0);
    $redirectUrl = $postAction === 'archive_item'
        ? 'seedsArchived.php'
        : 'seed.php' . (($returnMonth >= 1 && $returnMonth <= 12) ? '?month=' . $returnMonth : '');

    try {
        if (!$isAdmin) {
            throw new Exception('Only admins can add, edit, or archive inventory items.');
        }

        if (!hash_equals($_SESSION['inventory_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
            throw new Exception('Invalid request. Please refresh the page and try again.');
        }

        $inventoryId = (int)($_POST['inventory_id'] ?? 0);
        if ($postAction !== 'add_item' && $inventoryId <= 0) {
            throw new Exception('Invalid inventory item.');
        }

        if ($postAction === 'add_item') {
            $dateReceived = trim((string)($_POST['date_received'] ?? ''));
            $category = trim((string)($_POST['category'] ?? ''));
            $itemName = trim((string)($_POST['item_name'] ?? ''));
            $variety = trim((string)($_POST['variety'] ?? ''));
            $brand = trim((string)($_POST['brand'] ?? ''));
            $pieces = trim((string)($_POST['pieces'] ?? ''));
            $quantity = trim((string)($_POST['quantity'] ?? ''));
            $priceInput = trim((string)($_POST['price'] ?? '0'));
            $status = seedStockStatus($pieces);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateReceived);
            if (!$date || $date->format('Y-m-d') !== $dateReceived) {
                throw new Exception('Please enter a valid date received.');
            }
            if (!in_array($category, ['Seeds'], true)) {
                throw new Exception('Please select a valid category.');
            }
            if ($itemName === '' || mb_strlen($itemName) > 150) {
                throw new Exception('Item name is required (maximum 150 characters).');
            }
            foreach (['Variety' => $variety, 'Brand' => $brand] as $label => $value) {
                if (mb_strlen($value) > 150) throw new Exception($label . ' is too long.');
            }
            foreach (['Pieces' => $pieces, 'Quantity' => $quantity] as $label => $value) {
                if (mb_strlen($value) > 100) throw new Exception($label . ' is too long.');
            }
            if (!in_array($status, ['In Stock', 'Low Stock', 'No Stock'], true)) {
                throw new Exception('Please select a valid stock status.');
            }
            if ($priceInput === '' || !is_numeric($priceInput) || (float)$priceInput < 0 || (float)$priceInput > 99999999.99) {
                throw new Exception('Please enter a valid non-negative price.');
            }
            $price = (float)$priceInput;
            $stmt = $conn->prepare(
                "INSERT INTO inventory_items (date_received, category, item_name, variety, brand, pieces, quantity, price, status, is_archived)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
            );
            if (!$stmt) throw new Exception('Could not prepare the new inventory record.');
            $stmt->bind_param('sssssssds', $dateReceived, $category, $itemName, $variety, $brand, $pieces, $quantity, $price, $status);
            if (!$stmt->execute()) throw new Exception('Could not save the inventory record.');
            $stmt->close();
            $_SESSION['inventory_flash'] = ['type' => 'success', 'text' => 'Inventory item added successfully.'];
        } else if ($postAction === 'update_item') {
            $category = 'Seeds';
            $itemName = trim($_POST['item_name'] ?? '');
            $variety = trim($_POST['variety'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $pieces = trim($_POST['pieces'] ?? '');
            $quantity = trim($_POST['quantity'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $status = seedStockStatus($pieces);

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
$totalSeedPacks = 0;
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
    // Summary cards always reflect ALL active seed inventory, regardless of the
    // Date Received filter applied to the records table below.
    $summaryResult = $conn->query(
        "SELECT pieces, status FROM inventory_items
         WHERE category = 'Seeds' AND is_archived = 0"
    );
    if (!$summaryResult) {
        throw new Exception('Could not load seed inventory summary.');
    }
    while ($summaryItem = $summaryResult->fetch_assoc()) {
        $piecesText = str_replace(',', '', trim((string)($summaryItem['pieces'] ?? '')));
        if (preg_match('/\d+(?:\.\d+)?/', $piecesText, $packMatch)) {
            $totalSeedPacks += (float)$packMatch[0];
        }
        if (seedStockStatus($summaryItem['pieces'], $summaryItem['status']) === 'Low Stock') {
            $lowStockItems++;
        } elseif (seedStockStatus($summaryItem['pieces'], $summaryItem['status']) === 'No Stock') {
            $noStockItems++;
        }
    }
    $summaryResult->free();

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
        $row['status'] = seedStockStatus($row['pieces'], $row['status']);
        $items[] = $row;

        $varietyValue = trim((string)$row['variety']);
        if ($varietyValue !== '') {
            $varietyOptions[$varietyValue] = $varietyValue;
        }
        $brandValue = trim((string)$row['brand']);
        if ($brandValue !== '') {
            $brandOptions[$brandValue] = $brandValue;
        }
    }

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


/* Aligned compact filter row: reset sits alongside the controls. */
.simple-filter-fields{display:grid;grid-template-columns:minmax(0,1.25fr) repeat(4,minmax(0,.85fr)) auto;align-items:end;gap:10px}
.simple-filter-search{grid-column:auto}
.simple-filter-clear{display:flex;align-items:flex-end;min-width:0}
.simple-filter-clear .simple-filter-reset{height:41px;padding:0 13px;width:100%}
@media(max-width:1350px){.simple-filter-fields{grid-template-columns:repeat(2,minmax(0,1fr))}.simple-filter-search{grid-column:auto}.simple-filter-clear .simple-filter-reset{width:auto}}
@media(max-width:600px){.simple-filter-fields{grid-template-columns:1fr}.simple-filter-clear .simple-filter-reset{width:100%}}
</style>
<style id="simple-inventory-filter-design">
/* Scoped, responsive filter redesign for inventory pages. */
.inventory-toolbar .simple-inventory-filters{flex:1 1 100%;width:100%;box-sizing:border-box;padding:0;background:transparent;border:0;border-radius:0;box-shadow:none;font-family:Poppins,sans-serif}
.simple-filter-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
.simple-filter-heading>div{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.simple-filter-heading i{color:#277a49;font-size:16px}.simple-filter-heading strong{font-size:14px;color:#203e2a;font-weight:600}.simple-filter-heading span{font-size:11px;color:#84918a;font-weight:400}
.simple-filter-reset{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:1px solid #d5e1d9;background:#f7faf8;color:#346348;border-radius:9px;padding:9px 12px;font:500 12px Poppins,sans-serif;cursor:pointer;white-space:nowrap;transition:background .2s}
.simple-filter-reset:hover{background:#e9f4ec}
.simple-filter-fields{display:grid;grid-template-columns:minmax(190px,1.6fr) repeat(4,minmax(120px,1fr));gap:12px;align-items:end}
.simple-filter-field{display:flex;flex-direction:column;gap:6px;min-width:0}
.simple-filter-field>span{font-size:11px;font-weight:600;color:#4c6354}
.simple-filter-field select,.simple-search-box{box-sizing:border-box;width:100%;height:41px;border:1px solid #d3dcd7;border-radius:10px;background:#fff;color:#304a38;font:400 12px Poppins,sans-serif;outline:none}
.simple-filter-field select{padding:0 10px;cursor:pointer}.simple-filter-field select:focus,.simple-search-box:focus-within{border-color:#348f55;box-shadow:0 0 0 3px rgba(52,143,85,.10)}
.simple-search-box{display:flex;align-items:center;gap:9px;padding:0 12px}.simple-search-box i{color:#8b9c90;font-size:13px}.simple-search-box input{width:100%;min-width:0;border:0;outline:0;background:transparent;color:#304a38;font:400 12px Poppins,sans-serif}.simple-search-box input::placeholder{color:#9aa7a0}
.simple-filter-footer{display:flex;justify-content:flex-end;margin-top:10px;font-size:11px;color:#7b8c80}
@media(max-width:1350px){.simple-filter-fields{grid-template-columns:repeat(3,minmax(0,1fr))}.simple-filter-search{grid-column:span 2}}
@media(max-width:760px){.inventory-toolbar .simple-inventory-filters{padding:0}.simple-filter-fields{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.simple-filter-search{grid-column:1/-1}.simple-filter-heading span{display:none}}
@media(max-width:440px){.simple-filter-fields{grid-template-columns:1fr}.simple-filter-search{grid-column:auto}.simple-filter-heading{align-items:flex-start}.simple-filter-reset{padding:8px;font-size:11px}}
</style>
<style id="inventory-filter-action-alignment-fix">
/* Filters fill their own row; Clear Filters joins the export/add/archive action row. */
.inventory-toolbar .simple-inventory-filters{flex:1 1 100%;width:100%;}
.inventory-toolbar .simple-filter-clear{display:flex;flex:0 0 auto;align-items:center;min-width:0;margin:0;}
.inventory-toolbar .simple-filter-clear .simple-filter-reset{width:auto;height:36px;min-height:36px;padding:0 13px;white-space:nowrap;}
.inventory-toolbar .simple-filter-fields{grid-template-columns:minmax(0,1.15fr) repeat(4,minmax(0,1fr));}
.inventory-toolbar .simple-filter-search{grid-column:auto;}
@media(max-width:1000px){
 .inventory-toolbar .simple-filter-fields{grid-template-columns:repeat(2,minmax(0,1fr));}
 .inventory-toolbar .simple-filter-search{grid-column:1/-1;}
}
@media(max-width:600px){
 .inventory-toolbar .simple-filter-fields{grid-template-columns:1fr;}
 .inventory-toolbar .simple-filter-search{grid-column:auto;}
 .inventory-toolbar .simple-filter-clear .simple-filter-reset{height:36px;width:auto;}
}
</style>
<style id="inventory-single-line-toolbar-final">
/* Desktop: filter inputs and action buttons share ONE horizontal line. */
@media (min-width: 901px) {
 .inventory-layout-single .inventory-toolbar {display:flex !important;flex-wrap:nowrap !important;align-items:flex-end !important;gap:8px !important;overflow-x:auto;overflow-y:hidden;padding-bottom:8px;}
 .inventory-layout-single .inventory-toolbar .simple-inventory-filters {flex:1 0 640px !important;width:auto !important;min-width:0 !important;}
 .inventory-layout-single .inventory-toolbar .simple-filter-fields {display:grid !important;grid-template-columns: minmax(155px,1.35fr) repeat(4,minmax(105px,1fr)) !important;gap:8px !important;}
 .inventory-layout-single .inventory-toolbar .simple-filter-search {grid-column:auto !important;}
 .inventory-layout-single .inventory-toolbar .toolbar-space {display:none !important;}
 .inventory-layout-single .inventory-toolbar .simple-filter-clear {flex:0 0 auto !important;align-self:flex-end !important;}
 .inventory-layout-single .inventory-toolbar .inventory-export-actions {display:flex !important;flex:0 0 auto !important;flex-wrap:nowrap !important;gap:6px !important;align-self:flex-end !important;}
 .inventory-layout-single .inventory-toolbar .inventory-export-button,
 .inventory-layout-single .inventory-toolbar .add-item-button,
 .inventory-layout-single .inventory-toolbar .archive-items-button,
 .inventory-layout-single .inventory-toolbar .simple-filter-reset {height:41px !important;min-height:41px !important;box-sizing:border-box !important;display:inline-flex !important;align-items:center !important;justify-content:center !important;white-space:nowrap !important;margin:0 !important;padding:0 10px !important;font-size:11px !important;}
 .inventory-layout-single .inventory-toolbar .add-item-button,
 .inventory-layout-single .inventory-toolbar .archive-items-button {flex:0 0 auto !important;align-self:flex-end !important;}
 .inventory-layout-single .inventory-toolbar .simple-filter-footer {display:none !important;}
 .inventory-layout-single .inventory-toolbar .simple-filter-heading {margin-bottom:12px !important;}
}
</style>
<style id="inventory-even-toolbar-spacing">
/* Uniform 8px spacing across filters, Clear Filters, and action buttons. */
@media (min-width:901px) {
 .inventory-layout-single .inventory-toolbar {gap:8px !important;justify-content:flex-start !important;}
 .inventory-layout-single .inventory-toolbar .simple-inventory-filters {flex:0 0 auto !important;width:min(54vw, 810px) !important;min-width:640px !important;margin:0 !important;}
 .inventory-layout-single .inventory-toolbar .simple-filter-fields {grid-template-columns:minmax(190px, 1.35fr) repeat(4, minmax(110px, 1fr)) !important;gap:8px !important;}
 .inventory-layout-single .inventory-toolbar .simple-filter-clear,
 .inventory-layout-single .inventory-toolbar .inventory-export-actions,
 .inventory-layout-single .inventory-toolbar .add-item-button,
 .inventory-layout-single .inventory-toolbar .archive-items-button {margin-left:0 !important;margin-right:0 !important;}
 .inventory-layout-single .inventory-toolbar .inventory-export-actions {gap:8px !important;}
 .inventory-layout-single .inventory-toolbar .simple-filter-clear {margin-left:0 !important;}
}
</style>

<style id="inventory-accessible-toolbar-layout">
/* Filters stay together at left; actions stay together at right, with empty space between. */
.inventory-layout-single .inventory-toolbar {
 display:flex !important; flex-wrap:nowrap !important; align-items:flex-end !important;
 justify-content:space-between !important; column-gap:9px !important;
 overflow-x:auto !important; overflow-y:hidden !important; padding-bottom:12px !important;
}
.inventory-layout-single .inventory-toolbar .simple-inventory-filters {
 flex:0 0 auto !important; width:auto !important; min-width:0 !important; margin:0 !important;
}
.inventory-layout-single .inventory-toolbar .simple-filter-fields {
 display:grid !important; grid-template-columns:185px repeat(4, 125px) !important;
 gap:9px !important; align-items:end !important;
}
.inventory-layout-single .inventory-toolbar .simple-filter-search {grid-column:auto !important;}
.inventory-layout-single .inventory-toolbar .simple-filter-clear {
 flex:0 0 auto !important; align-self:flex-end !important; margin:0 !important;
}
.inventory-layout-single .inventory-toolbar .simple-filter-reset {
 height:39px !important; min-height:39px !important; padding:0 12px !important; font-size:11px !important;
}
.inventory-layout-single .inventory-toolbar .toolbar-space {
 display:block !important; flex:1 1 36px !important; min-width:24px !important;
}
.inventory-layout-single .inventory-toolbar .inventory-export-actions {
 display:flex !important; flex:0 0 auto !important; align-self:flex-end !important;
 gap:8px !important; flex-wrap:nowrap !important; margin:0 !important;
 padding:0 !important; border:0 !important;
}
.inventory-layout-single .inventory-toolbar .inventory-export-button,
.inventory-layout-single .inventory-toolbar .add-item-button,
.inventory-layout-single .inventory-toolbar .archive-items-button {
 height:39px !important; min-height:39px !important; font-size:11px !important;
 padding:0 11px !important; white-space:nowrap !important;
}
.inventory-layout-single .inventory-toolbar .simple-filter-heading {margin-bottom:12px !important;}
.inventory-layout-single .inventory-toolbar .simple-filter-footer {display:none !important;}
@media(max-width:700px) {.inventory-layout-single .inventory-toolbar {scrollbar-width:thin;}}
</style>
<style id="seed-status-button-filter-style">
/* Stock status is a clear, accessible single-choice button group. */
.inventory-layout-single .inventory-toolbar .simple-filter-fields {grid-template-columns:185px 125px 280px 125px 125px !important;}
.seed-status-buttons {display:flex;align-items:center;gap:4px;height:39px;white-space:nowrap;}
.seed-status-btn {height:36px;min-height:36px;box-sizing:border-box;border:1px solid #cfddd1;border-radius:9px;padding:0 9px;background:#fff;color:#31583c;font:500 10px Poppins,sans-serif;cursor:pointer;white-space:nowrap;transition:background .15s,border-color .15s;}
.seed-status-btn:hover {border-color:#3d8051;background:#f0f8f1;}
.seed-status-btn[aria-pressed="true"] {background:#286c3b;border-color:#286c3b;color:#fff;font-weight:600;}
.seed-status-btn:focus-visible {outline:3px solid #a6d9b4;outline-offset:2px;}
@media(max-width:900px){.inventory-layout-single .inventory-toolbar .simple-filter-fields {grid-template-columns:185px 125px 280px 125px 125px !important;}}
</style>

<style id="seed-status-order-palette-fix">
/* Same existing inventory status colors; status buttons follow Brand. */
.inventory-layout-single .inventory-toolbar .simple-filter-fields {grid-template-columns:185px 125px 125px 125px 280px !important;gap:9px !important;}
.seed-status-buttons {gap:5px !important;}
.seed-status-btn[aria-pressed="true"][data-status-value="In Stock"] {background:#cef5df !important;border-color:#329365 !important;color:#329365 !important;}
.seed-status-btn[aria-pressed="true"][data-status-value="Low Stock"] {background:#fff0b9 !important;border-color:#c29b20 !important;color:#8b6b08 !important;}
.seed-status-btn[aria-pressed="true"][data-status-value="No Stock"] {background:#f9d7d7 !important;border-color:#c04c4c !important;color:#c04c4c !important;}
.seed-status-btn[aria-pressed="true"][data-status-value=""] {background:#286c3b !important;border-color:#286c3b !important;color:#fff !important;}
@media(max-width:900px) {.inventory-layout-single .inventory-toolbar .simple-filter-fields {grid-template-columns:185px 125px 125px 125px 280px !important;}}
</style>
<style id="inventory-toolbar-final-spacing-fix">
/* Keep existing palette and layout. Give the full status group its own width. */
.inventory-layout-single .inventory-toolbar .simple-filter-fields {
  grid-template-columns:185px 125px 125px 125px 310px !important;
  gap:9px !important;
}
.inventory-layout-single .inventory-toolbar .seed-status-filter {min-width:0 !important;}
.inventory-layout-single .inventory-toolbar .seed-status-buttons {
  display:flex !important; align-items:center !important; gap:6px !important;
  width:max-content !important; height:39px !important;
}
.inventory-layout-single .inventory-toolbar .seed-status-btn {
  height:39px !important; min-height:39px !important;
  border-radius:10px !important; padding:0 10px !important;
  flex:0 0 auto !important;
}
.inventory-layout-single .inventory-toolbar .simple-filter-clear {
  margin-left:12px !important; flex:0 0 auto !important;
}
.inventory-layout-single .inventory-toolbar .simple-filter-reset {
  height:39px !important; min-height:39px !important;
  border-radius:10px !important; padding:0 12px !important;
  box-sizing:border-box !important;
}
.inventory-layout-single .inventory-toolbar .simple-search-box,
.inventory-layout-single .inventory-toolbar .simple-filter-field select {
  height:39px !important; min-height:39px !important;
  border-radius:10px !important; box-sizing:border-box !important;
}
.inventory-layout-single .inventory-toolbar .inventory-export-button,
.inventory-layout-single .inventory-toolbar .add-item-button,
.inventory-layout-single .inventory-toolbar .archive-items-button {
  height:39px !important; min-height:39px !important;
  border-radius:10px !important; box-sizing:border-box !important;
}
@media(max-width:900px) {
 .inventory-layout-single .inventory-toolbar .simple-filter-fields {grid-template-columns:185px 125px 125px 125px 310px !important;}
}
</style>
<style id="inventory-unified-control-sizing">
/* Unified geometry and typography for all inventory filter/action controls. */
.inventory-layout-single .inventory-toolbar .simple-search-box,
.inventory-layout-single .inventory-toolbar .simple-filter-field select,
.inventory-layout-single .inventory-toolbar .seed-status-btn,
.inventory-layout-single .inventory-toolbar .simple-filter-reset,
.inventory-layout-single .inventory-toolbar .inventory-export-button,
.inventory-layout-single .inventory-toolbar .add-item-button,
.inventory-layout-single .inventory-toolbar .archive-items-button {
  height:40px !important;
  min-height:40px !important;
  border-radius:12px !important;
  box-sizing:border-box !important;
  font-family:Poppins,sans-serif !important;
  font-size:11px !important;
  font-weight:500 !important;
  line-height:1.2 !important;
  white-space:nowrap !important;
}
.inventory-layout-single .inventory-toolbar .seed-status-buttons {height:40px !important;gap:6px !important;}
.inventory-layout-single .inventory-toolbar .seed-status-btn {padding:0 11px !important;}
.inventory-layout-single .inventory-toolbar .simple-filter-reset {padding:0 12px !important;}
.inventory-layout-single .inventory-toolbar .simple-filter-clear {margin-left:10px !important;}
.inventory-layout-single .inventory-toolbar .simple-filter-field select {padding:0 11px !important;}
.inventory-layout-single .inventory-toolbar .simple-search-box input {font-family:Poppins,sans-serif !important;font-size:11px !important;}
.inventory-layout-single .inventory-toolbar .simple-filter-field > span {font-family:Poppins,sans-serif !important;font-size:11px !important;}
</style>
<style id="inventory-pill-corners-final">
/* Match the pill-shaped Crops filter buttons; retain existing colors and sizes. */
.inventory-layout-single .inventory-toolbar .simple-search-box,
.inventory-layout-single .inventory-toolbar .simple-filter-field select,
.inventory-layout-single .inventory-toolbar .seed-status-btn,
.inventory-layout-single .inventory-toolbar .sowing-status-btn,
.inventory-layout-single .inventory-toolbar .simple-filter-reset,
.inventory-layout-single .inventory-toolbar .inventory-export-button,
.inventory-layout-single .inventory-toolbar .add-item-button,
.inventory-layout-single .inventory-toolbar .archive-items-button {
  border-radius:999px !important;
}
</style>
<style id="inventory-mobile-layout-repair">
/* Phone/tablet: cancel fixed desktop grid widths and prevent pill controls becoming circles. */
@media (max-width: 900px) {
  .inventory-layout-single .inventory-toolbar {
    display:flex !important; flex-wrap:wrap !important; align-items:stretch !important;
    justify-content:flex-start !important; overflow:visible !important;
    gap:12px !important; padding-bottom:12px !important;
  }
  .inventory-layout-single .inventory-toolbar .simple-inventory-filters {
    flex:1 1 100% !important; width:100% !important; min-width:0 !important;
  }
  .inventory-layout-single .inventory-toolbar .simple-filter-fields {
    display:grid !important; grid-template-columns:repeat(2,minmax(0,1fr)) !important;
    gap:12px !important; width:100% !important;
  }
  .inventory-layout-single .inventory-toolbar .simple-filter-search {
    grid-column:1 / -1 !important;
  }
  .inventory-layout-single .inventory-toolbar .simple-filter-field {
    width:100% !important; min-width:0 !important;
  }
  .inventory-layout-single .inventory-toolbar .simple-filter-field select,
  .inventory-layout-single .inventory-toolbar .simple-search-box {
    display:flex !important; width:100% !important; height:40px !important;
    min-height:40px !important; min-width:0 !important;
    border-radius:999px !important; aspect-ratio:auto !important;
  }
  .inventory-layout-single .inventory-toolbar .simple-filter-field select {display:block !important;}
  .inventory-layout-single .inventory-toolbar .seed-status-filter,
  .inventory-layout-single .inventory-toolbar .sowing-status-filter {
    grid-column:1 / -1 !important;
  }
  .inventory-layout-single .inventory-toolbar .seed-status-buttons,
  .inventory-layout-single .inventory-toolbar .sowing-status-buttons {
    display:flex !important; flex-wrap:wrap !important; width:100% !important;
    height:auto !important; gap:8px !important; white-space:normal !important;
  }
  .inventory-layout-single .inventory-toolbar .seed-status-btn,
  .inventory-layout-single .inventory-toolbar .sowing-status-btn {
    flex:0 0 auto !important; width:auto !important; height:40px !important;
    min-height:40px !important; padding:0 14px !important;
    border-radius:999px !important; aspect-ratio:auto !important;
  }
  .inventory-layout-single .inventory-toolbar .simple-filter-clear {
    flex:0 0 auto !important; margin:0 !important; align-self:center !important;
  }
  .inventory-layout-single .inventory-toolbar .toolbar-space {display:none !important;}
  .inventory-layout-single .inventory-toolbar .inventory-export-actions {
    display:flex !important; flex-wrap:wrap !important; gap:8px !important;
    align-self:center !important;
  }
  .inventory-layout-single .inventory-toolbar .inventory-export-button,
  .inventory-layout-single .inventory-toolbar .add-item-button,
  .inventory-layout-single .inventory-toolbar .archive-items-button,
  .inventory-layout-single .inventory-toolbar .simple-filter-reset {
    width:auto !important; height:40px !important; min-height:40px !important;
    padding:0 14px !important; border-radius:999px !important;
    white-space:nowrap !important; flex:0 0 auto !important;
  }
}
@media (max-width: 540px) {
  .inventory-layout-single .inventory-toolbar .simple-filter-fields {
    grid-template-columns:minmax(0,1fr) !important; gap:11px !important;
  }
  .inventory-layout-single .inventory-toolbar .simple-filter-search,
  .inventory-layout-single .inventory-toolbar .seed-status-filter,
  .inventory-layout-single .inventory-toolbar .sowing-status-filter {grid-column:1 !important;}
  .inventory-layout-single .inventory-toolbar .simple-filter-clear {
    flex:1 1 100% !important;
  }
  .inventory-layout-single .inventory-toolbar .simple-filter-clear .simple-filter-reset {
    width:100% !important;
  }
  .inventory-layout-single .inventory-toolbar .inventory-export-actions {
    flex:1 1 100% !important;
  }
  .inventory-layout-single .inventory-toolbar .inventory-export-actions > * {
    flex:1 1 auto !important;
  }
  .inventory-layout-single .inventory-toolbar .add-item-button,
  .inventory-layout-single .inventory-toolbar .archive-items-button {
    flex:1 1 auto !important;
  }
}
</style>
<style id="inventory-mobile-select-height-final">
/* Force compact, pill-shaped native dropdowns on phones, overriding older rules. */
@media (max-width: 900px) {
  body .inventory-layout-single .inventory-toolbar .simple-filter-fields > label.simple-filter-field {
    display:flex !important; flex-direction:column !important; align-items:stretch !important;
    justify-content:flex-start !important; height:auto !important; min-height:0 !important;
    max-height:none !important; aspect-ratio:auto !important; padding:0 !important;
  }
  body .inventory-layout-single .inventory-toolbar .simple-filter-fields > label.simple-filter-field > select,
  body .inventory-layout-single .inventory-toolbar .simple-filter-fields select.inventory-filter-select {
    display:block !important; width:100% !important; min-width:0 !important;
    height:40px !important; min-height:40px !important; max-height:40px !important;
    block-size:40px !important; min-block-size:40px !important; max-block-size:40px !important;
    aspect-ratio:auto !important; border-radius:999px !important;
    padding:0 32px 0 14px !important; margin:0 !important;
    line-height:normal !important; box-sizing:border-box !important;
    flex:0 0 40px !important; align-self:stretch !important;
    font:500 11px Poppins,sans-serif !important;
  }
}
</style>
<style id="seed-clear-filter-gap-fix">
/* Keep Clear Filters immediately after the last stock button, not after an oversized grid column. */
@media (min-width: 701px) {
  .inventory-layout-single .inventory-toolbar .simple-filter-fields {
    grid-template-columns: 185px 125px 125px 125px max-content !important;
    column-gap: 9px !important;
  }
  .inventory-layout-single .inventory-toolbar .seed-status-filter {
    width: max-content !important;
  }
  .inventory-layout-single .inventory-toolbar .simple-filter-clear {
    margin-left: 0 !important;
  }
}
</style>
<style>.seed-auto-status{min-height:40px;display:flex;align-items:center;padding:8px 12px;border:1px solid #cfddd1;border-radius:12px;background:#f6faf6;color:#31583c;font:500 11px Poppins,sans-serif;box-sizing:border-box}</style>
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
            <div class="summary-card"><div class="summary-icon summary-green"><i class="fa-solid fa-seedling"></i></div><div class="summary-info"><span>Total Seed Packs</span><strong><?= rtrim(rtrim(number_format($totalSeedPacks, 2, '.', ''), '0'), '.') ?></strong></div></div>
            <div class="summary-card"><div class="summary-icon summary-orange"><i class="fa-solid fa-triangle-exclamation"></i></div><div class="summary-info"><span>Low Stock</span><strong><?= $lowStockItems ?></strong></div></div>
            <div class="summary-card"><div class="summary-icon summary-red"><i class="fa-solid fa-box-open"></i></div><div class="summary-info"><span>No Stock</span><strong><?= $noStockItems ?></strong></div></div>
        </div>
    </section>

    <section class="inventory-layout inventory-layout-single">
        <div class="inventory-main">
            <div class="inventory-toolbar">
                <div class="simple-inventory-filters" aria-label="Inventory record filters">
                    <div class="simple-filter-heading"><div><i class="fa-solid fa-sliders"></i> <strong>Filter Records</strong><span>Find inventory records quickly</span></div></div>
                    <div class="simple-filter-fields">
                        <label class="simple-filter-field simple-filter-search"><span>Search Records</span><div class="simple-search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="inventorySearch" placeholder="Search seed name, variety or brand..." autocomplete="off"></div></label>
                        <label class="simple-filter-field"><span>Date Received</span><select id="inventoryMonth" aria-label="Filter by month received"><option value="0">All Months</option><?php foreach ($months as $monthNumber => $monthName): ?><option value="<?= (int)$monthNumber ?>" <?= $selectedMonth === $monthNumber ? 'selected' : '' ?>><?= e($monthName) ?></option><?php endforeach; ?></select></label>
                        <label class="simple-filter-field"><span>Variety</span><select class="inventory-filter-select" data-filter="variety" aria-label="Filter by variety"><option value="">All Varieties</option><?php foreach ($varietyOptions as $optionLabel): ?><option value="<?= e($optionLabel) ?>"><?= e($optionLabel) ?></option><?php endforeach; ?></select></label>
                        <label class="simple-filter-field"><span>Brand</span><select class="inventory-filter-select" data-filter="brand" aria-label="Filter by brand"><option value="">All Brands</option><?php foreach ($brandOptions as $optionLabel): ?><option value="<?= e($optionLabel) ?>"><?= e($optionLabel) ?></option><?php endforeach; ?></select></label>
                        <div class="simple-filter-field seed-status-filter" role="group" aria-label="Filter by stock status"><span>Stock Status</span><div class="seed-status-buttons" id="seedStatusButtons"><button type="button" class="seed-status-btn" data-status-value="" aria-pressed="<?= $selectedStatus === '' ? 'true' : 'false' ?>">All</button><button type="button" class="seed-status-btn" data-status-value="In Stock" aria-pressed="<?= $selectedStatus === 'In Stock' ? 'true' : 'false' ?>">In Stock</button><button type="button" class="seed-status-btn" data-status-value="Low Stock" aria-pressed="<?= $selectedStatus === 'Low Stock' ? 'true' : 'false' ?>">Low Stock</button><button type="button" class="seed-status-btn" data-status-value="No Stock" aria-pressed="<?= $selectedStatus === 'No Stock' ? 'true' : 'false' ?>">No Stock</button></div></div>
                    </div>
                    <div class="simple-filter-footer"><span id="inventoryFilterCount" aria-live="polite">Showing all records</span></div>
                </div>
                <div class="simple-filter-clear"><button type="button" class="simple-filter-reset" id="resetInventoryFilters"><i class="fa-solid fa-rotate-left"></i> Clear Filters</button></div>
                <div class="toolbar-space" aria-hidden="true"></div>
                <div class="inventory-export-actions">
                    <a class="inventory-export-button" href="export_inventory.php?type=seeds&amp;format=excel&amp;month=<?= (int)$selectedMonth ?>"><i class="fa-solid fa-file-excel"></i> Download Excel</a>
                    <a class="inventory-export-button" href="export_inventory.php?type=seeds&amp;format=pdf&amp;month=<?= (int)$selectedMonth ?>"><i class="fa-solid fa-file-pdf"></i> Download PDF</a>
                </div>

                <?php if ($isAdmin): ?>
                    <button type="button" class="add-item-button" id="openAddInventory"><i class="fa-solid fa-plus"></i> Add Seed</button>
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
<!-- Add inventory modal -->
<div class="inventory-modal-overlay" id="addInventoryModal" role="dialog" aria-modal="true" aria-labelledby="addInventoryTitle">
    <div class="inventory-modal">
        <div class="modal-header">
            <h2 id="addInventoryTitle">Add Seed Inventory</h2>
            <button type="button" class="modal-close" data-close-add aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="add_item">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['inventory_csrf']) ?>">
            <input type="hidden" name="month" value="0">
            <div class="modal-form-grid">
                <div class="form-group"><label for="addDate">Date Received</label><input id="addDate" type="date" name="date_received" value="<?= date('Y-m-d') ?>" required></div>
                <input type="hidden" name="category" value="Seeds">
                <div class="form-group"><label for="addName">Item Name</label><input id="addName" name="item_name" maxlength="150" required></div>
                <div class="form-group"><label for="addVariety">Variety</label><input id="addVariety" name="variety" maxlength="150"></div>
                <div class="form-group"><label for="addBrand">Brand</label><input id="addBrand" name="brand" maxlength="150"></div>
                <div class="form-group"><label for="addPieces">Pieces</label><input id="addPieces" name="pieces" maxlength="100" placeholder="e.g. 2 Packs"></div>
                <div class="form-group"><label for="addQuantity">Quantity / No. of Seeds</label><input id="addQuantity" name="quantity" maxlength="100" placeholder="e.g. 10 Grams"></div>
                <div class="form-group"><label for="addPrice">Price</label><input id="addPrice" type="number" name="price" min="0" max="99999999.99" step="0.01" value="0.00" required></div>
<div class="form-group"><label>Stock Status (Automatic)</label><div class="seed-auto-status" id="addAutoStatus" aria-live="polite">Based on Pieces: 0 = No Stock, 1 = Low Stock, 2+ = In Stock</div></div>
            </div>
            <div class="modal-actions"><button type="button" class="modal-cancel" data-close-add>Cancel</button><button type="submit" class="modal-save">Save Inventory</button></div>
        </form>
    </div>
</div>
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
                    <label>Stock Status (Automatic)</label>
                    <div class="seed-auto-status" id="editAutoStatus" aria-live="polite">Based on Pieces</div>
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
const inventorySearch = document.getElementById('inventorySearch');
const filterSelects = document.querySelectorAll('.inventory-filter-select');
const inventoryRows = document.querySelectorAll('#inventoryTableBody tr[data-searchable]');
const noMatchRow = document.getElementById('noMatchRow');
const inventoryMonth = document.getElementById('inventoryMonth');
const inventoryFilterCount = document.getElementById('inventoryFilterCount');
const resetInventoryFilters = document.getElementById('resetInventoryFilters');
const seedStatusButtons = document.querySelectorAll('#seedStatusButtons .seed-status-btn');
let activeSeedStatus = <?= json_encode($selectedStatus) ?>;
seedStatusButtons.forEach(button => button.addEventListener('click', () => {
    activeSeedStatus = button.dataset.statusValue;
    seedStatusButtons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    applyInventoryFilters();
}));

function applyInventoryFilters() {
    const term = inventorySearch.value.toLowerCase().trim();
    let visibleCount = 0;

    inventoryRows.forEach(row => {
        let match = row.dataset.searchable.toLowerCase().includes(term) && (!activeSeedStatus || row.dataset.status === activeSeedStatus);
        filterSelects.forEach(select => {
            if (match && select.value !== '' && row.dataset[select.dataset.filter] !== select.value) {
                match = false;
            }
        });
        row.style.display = match ? '' : 'none';
        if (match) visibleCount++;
    });

    if (inventoryFilterCount) inventoryFilterCount.textContent = `Showing ${visibleCount} of ${inventoryRows.length} records`;
    if (noMatchRow) {
        noMatchRow.style.display = visibleCount === 0 ? '' : 'none';
    }
}

inventorySearch.addEventListener('input', applyInventoryFilters);
filterSelects.forEach(select => select.addEventListener('change', applyInventoryFilters));
inventoryMonth.addEventListener('change', () => {
    const month = Number(inventoryMonth.value);
    const target = new URL(window.location.href);
    if (month > 0) target.searchParams.set('month', String(month));
    else target.searchParams.delete('month');
    target.searchParams.delete('status');
    window.location.assign(target.toString());
});
resetInventoryFilters.addEventListener('click', () => {
    if (inventoryMonth.value !== '0') {
        window.location.assign(window.location.pathname);
        return;
    }
    inventorySearch.value = '';
    filterSelects.forEach(select => select.value = '');
    activeSeedStatus = '';
    seedStatusButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.statusValue === '')));
    applyInventoryFilters();
});
applyInventoryFilters();

function seedStatusPreview(value) {
    const match = String(value).trim().match(/^(\d+)\s*(?:packs?|pcs?|pieces?)?$/i);
    if (!match) return 'Enter a whole number of packs (e.g. 1 Pack)';
    const packs = Number(match[1]);
    return packs === 0 ? 'No Stock' : packs === 1 ? 'Low Stock' : 'In Stock';
}
[['addPieces','addAutoStatus'],['inventoryPieces','editAutoStatus']].forEach(([fieldId, labelId]) => {
    const field = document.getElementById(fieldId);
    const label = document.getElementById(labelId);
    if (field && label) field.addEventListener('input', () => { label.textContent = seedStatusPreview(field.value); });
});
/* Add inventory modal */
const addInventoryModal = document.getElementById('addInventoryModal');
const addInventoryButton = document.getElementById('openAddInventory');
if (addInventoryModal && addInventoryButton) {
    const closeAddInventory = () => addInventoryModal.classList.remove('show');
    addInventoryButton.addEventListener('click', () => { addInventoryModal.classList.add('show'); document.getElementById('addName').focus(); });
    document.querySelectorAll('[data-close-add]').forEach(button => button.addEventListener('click', closeAddInventory));
    addInventoryModal.addEventListener('click', event => { if (event.target === addInventoryModal) closeAddInventory(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeAddInventory(); });
}
/* Admin only: edit modal + delete (archive). Elements exist only for admins. */
const inventoryModal = document.getElementById('inventoryModal');
if (inventoryModal) {
    const modalFields = {
        item_name: 'inventoryItemName',
        variety: 'inventoryVariety',
        brand: 'inventoryBrand',
        pieces: 'inventoryPieces',
        quantity: 'inventoryQuantity',
        price: 'inventoryPrice'
    };

    const openEditModal = item => {
        document.getElementById('inventoryId').value = item.inventory_id;
        Object.keys(modalFields).forEach(key => {
            document.getElementById(modalFields[key]).value = item[key] ?? '';
        });
        document.getElementById('editAutoStatus').textContent = seedStatusPreview(item.pieces);
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
