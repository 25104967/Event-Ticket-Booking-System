<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['Admin', 'Organizer', 'Staff']);

$db = get_db();
$result = null; // ['type' => 'success'|'error'|'warning', 'message' => ..., 'ticket' => [...]]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $code = trim($_POST['qr_data'] ?? '');

    if ($code === '') {
        $result = ['type' => 'error', 'message' => 'No code entered.'];
    } else {
        $stmt = $db->prepare(
            "SELECT q.*, b.Booking_ID, b.Booking_Status, c.First_Name, c.Last_Name, c.Email_Address,
                    tt.Tier_Name, e.Event_Name, e.Start_Date_Time, s.Seat_Row, s.Seat_Number
             FROM QR_Code q
             JOIN Bookings b ON b.Booking_ID = q.Booking_ID
             JOIN Customers c ON c.Customer_ID = b.Customer_ID
             JOIN Ticket_Tiers tt ON tt.Tier_ID = b.Tier_ID
             JOIN Events e ON e.Event_ID = tt.Event_ID
             LEFT JOIN Seats s ON s.Seat_ID = b.Seat_ID
             WHERE q.QR_Data = ?"
        );
        $stmt->execute([$code]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            $result = ['type' => 'error', 'message' => 'No ticket found for this code. It may be invalid or fake.'];
        } elseif ($ticket['Booking_Status'] === 'cancelled') {
            $result = ['type' => 'error', 'message' => 'This booking was cancelled.', 'ticket' => $ticket];
        } elseif ($ticket['Is_Used']) {
            $result = ['type' => 'warning', 'message' => 'This ticket was already used at check-in.', 'ticket' => $ticket];
        } elseif (strtotime($ticket['Expiry_Date']) < time()) {
            $result = ['type' => 'error', 'message' => 'This ticket has expired.', 'ticket' => $ticket];
        } else {
            $db->prepare("UPDATE QR_Code SET Is_Used = 1 WHERE QR_Code_ID = ?")->execute([$ticket['QR_Code_ID']]);
            $db->prepare("UPDATE Bookings SET Booking_Status = 'used' WHERE Booking_ID = ?")->execute([$ticket['Booking_ID']]);
            $result = ['type' => 'success', 'message' => 'Checked in successfully.', 'ticket' => $ticket];
        }
    }
}

$page_title = 'QR Check-In — TicketStub';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap" style="max-width:560px;">
    <span class="hero-eyebrow" style="color:var(--violet); border-color:rgba(124,92,252,0.3); background:rgba(124,92,252,0.07);">Door check-in</span>
    <h2>Scan a ticket</h2>
    <p>Use a USB/handheld QR scanner (acts like a keyboard) pointed at this field, type the code manually, or scan with your device's camera.</p>

    <div class="panel">
      <button type="button" id="toggleCameraBtn" class="btn btn-ghost btn-block" style="margin-bottom:14px;">📷 Scan with camera</button>
      <div id="cameraReader" style="display:none; margin-bottom:16px; border-radius:var(--radius-sm); overflow:hidden;"></div>
      <div id="cameraError" class="flash flash-error" style="display:none;"></div>
    </div>

    <form method="post" class="panel" id="scanForm">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="field">
        <label for="qr_data">Ticket code</label>
        <input type="text" id="qr_data" name="qr_data" autofocus autocomplete="off" placeholder="QR-XXXXXXXX-xxxxxxxx">
      </div>
      <button type="submit" class="btn btn-secondary btn-block">Check in</button>
    </form>

    <?php if ($result): ?>
      <?php
        $bannerClass = ['success' => 'flash-success', 'warning' => 'flash-error', 'error' => 'flash-error'][$result['type']];
      ?>
      <div class="flash <?= $bannerClass ?>" style="font-weight:700;"><?= e($result['message']) ?></div>

      <?php if (!empty($result['ticket'])): $t = $result['ticket']; ?>
        <div class="panel">
          <div class="qr-meta-grid" style="grid-template-columns:1fr 1fr;">
            <div><div class="label">Attendee</div><?= e($t['First_Name'] . ' ' . $t['Last_Name']) ?></div>
            <div><div class="label">Email</div><?= e($t['Email_Address']) ?></div>
            <div><div class="label">Event</div><?= e($t['Event_Name']) ?></div>
            <div><div class="label">Date</div><?= (new DateTime($t['Start_Date_Time']))->format('M j, Y g:i A') ?></div>
            <div><div class="label">Tier</div><?= e($t['Tier_Name']) ?></div>
            <div><div class="label">Seat</div><?= $t['Seat_Row'] ? e($t['Seat_Row'] . $t['Seat_Number']) : 'General admission' ?></div>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
  document.getElementById('qr_data').focus();

  (function () {
    const toggleBtn = document.getElementById('toggleCameraBtn');
    const readerEl = document.getElementById('cameraReader');
    const errorEl = document.getElementById('cameraError');
    const qrInput = document.getElementById('qr_data');
    const form = document.getElementById('scanForm');
    let html5QrCode = null;
    let running = false;

    async function startCamera() {
      errorEl.style.display = 'none';
      readerEl.style.display = 'block';
      html5QrCode = new Html5Qrcode('cameraReader');
      try {
        await html5QrCode.start(
          { facingMode: 'environment' },
          { fps: 10, qrbox: 220 },
          (decodedText) => {
            qrInput.value = decodedText;
            stopCamera();
            form.submit();
          },
          () => { /* ignore per-frame scan misses */ }
        );
        running = true;
        toggleBtn.textContent = '✕ Stop camera';
      } catch (err) {
        errorEl.textContent = 'Could not access the camera. Your device may not support it, or permission was denied. You can still type/scan into the field below.';
        errorEl.style.display = 'block';
        readerEl.style.display = 'none';
      }
    }

    async function stopCamera() {
      if (html5QrCode && running) {
        try { await html5QrCode.stop(); html5QrCode.clear(); } catch (e) {}
      }
      running = false;
      readerEl.style.display = 'none';
      toggleBtn.textContent = '📷 Scan with camera';
    }

    toggleBtn.addEventListener('click', () => { running ? stopCamera() : startCamera(); });
  })();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
