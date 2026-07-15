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

// Seats with an active booking anywhere can never be deleted or renumbered.
$locked_stmt = $db->prepare(
    "SELECT DISTINCT s.Seat_ID
     FROM Seats s
     JOIN Bookings b ON b.Seat_ID = s.Seat_ID
     WHERE s.Venue_ID = ? AND b.Booking_Status IN ('pending','confirmed','used')"
);
$locked_stmt->execute([$venue_id]);
$locked_ids = array_column($locked_stmt->fetchAll(), 'Seat_ID');
$locked_set = array_flip($locked_ids);

// Existing seats, keyed by Seat_ID, so we know each locked seat's real Row/Number
// (client payload is never trusted for locked seats).
$existing_stmt = $db->prepare('SELECT * FROM Seats WHERE Venue_ID = ?');
$existing_stmt->execute([$venue_id]);
$existing_by_id = [];
foreach ($existing_stmt->fetchAll() as $row) { $existing_by_id[$row['Seat_ID']] = $row; }

$incoming_ids = [];
foreach ($incoming as $s) { if (!empty($s['seat_id'])) $incoming_ids[] = (int)$s['seat_id']; }

$warning = null;

try {
    $db->beginTransaction();

    // 1) Delete any existing seat that's gone from the new layout — but never a locked one.
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

    // 2) Pass A — move every existing, non-locked seat we're about to update to a
    //    temporary unique Row/Number so the final renumbering pass never collides
    //    with the unique_seat (Venue_ID, Seat_Row, Seat_Number) constraint.
    $temp_stmt = $db->prepare('UPDATE Seats SET Seat_Row = ?, Seat_Number = ? WHERE Seat_ID = ?');
    foreach ($incoming as $s) {
        $seat_id = !empty($s['seat_id']) ? (int)$s['seat_id'] : null;
        if ($seat_id && isset($existing_by_id[$seat_id]) && !isset($locked_set[$seat_id])) {
            $temp_stmt->execute(['_tmp', $seat_id, $seat_id]); // Seat_ID is unique, so (_tmp, Seat_ID) is always unique
        }
    }

    // 3) Pass B — insert new seats / apply final positions for non-locked seats.
    //    Locked seats keep their original Seat_Row/Seat_Number no matter what the
    //    client sent; only their cosmetic Pos_X/Pos_Y/Section_Label may change.
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

    // A locked seat is NEVER deleted even if the client omitted it from the
    // payload entirely (e.g. stale UI state, or a direct API call) — step 1
    // already guaranteed that. So the response must reflect the *actual*
    // final table, not just what happened to be in the incoming payload,
    // or an admin could be told "0 seats saved" while a booked seat is
    // silently still sitting in the database untouched.
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
