<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['Admin', 'Organizer']);

$db = get_db();
$role = current_role();
$event_id = (int)($_GET['id'] ?? 0);
$is_edit = $event_id > 0;

$event = [
    'Event_Name' => '', 'Event_Description' => '', 'Category' => '', 'Venue_ID' => '',
    'Start_Date_Time' => '', 'End_Date_Time' => '', 'Event_Status' => 'published', 'Poster_Image' => null,
];

if ($is_edit) {
    $stmt = $db->prepare('SELECT * FROM Events WHERE Event_ID = ?');
    $stmt->execute([$event_id]);
    $found = $stmt->fetch();
    if (!$found) { $_SESSION['flash_error'] = 'Event not found.'; redirect('/staff/events.php'); }
    if ($role === 'Organizer' && (int)$found['Staff_ID'] !== (int)$_SESSION['staff_id']) {
        http_response_code(403);
        $_SESSION['flash_error'] = 'You can only edit events you organize.';
        redirect('/staff/events.php');
    }
    $event = $found;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);

    $event['Event_Name'] = trim($_POST['event_name'] ?? '');
    $event['Event_Description'] = trim($_POST['event_description'] ?? '');
    $event['Category'] = trim($_POST['category'] ?? '');
    $event['Venue_ID'] = (int)($_POST['venue_id'] ?? 0);
    $event['Start_Date_Time'] = $_POST['start_date_time'] ?? '';
    $event['End_Date_Time'] = $_POST['end_date_time'] ?? '';
    $event['Event_Status'] = $_POST['event_status'] ?? 'published';

    if ($event['Event_Name'] === '') $errors[] = 'Please enter an event name.';
    if ($event['Venue_ID'] <= 0) $errors[] = 'Please choose (or add) a venue.';
    if (!$event['Start_Date_Time'] || !$event['End_Date_Time']) $errors[] = 'Please set both a start and end date/time.';
    if ($event['Start_Date_Time'] && $event['End_Date_Time'] && $event['Start_Date_Time'] >= $event['End_Date_Time']) {
        $errors[] = 'The end time must be after the start time.';
    }

    // Optional poster upload
    $poster_path = $event['Poster_Image'];
    if (!empty($_FILES['poster']['name'])) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = mime_content_type($_FILES['poster']['tmp_name']);
        if ($_FILES['poster']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The poster image failed to upload.';
        } elseif (!isset($allowed[$mime])) {
            $errors[] = 'Poster must be a JPG, PNG, or WEBP image.';
        } elseif ($_FILES['poster']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Poster image must be under 5MB.';
        } else {
            $filename = 'poster_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
            $dest = __DIR__ . '/../uploads/posters/' . $filename;
            if (move_uploaded_file($_FILES['poster']['tmp_name'], $dest)) {
                $poster_path = '/uploads/posters/' . $filename;
            } else {
                $errors[] = 'Could not save the uploaded poster.';
            }
        }
    }

    if (!$errors) {
        if ($is_edit) {
            $stmt = $db->prepare(
                'UPDATE Events SET Event_Name=?, Event_Description=?, Category=?, Venue_ID=?, Start_Date_Time=?, End_Date_Time=?, Event_Status=?, Poster_Image=? WHERE Event_ID=?'
            );
            $stmt->execute([
                $event['Event_Name'], $event['Event_Description'], $event['Category'], $event['Venue_ID'],
                $event['Start_Date_Time'], $event['End_Date_Time'], $event['Event_Status'], $poster_path, $event_id,
            ]);
            $_SESSION['flash_success'] = 'Event updated.';
        } else {
            $stmt = $db->prepare(
                'INSERT INTO Events (Venue_ID, Staff_ID, Event_Name, Event_Description, Category, Poster_Image, Start_Date_Time, End_Date_Time, Event_Status)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $event['Venue_ID'], $_SESSION['staff_id'], $event['Event_Name'], $event['Event_Description'],
                $event['Category'], $poster_path, $event['Start_Date_Time'], $event['End_Date_Time'], $event['Event_Status'],
            ]);
            $event_id = (int)$db->lastInsertId();
            $_SESSION['flash_success'] = 'Event created. Now add your ticket tiers below.';
            redirect('/staff/event-tiers.php?event_id=' . $event_id);
        }
        redirect('/staff/events.php');
    }
}

$venues = $db->query('SELECT * FROM Venues ORDER BY Venue_Name')->fetchAll();

