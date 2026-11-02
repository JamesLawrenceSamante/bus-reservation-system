<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// Forget this device: remove the saved Remember Me token so the cookie can't sign in again
if (isset($_SESSION['user_id'])) {
    logActivity($conn, 'LOGOUT', 'Signed out');
    $clear = $conn->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $clear->bind_param("i", $_SESSION['user_id']);
    $clear->execute();
}

session_unset();
session_destroy();
setRememberCookie('', time() - 3600);
header("Location: login.php");
exit();
?>
