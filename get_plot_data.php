<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/map_special_plots.php';
$plot = strtolower(trim($_GET['plot'] ?? ''));
if ($plot === '') { echo json_encode(['error'=>'Missing plot']); exit; }
function normArea($v){$v=strtolower(preg_replace('/\s+/', '', (string)$v)); if($v==='nursery')return'nursery'; if($v==='mno')return'mno'; if(preg_match('/^(?:plot_)?([abc])(?:[0-9]+)?$/',$v,$m))return'plot_'.$m[1]; return $v;}
function safeRows($res){$a=[]; if($res) while($r=$res->fetch_assoc())$a[]=$r; return $a;}
$isLivestock = in_array($plot, ['rabbit_house', 'goat_house', 'goat_house_2', 'poultry_house', 'swine_house'], true);
if($isLivestock){
  $houses=[]; if($r=$conn->query('SELECT * FROM livestock_houses WHERE COALESCE(archived,0)=0')) while($h=$r->fetch_assoc())$houses[]=$h;
  $house=null;
  foreach($houses as $h){$code=strtolower((string)($h['house_code']??'')); if($code===$plot){$house=$h;break;}}
  $animals=[];
  if($house && isset($house['house_id'])){$stmt=$conn->prepare('SELECT * FROM livestocks WHERE COALESCE(archived,0)=0 AND house_id=? ORDER BY livestock_id ASC');$id=(int)$house['house_id'];$stmt->bind_param('i',$id);$stmt->execute();$animals=safeRows($stmt->get_result());$stmt->close();}
  // Only explicitly assigned records belong to this house.
  // Never fall back to livestock type because that duplicates records across houses.
  $total=count($animals);
  echo json_encode(['type'=>'livestock','plot_house_name'=>$house['house_name']??$house['plot_house_name']??ucwords(str_replace('_',' ',$plot)),'number_of_livestock'=>$total,'livestock_records'=>$animals]);exit;
}
$wanted=$plot==='nursery'?'nursery':($plot==='mno'?'mno':$plot);
$rows=[];
$stmt=$conn->prepare("SELECT plot_id, plot_code, plot_name FROM plots WHERE LOWER(plot_code)=LOWER(?) OR LOWER(plot_name)=LOWER(?) LIMIT 1");
$stmt->bind_param('ss',$wanted,$wanted); $stmt->execute(); $plotRow=$stmt->get_result()->fetch_assoc(); $stmt->close();
$plotId=$plotRow ? (int)$plotRow['plot_id'] : 0;
$stmt=$conn->prepare("SELECT c.*, b.bed_code FROM crops c LEFT JOIN plot_beds b ON b.bed_id=c.bed_id WHERE COALESCE(c.archived,0)=0 AND COALESCE(c.transferred_out,0)=0 AND (LOWER(TRIM(c.transplanted_area))=? OR (? > 0 AND c.plot_id=?)) ORDER BY c.crop_id ASC");
$stmt->bind_param('sii',$wanted,$plotId,$plotId); $stmt->execute(); $rows=safeRows($stmt->get_result()); $stmt->close();
$activeRows = $rows;
$ready=$soon=$growing=0; $today=new DateTime('today'); $limit=(clone $today)->modify('+14 days');
foreach($activeRows as $c){
  $st=strtolower(trim((string)($c['status']??'')));
  $statusKey=preg_replace('/[^a-z]+/', ' ', $st);
  if($statusKey==='ready' || str_contains($statusKey, 'ready for harvest') || str_contains($statusKey, 'ready to harvest')) $ready++;
  elseif($statusKey!=='harvested'){ $growing++; if(!empty($c['expected_harvest'])){try{$d=new DateTime($c['expected_harvest']);if($d>=$today&&$d<=$limit)$soon++;}catch(Throwable $e){}}}
}
$bedTotal=0; $occupied=[];
if($plotId>0){$bs=$conn->prepare('SELECT COUNT(*) AS n FROM plot_beds WHERE plot_id=?');$bs->bind_param('i',$plotId);$bs->execute();$bedTotal=(int)($bs->get_result()->fetch_assoc()['n']??0);$bs->close();}
foreach($activeRows as $c) if(($c['bed_id']??'')!=='') $occupied[(string)$c['bed_id']]=1;
$available=max(0,$bedTotal-count($occupied));
$name=$plotRow['plot_name']??($wanted==='nursery'?'Nursery':($wanted==='mno'?'MNO':'Plot '.strtoupper(substr($wanted,-1))));
echo json_encode(['type'=>'crop','plot_name'=>$name,'plot_id'=>$plotId,'plants_planted'=>count($activeRows),'bed_available'=>$available,'soon_to_harvest'=>$soon,'ready_to_harvest'=>$ready,'growing'=>$growing,'crop_records'=>$rows]);
