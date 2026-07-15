<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_customer();

$db = get_db();
$ids = array_filter(array_map('intval', explode(',', $_GET['booking_ids'] ?? '')));
if (!$ids) redirect('/customer/my-tickets.php');

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare(
    "SELECT b.Booking_ID, b.Booking_Status, tt.Tier_Name, tt.Price, e.Event_Name, s.Seat_Row, s.Seat_Number
     FROM Bookings b
     JOIN Ticket_Tiers tt ON tt.Tier_ID = b.Tier_ID
     JOIN Events e ON e.Event_ID = tt.Event_ID
     LEFT JOIN Seats s ON s.Seat_ID = b.Seat_ID
     WHERE b.Booking_ID IN ($placeholders) AND b.Customer_ID = ?"
);
$stmt->execute(array_merge($ids, [$_SESSION['customer_id']]));
$bookings = $stmt->fetchAll();

if (!$bookings) redirect('/customer/my-tickets.php');

$total = array_sum(array_column($bookings, 'Price'));
$page_title = 'Booking Confirmed — TicketStub';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap" style="max-width:640px;">
    <span class="hero-eyebrow">Order confirmed</span>
    <h2><?= e($bookings[0]['Event_Name']) ?> — <?= count($bookings) ?> tickets</h2>
    <p>Each ticket has its own QR code. Tap one to view and check in with it separately.</p>

    <div class="panel">
      <?php foreach ($bookings as $b): ?>
        <a class="tier-option" style="text-decoration:none;" href="<?= base_url('/customer/ticket.php?booking_id=' . $b['Booking_ID']) ?>">
          <div>
            <div class="tier-name">Ticket #<?= (int)$b['Booking_ID'] ?> — <?= e($b['Tier_Name']) ?></div>
            <div class="tier-remaining"><?= $b['Seat_Row'] ? 'Seat ' . e($b['Seat_Row'] . $b['Seat_Number']) : 'General admission' ?></div>
          </div>
          <div class="tier-price"><?= format_money((float)$b['Price']) ?></div>
        </a>
      <?php endforeach; ?>
      <div class="ticket-footer" style="padding-top:16px; border-top:1px solid var(--border);">
        <span style="font-weight:600;">Total paid</span>
        <span class="tier-price" style="font-size:1.2rem;"><?= format_money($total) ?></span>
      </div>
    </div>

    <div style="display:flex; gap:12px; justify-content:center;">
      <a class="btn btn-primary" href="<?= base_url('/customer/my-tickets.php') ?>">Go to My Tickets</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
