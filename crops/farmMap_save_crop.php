<?php

session_start();

header('Content-Type: application/json');

include '../db.php';


/* =========================================================
   LOGIN / ROLE
========================================================= */

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in.'
    ]);

    exit;
}


$role = $_SESSION['role'] ?? 'staff';


if ($role !== 'admin' && $role !== 'super admin') {

    echo json_encode([
        'success' => false,
        'message' => 'Only admins can edit crop records.'
    ]);

    exit;
}


$userId = (int) $_SESSION['user_id'];


/* =========================================================
   READ FORM DATA
========================================================= */

$cropId =
    !empty($_POST['crop_id'])
        ? (int) $_POST['crop_id']
        : null;


$plotLetter =
    strtoupper(
        trim(
            $_POST['plot_letter'] ?? ''
        )
    );


$recipient =
    trim(
        $_POST['recipient'] ?? ''
    );


$cropName =
    trim(
        $_POST['crop_name'] ?? ''
    );


$variety =
    trim(
        $_POST['variety'] ?? ''
    );


$pcs =
    isset($_POST['pcs'])
        ? (int) $_POST['pcs']
        : 0;


$dateSowing =
    !empty($_POST['date_sowing'])
        ? $_POST['date_sowing']
        : null;


$germinationRate =
    isset($_POST['germination_rate'])
        ? (float) $_POST['germination_rate']
        : 0;


$targetTransplantDate =
    !empty($_POST['target_transplant_date'])
        ? $_POST['target_transplant_date']
        : null;


$targetArea =
    trim(
        $_POST['target_area'] ?? ''
    );


$seedlingsReceived =
    isset($_POST['seedlings_received'])
        ? (int) $_POST['seedlings_received']
        : 0;


$transplantedArea =
    trim(
        $_POST['transplanted_area'] ?? ''
    );


$dateTransplanted =
    !empty($_POST['date_transplanted'])
        ? $_POST['date_transplanted']
        : null;


$expectedHarvest =
    !empty($_POST['expected_harvest'])
        ? $_POST['expected_harvest']
        : null;


$allowedStatuses = [
    'planted',
    'growing',
    'ready',
    'harvested'
];


$status =
    in_array(
        $_POST['status'] ?? '',
        $allowedStatuses,
        true
    )
        ? $_POST['status']
        : 'planted';

/* =========================================================
   IMAGE-ONLY UPLOAD
========================================================= */

if (
    isset($_POST['image_only']) &&
    $_POST['image_only'] === '1'
) {

    $imageCropId =
        !empty($_POST['crop_id'])
            ? (int) $_POST['crop_id']
            : 0;

    if ($imageCropId <= 0) {

        echo json_encode([
            'success' => false,
            'message' => 'Invalid crop ID.'
        ]);

        exit;
    }


    if (
        !isset($_FILES['crop_image']) ||
        $_FILES['crop_image']['error'] !== UPLOAD_ERR_OK
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'No crop image was uploaded.'
        ]);

        exit;
    }


    if (
        $_FILES['crop_image']['size'] >
        5 * 1024 * 1024
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'Crop image must be 5MB or smaller.'
        ]);

        exit;
    }


    $imageInfo =
        getimagesize(
            $_FILES['crop_image']['tmp_name']
        );


    if ($imageInfo === false) {

        echo json_encode([
            'success' => false,
            'message' => 'The uploaded file is not a valid image.'
        ]);

        exit;
    }


    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];


    $mimeType =
        $imageInfo['mime'];


    if (!isset($allowedMimeTypes[$mimeType])) {

        echo json_encode([
            'success' => false,
            'message' => 'Only JPG, PNG, WEBP, and GIF images are allowed.'
        ]);

        exit;
    }


    $extension =
        $allowedMimeTypes[$mimeType];


    $uploadDirectory =
        __DIR__ . '/uploads/';


    if (!is_dir($uploadDirectory)) {

        if (!mkdir($uploadDirectory, 0777, true)) {

            echo json_encode([
                'success' => false,
                'message' => 'Unable to create upload folder.'
            ]);

            exit;
        }
    }


    $fileName =
        'crop_' .
        uniqid() .
        '_' .
        time() .
        '.' .
        $extension;


    $uploadPath =
        $uploadDirectory .
        $fileName;


    if (
        !move_uploaded_file(
            $_FILES['crop_image']['tmp_name'],
            $uploadPath
        )
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'Unable to save the crop image.'
        ]);

        exit;
    }


    $imagePath =
        'crops/uploads/' .
        $fileName;


    $stmt =
        $conn->prepare("
            UPDATE crops
            SET image = ?
            WHERE crop_id = ?
            AND archived = 0
        ");


    if (!$stmt) {

        echo json_encode([
            'success' => false,
            'message' =>
                'Database error: ' .
                $conn->error
        ]);

        exit;
    }


    $stmt->bind_param(
        'si',
        $imagePath,
        $imageCropId
    );


    if (!$stmt->execute()) {

        echo json_encode([
            'success' => false,
            'message' =>
                'Unable to update crop image: ' .
                $stmt->error
        ]);

        $stmt->close();

        exit;
    }


    $stmt->close();


    echo json_encode([
        'success' => true,
        'crop_id' => $imageCropId,
        'image' => $imagePath
    ]);

    exit;
}
/* =========================================================
   VALIDATION
========================================================= */

