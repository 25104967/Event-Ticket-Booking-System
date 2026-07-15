<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['Admin', 'Organizer']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
verify_csrf($data['csrf_token'] ?? null);

$venue_id = (int)($data['venue_id'] ?? 0);
$incoming = is_array($data['seats'] ?? null) ? $data['seats'] : [];

$db = get_db();
$stmt = $db->prepare('SELECT * FROM Venues WHERE Venue_ID = ?');
$stmt->execute([$venue_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Venue not found.']);
    exit;
}


$locked_stmt = $db->prepare(
    "SELECT DISTINCT s.Seat_ID
     FROM Seats s
     JOIN Bookings b ON b.Seat_ID = s.Seat_ID
     WHERE s.Venue_ID = ? AND b.Booking_Status IN ('pending','confirmed','used')"
);
$locked_stmt->execute([$venue_id]);
$locked_ids = array_column($locked_stmt->fetchAll(), 'Seat_ID');
$locked_set = array_flip($locked_ids);


$existing_stmt = $db->prepare('SELECT * FROM Seats WHERE Venue_ID = ?');
$existing_stmt->execute([$venue_id]);
$existing_by_id = [];
foreach ($existing_stmt->fetchAll() as $row) { $existing_by_id[$row['Seat_ID']] = $row; }

$incoming_ids = [];
foreach ($incoming as $s) { if (!empty($s['seat_id'])) $incoming_ids[] = (int)$s['seat_id']; }

$warning = null;

try {
    $db->beginTransaction();

   
    $to_delete = [];
    foreach (array_keys($existing_by_id) as $seat_id) {
        if (!in_array($seat_id, $incoming_ids, true) && !isset($locked_set[$seat_id])) {
            $to_delete[] = $seat_id;
        }
    }
    if ($to_delete) {
        $placeholders = implode(',', array_fill(0, count($to_delete), '?'));
        $db->prepare("DELETE FROM Seats WHERE Seat_ID IN ($placeholders)")->execute($to_delete);
    }

  
    $temp_stmt = $db->prepare('UPDATE Seats SET Seat_Row = ?, Seat_Number = ? WHERE Seat_ID = ?');
    foreach ($incoming as $s) {
        $seat_id = !empty($s['seat_id']) ? (int)$s['seat_id'] : null;
        if ($seat_id && isset($existing_by_id[$seat_id]) && !isset($locked_set[$seat_id])) {
            $temp_stmt->execute(['_tmp', $seat_id, $seat_id]); // Seat_ID is unique, so (_tmp, Seat_ID) is always unique
        }
    }

 
    $insert_stmt = $db->prepare(
        'INSERT INTO Seats (Venue_ID, Seat_Row, Seat_Number, Section_Label, Pos_X, Pos_Y) VALUES (?,?,?,?,?,?)'
    );
    $update_stmt = $db->prepare(
        'UPDATE Seats SET Seat_Row = ?, Seat_Number = ?, Section_Label = ?, Pos_X = ?, Pos_Y = ? WHERE Seat_ID = ?'
    );
    $update_locked_stmt = $db->prepare(
        'UPDATE Seats SET Section_Label = ?, Pos_X = ?, Pos_Y = ? WHERE Seat_ID = ?'
    );

    $saved_seats = [];
    $locked_touched = 0;

    foreach ($incoming as $s) {
        $seat_id = !empty($s['seat_id']) ? (int)$s['seat_id'] : null;
        $row_label = substr(trim((string)($s['row_label'] ?? 'A')), 0, 5) ?: 'A';
        $seat_number = max(1, (int)($s['seat_number'] ?? 1));
        $section = $s['section'] !== null && trim((string)$s['section']) !== '' ? substr(trim((string)$s['section']), 0, 50) : null;
        $pos_x = (int)($s['pos_x'] ?? 0);
        $pos_y = (int)($s['pos_y'] ?? 0);

        if ($seat_id && isset($locked_set[$seat_id])) {
            $update_locked_stmt->execute([$section, $pos_x, $pos_y, $seat_id]);
            $locked_touched++;
            $existing = $existing_by_id[$seat_id];
            $saved_seats[] = ['Seat_ID' => $seat_id, 'Pos_X' => $pos_x, 'Pos_Y' => $pos_y];
        } elseif ($seat_id && isset($existing_by_id[$seat_id])) {
            $update_stmt->execute([$row_label, $seat_number, $section, $pos_x, $pos_y, $seat_id]);
            $saved_seats[] = ['Seat_ID' => $seat_id, 'Pos_X' => $pos_x, 'Pos_Y' => $pos_y];
        } else {
            $insert_stmt->execute([$venue_id, $row_label, $seat_number, $section, $pos_x, $pos_y]);
            $new_id = (int)$db->lastInsertId();
            $saved_seats[] = ['Seat_ID' => $new_id, 'Pos_X' => $pos_x, 'Pos_Y' => $pos_y];
        }
    }

    if ($locked_touched > 0) {
        $warning = "$locked_touched locked seat(s) kept their original seat label since they already have bookings.";
    }

 
    $omitted_locked = array_diff($locked_ids, $incoming_ids);
    if ($omitted_locked) {
        $locked_touched += count($omitted_locked);
        $warning = "$locked_touched locked seat(s) kept their original seat label and position since they already "
            . "have bookings (" . count($omitted_locked) . " of them weren't part of this save and were left completely untouched).";
    }

    $final_stmt = $db->prepare('SELECT Seat_ID, Pos_X, Pos_Y FROM Seats WHERE Venue_ID = ?');
    $final_stmt->execute([$venue_id]);
    $final_seats = $final_stmt->fetchAll();

    $db->commit();

    echo json_encode(['success' => true, 'seat_count' => count($final_seats), 'seats' => $final_seats, 'warning' => $warning]);
} catch (Throwable $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Could not save the seat map: ' . $e->getMessage()]);
}
