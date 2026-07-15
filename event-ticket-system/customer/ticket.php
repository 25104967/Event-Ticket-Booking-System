<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_customer();

$db = get_db();
$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $db->prepare(
    "SELECT b.*, tt.Tier_Name, tt.Price, e.Event_Name, e.Start_Date_Time, e.End_Date_Time, e.Category,
            v.Venue_Name, v.Address, s.Seat_Row, s.Seat_Number, q.QR_Data, q.Is_Used, q.Expiry_Date,
            t.Transaction_Reference_Number, t.Payment_Method, t.Amount_Paid
     FROM Bookings b
     JOIN Ticket_Tiers tt ON tt.Tier_ID = b.Tier_ID
     JOIN Events e ON e.Event_ID = tt.Event_ID
     JOIN Venues v ON v.Venue_ID = e.Venue_ID
     LEFT JOIN Seats s ON s.Seat_ID = b.Seat_ID
     LEFT JOIN QR_Code q ON q.Booking_ID = b.Booking_ID
     LEFT JOIN Transactions t ON t.Transaction_ID = b.Transaction_ID
     WHERE b.Booking_ID = ? AND b.Customer_ID = ?"
);
$stmt->execute([$booking_id, $_SESSION['customer_id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    $_SESSION['flash_error'] = 'Ticket not found.';
    redirect('/customer/my-tickets.php');
}

$status_class = [
    'confirmed' => 'status-confirmed',
    'pending'   => 'status-pending',
    'used'      => 'status-used',
    'cancelled' => 'status-cancelled',
][$ticket['Booking_Status']] ?? 'status-pending';

$page_title = 'Your ticket — ' . e($ticket['Event_Name']);
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap">
    <div class="qr-ticket">
      <div class="qr-ticket-top">
        <span class="ticket-category"><?= e($ticket['Category'] ?: 'Event') ?></span>
        <h2><?= e($ticket['Event_Name']) ?></h2>
        <p>📍 <?= e($ticket['Venue_Name']) ?></p>
        <p>🗓 <?= format_date_range($ticket['Start_Date_Time'], $ticket['End_Date_Time']) ?></p>
      </div>
      <div class="qr-perf"></div>
      <div class="qr-ticket-bottom">
        <span class="status-pill <?= $status_class ?>"><?= e($ticket['Booking_Status']) ?></span>
        <div class="qr-code-box" id="qrCodeBox"></div>
        <div class="qr-ref"><?= e($ticket['QR_Data'] ?? 'N/A') ?></div>
        <p style="margin:0; font-size:0.8rem;">Show this code at the entrance. Each code scans once.</p>

        <div class="qr-meta-grid">
          <div><div class="label">Tier</div><?= e($ticket['Tier_Name']) ?></div>
          <div><div class="label">Seat</div><?= $ticket['Seat_Row'] ? e($ticket['Seat_Row'] . $ticket['Seat_Number']) : 'General admission' ?></div>
          <div><div class="label">Order total</div><?= format_money((float)$ticket['Amount_Paid']) ?></div>
          <div><div class="label">Payment ref</div><?= e($ticket['Transaction_Reference_Number'] ?? '—') ?></div>
          <div><div class="label">Booking ID</div>#<?= (int)$ticket['Booking_ID'] ?></div>
          <div><div class="label">Booked on</div><?= (new DateTime($ticket['Booking_Date']))->format('M j, Y g:i A') ?></div>
        </div>
      </div>
    </div>

    <div style="text-align:center; margin-top:26px; display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
      <a class="btn btn-ghost" href="<?= base_url('/customer/my-tickets.php') ?>">← My tickets</a>
      <button class="btn btn-primary" onclick="window.print()">Print / Save PDF</button>
      <?php if ($ticket['Booking_Status'] === 'confirmed' && strtotime($ticket['Start_Date_Time']) > time()): ?>
        <form method="post" action="<?= base_url('/customer/cancel-booking.php') ?>" onsubmit="return confirm('Cancel this ticket? This cannot be undone.');">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="booking_id" value="<?= (int)$ticket['Booking_ID'] ?>">
          <button type="submit" class="btn btn-danger">Cancel ticket</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
  new QRCode(document.getElementById("qrCodeBox"), {
    text: <?= json_encode($ticket['QR_Data'] ?? 'INVALID') ?>,
    width: 176,
    height: 176,
    colorDark: "#0B0B10",
    colorLight: "#ffffff"
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
