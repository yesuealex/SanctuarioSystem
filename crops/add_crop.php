
<?php
require_once __DIR__ . '/../staff_access.php';
sanctuario_staff_guard('crops');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

include '../db.php';
include '../logfunc.php';


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login.php");

    exit();

}


/*
|--------------------------------------------------------------------------
| FETCH PLOTS
|--------------------------------------------------------------------------
|
| The dropdown will get the available plots directly
| from the plots table.
|
| plots.plot_id  →  crops.plot_id
|
|--------------------------------------------------------------------------
*/

$plotsResult = $conn->query("
    SELECT
        plot_id,
        plot_code,
        plot_name
    FROM plots
    ORDER BY plot_id ASC
");


if (!$plotsResult) die('Failed to load plots.');
$plotRows = [];
while ($plotRow = $plotsResult->fetch_assoc()) $plotRows[] = $plotRow;
$activeCropRows = [];
$activeRes = $conn->query("SELECT c.plot_id, c.bed_id, c.transplanted_area,
        COALESCE(NULLIF(nc.nursery_bed_label,''), pb.bed_code) AS nursery_bed_label
    FROM crops c LEFT JOIN nursery_crops nc ON nc.crop_id=c.crop_id AND COALESCE(nc.archived,0)=0
    LEFT JOIN plot_beds pb ON pb.bed_id=c.bed_id AND pb.plot_id=5
    WHERE COALESCE(c.archived,0)=0 AND COALESCE(c.transferred_out,0)=0");
if ($activeRes) while ($r = $activeRes->fetch_assoc()) $activeCropRows[] = $r;


/*
|--------------------------------------------------------------------------
| HANDLE FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /*
    |--------------------------------------------------------------------------
    | GET FORM VALUES
    |--------------------------------------------------------------------------
    */

    $crop_name = trim(
        $_POST['crop_name'] ?? ''
    );


    $recipient = trim(
        $_POST['recipient'] ?? ''
    );


    $variety = trim(
        $_POST['variety'] ?? ''
    );


    $pcs = intval(
        $_POST['pcs'] ?? 0
    );


    $germination_rate = floatval(
        $_POST['germination_rate'] ?? 0
    );


    $transplanted_area = trim(
        $_POST['transplanted_area'] ?? ''
    );

    $date_sowing = !empty($_POST['date_sowing']) ? $_POST['date_sowing'] : null;
    $target_transplant_date = !empty($_POST['target_transplant_date']) ? $_POST['target_transplant_date'] : null;

    $date_transplanted =
        !empty($_POST['date_transplanted'])
            ? $_POST['date_transplanted']
            : null;


    $expected_harvest =
        !empty($_POST['expected_harvest'])
            ? $_POST['expected_harvest']
            : null;


    /*
    |--------------------------------------------------------------------------
    | PLOT ID
    |--------------------------------------------------------------------------
    |
    | This must be the actual plot_id from the plots table.
    |
    */

    $plot_id = intval(
        $_POST['plot_id'] ?? 0
    );


    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($crop_name === '') {

        die("Crop name is required.");

    }


    if ($recipient === '') {

        die("Recipient is required.");

    }


    $isNursery = $plot_id === 0 || $plot_id === 5 || strtoupper(trim($transplanted_area)) === 'NURSERY' || preg_match('/^N\s*\d+$/i', $transplanted_area) === 1;
    if ($isNursery) {
        $date_sowing = $date_sowing ?: date('Y-m-d');
        // The actual date is set by the transfer flow after moving to a plot.
        $date_transplanted = null;
        if (!$target_transplant_date || !$expected_harvest) {
            die('Target transplant date and expected harvest are required for Nursery crops.');
        }
    }
    $isValidDate = static function ($value) {
        if ($value === null || $value === '') return true;
        $date = DateTime::createFromFormat('!Y-m-d', (string)$value);
        return $date !== false && $date->format('Y-m-d') === $value;
    };
    if (!$isValidDate($date_sowing) || !$isValidDate($target_transplant_date)
        || !$isValidDate($date_transplanted) || !$isValidDate($expected_harvest)) {
        die('Dates must be valid calendar dates.');
    }
    if ($plot_id <= 0 && !$isNursery) {
        die("Plot location is required.");
    }
    $nurseryBedLabel = '';
    $bed_id = null;
    if ($isNursery) {
        // Nursery has permanent beds N1-N20 in plot_beds (plot_id 5).
        $nurseryBedLabel = strtoupper(preg_replace('/\s+/', '', $transplanted_area));
        if (!preg_match('/^N(?:[1-9]|1[0-9]|20)$/', $nurseryBedLabel)) {
            die('Please select an available Nursery bed (N1-N20).');
        }
        $nurseryPlotId = 5;
        $bedCheck = $conn->prepare('SELECT bed_id, bed_code FROM plot_beds WHERE plot_id=? AND UPPER(TRIM(bed_code))=? LIMIT 1');
        $bedCheck->bind_param('is', $nurseryPlotId, $nurseryBedLabel);
        $bedCheck->execute();
        $nurseryBed = $bedCheck->get_result()->fetch_assoc();
        $bedCheck->close();
        if (!$nurseryBed) die('The selected Nursery bed does not exist in plot_beds.');
        $bed_id = (int)$nurseryBed['bed_id'];
        $nurseryBedLabel = (string)$nurseryBed['bed_code'];
        $occupied = $conn->prepare("SELECT c.crop_id FROM crops c LEFT JOIN nursery_crops nc ON nc.crop_id=c.crop_id AND COALESCE(nc.archived,0)=0 WHERE COALESCE(c.archived,0)=0 AND COALESCE(c.transferred_out,0)=0 AND (c.bed_id=? OR UPPER(TRIM(COALESCE(nc.nursery_bed_label,'')))=?) LIMIT 1");
        $occupied->bind_param('is', $bed_id, $nurseryBedLabel);
        $occupied->execute();
        if ($occupied->get_result()->num_rows > 0) { $occupied->close(); die('That Nursery bed is already occupied by an active crop.'); }
        $occupied->close();
        $plot_id = $nurseryPlotId;
        $transplanted_area = 'NURSERY';
    }
    if (!$isNursery && $plot_id > 0 && preg_match('/^([A-Za-z]+)\s*([0-9]+)$/', strtoupper($transplanted_area), $bedMatch)) {
        $bedPrefix = $bedMatch[1]; $bedNumber = (int)$bedMatch[2];
        $plotStmt = $conn->prepare('SELECT plot_code FROM plots WHERE plot_id = ? LIMIT 1');
        $plotStmt->bind_param('i', $plot_id); $plotStmt->execute(); $plotRow = $plotStmt->get_result()->fetch_assoc(); $plotStmt->close();
        $plotPrefix = strtoupper(substr(preg_replace('/^plot_/', '', (string)($plotRow['plot_code'] ?? '')), 0, 1));
        $bedLimit = ['A'=>17, 'B'=>15, 'C'=>19][$plotPrefix] ?? 20;
        if ($bedPrefix !== $plotPrefix || $bedNumber < 1 || $bedNumber > $bedLimit) die('Please choose a bed that belongs to the selected plot.');
        $bedCheck = $conn->prepare('SELECT bed_id, bed_code FROM plot_beds WHERE plot_id = ? AND bed_number = ? LIMIT 1');
        $bedCheck->bind_param('ii', $plot_id, $bedNumber); $bedCheck->execute(); $bed = $bedCheck->get_result()->fetch_assoc(); $bedCheck->close();
        if (!$bed) {
            $bedCode = $plotPrefix . $bedNumber;
            $createBed = $conn->prepare('INSERT INTO plot_beds (plot_id, bed_number, bed_code) VALUES (?, ?, ?)');
            $createBed->bind_param('iis', $plot_id, $bedNumber, $bedCode); $createBed->execute(); $createBed->close();
            $bedCheck = $conn->prepare('SELECT bed_id, bed_code FROM plot_beds WHERE plot_id = ? AND bed_number = ? LIMIT 1');
            $bedCheck->bind_param('ii', $plot_id, $bedNumber); $bedCheck->execute(); $bed = $bedCheck->get_result()->fetch_assoc(); $bedCheck->close();
        }
        if (!$bed) die('The selected bed could not be saved.');
        $bed_id = (int)$bed['bed_id']; $transplanted_area = $bed['bed_code'];
        $occupied = $conn->prepare('SELECT crop_id FROM crops WHERE COALESCE(archived,0)=0 AND ((bed_id = ? AND bed_id IS NOT NULL) OR (plot_id = ? AND UPPER(TRIM(transplanted_area)) = ?)) LIMIT 1');
        $occupied->bind_param('iis', $bed_id, $plot_id, $transplanted_area); $occupied->execute();
        if ($occupied->get_result()->num_rows > 0) { $occupied->close(); die('That bed is already occupied by an active crop.'); }
        $occupied->close();
    }
    /*
    |--------------------------------------------------------------------------
    | CHECK THAT PLOT EXISTS
    |--------------------------------------------------------------------------
    */

    $plotCheck = $conn->prepare("
        SELECT plot_id
        FROM plots
        WHERE plot_id = ?
    ");


    if (!$plotCheck) {

        die(
            "Plot check failed: " .
            $conn->error
        );

    }


    if (!$isNursery) {
        $plotCheck->bind_param("i", $plot_id);
    }


    $plotExists = $isNursery;
    if (!$isNursery) {
        $plotCheck->execute();
        $plotExists = $plotCheck->get_result()->num_rows > 0;
    }


    $plotCheck->close();


    if (!$plotExists) {

        die(
            "The selected plot does not exist."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | INSERT CROP
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("INSERT INTO crops
        (crop_name, recipient, variety, pcs, germination_rate, date_sowing,
         target_transplant_date, transplanted_area, date_transplanted,
         expected_harvest, plot_id, bed_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "sssidsssssii",
        $crop_name,
        $recipient,
        $variety,
        $pcs,
        $germination_rate,
        $date_sowing,
        $target_transplant_date,
        $transplanted_area,
        $date_transplanted,
        $expected_harvest,
        $plot_id,
        $bed_id
    );


    /*
    |--------------------------------------------------------------------------
    | EXECUTE
    |--------------------------------------------------------------------------
    */

    if (!$stmt->execute()) {

        die(
            "Insert failed: " .
            $stmt->error
        );

    }


    /*
    |--------------------------------------------------------------------------
    | CLOSE STATEMENT
    |--------------------------------------------------------------------------
    */

    $newCropId = (int)$conn->insert_id;
    $stmt->close();
    if ($isNursery) {
        $recordSync = $conn->prepare("INSERT INTO nursery_crops (crop_id, nursery_bed_label) VALUES (?, ?)");
        if ($recordSync) {
            $recordSync->bind_param('is', $newCropId, $nurseryBedLabel);
            $recordSync->execute();
            $recordSync->close();
        }
    } else {
        $recordDate = $date_transplanted ?: date('Y-m-d');
        $recordSync = $conn->prepare("INSERT INTO plot_crop_records (crop_id, plot_id, bed_id, crop_name, variety, record_date, date_transplanted, expected_harvest, germination_rate) VALUES (?,?,?,?,?,?,?,?,?)");
        if ($recordSync) {
            $recordSync->bind_param('iiisssssd', $newCropId, $plot_id, $bed_id, $crop_name, $variety, $recordDate, $date_transplanted, $expected_harvest, $germination_rate);
            $recordSync->execute();
            $recordSync->close();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVITY LOG
    |--------------------------------------------------------------------------
    */

    logActivity(
        $conn,
        $_SESSION['user_id'],
        "Added Crop",
        "Crop: $crop_name, Plot ID: $plot_id"
    );


    /*
    |--------------------------------------------------------------------------
    | CLOSE MODAL + REFRESH PARENT
    |--------------------------------------------------------------------------
    */

    echo '
    <script>

        if (
            window.parent &&
            window.parent !== window
        ) {

            window.parent.closeEditModal();

            window.parent.location.reload();

        } else {

            window.location.href =
                "../records.php";

        }

    </script>
    ';


    exit();

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
    Add Crop
</title>


<link
    rel="stylesheet"
    href="../style.css"
>


<style>

* {
    box-sizing: border-box;
}


html,
body {

    margin: 0;

    padding: 0;

    background: #ffffff;

    font-family:
        'Poppins',
        Arial,
        sans-serif;

}


body {

    min-height: 100vh;

}


.form-wrapper {

    width: 100%;

    padding: 25px;

}


.form-card {

    width: 100%;

    max-width: 560px;

    margin: 0 auto;

    background: #ffffff;

    border-radius: 14px;

    padding: 25px;

    border-top:
        5px solid #2f6b2f;

    box-shadow:
        0 6px 18px
        rgba(0, 0, 0, 0.08);

}


.form-card h2 {

    margin:
        0 0 20px;

    color:
        #2f6b2f;

    text-align:
        center;

    font-size:
        1.35rem;

}


.form-group {

    margin-bottom:
        12px;

}


.form-card label {

    display:
        block;

    margin-bottom:
        5px;

    font-size:
        0.85rem;

    color:
        #555;

}


.form-card input,
.form-card select {

    width: 100%;

    padding:
        11px 12px;

    border-radius:
        8px;

    border:
        1px solid #ddd;

    outline: none;

    font-family:
        inherit;

    font-size:
        0.9rem;

    background:
        #fff;

}


.form-card input:focus,
.form-card select:focus {

    border-color:
        #2f6b2f;

    box-shadow:
        0 0 0 2px
        rgba(47, 107, 47, 0.15);

}


.form-card button {

    width: 100%;

    padding:
        12px;

    margin-top:
        8px;

    background:
        #2f6b2f;

    color:
        white;

    border:
        none;

    border-radius:
        8px;

    font-family:
        inherit;

    font-weight:
        600;

    cursor:
        pointer;

    transition:
        0.2s ease;

}


.form-card button:hover {

    background:
        #245224;

}


@media (max-width: 600px) {

    .form-wrapper {

        padding:
            15px;

    }


    .form-card {

        padding:
            20px;

    }

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


<div class="form-wrapper">


    <div class="form-card">


        <h2>
            Add Crop
        </h2>


        <form
            method="POST"
        >


            <!-- =====================================================
                 CROP NAME
                 ===================================================== -->

            <div class="form-group">

                <label>
                    Crop Name
                </label>

                <input
                    type="text"
                    name="crop_name"
                    placeholder="Crop Name"
                    required
                >

            </div>


            <!-- =====================================================
                 RECIPIENT
                 ===================================================== -->

            <div class="form-group">

                <label>
                    Recipient
                </label>

                <input
                    type="text"
                    name="recipient"
                    placeholder="Staff Name"
                    required
                >

            </div>


            <!-- =====================================================
                 VARIETY
                 ===================================================== -->

            <div class="form-group">

                <label>
                    Variety
                </label>

                <input
                    type="text"
                    name="variety"
                    placeholder="Variety"
                >

            </div>


            <!-- =====================================================
                 PIECES
                 ===================================================== -->

            <div class="form-group">

                <label>
                    Pieces
                </label>

                <input
                    type="number"
                    name="pcs"
                    min="0"
                    placeholder="Pieces"
                >

            </div>


            <!-- =====================================================
                 GERMINATION RATE
                 ===================================================== -->

            <div class="form-group">

                <label>
                    Germination Rate (%)
                </label>

                <input
                    type="number"
                    name="germination_rate"
                    min="0"
                    max="100"
                    step="0.01"
                    placeholder="Germination Rate"
                >

            </div>


            <!-- Plot is selected first so the next field can show only valid beds. -->
            <div class="form-group">
                <label for="crop-plot">Plot Location</label>
                <select id="crop-plot" name="plot_id" required>
                    <option value="">Select Plot First</option>
                    <option value="0" data-plot-code="nursery">Nursery</option>
                    <?php foreach ($plotRows as $plot): ?>
                        <option value="<?= (int)$plot['plot_id'] ?>" data-plot-code="<?= htmlspecialchars($plot['plot_code'], ENT_QUOTES) ?>"><?= htmlspecialchars($plot['plot_name'] ?: $plot['plot_code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="crop-bed">Area / Available Bed</label>
                <select id="crop-bed" name="transplanted_area" required disabled>
                    <option value="">Select a plot first</option>
                </select>
                <small>Only unoccupied beds for the selected plot are shown.</small>
            </div>
            <div class="form-group">
                <label for="crop-date-sowing">Date of Sowing</label>
                <input id="crop-date-sowing" type="date" name="date_sowing" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES) ?>" required>
            </div>

            <div class="form-group" id="crop-target-date-group" hidden>
                <label for="crop-target-transplant-date">Target Transplant Date</label>
                <input id="crop-target-transplant-date" type="date" name="target_transplant_date">
            </div>

            <div class="form-group" id="crop-actual-transplant-group">
                <label for="crop-date-transplanted">Date Transplanted</label>
                <input id="crop-date-transplanted" type="date" name="date_transplanted">
            </div>


            <!-- =====================================================
                 EXPECTED HARVEST
                 ===================================================== -->

            <div class="form-group">

                <label>
                    Expected Harvest
                </label>

                <input id="crop-expected-harvest" type="date" name="expected_harvest">

            </div>


            <!-- =====================================================
                 SAVE
                 ===================================================== -->

            <button
                type="submit"
            >

                Save Crop

            </button>


        </form>


    </div>


</div>


<script>
(() => {
 const plot=document.getElementById('crop-plot'), bed=document.getElementById('crop-bed');
 const targetDateGroup=document.getElementById('crop-target-date-group');
 const actualTransplantGroup=document.getElementById('crop-actual-transplant-group');
 const targetDateInput=document.getElementById('crop-target-transplant-date');
 const expectedHarvestInput=document.getElementById('crop-expected-harvest');
 const active=<?= json_encode($activeCropRows, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
 const limits={plot_a:17,plot_b:15,plot_c:19,tent_house:9,hydro:16,mno:25,nursery:20};
 const prefixes={plot_a:'A',plot_b:'B',plot_c:'C',tent_house:'T',hydro:'H',mno:'M',nursery:'N'};
 const norm=v=>String(v||'').trim().toUpperCase().replace(/\s+/g,'');
 function refresh(){
  const opt=plot.options[plot.selectedIndex], code=String(opt?.dataset.plotCode||'').toLowerCase(); bed.innerHTML='';
  const isNursery=code==='nursery';
  targetDateGroup.hidden=!isNursery;
  actualTransplantGroup.hidden=isNursery;
  targetDateInput.required=isNursery;
  expectedHarvestInput.required=isNursery;
  if(!code){bed.disabled=true;bed.required=false;bed.append(new Option('Select a plot first',''));return;}
  const prefix=prefixes[code] || code.replace(/^plot_/,'').charAt(0).toUpperCase(), limit=limits[code]||20;
  let occupied=new Set();
  if(code==='nursery'){
    const nurseryRows=active.filter(r=>String(r.plot_id)==='5' || norm(r.transplanted_area)==='NURSERY' || norm(r.nursery_bed_label).startsWith('N'));
    nurseryRows.forEach(r=>{const label=norm(r.nursery_bed_label);if(/^N(?:[1-9]|1[0-9]|20)$/.test(label))occupied.add(label);});
    // Older Nursery records may not have a saved bed label yet. Reserve the
    // first free visual bed for each such record so the selector matches the map.
    nurseryRows.forEach(r=>{const label=norm(r.nursery_bed_label);if(/^N(?:[1-9]|1[0-9]|20)$/.test(label))return;for(let n=1;n<=limit;n++){const c='N'+n;if(!occupied.has(c)){occupied.add(c);break;}}});
  }else{
    occupied=new Set(active.filter(r=>String(r.plot_id)===String(plot.value)).map(r=>norm(r.transplanted_area)).filter(Boolean));
  }
  const available=[]; for(let n=1;n<=limit;n++){const c=prefix+n;if(!occupied.has(c))available.push(c);}
  bed.append(new Option(available.length?'Select an available bed':'No unoccupied beds available',''));
  available.forEach(c=>bed.append(new Option(c,c))); bed.disabled=!available.length; bed.required=!!available.length;
 }
 plot.addEventListener('change',refresh); refresh();
})();
</script>
</body>

</html>