<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('/index.php');

$error = null;
$next = $_GET['next'] ?? $_POST['next'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $db = get_db();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $lockout = $username !== '' ? login_lockout_message($username, 'customer') : null;

    if ($lockout) {
        $error = $lockout;
    } else {
        $stmt = $db->prepare('SELECT * FROM Customers WHERE Customer_User = ? OR Email_Address = ?');
        $stmt->execute([$username, $username]);
        $customer = $stmt->fetch();

        if ($customer && $customer['Account_Status'] === 'active' && password_verify($password, $customer['Password_Hash'])) {
            record_login_attempt($username, 'customer', true);
            clear_login_attempts($username, 'customer');
            login_customer($customer);
            $_SESSION['flash_success'] = 'Welcome back, ' . $customer['First_Name'] . '!';
            redirect($next && str_starts_with($next, '/') ? $next : '/index.php');
        } elseif ($customer && $customer['Account_Status'] !== 'active') {
            $error = 'This account has been suspended. Contact support for help.';
        } else {
            record_login_attempt($username, 'customer', false);
            $error = 'Incorrect username/email or password.';
        }
    }
}

$page_title = 'Log in';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="wrap">
    <div class="panel panel-narrow">
      <span class="hero-eyebrow">Customer login</span>
      <h2>Welcome back</h2>
      <p>Log in to book tickets and view your QR passes.</p>

      <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field"><label for="username">Username or email</label><input type="text" id="username" name="username" required autofocus></div>
        <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required></div>
        <div style="text-align:right; margin-bottom:16px;"><a href="<?= base_url('/forgot-password.php') ?>" style="font-size:0.82rem; color:var(--text-muted);">Forgot password?</a></div>
        <button type="submit" class="btn btn-primary btn-block">Log in</button>
        <div class="form-foot">
          <span>New here?</span>
          <a href="<?= base_url('/register.php') ?>" style="color:var(--amber); font-weight:600;">Create an account</a>
        </div>
      </form>
      <p style="margin-top:14px; font-size:0.82rem;"><a href="<?= base_url('/forgot-password.php') ?>" style="color:var(--text-muted);">Forgot your password?</a></p>
      <p style="margin-top:20px; font-size:0.78rem;">Demo account — user: <b style="color:var(--text)">juandelacruz</b> · pass: <b style="color:var(--text)">Password123!</b></p>
      <p style="font-size:0.78rem;">Staff / organizer / admin? <a href="<?= base_url('/staff/login.php') ?>" style="color:var(--violet); font-weight:600;">Log in here</a></p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
