<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['Admin']);

$db = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role_id = (int)($_POST['role_id'] ?? 0);
        $password = $_POST['password'] ?? '';

        if ($first === '' || $last === '') $errors[] = 'Please enter a first and last name.';
        if (strlen($username) < 3) $errors[] = 'Username must be at least 3 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
        if ($role_id <= 0) $errors[] = 'Please choose a role.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

        if (!$errors) {
            $check = $db->prepare('SELECT Staff_ID FROM Staff WHERE Staff_User = ? OR Email = ?');
            $check->execute([$username, $email]);
            if ($check->fetch()) $errors[] = 'That username or email is already in use.';
        }

        if (!$errors) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $db->prepare('INSERT INTO Staff (Staff_User, Password_Hash, First_Name, Last_Name, Email, Role_ID) VALUES (?,?,?,?,?,?)')
               ->execute([$username, $hash, $first, $last, $email, $role_id]);
            $_SESSION['flash_success'] = 'Staff account created.';
            redirect('/staff/manage-staff.php');
        }
    } elseif ($action === 'update') {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role_id = (int)($_POST['role_id'] ?? 0);

        if ($first === '' || $last === '') $errors[] = 'Please enter a first and last name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
        if ($role_id <= 0) $errors[] = 'Please choose a role.';
        if ($role_id !== 1 && $staff_id === (int)$_SESSION['staff_id']) {
            $errors[] = "You can't demote your own account away from Admin.";
        }

        if (!$errors) {
            $check = $db->prepare('SELECT Staff_ID FROM Staff WHERE Email = ? AND Staff_ID != ?');
            $check->execute([$email, $staff_id]);
            if ($check->fetch()) $errors[] = 'That email is already used by another account.';
        }

        if (!$errors) {
            $db->prepare('UPDATE Staff SET First_Name=?, Last_Name=?, Email=?, Role_ID=? WHERE Staff_ID=?')
               ->execute([$first, $last, $email, $role_id, $staff_id]);
            $_SESSION['flash_success'] = 'Staff account updated.';
            redirect('/staff/manage-staff.php');
        } else {
            $_SESSION['flash_error'] = implode(' ', $errors);
            redirect('/staff/manage-staff.php?edit=' . $staff_id);
        }
    } elseif ($action === 'toggle_status') {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        if ($staff_id === (int)$_SESSION['staff_id']) {
            $_SESSION['flash_error'] = "You can't suspend your own account.";
        } else {
            $stmt = $db->prepare('SELECT Account_Status FROM Staff WHERE Staff_ID = ?');
            $stmt->execute([$staff_id]);
            $current = $stmt->fetchColumn();
            $new_status = $current === 'active' ? 'suspended' : 'active';
            $db->prepare('UPDATE Staff SET Account_Status = ? WHERE Staff_ID = ?')->execute([$new_status, $staff_id]);
            $_SESSION['flash_success'] = 'Account status updated.';
        }
        redirect('/staff/manage-staff.php');
    }
}

$roles = $db->query('SELECT * FROM Roles ORDER BY Role_ID')->fetchAll();
$staff_list = $db->query(
    'SELECT s.*, r.Role_Name FROM Staff s JOIN Roles r ON r.Role_ID = s.Role_ID ORDER BY s.Created_At DESC'
)->fetchAll();

$editing = null;
if (!empty($_GET['edit'])) {
    foreach ($staff_list as $s) { if ((int)$s['Staff_ID'] === (int)$_GET['edit']) { $editing = $s; break; } }
}

