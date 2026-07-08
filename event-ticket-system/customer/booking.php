<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_customer();

$db = get_db();
$event_id = (int)($_GET['event_id'] ?? 0);
$tier_id  = (int)($_GET['tier_id'] ?? 0);
$MAX_PER_CHECKOUT = 6; 

$stmt = $db->prepare(
    "SELECT e.*, v.Venue_Name, v.Venue_ID FROM Events e JOIN Venues v ON v.Venue_ID = e.Venue_ID
     WHERE e.Event_ID = ? AND e.Event_Status = 'published'"
);
$stmt->execute([$event_id]);
$event = $stmt->fetch();
if (!$event) { $_SESSION['flash_error'] = 'That event is not available for booking.'; redirect('/index.php'); }

$tiers_stmt = $db->prepare('SELECT * FROM Ticket_Tiers WHERE Event_ID = ? ORDER BY Price DESC');
$tiers_stmt->execute([$event_id]);
$tiers = $tiers_stmt->fetchAll();

$selected_tier = null;
$requires_seat = false;
$taken_seat_ids = [];
$seats_by_row = [];
$remaining = 0;
$max_selectable = 1;

if ($tier_id) {
    foreach ($tiers as $t) { if ((int)$t['Tier_ID'] === $tier_id) { $selected_tier = $t; break; } }
    if ($selected_tier) {
        $remaining = tier_remaining($db, $tier_id);
        $requires_seat = (stripos($selected_tier['Tier_Name'], 'reserved') !== false);
        $max_selectable = max(0, min($MAX_PER_CHECKOUT, $remaining));

        if ($requires_seat) {
            $taken_stmt = $db->prepare(
                "SELECT b.Seat_ID FROM Bookings b
                 JOIN Ticket_Tiers tt ON tt.Tier_ID = b.Tier_ID
                 WHERE tt.Event_ID = ? AND b.Seat_ID IS NOT NULL AND b.Booking_Status IN ('pending','confirmed','used')"
            );
            $taken_stmt->execute([$event_id]);
            $taken_seat_ids = array_column($taken_stmt->fetchAll(), 'Seat_ID');

            $seats_stmt = $db->prepare('SELECT * FROM Seats WHERE Venue_ID = ? AND Is_Available = 1 ORDER BY Seat_Row, Seat_Number');
            $seats_stmt->execute([$event['Venue_ID']]);
            foreach ($seats_stmt->fetchAll() as $seat) {
                $seats_by_row[$seat['Seat_Row']][] = $seat;
            }
        }
    }
}

