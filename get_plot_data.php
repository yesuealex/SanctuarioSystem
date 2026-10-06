<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
$plot = strtolower(trim($_GET['plot'] ?? ''));
if ($plot === '') { echo json_encode(['error'=>'Missing plot']); exit; }
function normArea($v){$v=strtolower(preg_replace('/\s+/', '', (string)$v)); if($v==='nursery')return'nursery'; if($v==='mno')return'mno'; if(preg_match('/^(?:plot_)?([abc])(?:[0-9]+)?$/',$v,$m))return'plot_'.$m[1]; return $v;}
function safeRows($res){$a=[]; if($res) while($r=$res->fetch_assoc())$a[]=$r; return $a;}
$isLivestock = strpos($plot,'house')!==false;
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
$rows=[];$res=$conn->query('SELECT * FROM crops WHERE COALESCE(archived,0)=0 ORDER BY crop_id ASC');
if($res) while($c=$res->fetch_assoc()) if(normArea($c['transplanted_area']??'')===$wanted || ($wanted==='plot_a'&&normArea($c['transplanted_area']??'')==='a') || ($wanted==='plot_b'&&normArea($c['transplanted_area']??'')==='b') || ($wanted==='plot_c'&&normArea($c['transplanted_area']??'')==='c')) $rows[]=$c;
$ready=$soon=$growing=0;$today=new DateTime('today');$limit=(clone $today)->modify('+14 days');
foreach($rows as $c){$st=strtolower((string)($c['status']??''));if($st==='ready')$ready++;elseif($st==='harvested'){}else{$growing++; if(!empty($c['expected_harvest'])){try{$d=new DateTime($c['expected_harvest']);if($d>=$today&&$d<=$limit)$soon++;}catch(Throwable $e){}}}}
$bedTotal=0;$plots=$conn->query('SELECT * FROM plots WHERE COALESCE(archived,0)=0');if($plots)while($p=$plots->fetch_assoc()){if(strtolower((string)($p['plot_code']??''))===$wanted)$bedTotal=(int)($p['beds']??$p['total_beds']??$p['number_of_beds']??0);}
$occupied=[];foreach($rows as $c)if(isset($c['bed_id'])&&$c['bed_id']!=='')$occupied[(string)$c['bed_id']]=1;
$available=max(0,$bedTotal-(count($occupied)>0?count($occupied):count($rows)));
$name=$wanted==='nursery'?'Nursery':($wanted==='mno'?'MNO':'Plot '.strtoupper(substr($wanted,-1)));
echo json_encode(['type'=>'crop','plot_name'=>$name,'plants_planted'=>count($rows),'bed_available'=>$available,'soon_to_harvest'=>$soon,'ready_to_harvest'=>$ready,'growing'=>$growing,'crop_records'=>$rows]);