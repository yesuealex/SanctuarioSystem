
<?php

session_start();

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
| GET CROP ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id'])
    ? intval($_GET['id'])
    : 0;


if ($id <= 0) {

    exit("Invalid ID");

}


/*
|--------------------------------------------------------------------------
| FETCH CROP DATA
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM crops
    WHERE crop_id = ?
");

$stmt->bind_param(
    "i",
    $id
);

$stmt->execute();

$data = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();


if (!$data) {

    exit("Crop not found");

}

$bed_id = intval($data['bed_id'] ?? 0);
$nurseryBedLabel = '';
$nurseryBedStmt = $conn->prepare("SELECT nursery_bed_label FROM nursery_crops WHERE crop_id=? AND COALESCE(archived,0)=0 LIMIT 1");
if ($nurseryBedStmt) {
    $nurseryBedStmt->bind_param('i', $id);
    $nurseryBedStmt->execute();
    $nurseryBedLabel = (string)($nurseryBedStmt->get_result()->fetch_assoc()['nursery_bed_label'] ?? '');
    $nurseryBedStmt->close();
}
$harvestData = [];
$harvestRecordId = 0;
$requestedHarvestId = intval($_GET['harvest_id'] ?? 0);
$harvestFilter = $requestedHarvestId > 0 ? ' AND h.harvest_id=' . $requestedHarvestId : '';
$harvestStmt = $conn->prepare("SELECT pcr.record_id, h.harvest_id, h.harvest_date, h.total_quantity, h.total_unit,
        h.sales_quantity, h.sales_unit, h.sales_amount, h.farm_use_quantity, h.farm_use_unit,
        h.animal_feed_quantity, h.animal_feed_unit, h.rejected_quantity, h.rejected_unit
    FROM plot_crop_records pcr
    LEFT JOIN plot_crop_harvests h ON h.record_id=pcr.record_id
    WHERE pcr.crop_id=? AND COALESCE(pcr.archived,0)=0" . $harvestFilter . "
    ORDER BY h.harvest_date DESC, h.harvest_id DESC
    LIMIT 1");
if ($harvestStmt) {
    $harvestStmt->bind_param("i", $id);
    $harvestStmt->execute();
    $harvestData = $harvestStmt->get_result()->fetch_assoc() ?: [];
    $harvestRecordId = intval($harvestData['record_id'] ?? 0);
    $harvestStmt->close();
}


/*
|--------------------------------------------------------------------------
| FETCH PLOTS
|--------------------------------------------------------------------------
|
| We get the plots from the plots table.
|
| crops.plot_id
|       ↓
| plots.plot_id
|
| plot_name is only used for displaying the name.
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


if (!$plotsResult) {

    die(
        "Failed to load plots: " .
        $conn->error
    );

}


$bedsByPlot = [];
$bedsResult = $conn->query("SELECT bed_id, plot_id, bed_number, bed_code FROM plot_beds ORDER BY plot_id ASC, bed_number ASC");
if ($bedsResult) {
    while ($bedRow = $bedsResult->fetch_assoc()) {
        $bedsByPlot[(int)$bedRow['plot_id']][] = $bedRow;
    }
}
/*
|--------------------------------------------------------------------------
| UPDATE CROP
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /*
    |----------------------------------------------------------------------
    | GET FORM VALUES
    |----------------------------------------------------------------------
    */

    $crop_name = trim(
        $_POST['crop_name'] ?? ''
    );


    /*
    | IMPORTANT:
    | plot_id is now the actual foreign key.
    */

    $plot_id = intval(
        $_POST['plot_id'] ?? 0
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


    $target_area = trim(
        $_POST['target_area'] ?? ''
    );


    $transplanted_area = trim(
        $_POST['transplanted_area'] ?? ''
    );


    $date_transplanted =
        !empty($_POST['date_transplanted'])
            ? $_POST['date_transplanted']
            : null;


    $expected_harvest =
        !empty($_POST['expected_harvest'])
            ? $_POST['expected_harvest']
            : null;

    $harvest_date = trim($_POST['harvest_date'] ?? '');
    $harvest_record_id = intval($_POST['harvest_record_id'] ?? 0);
    $harvest_id = intval($_POST['harvest_id'] ?? 0);
    $nullableNumber = static function ($key) {
        $value = trim((string)($_POST[$key] ?? ''));
        return $value === '' ? null : (is_numeric($value) && (float)$value >= 0 ? (float)$value : false);
    };
    $total_quantity = $nullableNumber('total_quantity');
    $sales_quantity = $nullableNumber('sales_quantity');
    $sales_amount = $nullableNumber('sales_amount');
    $farm_use_quantity = $nullableNumber('farm_use_quantity');
    $animal_feed_quantity = $nullableNumber('animal_feed_quantity');
    $rejected_quantity = $nullableNumber('rejected_quantity');
    $total_unit = trim($_POST['total_unit'] ?? '');
    $sales_unit = trim($_POST['sales_unit'] ?? '');
    $farm_use_unit = trim($_POST['farm_use_unit'] ?? '');
    $animal_feed_unit = trim($_POST['animal_feed_unit'] ?? '');
    $rejected_unit = trim($_POST['rejected_unit'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($crop_name === '') {

        die("Crop name is required.");

    }


    if ($target_area === '') {

        die("Target area is required.");

    }


    if ($recipient === '') {

        die("Recipient is required.");

    }


    if ($plot_id <= 0) {

        die("Please select a valid plot.");

    }

    $bed_id = intval($_POST['bed_id'] ?? 0);
    $nurseryBedLabel = trim((string)($_POST['nursery_bed_label'] ?? ''));
    $selectedBed = null;
    if ($bed_id > 0) {
        $bedCheck = $conn->prepare("SELECT bed_id, bed_code FROM plot_beds WHERE bed_id=? AND plot_id=? LIMIT 1");
        $bedCheck->bind_param('ii', $bed_id, $plot_id);
        $bedCheck->execute();
        $selectedBed = $bedCheck->get_result()->fetch_assoc();
        $bedCheck->close();
        if (!$selectedBed) die("Please select a valid bed for the selected plot.");
        $occupiedCheck = $conn->prepare("SELECT crop_id FROM crops WHERE bed_id=? AND crop_id<>? AND COALESCE(archived,0)=0 AND COALESCE(transferred_out,0)=0 LIMIT 1");
        $occupiedCheck->bind_param('ii', $bed_id, $id);
        $occupiedCheck->execute();
        $occupied = $occupiedCheck->get_result()->fetch_assoc();
        $occupiedCheck->close();
        if ($occupied) die("That bed is already occupied by another active crop.");
        $transplanted_area = (string)$selectedBed['bed_code'];
    }
    if (in_array(false, [$total_quantity, $sales_quantity, $sales_amount, $farm_use_quantity, $animal_feed_quantity, $rejected_quantity], true)) {
        die("Harvest quantities and sales amount must be valid non-negative numbers.");
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

    $plotCheck->bind_param(
        "i",
        $plot_id
    );

    $plotCheck->execute();

    $plotExists =
        $plotCheck
            ->get_result()
            ->num_rows > 0;

    $plotCheck->close();


    if (!$plotExists) {

        die("Selected plot does not exist.");

    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE DATABASE
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE crops
        SET
            crop_name = ?,
            plot_id = ?,
            recipient = ?,
            variety = ?,
            pcs = ?,
            germination_rate = ?,
            target_area = ?,
            transplanted_area = ?,
            date_transplanted = ?,
            expected_harvest = ?,
            bed_id = ?
        WHERE crop_id = ?
    ");


    if (!$stmt) {

        die(
            "Prepare failed: " .
            $conn->error
        );

    }


    /*
    |--------------------------------------------------------------------------
    | BIND VALUES
    |--------------------------------------------------------------------------
    |
    | s = string
    | i = integer
    | d = decimal/double
    |
    | 10 values:
    |
    | 1. crop_name
    | 2. plot_id
    | 3. recipient
    | 4. variety
    | 5. pcs
    | 6. germination_rate
    | 7. transplanted_area
    | 8. date_transplanted
    | 9. expected_harvest
    | 10. id
    |
    |--------------------------------------------------------------------------
    */

    $stmt->bind_param(
        "sissidssssii",
        $crop_name,
        $plot_id,
        $recipient,
        $variety,
        $pcs,
        $germination_rate,
        $target_area,
        $transplanted_area,
        $date_transplanted,
        $expected_harvest,
        $bed_id,
        $id
    );


    /*
    |--------------------------------------------------------------------------
    | EXECUTE UPDATE
    |--------------------------------------------------------------------------
    */

    if (!$stmt->execute()) {

        die(
            "Update failed: " .
            $stmt->error
        );

    }


    $stmt->close();

    // Keep the separate plot record aligned with the shared crop edit.
    $recordSync = $conn->prepare("UPDATE plot_crop_records SET plot_id=?, bed_id=NULLIF(?,0), crop_name=?, variety=?, date_transplanted=?, expected_harvest=?, germination_rate=? WHERE crop_id=? AND COALESCE(archived,0)=0");
    if ($recordSync) {
        $recordSync->bind_param('iissssdi', $plot_id, $bed_id, $crop_name, $variety, $date_transplanted, $expected_harvest, $germination_rate, $id);
        $recordSync->execute();
        $recordSync->close();
    }

    $nurserySync = $conn->prepare("UPDATE nursery_crops SET nursery_bed_label=? WHERE crop_id=? AND COALESCE(archived,0)=0");
    if ($nurserySync) {
        $nurserySync->bind_param('si', $nurseryBedLabel, $id);
        $nurserySync->execute();
        $nurserySync->close();
    }
    if ($harvest_record_id > 0 && $harvest_date !== '') {
        if ($harvest_id > 0) {
            $harvestUpdate = $conn->prepare("UPDATE plot_crop_harvests SET harvest_date=?, total_quantity=?, total_unit=?, sales_quantity=?, sales_unit=?, sales_amount=?, farm_use_quantity=?, farm_use_unit=?, animal_feed_quantity=?, animal_feed_unit=?, rejected_quantity=?, rejected_unit=? WHERE harvest_id=? AND record_id=?");
            $harvestUpdate->bind_param("sdsdsdsdsdsdii", $harvest_date, $total_quantity, $total_unit, $sales_quantity, $sales_unit, $sales_amount, $farm_use_quantity, $farm_use_unit, $animal_feed_quantity, $animal_feed_unit, $rejected_quantity, $rejected_unit, $harvest_id, $harvest_record_id);
            if (!$harvestUpdate->execute()) die("Harvest update failed: " . $harvestUpdate->error);
            $harvestUpdate->close();
        } else {
            $harvestInsert = $conn->prepare("INSERT INTO plot_crop_harvests (record_id, harvest_date, total_quantity, total_unit, sales_quantity, sales_unit, sales_amount, farm_use_quantity, farm_use_unit, animal_feed_quantity, animal_feed_unit, rejected_quantity, rejected_unit) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $harvestInsert->bind_param("isdsdsdsdsdsd", $harvest_record_id, $harvest_date, $total_quantity, $total_unit, $sales_quantity, $sales_unit, $sales_amount, $farm_use_quantity, $farm_use_unit, $animal_feed_quantity, $animal_feed_unit, $rejected_quantity, $rejected_unit);
            if (!$harvestInsert->execute()) die("Harvest save failed: " . $harvestInsert->error);
            $harvestInsert->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | LOG ACTIVITY
    |--------------------------------------------------------------------------
    */

    logActivity(
        $conn,
        $_SESSION['user_id'],
        "Edited Crop",
        "Crop ID: $id updated"
    );


    /*
    |--------------------------------------------------------------------------
    | CLOSE MODAL + REFRESH PARENT PAGE
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
    Edit Crop
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


.edit-modal {

    width: 100%;

    padding: 25px;

}


.form-group {

    margin-bottom:
        12px;

}


.edit-modal label {

    display:
        block;

    margin-bottom:
        5px;

    font-size:
        0.85rem;

    color:
        #555;

}


.edit-modal input,
.edit-modal select {

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


.edit-modal input:focus,
.edit-modal select:focus {

    border-color:
        #2f6b2f;

    box-shadow:
        0 0 0 2px
        rgba(47,107,47,0.15);

}


.edit-modal button {

    width: 100%;

    padding:
        12px;

    margin-top:
        8px;

    background:
        #2f6b2f;

    color:
        white;

    border: none;

    border-radius:
        8px;

    font-family:
        inherit;

    font-weight:
        600;

    cursor:
        pointer;

}


.edit-modal button:hover {

    background:
        #245224;

}


.record-section-title {

    margin:
        24px 0 14px;

    padding-top:
        18px;

    border-top:
        1px solid #e5e5e5;

    color:
        #2f6b2f;

    font-size:
        1.05rem;

}


.record-section-note {

    margin:
        -4px 0 14px;

    color:
        #777;

    font-size:
        0.78rem;

}


.transplant-section-title {

    margin-top:
        0;

    padding-top:
        0;

    border-top:
        none;

}


.transplant-hidden {

    display: none;

}


@media (max-width: 600px) {

    .edit-modal {

        padding:
            20px;

    }

}

</style>

</head>


<body>


<form method="POST" class="edit-modal">


            <h3 class="record-section-title transplant-section-title">Transplant Records</h3>

            <div class="form-group">
                <label>Date</label>
                <input
                    type="date"
                    name="date_transplanted"
                    value="<?= htmlspecialchars($data['date_transplanted'] ?? '') ?>"
                >
            </div>


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
                    value="<?= htmlspecialchars(
                        $data['crop_name'] ?? ''
                    ) ?>"
                    required
                >

            </div>


            <!-- =====================================================
                 RECIPIENT
                 ===================================================== -->

            <div class="form-group transplant-hidden">

                <label>
                    Staff Name
                </label>

                <input
                    type="text"
                    name="recipient"
                    value="<?= htmlspecialchars(
                        $data['recipient'] ?? ''
                    ) ?>"
                    required
                >

            </div>


            <!-- =====================================================
                 VARIETY
                 ===================================================== -->

            <div class="form-group transplant-hidden">

                <label>
                    Variety
                </label>

                <input
                    type="text"
                    name="variety"
                    value="<?= htmlspecialchars(
                        $data['variety'] ?? ''
                    ) ?>"
                >

            </div>


            <!-- =====================================================
                 PIECES
                 ===================================================== -->

            <div class="form-group">

                <label>
                    Number of Crops
                </label>

                <input
                    type="number"
                    name="pcs"
                    min="0"
                    value="<?= htmlspecialchars(
                        $data['pcs'] ?? 0
                    ) ?>"
                >

            </div>


            <!-- =====================================================
                 GERMINATION RATE
                 ===================================================== -->

            <div class="form-group transplant-hidden">

                <label>
                    Germination Rate (%)
                </label>

                <input
                    type="number"
                    name="germination_rate"
                    min="0"
                    max="100"
                    step="0.01"
                    value="<?= htmlspecialchars(
                        $data['germination_rate'] ?? 0
                    ) ?>"
                >

            </div>


            <!-- =====================================================
                 TRANSPLANTED AREA
                 ===================================================== -->

            <div class="form-group transplant-hidden">

                <label>
                    Target Area
                </label>

                <input
                    type="text"
                    name="target_area"
                    value="<?= htmlspecialchars(
                        $data['target_area'] ?? ''
                    ) ?>"
                    required
                >

            </div>


            <div class="form-group transplant-hidden">

                <label>
                    Area
                </label>

                <input
                    type="text"
                    name="transplanted_area"
                    value="<?= htmlspecialchars(
                        $data['transplanted_area'] ?? ''
                    ) ?>"
                >

            </div>


            <!-- =====================================================
                 EXPECTED HARVEST
                 ===================================================== -->

            <div class="form-group transplant-hidden">

                <label>
                    Expected Harvest
                </label>

                <input
                    type="date"
                    name="expected_harvest"
                    value="<?= htmlspecialchars(
                        $data['expected_harvest'] ?? ''
                    ) ?>"
                >

            </div>


            <!-- =====================================================
                 PLOT LOCATION
                 ===================================================== -->

            <div class="form-group transplant-hidden">

                <label>
                    Plot Location
                </label>


                <select
                    name="plot_id"
                    required
                >


                    <option value="">
                        Select Plot
                    </option>


                    <?php while (
                        $plot =
                        $plotsResult->fetch_assoc()
                    ): ?>


                        <option
                            value="<?= intval(
                                $plot['plot_id']
                            ) ?>"
                            <?= (
                                intval(
                                    $data['plot_id'] ?? 0
                                )
                                ===
                                intval(
                                    $plot['plot_id']
                                )
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= htmlspecialchars(
                                $plot['plot_name']
                            ) ?>

                        </option>


                    <?php endwhile; ?>


                </select>

            </div>


            <div class="form-group transplant-hidden">
                <label for="edit-bed-id">Bed Number</label>
                <select id="edit-bed-id" name="bed_id">
                    <option value="0" data-plot-id="0">No bed assigned</option>
                    <?php foreach ($bedsByPlot as $bedPlotId => $plotBeds): foreach ($plotBeds as $bed): ?>
                        <option value="<?= (int)$bed['bed_id'] ?>" data-plot-id="<?= (int)$bedPlotId ?>" <?= (int)$bed['bed_id'] === (int)$bed_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($bed['bed_code'] ?: ('Bed ' . $bed['bed_number'])) ?>
                        </option>
                    <?php endforeach; endforeach; ?>
                </select>
                <small class="record-section-note">Only beds belonging to the selected plot are available. Occupied beds are rejected when saved.</small>
            </div>
            <div class="form-group transplant-hidden" id="nursery-bed-field">
                <label for="nursery-bed-label">Nursery Bed Number</label>
                <input id="nursery-bed-label" type="text" name="nursery_bed_label" value="<?= htmlspecialchars($nurseryBedLabel) ?>" placeholder="e.g. Nursery Bed 1">
            </div>
            <?php if ($harvestRecordId > 0): ?>
                <h3 class="record-section-title">Harvest Record</h3>
                <p class="record-section-note">Update the harvest information shown in the crop records table.</p>
                <input type="hidden" name="harvest_record_id" value="<?= $harvestRecordId ?>">
                <input type="hidden" name="harvest_id" value="<?= intval($harvestData['harvest_id'] ?? 0) ?>">

                <div class="form-group">
                    <label>Date of Pull / Harvest</label>
                    <input type="date" name="harvest_date" value="<?= htmlspecialchars($harvestData['harvest_date'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Total Harvest Quantity</label>
                    <input type="number" min="0" step="0.001" name="total_quantity" value="<?= htmlspecialchars($harvestData['total_quantity'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Total Harvest Unit</label>
                    <input type="text" name="total_unit" placeholder="kg, g, pcs" value="<?= htmlspecialchars($harvestData['total_unit'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Sales Quantity</label>
                    <input type="number" min="0" step="0.001" name="sales_quantity" value="<?= htmlspecialchars($harvestData['sales_quantity'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Sales Unit</label>
                    <input type="text" name="sales_unit" placeholder="kg, g, pcs" value="<?= htmlspecialchars($harvestData['sales_unit'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Sales Amount (PHP)</label>
                    <input type="number" min="0" step="0.01" name="sales_amount" value="<?= htmlspecialchars($harvestData['sales_amount'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Farm Use Quantity</label>
                    <input type="number" min="0" step="0.001" name="farm_use_quantity" value="<?= htmlspecialchars($harvestData['farm_use_quantity'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Farm Use Unit</label>
                    <input type="text" name="farm_use_unit" placeholder="kg, g, pcs" value="<?= htmlspecialchars($harvestData['farm_use_unit'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Animal Feed Quantity</label>
                    <input type="number" min="0" step="0.001" name="animal_feed_quantity" value="<?= htmlspecialchars($harvestData['animal_feed_quantity'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Animal Feed Unit</label>
                    <input type="text" name="animal_feed_unit" placeholder="kg, g, pcs" value="<?= htmlspecialchars($harvestData['animal_feed_unit'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Reject Quantity</label>
                    <input type="number" min="0" step="0.001" name="rejected_quantity" value="<?= htmlspecialchars($harvestData['rejected_quantity'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Reject Unit</label>
                    <input type="text" name="rejected_unit" placeholder="kg, g, pcs" value="<?= htmlspecialchars($harvestData['rejected_unit'] ?? '') ?>">
                </div>
            <?php endif; ?>


            <!-- =====================================================
                 UPDATE BUTTON
                 ===================================================== -->

            <button
                type="submit"
            >

                Update Crop

            </button>


</form>


<script>
(function () {
    const plot = document.querySelector('select[name="plot_id"]');
    const bed = document.getElementById('edit-bed-id');
    const nurseryField = document.getElementById('nursery-bed-field');
    if (!plot || !bed) return;
    function filterEditBeds() {
        const plotId = String(plot.value || '');
        const selectedPlotText = String(plot.options[plot.selectedIndex]?.text || '').toLowerCase();
        const isNursery = selectedPlotText.includes('nursery');
        if (nurseryField) nurseryField.style.display = isNursery ? '' : 'none';
        let selectedVisible = false;
        Array.from(bed.options).forEach(function (option) {
            const visible = option.value === '0' || option.dataset.plotId === plotId;
            option.hidden = !visible;
            if (visible && option.selected) selectedVisible = true;
        });
        if (!selectedVisible) bed.value = '0';
    }
    plot.addEventListener('change', filterEditBeds);
    filterEditBeds();
})();
</script>
</body>

</html>