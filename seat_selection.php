<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = "Select Seat";
$tripId = (int) ($_GET['trip_id'] ?? 0);

$stmt = $conn->prepare("SELECT t.*, b.bus_name, b.operator, b.seat_capacity, b.seat_layout, b.decks
                         FROM trips t JOIN buses b ON t.bus_id = b.id WHERE t.id = ?");
$stmt->bind_param("i", $tripId);
$stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();

if (!$trip) {
    header("Location: reservation.php");
    exit();
}

$takenSeats = takenSeatsForTrip($conn, $tripId);

$pickupPoints = array_filter(array_map('trim', explode(',', $trip['pickup_points'])));
$dropoffPoints = array_filter(array_map('trim', explode(',', $trip['dropoff_points'])));

include 'includes/header.php';
?>

<section class="panel">
    <div class="panel-head">
        <h2><?php echo htmlspecialchars($trip['origin'] . ' → ' . $trip['destination']); ?></h2>
        <span class="badge badge-confirmed"><?php echo htmlspecialchars($trip['bus_type']); ?><?php echo $trip['decks'] === 'Double' ? ' · Double Deck' : ''; ?></span>
    </div>
    <p class="muted"><?php echo htmlspecialchars($trip['bus_name']); ?> · <?php echo htmlspecialchars($trip['operator']); ?> ·
        <?php echo date('M d, Y', strtotime($trip['travel_date'])); ?> ·
        <?php echo date('h:i A', strtotime($trip['departure_time'])); ?></p>
</section>

<div class="seat-select-layout">
    <section class="panel">
        <div class="panel-head"><h2>Choose Your Seat</h2></div>
        <div class="seat-legend">
            <span><span class="seat-swatch available"></span> Available</span>
            <span><span class="seat-swatch selected"></span> Selected</span>
            <span><span class="seat-swatch taken"></span> Taken</span>
            <span><span class="seat-swatch window"></span> Window seat</span>
        </div>
        <?php renderSeatMap($trip['seat_layout'], $trip['decks'], (int) $trip['seat_capacity'], $takenSeats); ?>
    </section>

    <section class="panel seat-summary-panel">
        <div class="panel-head"><h2>Trip Details</h2></div>
        <form method="POST" action="checkout.php" id="seatForm">
            <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
            <input type="hidden" name="seat_number" id="selectedSeatInput" required>

            <div class="selected-seat-display">
                <span>Selected Seat:</span>
                <strong id="selectedSeatLabel">None</strong>
            </div>

            <label>Pickup Point</label>
            <select name="pickup_point" required>
                <?php foreach ($pickupPoints as $p): ?>
                    <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                <?php endforeach; ?>
            </select>

            <label>Drop-off Point</label>
            <select name="dropoff_point" required>
                <?php foreach ($dropoffPoints as $p): ?>
                    <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="fare-line">
                <span>Base Fare</span>
                <span>₱<?php echo number_format($trip['fare'], 2); ?></span>
            </div>

            <button type="submit" class="btn-primary" style="width:100%;margin-top:18px;" id="continueBtn" disabled>Continue to Checkout</button>
        </form>
    </section>
</div>

<script>
document.querySelectorAll('.seat-btn.available').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.seat-btn.selected').forEach(b => b.classList.remove('selected'));
        this.classList.add('selected');
        document.getElementById('selectedSeatInput').value = this.dataset.seat;
        document.getElementById('selectedSeatLabel').textContent = this.dataset.label;
        document.getElementById('continueBtn').disabled = false;
    });
});
</script>

<?php include 'includes/footer.php'; ?>