$page_title = 'Book — ' . e($event['Event_Name']);
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap" style="max-width: 760px;">
    <span class="hero-eyebrow">Booking <?= e($event['Event_Name']) ?></span>
    <h2><?= $selected_tier ? ($requires_seat ? 'Pick your seats' : 'Choose your quantity') : 'Choose your ticket tier' ?></h2>

    <?php if (!$selected_tier): ?>
      <p>Step 1 of 2 — select a tier below.</p>
      <div class="panel">
        <?php foreach ($tiers as $t): $tier_left = tier_remaining($db, $t['Tier_ID']); ?>
          <a class="tier-option" style="text-decoration:none; <?= $tier_left <= 0 ? 'opacity:0.5; pointer-events:none;' : '' ?>"
             href="<?= base_url('/customer/booking.php?event_id=' . $event_id . '&tier_id=' . $t['Tier_ID']) ?>">
            <div>
              <div class="tier-name"><?= e($t['Tier_Name']) ?></div>
              <div class="tier-remaining"><?= $tier_left > 0 ? $tier_left . ' left' : 'Sold out' ?></div>
            </div>
            <div class="tier-price"><?= format_money((float)$t['Price']) ?></div>
          </a>
        <?php endforeach; ?>
      </div>

    <?php elseif ($max_selectable <= 0): ?>
      <div class="empty-state">
        <h2>Sold out</h2>
        <p>This tier just sold out. Please pick another tier.</p>
        <a class="btn btn-primary" href="<?= base_url('/customer/booking.php?event_id=' . $event_id) ?>">← Change tier</a>
      </div>

    <?php else: ?>
      <p>Step 2 of 2 — <?= $requires_seat ? 'tap up to ' . $max_selectable . ' open seats, then confirm.' : 'choose how many tickets, then confirm.' ?></p>

      <form method="post" action="<?= base_url('/customer/process-booking.php') ?>" id="bookingForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="event_id" value="<?= $event_id ?>">
        <input type="hidden" name="tier_id" value="<?= $tier_id ?>">
        <div id="seatInputsContainer"></div>

        <div class="panel">
          <div class="tier-option selected" style="cursor:default;">
            <div>
              <div class="tier-name"><?= e($selected_tier['Tier_Name']) ?></div>
              <div class="tier-remaining"><?= $remaining ?> left · max <?= $max_selectable ?> per order</div>
            </div>
            <div class="tier-price"><?= format_money((float)$selected_tier['Price']) ?> / ticket</div>
          </div>

          <?php if ($requires_seat): ?>
            <div class="screen-label">Stage</div>
            <div class="screen-bar"></div>
            <div class="seatmap-wrap">
              <div class="seat-grid">
                <?php foreach ($seats_by_row as $row => $seats): ?>
                  <div class="seat-row">
                    <span class="seat-row-label"><?= e($row) ?></span>
                    <?php foreach ($seats as $seat):
                        $taken = in_array($seat['Seat_ID'], $taken_seat_ids);
                    ?>
                      <button type="button" class="seat <?= $taken ? 'seat-taken' : '' ?>"
                              data-seat-id="<?= $seat['Seat_ID'] ?>" <?= $taken ? 'disabled' : '' ?>
                              title="<?= e($row . $seat['Seat_Number']) ?>">
                        <?= (int)$seat['Seat_Number'] ?>
                      </button>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="seat-legend">
              <span><span class="legend-swatch" style="background:var(--surface-raised);"></span> Available</span>
              <span><span class="legend-swatch" style="background:var(--amber); border-color:var(--amber);"></span> Selected</span>
              <span><span class="legend-swatch" style="background:#262230; opacity:.6;"></span> Taken</span>
            </div>
            <p id="seatHint" style="text-align:center; margin-top:14px;">No seats selected yet. You can pick up to <?= $max_selectable ?>.</p>
          <?php else: ?>
            <div class="field" style="max-width:220px; margin-top:18px;">
              <label for="quantity">Number of tickets</label>
              <select id="quantity" name="quantity">
                <?php for ($i = 1; $i <= $max_selectable; $i++): ?>
                  <option value="<?= $i ?>"><?= $i ?> ticket<?= $i > 1 ? 's' : '' ?></option>
                <?php endfor; ?>
              </select>
            </div>
          <?php endif; ?>
        </div>

        <div class="panel" id="totalPanel" style="display:flex; justify-content:space-between; align-items:center;">
          <span>Total</span>
          <span class="tier-price" id="totalPrice" style="font-size:1.3rem;"><?= format_money((float)$selected_tier['Price']) ?></span>
        </div>

        <div style="display:flex; gap:12px; margin-top:20px;">
          <a class="btn btn-ghost" href="<?= base_url('/customer/booking.php?event_id=' . $event_id) ?>">← Change tier</a>
          <button type="submit" class="btn btn-primary btn-block" id="confirmBtn" <?= $requires_seat ? 'disabled' : '' ?>>
            Confirm booking
          </button>
        </div>
      </form>

      <script>
        (function () {
          const price = <?= (float)$selected_tier['Price'] ?>;
          const totalPriceEl = document.getElementById('totalPrice');
          const confirmBtn = document.getElementById('confirmBtn');
          const seatInputsContainer = document.getElementById('seatInputsContainer');

          function formatMoney(n) {
            return '₱' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          }

          <?php if ($requires_seat): ?>
            const maxSelectable = <?= $max_selectable ?>;
            const seats = document.querySelectorAll('.seat:not(.seat-taken)');
            const hint = document.getElementById('seatHint');
            let selected = [];

            function refresh() {
              seatInputsContainer.innerHTML = '';
              selected.forEach((id) => {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'seat_ids[]'; input.value = id;
                seatInputsContainer.appendChild(input);
              });
              totalPriceEl.textContent = formatMoney(price * selected.length);
              confirmBtn.disabled = selected.length === 0;
              hint.textContent = selected.length === 0
                ? 'No seats selected yet. You can pick up to ' + maxSelectable + '.'
                : selected.length + ' seat' + (selected.length > 1 ? 's' : '') + ' selected (max ' + maxSelectable + ').';
            }

            seats.forEach((seat) => {
              seat.addEventListener('click', () => {
                const id = seat.dataset.seatId;
                if (seat.classList.contains('seat-selected')) {
                  seat.classList.remove('seat-selected');
                  selected = selected.filter((s) => s !== id);
                } else {
                  if (selected.length >= maxSelectable) return; 
                  seat.classList.add('seat-selected');
                  selected.push(id);
                }
                refresh();
              });
            });
            refresh();
          <?php else: ?>
            const qtySelect = document.getElementById('quantity');
            function refreshQty() {
              totalPriceEl.textContent = formatMoney(price * parseInt(qtySelect.value, 10));
            }
            qtySelect.addEventListener('change', refreshQty);
            refreshQty();
          <?php endif; ?>
        })();
      </script>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
