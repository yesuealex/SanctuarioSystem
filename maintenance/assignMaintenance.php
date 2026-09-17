<?php
session_start();

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
| ADMIN CHECK
|--------------------------------------------------------------------------
*/
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../adminDashboard.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/
require_once '../db.php';


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}


$message = '';
$error = '';


/*
|--------------------------------------------------------------------------
| ASSIGN MAINTENANCE REQUEST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $requestId = (int)($_POST['request_id'] ?? 0);
        $staffId   = (int)($_POST['staff_id'] ?? 0);


        /*
        |--------------------------------------------------------------------------
        | BASIC VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($requestId <= 0) {
            throw new Exception('Invalid maintenance request.');
        }

        if ($staffId <= 0) {
            throw new Exception('Please select maintenance staff.');
        }


        /*
        |--------------------------------------------------------------------------
        | VERIFY STAFF ACCOUNT
        |--------------------------------------------------------------------------
        |
        | Your users table has:
        | user_id
        | name
        | username
        | password
        | role
        |
        | There is NO email column.
        |--------------------------------------------------------------------------
        */

        $check = $conn->prepare("
            SELECT user_id, name, username, role
            FROM users
            WHERE user_id = ?
            AND LOWER(TRIM(role)) = 'maintenance'
            LIMIT 1
        ");

        if (!$check) {
            throw new Exception(
                'Could not verify maintenance staff: ' . $conn->error
            );
        }

        $check->bind_param("i", $staffId);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows === 0) {

            $check->close();

            throw new Exception(
                'Selected user is not a maintenance staff account.'
            );
        }

        $check->close();


        /*
        |--------------------------------------------------------------------------
        | ASSIGN REQUEST
        |--------------------------------------------------------------------------
        |
        | If the request is Pending,
        | assigning staff automatically changes it to Ongoing.
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            UPDATE maintenance_requests
            SET
                assigned_to = ?,
                assigned_at = NOW(),
                status = CASE
                    WHEN status = 'Pending'
                    THEN 'Ongoing'
                    ELSE status
                END
            WHERE request_id = ?
        ");

        if (!$stmt) {
            throw new Exception(
                'Could not prepare assignment: ' . $conn->error
            );
        }

        $stmt->bind_param(
            "ii",
            $staffId,
            $requestId
        );

        $stmt->execute();

        if ($stmt->affected_rows < 0) {
            throw new Exception(
                'The maintenance request could not be assigned.'
            );
        }

        $stmt->close();


        $message = 'Maintenance request assigned successfully.';


    } catch (Throwable $ex) {

        $error = $ex->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| GET MAINTENANCE STAFF
|--------------------------------------------------------------------------
*/

$staff = [];

