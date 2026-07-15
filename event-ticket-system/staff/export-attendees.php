<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['Admin', 'Organizer']);

$db = get_db();
$role = current_role();
$event_id = (int)($_GET['event_id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM Events WHERE Event_ID = ?');
$stmt->execute([$event_id]);
$event = $stmt->fetch();
if (!$event) { $_SESSION['flash_error'] = 'Event not found.'; redirect('/staff/events.php'); }
if ($role === 'Organizer' && (int)$event['Staff_ID'] !== (int)$_SESSION['staff_id']) {
    $_SESSION['flash_error'] = 'You can only export attendees for events you organize.';
    redirect('/staff/events.php');
}

$rows_stmt = $db->prepare(
    "SELECT b.Booking_ID, c.First_Name, c.Last_Name, c.Email_Address, c.Phone_Number,
            tt.Tier_Name, tt.Price, s.Seat_Row, s.Seat_Number, b.Booking_Status, b.Booking_Date,
            q.QR_Data, q.Is_Used, t.Transaction_Reference_Number
     FROM Bookings b
     JOIN Ticket_Tiers tt ON tt.Tier_ID = b.Tier_ID
     JOIN Customers c ON c.Customer_ID = b.Customer_ID
     LEFT JOIN Seats s ON s.Seat_ID = b.Seat_ID
     LEFT JOIN QR_Code q ON q.Booking_ID = b.Booking_ID
     LEFT JOIN Transactions t ON t.Transaction_ID = b.Transaction_ID
     WHERE tt.Event_ID = ?
     ORDER BY b.Booking_Date ASC"
);
$rows_stmt->execute([$event_id]);
$rows = $rows_stmt->fetchAll();

$filename = 'attendees-' . preg_replace('/[^a-z0-9]+/i', '-', $event['Event_Name']) . '-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, [
    'Booking ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Ticket Tier', 'Price',
    'Seat', 'Booking Status', 'Booking Date', 'QR Code', 'Checked In', 'Payment Reference',
]);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['Booking_ID'],
        $r['First_Name'],
        $r['Last_Name'],
        $r['Email_Address'],
        $r['Phone_Number'],
        $r['Tier_Name'],
        number_format((float)$r['Price'], 2),
        $r['Seat_Row'] ? $r['Seat_Row'] . $r['Seat_Number'] : 'General Admission',
        $r['Booking_Status'],
        $r['Booking_Date'],
        $r['QR_Data'],
        $r['Is_Used'] ? 'Yes' : 'No',
        $r['Transaction_Reference_Number'],
    ]);
}
fclose($out);
