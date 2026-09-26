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

/* =========================================================
   FORM DATA
========================================================= */
$livestockId = intval($_POST['livestock_id'] ?? 0);
$houseId     = intval($_POST['house_id'] ?? 0);
$name        = trim($_POST['name'] ?? '');
$type        = trim($_POST['type'] ?? '');
$sex         = trim($_POST['sex'] ?? '');
$dateOfBirth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
$breed       = trim($_POST['breed'] ?? '');
$estimatedWeight = (
    isset($_POST['estimated_weight']) && $_POST['estimated_weight'] !== ''
) ? floatval($_POST['estimated_weight']) : null;
$number = max(1, intval($_POST['number'] ?? 1));
$forSale = intval($_POST['for_sale'] ?? 0);
$feedingSchedule = trim($_POST['feeding_schedule'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($name === '' || $type === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Livestock name and type are required.'
    ]);
    exit;
}

/* =========================================================
   VALIDATE HOUSE
   The Farm Map sends the numeric house_id from livestock_houses.
========================================================= */
if ($houseId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid livestock house.'
    ]);
    exit;
}

$houseStmt = $conn->prepare("\n    SELECT house_id, house_code, house_name\n    FROM livestock_houses\n    WHERE house_id = ?\n      AND archived = 0\n    LIMIT 1\n");

if (!$houseStmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Database preparation failed: ' . $conn->error
    ]);
    exit;
}

$houseStmt->bind_param('i', $houseId);
$houseStmt->execute();
$houseResult = $houseStmt->get_result();
$validHouse = $houseResult->fetch_assoc();
$houseStmt->close();

if (!$validHouse) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid livestock house. The selected house does not exist or is archived.'
    ]);
    exit;
}

/*
 * For NEW livestock, the selected house determines the animal type.
 * This prevents adding a rabbit to a goat house, etc.
 */
if ($livestockId <= 0) {
    $houseKey = strtolower(str_replace(['_', ' '], '-',
        ($validHouse['house_code'] ?? '') . '-' . ($validHouse['house_name'] ?? '')
    ));

    if (strpos($houseKey, 'rabbit') !== false) {
        $type = 'Rabbit';
    } elseif (strpos($houseKey, 'goat') !== false) {
        $type = 'Goat';
    } elseif (strpos($houseKey, 'poultry') !== false || strpos($houseKey, 'chicken') !== false) {
        $type = 'Chicken';
    } elseif (strpos($houseKey, 'swine') !== false || strpos($houseKey, 'pig') !== false) {
        $type = 'Pig';
    }
}

if ($name === '' || $type === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Livestock name and type are required.'
    ]);
    exit;
}

/* =========================================================
   UPDATE EXISTING LIVESTOCK
========================================================= */
if ($livestockId > 0) {
    $currentImage = '';

    $imageQuery = $conn->prepare("\n        SELECT image\n        FROM livestocks\n        WHERE livestock_id = ?\n          AND archived = 0\n        LIMIT 1\n    ");

    if (!$imageQuery) {
        echo json_encode([
            'success' => false,
            'message' => 'Database preparation failed: ' . $conn->error
        ]);
        exit;
    }

    $imageQuery->bind_param('i', $livestockId);
    $imageQuery->execute();
    $imageResult = $imageQuery->get_result();

    if ($imageRow = $imageResult->fetch_assoc()) {
        $currentImage = $imageRow['image'] ?? '';
    } else {
        $imageQuery->close();
        echo json_encode([
            'success' => false,
            'message' => 'Livestock record not found.'
        ]);
        exit;
    }
    $imageQuery->close();

    $newImage = $currentImage;

    /* ---------------------------------------------------------
       IMAGE UPLOAD FOR UPDATE
    --------------------------------------------------------- */
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

        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode([
                'success' => false,
                'message' => 'Image is too large. Maximum size is 5 MB.'
            ]);
            exit;
        }

        $uploadDirectory = __DIR__ . '/uploads/';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0777, true)) {
            echo json_encode([
                'success' => false,
                'message' => 'Unable to create livestock upload folder.'
            ]);
            exit;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = 'livestock_' . $livestockId . '_' . time() . '.' . $extension;
        $targetPath = $uploadDirectory . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo json_encode([
                'success' => false,
                'message' => 'Unable to upload livestock image.'
            ]);
            exit;
        }

        $newImage = 'livestock/uploads/' . $fileName;
    }

    $sql = "
        UPDATE livestocks
        SET
            house_id = ?,
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

    $stmt->bind_param(
        'isssssdissssi',
        $houseId,
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

    if (!$stmt->execute()) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update livestock record: ' . $stmt->error
        ]);
        $stmt->close();
        exit;
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Livestock profile updated successfully.',
        'livestock_id' => $livestockId,
        'image' => $newImage
    ]);
    exit;
}

/* =========================================================
   ADD NEW LIVESTOCK
========================================================= */
$sql = "
    INSERT INTO livestocks
    (
        house_id,
        name,
        type,
        sex,
        date_of_birth,
        breed,
        estimated_weight,
        number,
        for_sale,
        feeding_schedule,
        notes,
        image,
        archived
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', 0)
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Database preparation failed: ' . $conn->error
    ]);
    exit;
}

$emptyImage = '';
$stmt->bind_param(
    'isssssdisss',
    $houseId,
    $name,
    $type,
    $sex,
    $dateOfBirth,
    $breed,
    $estimatedWeight,
    $number,
    $forSale,
    $feedingSchedule,
    $notes
);

if (!$stmt->execute()) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add livestock record: ' . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$newLivestockId = $conn->insert_id;
$stmt->close();

$newImage = '';

/* ---------------------------------------------------------
   IMAGE UPLOAD FOR NEW LIVESTOCK
--------------------------------------------------------- */
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
            'message' => 'Livestock was added, but the image format is invalid.'
        ]);
        exit;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode([
            'success' => false,
            'message' => 'Livestock was added, but the image is larger than 5 MB.'
        ]);
        exit;
    }

    $uploadDirectory = __DIR__ . '/uploads/';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0777, true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Livestock was added, but the upload folder could not be created.'
        ]);
        exit;
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileName = 'livestock_' . $newLivestockId . '_' . time() . '.' . $extension;
    $targetPath = $uploadDirectory . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $newImage = 'livestock/uploads/' . $fileName;

        $imageUpdate = $conn->prepare("\n            UPDATE livestocks\n            SET image = ?\n            WHERE livestock_id = ?\n        ");

        if ($imageUpdate) {
            $imageUpdate->bind_param('si', $newImage, $newLivestockId);
            $imageUpdate->execute();
            $imageUpdate->close();
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Livestock added successfully.',
    'livestock_id' => $newLivestockId,
    'image' => $newImage
]);
?>
