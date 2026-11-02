<?php
require_once 'includes/auth.php';   // starts the session and signs in remembered users
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// Already signed in (normal session or Remember Me): skip the login page
if (isset($_SESSION['user_id']) && !isset($_POST['signin']) && !isset($_POST['signup'])) {
    header("Location: " . homePageFor(currentRole()));
    exit();
}

// ---------- Math CAPTCHA ----------
// The answer stays in the session (never in the page), and each question works only once.
function newCaptcha($key) {
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION[$key] = $a + $b;
    return "$a + $b";
}
function captchaPassed($key, $input) {
    $passed = isset($_SESSION[$key]) && trim((string) $input) !== '' && (int) $input === $_SESSION[$key];
    unset($_SESSION[$key]);
    return $passed;
}
$captchaError = "Incorrect answer to the security question. Please try again.";

$loginError = "";
$loginIdentifier = "";
$signupError = "";
$signupSuccess = "";

// ---------- SIGN IN ----------
if (isset($_POST['signin'])) {
    $emailOrUser = trim($_POST['login_identifier']);
    $password = $_POST['login_password'];
    $loginIdentifier = $emailOrUser;   // shown again in the form if sign-in fails

    if (!captchaPassed('captcha_signin', $_POST['captcha'] ?? '')) {
        $loginError = $captchaError;
    } else {
        // Sign in with username, email, or PH mobile number (any common format)
        $phoneGuess = normalizePhone($emailOrUser) ?? '';
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users
                                WHERE email = ? OR username = ? OR (phone IS NOT NULL AND phone = ?)");
        $stmt->bind_param("sss", $emailOrUser, $emailOrUser, $phoneGuess);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                if (isset($_POST['remember_me'])) {
                    // Cookie keeps the raw token; the database keeps only its hash
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    setRememberCookie($token, time() + 86400 * REMEMBER_DAYS);
                    $stmt2 = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $stmt2->bind_param("si", $tokenHash, $user['id']);
                    $stmt2->execute();
                }

                logActivity($conn, 'LOGIN', isset($_POST['remember_me']) ? 'Signed in (Remember Me on)' : 'Signed in');
                header("Location: " . pageAfterLogin($user['role']));
                exit();
            } else {
                $loginError = "Incorrect password.";
                logActivity($conn, 'LOGIN_FAILED', 'Wrong password', ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']]);
            }
        } else {
            $loginError = "No account found with that username, email or mobile number.";
            logActivity($conn, 'LOGIN_FAILED', 'Unknown account: ' . $emailOrUser, ['id' => null, 'username' => null, 'role' => null]);
        }
    }
}

// ---------- SIGN UP ----------
if (isset($_POST['signup'])) {
    if (!captchaPassed('captcha_signup', $_POST['captcha'] ?? '')) {
        $signupError = $captchaError;
    } else {
        // Same rules as the super admin's account form: email/phone unique, password policy, passwords match
        $signupError = createAccount($conn, $_POST['signup_username'], $_POST['signup_email'], $_POST['signup_phone'] ?? '',
                                     $_POST['signup_password'], $_POST['signup_confirm_password']) ?? "";
        if ($signupError === "") {
            $signupSuccess = "Account created! You can now sign in.";
            $newUser = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
            $newEmail = trim($_POST['signup_email']);
            $newUser->bind_param("s", $newEmail);
            $newUser->execute();
            $created = $newUser->get_result()->fetch_assoc();
            logActivity($conn, 'SIGNUP', 'New passenger account: ' . $newEmail,
                        ['id' => $created['id'] ?? null, 'username' => $created['username'] ?? null, 'role' => 'user']);
        }
    }
}

// Fresh questions every time the page is shown
$signinQuestion = newCaptcha('captcha_signin');
$signupQuestion = newCaptcha('captcha_signup');

