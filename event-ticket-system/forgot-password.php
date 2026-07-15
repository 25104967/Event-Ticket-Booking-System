<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('/index.php');

$submitted = false;
$dev_link = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $db = get_db();
    $email = trim($_POST['email'] ?? '');

    $stmt = $db->prepare('SELECT * FROM Customers WHERE Email_Address = ?');
    $stmt->execute([$email]);
    $customer = $stmt->fetch();

    
    $submitted = true;

    if ($customer && $customer['Account_Status'] === 'active') {
        $token = create_reset_token($db, 'customer', (int)$customer['Customer_ID']);
        $link = base_url('/reset-password.php?token=' . $token);
        $result = send_reset_email($customer['Email_Address'], $link);
        if ($result['dev_mode']) $dev_link = $result['link'];
    }
}

$page_title = 'Forgot Password';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="wrap">
    <div class="panel panel-narrow">
      <span class="hero-eyebrow">Account recovery</span>
      <h2>Forgot your password?</h2>

      <?php if ($submitted): ?>
        <div class="flash flash-success">If that email is registered, a reset link has been sent.</div>
        <?php if ($dev_link): ?>
          <div class="panel" style="background:var(--surface-raised); border-style:dashed;">
            <p style="margin-bottom:8px;"><b style="color:var(--text);">Dev mode:</b> no SMTP server is configured yet, so here's the link that would have been emailed:</p>
            <a href="<?= e($dev_link) ?>" style="word-break:break-all; color:var(--amber);"><?= e($dev_link) ?></a>
          </div>
        <?php endif; ?>
        <a class="btn btn-ghost btn-block" style="margin-top:16px;" href="<?= base_url('/login.php') ?>">Back to login</a>
      <?php else: ?>
        <p>Enter the email on your account and we'll send you a reset link.</p>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <div class="field"><label for="email">Email address</label><input type="email" id="email" name="email" required autofocus></div>
          <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
        </form>
        <div class="form-foot"><a href="<?= base_url('/login.php') ?>" style="color:var(--amber); font-weight:600;">← Back to login</a></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
