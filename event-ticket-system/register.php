<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('/index.php');

$errors = [];
$old = ['first_name' => '', 'last_name' => '', 'username' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $db = get_db();

    $old['first_name'] = trim($_POST['first_name'] ?? '');
    $old['last_name']  = trim($_POST['last_name'] ?? '');
    $old['username']   = trim($_POST['username'] ?? '');
    $old['email']      = trim($_POST['email'] ?? '');
    $old['phone']      = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    if ($old['first_name'] === '' || $old['last_name'] === '') $errors[] = 'Please enter your first and last name.';
    if (strlen($old['username']) < 3) $errors[] = 'Username must be at least 3 characters.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $password2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $stmt = $db->prepare('SELECT Customer_ID FROM Customers WHERE Customer_User = ? OR Email_Address = ?');
        $stmt->execute([$old['username'], $old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'That username or email is already registered.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare(
            'INSERT INTO Customers (Customer_User, Password_Hash, First_Name, Last_Name, Email_Address, Phone_Number)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([$old['username'], $hash, $old['first_name'], $old['last_name'], $old['email'], $old['phone'] ?: null]);

        $stmt = $db->prepare('SELECT * FROM Customers WHERE Customer_ID = ?');
        $stmt->execute([$db->lastInsertId()]);
        login_customer($stmt->fetch());

        $_SESSION['flash_success'] = 'Welcome, ' . $old['first_name'] . '! Your account is ready.';
        redirect('/index.php');
    }
}

$page_title = 'Create your account — TicketStub';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="wrap">
    <div class="panel panel-narrow">
      <span class="hero-eyebrow">Customer account</span>
      <h2>Create your account</h2>
      <p>Register to book tickets, hold seats, and receive your QR entry pass.</p>

      <?php if ($errors): ?>
        <div class="flash flash-error"><?php foreach ($errors as $err) echo e($err) . '<br>'; ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="form-row">
          <div class="field"><label for="first_name">First name</label><input type="text" id="first_name" name="first_name" value="<?= e($old['first_name']) ?>" required></div>
          <div class="field"><label for="last_name">Last name</label><input type="text" id="last_name" name="last_name" value="<?= e($old['last_name']) ?>" required></div>
        </div>
        <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" value="<?= e($old['username']) ?>" required></div>
        <div class="field"><label for="email">Email address</label><input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required></div>
        <div class="field"><label for="phone">Phone number <span class="hint" style="display:inline">(optional)</span></label><input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>"></div>
        <div class="form-row">
          <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required><span class="hint">At least 8 characters.</span></div>
          <div class="field"><label for="password_confirm">Confirm password</label><input type="password" id="password_confirm" name="password_confirm" required></div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Create account</button>
        <div class="form-foot">
          <span>Already have an account?</span>
          <a href="<?= base_url('/login.php') ?>" style="color:var(--amber); font-weight:600;">Log in</a>
        </div>
      </form>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
