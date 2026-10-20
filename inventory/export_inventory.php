<?php
require_once __DIR__ . '/../staff_access.php';
if (in_array(sanctuario_staff_role(), ['crops staff', 'livestock staff'], true)) { http_response_code(403); exit('Access denied.'); }
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
require_once __DIR__ . '/../db.php';
$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? '';
$month = filter_var($_GET['month'] ?? 0, FILTER_VALIDATE_INT);
if (!in_array($type, ['seeds','sowing'], true) || !in_array($format, ['pdf','excel'], true) || $month === false || $month < 0 || $month > 12) { http_response_code(400); exit('Invalid export request.'); }
$title = $type === 'seeds' ? 'SEED INVENTORY' : 'SOWING MATERIAL INVENTORY';
$headers = $type === 'seeds' ? ['#','DATE RECEIVED','SEEDS','VARIETY','BRAND','PIECES','NO. OF SEEDS','PRICE','STATUS'] : ['#','DATE RECEIVED','MATERIAL','CATEGORY','VARIETY','BRAND','PIECES','QUANTITY','PRICE','STATUS'];
$where = $type === 'seeds' ? "category = 'Seeds'" : "category IN ('Fertilizer','Tools','Feeds','Others')";
if ($month) $where .= ' AND MONTH(date_received) = ' . $month;
$query = "SELECT date_received, category, item_name, variety, brand, pieces, quantity, price, status FROM inventory_items WHERE $where AND is_archived = 0 ORDER BY date_received DESC, inventory_id DESC";
$result = $conn->query($query);
if (!$result) { http_response_code(500); exit('Unable to load inventory records.'); }
$rows=[];$i=0;
while ($item=$result->fetch_assoc()) {
    $row=[(string)++$i,(string)$item['date_received'],(string)$item['item_name']];
    if ($type==='sowing') $row[]=(string)$item['category'];
    array_push($row,(string)$item['variety'],(string)$item['brand'],(string)$item['pieces'],(string)$item['quantity'],(string)$item['price'],(string)$item['status']);
    $rows[]=$row;
}
$period=$month ? date('F',mktime(0,0,0,$month,1)) : 'All Months';
$filename=($type==='seeds'?'seed_inventory':'sowing_material_inventory').($month?'_'.strtolower($period):'').'_'.date('Ymd');
if ($format==='excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'.xls"');
    header('Cache-Control: no-store');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<?mso-application progid="Excel.Sheet"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Styles><Style ss:ID="head"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#326735" ss:Pattern="Solid"/></Style><Style ss:ID="title"><Font ss:Bold="1" ss:Size="16"/></Style></Styles><Worksheet ss:Name="Inventory"><Table>';
    $xml=function($v){return htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8');};
    echo '<Row><Cell ss:StyleID="title"><Data ss:Type="String">'.$xml('SANCTUARIO NATURE FARMS - '.$title).'</Data></Cell></Row>';
    echo '<Row><Cell><Data ss:Type="String">'.$xml('Period: '.$period.' | Generated: '.date('Y-m-d H:i')).'</Data></Cell></Row><Row/>';
    echo '<Row>';foreach($headers as $h) echo '<Cell ss:StyleID="head"><Data ss:Type="String">'.$xml($h).'</Data></Cell>';echo '</Row>';
    foreach($rows as $row){echo '<Row>';foreach($row as $value)echo '<Cell><Data ss:Type="String">'.$xml($value).'</Data></Cell>';echo '</Row>';}
    echo '</Table></Worksheet></Workbook>';exit;
}
// Self-contained, landscape PDF output without external Composer dependencies.
function pdfText($text) {
    $text = iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',(string)$text);
    return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$text ?: '');
}
function pdfCut($text,$limit) {
    $text=(string)$text;
    if (strlen($text)>$limit) return substr($text,0,max(0,$limit-3)).'...';
    return $text;
}
$width=1190;$height=842;$left=30;$right=30;
$colWidths=$type==='seeds'?[30,95,180,140,125,105,110,100,125]:[30,90,150,105,120,105,95,100,90,115];
$sum=array_sum($colWidths);$scale=($width-$left-$right)/$sum;$colWidths=array_map(fn($v)=>$v*$scale,$colWidths);
$pages=[];$commands='';$y=0;
$drawText=function($x,$baseline,$text,$size=9,$bold=false)use(&$commands,$height){$commands.="BT /".($bold?'F2':'F1')." $size Tf 1 0 0 1 ".round($x,2).' '.round($height-$baseline,2).' Tm ('.pdfText($text).") Tj ET\n";};
$line=function($x1,$y1,$x2,$y2)use(&$commands,$height){$commands.=sprintf('%.2f %.2f m %.2f %.2f l S' . "\n",$x1,$height-$y1,$x2,$height-$y2);};
$newPage=function()use(&$commands,&$pages,&$y,$drawText,$line,$headers,$colWidths,$left,$title,$period,$width){
    if ($commands!=='')$pages[]=$commands;
    $commands="0.16 0.26 0.15 rg\n";
    $drawText($left,42,'SANCTUARIO NATURE FARMS',17,true);
    $drawText($left,65,$title.'  |  '.$period,12,true);
    $drawText($width-245,65,'Generated: '.date('Y-m-d H:i'),9);
    $y=92;$x=$left;
    $commands.="0.19 0.40 0.21 rg\n";
    $commands.=sprintf('%.2f %.2f %.2f 25 re f' . "\n",$left,842-$y-9,array_sum($colWidths));
    $commands.="1 1 1 rg\n";
    foreach($headers as $j=>$head){$drawText($x+4,$y+8,pdfCut($head,max(5,(int)($colWidths[$j]/6))),9,true);$x+=$colWidths[$j];}
    $commands.="0 0 0 rg\n";$y+=27;
};
$newPage();
foreach($rows as $index=>$row){
    if ($y+25>805)$newPage();
    if ($index%2===0){$commands.="0.96 0.97 0.95 rg\n"; $commands.=sprintf('%.2f %.2f %.2f 24 re f' . "\n",$left,$height-$y-17,array_sum($colWidths));$commands.="0 0 0 rg\n";}
    $x=$left;
    foreach($row as $j=>$value){$limit=max(4,(int)(($colWidths[$j]-9)/5.2));$drawText($x+4,$y,pdfCut($value,$limit),9);$x+=$colWidths[$j];}
    $line($left,$y+7,$left+array_sum($colWidths),$y+7);$y+=25;
}
$pages[]=$commands;
$objects=[];
$add=function($value)use(&$objects){$objects[]=$value;return count($objects);};
$catalog=$add('');$pagesRoot=$add('');
$font1=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$font2=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
$pageRefs=[];
foreach($pages as $stream){$content=$add('<< /Length '.strlen($stream).' >>' . "\nstream\n".$stream."endstream");$pageRefs[]=$add("<< /Type /Page /Parent $pagesRoot 0 R /MediaBox [0 0 $width $height] /Resources << /Font << /F1 $font1 0 R /F2 $font2 0 R >> >> /Contents $content 0 R >>");}
$objects[$catalog-1]="<< /Type /Catalog /Pages $pagesRoot 0 R >>";
$objects[$pagesRoot-1]='<< /Type /Pages /Kids ['.implode(' ',array_map(fn($id)=>"$id 0 R",$pageRefs)).'] /Count '.count($pageRefs).' >>';
$pdf="%PDF-1.4\n";$offsets=[0];
foreach($objects as $i=>$obj){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n$obj\nendobj\n";}
$start=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
foreach(array_slice($offsets,1) as $offset)$pdf.=sprintf('%010d 00000 n ' . "\n",$offset);
$pdf.="trailer\n<< /Size ".(count($objects)+1)." /Root $catalog 0 R >>\nstartxref\n$start\n%%EOF";
header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$filename.'.pdf"');header('Content-Length: '.strlen($pdf));header('Cache-Control: no-store');echo $pdf;
