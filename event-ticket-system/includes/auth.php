<?php
/**
 * Authentication + Role-Based Access Control (RBAC)
 *
 * Two account families share one session shape:
 *   - Customers  -> $_SESSION['account_type'] = 'customer'
 *   - Staff      -> $_SESSION['account_type'] = 'staff', with $_SESSION['role'] = 'Admin'|'Organizer'|'Staff'
 *
 * Every gated page should call require_login() and, where relevant,
 * require_role([...]) at the very top, before any HTML is echoed.
 */

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** True if someone is logged in (customer OR staff). */
function is_logged_in(): bool {
    return isset($_SESSION['account_type']);
}

/** True if the logged-in account is a customer. */
function is_customer(): bool {
    return is_logged_in() && $_SESSION['account_type'] === 'customer';
}

/** True if the logged-in account is any staff role. */
function is_staff(): bool {
    return is_logged_in() && $_SESSION['account_type'] === 'staff';
}

/** Returns the current staff role name ('Admin'/'Organizer'/'Staff') or null. */
function current_role(): ?string {
    return $_SESSION['role'] ?? null;
}

/** Redirects to login if nobody is logged in. */
function require_login(string $redirect_to = '/login.php'): void {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Please log in to continue.';
        header('Location: ' . base_url($redirect_to) . '?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Restricts a page to one or more staff roles.
 * Example: require_role(['Admin', 'Organizer']);
 */
function require_role(array $allowed_roles): void {
    require_login('/staff/login.php');
    if (!is_staff() || !in_array(current_role(), $allowed_roles, true)) {
        http_response_code(403);
        echo '<link rel="stylesheet" href="' . base_url('/assets/css/style.css') . '">';
        echo '<div class="empty-state"><h2>403 — Access denied</h2><p>Your account (' .
             htmlspecialchars(current_role() ?? 'guest') .
             ') does not have permission to view this page.</p>' .
             '<a class="btn btn-primary" href="' . base_url('/index.php') . '">Back to home</a></div>';
        exit;
    }
}

/** Restricts a page to logged-in customers only. */
function require_customer(): void {
    require_login('/login.php');
    if (!is_customer()) {
        http_response_code(403);
        echo '<link rel="stylesheet" href="' . base_url('/assets/css/style.css') . '">';
        echo '<div class="empty-state"><h2>403 — Access denied</h2><p>This page is only available to customer accounts.</p></div>';
        exit;
    }
}

/**
 * Builds an absolute-from-root URL so links work regardless of subfolder
 * depth (works whether the project sits at the web root or in a subfolder,
 * e.g. http://localhost/event-ticket-system/).
 */
function base_url(string $path = ''): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $root = $script;
    foreach (['/staff/', '/customer/'] as $marker) {
        $pos = strpos($script, $marker);
        if ($pos !== false) { $root = substr($script, 0, $pos); break; }
    }
    if ($root === $script) {
        // Not inside a subfolder — use the script's own directory
        $root = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($root === '.') $root = '';
    }
    return $root . $path;
}

/** Logs a customer in by attaching session data. */
function login_customer(array $customer): void {
    session_regenerate_id(true);
    $_SESSION['account_type'] = 'customer';
    $_SESSION['customer_id']  = $customer['Customer_ID'];
    $_SESSION['user_name']    = $customer['First_Name'] . ' ' . $customer['Last_Name'];
    $_SESSION['user_email']   = $customer['Email_Address'];
}

/** Logs a staff member in by attaching session data. */
function login_staff(array $staff, string $role_name): void {
    session_regenerate_id(true);
    $_SESSION['account_type'] = 'staff';
    $_SESSION['staff_id']     = $staff['Staff_ID'];
    $_SESSION['user_name']    = $staff['First_Name'] . ' ' . $staff['Last_Name'];
    $_SESSION['role']         = $role_name;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/** Generates and stashes a CSRF token for a form. */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Verifies a submitted CSRF token, halting the request if it doesn't match. */
function verify_csrf(?string $submitted): void {
    if (!$submitted || !hash_equals($_SESSION['csrf_token'] ?? '', $submitted)) {
        http_response_code(400);
        die('Security check failed. Please go back and try again.');
    }
}

// ------------------------------------------------------------
// Login rate limiting (brute-force protection)
// ------------------------------------------------------------
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_MINUTES = 15;

/** Records a login attempt (success or failure) for rate-limiting purposes. */
function record_login_attempt(string $identifier, string $account_type, bool $success): void {
    $db = get_db();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $db->prepare('INSERT INTO Login_Attempts (Identifier, Account_Type, Ip_Address, Was_Success) VALUES (?,?,?,?)')
       ->execute([strtolower($identifier), $account_type, $ip, $success ? 1 : 0]);
}

/**
 * Returns null if login attempts are allowed, or a human-readable
 * message if the identifier is currently locked out.
 */
function login_lockout_message(string $identifier, string $account_type): ?string {
    $db = get_db();
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS failures, MAX(Attempted_At) AS last_attempt FROM Login_Attempts
         WHERE Identifier = ? AND Account_Type = ? AND Was_Success = 0
               AND Attempted_At > (NOW() - INTERVAL ? MINUTE)"
    );
    $stmt->execute([strtolower($identifier), $account_type, LOGIN_LOCKOUT_MINUTES]);
    $row = $stmt->fetch();

    if ($row && (int)$row['failures'] >= LOGIN_MAX_ATTEMPTS) {
        return "Too many failed login attempts. Please try again in " . LOGIN_LOCKOUT_MINUTES . " minutes.";
    }
    return null;
}

/** Clears failed-attempt history for an identifier after a successful login. */
function clear_login_attempts(string $identifier, string $account_type): void {
    $db = get_db();
    $db->prepare('DELETE FROM Login_Attempts WHERE Identifier = ? AND Account_Type = ?')
       ->execute([strtolower($identifier), $account_type]);
}