$page_title = ($is_edit ? 'Edit Event' : 'New Event') . ' — TicketStub';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap" style="max-width:720px;">
    <span class="hero-eyebrow">Event management</span>
    <h2><?= $is_edit ? 'Edit event' : 'Create a new event' ?></h2>

    <?php if ($errors): ?>
      <div class="flash flash-error"><?php foreach ($errors as $err) echo e($err) . '<br>'; ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="panel">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <div class="field">
        <label for="event_name">Event name</label>
        <input type="text" id="event_name" name="event_name" value="<?= e($event['Event_Name']) ?>" required>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="category">Category</label>
          <input type="text" id="category" name="category" value="<?= e($event['Category']) ?>" placeholder="Concert, Sports, Comedy…">
        </div>
        <div class="field">
          <label for="event_status">Status</label>
          <select id="event_status" name="event_status">
            <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $val => $label): ?>
              <option value="<?= $val ?>" <?= $event['Event_Status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field">
        <label for="event_description">Description</label>
        <textarea id="event_description" name="event_description"><?= e($event['Event_Description']) ?></textarea>
      </div>

      <div class="field">
        <label for="venue_id">Venue</label>
        <select id="venue_id" name="venue_id" required>
          <option value="">— Select a venue —</option>
          <?php foreach ($venues as $v): ?>
            <option value="<?= $v['Venue_ID'] ?>" <?= (int)$event['Venue_ID'] === (int)$v['Venue_ID'] ? 'selected' : '' ?>>
              <?= e($v['Venue_Name']) ?> (cap. <?= (int)$v['Max_Capacity'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <button type="button" id="toggleVenueForm" class="btn btn-ghost btn-sm" style="margin-top:10px;">+ Add a new venue</button>
        <p class="hint">Planning a reserved-seating tier? <a href="<?= base_url('/staff/venues.php') ?>" target="_blank" style="color:var(--amber);">Design that venue's seat map</a> first.</p>
      </div>

      <div id="venueForm" class="panel" style="display:none; background:var(--surface-raised); margin-bottom:18px;">
        <div class="field"><label for="new_venue_name">Venue name</label><input type="text" id="new_venue_name"></div>
        <div class="field"><label for="new_venue_address">Address</label><input type="text" id="new_venue_address"></div>
        <div class="field"><label for="new_venue_capacity">Max capacity</label><input type="number" id="new_venue_capacity" min="1"></div>
        <div id="venueFormError" class="flash flash-error" style="display:none;"></div>
        <button type="button" id="saveVenueBtn" class="btn btn-secondary btn-sm">Save venue</button>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="start_date_time">Start date &amp; time</label>
          <input type="datetime-local" id="start_date_time" name="start_date_time"
                 value="<?= $event['Start_Date_Time'] ? str_replace(' ', 'T', substr($event['Start_Date_Time'], 0, 16)) : '' ?>" required>
        </div>
        <div class="field">
          <label for="end_date_time">End date &amp; time</label>
          <input type="datetime-local" id="end_date_time" name="end_date_time"
                 value="<?= $event['End_Date_Time'] ? str_replace(' ', 'T', substr($event['End_Date_Time'], 0, 16)) : '' ?>" required>
        </div>
      </div>

      <div class="field">
        <label for="poster">Poster image <span class="hint" style="display:inline">(optional, JPG/PNG/WEBP, max 5MB)</span></label>
        <?php if (!empty($event['Poster_Image'])): ?>
          <div class="ticket-poster" style="aspect-ratio:16/6; margin-bottom:10px;"><img src="<?= base_url(e($event['Poster_Image'])) ?>" alt=""></div>
        <?php endif; ?>
        <input type="file" id="poster" name="poster" accept="image/jpeg,image/png,image/webp">
      </div>

      <div style="display:flex; gap:12px; margin-top:10px;">
        <a class="btn btn-ghost" href="<?= base_url('/staff/events.php') ?>">Cancel</a>
        <button type="submit" class="btn btn-primary btn-block"><?= $is_edit ? 'Save changes' : 'Create event & add tiers' ?></button>
      </div>
    </form>
  </div>
</section>

<script>
(function () {
  const toggleBtn = document.getElementById('toggleVenueForm');
  const venueForm = document.getElementById('venueForm');
  const saveBtn = document.getElementById('saveVenueBtn');
  const errBox = document.getElementById('venueFormError');
  const venueSelect = document.getElementById('venue_id');

  toggleBtn.addEventListener('click', () => {
    venueForm.style.display = venueForm.style.display === 'none' ? 'block' : 'none';
  });

  saveBtn.addEventListener('click', async () => {
    errBox.style.display = 'none';
    const payload = {
      csrf_token: <?= json_encode(csrf_token()) ?>,
      venue_name: document.getElementById('new_venue_name').value.trim(),
      address: document.getElementById('new_venue_address').value.trim(),
      max_capacity: document.getElementById('new_venue_capacity').value,
    };
    try {
      const res = await fetch(<?= json_encode(base_url('/staff/api-add-venue.php')) ?>, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!res.ok) { errBox.textContent = data.error || 'Could not save venue.'; errBox.style.display = 'block'; return; }
      const opt = document.createElement('option');
      opt.value = data.id; opt.textContent = data.name; opt.selected = true;
      venueSelect.appendChild(opt);
      venueForm.style.display = 'none';
    } catch (e) {
      errBox.textContent = 'Network error — please try again.'; errBox.style.display = 'block';
    }
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
