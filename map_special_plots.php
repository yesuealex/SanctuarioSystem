<?php
/**
 * Ensure the special crop facilities exist in the same plots/plot_beds tables
 * used by Plot A, Plot B, and Plot C. Safe to include on every page load.
 */
if (!isset($conn) || !($conn instanceof mysqli)) {
    return;
}

// Plot 9 is retired. Archive it so old database rows cannot make it reappear.
$conn->query("UPDATE plots SET archived=1 WHERE LOWER(plot_code)='plot_9' OR LOWER(plot_name)='plot 9'");

$specialPlots = [
    ['hydro', 'Hydro', 16],
    ['tent_house', 'Tent House', 9],
];

$plotStmt = $conn->prepare(
    'SELECT plot_id FROM plots WHERE LOWER(plot_code)=LOWER(?) LIMIT 1'
);
$insertPlotStmt = $conn->prepare(
    'INSERT INTO plots (plot_code, plot_name, archived) VALUES (?, ?, 0)'
);
$bedStmt = $conn->prepare(
    'SELECT bed_id FROM plot_beds WHERE plot_id=? AND bed_number=? LIMIT 1'
);
$insertBedStmt = $conn->prepare(
    'INSERT INTO plot_beds (plot_id, bed_number, bed_code) VALUES (?, ?, ?)'
);

if ($plotStmt && $insertPlotStmt && $bedStmt && $insertBedStmt) {
    foreach ($specialPlots as [$plotCode, $plotName, $bedCount]) {
        $plotId = 0;
        $plotStmt->bind_param('s', $plotCode);
        $plotStmt->execute();
        $plotRow = $plotStmt->get_result()->fetch_assoc();

        if ($plotRow) {
            $plotId = (int)$plotRow['plot_id'];
        } else {
            $insertPlotStmt->bind_param('ss', $plotCode, $plotName);
            if ($insertPlotStmt->execute()) {
                $plotId = (int)$conn->insert_id;
            }
        }

        if ($plotId <= 0) continue;

        for ($bedNumber = 1; $bedNumber <= $bedCount; $bedNumber++) {
            $bedStmt->bind_param('ii', $plotId, $bedNumber);
            $bedStmt->execute();
            if ($bedStmt->get_result()->num_rows > 0) continue;

            $bedCode = strtoupper($plotCode) . $bedNumber;
            $insertBedStmt->bind_param('iis', $plotId, $bedNumber, $bedCode);
            $insertBedStmt->execute();
        }
    }
}

if ($plotStmt) $plotStmt->close();
if ($insertPlotStmt) $insertPlotStmt->close();
if ($bedStmt) $bedStmt->close();
if ($insertBedStmt) $insertBedStmt->close();
