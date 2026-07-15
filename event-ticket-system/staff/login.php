<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) redirect(is_staff() ? '/staff/dashboard.php' : '/index.php');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $db = get_db();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $lockout = $username !== '' ? login_lockout_message($username, 'staff') : null;

    if ($lockout) {
        $error = $lockout;
    } else {
        $stmt = $db->prepare(
            'SELECT s.*, r.Role_Name FROM Staff s JOIN Roles r ON r.Role_ID = s.Role_ID WHERE s.Staff_User = ? OR s.Email = ?'
        );
        $stmt->execute([$username, $username]);
        $staff = $stmt->fetch();

        if ($staff && $staff['Account_Status'] === 'active' && password_verify($password, $staff['Password_Hash'])) {
            record_login_attempt($username, 'staff', true);
            clear_login_attempts($username, 'staff');
            login_staff($staff, $staff['Role_Name']);
            $_SESSION['flash_success'] = 'Welcome back, ' . $staff['First_Name'] . '.';
            redirect('/staff/dashboard.php');
        } elseif ($staff && $staff['Account_Status'] !== 'active') {
            $error = 'This staff account has been suspended.';
        } else {
            record_login_attempt($username, 'staff', false);
            $error = 'Incorrect username/email or password.';
        }
    }
}

$page_title = 'Staff Login — TicketStub';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="wrap">
    <div class="panel panel-narrow">
      <span class="hero-eyebrow" style="color:var(--violet); border-color:rgba(124,92,252,0.3); background:rgba(124,92,252,0.07);">Internal access</span>
      <h2>Staff &amp; Organizer login</h2>
      <p>For Admins, Organizers, and door Staff. Your role determines what you can access after logging in.</p>

      <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="field"><label for="username">Staff username or email</label><input type="text" id="username" name="username" required autofocus></div>
        <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required></div>
        <div style="text-align:right; margin-bottom:16px;"><a href="<?= base_url('/staff/forgot-password.php') ?>" style="font-size:0.82rem; color:var(--text-muted);">Forgot password?</a></div>
        <button type="submit" class="btn btn-secondary btn-block">Log in</button>
      </form>
      <p style="margin-top:20px; font-size:0.78rem;">Demo accounts (pass: <b style="color:var(--text)">Password123!</b>):</p>
      <p style="font-size:0.78rem;">Admin: <b style="color:var(--text)">admin</b> · Organizer: <b style="color:var(--text)">organizer1</b> · Staff: <b style="color:var(--text)">staff1</b></p>
      <p style="font-size:0.78rem;"><a href="<?= base_url('/staff/forgot-password.php') ?>" style="color:var(--text-muted);">Forgot your password?</a></p>
      <p style="font-size:0.78rem;">Booking tickets instead? <a href="<?= base_url('/login.php') ?>" style="color:var(--amber); font-weight:600;">Customer login</a></p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
