<?php

session_start();

include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| PLOTS
|--------------------------------------------------------------------------
*/

$plots = [];

$result = $conn->query("
    SELECT
        plot_id,
        plot_code,
        plot_name,
        map_x,
        map_y
    FROM plots
    ORDER BY plot_id ASC
");

while ($row = $result->fetch_assoc()) {

    $plots[] = $row;

}


/*
|--------------------------------------------------------------------------
| BEDS
|--------------------------------------------------------------------------
*/

$beds = [];

$result = $conn->query("
    SELECT
        bed_id,
        plot_id,
        bed_number,
        bed_code,
        map_x,
        map_y
    FROM plot_beds
    ORDER BY plot_id ASC, bed_number ASC
");

while ($row = $result->fetch_assoc()) {

    $beds[] = $row;

}


/*
|--------------------------------------------------------------------------
| CROPS
|--------------------------------------------------------------------------
*/

$crops = [];

$result = $conn->query("
    SELECT
        crop_id,
        plot_id,
        bed_id,
        crop_name,
        variety,
        recipient,
        pcs,
        germination_rate,
        transplanted_area,
        date_transplanted,
        expected_harvest,
        status
    FROM crops
    WHERE archived = 0
    ORDER BY crop_id ASC
");

while ($row = $result->fetch_assoc()) {

    $crops[] = $row;

}


/*
|--------------------------------------------------------------------------
| LIVESTOCK HOUSES
|--------------------------------------------------------------------------
*/

$houses = [];

$result = $conn->query("
    SELECT
        house_id,
        house_code,
        house_name,
        description,
        image,
        map_x,
        map_y
    FROM livestock_houses
    ORDER BY house_id ASC
");

while ($row = $result->fetch_assoc()) {

    $houses[] = $row;

}


/*
|--------------------------------------------------------------------------
| LIVESTOCK
|--------------------------------------------------------------------------
*/

$livestock = [];

$result = $conn->query("
    SELECT
        livestock_id,
        house_id,
        name,
        type,
        sex,
        dob,
        breed,
        estimated_weight,
        notes,
        image,
        feeding_schedule
    FROM livestocks
    WHERE archived = 0
    ORDER BY livestock_id ASC
");

while ($row = $result->fetch_assoc()) {

    $livestock[] = $row;

}


/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([
    'success' => true,
    'plots' => $plots,
    'beds' => $beds,
    'crops' => $crops,
    'houses' => $houses,
    'livestock' => $livestock
]);

$conn->close();