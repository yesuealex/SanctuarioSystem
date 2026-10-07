
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
            expected_harvest = ?
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
        "sissidssssi",
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
        rgba(0,0,0,0.08);

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
        rgba(47,107,47,0.15);

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
            Edit Crop
        </h2>


        <form method="POST">


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

            <div class="form-group">

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

            <div class="form-group">

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
                    Pieces
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
                    value="<?= htmlspecialchars(
                        $data['germination_rate'] ?? 0
                    ) ?>"
                >

            </div>


            <!-- =====================================================
                 TRANSPLANTED AREA
                 ===================================================== -->

            <div class="form-group">

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


            <div class="form-group">

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
                 DATE TRANSPLANTED
                 ===================================================== -->

            <div class="form-group">

                <label>
                    Date Transplanted
                </label>

                <input
                    type="date"
                    name="date_transplanted"
                    value="<?= htmlspecialchars(
                        $data['date_transplanted'] ?? ''
                    ) ?>"
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
                    value="<?= htmlspecialchars(
                        $data['expected_harvest'] ?? ''
                    ) ?>"
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


            <!-- =====================================================
                 UPDATE BUTTON
                 ===================================================== -->

            <button
                type="submit"
            >

                Update Crop

            </button>


        </form>


    </div>


</div>


</body>

</html>
