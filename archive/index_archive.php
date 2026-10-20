<?php
session_start();
if(!isset($_SESSION['user_id'])){header('Location: ../login.php');exit();}
require_once '../db.php';
require_once __DIR__.'/archive_functions.php';

$role=$_SESSION['role']??'staff';
$categories=[
 ['crops','Crops','agriculture','../crops/crops.php','../crops/archived_crops.php'],
 ['livestocks','Livestock','pets','../livestock/livestocks.php','../livestock/archived_livestocks.php'],
 ['inventory_items','Inventory','inventory_2','../inventory/adminInventory.php','../inventory/archivedInventory.php'],
 ['maintenance_requests','Maintenance','build','../maintenance/adminMaintenance.php','../maintenance/adminMaintenance.php'],
 ['plots','Farm Plots','grid_view','../farmMap/farmMap.php','table.php?source=plots'],
 ['livestock_houses','Livestock Houses','home_work','../farmMap/farmMap.php','table.php?source=livestock_houses'],
 ['chicken_weight_records','Chicken Monitoring','monitor_heart','../livestock/livestocks.php','records.php?source=chicken_weight_records'],
 ['chicken_egg_records','Chicken Eggs','egg','../livestock/livestocks.php','records.php?source=chicken_egg_records'],
 ['chicken_mortality_records','Chicken Mortality','warning','../livestock/livestocks.php','records.php?source=chicken_mortality_records'],
 ['chicken_feed_ingredients','Chicken Feed','restaurant','../livestock/livestocks.php','records.php?source=chicken_feed_ingredients'],
 ['chicken_drinking_water','Chicken Water','water_drop','../livestock/livestocks.php','records.php?source=chicken_drinking_water'],
 ['chicken_feeding_schedule','Chicken Feeding','schedule','../livestock/livestocks.php','records.php?source=chicken_feeding_schedule'],
 ['goat_monitoring_records','Goat Monitoring','pets','../livestock/livestocks.php','records.php?source=goat_monitoring_records'],
 ['goat_forage_records','Goat Forage','grass','../livestock/livestocks.php','records.php?source=goat_forage_records']
];
$available=[];
foreach($categories as $c){ if(archiveTableExists($conn,$c[0])) { try { ensureArchiveTable($conn,$c[0]); } catch(Throwable $e) {} $available[]=$c; } }
?>
<!doctype html><html lang="en">
    <head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Archive Records | Sanctuario Nature Farm</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../sidebar.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:wght@400" rel="stylesheet">
    
    <style>
body{font-family:Poppins,sans-serif}.layout{display:flex}.archive-dashboard{margin-left:260px;padding:20px;width:100%;box-sizing:border-box}.archive-header{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-bottom:20px}.archive-header h1{margin:0;font-size:1.7rem}.archive-header p{margin:5px 0 0;color:#777;font-size:.88rem}.options-wrap{position:relative}.options-btn{display:inline-flex;align-items:center;gap:7px;border:0;border-radius:9px;padding:10px 15px;background:#f1f5f9;color:#333;font:600 .84rem Poppins;cursor:pointer}.options-menu{display:none;position:absolute;right:0;top:46px;z-index:30;min-width:230px;background:#fff;border:1px solid #e5e7eb;border-radius:11px;box-shadow:0 10px 25px rgba(0,0,0,.12);padding:7px}.options-menu.open{display:block}.options-menu a{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:7px;text-decoration:none;color:#333;font-size:.82rem}.options-menu a:hover{background:#f4f7f2}.archive-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}.archive-card{background:#fff;border:1px solid #e9ecef;border-radius:13px;padding:18px;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:.18s}.archive-card:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.08)}.archive-card-icon{width:42px;height:42px;border-radius:10px;background:#eef6eb;color:#4d743f;display:flex;align-items:center;justify-content:center}.archive-card h3{margin:13px 0 5px;font-size:.98rem}.archive-card p{margin:0 0 14px;color:#777;font-size:.77rem}.archive-card a{display:inline-flex;align-items:center;gap:5px;text-decoration:none;color:#4d743f;font-size:.78rem;font-weight:600}.empty-archive{background:#fff;border:1px dashed #d7ddd3;border-radius:12px;padding:35px;text-align:center;color:#777}
</style>
<style id="sanctuario-responsive-refinement">
/* Sanctuario responsive refinement â€” layout geometry only, no feature changes. */
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
    <div class="layout"><?php include '../sidebar.php'; ?>
    <main class="archive-dashboard"><?php include '../topRightBar.php'; ?>
    <div class="archive-header">
        <div><h1>Archive Records</h1><p>Choose which archived records you want to view or manage.</p></div>
        <div class="options-wrap">
            <button class="options-btn" type="button" onclick="toggleArchiveOptions(event)">
                <span class="material-symbols-rounded">tune</span>Options<span class="material-symbols-rounded">expand_more</span>
            </button>
            <div class="options-menu" id="archiveOptions"><?php foreach($available as $c): ?><a href="<?=htmlspecialchars($c[4],ENT_QUOTES)?>">
                <span class="material-symbols-rounded"><?=htmlspecialchars($c[2])?></span>
                <?=htmlspecialchars($c[1])?></a><?php endforeach; ?>
            </div></div></div><?php if(!$available): ?>
                <div class="empty-archive">No archive tables have been created yet. Archive a record first and its separate archive table will be created automatically.</div>
                
                <?php else: ?><div class="archive-grid"><?php foreach($available as $c): ?>
                    <div class="archive-card"><div class="archive-card-icon">
                        <span class="material-symbols-rounded">
                            <?=htmlspecialchars($c[2])?>
                        </span>
                    </div>
                    <h3><?=htmlspecialchars($c[1])?></h3>
                    <p>Separate archived records for this module.</p>
                    <a href="<?=htmlspecialchars($c[4],ENT_QUOTES)?>">Open Archive <span class="material-symbols-rounded">arrow_forward</span>
                </a>
            </div>
            <?php endforeach; ?></div><?php endif; ?>
        </main>
    </div>
    <script>function toggleArchiveOptions(e){e.stopPropagation();document.getElementById('archiveOptions').classList.toggle('open')}document.addEventListener('click',()=>document.getElementById('archiveOptions')?.classList.remove('open'));

    </script>
    </body>
    </html>