if ($cropName === '') {

    echo json_encode([
        'success' => false,
        'message' => 'Crop name is required.'
    ]);

    exit;
}


/* =========================================================
   IMAGE UPLOAD
========================================================= */

$newImage = null;


if (
    isset($_FILES['crop_image']) &&
    $_FILES['crop_image']['error'] !== UPLOAD_ERR_NO_FILE
) {

    if ($_FILES['crop_image']['error'] !== UPLOAD_ERR_OK) {

        echo json_encode([
            'success' => false,
            'message' => 'There was an error uploading the crop image.'
        ]);

        exit;
    }


    /* Maximum 5 MB */

    if ($_FILES['crop_image']['size'] > 5 * 1024 * 1024) {

        echo json_encode([
            'success' => false,
            'message' => 'Crop image must be 5MB or smaller.'
        ]);

        exit;
    }


    /* Check actual image MIME type */

    $imageInfo =
        getimagesize(
            $_FILES['crop_image']['tmp_name']
        );


    if ($imageInfo === false) {

        echo json_encode([
            'success' => false,
            'message' => 'The uploaded file is not a valid image.'
        ]);

        exit;
    }


    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];


    $mimeType =
        $imageInfo['mime'];


    if (!isset($allowedMimeTypes[$mimeType])) {

        echo json_encode([
            'success' => false,
            'message' => 'Only JPG, PNG, WEBP, and GIF images are allowed.'
        ]);

        exit;
    }


    $extension =
        $allowedMimeTypes[$mimeType];


    /* Create uploads folder if it does not exist */

    $uploadDirectory =
        __DIR__ . '/uploads/';


    if (!is_dir($uploadDirectory)) {

        if (!mkdir($uploadDirectory, 0777, true)) {

            echo json_encode([
                'success' => false,
                'message' => 'Unable to create the crop image upload folder.'
            ]);

            exit;
        }
    }


    /* Unique filename */

    $fileName =
        'crop_' .
        uniqid() .
        '_' .
        time() .
        '.' .
        $extension;


    $uploadPath =
        $uploadDirectory .
        $fileName;


    if (
        !move_uploaded_file(
            $_FILES['crop_image']['tmp_name'],
            $uploadPath
        )
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'Unable to save the crop image.'
        ]);

        exit;
    }


    /*
     * This is the path that will be stored in the database.
     * JavaScript can later use:
     *
     * basePath + image
     *
     */

    $newImage =
        'crops/uploads/' .
        $fileName;
}


/* =========================================================
   UPDATE EXISTING CROP
========================================================= */