// Keep the Sign Up tab open when its form has an error, so the message is visible
$activeTab = $signupError ? 'signup' : 'signin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Bus Reservation System</title>
<link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="auth-body">
<div class="auth-wrapper">
    <div class="auth-brand">🚌 Bus Reservation System</div>
    <p class="auth-tagline">Travel smarter. Book faster.</p>
    <div class="auth-tabs">
        <button type="button" class="tab-btn <?php echo $activeTab === 'signin' ? 'active' : ''; ?>" data-tab="signin">Sign In</button>
        <button type="button" class="tab-btn <?php echo $activeTab === 'signup' ? 'active' : ''; ?>" data-tab="signup">Sign Up</button>
    </div>

    <div class="tab-panel <?php echo $activeTab === 'signin' ? '' : 'hidden'; ?>" id="signin-panel">
        <?php if ($signupSuccess): ?><p class="form-success"><?php echo htmlspecialchars($signupSuccess); ?></p><?php endif; ?>
        <?php if ($loginError): ?><p class="form-error"><?php echo htmlspecialchars($loginError); ?></p><?php endif; ?>
        <form method="POST" action="login.php">
            <label>Username, Email or Mobile Number</label>
            <input type="text" name="login_identifier" value="<?php echo htmlspecialchars($loginIdentifier); ?>" autocomplete="username" required>

            <label>Password</label>
            <div class="password-field">
                <input type="password" name="login_password" required>
                <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                    <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                    <svg class="eye-off-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.2A10.9 10.9 0 0 1 12 6c6.5 0 10 6 10 6a18.6 18.6 0 0 1-3.1 3.7M6.3 6.3C3.7 8 2 12 2 12s3.5 6 10 6c1.4 0 2.6-.3 3.7-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"></path></svg>
                </button>
            </div>

            <label for="captchaSignin">Security check: What is <?php echo $signinQuestion; ?>?</label>
            <input type="number" name="captcha" id="captchaSignin" class="captcha-input" inputmode="numeric" placeholder="Your answer" autocomplete="off" required>

            <div class="form-row">
                <label class="checkbox-label"><input type="checkbox" name="remember_me" <?php echo isset($_POST['remember_me']) ? 'checked' : ''; ?>> Remember Me</label>
                <a href="forgot_password.php" class="link-small">Forgot Password?</a>
            </div>

            <button type="submit" name="signin" class="btn-primary">Sign In</button>

            <div class="divider">or continue with</div>
            <div class="social-buttons">
                <a href="social_login.php?provider=google" class="btn-social">Gmail</a>
                <a href="social_login.php?provider=facebook" class="btn-social">Facebook</a>
            </div>
        </form>
    </div>

    <div class="tab-panel <?php echo $activeTab === 'signup' ? '' : 'hidden'; ?>" id="signup-panel">
        <?php if ($signupError): ?><p class="form-error"><?php echo htmlspecialchars($signupError); ?></p><?php endif; ?>
        <form method="POST" action="login.php">
            <label>Username / Email Address</label>
            <input type="text" name="signup_username" placeholder="Username" value="<?php echo $signupError ? htmlspecialchars($_POST['signup_username'] ?? '') : ''; ?>" required>
            <input type="email" name="signup_email" placeholder="Email Address" value="<?php echo $signupError ? htmlspecialchars($_POST['signup_email'] ?? '') : ''; ?>" required>

            <label>Mobile Number <span class="label-hint">(optional, lets you sign in with it)</span></label>
            <input type="tel" name="signup_phone" placeholder="09XX XXX XXXX" inputmode="tel" autocomplete="tel" value="<?php echo $signupError ? htmlspecialchars($_POST['signup_phone'] ?? '') : ''; ?>">

            <label>Password</label>
            <div class="password-field">
                <input type="password" name="signup_password" id="signupPassword" minlength="8" required aria-describedby="passwordRules" data-password-policy>
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

            <label>Confirm Password</label>
            <div class="password-field">
                <input type="password" name="signup_confirm_password" required data-password-confirm>
                <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                    <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                    <svg class="eye-off-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.2A10.9 10.9 0 0 1 12 6c6.5 0 10 6 10 6a18.6 18.6 0 0 1-3.1 3.7M6.3 6.3C3.7 8 2 12 2 12s3.5 6 10 6c1.4 0 2.6-.3 3.7-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"></path></svg>
                </button>
            </div>

            <label for="captchaSignup">Security check: What is <?php echo $signupQuestion; ?>?</label>
            <input type="number" name="captcha" id="captchaSignup" class="captcha-input" inputmode="numeric" placeholder="Your answer" autocomplete="off" required>

            <button type="submit" name="signup" class="btn-primary">Sign Up</button>
        </form>
    </div>
</div>
<script src="js/script.js?v=<?php echo filemtime(__DIR__ . '/js/script.js'); ?>"></script>
</body>
</html>
