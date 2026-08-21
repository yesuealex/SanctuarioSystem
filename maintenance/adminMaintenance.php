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
| ROLE CHECK
|--------------------------------------------------------------------------
| Only Admin can access this page.
|--------------------------------------------------------------------------
*/

$role = $_SESSION['role'] ?? '';

if ($role !== 'admin') {
    header("Location: ../adminDashboard.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

require_once '../db.php';


/*
|--------------------------------------------------------------------------
| ADMIN INFORMATION
|--------------------------------------------------------------------------
*/

$adminName = $_SESSION['username']
    ?? $_SESSION['name']
    ?? 'Administrator';


/*
|--------------------------------------------------------------------------
| DEFAULT COUNTERS
|--------------------------------------------------------------------------
*/

$totalRequests = 0;
$pendingRequests = 0;
$ongoingRequests = 0;
$completedRequests = 0;


/*
|--------------------------------------------------------------------------
| MAINTENANCE REQUEST COUNTS
|--------------------------------------------------------------------------
| If the table is not yet available, the page will still load.
|--------------------------------------------------------------------------
*/

try {

    // Total
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM maintenance_requests
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $totalRequests = (int)($row['total'] ?? 0);
    }


    // Pending
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM maintenance_requests
        WHERE status = 'Pending'
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $pendingRequests = (int)($row['total'] ?? 0);
    }


    // Ongoing
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM maintenance_requests
        WHERE status = 'Ongoing'
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $ongoingRequests = (int)($row['total'] ?? 0);
    }


    // Completed
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM maintenance_requests
        WHERE status = 'Completed'
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $completedRequests = (int)($row['total'] ?? 0);
    }

} catch (Exception $e) {

    $totalRequests = 0;
    $pendingRequests = 0;
    $ongoingRequests = 0;
    $completedRequests = 0;
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

    <title>Maintenance Management | Sanctuario Nature Farm</title>


    <!-- =================================================
         POPPINS
         ================================================= -->

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


    <!-- =================================================
         MATERIAL SYMBOLS
         ================================================= -->

    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:wght@400;500;600"
        rel="stylesheet"
    >


    <!-- =================================================
         MAIN CSS
         ================================================= -->

    <link
        rel="stylesheet"
        href="../style.css"
    >


    <!-- =================================================
         SIDEBAR CSS
         ================================================= -->

    <link
        rel="stylesheet"
        href="../sidebar.css"
    >


    <!-- =================================================
         ADMIN MAINTENANCE CSS
         ================================================= -->

    <link
        rel="stylesheet"
        href="maintenance.css"
    >

</head>


<body>


<div class="layout">


    <!-- =================================================
         SIDEBAR
         ================================================= -->

    <?php include '../sidebar.php'; ?>


    <!-- =================================================
         MAIN DASHBOARD
         ================================================= -->

    <main class="dashboard">


        <!-- =================================================
             TOP RIGHT BAR
             ================================================= -->

        <?php include '../topRightBar.php'; ?>


        <!-- =================================================
             PAGE HEADER
             ================================================= -->

        <section class="maintenance-header">

            <div>

                <span class="page-label">
                    ADMINISTRATION
                </span>

                <h1>
                    Maintenance Management
                </h1>

                <p>
                    Monitor, assign, and manage farm maintenance activities.
                </p>

            </div>


            <a
                href="addMaintenance.php"
                class="add-maintenance-btn"
            >

                <span class="material-symbols-rounded">
                    add
                </span>

                New Request

            </a>

        </section>


        <!-- =================================================
             SUMMARY CARDS
             ================================================= -->

        <section class="maintenance-cards">


            <!-- TOTAL -->

            <div class="maintenance-card">

                <div class="card-icon">

                    <span class="material-symbols-rounded">
                        construction
                    </span>

                </div>

                <div class="card-info">

                    <span>
                        Total Requests
                    </span>

                    <strong>
                        <?= $totalRequests ?>
                    </strong>

                </div>

            </div>


            <!-- PENDING -->

            <div class="maintenance-card">

                <div class="card-icon pending">

                    <span class="material-symbols-rounded">
                        pending_actions
                    </span>

                </div>

                <div class="card-info">

                    <span>
                        Pending
                    </span>

                    <strong>
                        <?= $pendingRequests ?>
                    </strong>

                </div>

            </div>


            <!-- ONGOING -->

            <div class="maintenance-card">

                <div class="card-icon ongoing">

                    <span class="material-symbols-rounded">
                        engineering
                    </span>

                </div>

                <div class="card-info">

                    <span>
                        Ongoing
                    </span>

                    <strong>
                        <?= $ongoingRequests ?>
                    </strong>

                </div>

            </div>


            <!-- COMPLETED -->

            <div class="maintenance-card">

                <div class="card-icon completed">

                    <span class="material-symbols-rounded">
                        task_alt
                    </span>

                </div>

                <div class="card-info">

                    <span>
                        Completed
                    </span>

                    <strong>
                        <?= $completedRequests ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- =================================================
             MAIN CONTENT
             ================================================= -->

        <section class="maintenance-grid">


            <!-- =================================================
                 RECENT REQUESTS
                 ================================================= -->

            <div class="maintenance-panel requests-panel">


                <div class="panel-header">

                    <div>

                        <h2>
                            Recent Maintenance Requests
                        </h2>

                        <p>
                            Latest maintenance activities submitted to the farm.
                        </p>

                    </div>


                    <a
                        href="maintenanceRequests.php"
                        class="view-all"
                    >
                        View All
                    </a>

                </div>


                <div class="request-list">

                    <?php

                    try {

                        $requestQuery = "
                            SELECT *
                            FROM maintenance_requests
                            ORDER BY request_id DESC
                            LIMIT 6
                        ";

                        $requestResult = $conn->query($requestQuery);


                        if (
                            $requestResult &&
                            $requestResult->num_rows > 0
                        ):

                            while (
                                $request = $requestResult->fetch_assoc()
                            ):

                                $status =
                                    $request['status']
                                    ?? 'Pending';


                                $statusClass =
                                    strtolower(
                                        str_replace(
                                            ' ',
                                            '-',
                                            $status
                                        )
                                    );

                    ?>

                        <div class="request-item">


                            <div class="request-icon">

                                <span class="material-symbols-rounded">
                                    handyman
                                </span>

                            </div>


                            <div class="request-details">

                                <h3>

                                    <?= htmlspecialchars(
                                        $request['title']
                                        ?? $request['description']
                                        ?? 'Maintenance Request'
                                    ) ?>

                                </h3>


                                <p>

                                    <?= htmlspecialchars(
                                        $request['description']
                                        ?? 'No description available.'
                                    ) ?>

                                </p>


                                <small>

                                    Request #<?= htmlspecialchars(
                                        $request['request_id']
                                        ?? '—'
                                    ) ?>

                                </small>

                            </div>


                            <span
                                class="status <?= htmlspecialchars(
                                    $statusClass
                                ) ?>"
                            >

                                <?= htmlspecialchars($status) ?>

                            </span>


                        </div>


                    <?php

                            endwhile;

                        else:

                    ?>

                        <div class="empty-state">

                            <span class="material-symbols-rounded">
                                assignment
                            </span>

                            <h3>
                                No maintenance requests
                            </h3>

                            <p>
                                Maintenance requests will appear here.
                            </p>

                        </div>

                    <?php

                        endif;

                    } catch (Exception $e) {

                    ?>

                        <div class="empty-state">

                            <span class="material-symbols-rounded">
                                assignment
                            </span>

                            <h3>
                                No maintenance requests yet
                            </h3>

                            <p>
                                Maintenance requests will appear here once submitted.
                            </p>

                        </div>

                    <?php } ?>

                </div>

            </div>


            <!-- =================================================
                 ADMIN ACTIONS
                 ================================================= -->

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


                    <!-- REQUESTS -->

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


                    <!-- ASSIGN -->

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


                    <!-- STAFF -->

                    <a
                        href="maintenanceStaffList.php"
                        class="management-action"
                    >

                        <div class="action-icon">

                            <span class="material-symbols-rounded">
                                groups
                            </span>

                        </div>

                        <div>

                            <strong>
                                Maintenance Staff
                            </strong>

                            <span>
                                View maintenance personnel
                            </span>

                        </div>

                        <span class="material-symbols-rounded arrow">
                            chevron_right
                        </span>

                    </a>


                    <!-- HISTORY -->

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


        <!-- =================================================
             QUICK ACTIONS
             ================================================= -->

        <section class="quick-section">


            <h2>
                Quick Actions
            </h2>


            <div class="quick-grid">


                <a
                    href="addMaintenance.php"
                    class="quick-card"
                >

                    <span class="material-symbols-rounded">
                        add_circle
                    </span>

                    <div>

                        <strong>
                            Create Request
                        </strong>

                        <small>
                            Add a new maintenance request
                        </small>

                    </div>

                </a>


                <a
                    href="assignMaintenance.php"
                    class="quick-card"
                >

                    <span class="material-symbols-rounded">
                        assignment_ind
                    </span>

                    <div>

                        <strong>
                            Assign Task
                        </strong>

                        <small>
                            Assign work to maintenance staff
                        </small>

                    </div>

                </a>


                <a
                    href="maintenanceHistory.php"
                    class="quick-card"
                >

                    <span class="material-symbols-rounded">
                        history
                    </span>

                    <div>

                        <strong>
                            View History
                        </strong>

                        <small>
                            Review completed maintenance
                        </small>

                    </div>

                </a>


            </div>

        </section>


    </main>

</div>


</body>

</html>