$page_title = 'Manage Staff Accounts — TicketStub';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <div><h2>Staff accounts</h2><p>Admin, Organizer, and Staff accounts — and what each role can access.</p></div>
    </div>

    <?php if ($errors): ?>
      <div class="flash flash-error"><?php foreach ($errors as $err) echo e($err) . '<br>'; ?></div>
    <?php endif; ?>

    <div class="panel">
      <table class="data-table">
        <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($staff_list as $s): ?>
            <tr>
              <td><?= e($s['First_Name'] . ' ' . $s['Last_Name']) ?></td>
              <td><?= e($s['Staff_User']) ?></td>
              <td><?= e($s['Email']) ?></td>
              <td><span class="role-badge"><?= e($s['Role_Name']) ?></span></td>
              <td><span class="status-pill <?= $s['Account_Status'] === 'active' ? 'status-confirmed' : 'status-cancelled' ?>"><?= e($s['Account_Status']) ?></span></td>
              <td style="white-space:nowrap;">
                <a class="btn btn-ghost btn-sm" href="<?= base_url('/staff/manage-staff.php?edit=' . $s['Staff_ID']) ?>">Edit</a>
                <form method="post" style="display:inline;" onsubmit="return confirm('<?= $s['Account_Status'] === 'active' ? 'Suspend' : 'Reactivate' ?> this account?');">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="staff_id" value="<?= $s['Staff_ID'] ?>">
                  <button type="submit" class="btn btn-sm <?= $s['Account_Status'] === 'active' ? 'btn-danger' : 'btn-ghost' ?>" <?= (int)$s['Staff_ID'] === (int)$_SESSION['staff_id'] ? 'disabled title="You cannot suspend yourself"' : '' ?>>
                    <?= $s['Account_Status'] === 'active' ? 'Suspend' : 'Reactivate' ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($editing): ?>
      <div class="panel" style="max-width:560px; border-color:var(--amber);">
        <h3 style="font-size:1.1rem;">Edit <?= e($editing['First_Name'] . ' ' . $editing['Last_Name']) ?></h3>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="staff_id" value="<?= $editing['Staff_ID'] ?>">
          <div class="form-row">
            <div class="field"><label for="edit_first_name">First name</label><input type="text" id="edit_first_name" name="first_name" value="<?= e($editing['First_Name']) ?>" required></div>
            <div class="field"><label for="edit_last_name">Last name</label><input type="text" id="edit_last_name" name="last_name" value="<?= e($editing['Last_Name']) ?>" required></div>
          </div>
          <div class="field"><label for="edit_email">Email</label><input type="email" id="edit_email" name="email" value="<?= e($editing['Email']) ?>" required></div>
          <div class="field">
            <label for="edit_role_id">Role</label>
            <select id="edit_role_id" name="role_id" required <?= (int)$editing['Staff_ID'] === (int)$_SESSION['staff_id'] ? 'disabled' : '' ?>>
              <?php foreach ($roles as $r): ?>
                <option value="<?= $r['Role_ID'] ?>" <?= (int)$editing['Role_ID'] === (int)$r['Role_ID'] ? 'selected' : '' ?>><?= e($r['Role_Name']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ((int)$editing['Staff_ID'] === (int)$_SESSION['staff_id']): ?>
              <input type="hidden" name="role_id" value="<?= $editing['Role_ID'] ?>">
              <span class="hint">You can't change your own role.</span>
            <?php endif; ?>
          </div>
          <div style="display:flex; gap:12px;">
            <a class="btn btn-ghost" href="<?= base_url('/staff/manage-staff.php') ?>">Cancel</a>
            <button type="submit" class="btn btn-primary">Save changes</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <div class="panel" style="max-width:560px;">
      <h3 style="font-size:1.1rem;">Add a staff account</h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <div class="field"><label for="first_name">First name</label><input type="text" id="first_name" name="first_name" required></div>
          <div class="field"><label for="last_name">Last name</label><input type="text" id="last_name" name="last_name" required></div>
        </div>
        <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" required></div>
        <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" required></div>
        <div class="field">
          <label for="role_id">Role</label>
          <select id="role_id" name="role_id" required>
            <option value="">— Select a role —</option>
            <?php foreach ($roles as $r): ?>
              <option value="<?= $r['Role_ID'] ?>"><?= e($r['Role_Name']) ?> — <?= e($r['Description']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label for="password">Temporary password</label><input type="password" id="password" name="password" required><span class="hint">At least 8 characters. Share this with them securely.</span></div>
        <button type="submit" class="btn btn-primary">Create account</button>
      </form>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