try {

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    | Do NOT select email because your users table does not have
    | an email column.
    |--------------------------------------------------------------------------
    */

    $result = $conn->query("
        SELECT
            user_id,
            name,
            username,
            role
        FROM users
        WHERE LOWER(TRIM(role)) = 'maintenance'
        ORDER BY name, username
    ");

    if (!$result) {
        throw new Exception(
            'Could not load maintenance staff: ' . $conn->error
        );
    }


    while ($row = $result->fetch_assoc()) {

        $staff[] = $row;
    }


} catch (Throwable $ex) {

    $error = $error ?: $ex->getMessage();
}


/*
|--------------------------------------------------------------------------
| GET MAINTENANCE REQUESTS
|--------------------------------------------------------------------------
*/

$requests = [];

try {

    $result = $conn->query("
        SELECT
            mr.*,
            u.name AS assigned_name,
            u.username AS assigned_username
        FROM maintenance_requests mr

        LEFT JOIN users u
            ON u.user_id = mr.assigned_to

        WHERE mr.status NOT IN ('Completed', 'Cancelled')

        ORDER BY mr.request_id DESC
    ");


    if (!$result) {

        throw new Exception(
            'Could not load maintenance requests: ' . $conn->error
        );
    }


    while ($row = $result->fetch_assoc()) {

        $requests[] = $row;
    }


} catch (Throwable $ex) {

    $error = $error ?: $ex->getMessage();
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
        Assign Maintenance | Sanctuario Nature Farm
    </title>


    <!-- Poppins -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- Material Symbols -->

    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:wght@400;500;600"
        rel="stylesheet"
    >


    <!-- Existing CSS -->

    <link
        rel="stylesheet"
        href="../style.css"
    >

    <link
        rel="stylesheet"
        href="../sidebar.css"
    >

    <link
        rel="stylesheet"
        href="maintenance.css"
    >


    <style>

        .page {

            padding: 24px;

        }


        .box {

            background: #ffffff;

            border-radius: 16px;

            padding: 22px;

            box-shadow:
                0 4px 18px rgba(0,0,0,.04);

        }


        .btn {

            padding: 9px 13px;

            border: 0;

            border-radius: 8px;

            background: #2f6f45;

            color: #ffffff;

            font-weight: 600;

            cursor: pointer;

            font-family: Poppins, sans-serif;

        }


        .btn:hover {

            opacity: .9;

        }


        .msg,
        .err {

            padding: 12px 15px;

            border-radius: 9px;

            margin-bottom: 15px;

        }


        .msg {

            background: #eaf6ed;

            color: #27623a;

        }


        .err {

            background: #fff0ef;

            color: #9b2c2c;

        }


        table {

            width: 100%;

            border-collapse: collapse;

        }


        th,
        td {

            padding: 12px 9px;

            border-bottom:
                1px solid #e8ece9;

            text-align: left;

            font-size: 13px;

        }


        th {

            font-weight: 600;

        }


        .muted {

            font-size: 12px;

            color: #718077;

        }


        .table-wrap {

            overflow-x: auto;

        }


        select {

            padding: 10px;

            border:
                1px solid #dce4de;

            border-radius: 9px;

            font-family: Poppins, sans-serif;

            font-size: 14px;

        }


        .assign-form {

            display: flex;

            gap: 8px;

            align-items: center;

        }


        .staff-name {

            font-weight: 600;

        }


        .staff-username {

            font-size: 12px;

            color: #718077;

        }


        .empty-message {

            margin-top: 15px;

            padding: 15px;

            background: #f7faf7;

            border-radius: 10px;

            color: #68756d;

        }

    </style>

</head>


<body>


<div class="layout">


    <!-- SIDEBAR -->

    <?php include '../sidebar.php'; ?>


    <!-- MAIN -->

    <main class="dashboard">


        <!-- TOP RIGHT BAR -->

        <?php include '../topRightBar.php'; ?>


        <div class="page">


            <!-- PAGE TITLE -->

            <h1>
                Assign Maintenance
            </h1>


            <p>

                Assign pending or ongoing maintenance
                requests to maintenance staff.

            </p>


            <!-- SUCCESS -->

            <?php if ($message): ?>

                <div class="msg">

                    <?= e($message) ?>

                </div>

            <?php endif; ?>


            <!-- ERROR -->

            <?php if ($error): ?>

                <div class="err">

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 REQUEST TABLE
                 ================================================= -->

            <div class="box">


                <h2>
                    Maintenance Requests
                </h2>


                <p class="muted">

                    Select a maintenance staff member
                    to assign the request.

                </p>


                <div class="table-wrap">


                    <table>


                        <thead>

                            <tr>

                                <th>
                                    Request
                                </th>

                                <th>
                                    Location
                                </th>

                                <th>
                                    Priority
                                </th>

                                <th>
                                    Current Staff
                                </th>

                                <th>
                                    Assign To
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php if (!$requests): ?>


                            <tr>

                                <td colspan="5">

                                    No uncompleted
                                    maintenance requests
                                    available.

                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach ($requests as $r): ?>


                                <tr>


                                    <!-- REQUEST -->

                                    <td>

                                        <strong>

                                            <?= e(
                                                $r['title']
                                            ) ?>

                                        </strong>


                                        <br>


                                        <span class="muted">

                                            Request #

                                            <?= e(
                                                $r['request_id']
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- LOCATION -->

                                    <td>

                                        <?= e(
                                            $r['location']
                                            ?: 'No location'
                                        ) ?>

                                    </td>


                                    <!-- PRIORITY -->

                                    <td>

                                        <?= e(
                                            $r['priority']
                                        ) ?>

                                    </td>


                                    <!-- CURRENT STAFF -->

                                    <td>

                                        <?php if (
                                            !empty(
                                                $r['assigned_name']
                                            )
                                        ): ?>

                                            <span class="staff-name">

                                                <?= e(
                                                    $r['assigned_name']
                                                ) ?>

                                            </span>

                                            <br>

                                            <span class="staff-username">

                                                @<?= e(
                                                    $r['assigned_username']
                                                ) ?>

                                            </span>

                                        <?php else: ?>

                                            <span class="muted">

                                                Unassigned

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- ASSIGN -->

                                    <td>


                                        <form
                                            method="post"
                                            class="assign-form"
                                        >


                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= e(
                                                    $r['request_id']
                                                ) ?>"
                                            >


                                            <select
                                                name="staff_id"
                                                required
                                            >


                                                <option
                                                    value=""
                                                >

                                                    Select staff

                                                </option>


                                                <?php foreach (
                                                    $staff
                                                    as $s
                                                ): ?>


                                                    <option
                                                        value="<?= e(
                                                            $s['user_id']
                                                        ) ?>"
                                                        <?= (
                                                            (int)$r['assigned_to']
                                                            ===
                                                            (int)$s['user_id']
                                                        )
                                                        ? 'selected'
                                                        : ''
                                                        ?>
                                                    >

                                                        <?= e(
                                                            $s['name']
                                                            ?: $s['username']
                                                        ) ?>

                                                        -
                                                        <?= e(
                                                            $s['username']
                                                        ) ?>

                                                    </option>


                                                <?php endforeach; ?>


                                            </select>


                                            <button
                                                type="submit"
                                                class="btn"
                                            >

                                                Assign

                                            </button>


                                        </form>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        <?php endif; ?>


                        </tbody>


                    </table>


                </div>


                <!-- NO STAFF -->

                <?php if (!$staff): ?>

                    <div class="empty-message">

                        No maintenance staff accounts
                        were found.

                    </div>

                <?php else: ?>

                    <div class="empty-message">

                        <strong>
                            Available Maintenance Staff:
                        </strong>

                        <?php foreach (
                            $staff
                            as $s
                        ): ?>

                            <?= e(
                                $s['name']
                                ?: $s['username']
                            ) ?>

                            (<?= e(
                                $s['username']
                            ) ?>)

                            <?php if (
                                $s !== end($staff)
                            ): ?>

                                ,

                            <?php endif; ?>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>


            </div>


        </div>


    </main>


</div>


</body>

</html>