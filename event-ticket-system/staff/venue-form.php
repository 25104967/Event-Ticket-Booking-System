<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['Admin', 'Organizer']);

$db = get_db();
$venue_id = (int)($_GET['id'] ?? 0);
$is_edit = $venue_id > 0;
$errors = [];
$old = ['venue_name' => '', 'address' => '', 'max_capacity' => ''];

if ($is_edit) {
    $stmt = $db->prepare('SELECT * FROM Venues WHERE Venue_ID = ?');
    $stmt->execute([$venue_id]);
    $venue = $stmt->fetch();
    if (!$venue) { $_SESSION['flash_error'] = 'Venue not found.'; redirect('/staff/venues.php'); }
    $old = ['venue_name' => $venue['Venue_Name'], 'address' => $venue['Address'], 'max_capacity' => $venue['Max_Capacity']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $old['venue_name'] = trim($_POST['venue_name'] ?? '');
    $old['address'] = trim($_POST['address'] ?? '');
    $old['max_capacity'] = (int)($_POST['max_capacity'] ?? 0);

    if ($old['venue_name'] === '') $errors[] = 'Please enter a venue name.';
    if ($old['address'] === '') $errors[] = 'Please enter an address.';
    if ($old['max_capacity'] <= 0) $errors[] = 'Capacity must be greater than 0.';

    if (!$errors && $is_edit) {
        $stmt = $db->prepare('UPDATE Venues SET Venue_Name = ?, Address = ?, Max_Capacity = ? WHERE Venue_ID = ?');
        $stmt->execute([$old['venue_name'], $old['address'], $old['max_capacity'], $venue_id]);
        $_SESSION['flash_success'] = 'Venue updated.';
        redirect('/staff/venues.php');
    } elseif (!$errors) {
        $stmt = $db->prepare('INSERT INTO Venues (Venue_Name, Address, Max_Capacity) VALUES (?,?,?)');
        $stmt->execute([$old['venue_name'], $old['address'], $old['max_capacity']]);
        $new_id = (int)$db->lastInsertId();
        $_SESSION['flash_success'] = 'Venue created. Now design its seat map below.';
        redirect('/staff/venue-seatmap.php?venue_id=' . $new_id);
    }
}

$page_title = ($is_edit ? 'Edit Venue' : 'New Venue') . ' — TicketStub';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap" style="max-width:560px;">
    <span class="hero-eyebrow">Venue management</span>
    <h2><?= $is_edit ? 'Edit venue' : 'Create a new venue' ?></h2>

    <?php if ($errors): ?>
      <div class="flash flash-error"><?php foreach ($errors as $err) echo e($err) . '<br>'; ?></div>
    <?php endif; ?>

    <form method="post" class="panel">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="field"><label for="venue_name">Venue name</label><input type="text" id="venue_name" name="venue_name" value="<?= e($old['venue_name']) ?>" required></div>
      <div class="field"><label for="address">Address</label><input type="text" id="address" name="address" value="<?= e($old['address']) ?>" required></div>
      <div class="field"><label for="max_capacity">Max capacity</label><input type="number" id="max_capacity" name="max_capacity" min="1" value="<?= e((string)$old['max_capacity']) ?>" required></div>
      <div style="display:flex; gap:12px;">
        <a class="btn btn-ghost" href="<?= base_url('/staff/venues.php') ?>">Cancel</a>
        <button type="submit" class="btn btn-primary btn-block"><?= $is_edit ? 'Save changes' : 'Create venue & design seat map' ?></button>
      </div>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
