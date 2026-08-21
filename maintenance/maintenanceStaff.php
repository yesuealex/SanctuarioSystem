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
| Only maintenance staff can access this page.
|--------------------------------------------------------------------------
*/

$role = $_SESSION['role'] ?? '';

if ($role !== 'maintenance') {
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
| USER INFORMATION
|--------------------------------------------------------------------------
*/
$userName = $_SESSION['username'] ?? $_SESSION['name'] ?? 'Maintenance Staff';

/*
|--------------------------------------------------------------------------
| GET MAINTENANCE REQUESTS
|--------------------------------------------------------------------------
| This section assumes you have a maintenance_requests table.
|
| If your table has different columns, send me your table structure
| and I can adjust this part.
|--------------------------------------------------------------------------
*/

$totalRequests = 0;
$pendingRequests = 0;
$ongoingRequests = 0;
$completedRequests = 0;

try {

    // Total requests
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM maintenance_requests
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $totalRequests = $row['total'] ?? 0;
    }

    // Pending
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM maintenance_requests
        WHERE status = 'Pending'
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $pendingRequests = $row['total'] ?? 0;
    }

    // Ongoing
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM maintenance_requests
        WHERE status = 'Ongoing'
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $ongoingRequests = $row['total'] ?? 0;
    }

    // Completed
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM maintenance_requests
        WHERE status = 'Completed'
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $completedRequests = $row['total'] ?? 0;
    }

} catch (Exception $e) {

    // If the table/columns don't exist yet,
    // the dashboard will still load.
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

    <title>Maintenance Staff | Sanctuario Nature Farm</title>

    <!-- Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">

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

    <!-- Main CSS -->
    <link
        rel="stylesheet"
        href="../style.css"
    >

    <!-- Sidebar CSS -->
    <link
        rel="stylesheet"
        href="../sidebar.css"
    >

    <!-- Maintenance CSS -->
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

        <!-- TOP RIGHT BAR -->

        <?php include '../topRightBar.php'; ?>


        <!-- =================================================
             PAGE HEADER
             ================================================= -->

        <section class="maintenance-header">

            <div>

                <h1>
                    Maintenance
                </h1>

                <p>
                    Welcome back, <?= htmlspecialchars($userName) ?>!
                </p>

            </div>

        </section>


        <!-- =================================================
             SUMMARY CARDS
             ================================================= -->

        <section class="maintenance-cards">


            <!-- TOTAL REQUESTS -->

            <div class="maintenance-card">

                <div class="maintenance-card-icon">

                    <span class="material-symbols-rounded">
                        build
                    </span>

                </div>

                <div class="maintenance-card-content">

                    <span class="maintenance-card-label">
                        Total Requests
                    </span>

                    <strong>
                        <?= $totalRequests ?>
                    </strong>

                </div>

            </div>


            <!-- PENDING -->

            <div class="maintenance-card">

                <div class="maintenance-card-icon pending">

                    <span class="material-symbols-rounded">
                        pending_actions
                    </span>

                </div>

                <div class="maintenance-card-content">

                    <span class="maintenance-card-label">
                        Pending
                    </span>

                    <strong>
                        <?= $pendingRequests ?>
                    </strong>

                </div>

            </div>


            <!-- ONGOING -->

            <div class="maintenance-card">

                <div class="maintenance-card-icon ongoing">

                    <span class="material-symbols-rounded">
                        engineering
                    </span>

                </div>

                <div class="maintenance-card-content">

                    <span class="maintenance-card-label">
                        Ongoing
                    </span>

                    <strong>
                        <?= $ongoingRequests ?>
                    </strong>

                </div>

            </div>


            <!-- COMPLETED -->

            <div class="maintenance-card">

                <div class="maintenance-card-icon completed">

                    <span class="material-symbols-rounded">
                        task_alt
                    </span>

                </div>

                <div class="maintenance-card-content">

                    <span class="maintenance-card-label">
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

        <section class="maintenance-content">


            <!-- =================================================
                 MAINTENANCE TASKS
                 ================================================= -->

            <div class="maintenance-panel">

                <div class="panel-header">

                    <div>

                        <h2>
                            Maintenance Tasks
                        </h2>

                        <p>
                            View and manage assigned maintenance work.
                        </p>

                    </div>

                    <a
                        href="maintenanceRequests.php"
                        class="view-all-btn"
                    >
                        View All
                    </a>

                </div>


                <div class="task-list">

                    <?php

                    try {

                        $taskQuery = "
                            SELECT *
                            FROM maintenance_requests
                            ORDER BY request_id DESC
                            LIMIT 5
                        ";

                        $taskResult = $conn->query($taskQuery);

                        if ($taskResult && $taskResult->num_rows > 0):

                            while ($task = $taskResult->fetch_assoc()):

                                $status = $task['status'] ?? 'Pending';

                                $statusClass = strtolower(
                                    str_replace(' ', '-', $status)
                                );

                    ?>

                        <div class="task-item">

                            <div class="task-icon">

                                <span class="material-symbols-rounded">
                                    construction
                                </span>

                            </div>

                            <div class="task-info">

                                <h3>
                                    <?= htmlspecialchars(
                                        $task['title']
                                        ?? $task['description']
                                        ?? 'Maintenance Request'
                                    ) ?>
                                </h3>

                                <p>
                                    <?= htmlspecialchars(
                                        $task['description']
                                        ?? 'No description available.'
                                    ) ?>
                                </p>

                            </div>

                            <span
                                class="status-badge <?= htmlspecialchars($statusClass) ?>"
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
                                There are currently no maintenance tasks to display.
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
                                No maintenance requests
                            </h3>

                            <p>
                                Maintenance requests will appear here.

                            </p>

                        </div>

                    <?php } ?>

                </div>

            </div>


            <!-- =================================================
                 QUICK ACTIONS
                 ================================================= -->

            <div class="maintenance-panel quick-actions-panel">

                <div class="panel-header">

                    <div>

                        <h2>
                            Quick Actions
                        </h2>

                        <p>
                            Common maintenance activities.
                        </p>

                    </div>

                </div>


                <div class="quick-actions">

                    <a
                        href="maintenanceRequests.php"
                        class="quick-action"
                    >

                        <span class="material-symbols-rounded">
                            assignment
                        </span>

                        <div>

                            <strong>
                                View Requests
                            </strong>

                            <small>
                                Check maintenance requests
                            </small>

                        </div>

                    </a>


                    <a
                        href="maintenanceTasks.php"
                        class="quick-action"
                    >

                        <span class="material-symbols-rounded">
                            engineering
                        </span>

                        <div>

                            <strong>
                                My Tasks
                            </strong>

                            <small>
                                View assigned tasks
                            </small>

                        </div>

                    </a>


                    <a
                        href="maintenanceHistory.php"
                        class="quick-action"
                    >

                        <span class="material-symbols-rounded">
                            history
                        </span>

                        <div>

                            <strong>
                                Maintenance History
                            </strong>

                            <small>
                                View completed work
                            </small>

                        </div>

                    </a>

                </div>

            </div>

        </section>


    </main>

</div>

</body>

</html>