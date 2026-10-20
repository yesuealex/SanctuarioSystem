<?php
session_start();
if(!isset($_SESSION['user_id'])){header('Location: ../login.php');exit();}
require_once '../db.php'; require_once __DIR__.'/archive_functions.php';
$source=$_GET['source']??''; $pk='id';
$names=['chicken_weight_records'=>'Chicken Weight Records','chicken_egg_records'=>'Chicken Egg Records','chicken_mortality_records'=>'Chicken Mortality Records','chicken_feed_ingredients'=>'Chicken Feed Ingredients','chicken_drinking_water'=>'Chicken Drinking Water','chicken_feeding_schedule'=>'Chicken Feeding Schedule','goat_monitoring_records'=>'Goat Monitoring Records','goat_forage_records'=>'Goat Forage Records'];
if(!isset($names[$source])){http_response_code(404);exit('Archive category not found.');}
$archive=ensureArchiveTable($conn,$source);$message='';$error='';
if(($_SESSION['role']??'')==='admin' && $_SERVER['REQUEST_METHOD']==='POST'){
 try{ $id=(int)($_POST['id']??0); $action=$_POST['action']??''; if($action==='restore') restoreRecord($conn,$source,$pk,$id); elseif($action==='delete') permanentlyDeleteArchived($conn,$source,$pk,$id); else throw new Exception('Invalid archive action.'); $message=$action==='restore'?'Record restored successfully.':'Record permanently deleted.'; }catch(Throwable $e){$error=$e->getMessage();}}
$cols=archiveColumns($conn,$archive);$result=$conn->query("SELECT * FROM `{$archive}` ORDER BY `{$pk}` DESC");
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars($names[$source])?> | Archive</title><link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="../sidebar.css"><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"><link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:wght@400" rel="stylesheet"><style>body{font-family:Poppins,sans-serif}.layout{display:flex}.dashboard{margin-left:260px;padding:20px;width:100%;box-sizing:border-box}.head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}.head h2{margin:0}.back{display:inline-flex;gap:6px;align-items:center;padding:9px 13px;background:#f1f5f9;color:#333;border-radius:8px;text-decoration:none;font-size:.82rem}.table-wrap{overflow:auto;background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05)}table{border-collapse:collapse;width:100%;min-width:900px}th,td{padding:10px 12px;border-bottom:1px solid #eee;text-align:left;font-size:.78rem;white-space:nowrap}th{background:#f1f5f9}.actions{display:flex;gap:6px}.act{border:0;border-radius:7px;padding:7px 9px;cursor:pointer;font:600 .72rem Poppins}.restore{background:#e8f5e9;color:#2f6b2f}.delete{background:#fdecec;color:#b72d2d}.msg{padding:10px 13px;border-radius:8px;background:#e8f5e9;color:#2f6b2f;margin-bottom:12px;font-size:.8rem}.err{background:#fdecec;color:#b72d2d}</style><style id="sanctuario-responsive-refinement">
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
</head><body><div class="layout"><?php include '../sidebar.php'; ?><main class="dashboard"><?php include '../topRightBar.php'; ?><div class="head"><h2><?=htmlspecialchars($names[$source])?></h2><a class="back" href="index.php"><span class="material-symbols-rounded">arrow_back</span>Archive Options</a></div><?php if($message):?><div class="msg"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="msg err"><?=htmlspecialchars($error)?></div><?php endif;?><div class="table-wrap"><table><thead><tr><?php foreach($cols as $c):?><th><?=htmlspecialchars(ucwords(str_replace('_',' ',$c)))?></th><?php endforeach;?><?php if(($_SESSION['role']??'')==='admin'):?><th>ACTIONS</th><?php endif;?></tr></thead><tbody><?php if(!$result||$result->num_rows===0):?><tr><td colspan="<?=count($cols)+1?>" style="text-align:center;padding:30px;color:#888">No archived records found.</td></tr><?php else: while($row=$result->fetch_assoc()):?><tr><?php foreach($cols as $c):?><td><?=htmlspecialchars((string)($row[$c]??'—'))?></td><?php endforeach;?><?php if(($_SESSION['role']??'')==='admin'):?><td><div class="actions"><form method="post"><input type="hidden" name="id" value="<?= (int)$row[$pk]?>"><input type="hidden" name="action" value="restore"><button class="act restore" onclick="return confirm('Restore this record?')">Restore</button></form><form method="post"><input type="hidden" name="id" value="<?= (int)$row[$pk]?>"><input type="hidden" name="action" value="delete"><button class="act delete" onclick="return confirm('Permanently delete this archived record? This cannot be undone.')">Delete</button></form></div></td><?php endif;?></tr><?php endwhile; endif;?></tbody></table></div></main></div></body></html>