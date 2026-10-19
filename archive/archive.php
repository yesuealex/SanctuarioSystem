<?php
/* Shared physical archive helpers for Sanctuario Nature Farm. */

function archiveTableAllowed(string $table): bool {
    static $allowed = [
        'crops','livestocks','inventory_items','maintenance_requests',
        'plots','livestock_houses','inventory_requests',
        'chicken_weight_records','chicken_egg_records','chicken_mortality_records',
        'chicken_feed_ingredients','chicken_drinking_water','chicken_feeding_schedule',
        'goat_monitoring_records','goat_forage_records'
    ];
    return in_array($table, $allowed, true);
}

function archiveTableName(string $source): string { return 'archived_' . $source; }

function archiveTableExists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '{$safe}'");
    $ok = $r && $r->num_rows > 0;
    if ($r) $r->free();
    return $ok;
}

function archiveColumns(mysqli $conn, string $table): array {
    $cols=[];
    $r=$conn->query("SHOW COLUMNS FROM `{$table}`");
    if(!$r) throw new Exception('Could not inspect table: '.$conn->error);
    while($row=$r->fetch_assoc()) $cols[]=$row['Field'];
    $r->free();
    return $cols;
}

function ensureArchiveTable(mysqli $conn, string $source, bool $migrateLegacy = true): string {
    if (!archiveTableAllowed($source)) throw new Exception('Archive source is not allowed.');
    $archive=archiveTableName($source);
    if (!archiveTableExists($conn,$source)) throw new Exception("Source table '{$source}' does not exist.");
    if (!archiveTableExists($conn,$archive)) {
        if(!$conn->query("CREATE TABLE `{$archive}` LIKE `{$source}`")) throw new Exception('Could not create archive table: '.$conn->error);
    }
    $cols=archiveColumns($conn,$archive);
    if(!in_array('archived_at',$cols,true)) {
        if(!$conn->query("ALTER TABLE `{$archive}` ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL")) throw new Exception('Could not add archive timestamp: '.$conn->error);
    }
    if(!in_array('archived_by',$cols,true)) {
        if(!$conn->query("ALTER TABLE `{$archive}` ADD COLUMN `archived_by` INT NULL DEFAULT NULL")) throw new Exception('Could not add archive user field: '.$conn->error);
    }

    if($migrateLegacy) {
        // Move legacy soft-archived rows into their physical archive table.
        $sourceCols = archiveColumns($conn,$source);
        $flag = in_array('archived',$sourceCols,true) ? 'archived' : (in_array('is_archived',$sourceCols,true) ? 'is_archived' : null);
        if($flag) {
            $conn->query("INSERT IGNORE INTO `{$archive}` (`".implode('`,`',$sourceCols)."`,`archived_at`,`archived_by`) SELECT `".implode('`,`',$sourceCols)."`,NOW(),NULL FROM `{$source}` WHERE `{$flag}`=1");
            $conn->query("DELETE FROM `{$source}` WHERE `{$flag}`=1");
        } elseif($source==='maintenance_requests') {
            $conn->query("INSERT IGNORE INTO `{$archive}` (`".implode('`,`',$sourceCols)."`,`archived_at`,`archived_by`) SELECT `".implode('`,`',$sourceCols)."`,NOW(),NULL FROM `{$source}` WHERE `status` IN ('Completed','Cancelled')");
            $conn->query("DELETE FROM `{$source}` WHERE `status` IN ('Completed','Cancelled')");
        }
    }
    return $archive;
}

