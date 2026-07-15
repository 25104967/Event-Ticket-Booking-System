<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$db = get_db();
$event_id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare(
    "SELECT e.*, v.Venue_Name, v.Address, v.Max_Capacity, s.First_Name AS Organizer_First, s.Last_Name AS Organizer_Last
     FROM Events e
     JOIN Venues v ON v.Venue_ID = e.Venue_ID
     JOIN Staff s ON s.Staff_ID = e.Staff_ID
     WHERE e.Event_ID = ?"
);
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    $page_title = 'Event not found — TicketStub';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><h2>Event not found</h2><p>This event may have been removed or the link is incorrect.</p><a class="btn btn-primary" href="' . base_url('/index.php') . '">Browse events</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$tiers = $db->prepare('SELECT * FROM Ticket_Tiers WHERE Event_ID = ? ORDER BY Price DESC');
$tiers->execute([$event_id]);
$tiers = $tiers->fetchAll();

$page_title = e($event['Event_Name']) . ' — TicketStub';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="wrap">
    <div style="display:grid; grid-template-columns: 1.4fr 1fr; gap:36px; align-items:start;">
      <div>
        <div class="ticket-poster" style="aspect-ratio:16/7; margin-bottom:24px;">
          <?php if (!empty($event['Poster_Image'])): ?>
            <img src="<?= e($event['Poster_Image']) ?>" alt="">
          <?php else: ?>
            <span class="poster-fallback" style="font-size:2rem;"><?= e($event['Event_Name']) ?></span>
          <?php endif; ?>
        </div>
        <span class="ticket-category"><?= e($event['Category'] ?: 'Event') ?></span>
        <h1 style="font-size: clamp(2rem,4vw,3rem); margin-top:6px;"><?= e($event['Event_Name']) ?></h1>
        <p style="font-size:1.02rem; color:var(--text); max-width:60ch; margin-top:12px;"><?= nl2br(e($event['Event_Description'])) ?></p>

        <div class="panel" style="margin-top:24px;">
          <h3 style="font-size:1.1rem;">Event details</h3>
          <div class="ticket-meta" style="font-size:0.95rem; gap:10px;">
            <span>🗓 <?= format_date_range($event['Start_Date_Time'], $event['End_Date_Time']) ?></span>
            <span>📍 <?= e($event['Venue_Name']) ?> — <?= e($event['Address']) ?></span>
            <span>👤 Organized by <?= e($event['Organizer_First'] . ' ' . $event['Organizer_Last']) ?></span>
            <span>🎫 Venue capacity: <?= (int)$event['Max_Capacity'] ?></span>
          </div>
        </div>
      </div>

      <div class="panel" style="position:sticky; top:96px;">
        <h3>Select your tickets</h3>
        <p style="margin-bottom:18px;"></p>
        <?php if (!$tiers): ?>
          <p>Ticket tiers for this event have not been published yet.</p>
        <?php else: ?>
          <?php foreach ($tiers as $tier): $remaining = tier_remaining($db, $tier['Tier_ID']); ?>
            <div class="tier-option" style="cursor:default;">
              <div>
                <div class="tier-name"><?= e($tier['Tier_Name']) ?></div>
                <div class="tier-remaining"><?= $remaining > 0 ? $remaining . ' left' : 'Sold out' ?></div>
              </div>
              <div class="tier-price"><?= format_money((float)$tier['Price']) ?></div>
            </div>
          <?php endforeach; ?>
          <a class="btn btn-primary btn-block" href="<?= base_url('/customer/booking.php?event_id=' . $event_id) ?>" style="margin-top:8px;">
            Book tickets
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
