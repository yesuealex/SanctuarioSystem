<?php
session_start();
if(!isset($_SESSION['user_id'])){header("Location: ../login.php");exit();}
if(($_SESSION['role']??'')!=='maintenance'){header("Location: ../adminDashboard.php");exit();}
require_once '../db.php';function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$id=(int)$_SESSION['user_id'];$rows=[];
try{$st=$conn->prepare("SELECT * FROM maintenance_requests WHERE assigned_to=? AND status='Completed' ORDER BY request_id DESC");$st->bind_param("i",$id);$st->execute();$r=$st->get_result();while($x=$r->fetch_assoc())$rows[]=$x;$st->close();}catch(Throwable $ex){$error='Could not load tasks.';}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>My Maintenance Tasks</title><link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="../sidebar.css"><link rel="stylesheet" href="maintenance.css"><style>.page{padding:24px}.box{background:#fff;border-radius:16px;padding:22px}.item{padding:14px 0;border-bottom:1px solid #e8ece9}.muted{color:#718077;font-size:12px}</style></head><body><div class="layout"><?php include '../sidebar.php';?><main class="dashboard"><?php include '../topRightBar.php';?><div class="page"><h1>Maintenance History</h1><p>Completed maintenance work assigned to you.</p><div class="box"><?php if(!$rows):?><p>No completed tasks.</p><?php else:foreach($rows as $r):?><div class="item"><h3><?=e($r['title'])?></h3><p><?=e($r['description'])?></p><p class="muted"><?=e($r['location']?:'No location')?> · <?=e($r['priority'])?> · <?=e($r['status'])?></p></div><?php endforeach;endif;?></div></div></main></div></body></html>