function archiveRecordInTransaction(mysqli $conn, string $source, string $pk, int $id, int $userId): void {
    if (!archiveTableAllowed($source)) throw new Exception('Archive source is not allowed.');
    $sourceCols=archiveColumns($conn,$source);
    if(!in_array($pk,$sourceCols,true)) throw new Exception("Invalid primary key for {$source}.");
    $archive=ensureArchiveTable($conn,$source,false);
    $archiveCols=archiveColumns($conn,$archive);
    $selectCols=[];
    foreach($sourceCols as $c) $selectCols[]="`{$c}`";
    $sql="INSERT INTO `{$archive}` (`".implode('`,`',$sourceCols)."`,`archived_at`,`archived_by`) SELECT ".implode(',',$selectCols).",NOW(),? FROM `{$source}` WHERE `{$pk}`=? LIMIT 1";
    $stmt=$conn->prepare($sql);
    if(!$stmt) throw new Exception('Could not prepare archive: '.$conn->error);
    $stmt->bind_param('ii',$userId,$id);
    if(!$stmt->execute()) { $e=$stmt->error; $stmt->close(); throw new Exception('Could not archive record: '.$e); }
    if($stmt->affected_rows!==1) { $stmt->close(); throw new Exception('Record was not found or is already archived.'); }
    $stmt->close();
    $stmt=$conn->prepare("DELETE FROM `{$source}` WHERE `{$pk}`=? LIMIT 1");
    if(!$stmt) throw new Exception('Could not prepare source deletion: '.$conn->error);
    $stmt->bind_param('i',$id);
    if(!$stmt->execute()) { $e=$stmt->error; $stmt->close(); throw new Exception('Could not remove active record: '.$e); }
    $stmt->close();
}

function archiveRecord(mysqli $conn,string $source,string $pk,int $id,int $userId): void {
    $conn->begin_transaction();
    try { archiveRecordInTransaction($conn,$source,$pk,$id,$userId); $conn->commit(); }
    catch(Throwable $e){ $conn->rollback(); throw $e; }
}

function restoreRecord(mysqli $conn,string $source,string $pk,int $id): void {
    if (!archiveTableAllowed($source)) throw new Exception('Archive source is not allowed.');
    $archive=ensureArchiveTable($conn,$source,false);
    $sourceCols=archiveColumns($conn,$source);
    $expr=[];
    foreach($sourceCols as $c) {
        if($c==='archived' || $c==='is_archived') $expr[]="0 AS `{$c}`";
        else $expr[]="`{$c}`";
    }
    $conn->begin_transaction();
    try {
        $check=$conn->prepare("SELECT COUNT(*) FROM `{$source}` WHERE `{$pk}`=?");
        if(!$check) throw new Exception('Could not check active record.');
        $check->bind_param('i',$id); $check->execute(); $check->bind_result($count); $check->fetch(); $check->close();
        if($count>0) throw new Exception('A record with this ID already exists in the active table.');
        $sql="INSERT INTO `{$source}` (`".implode('`,`',$sourceCols)."`) SELECT ".implode(',',$expr)." FROM `{$archive}` WHERE `{$pk}`=? LIMIT 1";
        $stmt=$conn->prepare($sql); if(!$stmt) throw new Exception('Could not prepare restore: '.$conn->error);
        $stmt->bind_param('i',$id); if(!$stmt->execute()) { $e=$stmt->error; $stmt->close(); throw new Exception('Could not restore record: '.$e); }
        if($stmt->affected_rows!==1) { $stmt->close(); throw new Exception('Archived record was not found.'); }
        $stmt->close();
        $stmt=$conn->prepare("DELETE FROM `{$archive}` WHERE `{$pk}`=? LIMIT 1");
        if(!$stmt) throw new Exception('Could not prepare archive cleanup: '.$conn->error);
        $stmt->bind_param('i',$id); if(!$stmt->execute()) { $e=$stmt->error; $stmt->close(); throw new Exception('Could not remove restored archive copy: '.$e); }
        $stmt->close();
        $conn->commit();
    } catch(Throwable $e){ $conn->rollback(); throw $e; }
}

