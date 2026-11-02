<?php
session_start();

const REMEMBER_COOKIE = 'remember_token';
const REMEMBER_DAYS = 30;

/**
 * Sets the "Remember Me" cookie. The browser keeps the raw token; the database only stores its hash.
 */
function setRememberCookie($token, $expires) {
    setcookie(REMEMBER_COOKIE, $token, [
        'expires' => $expires,
        'path' => '/',
        'httponly' => true,   // not readable from JavaScript
        'samesite' => 'Lax',
    ]);
}

/**
 * Logs the user in from the "Remember Me" cookie when there is no active session.
 */
function restoreRememberedLogin($conn) {
    $hash = hash('sha256', $_COOKIE[REMEMBER_COOKIE]);
    $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        require_once __DIR__ . '/helpers.php';
        logActivity($conn, 'LOGIN', 'Signed in automatically (Remember Me)');
    } else {
        setRememberCookie('', time() - 3600);   // unknown or expired token: drop it
    }
}
if (!isset($_SESSION['user_id']) && !empty($_COOKIE[REMEMBER_COOKIE])) {
    require_once __DIR__ . '/db.php';   // included here (not inside the function) so $conn stays global for the page
    restoreRememberedLogin($conn);
}

/**
 * Re-reads the signed-in account on every request, so a role change by the super admin
 * (or a removed account) takes effect right away instead of at the next login.
 */
function refreshSignedInUser($conn) {
    $stmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
    } else {
        session_unset();   // account no longer exists
    }
}
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/db.php';
    refreshSignedInUser($conn);
}

// ---------- Roles ----------
// user = passenger, admin = staff (employee), superadmin = owner who also manages staff accounts
function currentRole() { return $_SESSION['role'] ?? 'user'; }
function isStaffRole($role) { return $role === 'admin' || $role === 'superadmin'; }
function isStaff() { return isStaffRole(currentRole()); }
function isSuperAdmin() { return currentRole() === 'superadmin'; }
function roleLabel($role) {
    return ['superadmin' => 'Super Admin', 'admin' => 'Admin'][$role] ?? 'User';
}
function homePageFor($role) { return isStaffRole($role) ? 'admin_dashboard.php' : 'dashboard.php'; }

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        // Remember where they were going (e.g. a trip picked on the homepage) so login can send them back
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $query = $_SERVER['QUERY_STRING'] ?? '';
            $_SESSION['after_login'] = basename($_SERVER['PHP_SELF']) . ($query !== '' ? '?' . $query : '');
        }
        header("Location: login.php");
        exit();
    }
}

/**
 * Where to go right after signing in: the page they were trying to open (passengers only), else their home page.
 * Only plain local page names are accepted, so this can't be used to send someone to another site.
 */
function pageAfterLogin($role) {
    $target = $_SESSION['after_login'] ?? '';
    unset($_SESSION['after_login']);
    if (!isStaffRole($role) && preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.-]*)?$/', $target)) {
        return $target;
    }
    return homePageFor($role);
}

// Admin pages: open to admins and the super admin
function requireAdmin() {
    requireLogin();
    if (!isStaff()) {
        header("Location: dashboard.php");
        exit();
    }
}

// Super admin only (staff account management)
function requireSuperAdmin() {
    requireLogin();
    if (!isSuperAdmin()) {
        header("Location: " . homePageFor(currentRole()));
        exit();
    }
}
?>
