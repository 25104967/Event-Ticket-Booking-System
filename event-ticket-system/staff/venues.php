<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['Admin', 'Organizer']);

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $del_id = (int)($_POST['venue_id'] ?? 0);

    $event_count_stmt = $db->prepare('SELECT COUNT(*) FROM Events WHERE Venue_ID = ?');
    $event_count_stmt->execute([$del_id]);
    $event_count = (int)$event_count_stmt->fetchColumn();

    if ($event_count > 0) {
        $_SESSION['flash_error'] = "This venue can't be deleted — it still has $event_count event(s) using it. Remove or reassign those events first.";
    } else {
        try {
            // Seats cascade-delete automatically (Seats.Venue_ID ON DELETE CASCADE).
            // No events reference this venue (checked above), so this is always safe.
            $db->prepare('DELETE FROM Venues WHERE Venue_ID = ?')->execute([$del_id]);
            $_SESSION['flash_success'] = 'Venue deleted.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = "This venue couldn't be deleted because other records still reference it.";
        }
    }
    redirect('/staff/venues.php');
}

$venues = $db->query(
    "SELECT v.*,
            (SELECT COUNT(*) FROM Seats s WHERE s.Venue_ID = v.Venue_ID) AS seat_count,
            (SELECT COUNT(*) FROM Events e WHERE e.Venue_ID = v.Venue_ID) AS event_count
     FROM Venues v
     ORDER BY v.Venue_Name"
)->fetchAll();

$page_title = 'Manage Venues — TicketStub';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <div>
        <h2>Manage venues</h2>
        <p>Venues and their seat maps. Any Organizer or Admin can design a venue's seat layout.</p>
      </div>
      <a class="btn btn-primary" href="<?= base_url('/staff/venue-form.php') ?>">+ New venue</a>
    </div>

    <?php if (!$venues): ?>
      <div class="empty-state">
        <h2>No venues yet</h2>
        <p>Create a venue, then design its seat map before adding a reserved-seating event.</p>
        <a class="btn btn-primary" href="<?= base_url('/staff/venue-form.php') ?>">+ New venue</a>
      </div>
    <?php else: ?>
      <div class="panel">
        <table class="data-table">
          <thead><tr><th>Venue</th><th>Address</th><th>Capacity</th><th>Seats mapped</th><th>Events</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($venues as $v): ?>
              <tr>
                <td><?= e($v['Venue_Name']) ?></td>
                <td><?= e($v['Address']) ?></td>
                <td><?= (int)$v['Max_Capacity'] ?></td>
                <td><?= (int)$v['seat_count'] ?: '—' ?></td>
                <td><?= (int)$v['event_count'] ?></td>
                <td style="white-space:nowrap;">
                  <a class="btn btn-ghost btn-sm" href="<?= base_url('/staff/venue-seatmap.php?venue_id=' . $v['Venue_ID']) ?>">
                    <?= (int)$v['seat_count'] ? 'Edit seat map' : 'Design seat map' ?>
                  </a>
                  <a class="btn btn-ghost btn-sm" href="<?= base_url('/staff/venue-form.php?id=' . $v['Venue_ID']) ?>">Edit</a>
                  <form method="post" style="display:inline;" onsubmit="return confirm('Delete this venue? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="venue_id" value="<?= $v['Venue_ID'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm" <?= (int)$v['event_count'] ? 'disabled title="Remove this venue\'s events first"' : '' ?>>Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
