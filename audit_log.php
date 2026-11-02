<?php
require_once 'includes/auth.php';
requireSuperAdmin();
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = "Audit Log";
const AUDIT_PER_PAGE = 30;

$groups = ['all' => 'All activity', 'account' => 'Sign-ins & accounts', 'booking' => 'Bookings', 'trips' => 'Trips', 'accounts' => 'Staff management'];
$roles = ['all' => 'Everyone', 'user' => 'Users', 'admin' => 'Admins', 'superadmin' => 'Super Admin', 'guest' => 'Guests'];

$group = array_key_exists($_GET['group'] ?? '', $groups) ? $_GET['group'] : 'all';
$role = array_key_exists($_GET['role'] ?? '', $roles) ? $_GET['role'] : 'all';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));

// ---------- Build the filter ----------
$where = [];
$params = [];
$types = '';
if ($group !== 'all') {
    $codes = array_keys(array_filter(AUDIT_ACTIONS, fn($a) => $a[1] === $group));
    $where[] = 'action IN (' . implode(',', array_fill(0, count($codes), '?')) . ')';
    array_push($params, ...$codes);
    $types .= str_repeat('s', count($codes));
}
if ($role !== 'all') { $where[] = 'role = ?'; $params[] = $role; $types .= 's'; }
if ($from !== '') { $where[] = 'created_at >= ?'; $params[] = "$from 00:00:00"; $types .= 's'; }
if ($to !== '') { $where[] = 'created_at < DATE_ADD(?, INTERVAL 1 DAY)'; $params[] = $to; $types .= 's'; }
if ($search !== '') {
    $where[] = '(username LIKE ? OR details LIKE ? OR ip_address LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like);
    $types .= 'sss';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

function runQuery($conn, $sql, $types, $params) {
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

$total = (int) runQuery($conn, "SELECT COUNT(*) AS c FROM audit_logs $whereSql", $types, $params)->fetch_assoc()['c'];
$pages = max(1, (int) ceil($total / AUDIT_PER_PAGE));
$page = min($page, $pages);
$offset = ($page - 1) * AUDIT_PER_PAGE;
$logs = runQuery($conn, "SELECT * FROM audit_logs $whereSql ORDER BY created_at DESC, id DESC LIMIT " . AUDIT_PER_PAGE . " OFFSET $offset", $types, $params);

// ---------- Today at a glance ----------
$today = $conn->query("SELECT
        COUNT(*) AS events,
        COALESCE(SUM(action = 'LOGIN'), 0) AS logins,
        COALESCE(SUM(action = 'LOGIN_FAILED'), 0) AS failed,
        COALESCE(SUM(action = 'BOOKING_CREATED'), 0) AS bookings
    FROM audit_logs WHERE created_at >= CURDATE()")->fetch_assoc();

function pageLink($n) {
    return 'audit_log.php?' . http_build_query(array_merge($_GET, ['page' => $n]));
}

include 'includes/header.php';
?>

<section class="stat-grid">
    <div class="stat-card"><span class="stat-label">Activity Today</span><span class="stat-value"><?php echo $today['events']; ?></span></div>
    <div class="stat-card"><span class="stat-label">Sign-ins Today</span><span class="stat-value"><?php echo $today['logins']; ?></span></div>
    <div class="stat-card"><span class="stat-label">Failed Sign-ins Today</span><span class="stat-value"><?php echo $today['failed']; ?></span></div>
    <div class="stat-card"><span class="stat-label">Bookings Today</span><span class="stat-value"><?php echo $today['bookings']; ?></span></div>
</section>

<section class="panel">
    <div class="panel-head"><h2>Filter</h2></div>
    <form method="GET" action="audit_log.php" class="inline-form report-form">
        <div class="form-field">
            <label>Activity</label>
            <select name="group">
                <?php foreach ($groups as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $group === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label>Who</label>
            <select name="role">
                <?php foreach ($roles as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $role === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label>From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
        </div>
        <div class="form-field">
            <label>To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
        </div>
        <div class="form-field">
            <label>Search</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, PNR, IP…">
        </div>
        <button type="submit" class="btn-primary">Apply</button>
        <a href="audit_log.php" class="btn-secondary">Reset</a>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Activity Records</h2>
        <span class="muted"><?php echo number_format($total); ?> record<?php echo $total === 1 ? '' : 's'; ?><?php echo $total > 0 ? " · page $page of $pages" : ''; ?></span>
    </div>
    <div class="table-wrap">
    <table class="data-table audit-table">
        <thead><tr><th>Date &amp; Time</th><th>Who</th><th>Action</th><th>Details</th><th>IP Address</th></tr></thead>
        <tbody>
        <?php if ($total === 0): ?>
            <tr><td colspan="5" class="empty-row">No activity matches these filters.</td></tr>
        <?php else: while ($log = $logs->fetch_assoc()):
            [$label, $logGroup] = AUDIT_ACTIONS[$log['action']] ?? [$log['action'], 'account']; ?>
            <tr>
                <td class="nowrap"><?php echo date('M d, Y', strtotime($log['created_at'])); ?><br><span class="muted"><?php echo date('h:i:s A', strtotime($log['created_at'])); ?></span></td>
                <td>
                    <?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?>
                    <?php if ($log['role']): ?><br><span class="role-badge <?php echo htmlspecialchars($log['role']); ?>"><?php echo $log['role'] === 'guest' ? 'Guest' : roleLabel($log['role']); ?></span><?php endif; ?>
                </td>
                <td><span class="audit-action audit-<?php echo $logGroup; ?><?php echo $log['action'] === 'LOGIN_FAILED' ? ' audit-warn' : ''; ?>"><?php echo htmlspecialchars($label); ?></span></td>
                <td class="audit-details"><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                <td class="nowrap muted"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?><a href="<?php echo htmlspecialchars(pageLink($page - 1)); ?>" class="btn-secondary btn-small">← Newer</a><?php endif; ?>
        <span class="muted">Page <?php echo $page; ?> of <?php echo $pages; ?></span>
        <?php if ($page < $pages): ?><a href="<?php echo htmlspecialchars(pageLink($page + 1)); ?>" class="btn-secondary btn-small">Older →</a><?php endif; ?>
    </div>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
