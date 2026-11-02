<?php
require_once 'includes/auth.php';
requireAdmin();
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = "Manage Trips";
$message = "";
$messageType = "";
$allAmenities = ['AC', 'WiFi', 'USB Charging', 'Restroom', 'Blanket', 'Reclining Seats'];

// ---------- ADD NEW TRIP ----------
if (isset($_POST['add_trip'])) {
    $busId = (int) $_POST['bus_id'];
    $origin = trim($_POST['origin']);
    $destination = trim($_POST['destination']);
    $travelDate = $_POST['travel_date'];
    $departureTime = $_POST['departure_time'];
    $arrivalTime = $_POST['arrival_time'];
    $fare = (float) $_POST['fare'];
    $seats = (int) $_POST['available_seats'];
    $busType = $_POST['bus_type'];
    $amenities = implode(',', $_POST['amenities'] ?? []);
    $pickupPoints = trim($_POST['pickup_points']);
    $dropoffPoints = trim($_POST['dropoff_points']);
    $originTerminalId = $_POST['origin_terminal_id'] !== '' ? (int) $_POST['origin_terminal_id'] : null;
    $destinationTerminalId = $_POST['destination_terminal_id'] !== '' ? (int) $_POST['destination_terminal_id'] : null;

    $stmt = $conn->prepare("INSERT INTO trips
        (bus_id, origin, destination, travel_date, departure_time, arrival_time, fare, available_seats, bus_type, amenities, pickup_points, dropoff_points, origin_terminal_id, destination_terminal_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssdissssii", $busId, $origin, $destination, $travelDate, $departureTime, $arrivalTime, $fare, $seats, $busType, $amenities, $pickupPoints, $dropoffPoints, $originTerminalId, $destinationTerminalId);
    if ($stmt->execute()) {
        logActivity($conn, 'TRIP_ADDED', sprintf('Trip #%d · %s → %s · %s %s · %s · ₱%s · %d seats',
            $conn->insert_id, $origin, $destination, $travelDate, substr($departureTime, 0, 5), $busType, number_format($fare, 2), $seats));
        $message = "Trip added successfully.";
        $messageType = "success";
    } else {
        $message = "Failed to add trip.";
        $messageType = "error";
    }
}

// ---------- DELETE TRIP ----------
if (isset($_POST['delete_trip'])) {
    $tripId = (int) $_POST['trip_id'];
    // Read the trip first so the audit log can say which one was removed
    $info = $conn->prepare("SELECT origin, destination, travel_date, departure_time FROM trips WHERE id = ?");
    $info->bind_param("i", $tripId);
    $info->execute();
    $removed = $info->get_result()->fetch_assoc();
    $del = $conn->prepare("DELETE FROM trips WHERE id = ?");
    $del->bind_param("i", $tripId);
    $del->execute();
    if ($removed) {
        logActivity($conn, 'TRIP_REMOVED', sprintf('Trip #%d · %s → %s · %s %s',
            $tripId, $removed['origin'], $removed['destination'], $removed['travel_date'], substr($removed['departure_time'], 0, 5)));
    }
    $message = "Trip removed.";
    $messageType = "success";
}

$buses = $conn->query("SELECT id, bus_name FROM buses ORDER BY bus_name ASC");
$terminals = $conn->query("SELECT id, name FROM terminals ORDER BY name ASC");
$terminalOptions = [];
while ($row = $terminals->fetch_assoc()) { $terminalOptions[] = $row; }

$trips = $conn->query("SELECT t.id, b.bus_name, t.origin, t.destination, t.travel_date, t.departure_time, t.fare, t.available_seats, t.bus_type
                        FROM trips t JOIN buses b ON t.bus_id = b.id
                        ORDER BY t.travel_date ASC");

include 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<section class="panel">
    <div class="panel-head"><h2>Add New Trip</h2></div>
    <form method="POST" action="manage_trips.php">
        <div class="inline-form">
            <div class="form-field">
                <label>Bus</label>
                <select name="bus_id" required>
                    <?php while ($b = $buses->fetch_assoc()): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['bus_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-field">
                <label>Bus Type</label>
                <select name="bus_type" required>
                    <?php foreach (['Regular','Express','Sleeper','Luxury'] as $type): ?>
                        <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label>Origin</label>
                <input type="text" name="origin" required>
            </div>
            <div class="form-field">
                <label>Destination</label>
                <input type="text" name="destination" required>
            </div>
            <div class="form-field">
                <label>Travel Date</label>
                <input type="date" name="travel_date" required>
            </div>
            <div class="form-field">
                <label>Departure</label>
                <input type="time" name="departure_time" required>
            </div>
            <div class="form-field">
                <label>Arrival</label>
                <input type="time" name="arrival_time" required>
            </div>
            <div class="form-field">
                <label>Fare (₱)</label>
                <input type="number" step="0.01" name="fare" required>
            </div>
            <div class="form-field">
                <label>Seats</label>
                <input type="number" name="available_seats" value="45" required>
            </div>
        </div>

        <label>Amenities</label>
        <div class="payment-options" style="flex-direction:row;flex-wrap:wrap;gap:16px;">
            <?php foreach ($allAmenities as $a): ?>
                <label class="checkbox-label"><input type="checkbox" name="amenities[]" value="<?php echo $a; ?>"> <?php echo $a; ?></label>
            <?php endforeach; ?>
        </div>

        <div class="inline-form" style="margin-top:14px;">
            <div class="form-field">
                <label>Pickup Points (comma-separated)</label>
                <input type="text" name="pickup_points" placeholder="e.g. Cubao Terminal, Pasay Terminal" required>
            </div>
            <div class="form-field">
                <label>Drop-off Points (comma-separated)</label>
                <input type="text" name="dropoff_points" placeholder="e.g. Baguio Terminal" required>
            </div>
        </div>

        <div class="inline-form" style="margin-top:14px;">
            <div class="form-field">
                <label>Origin Terminal (for map on e-ticket)</label>
                <select name="origin_terminal_id">
                    <option value="">None</option>
                    <?php foreach ($terminalOptions as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label>Destination Terminal</label>
                <select name="destination_terminal_id">
                    <option value="">None</option>
                    <?php foreach ($terminalOptions as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button type="submit" name="add_trip" class="btn-primary" style="margin-top:16px;">Add Trip</button>
    </form>
</section>

<section class="panel">
    <div class="panel-head"><h2>All Trips</h2></div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Bus</th>
                <th>Type</th>
                <th>Route</th>
                <th>Date</th>
                <th>Departure</th>
                <th>Fare</th>
                <th>Seats Left</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($trips->num_rows === 0): ?>
                <tr><td colspan="8" class="empty-row">No trips added yet.</td></tr>
            <?php else: while ($t = $trips->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($t['bus_name']); ?></td>
                    <td><?php echo htmlspecialchars($t['bus_type']); ?></td>
                    <td><?php echo htmlspecialchars($t['origin'] . ' → ' . $t['destination']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($t['travel_date'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($t['departure_time'])); ?></td>
                    <td>₱<?php echo number_format($t['fare'], 2); ?></td>
                    <td><?php echo $t['available_seats']; ?></td>
                    <td>
                        <form method="POST" action="manage_trips.php" onsubmit="return confirm('Remove this trip?');">
                            <input type="hidden" name="trip_id" value="<?php echo $t['id']; ?>">
                            <button type="submit" name="delete_trip" class="btn-danger btn-small">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>
</section>

<?php include 'includes/footer.php'; ?>
