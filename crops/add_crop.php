
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


if (!$plotsResult) {

    die(
        "Failed to load plots: " .
        $conn->error
    );

}


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


    $isNursery = $plot_id === 0 || strtoupper(trim($transplanted_area)) === 'NURSERY';
    if ($plot_id <= 0 && !$isNursery) {
        die("Plot location is required.");
    }
    if ($isNursery) {
        $plot_id = null;
        $transplanted_area = 'NURSERY';
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

    $stmt = $conn->prepare("
        INSERT INTO crops
        (
            crop_name,
            recipient,
            variety,
            pcs,
            germination_rate,
            transplanted_area,
            date_transplanted,
            expected_harvest,
            plot_id
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");


    if (!$stmt) {

        die(
            "Prepare failed: " .
            $conn->error
        );

    }


    /*
    |--------------------------------------------------------------------------
    | BIND PARAMETERS
    |--------------------------------------------------------------------------
    |
    | s = string
    | i = integer
    | d = decimal/double
    |
    | 9 values:
    |
    | 1. crop_name              s
    | 2. recipient              s
    | 3. variety                s
    | 4. pcs                    i
    | 5. germination_rate       d
    | 6. transplanted_area      s
    | 7. date_transplanted      s
    | 8. expected_harvest       s
    | 9. plot_id                i
    |
    |--------------------------------------------------------------------------
    */

    if ($isNursery) {
        $stmt = $conn->prepare("INSERT INTO crops (crop_name, recipient, variety, pcs, germination_rate, transplanted_area, date_transplanted, expected_harvest, plot_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)");
        $stmt->bind_param("sssidsss", $crop_name, $recipient, $variety, $pcs, $germination_rate, $transplanted_area, $date_transplanted, $expected_harvest);
    } else {
        $stmt->bind_param("sssidsssi", $crop_name, $recipient, $variety, $pcs, $germination_rate, $transplanted_area, $date_transplanted, $expected_harvest, $plot_id);
    }


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

    $stmt->close();


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


            <!-- =====================================================
                 AREA
                 ===================================================== -->

            <div class="form-group">

                <label>
                    Area
                </label>

                <input
                    type="text"
                    name="transplanted_area"
                    placeholder="Area"
                >

            </div>


            <!-- =====================================================
                 DATE TRANSPLANTED
                 ===================================================== -->

            <div class="form-group">

                <label>
                    Date Transplanted
                </label>

                <input
                    type="date"
                    name="date_transplanted"
                >

            </div>


            <!-- =====================================================
                 EXPECTED HARVEST
                 ===================================================== -->

            <div class="form-group">

                <label>
                    Expected Harvest
                </label>

                <input
                    type="date"
                    name="expected_harvest"
                >

            </div>


            <!-- =====================================================
                 PLOT LOCATION
                 ===================================================== -->

            <div class="form-group">

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
                    <option value="0" data-location="NURSERY">
                        Nursery
                    </option>


                    <?php while (
                        $plot =
                        $plotsResult->fetch_assoc()
                    ): ?>


                        <option
                            value="<?= intval(
                                $plot['plot_id']
                            ) ?>"
                        >

                            <?= htmlspecialchars(
                                $plot['plot_name']
                            ) ?>

                        </option>


                    <?php endwhile; ?>


                </select>

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


</body>

</html>