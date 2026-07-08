<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['Admin', 'Organizer']);

$db = get_db();
$role = current_role();
$event_id = (int)($_GET['event_id'] ?? 0);

$stmt = $db->prepare('SELECT e.*, v.Venue_Name FROM Events e JOIN Venues v ON v.Venue_ID = e.Venue_ID WHERE e.Event_ID = ?');
$stmt->execute([$event_id]);
$event = $stmt->fetch();
if (!$event) { $_SESSION['flash_error'] = 'Event not found.'; redirect('/staff/events.php'); }
if ($role === 'Organizer' && (int)$event['Staff_ID'] !== (int)$_SESSION['staff_id']) {
    http_response_code(403);
    $_SESSION['flash_error'] = 'You can only manage tiers for events you organize.';
    redirect('/staff/events.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $tier_name = trim($_POST['tier_name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $qty = (int)($_POST['quantity_available'] ?? 0);
        if ($tier_name === '') $errors[] = 'Please enter a tier name.';
        if ($price < 0) $errors[] = 'Price cannot be negative.';
        if ($qty <= 0) $errors[] = 'Quantity available must be greater than 0.';
        if (!$errors) {
            $db->prepare('INSERT INTO Ticket_Tiers (Event_ID, Tier_Name, Price, Quantity_Available) VALUES (?,?,?,?)')
               ->execute([$event_id, $tier_name, $price, $qty]);
            $_SESSION['flash_success'] = 'Tier added.';
            redirect('/staff/event-tiers.php?event_id=' . $event_id);
        }
    } elseif ($action === 'delete') {
        $tier_id = (int)($_POST['tier_id'] ?? 0);
        $sold_check = $db->prepare(
            "SELECT COUNT(*) FROM Bookings WHERE Tier_ID = ? AND Booking_Status IN ('pending','confirmed','used')"
        );
        $sold_check->execute([$tier_id]);
        if ((int)$sold_check->fetchColumn() > 0) {
            $_SESSION['flash_error'] = 'This tier already has bookings and cannot be deleted. Set its quantity to 0 instead, or cancel the event.';
        } else {
            $db->prepare('DELETE FROM Ticket_Tiers WHERE Tier_ID = ? AND Event_ID = ?')->execute([$tier_id, $event_id]);
            $_SESSION['flash_success'] = 'Tier removed.';
        }
        redirect('/staff/event-tiers.php?event_id=' . $event_id);
    } elseif ($action === 'update_quantity') {
        $tier_id = (int)($_POST['tier_id'] ?? 0);
        $qty = (int)($_POST['quantity_available'] ?? 0);
        $sold_stmt = $db->prepare('SELECT Quantity_Sold FROM Ticket_Tiers WHERE Tier_ID = ?');
        $sold_stmt->execute([$tier_id]);
        $sold = (int)$sold_stmt->fetchColumn();
        if ($qty < $sold) {
            $_SESSION['flash_error'] = "Quantity can't be lower than the $sold already sold.";
        } else {
            $db->prepare('UPDATE Ticket_Tiers SET Quantity_Available = ? WHERE Tier_ID = ? AND Event_ID = ?')
               ->execute([$qty, $tier_id, $event_id]);
            $_SESSION['flash_success'] = 'Quantity updated.';
        }
        redirect('/staff/event-tiers.php?event_id=' . $event_id);
    }
}

$tiers_stmt = $db->prepare('SELECT * FROM Ticket_Tiers WHERE Event_ID = ? ORDER BY Price DESC');
$tiers_stmt->execute([$event_id]);
$tiers = $tiers_stmt->fetchAll();

$page_title = 'Ticket Tiers — ' . e($event['Event_Name']);
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap" style="max-width:760px;">
    <span class="hero-eyebrow">Managing tiers for</span>
    <h2><?= e($event['Event_Name']) ?></h2>
    <p>📍 <?= e($event['Venue_Name']) ?> · 🗓 <?= format_date_range($event['Start_Date_Time'], $event['End_Date_Time']) ?></p>

    <?php if ($errors): ?>
      <div class="flash flash-error"><?php foreach ($errors as $err) echo e($err) . '<br>'; ?></div>
    <?php endif; ?>

    <div class="panel">
      <h3 style="font-size:1.1rem;">Current tiers</h3>
      <?php if (!$tiers): ?>
        <p>No tiers yet — add your first one below. Name a tier containing the word "Reserved" (e.g. "VIP (Reserved Seating)") to enable seat-map selection for it.</p>
      <?php else: ?>
        <table class="data-table">
          <thead><tr><th>Tier</th><th>Price</th><th>Available</th><th>Sold</th><th>Remaining</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($tiers as $t): $remaining = tier_remaining($db, $t['Tier_ID']); ?>
              <tr>
                <td><?= e($t['Tier_Name']) ?><?= stripos($t['Tier_Name'], 'reserved') !== false ? ' <span class="tier-remaining">(seat map)</span>' : '' ?></td>
                <td><?= format_money((float)$t['Price']) ?></td>
                <td>
                  <form method="post" style="display:flex; gap:6px; align-items:center;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_quantity">
                    <input type="hidden" name="tier_id" value="<?= $t['Tier_ID'] ?>">
                    <input type="number" name="quantity_available" value="<?= (int)$t['Quantity_Available'] ?>" min="0" style="width:80px; padding:6px 8px;">
                    <button type="submit" class="btn btn-ghost btn-sm">Save</button>
                  </form>
                </td>
                <td><?= (int)$t['Quantity_Sold'] ?></td>
                <td><?= $remaining ?></td>
                <td>
                  <form method="post" onsubmit="return confirm('Remove this tier?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="tier_id" value="<?= $t['Tier_ID'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h3 style="font-size:1.1rem;">Add a new tier</h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
          <div class="field"><label for="tier_name">Tier name</label><input type="text" id="tier_name" name="tier_name" placeholder="e.g. VIP (Reserved Seating), General Admission" required></div>
          <div class="field"><label for="price">Price (₱)</label><input type="number" id="price" name="price" min="0" step="0.01" required></div>
        </div>
        <div class="field"><label for="quantity_available">Quantity available</label><input type="number" id="quantity_available" name="quantity_available" min="1" required></div>
        <button type="submit" class="btn btn-primary">Add tier</button>
      </form>
    </div>

    <a class="btn btn-ghost" href="<?= base_url('/staff/events.php') ?>">← Back to events</a>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
