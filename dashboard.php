<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';

$pageTitle = "Dashboard";
$userId = $_SESSION['user_id'];

// Stats
$totalReservations = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE user_id = $userId AND status='Confirmed'")->fetch_assoc()['c'];
$totalCancelled = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE user_id = $userId AND status='Cancelled'")->fetch_assoc()['c'];
$totalSpent = $conn->query("SELECT COALESCE(SUM(amount),0) AS s FROM reservations WHERE user_id = $userId AND status='Confirmed'")->fetch_assoc()['s'];
$upcomingTrips = $conn->query("SELECT COUNT(*) AS c FROM reservations r JOIN trips t ON r.trip_id = t.id WHERE r.user_id = $userId AND r.status='Confirmed' AND t.travel_date >= CURDATE()")->fetch_assoc()['c'];

// Recent reservations
$recent = $conn->query("SELECT r.id, r.pnr, t.origin, t.destination, t.travel_date, t.departure_time, r.seat_number, r.status, r.amount
                         FROM reservations r JOIN trips t ON r.trip_id = t.id
                         WHERE r.user_id = $userId
                         ORDER BY r.reservation_date DESC LIMIT 5");

include 'includes/header.php';
?>

<section class="stat-grid">
    <div class="stat-card">
        <span class="stat-label">Active Reservations</span>
        <span class="stat-value"><?php echo $totalReservations; ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Upcoming Trips</span>
        <span class="stat-value"><?php echo $upcomingTrips; ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Cancelled</span>
        <span class="stat-value"><?php echo $totalCancelled; ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Total Spent</span>
        <span class="stat-value">₱<?php echo number_format($totalSpent, 2); ?></span>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Recent Reservations</h2>
        <a href="reservation.php" class="btn-primary btn-small">+ New Reservation</a>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>PNR</th>
                <th>Route</th>
                <th>Travel Date</th>
                <th>Departure</th>
                <th>Seat</th>
                <th>Amount</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($recent->num_rows === 0): ?>
                <tr><td colspan="8" class="empty-row">No reservations yet.</td></tr>
            <?php else: while ($row = $recent->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['pnr']); ?></td>
                    <td><?php echo htmlspecialchars($row['origin'] . ' → ' . $row['destination']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($row['travel_date'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($row['departure_time'])); ?></td>
                    <td>#<?php echo $row['seat_number']; ?></td>
                    <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span></td>
                    <td><a href="confirmation.php?pnr=<?php echo urlencode($row['pnr']); ?>" class="link-small">View Ticket</a></td>
                </tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>
</section>

<?php include 'includes/footer.php'; ?>
