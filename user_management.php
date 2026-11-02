<?php
require_once 'includes/auth.php';
requireSuperAdmin();
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = "User Management";
$message = "";
$messageType = "success";

// Protects the forms on this page from being submitted by other sites
if (empty($_SESSION['um_csrf'])) $_SESSION['um_csrf'] = bin2hex(random_bytes(16));
$csrfOk = hash_equals($_SESSION['um_csrf'], $_POST['csrf'] ?? '');

// ---------- CREATE ADMIN (staff) ACCOUNT ----------
if (isset($_POST['create_admin'])) {
    if (!$csrfOk) {
        $message = "Your session expired. Please try again.";
    } else {
        $message = createAccount($conn, $_POST['username'], $_POST['email'], $_POST['phone'] ?? '',
                                 $_POST['password'], $_POST['confirm_password'], 'admin')
            ?? "Admin account for " . trim($_POST['username']) . " has been created. They can now sign in.";
    }
    $messageType = str_starts_with($message, 'Admin account for') ? 'success' : 'error';
    if ($messageType === 'success') {
        logActivity($conn, 'ADMIN_CREATED', 'New admin: ' . trim($_POST['username']) . ' (' . trim($_POST['email']) . ')');
    }
}

// ---------- CHANGE ROLE (user <-> admin) ----------
if (isset($_POST['change_role'])) {
    $targetId = (int) $_POST['user_id'];
    $newRole = $_POST['new_role'] ?? '';
    $messageType = 'error';

    $stmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $targetId);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();

    if (!$csrfOk) {
        $message = "Your session expired. Please try again.";
    } elseif (!$target) {
        $message = "Account not found.";
    } elseif ($targetId === (int) $_SESSION['user_id'] || $target['role'] === 'superadmin') {
        $message = "The super admin account can't be changed here.";
    } elseif (!in_array($newRole, ['user', 'admin'], true)) {
        $message = "Please choose a valid role.";
    } elseif ($newRole === $target['role']) {
        $message = "{$target['username']} is already " . roleLabel($newRole) . ".";
    } else {
        $update = $conn->prepare("UPDATE users SET role = ?, remember_token = NULL WHERE id = ?");   // sign them in again with the new role
        $update->bind_param("si", $newRole, $targetId);
        $update->execute();
        $message = "{$target['username']} is now " . ($newRole === 'admin' ? 'an Admin' : 'a User') . ".";
        $messageType = 'success';
        logActivity($conn, 'ROLE_CHANGED', sprintf('%s: %s → %s', $target['username'], roleLabel($target['role']), roleLabel($newRole)));
    }
}

// ---------- LIST ----------
$roleFilter = $_GET['role'] ?? 'all';
$sql = "SELECT id, username, email, phone, role, created_at FROM users";
if (in_array($roleFilter, ['user', 'admin', 'superadmin'], true)) $sql .= " WHERE role = '$roleFilter'";
$sql .= " ORDER BY FIELD(role, 'superadmin', 'admin', 'user'), created_at ASC";
$accounts = $conn->query($sql);

$counts = ['all' => 0, 'superadmin' => 0, 'admin' => 0, 'user' => 0];
foreach ($conn->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role") as $row) {
    $counts[$row['role']] = (int) $row['c'];
    $counts['all'] += (int) $row['c'];
}

$keep = $messageType === 'error' && isset($_POST['create_admin']);   // keep typed values after a failed create
include 'includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<section class="stat-grid">
    <div class="stat-card"><span class="stat-label">All Accounts</span><span class="stat-value"><?php echo $counts['all']; ?></span></div>
    <div class="stat-card"><span class="stat-label">Super Admin</span><span class="stat-value"><?php echo $counts['superadmin']; ?></span></div>
    <div class="stat-card"><span class="stat-label">Admins (Staff)</span><span class="stat-value"><?php echo $counts['admin']; ?></span></div>
    <div class="stat-card"><span class="stat-label">Users (Passengers)</span><span class="stat-value"><?php echo $counts['user']; ?></span></div>
</section>

<div class="um-layout">
    <section class="panel">
        <div class="panel-head"><h2>Create Admin Account</h2></div>
        <p class="muted" style="font-size:13px;margin-bottom:6px;">For newly hired staff. Admins can manage trips, inquiries, accounting and reports, but cannot manage accounts.</p>
        <form method="POST" action="user_management.php">
            <input type="hidden" name="csrf" value="<?php echo $_SESSION['um_csrf']; ?>">

            <label>Full Name / Username</label>
            <input type="text" name="username" value="<?php echo $keep ? htmlspecialchars($_POST['username']) : ''; ?>" required>

            <label>Email Address</label>
            <input type="email" name="email" value="<?php echo $keep ? htmlspecialchars($_POST['email']) : ''; ?>" required>

            <label>Mobile Number <span class="label-hint">(optional)</span></label>
            <input type="tel" name="phone" placeholder="09XX XXX XXXX" inputmode="tel" value="<?php echo $keep ? htmlspecialchars($_POST['phone']) : ''; ?>">

            <label>Temporary Password</label>
            <div class="password-field">
                <input type="password" name="password" minlength="8" autocomplete="new-password" required aria-describedby="passwordRules" data-password-policy>
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
                <input type="password" name="confirm_password" autocomplete="new-password" required data-password-confirm>
                <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                    <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                    <svg class="eye-off-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.2A10.9 10.9 0 0 1 12 6c6.5 0 10 6 10 6a18.6 18.6 0 0 1-3.1 3.7M6.3 6.3C3.7 8 2 12 2 12s3.5 6 10 6c1.4 0 2.6-.3 3.7-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"></path></svg>
                </button>
            </div>

            <button type="submit" name="create_admin" class="btn-primary">Create Admin Account</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>All Accounts</h2>
            <div class="um-filter">
                <?php foreach (['all' => 'All', 'admin' => 'Admins', 'user' => 'Users'] as $key => $label): ?>
                    <a href="user_management.php?role=<?php echo $key; ?>" class="btn-small <?php echo $roleFilter === $key ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo $label; ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Email / Mobile</th><th>Role</th><th>Joined</th><th>Change Role</th></tr></thead>
            <tbody>
            <?php if ($accounts->num_rows === 0): ?>
                <tr><td colspan="5" class="empty-row">No accounts found.</td></tr>
            <?php else: while ($a = $accounts->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($a['username']); ?><?php echo (int) $a['id'] === (int) $_SESSION['user_id'] ? ' <span class="muted">(you)</span>' : ''; ?></td>
                    <td><?php echo htmlspecialchars($a['email']); ?><?php if ($a['phone']): ?><br><span class="muted"><?php echo htmlspecialchars($a['phone']); ?></span><?php endif; ?></td>
                    <td><span class="role-badge <?php echo $a['role']; ?>"><?php echo roleLabel($a['role']); ?></span></td>
                    <td><?php echo date('M d, Y', strtotime($a['created_at'])); ?></td>
                    <td>
                        <?php if ($a['role'] === 'superadmin'): ?>
                            <span class="muted">—</span>
                        <?php else: ?>
                            <form method="POST" action="user_management.php?role=<?php echo htmlspecialchars($roleFilter); ?>" class="inline-role-form">
                                <input type="hidden" name="csrf" value="<?php echo $_SESSION['um_csrf']; ?>">
                                <input type="hidden" name="user_id" value="<?php echo $a['id']; ?>">
                                <select name="new_role" aria-label="New role for <?php echo htmlspecialchars($a['username']); ?>">
                                    <option value="user" <?php echo $a['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="admin" <?php echo $a['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                                <button type="submit" name="change_role" class="btn-secondary btn-small">Save</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
