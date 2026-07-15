<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['Admin', 'Organizer']);

$db = get_db();
$venue_id = (int)($_GET['venue_id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM Venues WHERE Venue_ID = ?');
$stmt->execute([$venue_id]);
$venue = $stmt->fetch();
if (!$venue) { $_SESSION['flash_error'] = 'Venue not found.'; redirect('/staff/venues.php'); }

$seats_stmt = $db->prepare('SELECT * FROM Seats WHERE Venue_ID = ? ORDER BY Pos_Y, Pos_X');
$seats_stmt->execute([$venue_id]);
$existing_seats = $seats_stmt->fetchAll();

// Seats with an active booking anywhere can never be removed or renumbered from the builder.
$locked_stmt = $db->prepare(
    "SELECT DISTINCT s.Seat_ID
     FROM Seats s
     JOIN Bookings b ON b.Seat_ID = s.Seat_ID
     WHERE s.Venue_ID = ? AND b.Booking_Status IN ('pending','confirmed','used')"
);
$locked_stmt->execute([$venue_id]);
$locked_seat_ids = array_column($locked_stmt->fetchAll(), 'Seat_ID');

$page_title = 'Seat Map — ' . e($venue['Venue_Name']);
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap" style="max-width:920px;">
    <span class="hero-eyebrow">Seat map builder</span>
    <h2><?= e($venue['Venue_Name']) ?></h2>
    <p><?= e($venue['Address']) ?> · Capacity <?= (int)$venue['Max_Capacity'] ?></p>

    <?php if ($locked_seat_ids): ?>
      <div class="flash flash-error" style="background:rgba(255,178,56,0.1); color:var(--amber); border-color:rgba(255,178,56,0.25);">
        <?= count($locked_seat_ids) ?> seat(s) in this venue already have active bookings and are locked (shown with a padlock) —
        they can't be removed, moved, or renumbered here.
      </div>
    <?php endif; ?>

    <div class="panel">
      <h3 style="font-size:1.05rem;">1. Grid size</h3>
      <div class="form-row">
        <div class="field"><label for="rowsInput">Rows</label><input type="number" id="rowsInput" min="1" max="26" value="4"></div>
        <div class="field"><label for="colsInput">Seats per row (max width)</label><input type="number" id="colsInput" min="1" max="40" value="10"></div>
      </div>
      <button type="button" id="resizeBtn" class="btn btn-ghost">Apply size</button>

      <h3 style="font-size:1.05rem; margin-top:24px;">2. Edit the layout</h3>
      <p style="margin-bottom:10px;">
        <b>Toggle mode:</b> click a cell to turn it into a seat or an empty gap (aisle).
        <b>Paint mode:</b> click seats to label them with the section name below (e.g. "VIP", "Balcony").
      </p>
      <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px; align-items:center;">
        <button type="button" class="btn btn-secondary btn-sm" id="modeToggleBtn" data-mode="toggle">Mode: Toggle seats</button>
        <input type="text" id="sectionInput" placeholder="Section name (for Paint mode)" style="max-width:220px; padding:9px 12px;">
      </div>

      <div class="screen-label">Stage</div>
      <div class="screen-bar"></div>
      <div class="seatmap-wrap">
        <div id="builderGrid" class="seat-grid"></div>
      </div>
      <div class="seat-legend">
        <span><span class="legend-swatch" style="background:var(--surface-raised);"></span> Seat</span>
        <span><span class="legend-swatch" style="background:transparent; border-style:dashed;"></span> Gap / aisle</span>
        <span><span class="legend-swatch" style="background:#262230; opacity:.7;"></span> Locked (booked)</span>
      </div>

      <div id="saveMsg" class="flash" style="display:none; margin-top:16px;"></div>
      <div style="display:flex; gap:12px; margin-top:20px;">
        <a class="btn btn-ghost" href="<?= base_url('/staff/venues.php') ?>">← Back to venues</a>
        <button type="button" class="btn btn-primary btn-block" id="saveBtn">Save seat map</button>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  const existingSeats = <?= json_encode($existing_seats) ?>;
  const lockedIds = new Set(<?= json_encode($locked_seat_ids) ?>);
  const rowsInput = document.getElementById('rowsInput');
  const colsInput = document.getElementById('colsInput');
  const grid = document.getElementById('builderGrid');
  const modeBtn = document.getElementById('modeToggleBtn');
  const sectionInput = document.getElementById('sectionInput');
  const saveBtn = document.getElementById('saveBtn');
  const saveMsg = document.getElementById('saveMsg');

  function rowLabel(index) {
    // 0->A, 1->B ... 25->Z, 26->AA, matches spreadsheet-style column naming
    let n = index, s = '';
    do { s = String.fromCharCode(65 + (n % 26)) + s; n = Math.floor(n / 26) - 1; } while (n >= 0);
    return s;
  }

  // cells[y][x] = { active, section, locked, seatId }
  let cells = [];
  let rows = 0, cols = 0;

  function blankCell() { return { active: false, section: null, locked: false, seatId: null }; }

  function initFromExisting() {
    if (!existingSeats.length) {
      rows = parseInt(rowsInput.value, 10) || 4;
      cols = parseInt(colsInput.value, 10) || 10;
      cells = Array.from({ length: rows }, () => Array.from({ length: cols }, blankCell));
      // default: fill everything as seats to start from a full grid
      cells.forEach((r) => r.forEach((c) => { c.active = true; }));
      return;
    }
    const maxY = Math.max(...existingSeats.map((s) => s.Pos_Y));
    const maxX = Math.max(...existingSeats.map((s) => s.Pos_X));
    rows = maxY + 1; cols = maxX + 1;
    cells = Array.from({ length: rows }, () => Array.from({ length: cols }, blankCell));
    existingSeats.forEach((s) => {
      cells[s.Pos_Y][s.Pos_X] = {
        active: true,
        section: s.Section_Label || null,
        locked: lockedIds.has(s.Seat_ID),
        seatId: s.Seat_ID,
      };
    });
    rowsInput.value = rows;
    colsInput.value = cols;
  }

  function minRowsColsNeeded() {
    let minRow = 0, minCol = 0;
    cells.forEach((r, y) => r.forEach((c, x) => {
      if (c.locked) { minRow = Math.max(minRow, y + 1); minCol = Math.max(minCol, x + 1); }
    }));
    return { minRow, minCol };
  }

  function resize() {
    const newRows = parseInt(rowsInput.value, 10) || 1;
    const newCols = parseInt(colsInput.value, 10) || 1;
    const { minRow, minCol } = minRowsColsNeeded();
    if (newRows < minRow || newCols < minCol) {
      alert('This venue has locked (booked) seats that need at least ' + minRow + ' row(s) and ' + minCol + ' column(s). Choose a larger size.');
      rowsInput.value = Math.max(newRows, minRow);
      colsInput.value = Math.max(newCols, minCol);
      return;
    }
    const next = Array.from({ length: newRows }, (_, y) => Array.from({ length: newCols }, (_, x) => {
      return (cells[y] && cells[y][x]) ? cells[y][x] : blankCell();
    }));
    cells = next; rows = newRows; cols = newCols;
    render();
  }

  function recomputeSeatNumbers() {
    cells.forEach((r) => {
      let n = 0;
      r.forEach((c) => { if (c.active) { n += 1; c.seatNumber = n; } });
    });
  }

  function render() {
    recomputeSeatNumbers();
    grid.innerHTML = '';
    cells.forEach((r, y) => {
      const rowEl = document.createElement('div');
      rowEl.className = 'seat-row';
      const label = document.createElement('span');
      label.className = 'seat-row-label';
      label.textContent = rowLabel(y);
      rowEl.appendChild(label);
      r.forEach((c, x) => {
        if (!c.active) {
          const gap = document.createElement('span');
          gap.className = 'seat-gap';
          gap.style.border = '1px dashed var(--border-strong)';
          gap.style.borderRadius = '7px';
          gap.style.cursor = 'pointer';
          gap.title = 'Empty — click to add a seat here';
          gap.addEventListener('click', () => onCellClick(y, x));
          rowEl.appendChild(gap);
        } else {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'seat';
          btn.textContent = c.locked ? '🔒' : c.seatNumber;
          btn.title = rowLabel(y) + c.seatNumber + (c.section ? ' · ' + c.section : '') + (c.locked ? ' (locked, booked)' : '');
          if (c.locked) { btn.style.background = '#262230'; btn.style.opacity = '0.7'; btn.style.cursor = 'not-allowed'; }
          else if (c.section) { btn.style.borderColor = 'var(--violet)'; btn.style.color = 'var(--text)'; }
          btn.addEventListener('click', () => onCellClick(y, x));
          rowEl.appendChild(btn);
        }
      });
      grid.appendChild(rowEl);
    });
  }

  function onCellClick(y, x) {
    const cell = cells[y][x];
    if (cell.locked) return; // never editable
    const mode = modeBtn.dataset.mode;
    if (mode === 'toggle') {
      cell.active = !cell.active;
      if (!cell.active) { cell.section = null; cell.seatId = null; }
    } else if (mode === 'paint') {
      if (cell.active) cell.section = sectionInput.value.trim() || null;
    }
    render();
  }

  modeBtn.addEventListener('click', () => {
    const isToggle = modeBtn.dataset.mode === 'toggle';
    modeBtn.dataset.mode = isToggle ? 'paint' : 'toggle';
    modeBtn.textContent = isToggle ? 'Mode: Paint section' : 'Mode: Toggle seats';
  });

  document.getElementById('resizeBtn').addEventListener('click', resize);

  saveBtn.addEventListener('click', async () => {
    recomputeSeatNumbers();
    const seats = [];
    cells.forEach((r, y) => r.forEach((c, x) => {
      if (c.active) {
        seats.push({
          seat_id: c.seatId, pos_x: x, pos_y: y,
          row_label: rowLabel(y), seat_number: c.seatNumber, section: c.section,
        });
      }
    }));
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving…';
    try {
      const res = await fetch(<?= json_encode(base_url('/staff/api-save-seatmap.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: <?= json_encode(csrf_token()) ?>, venue_id: <?= $venue_id ?>, seats }),
      });
      const data = await res.json();
      saveMsg.style.display = 'block';
      if (!res.ok) {
        saveMsg.className = 'flash flash-error';
        saveMsg.textContent = data.error || 'Could not save the seat map.';
      } else {
        saveMsg.className = 'flash flash-success';
        saveMsg.textContent = 'Seat map saved — ' + data.seat_count + ' seats.' + (data.warning ? ' ' + data.warning : '');
        // refresh seat IDs so a second save in the same session works cleanly
        data.seats.forEach((s) => { cells[s.Pos_Y][s.Pos_X].seatId = s.Seat_ID; });
      }
    } catch (e) {
      saveMsg.style.display = 'block';
      saveMsg.className = 'flash flash-error';
      saveMsg.textContent = 'Network error — please try again.';
    }
    saveBtn.disabled = false;
    saveBtn.textContent = 'Save seat map';
  });

  initFromExisting();
  render();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
