<?php
require_once __DIR__ . "/../staff_access.php";
if (in_array(sanctuario_staff_role(), ["crops staff","livestock staff"], true)) { http_response_code(403); exit("Access denied."); }
?>
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../adminDashboard.php");
    exit();
}

require_once '../db.php';
require_once '../archive/archive_functions.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

/* Restore an archived item */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $action = $_POST['action'] ?? '';

        if ($action === 'restore_item') {

            $inventoryId =
                (int)($_POST['inventory_id'] ?? 0);

            if ($inventoryId <= 0) {
                throw new Exception('Invalid inventory item.');
            }

            restoreRecord($conn, 'inventory_items', 'inventory_id', $inventoryId);

            $message =
                'Inventory item restored successfully.';
        }


        if ($action === 'delete_permanently') {

            $inventoryId =
                (int)($_POST['inventory_id'] ?? 0);

            if ($inventoryId <= 0) {
                throw new Exception('Invalid inventory item.');
            }

            permanentlyDeleteArchived($conn, 'inventory_items', 'inventory_id', $inventoryId);

            $message =
                'Archived inventory item permanently deleted.';
        }

    } catch (Throwable $ex) {

        $error = $ex->getMessage();
    }
}


/* Load archived items */
$items = [];

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
        FROM archived_inventory_items
        ORDER BY inventory_id DESC
    ");

    if (!$result) {
        throw new Exception(
            'Could not load archived items: '
            . $conn->error
        );
    }

    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

} catch (Throwable $ex) {

    $error =
        $error
        ?: 'Run inventory_tables.sql first.';
}

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
    Archived Inventory | Sanctuario Nature Farm
</title>

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
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

<link
    rel="stylesheet"
    href="inventory.css?v=20261016-table-grid"
>

<style>

.archived-page {

    margin-left: 265px;

    min-height: 100vh;

    padding: 20px 28px 35px;

}

.archived-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 18px;

}

.archived-header h1 {

    color: #386638;

    font-size: 25px;

}

.archived-back {

    height: 32px;

    padding: 0 15px;

    border: none;

    border-radius: 18px;

    background: #326735;

    color: white;

    text-decoration: none;

    display: flex;

    align-items: center;

    gap: 7px;

    font-size: 13px;

}

.archived-box {

    background: #fff;

    border-radius: 9px;

    max-width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;

}

.archived-alert {

    padding: 11px 14px;

    margin-bottom: 14px;

    border-radius: 8px;

    font-size: 12px;

}

.archived-alert.success {

    background: #e1f4e7;

    color: #2f7b4b;

}

.archived-alert.error {

    background: #f9dddd;

    color: #b34343;

}

.archived-actions {

    display: flex;

    justify-content: center;

    gap: 8px;

}

.archived-actions form {

    margin: 0;

}

.restore-button,
.permanent-delete-button {

    border: none;

    border-radius: 8px;

    padding: 7px 10px;

    font-size: 11px;

    cursor: pointer;

}

.restore-button {

    background: #cef4dd;

    color: #379563;

}

.permanent-delete-button {

    background: #f5d1d1;

    color: #c54d4d;

}

.archived-empty {

    padding: 30px;

    text-align: center;

    color: #8b8b8b;

    font-size: 13px;

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

<main class="archived-page">

<?php include '../topRightBar.php'; ?>

<div class="archived-header">

    <h1>
        ARCHIVED INVENTORY
    </h1>

    <a
        href="adminInventory.php"
        class="archived-back"
    >
        <i class="fa-solid fa-arrow-left"></i>
        Back to Inventory
    </a>

</div>

<?php if ($message): ?>

    <div class="archived-alert success">
        <?= e($message) ?>
    </div>

<?php endif; ?>

<?php if ($error): ?>

    <div class="archived-alert error">
        <?= e($error) ?>
    </div>

<?php endif; ?>


<div class="archived-box">

<table class="inventory-table">

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

<tbody>

<?php if (!$items): ?>

<tr>

    <td
        colspan="9"
        class="archived-empty"
    >
        No archived inventory items.
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

<tr>

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
            $item['variety']
            ?: '—'
        ) ?>
    </td>

    <td>
        <?= e(
            $item['brand']
            ?: '—'
        ) ?>
    </td>

    <td>
        <?= e(
            $item['pieces']
            ?: '—'
        ) ?>
    </td>

    <td>
        <?= e(
            $item['quantity']
            ?: '—'
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
            class="stock-status <?= e($statusClass) ?>"
        >
            <?= e($item['status']) ?>
        </span>

    </td>

    <td>

        <div class="archived-actions">

            <form
                method="post"
                onsubmit="return confirm('Restore this inventory item?');"
            >

                <input
                    type="hidden"
                    name="action"
                    value="restore_item"
                >

                <input
                    type="hidden"
                    name="inventory_id"
                    value="<?= (int)$item['inventory_id'] ?>"
                >

                <button
                    type="submit"
                    class="restore-button"
                >
                    Restore
                </button>

            </form>


            <form
                method="post"
                onsubmit="return confirm('Permanently delete this archived item? This cannot be undone.');"
            >

                <input
                    type="hidden"
                    name="action"
                    value="delete_permanently"
                >

                <input
                    type="hidden"
                    name="inventory_id"
                    value="<?= (int)$item['inventory_id'] ?>"
                >

                <button
                    type="submit"
                    class="permanent-delete-button"
                >
                    Delete
                </button>

            </form>

        </div>

    </td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

</table>

</div>

</main>

</body>

</html>