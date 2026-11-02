<?php
// Placeholder for Gmail / Facebook OAuth integration.
// In production, use libraries like league/oauth2-google or the Facebook PHP SDK
// to authenticate the user, then create/find the matching row in `users`
// and set $_SESSION['user_id'] and $_SESSION['username'] before redirecting.

$provider = $_GET['provider'] ?? 'unknown';
session_start();
$_SESSION['social_provider'] = $provider;

echo "<p style='font-family:sans-serif;text-align:center;margin-top:40px;'>";
echo "This is a placeholder for " . htmlspecialchars(ucfirst($provider)) . " login integration.<br>";
echo "Connect your OAuth credentials here to enable real social sign-in.<br><br>";
echo "<a href='login.php'>Back to Login</a></p>";
?>
