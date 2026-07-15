<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_customer();

$db = get_db();
$per_page = 9;
$page = current_page();

$count_stmt = $db->prepare('SELECT COUNT(*) FROM Bookings WHERE Customer_ID = ?');
$count_stmt->execute([$_SESSION['customer_id']]);
$total = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$stmt = $db->prepare(
    "SELECT b.Booking_ID, b.Booking_Status, b.Booking_Date, tt.Tier_Name, tt.Price,
            e.Event_Name, e.Start_Date_Time, e.Category, v.Venue_Name, s.Seat_Row, s.Seat_Number
     FROM Bookings b
     JOIN Ticket_Tiers tt ON tt.Tier_ID = b.Tier_ID
     JOIN Events e ON e.Event_ID = tt.Event_ID
     JOIN Venues v ON v.Venue_ID = e.Venue_ID
     LEFT JOIN Seats s ON s.Seat_ID = b.Seat_ID
     WHERE b.Customer_ID = ?
     ORDER BY b.Booking_Date DESC
     LIMIT $per_page OFFSET $offset"
);
$stmt->execute([$_SESSION['customer_id']]);
$bookings = $stmt->fetchAll();

$page_title = 'My Tickets — TicketStub';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <div><h2>My tickets</h2><p>Every booking you've made, with live status.</p></div>
    </div>

    <?php if (!$bookings): ?>
      <div class="empty-state">
        <h2>No tickets yet</h2>
        <p>Once you book an event, your QR tickets will show up here.</p>
        <a class="btn btn-primary" href="<?= base_url('/index.php') ?>">Browse events</a>
      </div>
    <?php else: ?>
      <div class="event-grid">
        <?php foreach ($bookings as $b):
          $status_class = ['confirmed'=>'status-confirmed','pending'=>'status-pending','used'=>'status-used','cancelled'=>'status-cancelled'][$b['Booking_Status']] ?? 'status-pending';
        ?>
          <a class="ticket" style="grid-template-columns:1fr 110px;" href="<?= base_url('/customer/ticket.php?booking_id=' . $b['Booking_ID']) ?>">
            <div class="ticket-main">
              <span class="status-pill <?= $status_class ?>" style="width:fit-content;"><?= e($b['Booking_Status']) ?></span>
              <h3><?= e($b['Event_Name']) ?></h3>
              <div class="ticket-meta">
                <span>📍 <?= e($b['Venue_Name']) ?></span>
                <span>🗓 <?= (new DateTime($b['Start_Date_Time']))->format('D, M j, Y · g:i A') ?></span>
                <span>🎟 <?= e($b['Tier_Name']) ?><?= $b['Seat_Row'] ? ' · Seat ' . e($b['Seat_Row'] . $b['Seat_Number']) : '' ?></span>
              </div>
            </div>
            <div class="ticket-stub">
              <span class="stub-label">Paid</span>
              <span class="stub-price" style="font-size:1.2rem;"><?= number_format((float)$b['Price'],0) ?></span>
              <span class="stub-label">View →</span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
      <?= render_pagination($page, $total_pages, '/customer/my-tickets.php') ?>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