if ($cropId !== null) {

    /*
     * If a new image was uploaded, update the image column too.
     * Otherwise, keep the existing image.
     */

    if ($newImage !== null) {

        $stmt = $conn->prepare("
            UPDATE crops

            SET
                recipient = ?,
                crop_name = ?,
                variety = ?,
                pcs = ?,
                date_sowing = ?,
                germination_rate = ?,
                target_transplant_date = ?,
                target_area = ?,
                seedlings_received = ?,
                transplanted_area = ?,
                date_transplanted = ?,
                expected_harvest = ?,
                status = ?,
                image = ?

            WHERE crop_id = ?
            AND archived = 0
        ");


        if (!$stmt) {

            echo json_encode([
                'success' => false,
                'message' =>
                    'Database error while preparing crop update: ' .
                    $conn->error
            ]);

            exit;
        }


        $stmt->bind_param(
            'sssisdssisssssi',
            $recipient,
            $cropName,
            $variety,
            $pcs,
            $dateSowing,
            $germinationRate,
            $targetTransplantDate,
            $targetArea,
            $seedlingsReceived,
            $transplantedArea,
            $dateTransplanted,
            $expectedHarvest,
            $status,
            $newImage,
            $cropId
        );

    } else {

        /*
         * No new image selected.
         * Keep the existing image.
         */

        $stmt = $conn->prepare("
            UPDATE crops

            SET
                recipient = ?,
                crop_name = ?,
                variety = ?,
                pcs = ?,
                date_sowing = ?,
                germination_rate = ?,
                target_transplant_date = ?,
                target_area = ?,
                seedlings_received = ?,
                transplanted_area = ?,
                date_transplanted = ?,
                expected_harvest = ?,
                status = ?

            WHERE crop_id = ?
            AND archived = 0
        ");


        if (!$stmt) {

            echo json_encode([
                'success' => false,
                'message' =>
                    'Database error while preparing crop update: ' .
                    $conn->error
            ]);

            exit;
        }


        $stmt->bind_param(
            'sssisdssissssi',
            $recipient,
            $cropName,
            $variety,
            $pcs,
            $dateSowing,
            $germinationRate,
            $targetTransplantDate,
            $targetArea,
            $seedlingsReceived,
            $transplantedArea,
            $dateTransplanted,
            $expectedHarvest,
            $status,
            $cropId
        );
    }


    if (!$stmt->execute()) {

        echo json_encode([
            'success' => false,
            'message' =>
                'Database error while updating crop: ' .
                $stmt->error
        ]);

        $stmt->close();

        exit;
    }


    $stmt->close();


    /* =====================================================
       ACTIVITY LOG
    ===================================================== */

    $log = $conn->prepare("
        INSERT INTO activity_logs
            (user_id, action, details)

        VALUES
            (?, 'Edited Crop', ?)
    ");


    if ($log) {

        $details =
            "Crop ID: {$cropId} ({$cropName}) updated.";

        $log->bind_param(
            'is',
            $userId,
            $details
        );

        $log->execute();

        $log->close();
    }


    echo json_encode([
        'success' => true,
        'crop_id' => $cropId,
        'image' => $newImage
    ]);

    exit;
}


/* =========================================================
   NEW CROP
========================================================= */

if ($plotLetter === '') {

    echo json_encode([
        'success' => false,
        'message' => 'Plot is required.'
    ]);

    exit;
}


$plotCode =
    'plot_' .
    strtolower(
        $plotLetter
    );


/* =========================================================
   FIND PLOT
========================================================= */

$plotStmt = $conn->prepare("
    SELECT plot_id

    FROM plots

    WHERE plot_code = ?
    AND archived = 0

    LIMIT 1
");


if (!$plotStmt) {

    echo json_encode([
        'success' => false,
        'message' =>
            'Database error while finding plot: ' .
            $conn->error
    ]);

    exit;
}


$plotStmt->bind_param(
    's',
    $plotCode
);


if (!$plotStmt->execute()) {

    echo json_encode([
        'success' => false,
        'message' =>
            'Unable to find plot: ' .
            $plotStmt->error
    ]);

    $plotStmt->close();

    exit;
}


$result =
    $plotStmt->get_result();


$plotRow =
    $result->fetch_assoc();


$plotStmt->close();


if (!$plotRow) {

    echo json_encode([
        'success' => false,
        'message' =>
            "Plot {$plotLetter} was not found."
    ]);

    exit;
}


$plotId =
    (int) $plotRow['plot_id'];


/* =========================================================
   INSERT NEW CROP
========================================================= */

$stmt = $conn->prepare("
    INSERT INTO crops (

        recipient,
        plot_id,
        crop_name,
        variety,
        pcs,
        date_sowing,
        germination_rate,
        target_transplant_date,
        target_area,
        seedlings_received,
        transplanted_area,
        date_transplanted,
        expected_harvest,
        status,
        image,
        archived

    )

    VALUES (

        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        0

    )
");


if (!$stmt) {

    echo json_encode([
        'success' => false,
        'message' =>
            'Database error while preparing crop creation: ' .
            $conn->error
    ]);

    exit;
}


$stmt->bind_param(
    'sissisdsissssss',
    $recipient,
    $plotId,
    $cropName,
    $variety,
    $pcs,
    $dateSowing,
    $germinationRate,
    $targetTransplantDate,
    $targetArea,
    $seedlingsReceived,
    $transplantedArea,
    $dateTransplanted,
    $expectedHarvest,
    $status,
    $newImage
);


if (!$stmt->execute()) {

    echo json_encode([
        'success' => false,
        'message' =>
            'Database error while creating crop: ' .
            $stmt->error
    ]);

    $stmt->close();

    exit;
}


$newCropId =
    $conn->insert_id;


$stmt->close();


/* =========================================================
   ACTIVITY LOG
========================================================= */

$log = $conn->prepare("
    INSERT INTO activity_logs
        (user_id, action, details)

    VALUES
        (?, 'Added Crop', ?)
");


if ($log) {

    $details =
        "Crop: {$cropName} added to {$plotCode}.";

    $log->bind_param(
        'is',
        $userId,
        $details
    );

    $log->execute();

    $log->close();
}


/* =========================================================
   SUCCESS
========================================================= */

echo json_encode([
    'success' => true,
    'crop_id' => $newCropId,
    'image' => $newImage
]);

?>