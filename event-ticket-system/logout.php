<?php
require_once __DIR__ . '/includes/auth.php';
$was_staff = is_staff();
logout();
session_start();
$_SESSION['flash_success'] = 'You have been logged out.';
header('Location: ' . base_url($was_staff ? '/staff/login.php' : '/login.php'));
exit;
