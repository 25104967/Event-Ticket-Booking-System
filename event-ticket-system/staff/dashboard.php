<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['Admin', 'Organizer', 'Staff']); // any internal account may view the dashboard shell

$db = get_db();
$role = current_role();

$page_title = 'Dashboard — TicketStub';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <div>
        <h2>Welcome, <?= e($_SESSION['user_name']) ?></h2>
        <p>Signed in as <b style="color:var(--violet);"><?= e($role) ?></b> — this dashboard only shows what your role is allowed to see.</p>
      </div>
    </div>

    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:28px;">
      <?php if (in_array($role, ['Admin', 'Organizer'], true)): ?>
        <a class="btn btn-primary" href="<?= base_url('/staff/event-form.php') ?>">+ New event</a>
        <a class="btn btn-ghost" href="<?= base_url('/staff/events.php') ?>">Manage events</a>
        <a class="btn btn-ghost" href="<?= base_url('/staff/venues.php') ?>">Manage venues</a>
        <a class="btn btn-ghost" href="<?= base_url('/staff/export-sales.php') ?>">Export sales report (CSV)</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="<?= base_url('/staff/scan.php') ?>">QR check-in scanner</a>
      <?php if ($role === 'Admin'): ?>
        <a class="btn btn-ghost" href="<?= base_url('/staff/manage-staff.php') ?>">Manage staff accounts</a>
      <?php endif; ?>
    </div>

    <?php if (in_array($role, ['Admin', 'Organizer'], true)): ?>
      <?php
        // Admins see every event; Organizers see only events they created.
        $params = [];
        $scope_sql = '';
        if ($role === 'Organizer') {
            $scope_sql = 'WHERE e.Staff_ID = ?';
            // Bound in query-text order: the revenue subquery's placeholder comes
            // first, then the outer WHERE clause's placeholder.
            $params = [$_SESSION['staff_id'], $_SESSION['staff_id']];
        }

        $stats = $db->prepare(
            "SELECT COUNT(DISTINCT e.Event_ID) AS event_count,
                    COUNT(b.Booking_ID) AS tickets_sold,
                    (SELECT COALESCE(SUM(t3.Amount_Paid), 0) FROM Transactions t3
                     WHERE t3.Transaction_ID IN (
                         SELECT DISTINCT b3.Transaction_ID
                         FROM Bookings b3
                         JOIN Ticket_Tiers tt3 ON tt3.Tier_ID = b3.Tier_ID
                         JOIN Events e3 ON e3.Event_ID = tt3.Event_ID
                         WHERE b3.Booking_Status IN ('confirmed','used')
                           AND b3.Transaction_ID IS NOT NULL"
                           . ($role === 'Organizer' ? ' AND e3.Staff_ID = ?' : '') . "
                     )) AS revenue
             FROM Events e
             LEFT JOIN Ticket_Tiers tt ON tt.Event_ID = e.Event_ID
             LEFT JOIN Bookings b ON b.Tier_ID = tt.Tier_ID AND b.Booking_Status IN ('confirmed','used')
             $scope_sql"
        );
        $stats->execute($params);
        $s = $stats->fetch();

        $events_stmt = $db->prepare(
            "SELECT e.Event_ID, e.Event_Name, e.Start_Date_Time, e.Event_Status, v.Venue_Name,
                    (SELECT COUNT(*) FROM Ticket_Tiers tt2 JOIN Bookings b2 ON b2.Tier_ID = tt2.Tier_ID
                     WHERE tt2.Event_ID = e.Event_ID AND b2.Booking_Status IN ('confirmed','used')) AS sold
             FROM Events e JOIN Venues v ON v.Venue_ID = e.Venue_ID
             $scope_sql
             ORDER BY e.Start_Date_Time DESC LIMIT 10"
        );
        $events_stmt->execute($role === 'Organizer' ? [$_SESSION['staff_id']] : []);
        $events = $events_stmt->fetchAll();
      ?>
      <div class="stat-grid">
        <div class="stat-card"><div class="stat-label"><?= $role === 'Admin' ? 'Total events' : 'Your events' ?></div><div class="stat-value"><?= (int)$s['event_count'] ?></div></div>
        <div class="stat-card"><div class="stat-label">Tickets sold</div><div class="stat-value amber"><?= (int)$s['tickets_sold'] ?></div></div>
        <div class="stat-card"><div class="stat-label">Revenue</div><div class="stat-value violet"><?= format_money((float)$s['revenue']) ?></div></div>
      </div>

      <div class="panel">
        <h3 style="font-size:1.1rem;">Recent events</h3>
        <?php if (!$events): ?>
          <p>No events to show yet.</p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th>Event</th><th>Venue</th><th>Date</th><th>Status</th><th>Sold</th></tr></thead>
            <tbody>
              <?php foreach ($events as $ev): ?>
                <tr>
                  <td><?= e($ev['Event_Name']) ?></td>
                  <td><?= e($ev['Venue_Name']) ?></td>
                  <td><?= (new DateTime($ev['Start_Date_Time']))->format('M j, Y') ?></td>
                  <td><?= e($ev['Event_Status']) ?></td>
                  <td><?= (int)$ev['sold'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <div class="panel" style="border-style:dashed; opacity:0.85;">
        <h3 style="font-size:1.05rem;">Coming in a future phase</h3>
        <p>Sales report exports and richer analytics. Event/tier management, staff accounts, and QR check-in are already live.</p>
      </div>
    <?php endif; ?>

    <?php if ($role === 'Staff'): ?>
      <div class="panel">
        <h3 style="font-size:1.1rem;">🔍 QR check-in scanner</h3>
        <p>Validate and check in attendee tickets at the door.</p>
        <a class="btn btn-primary" href="<?= base_url('/staff/scan.php') ?>">Open scanner</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
