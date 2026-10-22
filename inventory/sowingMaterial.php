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

/* Automatic sowing material stock status based on number of pieces. */
function sowingPieceCount($pieces) {
    $value = trim((string)$pieces);
    if (!preg_match('/^(\d+)\s*(?:pcs?|pieces?|packs?|units?)?$/i', $value, $match)) {
        throw new InvalidArgumentException('Enter Pieces as a whole number, e.g. 0, 1 piece, or 15 pieces.');
    }
    return (int)$match[1];
}
function sowingStockStatus($pieces) {
    $count = sowingPieceCount($pieces);
    return $count === 0 ? 'No Stock' : ($count === 1 ? 'Low Stock' : 'In Stock');
}

$message = '';
$error = '';
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

/* Keep dedicated archive storage available even on older installations. */
if ($isAdmin) {
    $conn->query("CREATE TABLE IF NOT EXISTS `archived_sowingMaterial` (
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
        KEY idx_archived_sowingMaterial_original (original_inventory_id),
        KEY idx_archived_sowingMaterial_archived_at (archived_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/* ------------------------------------------------------------
   Admin only: edit (modal on this page) and delete (= archive).
   Deleted records are moved to the dedicated archived_sowingMaterial table
   and displayed in sowingMaterialArchived.php.
------------------------------------------------------------ */
if (empty($_SESSION['inventory_csrf'])) {
    $_SESSION['inventory_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($_POST['action'] ?? '', ['add_item', 'update_item', 'archive_item'], true)) {

    $postAction = $_POST['action'];
    $returnMonth = (int)($_POST['month'] ?? 0);
    $redirectUrl = $postAction === 'archive_item'
        ? 'sowingMaterialArchived.php'
        : 'sowingMaterial.php' . (($returnMonth >= 1 && $returnMonth <= 12) ? '?month=' . $returnMonth : '');

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
            $status = sowingStockStatus($pieces);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateReceived);
            if (!$date || $date->format('Y-m-d') !== $dateReceived) {
                throw new Exception('Please enter a valid date received.');
            }
            if (!in_array($category, ['Fertilizer', 'Tools', 'Feeds', 'Others'], true)) {
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
            $category = ''; // Category is not editable from the sowing material editor.
            $itemName = trim($_POST['item_name'] ?? '');
            $variety = trim($_POST['variety'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $pieces = trim($_POST['pieces'] ?? '');
            $quantity = trim($_POST['quantity'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $status = sowingStockStatus($pieces);

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
                 SET item_name = ?, variety = ?, brand = ?,
                     pieces = ?, quantity = ?, price = ?, status = ?
                 WHERE inventory_id = ?
                   AND category IN ('Fertilizer', 'Tools', 'Feeds', 'Others')
                   AND is_archived = 0"
            );

            if (!$stmt) {
                throw new Exception('Could not prepare item update.');
            }

            $stmt->bind_param('sssssdsi', $itemName, $variety, $brand, $pieces, $quantity, $price, $status, $inventoryId);
            $stmt->execute();
            $stmt->close();

            $_SESSION['inventory_flash'] = ['type' => 'success', 'text' => 'Inventory item updated successfully.'];
        } else {
            /*
             * Move the sowing-material record to the dedicated archive table.
             * The active record is removed only after the archive insert succeeds.
             */
            $conn->begin_transaction();

            try {
                $stmt = $conn->prepare(
                    "INSERT INTO archived_sowingMaterial
                        (original_inventory_id, date_received, category, item_name,
                         variety, brand, pieces, quantity, price, status)
                     SELECT inventory_id, date_received, category, item_name,
                            variety, brand, pieces, quantity, price, status
                     FROM inventory_items
                     WHERE inventory_id = ?
                       AND category IN ('Fertilizer', 'Tools', 'Feeds', 'Others')
                       AND is_archived = 0"
                );

                if (!$stmt) {
                    throw new Exception('Could not prepare the sowing-material archive request.');
                }

                $stmt->bind_param('i', $inventoryId);
                $stmt->execute();
                $archivedRows = $stmt->affected_rows;
                $stmt->close();

                if ($archivedRows < 1) {
                    throw new Exception('That sowing material was not found or is already archived.');
                }

                $stmt = $conn->prepare(
                    "DELETE FROM inventory_items
                     WHERE inventory_id = ?
                       AND category IN ('Fertilizer', 'Tools', 'Feeds', 'Others')
                       AND is_archived = 0"
                );

                if (!$stmt) {
                    throw new Exception('Could not prepare removal of the active sowing-material record.');
                }

                $stmt->bind_param('i', $inventoryId);
                $stmt->execute();
                $deletedRows = $stmt->affected_rows;
                $stmt->close();

                if ($deletedRows < 1) {
                    throw new Exception('The sowing material could not be removed from active inventory.');
                }

                $conn->commit();
                $_SESSION['inventory_flash'] = [
                    'type' => 'success',
                    'text' => 'Sowing material moved to archived sowing materials.'
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
$categoryFilters = ['Fertilizer', 'Tools', 'Feeds', 'Others'];
$statusFilters = ['In Stock', 'Low Stock', 'No Stock'];
$selectedMonth = (int)($_GET['month'] ?? 0);
if ($selectedMonth < 0 || $selectedMonth > 12) {
    $selectedMonth = 0;
}
$monthClause = $selectedMonth > 0
    ? " AND MONTH(date_received) = {$selectedMonth}"
    : '';

$brandOptions = [];

// Summary cards always represent ALL active sowing materials, regardless of the selected month.
try {
    $summaryResult = $conn->query(
        "SELECT COUNT(*) AS total_items,
                COALESCE(SUM(CASE WHEN status = 'Low Stock' THEN 1 ELSE 0 END), 0) AS low_stock_items,
                COALESCE(SUM(CASE WHEN status = 'No Stock' THEN 1 ELSE 0 END), 0) AS no_stock_items
         FROM inventory_items
         WHERE category IN ('Fertilizer', 'Tools', 'Feeds', 'Others')
           AND is_archived = 0"
    );
    if (!$summaryResult) {
        throw new Exception('Could not load sowing material summary.');
    }
    $summary = $summaryResult->fetch_assoc();
    $totalItems = (int)$summary['total_items'];
    $lowStockItems = (int)$summary['low_stock_items'];
    $noStockItems = (int)$summary['no_stock_items'];
} catch (Throwable $ex) {
    $error = $ex->getMessage();
}

try {
    $result = $conn->query(
        "SELECT inventory_id, date_received, category, item_name, variety,
                brand, pieces, quantity, price, status
         FROM inventory_items
         WHERE category IN ('Fertilizer', 'Tools', 'Feeds', 'Others')
           AND is_archived = 0
           {$monthClause}
         ORDER BY inventory_id DESC"
    );

    if (!$result) {
        throw new Exception('Could not load sowing material inventory.');
    }

    while ($row = $result->fetch_assoc()) {
        $items[] = $row;

        $brandValue = trim((string)$row['brand']);
        if ($brandValue !== '') {
            $brandOptions[$brandValue] = $brandValue;
        }
    }

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
    <title>Sowing Material Inventory | Sanctuario Nature Farm</title>
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
.simple-filter-fields{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr) minmax(0,.9fr) auto;align-items:end;gap:10px}
.simple-filter-search{grid-column:auto}
.simple-filter-clear{display:flex;align-items:flex-end;min-width:0}
.simple-filter-clear .simple-filter-reset{height:41px;padding:0 13px;width:100%}
@media(max-width:1100px){.simple-filter-fields{grid-template-columns:repeat(2,minmax(0,1fr))}.simple-filter-search{grid-column:auto}.simple-filter-clear .simple-filter-reset{width:auto}}
@media(max-width:600px){.simple-filter-fields{grid-template-columns:1fr}.simple-filter-clear .simple-filter-reset{width:100%}}
</style>
<style>
/* Only sowing material table: five record columns plus admin action controls. */
.inventory-layout-single .sowing-material-table { min-width: 690px; table-layout: fixed; }
.inventory-layout-single .sowing-material-table th,
.inventory-layout-single .sowing-material-table td { width: auto; }
.inventory-layout-single .sowing-material-table th:nth-child(1) { width: 17%; }
.inventory-layout-single .sowing-material-table th:nth-child(2) { width: 31%; }
.inventory-layout-single .sowing-material-table th:nth-child(3) { width: 16%; }
.inventory-layout-single .sowing-material-table th:nth-child(4) { width: 17%; }
.inventory-layout-single .sowing-material-table th:nth-child(5) { width: 12%; }
.inventory-layout-single .sowing-material-table.has-actions th:last-child { width: 112px; }
</style>
<style id="simple-inventory-filter-design">
/* Scoped, responsive filter redesign for inventory pages. */
.inventory-toolbar .simple-inventory-filters{flex:1 1 100%;width:100%;box-sizing:border-box;padding:0;background:transparent;border:0;border-radius:0;box-shadow:none;font-family:Poppins,sans-serif}
.simple-filter-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
.simple-filter-heading>div{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.simple-filter-heading i{color:#277a49;font-size:16px}.simple-filter-heading strong{font-size:14px;color:#203e2a;font-weight:600}.simple-filter-heading span{font-size:11px;color:#84918a;font-weight:400}
.simple-filter-reset{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:1px solid #d5e1d9;background:#f7faf8;color:#346348;border-radius:9px;padding:9px 12px;font:500 12px Poppins,sans-serif;cursor:pointer;white-space:nowrap;transition:background .2s}
.simple-filter-reset:hover{background:#e9f4ec}
.simple-filter-fields{display:grid;grid-template-columns:minmax(190px,1.3fr) repeat(2,minmax(140px,1fr));gap:12px;align-items:end}
.simple-filter-field{display:flex;flex-direction:column;gap:6px;min-width:0}
.simple-filter-field>span{font-size:11px;font-weight:600;color:#4c6354}
.simple-filter-field select,.simple-search-box{box-sizing:border-box;width:100%;height:41px;border:1px solid #d3dcd7;border-radius:10px;background:#fff;color:#304a38;font:400 12px Poppins,sans-serif;outline:none}
.simple-filter-field select{padding:0 10px;cursor:pointer}.simple-filter-field select:focus,.simple-search-box:focus-within{border-color:#348f55;box-shadow:0 0 0 3px rgba(52,143,85,.10)}
.simple-search-box{display:flex;align-items:center;gap:9px;padding:0 12px}.simple-search-box i{color:#8b9c90;font-size:13px}.simple-search-box input{width:100%;min-width:0;border:0;outline:0;background:transparent;color:#304a38;font:400 12px Poppins,sans-serif}.simple-search-box input::placeholder{color:#9aa7a0}
.simple-filter-footer{display:flex;justify-content:flex-end;margin-top:10px;font-size:11px;color:#7b8c80}
@media(max-width:1350px){.simple-filter-fields{grid-template-columns:repeat(3,minmax(0,1fr))}.simple-filter-search{grid-column:span 2}}
@media(max-width:760px){.inventory-toolbar .simple-inventory-filters{padding:0}.simple-filter-fields{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.simple-filter-search{grid-column:1/-1}.simple-filter-heading span{display:none}}
@media(max-width:440px){.simple-filter-fields{grid-template-columns:1fr}.simple-filter-search{grid-column:auto}.simple-filter-heading{align-items:flex-start}.simple-filter-reset{padding:8px;font-size:11px}}

.inventory-layout-single .sowing-material-table td:nth-child(5){white-space:nowrap}
</style>
<style id="inventory-filter-action-alignment-fix">
/* Filters fill their own row; Clear Filters joins the export/add/archive action row. */
.inventory-toolbar .simple-inventory-filters{flex:1 1 100%;width:100%;}
.inventory-toolbar .simple-filter-clear{display:flex;flex:0 0 auto;align-items:center;min-width:0;margin:0;}
.inventory-toolbar .simple-filter-clear .simple-filter-reset{width:auto;height:36px;min-height:36px;padding:0 13px;white-space:nowrap;}
.inventory-toolbar .simple-filter-fields{grid-template-columns:minmax(0,1.15fr) repeat(2,minmax(0,1fr));}
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
 .inventory-layout-single .inventory-toolbar .simple-inventory-filters {flex:1 0 470px !important;width:auto !important;min-width:0 !important;}
 .inventory-layout-single .inventory-toolbar .simple-filter-fields {display:grid !important;grid-template-columns: minmax(170px,1.25fr) repeat(2,minmax(125px,1fr)) !important;gap:8px !important;}
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
 .inventory-layout-single .inventory-toolbar .simple-inventory-filters {flex:0 0 auto !important;width:min(53vw, 790px) !important;min-width:580px !important;margin:0 !important;}
 .inventory-layout-single .inventory-toolbar .simple-filter-fields {grid-template-columns:minmax(230px, 1.4fr) minmax(180px, 1.1fr) minmax(135px, .7fr) !important;gap:8px !important;}
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
 display:grid !important; grid-template-columns:240px 185px 135px !important;
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
<style id="sowing-status-button-filter-style">
/* Same accessible status buttons as Seed Inventory. */
.inventory-layout-single .inventory-toolbar .simple-filter-fields {grid-template-columns:175px 125px 125px 280px !important;gap:8px !important;}
.sowing-status-buttons {display:flex;align-items:center;gap:4px;height:39px;white-space:nowrap;}
.sowing-status-btn {height:36px;min-height:36px;box-sizing:border-box;border:1px solid #cfddd1;border-radius:9px;padding:0 9px;background:#fff;color:#31583c;font:500 10px Poppins,sans-serif;cursor:pointer;white-space:nowrap;transition:background .15s,border-color .15s;}
.sowing-status-btn:hover {border-color:#3d8051;background:#f0f8f1;}
.sowing-status-btn[aria-pressed="true"] {background:#286c3b;border-color:#286c3b;color:#fff;font-weight:600;}
.sowing-status-btn:focus-visible {outline:3px solid #a6d9b4;outline-offset:2px;}
@media(max-width:900px){.inventory-layout-single .inventory-toolbar .simple-filter-fields {grid-template-columns:175px 125px 125px 280px !important;}}
</style>

<style id="sowing-status-order-palette-fix">
/* Same existing inventory status colors; status buttons follow Brand. */
.inventory-layout-single .inventory-toolbar .simple-filter-fields {grid-template-columns:175px 125px 125px 280px !important;gap:9px !important;}
.sowing-status-buttons {gap:5px !important;}
.sowing-status-btn[aria-pressed="true"][data-status-value="In Stock"] {background:#cef5df !important;border-color:#329365 !important;color:#329365 !important;}
.sowing-status-btn[aria-pressed="true"][data-status-value="Low Stock"] {background:#fff0b9 !important;border-color:#c29b20 !important;color:#8b6b08 !important;}
.sowing-status-btn[aria-pressed="true"][data-status-value="No Stock"] {background:#f9d7d7 !important;border-color:#c04c4c !important;color:#c04c4c !important;}
.sowing-status-btn[aria-pressed="true"][data-status-value=""] {background:#286c3b !important;border-color:#286c3b !important;color:#fff !important;}
@media(max-width:900px) {.inventory-layout-single .inventory-toolbar .simple-filter-fields {grid-template-columns:175px 125px 125px 280px !important;}}
</style>
<style id="inventory-toolbar-final-spacing-fix">
/* Keep existing palette and layout. Give the full status group its own width. */
.inventory-layout-single .inventory-toolbar .simple-filter-fields {
  grid-template-columns:175px 125px 125px 310px !important;
  gap:9px !important;
}
.inventory-layout-single .inventory-toolbar .sowing-status-filter {min-width:0 !important;}
.inventory-layout-single .inventory-toolbar .sowing-status-buttons {
  display:flex !important; align-items:center !important; gap:6px !important;
  width:max-content !important; height:39px !important;
}
.inventory-layout-single .inventory-toolbar .sowing-status-btn {
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
 .inventory-layout-single .inventory-toolbar .simple-filter-fields {grid-template-columns:175px 125px 125px 310px !important;}
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
<style>.sowing-auto-status{min-height:40px;display:flex;align-items:center;padding:8px 12px;border:1px solid #cfddd1;border-radius:12px;background:#f6faf6;color:#31583c;font:500 11px Poppins,sans-serif;box-sizing:border-box}</style>
</head>
<body>
<?php include '../sidebar.php'; ?>

<main class="inventory-page">
    <?php include '../topRightBar.php'; ?>

    <section class="inventory-summary">
        <div class="inventory-header">
            <h1>SOWING MATERIAL</h1>
        </div>
        <div class="summary-message">here's what's happening on farm today...</div>
        <?php if ($message): ?><div class="inventory-page-alert success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="inventory-page-alert error"><?= e($error) ?></div><?php endif; ?>

        <div class="summary-cards">
            <div class="summary-card"><div class="summary-icon summary-green"><i class="fa-solid fa-boxes-stacked"></i></div><div class="summary-info"><span>Total Materials</span><strong><?= $totalItems ?></strong></div></div>
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
                        <label class="simple-filter-field simple-filter-search"><span>Search Records</span><div class="simple-search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="inventorySearch" placeholder="Search material name or brand..." autocomplete="off"></div></label>
                        <label class="simple-filter-field"><span>Date Received</span><select id="inventoryMonth" aria-label="Filter by month received"><option value="0">All Months</option><?php foreach ($months as $monthNumber => $monthName): ?><option value="<?= (int)$monthNumber ?>" <?= $selectedMonth === $monthNumber ? 'selected' : '' ?>><?= e($monthName) ?></option><?php endforeach; ?></select></label>
                        <label class="simple-filter-field"><span>Brand</span><select class="inventory-filter-select" data-filter="brand" aria-label="Filter by brand"><option value="">All Brands</option><?php foreach ($brandOptions as $optionLabel): ?><option value="<?= e($optionLabel) ?>"><?= e($optionLabel) ?></option><?php endforeach; ?></select></label>
                        <div class="simple-filter-field sowing-status-filter" role="group" aria-label="Filter by stock status"><span>Stock Status</span><div class="sowing-status-buttons" id="sowingStatusButtons"><button type="button" class="sowing-status-btn" data-status-value="" aria-pressed="true">All</button><button type="button" class="sowing-status-btn" data-status-value="In Stock" aria-pressed="false">In Stock</button><button type="button" class="sowing-status-btn" data-status-value="Low Stock" aria-pressed="false">Low Stock</button><button type="button" class="sowing-status-btn" data-status-value="No Stock" aria-pressed="false">No Stock</button></div></div>
                    </div>
                    <div class="simple-filter-footer"><span id="inventoryFilterCount" aria-live="polite">Showing all records</span></div>
                </div>
                <div class="simple-filter-clear"><button type="button" class="simple-filter-reset" id="resetInventoryFilters"><i class="fa-solid fa-rotate-left"></i> Clear Filters</button></div>
                <div class="toolbar-space" aria-hidden="true"></div>
                <div class="inventory-export-actions">
                    <a class="inventory-export-button" href="export_inventory.php?type=sowing&amp;format=excel&amp;month=<?= (int)$selectedMonth ?>"><i class="fa-solid fa-file-excel"></i> Download Excel</a>
                    <a class="inventory-export-button" href="export_inventory.php?type=sowing&amp;format=pdf&amp;month=<?= (int)$selectedMonth ?>"><i class="fa-solid fa-file-pdf"></i> Download PDF</a>
                </div>

                <?php if ($isAdmin): ?>
                    <button type="button" class="add-item-button" id="openAddInventory"><i class="fa-solid fa-plus"></i> Add Sowing Material</button>
                    <a class="archive-items-button" href="sowingMaterialArchived.php">View Archived Items <i class="fa-solid fa-chevron-right"></i></a>
                    
                <?php endif; ?>
            </div>

            <div class="inventory-table-wrapper">
                <table class="inventory-table sowing-material-table<?= $isAdmin ? ' has-actions' : '' ?>" id="inventoryTable">
                    <thead><tr><th class="row-number-col">#</th><th>DATE RECEIVED</th><th>NAME OF MATERIALS</th><th>PIECES</th><th>BRAND</th><th>PRICE</th><th>STATUS</th><?php if ($isAdmin): ?><th>ACTIONS</th><?php endif; ?></tr></thead>
                    <tbody id="inventoryTableBody">
                    <?php if (!$items): ?>
                        <tr><td colspan="<?= $isAdmin ? 8 : 7 ?>" class="inventory-empty">No sowing materials yet.</td></tr>
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
                            data-category="<?= e($item['category']) ?>"
                            data-status="<?= e($item['status']) ?>"
                            data-brand="<?= e(trim((string)$item['brand'])) ?>">
                            <td class="row-number"><?= $inventoryRowNumber++ ?></td>
                            <td><?= e(date('m/d/Y', strtotime($item['date_received']))) ?></td>
                            <td class="item-name"><?= e($item['item_name']) ?></td>
                            <td><?= e($item['pieces'] ?: '—') ?></td>
                            <td><?= e($item['brand'] ?: '—') ?></td>
                            <td><?= number_format((float)$item['price'], 2) ?></td>
                            <td><span class="stock-status <?= e($statusClass) ?>"><?= e($item['status']) ?></span></td>
                            <?php if ($isAdmin): ?><td><div class="table-actions"><button type="button" class="edit-button" data-item="<?= e($editPayload) ?>" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button><button type="button" class="delete-button" data-id="<?= (int)$item['inventory_id'] ?>" data-name="<?= e($item['item_name']) ?>" title="Delete"><i class="fa-solid fa-trash"></i></button></div></td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                        <tr id="noMatchRow" style="display:none;"><td colspan="<?= $isAdmin ? 8 : 7 ?>" class="inventory-empty">No sowing materials match the current filters.</td></tr>
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
            <h2 id="addInventoryTitle">Add Sowing Material Inventory</h2>
            <button type="button" class="modal-close" data-close-add aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="add_item">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['inventory_csrf']) ?>">
            <input type="hidden" name="month" value="0">
            <div class="modal-form-grid">
                <div class="form-group"><label for="addDate">Date Received</label><input id="addDate" type="date" name="date_received" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label for="addCategory">Category</label><select id="addCategory" name="category" required><option value="">Select Category</option><option>Fertilizer</option><option>Tools</option><option>Feeds</option><option>Others</option></select></div>
                <div class="form-group"><label for="addName">Item Name</label><input id="addName" name="item_name" maxlength="150" required></div>
                <div class="form-group"><label for="addVariety">Variety</label><input id="addVariety" name="variety" maxlength="150"></div>
                <div class="form-group"><label for="addBrand">Brand</label><input id="addBrand" name="brand" maxlength="150"></div>
                <div class="form-group"><label for="addPieces">Pieces</label><input id="addPieces" name="pieces" maxlength="100" placeholder="e.g. 2 Packs"></div>
                <div class="form-group"><label for="addQuantity">Quantity / No. of Seeds</label><input id="addQuantity" name="quantity" maxlength="100" placeholder="e.g. 10 Grams"></div>
                <div class="form-group"><label for="addPrice">Price</label><input id="addPrice" type="number" name="price" min="0" max="99999999.99" step="0.01" value="0.00" required></div>
                <div class="form-group"><label>Stock Status (Automatic)</label><div class="sowing-auto-status" id="addAutoStatus" aria-live="polite">Enter Pieces to preview stock status</div></div>
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
                <div class="form-group"><label>Stock Status (Automatic)</label><div class="sowing-auto-status" id="editAutoStatus" aria-live="polite">Based on Pieces</div></div>
            </div>

            <div class="modal-actions">
                <button type="button" class="modal-cancel" data-close-modal>Cancel</button>
                <button type="submit" class="modal-save">Update Item</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete = move to archived sowing materials -->
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
const sowingStatusButtons = document.querySelectorAll('#sowingStatusButtons .sowing-status-btn');
let activeSowingStatus = '';
sowingStatusButtons.forEach(button => button.addEventListener('click', () => {
    activeSowingStatus = button.dataset.statusValue;
    sowingStatusButtons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    applyInventoryFilters();
}));

function applyInventoryFilters() {
    const term = inventorySearch.value.toLowerCase().trim();
    let visibleCount = 0;

    inventoryRows.forEach(row => {
        let match = row.dataset.searchable.toLowerCase().includes(term) && (!activeSowingStatus || row.dataset.status === activeSowingStatus);
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
    activeSowingStatus = '';
    sowingStatusButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.statusValue === '')));
    applyInventoryFilters();
});
applyInventoryFilters();

function sowingStatusPreview(value) {
    const match = String(value).trim().match(/^(\d+)\s*(?:pcs?|pieces?|packs?|units?)?$/i);
    if (!match) return 'Enter a whole number of pieces (e.g. 1 piece)';
    const count = Number(match[1]);
    return count === 0 ? 'No Stock' : count === 1 ? 'Low Stock' : 'In Stock';
}
[['addPieces','addAutoStatus'],['inventoryPieces','editAutoStatus']].forEach(([fieldId, labelId]) => {
    const field = document.getElementById(fieldId);
    const label = document.getElementById(labelId);
    if (field && label) field.addEventListener('input', () => { label.textContent = sowingStatusPreview(field.value); });
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
        document.getElementById('editAutoStatus').textContent = sowingStatusPreview(item.pieces);
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
