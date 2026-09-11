
<?php

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit;
}

include '../db.php';


/*
|--------------------------------------------------------------------------
| GET FORM DATA
|--------------------------------------------------------------------------
*/

$livestockId = intval(
    $_POST['livestock_id'] ?? 0
);

$name = trim(
    $_POST['name'] ?? ''
);

$type = trim(
    $_POST['type'] ?? ''
);

$sex = trim(
    $_POST['sex'] ?? ''
);

$dateOfBirth =
    !empty($_POST['date_of_birth'])
        ? $_POST['date_of_birth']
        : null;

$breed = trim(
    $_POST['breed'] ?? ''
);

$estimatedWeight =
    isset($_POST['estimated_weight']) &&
    $_POST['estimated_weight'] !== ''
        ? floatval($_POST['estimated_weight'])
        : null;

$number = intval(
    $_POST['number'] ?? 1
);

$forSale = intval(
    $_POST['for_sale'] ?? 0
);

$feedingSchedule = trim(
    $_POST['feeding_schedule'] ?? ''
);

$notes = trim(
    $_POST['notes'] ?? ''
);


/*
|--------------------------------------------------------------------------
| VALIDATE LIVESTOCK ID
|--------------------------------------------------------------------------
*/

if ($livestockId <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid livestock ID.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| GET CURRENT IMAGE
|--------------------------------------------------------------------------
*/

$currentImage = '';

$imageQuery = $conn->prepare("
    SELECT image
    FROM livestocks
    WHERE livestock_id = ?
      AND archived = 0
    LIMIT 1
");

$imageQuery->bind_param(
    "i",
    $livestockId
);

$imageQuery->execute();

$imageResult = $imageQuery->get_result();

if ($imageRow = $imageResult->fetch_assoc()) {
    $currentImage = $imageRow['image'] ?? '';
}

$imageQuery->close();


/*
|--------------------------------------------------------------------------
| IMAGE UPLOAD
|--------------------------------------------------------------------------
*/

$newImage = $currentImage;

if (
    isset($_FILES['livestock_image']) &&
    $_FILES['livestock_image']['error'] === UPLOAD_ERR_OK
) {

    $file = $_FILES['livestock_image'];

    $allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif'
    ];

    if (!in_array($file['type'], $allowedTypes, true)) {

        echo json_encode([
            'success' => false,
            'message' => 'Invalid image format. Please use JPG, PNG, WEBP, or GIF.'
        ]);

        exit;
    }


    /*
    |----------------------------------------------------------------------
    | LIMIT IMAGE SIZE
    |----------------------------------------------------------------------
    */

    if ($file['size'] > 5 * 1024 * 1024) {

        echo json_encode([
            'success' => false,
            'message' => 'Image is too large. Maximum size is 5 MB.'
        ]);

        exit;
    }


    /*
    |----------------------------------------------------------------------
    | CREATE UPLOAD FOLDER
    |----------------------------------------------------------------------
    */

    $uploadDirectory = __DIR__ . '/uploads/';

    if (!is_dir($uploadDirectory)) {

        mkdir(
            $uploadDirectory,
            0777,
            true
        );
    }


    /*
    |----------------------------------------------------------------------
    | CREATE UNIQUE FILE NAME
    |----------------------------------------------------------------------
    */

    $extension = strtolower(
        pathinfo(
            $file['name'],
            PATHINFO_EXTENSION
        )
    );

    $fileName =
        'livestock_' .
        $livestockId .
        '_' .
        time() .
        '.' .
        $extension;


    $targetPath =
        $uploadDirectory .
        $fileName;


    /*
    |----------------------------------------------------------------------
    | MOVE IMAGE
    |----------------------------------------------------------------------
    */

    if (!move_uploaded_file(
        $file['tmp_name'],
        $targetPath
    )) {

        echo json_encode([
            'success' => false,
            'message' => 'Unable to upload livestock image.'
        ]);

        exit;
    }


    /*
    |----------------------------------------------------------------------
    | DATABASE PATH
    |----------------------------------------------------------------------
    */

    $newImage =
        'livestock/uploads/' .
        $fileName;
}


/*
|--------------------------------------------------------------------------
| UPDATE LIVESTOCK
|--------------------------------------------------------------------------
*/

$sql = "
    UPDATE livestocks
    SET
        name = ?,
        type = ?,
        sex = ?,
        date_of_birth = ?,
        breed = ?,
        estimated_weight = ?,
        number = ?,
        for_sale = ?,
        feeding_schedule = ?,
        notes = ?,
        image = ?
    WHERE livestock_id = ?
      AND archived = 0
";


$stmt = $conn->prepare($sql);

if (!$stmt) {

    echo json_encode([
        'success' => false,
        'message' => 'Database preparation failed: ' . $conn->error
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| BIND PARAMETERS
|--------------------------------------------------------------------------
*/

$stmt->bind_param(
    "sssssdissssi",
    $name,
    $type,
    $sex,
    $dateOfBirth,
    $breed,
    $estimatedWeight,
    $number,
    $forSale,
    $feedingSchedule,
    $notes,
    $newImage,
    $livestockId
);


/*
|--------------------------------------------------------------------------
| EXECUTE
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {

    echo json_encode([
        'success' => false,
        'message' => 'Failed to update livestock record: ' . $stmt->error
    ]);

    $stmt->close();

    exit;
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

echo json_encode([
    'success' => true,
    'message' => 'Livestock profile updated successfully.',
    'image' => $newImage
]);

?>
