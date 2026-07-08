<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_customer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/index.php');
verify_csrf($_POST['csrf_token'] ?? null);

$db = get_db();
$customer_id = $_SESSION['customer_id'];
$event_id = (int)($_POST['event_id'] ?? 0);
$tier_id  = (int)($_POST['tier_id'] ?? 0);
$MAX_PER_CHECKOUT = 6;

$tier_stmt = $db->prepare('SELECT * FROM Ticket_Tiers WHERE Tier_ID = ? AND Event_ID = ?');
$tier_stmt->execute([$tier_id, $event_id]);
$tier = $tier_stmt->fetch();

if (!$tier) {
    $_SESSION['flash_error'] = 'That ticket tier is no longer available.';
    redirect('/event-details.php?id=' . $event_id);
}

$requires_seat = (stripos($tier['Tier_Name'], 'reserved') !== false);

// Build the list of "units" to book: either one per selected seat, or `quantity` GA units (seat = null)
$seat_ids = [];
if ($requires_seat) {
    $seat_ids = array_map('intval', $_POST['seat_ids'] ?? []);
    $seat_ids = array_values(array_unique(array_filter($seat_ids)));
    if (!$seat_ids) {
        $_SESSION['flash_error'] = 'Please select at least one seat before confirming.';
        redirect('/customer/booking.php?event_id=' . $event_id . '&tier_id=' . $tier_id);
    }
    if (count($seat_ids) > $MAX_PER_CHECKOUT) {
        $_SESSION['flash_error'] = 'You can book at most ' . $MAX_PER_CHECKOUT . ' tickets per order.';
        redirect('/customer/booking.php?event_id=' . $event_id . '&tier_id=' . $tier_id);
    }
    $quantity = count($seat_ids);
} else {
    $quantity = max(1, min($MAX_PER_CHECKOUT, (int)($_POST['quantity'] ?? 1)));
}

try {
    $db->beginTransaction();

    // Re-check availability inside the transaction to prevent race conditions / overbooking.
    if (tier_remaining($db, $tier_id) < $quantity) {
        throw new RuntimeException('Only ' . tier_remaining($db, $tier_id) . ' left in this tier now — please adjust your order.');
    }

    if ($requires_seat) {
        // Lock and verify every requested seat is still free for this event
        $placeholders = implode(',', array_fill(0, count($seat_ids), '?'));
        $check = $db->prepare(
            "SELECT b.Seat_ID FROM Bookings b
             JOIN Ticket_Tiers tt ON tt.Tier_ID = b.Tier_ID
             WHERE tt.Event_ID = ? AND b.Seat_ID IN ($placeholders) AND b.Booking_Status IN ('pending','confirmed','used')
             FOR UPDATE"
        );
        $check->execute(array_merge([$event_id], $seat_ids));
        if ($check->fetch()) {
            throw new RuntimeException('One of your selected seats was just taken by someone else. Please pick again.');
        }
    }

    // 1) Create ONE transaction covering the whole order (one receipt, multiple tickets)
    $total_amount = (float)$tier['Price'] * $quantity;
    $payment_method = $_POST['payment_method'] ?? 'Mock';
    $reference = generate_reference('PAY');
    // -----------------------------------------------------------------
    // INTEGRATION POINT: swap this block for a real call to your payment
    // gateway (GCash / Maya / DragonPay) per Section 3.9 of the docs.
    // -----------------------------------------------------------------
    $insert_txn = $db->prepare(
        'INSERT INTO Transactions (Amount_Paid, Payment_Method, Transaction_Status, Transaction_Reference_Number)
         VALUES (?,?,?,?)'
    );
    $insert_txn->execute([$total_amount, $payment_method, 'success', $reference]);
    $transaction_id = (int)$db->lastInsertId();

    // 2) Create one Booking + one QR_Code per ticket, all linked to that transaction
    $event_end_stmt = $db->prepare('SELECT End_Date_Time, Event_Name FROM Events WHERE Event_ID = ?');
    $event_end_stmt->execute([$event_id]);
    $event_row = $event_end_stmt->fetch();
    $expiry = $event_row['End_Date_Time'];

    $booking_ids = [];
    $email_tickets = [];
    for ($i = 0; $i < $quantity; $i++) {
        $seat_id = $requires_seat ? $seat_ids[$i] : null;

        $insert_booking = $db->prepare(
            'INSERT INTO Bookings (Customer_ID, Tier_ID, Seat_ID, Transaction_ID, Booking_Status) VALUES (?,?,?,?,?)'
        );
        $insert_booking->execute([$customer_id, $tier_id, $seat_id, $transaction_id, 'confirmed']);
        $booking_id = (int)$db->lastInsertId();
        $booking_ids[] = $booking_id;

        $qr_data = generate_reference('QR') . '-' . bin2hex(random_bytes(4));
        $db->prepare('INSERT INTO QR_Code (Booking_ID, QR_Data, Expiry_Date) VALUES (?,?,?)')
           ->execute([$booking_id, $qr_data, $expiry]);

        $seat_label = null;
        if ($seat_id) {
            $seat_stmt = $db->prepare('SELECT Seat_Row, Seat_Number FROM Seats WHERE Seat_ID = ?');
            $seat_stmt->execute([$seat_id]);
            $seat_row = $seat_stmt->fetch();
            if ($seat_row) $seat_label = $seat_row['Seat_Row'] . $seat_row['Seat_Number'];
        }
        $email_tickets[] = [
            'event_name' => $event_row['Event_Name'],
            'tier_name'  => $tier['Tier_Name'],
            'seat'       => $seat_label,
            'qr_data'    => $qr_data,
        ];
    }

    // 3) Bump the sold counter
    $db->prepare('UPDATE Ticket_Tiers SET Quantity_Sold = Quantity_Sold + ? WHERE Tier_ID = ?')->execute([$quantity, $tier_id]);

    $db->commit();

    send_booking_confirmation_email(
        $_SESSION['user_email'],
        $_SESSION['user_name'],
        $email_tickets,
        $total_amount,
        $reference
    );

    $_SESSION['flash_success'] = $quantity > 1
        ? "Booking confirmed! Your $quantity QR tickets are ready below."
        : 'Booking confirmed! Your QR ticket is ready below.';

    if ($quantity === 1) {
        redirect('/customer/ticket.php?booking_id=' . $booking_ids[0]);
    }
    redirect('/customer/confirmation.php?booking_ids=' . implode(',', $booking_ids));

} catch (Throwable $e) {
    $db->rollBack();
    $_SESSION['flash_error'] = $e->getMessage() ?: 'Something went wrong while booking. Please try again.';
    redirect('/customer/booking.php?event_id=' . $event_id . '&tier_id=' . $tier_id);
}
