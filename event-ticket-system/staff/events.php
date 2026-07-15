<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['Admin', 'Organizer']);

$db = get_db();
$role = current_role();
$per_page = 10;
$page = current_page();

$params = [];
$scope_sql = '';
if ($role === 'Organizer') { $scope_sql = 'WHERE e.Staff_ID = ?'; $params[] = $_SESSION['staff_id']; }

$count_stmt = $db->prepare("SELECT COUNT(*) FROM Events e $scope_sql");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$stmt = $db->prepare(
    "SELECT e.*, v.Venue_Name,
            (SELECT COUNT(*) FROM Ticket_Tiers tt WHERE tt.Event_ID = e.Event_ID) AS tier_count,
            (SELECT COUNT(*) FROM Ticket_Tiers tt2 JOIN Bookings b2 ON b2.Tier_ID = tt2.Tier_ID
             WHERE tt2.Event_ID = e.Event_ID AND b2.Booking_Status IN ('confirmed','used')) AS sold
     FROM Events e JOIN Venues v ON v.Venue_ID = e.Venue_ID
     $scope_sql
     ORDER BY e.Start_Date_Time DESC
     LIMIT $per_page OFFSET $offset"
);
$stmt->execute($params);
$events = $stmt->fetchAll();

$page_title = 'Manage Events — TicketStub';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <div>
        <h2>Manage events</h2>
        <p><?= $role === 'Admin' ? 'Every event in the system.' : 'Events you organize.' ?></p>
      </div>
      <a class="btn btn-primary" href="<?= base_url('/staff/event-form.php') ?>">+ New event</a>
    </div>

    <?php if (!$events): ?>
      <div class="empty-state">
        <h2>No events yet</h2>
        <p>Create your first event to start selling tickets.</p>
        <a class="btn btn-primary" href="<?= base_url('/staff/event-form.php') ?>">+ New event</a>
      </div>
    <?php else: ?>
      <div class="panel">
        <table class="data-table">
          <thead><tr><th>Event</th><th>Venue</th><th>Date</th><th>Status</th><th>Tiers</th><th>Sold</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($events as $ev): ?>
              <tr>
                <td><?= e($ev['Event_Name']) ?></td>
                <td><?= e($ev['Venue_Name']) ?></td>
                <td><?= (new DateTime($ev['Start_Date_Time']))->format('M j, Y g:i A') ?></td>
                <td><span class="status-pill <?= $ev['Event_Status'] === 'published' ? 'status-confirmed' : ($ev['Event_Status'] === 'cancelled' ? 'status-cancelled' : 'status-pending') ?>"><?= e($ev['Event_Status']) ?></span></td>
                <td><?= (int)$ev['tier_count'] ?></td>
                <td><?= (int)$ev['sold'] ?></td>
                <td style="white-space:nowrap;">
                  <a class="btn btn-ghost btn-sm" href="<?= base_url('/staff/event-form.php?id=' . $ev['Event_ID']) ?>">Edit</a>
                  <a class="btn btn-ghost btn-sm" href="<?= base_url('/staff/event-tiers.php?event_id=' . $ev['Event_ID']) ?>">Tiers</a>
                  <a class="btn btn-ghost btn-sm" href="<?= base_url('/staff/export-attendees.php?event_id=' . $ev['Event_ID']) ?>">Export CSV</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= render_pagination($page, $total_pages, '/staff/events.php') ?>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
