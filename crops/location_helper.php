<?php

/*
 * Central crop-location helper.
 * Reuses the existing plots table and crops.plot_id field.
 * No new crop table or column is created.
 */

function sanctuarioEnsureCropLocations(mysqli $conn): array
{
    $required = [
        'nursery' => 'NURSERY',
        'mno'     => 'MNO'
    ];

    $locations = [];

    foreach ($required as $code => $name) {
        $stmt = $conn->prepare("SELECT plot_id, plot_code, plot_name FROM plots WHERE LOWER(plot_code) = LOWER(?) LIMIT 1");
        if (!$stmt) {
            continue;
        }

        $stmt->bind_param('s', $code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $insert = $conn->prepare("INSERT INTO plots (plot_code, plot_name, archived) VALUES (?, ?, 0)");
            if ($insert) {
                $insert->bind_param('ss', $code, $name);
                $insert->execute();
                $insert->close();
            }

            $stmt = $conn->prepare("SELECT plot_id, plot_code, plot_name FROM plots WHERE LOWER(plot_code) = LOWER(?) LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $code);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }

        if ($row) {
            $locations[$code] = $row;
        }
    }
    foreach (['Nursery' => 'nursery', 'MNO' => 'mno'] as $legacyName => $code) {
        if (empty($locations[$code]['plot_id'])) {
            continue;
        }

        $locationId = (int)$locations[$code]['plot_id'];
        $legacy = $legacyName;

        $migrate = $conn->prepare("\n            UPDATE crops\n            SET plot_id = ?\n            WHERE archived = 0\n              AND LOWER(TRIM(COALESCE(transplanted_area, ''))) = LOWER(?)\n              AND plot_id <> ?\n        ");

        if ($migrate) {
            $migrate->bind_param('isi', $locationId, $legacy, $locationId);
            $migrate->execute();
            $migrate->close();
        }
    }

    return $locations;
}

function sanctuarioGetCropLocations(mysqli $conn): array
{
    sanctuarioEnsureCropLocations($conn);

    $locations = [];
    $result = $conn->query("\n        SELECT plot_id, plot_code, plot_name\n        FROM plots\n        WHERE archived = 0\n        ORDER BY plot_id ASC\n    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $locations[] = $row;
        }
    }

    return $locations;
}

function sanctuarioFindLocationId(mysqli $conn, string $code): int
{
    $stmt = $conn->prepare("SELECT plot_id FROM plots WHERE LOWER(plot_code) = LOWER(?) AND archived = 0 LIMIT 1");
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int)$row['plot_id'] : 0;
}