function permanentlyDeleteArchived(mysqli $conn,string $source,string $pk,int $id): void {
    $archive=ensureArchiveTable($conn,$source,false);
    $stmt=$conn->prepare("DELETE FROM `{$archive}` WHERE `{$pk}`=? LIMIT 1");
    if(!$stmt) throw new Exception('Could not prepare permanent delete: '.$conn->error);
    $stmt->bind_param('i',$id); if(!$stmt->execute()) { $e=$stmt->error; $stmt->close(); throw new Exception('Could not permanently delete record: '.$e); }
    $stmt->close();
}

function archiveTableRowsForLivestock(mysqli $conn,string $source,int $livestockId,int $userId): void {
    if(!archiveTableExists($conn,$source)) return;
    $archive=ensureArchiveTable($conn,$source);
    $cols=archiveColumns($conn,$source);
    if(!in_array('livestock_id',$cols,true)) return;
    $where='`livestock_id`=?';
    if(in_array('archived',$cols,true)) $where.=' AND `archived`=0';
    $sql="SELECT `".implode('`,`',$cols)."` FROM `{$source}` WHERE {$where}";
    $res=$conn->prepare($sql); if(!$res) throw new Exception('Could not read related monitoring records.');
    $res->bind_param('i',$livestockId); $res->execute(); $result=$res->get_result();
    while($row=$result->fetch_assoc()) {
        $ph=[];$vals=[];$types='';
        foreach($cols as $c){$ph[]='?';$vals[]=$row[$c];$types.='s';}
        $ph[]='NOW()';$ph[]='?';$vals[]=$userId;$types.='i';
        $sql2="INSERT INTO `{$archive}` (`".implode('`,`',$cols)."`,`archived_at`,`archived_by`) VALUES (".implode(',',$ph).")";
        $ins=$conn->prepare($sql2); if(!$ins) throw new Exception('Could not archive related record.');
        $bind=[$types]; foreach($vals as $k=>&$v)$bind[]=&$v; call_user_func_array([$ins,'bind_param'],$bind); if(!$ins->execute()){ $e=$ins->error;$ins->close();throw new Exception($e);} $ins->close();
        $del=$conn->prepare("DELETE FROM `{$source}` WHERE `id`=? LIMIT 1"); if(!$del) throw new Exception('Could not remove related record.');
        $rid=(int)$row['id'];$del->bind_param('i',$rid);$del->execute();$del->close();
    }
    $res->close();
}

function restoreLivestockRelated(mysqli $conn,string $source,int $livestockId): void {
    if(!archiveTableExists($conn,$source)) return;
    $archive=ensureArchiveTable($conn,$source); $cols=archiveColumns($conn,$source);
    if(!in_array('livestock_id',$cols,true)) return;
    $expr=[]; foreach($cols as $c) $expr[]=($c==='archived'?'0 AS `archived`':"`{$c}`");
    $sql="SELECT ".implode(',',$expr)." FROM `{$archive}` WHERE `livestock_id`=?";
    $stmt=$conn->prepare($sql); if(!$stmt) throw new Exception('Could not read archived related records.'); $stmt->bind_param('i',$livestockId);$stmt->execute();$res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $fields=$cols;$place=[];$vals=[];$types=''; foreach($fields as $c){$place[]='?';$vals[]=$row[$c];$types.='s';}
        $sql2="INSERT INTO `{$source}` (`".implode('`,`',$fields)."`) VALUES (".implode(',',$place).")";
        $ins=$conn->prepare($sql2); if(!$ins) throw new Exception('Could not restore related record.'); $bind=[$types];foreach($vals as $k=>&$v)$bind[]=&$v;call_user_func_array([$ins,'bind_param'],$bind);if(!$ins->execute()){ $e=$ins->error;$ins->close();throw new Exception($e);} $ins->close();
    }
    $res->free();$stmt->close();
    $del=$conn->prepare("DELETE FROM `{$archive}` WHERE `livestock_id`=?"); if(!$del) throw new Exception('Could not clean restored archive records.');$del->bind_param('i',$livestockId);$del->execute();$del->close();
}
?>s