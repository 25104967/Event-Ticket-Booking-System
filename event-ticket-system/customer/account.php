<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_customer();

$db = get_db();
$errors = [];
$pw_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($first === '' || $last === '') $errors[] = 'Please enter your first and last name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

        if (!$errors) {
            $check = $db->prepare('SELECT Customer_ID FROM Customers WHERE Email_Address = ? AND Customer_ID != ?');
            $check->execute([$email, $_SESSION['customer_id']]);
            if ($check->fetch()) $errors[] = 'That email is already used by another account.';
        }

        if (!$errors) {
            $db->prepare('UPDATE Customers SET First_Name=?, Last_Name=?, Email_Address=?, Phone_Number=? WHERE Customer_ID=?')
               ->execute([$first, $last, $email, $phone ?: null, $_SESSION['customer_id']]);
            $_SESSION['user_name'] = "$first $last";
            $_SESSION['user_email'] = $email;
            $_SESSION['flash_success'] = 'Profile updated.';
            redirect('/customer/account.php');
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $db->prepare('SELECT Password_Hash FROM Customers WHERE Customer_ID = ?');
        $stmt->execute([$_SESSION['customer_id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) $pw_errors[] = 'Your current password is incorrect.';
        if (strlen($new) < 8) $pw_errors[] = 'New password must be at least 8 characters.';
        if ($new !== $confirm) $pw_errors[] = 'New passwords do not match.';

        if (!$pw_errors) {
            $db->prepare('UPDATE Customers SET Password_Hash = ? WHERE Customer_ID = ?')
               ->execute([password_hash($new, PASSWORD_BCRYPT), $_SESSION['customer_id']]);
            $_SESSION['flash_success'] = 'Password changed.';
            redirect('/customer/account.php');
        }
    }
}

$stmt = $db->prepare('SELECT * FROM Customers WHERE Customer_ID = ?');
$stmt->execute([$_SESSION['customer_id']]);
$customer = $stmt->fetch();

$counts = $db->prepare("SELECT COUNT(*) FROM Bookings WHERE Customer_ID = ? AND Booking_Status = 'confirmed'");
$counts->execute([$_SESSION['customer_id']]);
$confirmed_count = (int)$counts->fetchColumn();

$page_title = 'My Account - TicketStub';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap">
    <h2>My account</h2>
    

    <div class="stat-grid" style="margin-top:24px;">
      <div class="stat-card"><div class="stat-label">Confirmed tickets</div><div class="stat-value amber"><?= $confirmed_count ?></div></div>
      <div class="stat-card"><div class="stat-label">Member since</div><div class="stat-value" style="font-size:1.4rem;"><?= (new DateTime($customer['Created_At']))->format('M Y') ?></div></div>
    </div>

    <div class="panel" style="max-width:520px;">
      <h3 style="font-size:1.1rem;">Edit profile</h3>
      <?php if ($errors): ?><div class="flash flash-error"><?php foreach ($errors as $err) echo e($err) . '<br>'; ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-row">
          <div class="field"><label for="first_name">First name</label><input type="text" id="first_name" name="first_name" value="<?= e($customer['First_Name']) ?>" required></div>
          <div class="field"><label for="last_name">Last name</label><input type="text" id="last_name" name="last_name" value="<?= e($customer['Last_Name']) ?>" required></div>
        </div>
        <div class="field"><label for="username_display">Username</label><input type="text" id="username_display" value="<?= e($customer['Customer_User']) ?>" disabled><span class="hint">Usernames can't be changed.</span></div>
        <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= e($customer['Email_Address']) ?>" required></div>
        <div class="field"><label for="phone">Phone number</label><input type="tel" id="phone" name="phone" value="<?= e($customer['Phone_Number']) ?>"></div>
        <button type="submit" class="btn btn-primary">Save changes</button>
      </form>
    </div>

    <div class="panel" style="max-width:520px;">
      <h3 style="font-size:1.1rem;">Change password</h3>
      <?php if ($pw_errors): ?><div class="flash flash-error"><?php foreach ($pw_errors as $err) echo e($err) . '<br>'; ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="change_password">
        <div class="field"><label for="current_password">Current password</label><input type="password" id="current_password" name="current_password" required></div>
        <div class="form-row">
          <div class="field"><label for="new_password">New password</label><input type="password" id="new_password" name="new_password" required><span class="hint">At least 8 characters.</span></div>
          <div class="field"><label for="confirm_password">Confirm new password</label><input type="password" id="confirm_password" name="confirm_password" required></div>
        </div>
        <button type="submit" class="btn btn-secondary">Update password</button>
      </form>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

