<?php
/**
 * SMTP configuration for sending real password-reset emails.
 *
 * Leave SMTP_HOST empty to run in "dev mode": instead of emailing the
 * reset link, the forgot-password pages will display the link directly
 * on screen so you can test the flow without a mail server.
 *
 * To send real emails, fill these in (e.g. a Gmail App Password, or your
 * school/host's SMTP details) and wire them up in includes/functions.php
 * inside send_reset_email() using PHPMailer or PHP's mail().
 */
define('SMTP_HOST', '');       // e.g. 'smtp.gmail.com'
define('SMTP_PORT', 587);
define('SMTP_USER', '');       // e.g. 'yourapp@gmail.com'
define('SMTP_PASS', '');       // an app password, never your real password
define('SMTP_FROM', 'no-reply@ticketstub.test');
define('SMTP_FROM_NAME', 'TicketStub');
