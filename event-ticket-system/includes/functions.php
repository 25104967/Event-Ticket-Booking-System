<?php
/** Shared helper functions */

require_once __DIR__ . '/../config/mail.php';

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function flash_get(string $key): ?string {
    if (!empty($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

function format_money(float $amount): string {
    return '₱' . number_format($amount, 2);
}

function format_date_range(string $start, string $end): string {
    $s = new DateTime($start);
    $en = new DateTime($end);
    $sameDay = $s->format('Y-m-d') === $en->format('Y-m-d');
    if ($sameDay) {
        return $s->format('D, M j, Y') . ' · ' . $s->format('g:i A') . ' – ' . $en->format('g:i A');
    }
    return $s->format('M j, Y, g:i A') . ' – ' . $en->format('M j, Y, g:i A');
}

/** Real-time count of tickets still available for a tier (Quantity_Available - confirmed/pending bookings). */
function tier_remaining(PDO $db, int $tier_id): int {
    $stmt = $db->prepare(
        "SELECT tt.Quantity_Available -
                (SELECT COUNT(*) FROM Bookings b
                 WHERE b.Tier_ID = tt.Tier_ID AND b.Booking_Status IN ('pending','confirmed','used')) AS remaining
         FROM Ticket_Tiers tt WHERE tt.Tier_ID = ?"
    );
    $stmt->execute([$tier_id]);
    $row = $stmt->fetch();
    return $row ? max(0, (int)$row['remaining']) : 0;
}

/** Generates a short, human-friendly, unique booking reference e.g. AFG-8K3P2Q */
function generate_reference(string $prefix = 'TKT'): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no ambiguous chars
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return strtoupper($prefix) . '-' . $code;
}

function redirect(string $path): void {
    header('Location: ' . base_url($path));
    exit;
}

/**
 * Reads & sanitizes the current page number from $_GET['page'].
 */
function current_page(): int {
    return max(1, (int)($_GET['page'] ?? 1));
}

/**
 * Renders simple Prev/Next + page-number pagination controls.
 * $query_params: extra GET params to preserve (e.g. ['q' => 'jazz']), page is added automatically.
 */
function render_pagination(int $current_page, int $total_pages, string $path, array $query_params = []): string {
    if ($total_pages <= 1) return '';

    $build = function (int $page) use ($path, $query_params) {
        $params = array_merge($query_params, ['page' => $page]);
        return base_url($path) . '?' . http_build_query($params);
    };

    $html = '<div style="display:flex; gap:8px; justify-content:center; align-items:center; margin-top:28px; flex-wrap:wrap;">';
    if ($current_page > 1) {
        $html .= '<a class="btn btn-ghost btn-sm" href="' . e($build($current_page - 1)) . '">← Prev</a>';
    }
    for ($p = 1; $p <= $total_pages; $p++) {
        $active = $p === $current_page;
        $html .= '<a class="btn btn-sm ' . ($active ? 'btn-primary' : 'btn-ghost') . '" href="' . e($build($p)) . '" style="min-width:38px; text-align:center;">' . $p . '</a>';
    }
    if ($current_page < $total_pages) {
        $html .= '<a class="btn btn-ghost btn-sm" href="' . e($build($current_page + 1)) . '">Next →</a>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Creates a password reset token for a customer or staff account.
 * Returns the RAW token (put it in the reset link) — only its SHA-256
 * hash is stored in the database, exactly like we never store raw passwords.
 */
function create_reset_token(PDO $db, string $account_type, int $account_id): string {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');
    // Invalidate any older unused tokens for this account first
    $db->prepare('UPDATE Password_Resets SET Used = 1 WHERE Account_Type = ? AND Account_ID = ? AND Used = 0')
       ->execute([$account_type, $account_id]);
    $db->prepare('INSERT INTO Password_Resets (Account_Type, Account_ID, Token_Hash, Expires_At) VALUES (?,?,?,?)')
       ->execute([$account_type, $account_id, $hash, $expires]);
    return $token;
}

/** Looks up a raw token; returns the reset row if it's valid (unused, unexpired), else null. */
function verify_reset_token(PDO $db, string $token): ?array {
    $hash = hash('sha256', $token);
    $stmt = $db->prepare('SELECT * FROM Password_Resets WHERE Token_Hash = ? AND Used = 0 AND Expires_At > NOW()');
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function consume_reset_token(PDO $db, int $reset_id): void {
    $db->prepare('UPDATE Password_Resets SET Used = 1 WHERE Reset_ID = ?')->execute([$reset_id]);
}

/**
 * Sends (or, in dev mode, simulates sending) a password reset email.
 * -----------------------------------------------------------------
 * INTEGRATION POINT: this project has no SMTP server configured, so it
 * runs in "dev mode" — instead of emailing the link, it hands the link
 * back so the page can display it directly. To send real email, install
 * PHPMailer (or use PHP's mail()), configure SMTP credentials in
 * config/mail.php, and replace the body of this function with an actual
 * send call. Keep the return value contract the same (bool success).
 * -----------------------------------------------------------------
 */
function send_reset_email(string $to_email, string $reset_link): array {
    $mail_configured = defined('SMTP_HOST') && SMTP_HOST !== '';
    $subject = 'Reset your TicketStub password';
    $body = "We received a request to reset your TicketStub password.\n\n"
        . "Click the link below to choose a new one (expires in 30 minutes):\n\n"
        . "$reset_link\n\n"
        . "If you didn't request this, you can safely ignore this email.\n\n"
        . "— TicketStub";

    if ($mail_configured) {
        // Real send would go here once SMTP_HOST etc. are configured.
    }

    // Always log locally too, so you can see exactly what would have been sent.
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) mkdir($log_dir, 0777, true);
    $entry = "\n" . str_repeat('=', 70) . "\nTo: $to_email\nSubject: $subject\nDate: " . date('Y-m-d H:i:s') . "\n" . str_repeat('-', 70) . "\n$body\n";
    file_put_contents($log_dir . '/mail.log', $entry, FILE_APPEND);

    if (!$mail_configured) {
        return ['sent' => false, 'dev_mode' => true, 'link' => $reset_link];
    }
    return ['sent' => true, 'dev_mode' => false, 'link' => $reset_link];
}

/**
 * Sends (or, in dev mode, just logs) a booking confirmation email listing
 * every ticket in the order. Uses the same SMTP dev-mode pattern above.
 */
function send_booking_confirmation_email(string $to_email, string $customer_name, array $tickets, float $total, string $reference): void {
    $lines = [];
    foreach ($tickets as $t) {
        $seat_part = $t['seat'] ? ", Seat {$t['seat']}" : '';
        $lines[] = "  - {$t['event_name']} ({$t['tier_name']}{$seat_part}) — QR: {$t['qr_data']}";
    }
    $subject = 'Your TicketStub booking is confirmed (' . $reference . ')';
    $body = "Hi $customer_name,\n\n"
        . "Your booking is confirmed! Here's your order summary:\n\n"
        . implode("\n", $lines) . "\n\n"
        . "Total paid: PHP " . number_format($total, 2) . "\n"
        . "Payment reference: $reference\n\n"
        . "You can view your QR tickets any time under My Tickets.\n\n"
        . "— TicketStub";

    $mail_configured = defined('SMTP_HOST') && SMTP_HOST !== '';
    if ($mail_configured) {
        // INTEGRATION POINT: real SMTP send would go here (see config/mail.php).
    }

    // Always log locally too, so you can see exactly what would have been sent.
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) mkdir($log_dir, 0777, true);
    $entry = "\n" . str_repeat('=', 70) . "\nTo: $to_email\nSubject: $subject\nDate: " . date('Y-m-d H:i:s') . "\n" . str_repeat('-', 70) . "\n$body\n";
    file_put_contents($log_dir . '/mail.log', $entry, FILE_APPEND);
}
