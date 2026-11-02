<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$reset = $_SESSION['password_reset'] ?? null;
$error = "";
$done = false;

// The link is valid only if it matches the token issued in this browser and hasn't expired
$tokenValid = $reset
    && $token !== ''
    && hash_equals($reset['token_hash'], hash('sha256', $token))
    && time() <= $reset['expires'];

if ($tokenValid && isset($_POST['set_password'])) {
    $password = $_POST['new_password'];
    $missing = passwordPolicyErrors($password);

    if ($missing) {
        $error = "Password must have " . implode(', ', $missing) . ".";
    } elseif ($password !== $_POST['confirm_password']) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        // New password; also sign out remembered devices that used the old one
        $update = $conn->prepare("UPDATE users SET password = ?, remember_token = NULL WHERE id = ?");
        $update->bind_param("si", $hashed, $reset['user_id']);
        $update->execute();
        unset($_SESSION['password_reset']);   // the link works only once
        $done = true;

        $who = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
        $who->bind_param("i", $reset['user_id']);
        $who->execute();
        $account = $who->get_result()->fetch_assoc();
        if ($account) logActivity($conn, 'PASSWORD_RESET', 'Password changed via reset link', $account);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create New Password - Bus Reservation System</title>
<link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="auth-body">
<div class="auth-wrapper">
    <div class="auth-brand">🚌 Create New Password</div>

    <?php if ($done): ?>
        <p class="form-success">Your password has been changed. You can now sign in with your new password.</p>
        <a href="login.php" class="btn-primary btn-block">Go to Sign In</a>

    <?php elseif (!$tokenValid): ?>
        <p class="form-error">This reset link is invalid or has expired. Please request a new one.</p>
        <a href="forgot_password.php" class="btn-primary btn-block">Request a New Link</a>

    <?php else: ?>
        <p class="auth-tagline">Choose a new password for your account.</p>
        <?php if ($error): ?><p class="form-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <form method="POST" action="reset_password.php">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <label>New Password</label>
            <div class="password-field">
                <input type="password" name="new_password" minlength="8" autocomplete="new-password" required aria-describedby="passwordRules" data-password-policy>
                <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                    <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                    <svg class="eye-off-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.2A10.9 10.9 0 0 1 12 6c6.5 0 10 6 10 6a18.6 18.6 0 0 1-3.1 3.7M6.3 6.3C3.7 8 2 12 2 12s3.5 6 10 6c1.4 0 2.6-.3 3.7-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"></path></svg>
                </button>
            </div>
            <ul class="password-rules" id="passwordRules">
                <li data-rule="length">At least 8 characters</li>
                <li data-rule="upper">One uppercase letter (A–Z)</li>
                <li data-rule="lower">One lowercase letter (a–z)</li>
                <li data-rule="number">One number (0–9)</li>
            </ul>

            <label>Confirm New Password</label>
            <div class="password-field">
                <input type="password" name="confirm_password" autocomplete="new-password" required data-password-confirm>
                <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                    <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                    <svg class="eye-off-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.2A10.9 10.9 0 0 1 12 6c6.5 0 10 6 10 6a18.6 18.6 0 0 1-3.1 3.7M6.3 6.3C3.7 8 2 12 2 12s3.5 6 10 6c1.4 0 2.6-.3 3.7-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"></path></svg>
                </button>
            </div>

            <button type="submit" name="set_password" class="btn-primary">Save New Password</button>
        </form>
    <?php endif; ?>

    <p style="text-align:center;margin-top:16px;"><a href="login.php" class="link-small">Back to Sign In</a></p>
</div>
<script src="js/script.js?v=<?php echo filemtime(__DIR__ . '/js/script.js'); ?>"></script>
</body>
</html>
