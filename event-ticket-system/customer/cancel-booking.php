<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_customer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/customer/my-tickets.php');
verify_csrf($_POST['csrf_token'] ?? null);

$db = get_db();
$booking_id = (int)($_POST['booking_id'] ?? 0);

$stmt = $db->prepare(
    "SELECT b.*, e.Start_Date_Time, e.Event_Name
     FROM Bookings b
     JOIN Ticket_Tiers tt ON tt.Tier_ID = b.Tier_ID
     JOIN Events e ON e.Event_ID = tt.Event_ID
     WHERE b.Booking_ID = ? AND b.Customer_ID = ?"
);
$stmt->execute([$booking_id, $_SESSION['customer_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    $_SESSION['flash_error'] = 'Booking not found.';
    redirect('/customer/my-tickets.php');
}

if ($booking['Booking_Status'] !== 'confirmed') {
    $_SESSION['flash_error'] = 'Only confirmed bookings can be cancelled.';
    redirect('/customer/ticket.php?booking_id=' . $booking_id);
}

if (strtotime($booking['Start_Date_Time']) <= time()) {
    $_SESSION['flash_error'] = 'This event has already started, so it can no longer be cancelled online.';
    redirect('/customer/ticket.php?booking_id=' . $booking_id);
}

try {
    $db->beginTransaction();
    $db->prepare("UPDATE Bookings SET Booking_Status = 'cancelled' WHERE Booking_ID = ?")->execute([$booking_id]);
    $db->prepare('UPDATE Ticket_Tiers SET Quantity_Sold = GREATEST(0, Quantity_Sold - 1) WHERE Tier_ID = ?')->execute([$booking['Tier_ID']]);
    // Invalidate the QR code so it can never be scanned in, even though Booking_Status already blocks it.
    $db->prepare('UPDATE QR_Code SET Is_Used = 1 WHERE Booking_ID = ?')->execute([$booking_id]);
    $db->commit();
    $_SESSION['flash_success'] = 'Your booking for "' . $booking['Event_Name'] . '" has been cancelled.';
} catch (Throwable $e) {
    $db->rollBack();
    $_SESSION['flash_error'] = 'Could not cancel the booking. Please try again.';
}

redirect('/customer/my-tickets.php');
