<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$db = get_db();
$events = $db->query(
    "SELECT e.*, v.Venue_Name, v.Address,
            (SELECT MIN(Price) FROM Ticket_Tiers WHERE Event_ID = e.Event_ID) AS min_price
     FROM Events e
     JOIN Venues v ON v.Venue_ID = e.Venue_ID
     WHERE e.Event_Status = 'published' AND e.Start_Date_Time >= NOW()
     ORDER BY e.Start_Date_Time ASC"
)->fetchAll();

$categories = array_values(array_unique(array_filter(array_column($events, 'Category'))));
$total_events = count($events);

$page_title = 'TicketStub';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <span class="hero-eyebrow">● Book Your Tickets Now!</span>
      <h1>Your ticket to <span class="accent">what's&nbsp;next.</span></h1>
      <p class="hero-sub">Browse concerts, festivals, and shows near you.</p>
      <div class="hero-actions">
        <a href="#events" class="btn btn-primary">Browse events</a>
        <?php if (!is_logged_in()): ?>
          <a href="<?= base_url('/register.php') ?>" class="btn btn-ghost">Create an account</a>
        <?php endif; ?>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><b><?= $total_events ?></b><span>Upcoming events</span></div>
        
      </div>
    </div>
    <div class="ticket" style="grid-template-columns:1fr;">
      <div class="ticket-main">
        <span class="ticket-category">Featured</span>
        <?php if ($events): $f = $events[0]; ?>
          <h3><?= e($f['Event_Name']) ?></h3>
          <div class="ticket-meta">
            <span>📍 <?= e($f['Venue_Name']) ?></span>
            <span>🗓 <?= format_date_range($f['Start_Date_Time'], $f['End_Date_Time']) ?></span>
          </div>
          <div class="ticket-footer">
            <div class="ticket-from"><span>From</span><br><b><?= format_money((float)$f['min_price']) ?></b></div>
            <a class="btn btn-primary btn-sm" href="<?= base_url('/event-details.php?id=' . $f['Event_ID']) ?>">View event</a>
          </div>
        <?php else: ?>
          <p>No events yet — check back soon.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="section" id="events">
  <div class="wrap">
    <div class="section-head">
      <div>
        <h2>Upcoming events</h2>
        <p>Everything on sale right now, updated in real time.</p>
      </div>
    </div>

    <div class="filter-bar" style="align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <button class="chip active" data-filter="all">All</button>
        <?php foreach ($categories as $cat): ?>
          <button class="chip" data-filter="<?= e($cat) ?>"><?= e($cat) ?></button>
        <?php endforeach; ?>
      </div>
      <div style="max-width:280px; width:100%;">
        <input type="text" id="eventSearch" placeholder="Search events or venues…" style="margin:0;">
      </div>
    </div>

    <?php if (!$events): ?>
      <div class="empty-state">
        <h2>No events on sale</h2>
        <p>There's nothing published right now. Organizers can add events from the staff dashboard.</p>
      </div>
    <?php else: ?>
      <div class="event-grid">
        <?php foreach ($events as $ev): ?>
          <a class="ticket" data-category="<?= e($ev['Category']) ?>" data-search="<?= e(strtolower($ev['Event_Name'] . ' ' . $ev['Venue_Name'])) ?>" href="<?= base_url('/event-details.php?id=' . $ev['Event_ID']) ?>">
            <div class="ticket-main">
              <div class="ticket-poster">
                <?php if (!empty($ev['Poster_Image'])): ?>
                  <img src="<?= e($ev['Poster_Image']) ?>" alt="">
                <?php else: ?>
                  <span class="poster-fallback"><?= e($ev['Event_Name']) ?></span>
                <?php endif; ?>
              </div>
              <span class="ticket-category"><?= e($ev['Category'] ?: 'Event') ?></span>
              <h3><?= e($ev['Event_Name']) ?></h3>
              <div class="ticket-meta">
                <span>📍 <?= e($ev['Venue_Name']) ?></span>
                <span>🗓 <?= format_date_range($ev['Start_Date_Time'], $ev['End_Date_Time']) ?></span>
              </div>
            </div>
            <div class="ticket-stub">
              <span class="stub-label">From</span>
              <span class="stub-price"><?= number_format((float)$ev['min_price'], 0) ?></span>
              <span class="stub-label">PHP</span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="empty-state" id="noResults" style="display:none;">
        <h2>No matches</h2>
        <p>Try a different search term or category.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
