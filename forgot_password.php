<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

const RESET_MINUTES = 15;

$message = "";
$resetLink = "";

if (isset($_POST['reset'])) {
    $email = trim($_POST['email']);
    $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    $message = "If this email is registered, a password reset link has been sent.";

    if ($user) {
        // One-time token: the link carries the raw token, the server keeps only its hash.
        // Demo version: kept in the session because XAMPP has no mail server to email the link.
        $token = bin2hex(random_bytes(32));
        $_SESSION['password_reset'] = [
            'user_id' => $user['id'],
            'token_hash' => hash('sha256', $token),
            'expires' => time() + RESET_MINUTES * 60,
        ];
        $resetLink = 'reset_password.php?token=' . $token;
        logActivity($conn, 'PASSWORD_RESET_REQUEST', 'Reset link issued for ' . $email,
                    ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - Bus Reservation System</title>
<link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="auth-body">
<div class="auth-wrapper">
    <div class="auth-brand">🚌 Reset Password</div>
    <p class="auth-tagline">Enter the email you used to sign up and we'll send you a link to create a new password.</p>

    <?php if ($message): ?><p class="form-success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

    <?php if ($resetLink): ?>
        <div class="demo-notice">
            <strong>Demo mode</strong>
            <p>This XAMPP project has no email server, so the link that would be emailed is shown here instead. It works once and expires in <?php echo RESET_MINUTES; ?> minutes.</p>
            <a href="<?php echo htmlspecialchars($resetLink); ?>" class="btn-primary">Open Reset Link</a>
        </div>
    <?php else: ?>
        <form method="POST" action="forgot_password.php">
            <label>Registered email address</label>
            <input type="email" name="email" autocomplete="email" required>
            <button type="submit" name="reset" class="btn-primary">Send Reset Link</button>
        </form>
    <?php endif; ?>

    <p style="text-align:center;margin-top:16px;"><a href="login.php" class="link-small">Back to Sign In</a></p>
</div>
</body>
</html>
