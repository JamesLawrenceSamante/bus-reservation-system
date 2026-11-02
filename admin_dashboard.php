<?php
require_once 'includes/auth.php';
requireAdmin();
require_once 'includes/db.php';

$pageTitle = "Admin Dashboard";

// System-wide stats
$totalUsers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'user'")->fetch_assoc()['c'];   // passengers only, not staff
$totalTrips = $conn->query("SELECT COUNT(*) AS c FROM trips")->fetch_assoc()['c'];
$totalReservations = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status = 'Confirmed'")->fetch_assoc()['c'];
// Net revenue: everything received minus the refunds actually given back
$totalRevenue = $conn->query("SELECT COALESCE(SUM(amount),0) AS s FROM payments")->fetch_assoc()['s']
              - $conn->query("SELECT COALESCE(SUM(refund_amount),0) AS s FROM cancellations")->fetch_assoc()['s'];

// Recent reservations (all users)
$recent = $conn->query("SELECT r.id, u.username, t.origin, t.destination, t.travel_date, r.seat_number, r.status, r.amount
                         FROM reservations r
                         JOIN users u ON r.user_id = u.id
                         JOIN trips t ON r.trip_id = t.id
                         ORDER BY r.reservation_date DESC LIMIT 8");

include 'includes/header.php';
?>

<section class="stat-grid">
    <div class="stat-card">
        <span class="stat-label">Registered Users</span>
        <span class="stat-value"><?php echo $totalUsers; ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Total Trips</span>
        <span class="stat-value"><?php echo $totalTrips; ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Active Reservations</span>
        <span class="stat-value"><?php echo $totalReservations; ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Total Revenue</span>
        <span class="stat-value">₱<?php echo number_format($totalRevenue, 2); ?></span>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Recent Reservations (All Users)</h2>
        <a href="manage_trips.php" class="btn-primary btn-small">+ Manage Trips</a>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Passenger</th>
                <th>Route</th>
                <th>Travel Date</th>
                <th>Seat</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($recent->num_rows === 0): ?>
                <tr><td colspan="6" class="empty-row">No reservations yet.</td></tr>
            <?php else: while ($row = $recent->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['origin'] . ' → ' . $row['destination']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($row['travel_date'])); ?></td>
                    <td>#<?php echo $row['seat_number']; ?></td>
                    <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span></td>
                </tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>
</section>

<?php include 'includes/footer.php'; ?>
