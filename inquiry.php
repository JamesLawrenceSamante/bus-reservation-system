<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';

$pageTitle = "Inquiry";

$origin = $_GET['origin'] ?? '';
$destination = $_GET['destination'] ?? '';
$date = $_GET['date'] ?? '';

$sql = "SELECT t.id, b.bus_name, t.origin, t.destination, t.travel_date, t.departure_time, t.arrival_time, t.fare, t.available_seats
        FROM trips t JOIN buses b ON t.bus_id = b.id WHERE 1=1";
$params = [];
$types = "";

if ($origin !== '') {
    $sql .= " AND t.origin LIKE ?";
    $params[] = "%$origin%";
    $types .= "s";
}
if ($destination !== '') {
    $sql .= " AND t.destination LIKE ?";
    $params[] = "%$destination%";
    $types .= "s";
}
if ($date !== '') {
    $sql .= " AND t.travel_date = ?";
    $params[] = $date;
    $types .= "s";
}
$sql .= " ORDER BY t.travel_date ASC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$results = $stmt->get_result();

include 'includes/header.php';
?>

<section class="panel">
    <div class="panel-head"><h2>Search Trips</h2></div>
    <form method="GET" action="inquiry.php" class="inline-form">
        <div class="form-field">
            <label>From</label>
            <input type="text" name="origin" value="<?php echo htmlspecialchars($origin); ?>" placeholder="e.g. Manila">
        </div>
        <div class="form-field">
            <label>To</label>
            <input type="text" name="destination" value="<?php echo htmlspecialchars($destination); ?>" placeholder="e.g. Baguio">
        </div>
        <div class="form-field">
            <label>Date</label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>">
        </div>
        <button type="submit" class="btn-primary">Search</button>
    </form>
</section>

<section class="panel">
    <div class="panel-head"><h2>Results</h2></div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Bus</th>
                <th>Route</th>
                <th>Date</th>
                <th>Departure</th>
                <th>Arrival</th>
                <th>Fare</th>
                <th>Seats Left</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($results->num_rows === 0): ?>
                <tr><td colspan="7" class="empty-row">No trips match your search.</td></tr>
            <?php else: while ($t = $results->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($t['bus_name']); ?></td>
                    <td><?php echo htmlspecialchars($t['origin'] . ' → ' . $t['destination']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($t['travel_date'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($t['departure_time'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($t['arrival_time'])); ?></td>
                    <td>₱<?php echo number_format($t['fare'], 2); ?></td>
                    <td><?php echo $t['available_seats']; ?></td>
                </tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>
</section>

<?php include 'includes/footer.php'; ?>
