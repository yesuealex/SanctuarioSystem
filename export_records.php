<?php
/** Sanctuario read-only report exporter. No database changes. */
require_once __DIR__ . '/staff_access.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); exit('Please log in.'); }
require_once __DIR__ . '/db.php';
$kind = (string)($_GET['report'] ?? '');
$format = (string)($_GET['format'] ?? '');
$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($format, ['pdf','xlsx'], true)) { http_response_code(400); exit('Invalid format'); }
if (!in_array($kind, ['crops','harvests','livestock','inventory','transactions','chicken_weight_records','chicken_egg_records','chicken_mortality_records','chicken_feed_ingredients','chicken_drinking_water','chicken_feeding_schedule','goat_monitoring_records','goat_forage_records'],true)) { http_response_code(400); exit('Invalid report'); }
if (in_array($role,['crops staff','livestock staff'],true)) {
    if ($kind === 'inventory' || $kind === 'transactions' || ($role === 'crops staff' && !in_array($kind,['crops','harvests'],true)) || ($role === 'livestock staff' && in_array($kind,['crops','harvests'],true))) { http_response_code(403); exit('Access denied'); }
}
// Location-aware crop PDF exports. Other reports and Excel remain unchanged.
if ($kind === 'crops' && $format === 'pdf' && isset($_GET['location'])) {
    $location = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', (string)$_GET['location']))));
    $allowed = ['NURSERY','PLOT A','PLOT B','PLOT C','MNO','HYDRO','TENT HOUSE'];
    if (!in_array($location, $allowed, true)) { http_response_code(400); exit('Invalid crop location'); }
    $nursery = $location === 'NURSERY';
    $normalize = static function($v) {
        $v = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', (string)$v))));
        if (preg_match('/^(?:PLOT )?([ABC])(?:[0-9]+)?$/', str_replace(' ', '', $v), $m)) return 'PLOT '.$m[1];
        return $v;
    };
    $matches = static function($row) use ($location, $normalize) {
        foreach (['plot_name','plot_code','transplanted_area'] as $field) {
            if (isset($row[$field]) && $normalize($row[$field]) === $location) return true;
        }
        return false;
    };
    $date = static function($v) { return !empty($v) && strtotime((string)$v) ? date('m/d/Y', strtotime((string)$v)) : ''; };
    $qty = static function($n,$u='') { return $n === null || $n === '' ? '' : rtrim(rtrim(number_format((float)$n,3,'.',''),'0'),'.').($u ? ' '.$u : ''); };
    if ($nursery) {
        $query = "SELECT c.*, p.plot_name, p.plot_code FROM crops c LEFT JOIN plots p ON p.plot_id=c.plot_id WHERE COALESCE(c.archived,0)=0 AND COALESCE(c.transferred_out,0)=0 ORDER BY c.crop_id DESC";
        $result = $conn->query($query);
        if (!$result) { http_response_code(500); exit('Unable to load nursery records.'); }
        $head = ['RECIPIENT','CROP','VARIETY','NO. OF TRAYS','DATE OF SOWING','GERMINATION RATE','TARGET TRANSPLANT DATE','TARGET AREA','DATE TRANSPLANTED'];
        $rows=[];
        while ($r=$result->fetch_assoc()) {
            if (!$matches($r)) continue;
            // Use the stored trays field if present; otherwise the existing pcs field.
            $trays = $r['no_of_trays'] ?? $r['number_of_trays'] ?? $r['trays'] ?? $r['pcs'] ?? '';
            $rate = trim((string)($r['germination_rate'] ?? ''));
            $rows[] = [$r['recipient'] ?? '',$r['crop_name'] ?? '',$r['variety'] ?? '',$trays,$date($r['date_sowing'] ?? ''),$rate === '' ? '' : $rate.'%',$date($r['target_transplant_date'] ?? ''),$r['target_area'] ?? '',$date($r['date_transplanted'] ?? '')];
        }
        $widths=[125,130,110,85,115,105,135,130,115];
    } else {
        $query = "SELECT p.plot_name,p.plot_code,pb.bed_code,pb2.bed_code AS crop_bed_code,c.transplanted_area,c.crop_name AS original_crop_name,c.date_transplanted,c.date_sowing,r.crop_name,r.record_date,r.fertilizer_insecticide_applied,h.harvest_count,h.total_quantity,h.total_unit,h.sales_quantity,h.sales_unit,h.sales_amount,h.farm_use_quantity,h.farm_use_unit,h.animal_feed_quantity,h.animal_feed_unit,h.rejected_quantity,h.rejected_unit FROM plots p LEFT JOIN crops c ON (c.plot_id=p.plot_id OR UPPER(TRIM(c.transplanted_area))=UPPER(TRIM(p.plot_name)) OR LOWER(TRIM(c.transplanted_area))=LOWER(TRIM(p.plot_code))) LEFT JOIN plot_crop_records r ON r.crop_id=c.crop_id AND r.plot_id=p.plot_id AND COALESCE(r.archived,0)=0 LEFT JOIN plot_beds pb ON pb.bed_id=r.bed_id LEFT JOIN plot_beds pb2 ON pb2.bed_id=c.bed_id LEFT JOIN plot_crop_harvests h ON h.record_id=r.record_id WHERE c.crop_id IS NOT NULL AND COALESCE(c.archived,0)=0 AND COALESCE(p.archived,0)=0 ORDER BY COALESCE(h.harvest_date,r.record_date,c.date_transplanted,c.date_sowing) DESC,c.crop_id DESC";
        $result=$conn->query($query);
        if (!$result) { http_response_code(500); exit('Unable to load plot harvest records.'); }
        $head=['PLOT NUMBER','CROP','DATE','FERTILIZER / INSECTICIDE APPLIED','TOTAL NUMBER OF HARVESTS','QUANTITY','AMOUNT','FARM USE','ANIMAL FEED','REJECT'];
        $rows=[];
        while ($r=$result->fetch_assoc()) {
            if (!$matches($r)) continue;
            $rows[]=[ $r['bed_code'] ?: ($r['crop_bed_code'] ?? ''),$r['crop_name'] ?: ($r['original_crop_name'] ?? ''),$date($r['record_date'] ?: ($r['date_transplanted'] ?: $r['date_sowing'])),$r['fertilizer_insecticide_applied'] ?? '',$qty($r['total_quantity'],$r['total_unit']),$qty($r['sales_quantity'],$r['sales_unit']),$r['sales_amount'] === null || $r['sales_amount'] === '' ? '' : number_format((float)$r['sales_amount'],2),$qty($r['farm_use_quantity'],$r['farm_use_unit']),$qty($r['animal_feed_quantity'],$r['animal_feed_unit']),$qty($r['rejected_quantity'],$r['rejected_unit']) ];
        }
        $widths=[95,115,95,165,125,95,95,110,110,110];
    }
    // Draw a real PDF table with merged group headings, repeating on every page.
    $pw=1190.55; $ph=841.89; $left=34; $top=752; $rowH=35; $headerH=$nursery?48:78;
    $usable=$pw-2*$left; $factor=$usable/array_sum($widths); $widths=array_map(static fn($w)=>$w*$factor,$widths);
    $esc=static function($v) { $s=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v); return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$s===false?'':$s); };
    $pages=[]; $content=''; $y=$top;
    $rect=static function($x,$bottom,$w,$h) use (&$content) { $content.=sprintf('%.2f %.2f %.2f %.2f re S ', $x,$bottom,$w,$h); };
    $write=static function($value,$x,$bottom,$w,$h,$bold=false,$size=8) use (&$content,$esc) {
        $words=preg_split('/\s+/',trim((string)$value)); $lines=[]; $line=''; $max=max(4,(int)floor(($w-10)/($size*.51)));
        foreach($words as $word) { if(strlen($line.' '.$word)>$max && $line!=='') { $lines[]=$line; $line=$word; } else $line=trim($line.' '.$word); }
        if($line!=='') $lines[]=$line;
        $lines=array_slice($lines,0,max(1,(int)floor(($h-6)/($size+2))));
        $baseline=$bottom+$h/2+(count($lines)-1)*($size+2)/2-$size*.35;
        foreach($lines as $line) { $line=substr($line,0,$max); $content.=sprintf('BT /%s %.1f Tf %.2f %.2f Td (%s) Tj ET ', $bold?'F2':'F1',$size,$x+max(3,($w-strlen($line)*$size*($bold ? .55 : .50))/2),$baseline,$esc($line)); $baseline-=$size+2; }
    };
    $header=static function() use (&$content,&$y,$ph,$left,$pw,$location,$nursery,$headerH,$head,$widths,$rect,$write,$esc) {
        $content='0 G 0 g 0.65 w ';
        // Keep the report title left-aligned; center only table content.
        $content.=sprintf('BT /F2 15 Tf %.2f %.2f Td (%s) Tj ET ', $left, $ph-34, $esc($location.' RECORDS'));
        $generated = 'Generated: '.date('m/d/Y H:i');
        $dateFontSize = 8;
        $dateWidth = strlen($generated) * $dateFontSize * 0.50;
        $content.=sprintf('BT /F1 %.1f Tf %.2f %.2f Td (%s) Tj ET ', $dateFontSize, $pw-$left-$dateWidth, $ph-34, $esc($generated));
        $y-= $headerH;
        if($nursery) { $x=$left; foreach($head as $i=>$label) { $rect($x,$y,$widths[$i],$headerH); $write($label,$x,$y,$widths[$i],$headerH,true,8); $x+=$widths[$i]; } }
        else {
            $topGroup=20; $salesGroup=20; $x=$left;
            foreach($head as $i=>$label) {
                $w=$widths[$i];
                if($i<4) { $rect($x,$y,$w,$headerH-$topGroup); $write($label,$x,$y,$w,$headerH-$topGroup,true,8); }
                elseif($i===5 || $i===6) { $rect($x,$y,$w,$headerH-$topGroup-$salesGroup); $write($label,$x,$y,$w,$headerH-$topGroup-$salesGroup,true,8); }
                else { $rect($x,$y,$w,$headerH-$topGroup); $write($label,$x,$y,$w,$headerH-$topGroup,true,8); }
                $x+=$w;
            }
            $tx=$left; $tw=array_sum(array_slice($widths,0,4));
            $rect($tx,$y+$headerH-$topGroup,$tw,$topGroup);
            $write('TRANSPLANT',$tx,$y+$headerH-$topGroup,$tw,$topGroup,true,9);
            $hx=$left+array_sum(array_slice($widths,0,4));
            $hw=array_sum(array_slice($widths,4));
            $rect($hx,$y+$headerH-$topGroup,$hw,$topGroup);
            $write('HARVEST',$hx,$y+$headerH-$topGroup,$hw,$topGroup,true,9);
            $sx=$hx+$widths[4]; $sw=$widths[5]+$widths[6];
            $rect($sx,$y+$headerH-$topGroup-$salesGroup,$sw,$salesGroup);
            $write('SALES',$sx,$y+$headerH-$topGroup-$salesGroup,$sw,$salesGroup,true,9);
        }
    };
    $header();
    foreach($rows as $row) {
        if($y-$rowH<38) { $pages[]=$content; $y=$top; $header(); }
        $y-=$rowH; $x=$left;
        foreach($widths as $i=>$w) { $rect($x,$y,$w,$rowH); $write($row[$i] ?? '',$x,$y,$w,$rowH,false,8); $x+=$w; }
    }
    if(!$rows) $write('No records found for this section.',$left,$y-34,500,30,false,10);
    $pages[]=$content;
    $objects=[]; $add=static function($v) use (&$objects) { $objects[]=$v; return count($objects); };
    $catalog=$add(''); $pagesObj=$add(''); $f1=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'); $f2=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'); $ids=[];
    foreach($pages as $stream) { $sid=$add('<< /Length '.strlen($stream).' >>'."\nstream\n".$stream."\nendstream"); $ids[]=$add('<< /Type /Page /Parent '.$pagesObj.' 0 R /MediaBox [0 0 '.$pw.' '.$ph.'] /Resources << /Font << /F1 '.$f1.' 0 R /F2 '.$f2.' 0 R >> >> /Contents '.$sid.' 0 R >>'); }
    $objects[$catalog-1]='<< /Type /Catalog /Pages '.$pagesObj.' 0 R >>'; $objects[$pagesObj-1]='<< /Type /Pages /Kids ['.implode(' ',array_map(static fn($id)=>$id.' 0 R',$ids)).'] /Count '.count($ids).' >>';
    $pdf="%PDF-1.4\n"; $offsets=[0]; foreach($objects as $i=>$obj) { $offsets[]=strlen($pdf); $pdf.=($i+1)." 0 obj\n".$obj."\nendobj\n"; }
    $xref=strlen($pdf); $pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n"; foreach(array_slice($offsets,1) as $off) $pdf.=sprintf('%010d 00000 n ', $off)."\n";
    $pdf.="trailer\n<< /Size ".(count($objects)+1).' /Root '.$catalog." 0 R >>\nstartxref\n".$xref."\n%%EOF";
    $filename='Sanctuario_'.str_replace(' ','_',$location).'_Records_'.date('Y-m-d').'.pdf';
    header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="'.$filename.'"'); header('Content-Length: '.strlen($pdf)); echo $pdf; exit;
}
// Use explicit approved SQL, never interpolate user-controlled table names.
$sql = [
 'crops' => 'SELECT * FROM crops WHERE COALESCE(archived,0)=0 AND COALESCE(transferred_out,0)=0 ORDER BY crop_id DESC',
 'harvests' => 'SELECT p.plot_name AS plot, pb.bed_code AS bed, r.crop_name, r.record_date, r.fertilizer_insecticide_applied, h.harvest_date, h.harvest_count, h.total_quantity, h.total_unit, h.sales_quantity, h.sales_unit, h.sales_amount, h.farm_use_quantity, h.farm_use_unit, h.animal_feed_quantity, h.animal_feed_unit, h.rejected_quantity, h.rejected_unit, h.other_purposes_quantity, h.other_purposes_unit FROM plot_crop_records r LEFT JOIN plots p ON p.plot_id=r.plot_id LEFT JOIN plot_beds pb ON pb.bed_id=r.bed_id LEFT JOIN plot_crop_harvests h ON h.record_id=r.record_id WHERE COALESCE(r.archived,0)=0 ORDER BY r.record_date DESC, h.harvest_date DESC',
 'livestock' => 'SELECT * FROM livestocks WHERE COALESCE(archived,0)=0 ORDER BY livestock_id DESC',
 'inventory' => 'SELECT * FROM inventory_items ORDER BY inventory_id DESC',
 'transactions' => 'SELECT * FROM inventory_transactions ORDER BY created_at DESC',
];
$monitorTables = ['chicken_weight_records','chicken_egg_records','chicken_mortality_records','chicken_feed_ingredients','chicken_drinking_water','chicken_feeding_schedule','goat_monitoring_records','goat_forage_records'];
foreach ($monitorTables as $table) $sql[$table] = "SELECT * FROM `$table` WHERE COALESCE(archived,0)=0 ORDER BY id DESC";
$livestockId = filter_input(INPUT_GET, 'livestock_id', FILTER_VALIDATE_INT);
if (in_array($kind,$monitorTables,true) && $livestockId && $livestockId>0) {
    $sql[$kind] = "SELECT * FROM `$kind` WHERE COALESCE(archived,0)=0 AND (livestock_id=".(int)$livestockId." OR livestock_id=0) ORDER BY id DESC";
}
try {
 $res = $conn->query($sql[$kind]);
 if (!$res) throw new Exception($conn->error);
 $cols = []; foreach ($res->fetch_fields() as $field) {
   if (preg_match('/^(?:password|token|secret|reset_|verification_|image|photo|profile_picture)/i',$field->name)) continue;
   $cols[]=$field->name;
 }
 $rows=[];
 while ($record=$res->fetch_assoc()) { $row=[]; foreach($cols as $col) {
   $value=$record[$col] ?? '';
   if (is_string($value) && preg_match('/^(?:https?:\/\/|data:image\/)/i',$value)) $value='';
   $row[]=is_scalar($value) ? (string)$value : '';
 } $rows[]=$row; }
} catch(Throwable $ex) { http_response_code(500); exit('Unable to generate report: database table or query unavailable.'); }
$title = strtoupper(str_replace('_',' ', $kind));
$filename='Sanctuario_'.preg_replace('/[^a-z0-9_]/i','_',$kind).'_'.date('Y-m-d');
function xmlsafe($s) { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',(string)$s), ENT_XML1 | ENT_QUOTES,'UTF-8'); }
if ($format==='xlsx') {
 if (!class_exists('ZipArchive')) { http_response_code(500); exit('PHP ZipArchive extension is required for Excel exports.'); }
 $zip=new ZipArchive(); $tmp=tempnam(sys_get_temp_dir(),'sanctuario_');
 if ($zip->open($tmp, ZipArchive::OVERWRITE)!==true) { http_response_code(500); exit('Cannot create workbook.'); }
 $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
 $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
 $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Records" sheetId="1" r:id="rId1"/></sheets></workbook>');
 $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
 $zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/></font><font><b/><sz val="11"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>');
 $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="16"/><sheetData>';
 $all=array_merge([$cols],$rows); foreach($all as $r=>$values) { $sheet.='<row r="'.($r+1).'">'; foreach($values as $c=>$v) { $letters=''; $n=$c+1; while($n>0){$n--; $letters=chr(65+$n%26).$letters;$n=intdiv($n,26);} $sheet.='<c r="'.$letters.($r+1).'" t="inlineStr"'.($r===0?' s="1"':'').'><is><t xml:space="preserve">'.xmlsafe($v).'</t></is></c>'; } $sheet.='</row>'; }
 $sheet.='</sheetData><autoFilter ref="A1:'; $n=max(1,count($cols)); $last=''; while($n>0){$n--; $last=chr(65+$n%26).$last;$n=intdiv($n,26);} $sheet.=$last.max(1,count($all)).'"/></worksheet>';
 $zip->addFromString('xl/worksheets/sheet1.xml',$sheet); $zip->close();
 header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="'.$filename.'.xlsx"'); header('Content-Length: '.filesize($tmp)); readfile($tmp); unlink($tmp); exit;
}
// Standalone black-and-white PDF table, landscape A3, repeating headers across pages.
function pdfText($s) { $s=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$s); return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$s===false?'':$s); }
$pw=1190.55; $ph=841.89; $margin=30; $font=7; $line=21; $count=max(1,count($cols)); $cw=($pw-2*$margin)/$count;
$pages=[]; $content=''; $y=$ph-46; $rowNumber=0;
$drawRow=function($values,$heading=false) use (&$content,&$y,$margin,$cw,$count,$line,$font) {
 $bottom=$y-$line; $content.="0 G 0 g 0.55 w ";
 for($j=0;$j<$count;$j++) { $x=$margin+$j*$cw; $content.=sprintf('%.2f %.2f %.2f %.2f re S ',$x,$bottom,$cw,$line); $max=max(3,(int)floor(($cw-8)/($font*.52))); $v=substr(pdfText($values[$j]??''),0,$max); $content.=sprintf('BT /F1 %.1f Tf %.2f %.2f Td (%s) Tj ET ',$font,$x+4,$bottom+7,$v); }
 $content.="\n"; $y=$bottom;
};
$startPage=function() use (&$content,&$y,$ph,$margin,$title,$cols,$drawRow) { $content=''; $y=$ph-46; $content.=sprintf('BT /F2 15 Tf %.2f %.2f Td (%s) Tj ET ',$margin,$ph-27,pdfText('SANCTUARIO NATURE FARMS - '.$title)); $content.=sprintf('BT /F1 8 Tf %.2f %.2f Td (%s) Tj ET ',$margin,$ph-40,pdfText('Generated: '.date('Y-m-d H:i'))); $drawRow(array_map(fn($x)=>str_replace('_',' ',strtoupper($x)),$cols),true); };
$startPage(); foreach($rows as $row) { if($y-$line<$margin) { $pages[]=$content; $startPage(); } $drawRow($row); } $pages[]=$content;
$objects=[]; $add=function($s) use (&$objects) { $objects[]=$s; return count($objects); };
$catalog=$add(''); $pagesObj=$add(''); $font1=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'); $font2=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'); $pageIds=[];
foreach($pages as $page) { $streamId=$add('<< /Length '.strlen($page).' >>' . "\nstream\n".$page."\nendstream"); $pageIds[]=$add('<< /Type /Page /Parent '.$pagesObj.' 0 R /MediaBox [0 0 '.$pw.' '.$ph.'] /Resources << /Font << /F1 '.$font1.' 0 R /F2 '.$font2.' 0 R >> >> /Contents '.$streamId.' 0 R >>'); }
$objects[$catalog-1]='<< /Type /Catalog /Pages '.$pagesObj.' 0 R >>'; $objects[$pagesObj-1]='<< /Type /Pages /Kids ['.implode(' ',array_map(fn($n)=>$n.' 0 R',$pageIds)).'] /Count '.count($pageIds).' >>';
$pdf="%PDF-1.4\n"; $offsets=[0]; foreach($objects as $i=>$obj){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$obj."\nendobj\n";} $xref=strlen($pdf); $pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n"; foreach(array_slice($offsets,1) as $o)$pdf.=sprintf('%010d 00000 n ', $o)."\n"; $pdf.="trailer\n<< /Size ".(count($objects)+1).' /Root '.$catalog." 0 R >>\nstartxref\n".$xref."\n%%EOF";
header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="'.$filename.'.pdf"'); header('Content-Length: '.strlen($pdf)); echo $pdf;