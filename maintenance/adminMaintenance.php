<?php

session_start();

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

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
require_once '../archive/archive_functions.php';


/*
|--------------------------------------------------------------------------
| HELPER FUNCTION
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$message = '';
$error = '';

$totalRequests = 0;
$pendingRequests = 0;
$ongoingRequests = 0;
$completedRequests = 0;

$activeRequests = [];
$archiveRequests = [];


/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE FROM SESSION
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['maintenance_message'])) {

    $message = $_SESSION['maintenance_message'];

    unset($_SESSION['maintenance_message']);
}


/*
|--------------------------------------------------------------------------
| CHECK DATABASE TABLE
|--------------------------------------------------------------------------
*/

$tableReady = true;

try {

    $checkTable = $conn->query("
        SELECT request_id
        FROM maintenance_requests
        LIMIT 1
    ");

    if (!$checkTable) {
        $tableReady = false;

        $error =
            "The maintenance_requests table could not be accessed.";
    }

} catch (Throwable $ex) {

    $tableReady = false;

    $error =
        "The maintenance_requests table could not be accessed.";
}


try {
    if ($tableReady) {
        ensureArchiveTable($conn, 'maintenance_requests');
    }
} catch (Throwable $archiveSetupError) {
    if (!$error) {
        $error = $archiveSetupError->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| ADMIN STATUS ACTION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $tableReady
) {

    $action =
        $_POST['action'] ?? '';

    $requestId =
        (int)($_POST['request_id'] ?? 0);


    try {

        /*
        |--------------------------------------------------------------------------
        | VALIDATE REQUEST ID
        |--------------------------------------------------------------------------
        */

        if ($requestId <= 0) {

            throw new Exception(
                "Invalid maintenance request."
            );

        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE STATUS
        |--------------------------------------------------------------------------
        */

        if ($action === 'update_status') {

            $newStatus =
                trim($_POST['status'] ?? '');


            $allowedStatuses = [
                'Pending',
                'Ongoing',
                'Completed',
                'Cancelled'
            ];


            if (
                !in_array(
                    $newStatus,
                    $allowedStatuses,
                    true
                )
            ) {

                throw new Exception(
                    "Invalid maintenance status."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | COMPLETED
            |--------------------------------------------------------------------------
            |
            | When the admin changes the request to Completed,
            | completed_at is automatically recorded.
            |
            */

            if ($newStatus === 'Completed') {

                $stmt = $conn->prepare("
                    UPDATE maintenance_requests
                    SET
                        status = ?,
                        completed_at = NOW()
                    WHERE request_id = ?
                ");

            }

            /*
            |--------------------------------------------------------------------------
            | CANCELLED
            |--------------------------------------------------------------------------
            |
            | Cancelled requests are also moved to the archive.
            |
            */

            elseif ($newStatus === 'Cancelled') {

                $stmt = $conn->prepare("
                    UPDATE maintenance_requests
                    SET
                        status = ?,
                        completed_at = NULL
                    WHERE request_id = ?
                ");

            }

            /*
            |--------------------------------------------------------------------------
            | PENDING / ONGOING
            |--------------------------------------------------------------------------
            */

            else {

                $stmt = $conn->prepare("
                    UPDATE maintenance_requests
                    SET
                        status = ?,
                        completed_at = NULL
                    WHERE request_id = ?
                ");

            }


            if (!$stmt) {

                throw new Exception(
                    "Could not prepare status update: "
                    . $conn->error
                );

            }


            $stmt->bind_param(
                "si",
                $newStatus,
                $requestId
            );


            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Could not update the maintenance request."
                );

            }


            $stmt->close();

            if ($newStatus === 'Completed' || $newStatus === 'Cancelled') {
                try {
                    archiveRecord($conn, 'maintenance_requests', 'request_id', $requestId, (int)$_SESSION['user_id']);
                } catch (Throwable $archiveError) {
                    $error = $archiveError->getMessage();
                }
            }


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            $_SESSION['maintenance_message'] =
                "Maintenance request updated successfully.";


            header(
                "Location: adminMaintenance.php"
            );

            exit();
        }


        /*
        |--------------------------------------------------------------------------
        | QUICK CANCEL BUTTON
        |--------------------------------------------------------------------------
        */

        if ($action === 'cancel_request') {

            $stmt = $conn->prepare("
                UPDATE maintenance_requests
                SET
                    status = 'Cancelled'
                WHERE request_id = ?
                AND status NOT IN ('Completed', 'Cancelled')
            ");


            if (!$stmt) {

                throw new Exception(
                    "Could not prepare cancellation: "
                    . $conn->error
                );

            }


            $stmt->bind_param(
                "i",
                $requestId
            );


            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Could not cancel the maintenance request."
                );

            }


            $stmt->close();

            archiveRecord($conn, 'maintenance_requests', 'request_id', $requestId, (int)$_SESSION['user_id']);


            $_SESSION['maintenance_message'] =
                "Maintenance request cancelled successfully.";


            header(
                "Location: adminMaintenance.php"
            );

            exit();
        }

    } catch (Throwable $ex) {

        $error =
            $ex->getMessage();

    }
}


/*
|--------------------------------------------------------------------------
| LOAD SUMMARY STATISTICS
|--------------------------------------------------------------------------
*/

if ($tableReady) {

    try {

        $statsQuery = $conn->query("
            SELECT

                COUNT(*) AS total_requests,

                SUM(
                    CASE
                        WHEN status = 'Pending'
                        THEN 1
                        ELSE 0
                    END
                ) AS pending_requests,

                SUM(
                    CASE
                        WHEN status = 'Ongoing'
                        THEN 1
                        ELSE 0
                    END
                ) AS ongoing_requests,

                SUM(
                    CASE
                        WHEN status = 'Completed'
                        THEN 1
                        ELSE 0
                    END
                ) AS completed_requests

            FROM maintenance_requests
        ");


        if (!$statsQuery) {

            throw new Exception(
                "Could not load maintenance statistics."
            );

        }


        $stats =
            $statsQuery->fetch_assoc();


        $totalRequests =
            (int)($stats['total_requests'] ?? 0);

        $pendingRequests =
            (int)($stats['pending_requests'] ?? 0);

        $ongoingRequests =
            (int)($stats['ongoing_requests'] ?? 0);

        $completedRequests =
            (int)($stats['completed_requests'] ?? 0);


    } catch (Throwable $ex) {

        if (!$error) {

            $error =
                $ex->getMessage();

        }

    }
}


/*
|--------------------------------------------------------------------------
| LOAD ACTIVE REQUESTS
|--------------------------------------------------------------------------
|
| ONLY Pending and Ongoing requests appear here.
|
| Completed and Cancelled requests are NOT shown here.
|
|--------------------------------------------------------------------------
*/

if ($tableReady) {

    try {

        $stmt = $conn->prepare("
            SELECT

                mr.request_id,
                mr.title,
                mr.description,
                mr.location,
                mr.priority,
                mr.status,

                mr.requester_id,
                mr.created_by,
                mr.assigned_to,

                mr.created_at,
                mr.assigned_at,
                mr.completed_at,
                mr.updated_at,

                assigned.name AS assigned_name,
                assigned.username AS assigned_username,

                requester.name AS requester_name,
                requester.username AS requester_username,

                creator.name AS creator_name,
                creator.username AS creator_username

            FROM archived_maintenance_requests mr

            LEFT JOIN users assigned
                ON assigned.user_id = mr.assigned_to

            LEFT JOIN users requester
                ON requester.user_id = mr.requester_id

            LEFT JOIN users creator
                ON creator.user_id = mr.created_by

            WHERE mr.status IN ('Pending', 'Ongoing')

            ORDER BY

                CASE
                    WHEN mr.status = 'Pending'
                    THEN 1

                    WHEN mr.status = 'Ongoing'
                    THEN 2

                    ELSE 3
                END,

                CASE
                    WHEN mr.priority = 'Urgent'
                    THEN 1

                    WHEN mr.priority = 'High'
                    THEN 2

                    WHEN mr.priority = 'Medium'
                    THEN 3

                    WHEN mr.priority = 'Low'
                    THEN 4

                    ELSE 5
                END,

                mr.request_id DESC

            LIMIT 6
        ");


        if (!$stmt) {

            throw new Exception(
                "Could not prepare active maintenance query: "
                . $conn->error
            );

        }


        if (!$stmt->execute()) {

            $stmt->close();

            throw new Exception(
                "Could not load active maintenance requests."
            );

        }


        $result =
            $stmt->get_result();


        while ($row = $result->fetch_assoc()) {

            $activeRequests[] =
                $row;

        }


        $stmt->close();


    } catch (Throwable $ex) {

        if (!$error) {

            $error =
                $ex->getMessage();

        }

    }
}


/*
|--------------------------------------------------------------------------
| LOAD ARCHIVED REQUESTS
|--------------------------------------------------------------------------
|
| Completed and Cancelled requests are shown here.
|
| NOTHING IS DELETED FROM THE DATABASE.
|
|--------------------------------------------------------------------------
*/

if ($tableReady) {

    try {

        $stmt = $conn->prepare("
            SELECT

                mr.request_id,
                mr.title,
                mr.description,
                mr.location,
                mr.priority,
                mr.status,

                mr.requester_id,
                mr.created_by,
                mr.assigned_to,

                mr.created_at,
                mr.assigned_at,
                mr.completed_at,
                mr.updated_at,

                assigned.name AS assigned_name,
                assigned.username AS assigned_username,

                requester.name AS requester_name,
                requester.username AS requester_username,

                creator.name AS creator_name,
                creator.username AS creator_username

            FROM maintenance_requests mr

            LEFT JOIN users assigned
                ON assigned.user_id = mr.assigned_to

            LEFT JOIN users requester
                ON requester.user_id = mr.requester_id

            LEFT JOIN users creator
                ON creator.user_id = mr.created_by

            

            ORDER BY

                COALESCE(
                    mr.completed_at,
                    mr.updated_at,
                    mr.created_at
                ) DESC,

                mr.request_id DESC

            LIMIT 10
        ");


        if (!$stmt) {

            throw new Exception(
                "Could not prepare archive query: "
                . $conn->error
            );

        }


        if (!$stmt->execute()) {

            $stmt->close();

            throw new Exception(
                "Could not load maintenance archive."
            );

        }


        $result =
            $stmt->get_result();


        while ($row = $result->fetch_assoc()) {

            $archiveRequests[] =
                $row;

        }


        $stmt->close();


    } catch (Throwable $ex) {

        if (!$error) {

            $error =
                $ex->getMessage();

        }

    }
}


/*
|--------------------------------------------------------------------------
| PRIORITY CLASS
|--------------------------------------------------------------------------
*/

function priorityClass($priority)
{
    switch ($priority) {

        case 'Urgent':
            return 'urgent';

        case 'High':
            return 'high';

        case 'Low':
            return 'low';

        default:
            return 'medium';
    }
}


/*
|--------------------------------------------------------------------------
| STATUS CLASS
|--------------------------------------------------------------------------
*/

function statusClass($status)
{
    return strtolower(
        str_replace(
            ' ',
            '-',
            (string)$status
        )
    );
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
        Maintenance Management
    </title>


    <!-- =========================================================
         GOOGLE FONTS
         ========================================================= -->

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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Material+Symbols+Rounded:wght@400;500;600"
        rel="stylesheet"
    >


    <!-- =========================================================
         EXISTING CSS
         ========================================================= -->

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


    <!-- =========================================================
         ADDITIONAL MAINTENANCE STYLES
         ========================================================= -->

    <style>

        /*
        ----------------------------------------------------------
        ALERTS
        ----------------------------------------------------------
        */

        .maintenance-alert {
            margin: 0 0 18px 0;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
        }

        .maintenance-alert.success {
            background: #e9f6ec;
            color: #2f7043;
            border: 1px solid #d3ead8;
        }

        .maintenance-alert.error {
            background: #fdeaea;
            color: #a43d3d;
            border: 1px solid #f2cccc;
        }


        /*
        ----------------------------------------------------------
        REQUEST META
        ----------------------------------------------------------
        */

        .request-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 7px;
            color: #78847b;
            font-size: 11px;
        }

        .request-meta span {
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .request-meta .material-symbols-rounded {
            font-size: 15px;
        }


        /*
        ----------------------------------------------------------
        ASSIGNED STAFF
        ----------------------------------------------------------
        */

        .assigned-label {
            display: block;
            margin-top: 7px;
            color: #748078;
            font-size: 11px;
        }

        .assigned-label strong {
            color: #4c5c51;
        }


        /*
        ----------------------------------------------------------
        PRIORITY
        ----------------------------------------------------------
        */

        .priority-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
        }

        .priority-badge.low {
            background: #e8f3e9;
            color: #4d8758;
        }

        .priority-badge.medium {
            background: #fff2cc;
            color: #9c7c18;
        }

        .priority-badge.high {
            background: #ffe4cc;
            color: #b76225;
        }

        .priority-badge.urgent {
            background: #f8dada;
            color: #b33e3e;
        }


        /*
        ----------------------------------------------------------
        REQUEST ACTIONS
        ----------------------------------------------------------
        */

        .request-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 11px;
        }

        .request-actions form {
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 0;
        }

        .maintenance-status-select {
            padding: 7px 9px;
            border: 1px solid #dce4de;
            border-radius: 7px;
            background: #ffffff;
            color: #536157;
            font-family: Poppins, sans-serif;
            font-size: 11px;
            outline: none;
        }

        .maintenance-status-select:focus {
            border-color: #6c9875;
        }


        /*
        ----------------------------------------------------------
        UPDATE BUTTON
        ----------------------------------------------------------
        */

        .maintenance-status-btn {
            padding: 7px 11px;
            border: none;
            border-radius: 7px;
            background: #356d42;
            color: #ffffff;
            font-family: Poppins, sans-serif;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }

        .maintenance-status-btn:hover {
            opacity: .9;
        }


        /*
        ----------------------------------------------------------
        CANCEL BUTTON
        ----------------------------------------------------------
        */

        .maintenance-cancel-btn {
            padding: 7px 11px;
            border: none;
            border-radius: 7px;
            background: #f5dfdf;
            color: #a74a4a;
            font-family: Poppins, sans-serif;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }

        .maintenance-cancel-btn:hover {
            background: #efd2d2;
        }


        /*
        ----------------------------------------------------------
        EMPTY STATE
        ----------------------------------------------------------
        */

        .request-empty-note {
            text-align: center;
            padding: 42px 20px;
        }

        .request-empty-note .material-symbols-rounded {
            font-size: 42px;
            color: #95a198;
        }

        .request-empty-note h3 {
            margin: 10px 0 5px;
            color: #4c5b51;
            font-size: 15px;
        }

        .request-empty-note p {
            margin: 0;
            color: #7e8981;
            font-size: 12px;
        }


        /*
        ----------------------------------------------------------
        ARCHIVE
        ----------------------------------------------------------
        */

        .archive-panel {
            margin-top: 20px;
        }

        .archive-list {
            display: flex;
            flex-direction: column;
        }

        .archive-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 15px 0;
            border-bottom: 1px solid #edf0ed;
        }

        .archive-item:last-child {
            border-bottom: none;
        }


        /*
        ----------------------------------------------------------
        ARCHIVE ICON
        ----------------------------------------------------------
        */

        .archive-icon {
            width: 40px;
            height: 40px;
            min-width: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef4ee;
            color: #5b8062;
        }

        .archive-icon .material-symbols-rounded {
            font-size: 22px;
        }


        /*
        ----------------------------------------------------------
        ARCHIVE DETAILS
        ----------------------------------------------------------
        */

        .archive-details {
            flex: 1;
            min-width: 0;
        }

        .archive-details strong {
            display: block;
            margin-bottom: 3px;
            color: #354239;
            font-size: 13px;
        }

        .archive-details span {
            display: block;
            margin-bottom: 3px;
            color: #748078;
            font-size: 11px;
        }

        .archive-details small {
            color: #929b94;
            font-size: 10px;
        }


        /*
        ----------------------------------------------------------
        ARCHIVE STATUS
        ----------------------------------------------------------
        */

        .archive-status {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }

        .archive-status-badge {
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        .archive-status-badge.completed {
            background: #e5f2e7;
            color: #397047;
        }

        .archive-status-badge.cancelled {
            background: #f5e1e1;
            color: #a54b4b;
        }

        .archive-status small {
            color: #929b94;
            font-size: 10px;
        }


        /*
        ----------------------------------------------------------
        ARCHIVE EMPTY
        ----------------------------------------------------------
        */

        .archive-empty {
            padding: 35px 20px;
            text-align: center;
        }

        .archive-empty .material-symbols-rounded {
            color: #95a198;
            font-size: 40px;
        }

        .archive-empty h3 {
            margin: 9px 0 5px;
            color: #4c5b51;
            font-size: 14px;
        }

        .archive-empty p {
            margin: 0;
            color: #7e8981;
            font-size: 11px;
        }


        /*
        ----------------------------------------------------------
        RESPONSIVE
        ----------------------------------------------------------
        */

        @media (max-width: 700px) {

            .archive-item {
                align-items: flex-start;
            }

            .archive-status {
                align-items: flex-start;
            }

            .request-actions {
                flex-direction: column;
                align-items: flex-start;
            }

            .request-actions form {
                flex-wrap: wrap;
            }

        }

    </style>

</head>


<body>


<div class="layout">


    <!-- =========================================================
         SIDEBAR
         ========================================================= -->

    <?php include '../sidebar.php'; ?>


    <!-- =========================================================
         MAIN DASHBOARD
         ========================================================= -->

    <main class="dashboard">


        <!-- =====================================================
             TOP RIGHT BAR
             ===================================================== -->

        <?php include '../topRightBar.php'; ?>


        <!-- =====================================================
             MAINTENANCE HERO
             ===================================================== -->

        <section class="maintenance-hero">


            <!-- =================================================
                 HERO TOP
                 ================================================= -->

            <div class="maintenance-hero-top">

                <div class="maintenance-hero-title">

                    <span class="material-symbols-rounded">
                        construction
                    </span>

                    <h2>
                        Maintenance Overview
                    </h2>

                </div>


                <div class="maintenance-date">

                    <span class="material-symbols-rounded">
                        calendar_month
                    </span>

                    <?= date('F j, Y') ?>

                </div>

            </div>


            <!-- =================================================
                 HERO DESCRIPTION
                 ================================================= -->

            <p class="maintenance-hero-description">

                Here's what's happening with maintenance activities
                around Sanctuario Nature Farm today.

            </p>


            <!-- =================================================
                 SUCCESS MESSAGE
                 ================================================= -->

            <?php if ($message): ?>

                <div class="maintenance-alert success">

                    <?= e($message) ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 ERROR MESSAGE
                 ================================================= -->

            <?php if ($error): ?>

                <div class="maintenance-alert error">

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 SUMMARY CARDS
                 ================================================= -->

            <div class="maintenance-hero-cards">


                <!-- TOTAL -->

                <div class="maintenance-hero-card">

                    <div class="maintenance-hero-icon total">

                        <span class="material-symbols-rounded">
                            assignment
                        </span>

                    </div>

                    <div class="maintenance-hero-info">

                        <span>
                            Total Requests
                        </span>

                        <strong>
                            <?= $totalRequests ?>
                        </strong>

                    </div>

                </div>


                <!-- PENDING -->

                <div class="maintenance-hero-card">

                    <div class="maintenance-hero-icon pending">

                        <span class="material-symbols-rounded">
                            schedule
                        </span>

                    </div>

                    <div class="maintenance-hero-info">

                        <span>
                            Pending
                        </span>

                        <strong>
                            <?= $pendingRequests ?>
                        </strong>

                    </div>

                </div>


                <!-- ONGOING -->

                <div class="maintenance-hero-card">

                    <div class="maintenance-hero-icon ongoing">

                        <span class="material-symbols-rounded">
                            engineering
                        </span>

                    </div>

                    <div class="maintenance-hero-info">

                        <span>
                            Ongoing
                        </span>

                        <strong>
                            <?= $ongoingRequests ?>
                        </strong>

                    </div>

                </div>


                <!-- COMPLETED -->

                <div class="maintenance-hero-card">

                    <div class="maintenance-hero-icon completed">

                        <span class="material-symbols-rounded">
                            task_alt
                        </span>

                    </div>

                    <div class="maintenance-hero-info">

                        <span>
                            Completed
                        </span>

                        <strong>
                            <?= $completedRequests ?>
                        </strong>

                    </div>

                </div>


            </div>


        </section>


        <!-- =====================================================
             ACTIVE MAINTENANCE REQUESTS
             ===================================================== -->

        <section class="maintenance-grid">


            <div class="maintenance-panel requests-panel">


                <!-- PANEL HEADER -->

                <div class="panel-header">

                    <div>

                        <h2>
                            Recent Maintenance Requests
                        </h2>

                        <p>
                            Latest pending and ongoing maintenance activities.
                        </p>

                    </div>


                    <a
                        href="maintenanceRequests.php"
                        class="view-all"
                    >
                        View All
                    </a>

                </div>


                <!-- =================================================
                     ACTIVE REQUEST LIST
                     ================================================= -->

                <div class="request-list">


                    <?php if (!$tableReady): ?>


                        <div class="request-empty-note">

                            <span class="material-symbols-rounded">
                                database
                            </span>

                            <h3>
                                Maintenance data unavailable
                            </h3>

                            <p>
                                Please check your maintenance_requests table.
                            </p>

                        </div>


                    <?php elseif (!$activeRequests): ?>


                        <div class="request-empty-note">

                            <span class="material-symbols-rounded">
                                task_alt
                            </span>

                            <h3>
                                No active maintenance requests
                            </h3>

                            <p>
                                New pending or ongoing requests will appear here.
                            </p>

                        </div>


                    <?php else: ?>


                        <?php foreach ($activeRequests as $request): ?>


                            <?php

                            /*
                            |--------------------------------------------------------------------------
                            | REQUEST VALUES
                            |--------------------------------------------------------------------------
                            */

                            $requestId =
                                (int)$request['request_id'];

                            $title =
                                $request['title']
                                ?: 'Maintenance Request';

                            $description =
                                $request['description']
                                ?: 'No description available.';

                            $location =
                                $request['location']
                                ?: 'No location';

                            $priority =
                                $request['priority']
                                ?: 'Medium';

                            $status =
                                $request['status']
                                ?: 'Pending';


                            /*
                            |--------------------------------------------------------------------------
                            | ASSIGNED STAFF
                            |--------------------------------------------------------------------------
                            */

                            $assignedName =
                                $request['assigned_name']
                                ?: $request['assigned_username']
                                ?: 'Unassigned';


                            /*
                            |--------------------------------------------------------------------------
                            | REQUESTER
                            |--------------------------------------------------------------------------
                            |
                            | requester_id is the primary requester.
                            |
                            | If requester_id is NULL, fall back to
                            | created_by.
                            |
                            */

                            $requesterName =
                                $request['requester_name']
                                ?: $request['requester_username']
                                ?: $request['creator_name']
                                ?: $request['creator_username']
                                ?: 'Unknown';


                            /*
                            |--------------------------------------------------------------------------
                            | CREATED DATE
                            |--------------------------------------------------------------------------
                            */

                            $createdDate = '';

                            if (!empty($request['created_at'])) {

                                $timestamp =
                                    strtotime(
                                        $request['created_at']
                                    );

                                if ($timestamp !== false) {

                                    $createdDate =
                                        date(
                                            'M j, Y g:i A',
                                            $timestamp
                                        );

                                }

                            }

                            ?>


                            <!-- =================================================
                                 REQUEST ITEM
                                 ================================================= -->

                            <div class="request-item">


                                <!-- REQUEST ICON -->

                                <div class="request-icon">

                                    <span class="material-symbols-rounded">
                                        handyman
                                    </span>

                                </div>


                                <!-- REQUEST INFORMATION -->

                                <div class="request-details">


                                    <!-- TITLE -->

                                    <h3>

                                        <?= e($title) ?>

                                    </h3>


                                    <!-- DESCRIPTION -->

                                    <p>

                                        <?= e($description) ?>

                                    </p>


                                    <!-- REQUEST NUMBER -->

                                    <small>

                                        Request #

                                        <?= $requestId ?>

                                    </small>


                                    <!-- META -->

                                    <div class="request-meta">


                                        <!-- LOCATION -->

                                        <span>

                                            <span class="material-symbols-rounded">
                                                location_on
                                            </span>

                                            <?= e($location) ?>

                                        </span>


                                        <!-- REQUESTER -->

                                        <span>

                                            Requested by:

                                            <?= e($requesterName) ?>

                                        </span>


                                        <!-- CREATED DATE -->

                                        <?php if ($createdDate): ?>

                                            <span>

                                                <span class="material-symbols-rounded">
                                                    schedule
                                                </span>

                                                <?= e($createdDate) ?>

                                            </span>

                                        <?php endif; ?>


                                    </div>


                                    <!-- ASSIGNED STAFF -->

                                    <span class="assigned-label">

                                        Assigned to:

                                        <strong>
                                            <?= e($assignedName) ?>
                                        </strong>

                                    </span>


                                    <!-- =================================================
                                         ADMIN ACTIONS
                                         ================================================= -->

                                    <div class="request-actions">


                                        <!-- STATUS FORM -->

                                        <form
                                            method="POST"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="update_status"
                                            >

                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= $requestId ?>"
                                            >


                                            <select
                                                name="status"
                                                class="maintenance-status-select"
                                                required
                                            >

                                                <option
                                                    value="Pending"
                                                    <?= $status === 'Pending'
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    Pending
                                                </option>

                                                <option
                                                    value="Ongoing"
                                                    <?= $status === 'Ongoing'
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    Ongoing
                                                </option>

                                                <option
                                                    value="Completed"
                                                    <?= $status === 'Completed'
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    Completed
                                                </option>

                                                <option
                                                    value="Cancelled"
                                                    <?= $status === 'Cancelled'
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    Cancelled
                                                </option>

                                            </select>


                                            <button
                                                type="submit"
                                                class="maintenance-status-btn"
                                            >
                                                Update
                                            </button>

                                        </form>


                                        <!-- CANCEL FORM -->

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to cancel this maintenance request?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="cancel_request"
                                            >

                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= $requestId ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="maintenance-cancel-btn"
                                            >
                                                Cancel
                                            </button>

                                        </form>


                                    </div>


                                </div>


                                <!-- STATUS -->

                                <span
                                    class="status <?= e(
                                        statusClass($status)
                                    ) ?>"
                                >

                                    <?= e($status) ?>

                                </span>


                                <!-- PRIORITY -->

                                <span
                                    class="priority-badge <?= e(
                                        priorityClass($priority)
                                    ) ?>"
                                >

                                    <?= e($priority) ?>

                                </span>


                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>


            </div>


            <!-- =====================================================
                 MANAGEMENT PANEL
                 ===================================================== -->

            <div class="maintenance-panel">


                <div class="panel-header">

                    <div>

                        <h2>
                            Management
                        </h2>

                        <p>
                            Maintenance administration.
                        </p>

                    </div>

                </div>


                <div class="management-actions">


                    <!-- =================================================
                         MAINTENANCE REQUESTS
                         ================================================= -->

                    <a
                        href="maintenanceRequests.php"
                        class="management-action"
                    >

                        <div class="action-icon">

                            <span class="material-symbols-rounded">
                                assignment
                            </span>

                        </div>


                        <div>

                            <strong>
                                Maintenance Requests
                            </strong>

                            <span>
                                Review all submitted requests
                            </span>

                        </div>


                        <span class="material-symbols-rounded arrow">
                            chevron_right
                        </span>

                    </a>


                    <!-- =================================================
                         ASSIGN MAINTENANCE
                         ================================================= -->

                    <a
                        href="assignMaintenance.php"
                        class="management-action"
                    >

                        <div class="action-icon">

                            <span class="material-symbols-rounded">
                                person_add
                            </span>

                        </div>


                        <div>

                            <strong>
                                Assign Maintenance
                            </strong>

                            <span>
                                Assign tasks to maintenance staff
                            </span>

                        </div>


                        <span class="material-symbols-rounded arrow">
                            chevron_right
                        </span>

                    </a>


                    <!-- =================================================
                         HISTORY
                         ================================================= -->

                    <a
                        href="maintenanceHistory.php"
                        class="management-action"
                    >

                        <div class="action-icon">

                            <span class="material-symbols-rounded">
                                history
                            </span>

                        </div>


                        <div>

                            <strong>
                                Maintenance History
                            </strong>

                            <span>
                                View completed maintenance work
                            </span>

                        </div>


                        <span class="material-symbols-rounded arrow">
                            chevron_right
                        </span>

                    </a>


                </div>


            </div>


        </section>


        <!-- =====================================================
             MAINTENANCE ARCHIVE
             ===================================================== -->

        <section class="maintenance-panel archive-panel">


            <!-- PANEL HEADER -->

            <div class="panel-header">

                <div>

                    <h2>
                        Maintenance Archive
                    </h2>

                    <p>
                        Completed and cancelled maintenance records.
                    </p>

                </div>


                <a
                    href="maintenanceHistory.php"
                    class="view-all"
                >
                    View History
                </a>

            </div>


            <!-- =================================================
                 ARCHIVE LIST
                 ================================================= -->

            <div class="archive-list">


                <?php if (!$archiveRequests): ?>


                    <div class="archive-empty">

                        <span class="material-symbols-rounded">
                            inventory_2
                        </span>

                        <h3>
                            No archived requests
                        </h3>

                        <p>
                            Completed or cancelled requests will appear here.
                        </p>

                    </div>


                <?php else: ?>


                    <?php foreach ($archiveRequests as $archive): ?>


                        <?php

                        $archiveId =
                            (int)$archive['request_id'];

                        $archiveTitle =
                            $archive['title']
                            ?: 'Maintenance Request';

                        $archiveLocation =
                            $archive['location']
                            ?: 'No location';

                        $archiveStatus =
                            $archive['status']
                            ?: 'Completed';


                        /*
                        |--------------------------------------------------------------------------
                        | REQUESTER
                        |--------------------------------------------------------------------------
                        */

                        $archiveRequester =
                            $archive['requester_name']
                            ?: $archive['requester_username']
                            ?: $archive['creator_name']
                            ?: $archive['creator_username']
                            ?: 'Unknown';


                        /*
                        |--------------------------------------------------------------------------
                        | ARCHIVE DATE
                        |--------------------------------------------------------------------------
                        */

                        $archiveDate =
                            $archive['completed_at']
                            ?: $archive['updated_at']
                            ?: $archive['created_at'];


                        $formattedArchiveDate = '';

                        if (!empty($archiveDate)) {

                            $archiveTimestamp =
                                strtotime($archiveDate);

                            if (
                                $archiveTimestamp !== false
                            ) {

                                $formattedArchiveDate =
                                    date(
                                        'M j, Y g:i A',
                                        $archiveTimestamp
                                    );

                            }

                        }

                        ?>


                        <!-- =================================================
                             ARCHIVE ITEM
                             ================================================= -->

                        <div class="archive-item">


                            <!-- ICON -->

                            <div class="archive-icon">

                                <span class="material-symbols-rounded">

                                    <?php if (
                                        $archiveStatus === 'Completed'
                                    ): ?>

                                        task_alt

                                    <?php else: ?>

                                        cancel

                                    <?php endif; ?>

                                </span>

                            </div>


                            <!-- DETAILS -->

                            <div class="archive-details">

                                <strong>

                                    <?= e(
                                        $archiveTitle
                                    ) ?>

                                </strong>


                                <span>

                                    <?= e(
                                        $archiveLocation
                                    ) ?>

                                </span>


                                <small>

                                    Request #

                                    <?= $archiveId ?>

                                    &nbsp;·&nbsp;

                                    Requested by

                                    <?= e(
                                        $archiveRequester
                                    ) ?>

                                </small>

                            </div>


                            <!-- STATUS -->

                            <div class="archive-status">

                                <span
                                    class="archive-status-badge <?= e(
                                        statusClass($archiveStatus)
                                    ) ?>"
                                >

                                    <?= e(
                                        $archiveStatus
                                    ) ?>

                                </span>


                                <?php if (
                                    $formattedArchiveDate
                                ): ?>

                                    <small>

                                        <?= e(
                                            $formattedArchiveDate
                                        ) ?>

                                    </small>

                                <?php endif; ?>


                            </div>


                        </div>


                    <?php endforeach; ?>


                <?php endif; ?>


            </div>


        </section>


    </main>

</div>


</body>

</html>