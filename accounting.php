<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';

$pageTitle = "Accounting";
$userId = $_SESSION['user_id'];
$isAdmin = isStaff();
$scopeFilter = $isAdmin ? "1=1" : "r.user_id = $userId";

// Summary
// Total Paid = all money received; Total Refunded = actual refund amounts (90/70/40/0% of what was paid)
$totalPaid = $conn->query("SELECT COALESCE(SUM(p.amount),0) AS s FROM payments p JOIN reservations r ON p.reservation_id = r.id WHERE $scopeFilter")->fetch_assoc()['s'];
$totalRefunded = $conn->query("SELECT COALESCE(SUM(c.refund_amount),0) AS s FROM cancellations c JOIN reservations r ON c.reservation_id = r.id WHERE $scopeFilter")->fetch_assoc()['s'];
$transactionCount = $conn->query("SELECT COUNT(*) AS c FROM payments p JOIN reservations r ON p.reservation_id = r.id WHERE $scopeFilter")->fetch_assoc()['c'];

// Payment history
$payments = $conn->query("SELECT p.id, t.origin, t.destination, p.amount, p.payment_method, p.payment_status, p.payment_date, u.username
                           FROM payments p
                           JOIN reservations r ON p.reservation_id = r.id
                           JOIN trips t ON r.trip_id = t.id
                           JOIN users u ON r.user_id = u.id
                           WHERE $scopeFilter
                           ORDER BY p.payment_date DESC");

include 'includes/header.php';
?>

<section class="stat-grid">
    <div class="stat-card">
        <span class="stat-label">Total Paid</span>
        <span class="stat-value">₱<?php echo number_format($totalPaid, 2); ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Total Refunded</span>
        <span class="stat-value">₱<?php echo number_format($totalRefunded, 2); ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Transactions</span>
        <span class="stat-value"><?php echo $transactionCount; ?></span>
    </div>
</section>

<section class="panel">
    <div class="panel-head"><h2>Payment History</h2></div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Transaction #</th>
                <?php if ($isAdmin): ?><th>Passenger</th><?php endif; ?>
                <th>Route</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($payments->num_rows === 0): ?>
                <tr><td colspan="<?php echo $isAdmin ? 7 : 6; ?>" class="empty-row">No transactions yet.</td></tr>
            <?php else: while ($p = $payments->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo str_pad($p['id'], 5, '0', STR_PAD_LEFT); ?></td>
                    <?php if ($isAdmin): ?><td><?php echo htmlspecialchars($p['username']); ?></td><?php endif; ?>
                    <td><?php echo htmlspecialchars($p['origin'] . ' → ' . $p['destination']); ?></td>
                    <td>₱<?php echo number_format($p['amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($p['payment_status']); ?>"><?php echo $p['payment_status']; ?></span></td>
                    <td><?php echo date('M d, Y h:i A', strtotime($p['payment_date'])); ?></td>
                </tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>
</section>

<?php include 'includes/footer.php'; ?>
