<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$db = get_db();
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$reset = $token ? verify_reset_token($db, $token) : null;

$errors = [];
$done = false;

if (!$token || !$reset) {
    $page_title = 'Reset Password';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="section">
      <div class="wrap">
        <div class="empty-state">
          <h2>This reset link is invalid or has expired</h2>
          <p>Reset links are only valid for 30 minutes and can only be used once. Please request a new one.</p>
          <a class="btn btn-primary" href="<?= base_url('/forgot-password.php') ?>">Request a new link</a>
        </div>
      </div>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $password2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        if ($reset['Account_Type'] === 'customer') {
            $db->prepare('UPDATE Customers SET Password_Hash = ? WHERE Customer_ID = ?')->execute([$hash, $reset['Account_ID']]);
        } else {
            $db->prepare('UPDATE Staff SET Password_Hash = ? WHERE Staff_ID = ?')->execute([$hash, $reset['Account_ID']]);
        }
        consume_reset_token($db, (int)$reset['Reset_ID']);
        $done = true;
    }
}

$page_title = 'Reset Password — TicketStub';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="wrap">
    <div class="panel panel-narrow">
      <span class="hero-eyebrow">Account recovery</span>
      <h2>Set a new password</h2>

      <?php if ($done): ?>
        <div class="flash flash-success">Your password has been updated. You can log in now.</div>
        <a class="btn btn-primary btn-block" style="margin-top:16px;"
           href="<?= base_url($reset['Account_Type'] === 'customer' ? '/login.php' : '/staff/login.php') ?>">Go to login</a>
      <?php else: ?>
        <?php if ($errors): ?>
          <div class="flash flash-error"><?php foreach ($errors as $err) echo e($err) . '<br>'; ?></div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <div class="field"><label for="password">New password</label><input type="password" id="password" name="password" required autofocus><span class="hint">At least 8 characters.</span></div>
          <div class="field"><label for="password_confirm">Confirm new password</label><input type="password" id="password_confirm" name="password_confirm" required></div>
          <button type="submit" class="btn btn-primary btn-block">Update password</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
