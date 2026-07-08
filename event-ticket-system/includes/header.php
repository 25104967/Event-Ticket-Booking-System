<?php
/**
 * Shared header. Expects optional $page_title to be set before include.
 * Requires includes/auth.php and includes/functions.php already loaded.
 */
$page_title = $page_title ?? 'TicketStub — Book your night out';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Work+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('/assets/css/style.css') ?>">
</head>
<body>

<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="<?= base_url('/index.php') ?>">
      <span class="brand-mark" aria-hidden="true"></span>
      <span class="brand-name">Ticket<em>Stub</em></span>
    </a>

    <nav class="main-nav" id="mainNav">
      <a href="<?= base_url('/index.php') ?>">Events</a>
      <?php if (is_customer()): ?>
        <a href="<?= base_url('/customer/my-tickets.php') ?>">My Tickets</a>
        <a href="<?= base_url('/customer/account.php') ?>">Account</a>
      <?php endif; ?>
      <?php if (is_staff()): ?>
        <a href="<?= base_url('/staff/dashboard.php') ?>">Dashboard</a>
        <?php if (in_array(current_role(), ['Admin', 'Organizer'], true)): ?>
          <a href="<?= base_url('/staff/events.php') ?>">Events</a>
        <?php endif; ?>
        <?php if (in_array(current_role(), ['Admin', 'Organizer', 'Staff'], true)): ?>
          <a href="<?= base_url('/staff/scan.php') ?>">Scan</a>
        <?php endif; ?>
        <?php if (current_role() === 'Admin'): ?>
          <a href="<?= base_url('/staff/manage-staff.php') ?>">Staff Accounts</a>
        <?php endif; ?>
      <?php endif; ?>
    </nav>

    <div class="header-actions">
      <?php if (is_logged_in()): ?>
        <span class="user-chip">
          <span class="user-chip-dot" aria-hidden="true"></span>
          <?= e($_SESSION['user_name']) ?>
          <?php if (is_staff()): ?><span class="role-badge"><?= e(current_role()) ?></span><?php endif; ?>
        </span>
        <a class="btn btn-ghost btn-sm" href="<?= base_url('/logout.php') ?>">Log out</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="<?= base_url('/login.php') ?>">Log in</a>
        <a class="btn btn-primary btn-sm" href="<?= base_url('/register.php') ?>">Sign up</a>
      <?php endif; ?>
      <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<?php $flash_success = flash_get('flash_success'); $flash_error = flash_get('flash_error'); ?>
<?php if ($flash_success): ?>
  <div class="wrap"><div class="flash flash-success"><?= e($flash_success) ?></div></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="wrap"><div class="flash flash-error"><?= e($flash_error) ?></div></div>
<?php endif; ?>

<main>
