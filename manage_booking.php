<?php
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = "Manage Booking";
$booking = null;
$errorMsg = "";
$successMsg = "";

function findBooking($conn, $pnr, $phone) {
    $stmt = $conn->prepare("SELECT r.*, t.origin, t.destination, t.travel_date, t.departure_time, t.arrival_time, t.id AS trip_id,
                                   b.bus_name, b.seat_capacity, b.seat_layout, b.decks
                             FROM reservations r
                             JOIN trips t ON r.trip_id = t.id
                             JOIN buses b ON t.bus_id = b.id
                             WHERE r.pnr = ? AND r.passenger_phone = ?");
    $stmt->bind_param("ss", $pnr, $phone);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Manage Booking needs no login, so changes are recorded under the booking's owner (or the passenger as a guest)
function bookingActor($conn, $booking) {
    if (!empty($booking['user_id'])) {
        $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $booking['user_id']);
        $stmt->execute();
        $owner = $stmt->get_result()->fetch_assoc();
        if ($owner) return $owner;
    }
    return ['id' => null, 'username' => $booking['passenger_name'] . ' (guest)', 'role' => 'guest'];
}

function isModifiable($booking) {
    return $booking['status'] === 'Confirmed'
        && strtotime($booking['travel_date'] . ' ' . $booking['departure_time']) > time();
}

$pnr = trim($_POST['pnr'] ?? '');
$phone = trim($_POST['phone'] ?? '');

// ---------- LOOKUP ----------
if (isset($_POST['lookup'])) {
    $booking = findBooking($conn, $pnr, $phone);
    if (!$booking) {
        $errorMsg = "No booking found with that PNR and phone number. Please double-check and try again.";
    }
}

// ---------- MODIFY TRAVEL DATE ----------
if (isset($_POST['change_date'])) {
    $booking = findBooking($conn, $pnr, $phone);
    $newTripId = (int) $_POST['new_trip_id'];

    if (!$booking || !isModifiable($booking)) {
        $errorMsg = "This booking can no longer be modified.";
    } else {
        $tripStmt = $conn->prepare("SELECT t.id, b.seat_capacity FROM trips t JOIN buses b ON t.bus_id = b.id
                                    WHERE t.id = ? AND t.origin = ? AND t.destination = ? AND t.travel_date >= CURDATE() AND t.available_seats > 0");
        $tripStmt->bind_param("iss", $newTripId, $booking['origin'], $booking['destination']);
        $tripStmt->execute();
        $newTrip = $tripStmt->get_result()->fetch_assoc();

        if (!$newTrip || $newTrip['id'] == $booking['trip_id']) {
            $errorMsg = "Please choose a valid trip on the same route.";
        } else {
            // Keep the same seat number if it's free on the new trip, otherwise take the first free seat
            $taken = takenSeatsForTrip($conn, $newTrip['id']);
            $seat = (string) $booking['seat_number'];
            $seatKept = (int) $seat <= $newTrip['seat_capacity'] && !in_array($seat, $taken, true);
            if (!$seatKept) {
                for ($s = 1; $s <= $newTrip['seat_capacity']; $s++) {
                    if (!in_array((string) $s, $taken, true)) { $seat = (string) $s; break; }
                }
            }

            $conn->begin_transaction();
            $update = $conn->prepare("UPDATE reservations SET trip_id = ?, seat_number = ? WHERE id = ?");
            $update->bind_param("isi", $newTrip['id'], $seat, $booking['id']);
            $update->execute();
            $release = $conn->prepare("UPDATE trips SET available_seats = available_seats + 1 WHERE id = ?");
            $release->bind_param("i", $booking['trip_id']);
            $release->execute();
            $claim = $conn->prepare("UPDATE trips SET available_seats = available_seats - 1 WHERE id = ?");
            $claim->bind_param("i", $newTrip['id']);
            $claim->execute();
            $conn->commit();

            $successMsg = $seatKept
                ? "Your travel date has been updated successfully. You kept Seat #$seat."
                : "Your travel date has been updated. Your previous seat was taken on that trip, so you've been moved to Seat #$seat — you can pick a different seat below.";
            $oldDate = date('M d, Y', strtotime($booking['travel_date']));
            $booking = findBooking($conn, $pnr, $phone);
            logActivity($conn, 'DATE_CHANGED', sprintf('PNR %s · %s → %s · Seat %s',
                $booking['pnr'], $oldDate, date('M d, Y', strtotime($booking['travel_date'])), $seat), bookingActor($conn, $booking));
        }
    }
}

// ---------- CHANGE SEAT ----------
if (isset($_POST['change_seat'])) {
    $booking = findBooking($conn, $pnr, $phone);
    $newSeat = (string) (int) ($_POST['new_seat'] ?? 0);

    if (!$booking || !isModifiable($booking)) {
        $errorMsg = "This booking can no longer be modified.";
    } elseif ($newSeat === (string) $booking['seat_number']) {
        $errorMsg = "That's already your current seat.";
    } elseif ((int) $newSeat < 1 || (int) $newSeat > $booking['seat_capacity']
              || in_array($newSeat, takenSeatsForTrip($conn, $booking['trip_id']), true)) {
        $errorMsg = "Sorry, Seat #$newSeat is not available. Please choose another seat.";
    } else {
        $update = $conn->prepare("UPDATE reservations SET seat_number = ? WHERE id = ?");
        $update->bind_param("si", $newSeat, $booking['id']);
        $update->execute();
        $successMsg = "Your seat has been changed to " . seatLabel($newSeat, $booking['seat_layout'], $booking['decks'], (int) $booking['seat_capacity']) . ".";
        logActivity($conn, 'SEAT_CHANGED', sprintf('PNR %s · Seat %s → Seat %s', $booking['pnr'], $booking['seat_number'], $newSeat),
                    bookingActor($conn, $booking));
        $booking = findBooking($conn, $pnr, $phone);
    }
}

// Alternative trips on the same route (for date change) and seat map data (for seat change)
$alternativeTrips = [];
$takenSeats = [];
$canModify = $booking && isModifiable($booking);
if ($canModify) {
    $altStmt = $conn->prepare("SELECT id, travel_date, departure_time, available_seats FROM trips
                                WHERE origin = ? AND destination = ? AND travel_date >= CURDATE() AND id != ? AND available_seats > 0
                                ORDER BY travel_date ASC");
    $altStmt->bind_param("ssi", $booking['origin'], $booking['destination'], $booking['trip_id']);
    $altStmt->execute();
    $alternativeTrips = $altStmt->get_result();
    $takenSeats = takenSeatsForTrip($conn, $booking['trip_id']);
}

include 'includes/public_header.php';
?>

<section class="page-header">
    <h1>Manage Your Booking</h1>
    <p>Look up your reservation using your PNR and phone number.</p>
</section>

<section class="section narrow">
    <?php if ($errorMsg): ?><div class="alert alert-error"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>
    <?php if ($successMsg): ?><div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>

    <?php if (!$booking): ?>
        <form method="POST" action="manage_booking.php" class="panel">
            <label>PNR (Booking Reference)</label>
            <input type="text" name="pnr" placeholder="e.g. A1B2C3D4" required>

            <label>Phone Number</label>
            <input type="text" name="phone" placeholder="The phone number used when booking" required>

            <button type="submit" name="lookup" class="btn-primary" style="width:100%;margin-top:18px;">Find My Booking</button>
        </form>
    <?php else: ?>
        <div class="panel">
            <div class="panel-head"><h2>Booking Found</h2><span class="badge badge-<?php echo strtolower($booking['status']); ?>"><?php echo $booking['status']; ?></span></div>
            <p><strong>PNR:</strong> <?php echo htmlspecialchars($booking['pnr']); ?></p>
            <p><strong>Passenger:</strong> <?php echo htmlspecialchars($booking['passenger_name']); ?></p>
            <p><strong>Route:</strong> <?php echo htmlspecialchars($booking['origin'] . ' → ' . $booking['destination']); ?></p>
            <p><strong>Travel Date:</strong> <?php echo date('M d, Y', strtotime($booking['travel_date'])); ?> at <?php echo date('h:i A', strtotime($booking['departure_time'])); ?></p>
            <p><strong>Seat:</strong> <?php echo htmlspecialchars(seatLabel($booking['seat_number'], $booking['seat_layout'], $booking['decks'], (int) $booking['seat_capacity'])); ?></p>
            <p><strong>Amount Paid:</strong> ₱<?php echo number_format($booking['amount'], 2); ?></p>

            <div class="hero-actions" style="justify-content:flex-start;margin-top:20px;">
                <a href="print_ticket.php?pnr=<?php echo urlencode($booking['pnr']); ?>&phone=<?php echo urlencode($booking['passenger_phone']); ?>" class="btn-primary btn-small">Resend / View E-Ticket</a>
                <a href="manage_booking.php" class="btn-secondary btn-small">Search Another Booking</a>
            </div>
        </div>

        <?php if ($canModify): ?>
        <div class="panel" style="margin-top:16px;">
            <div class="panel-head"><h2>Change Seat</h2></div>
            <p class="muted" style="margin-bottom:14px;">Your current seat has a dashed outline. Pick any available seat, then confirm.</p>
            <div class="seat-legend">
                <span><span class="seat-swatch available"></span> Available</span>
                <span><span class="seat-swatch selected"></span> Selected</span>
                <span><span class="seat-swatch taken"></span> Taken</span>
                <span><span class="seat-swatch window"></span> Window seat</span>
            </div>
            <?php renderSeatMap($booking['seat_layout'], $booking['decks'], (int) $booking['seat_capacity'], $takenSeats, $booking['seat_number']); ?>

            <form method="POST" action="manage_booking.php" style="margin-top:16px;">
                <input type="hidden" name="pnr" value="<?php echo htmlspecialchars($booking['pnr']); ?>">
                <input type="hidden" name="phone" value="<?php echo htmlspecialchars($booking['passenger_phone']); ?>">
                <input type="hidden" name="new_seat" id="newSeatInput">
                <div class="selected-seat-display">
                    <span>New Seat:</span>
                    <strong id="newSeatLabel">None</strong>
                </div>
                <button type="submit" name="change_seat" class="btn-primary" style="width:100%;margin-top:12px;" id="changeSeatBtn" disabled>Confirm Seat Change</button>
            </form>
        </div>

        <?php if ($alternativeTrips->num_rows > 0): ?>
        <div class="panel" style="margin-top:16px;">
            <div class="panel-head"><h2>Modify Travel Date</h2></div>
            <form method="POST" action="manage_booking.php">
                <input type="hidden" name="pnr" value="<?php echo htmlspecialchars($booking['pnr']); ?>">
                <input type="hidden" name="phone" value="<?php echo htmlspecialchars($booking['passenger_phone']); ?>">

                <label>Choose a new available date for this route</label>
                <select name="new_trip_id" required>
                    <?php while ($alt = $alternativeTrips->fetch_assoc()): ?>
                        <option value="<?php echo $alt['id']; ?>">
                            <?php echo date('M d, Y', strtotime($alt['travel_date'])); ?> at <?php echo date('h:i A', strtotime($alt['departure_time'])); ?>
                            (<?php echo $alt['available_seats']; ?> seats left)
                        </option>
                    <?php endwhile; ?>
                </select>

                <button type="submit" name="change_date" class="btn-primary" style="width:100%;margin-top:18px;">Update Travel Date</button>
            </form>
        </div>
        <?php endif; ?>

        <script>
        document.querySelectorAll('.seat-btn.available:not(.current)').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.seat-btn.selected').forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('newSeatInput').value = this.dataset.seat;
                document.getElementById('newSeatLabel').textContent = this.dataset.label;
                document.getElementById('changeSeatBtn').disabled = false;
            });
        });
        </script>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php include 'includes/public_footer.php'; ?>
