<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = "Checkout";

// ---------- Step 1: Arrive from seat_selection.php (POST) ----------
if (isset($_POST['trip_id']) && !isset($_POST['confirm_payment'])) {
    $tripId = (int) $_POST['trip_id'];
    $seatNumber = $_POST['seat_number'];
    $pickup = $_POST['pickup_point'];
    $dropoff = $_POST['dropoff_point'];

    $_SESSION['checkout'] = [
        'trip_id' => $tripId,
        'seat_number' => $seatNumber,
        'pickup_point' => $pickup,
        'dropoff_point' => $dropoff,
    ];
}

if (!isset($_SESSION['checkout'])) {
    header("Location: reservation.php");
    exit();
}

$checkoutData = $_SESSION['checkout'];
$tripId = $checkoutData['trip_id'];

$stmt = $conn->prepare("SELECT t.*, b.bus_name, b.operator, b.seat_capacity, b.seat_layout, b.decks FROM trips t JOIN buses b ON t.bus_id = b.id WHERE t.id = ?");
$stmt->bind_param("i", $tripId);
$stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();

if (!$trip) {
    unset($_SESSION['checkout']);
    header("Location: reservation.php");
    exit();
}

$LUGGAGE_FEE = LUGGAGE_FEE;
$INSURANCE_FEE = INSURANCE_FEE;
$errorMsg = "";
$promoDiscount = 0;
$promoApplied = "";

// ---------- Step 2: Confirm & Pay ----------
if (isset($_POST['confirm_payment'])) {
    $passengerName = trim($_POST['passenger_name']);
    $idNumber = trim($_POST['id_number']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $wantsLuggage = isset($_POST['add_luggage']);
    $wantsInsurance = isset($_POST['add_insurance']);
    $promoCode = trim($_POST['promo_code']);
    $paymentMethod = in_array($_POST['payment_method'] ?? '', ['GCash', 'Card', 'Cash'], true) ? $_POST['payment_method'] : 'GCash';

    $luggageFee = $wantsLuggage ? $LUGGAGE_FEE : 0;
    $insuranceFee = $wantsInsurance ? $INSURANCE_FEE : 0;
    $discount = 0;

    if ($promoCode !== '') {
        $promoStmt = $conn->prepare("SELECT discount_type, discount_value FROM promo_codes WHERE code = ? AND active = 1");
        $promoStmt->bind_param("s", $promoCode);
        $promoStmt->execute();
        $promoResult = $promoStmt->get_result();
        if ($promoResult->num_rows > 0) {
            $promo = $promoResult->fetch_assoc();
            $discount = $promo['discount_type'] === 'percent'
                ? round($trip['fare'] * ($promo['discount_value'] / 100), 2)
                : $promo['discount_value'];
        } else {
            $errorMsg = "Invalid or expired promo code.";
        }
    }

    if ($errorMsg === '') {
        $totalAmount = $trip['fare'] + $luggageFee + $insuranceFee - $discount;
        $totalAmount = max($totalAmount, 0);
        $pnr = generatePNR($conn);
        $userId = $_SESSION['user_id'];

        $conn->begin_transaction();
        try {
            $insert = $conn->prepare("INSERT INTO reservations
                (pnr, user_id, trip_id, passenger_name, passenger_id_number, passenger_phone, passenger_email,
                 seat_number, pickup_point, dropoff_point, base_fare, luggage_fee, insurance_fee, promo_code, discount_amount, amount)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param(
                "siisssssssdddsdd",
                $pnr, $userId, $tripId, $passengerName, $idNumber, $phone, $email,
                $checkoutData['seat_number'], $checkoutData['pickup_point'], $checkoutData['dropoff_point'],
                $trip['fare'], $luggageFee, $insuranceFee, $promoCode, $discount, $totalAmount
            );
            $insert->execute();
            $reservationId = $conn->insert_id;

            $update = $conn->prepare("UPDATE trips SET available_seats = available_seats - 1 WHERE id = ?");
            $update->bind_param("i", $tripId);
            $update->execute();

            $payment = $conn->prepare("INSERT INTO payments (reservation_id, amount, payment_method, payment_status) VALUES (?, ?, ?, 'Paid')");
            $payment->bind_param("ids", $reservationId, $totalAmount, $paymentMethod);
            $payment->execute();

            $conn->commit();
            unset($_SESSION['checkout']);
            logActivity($conn, 'BOOKING_CREATED', sprintf('PNR %s · %s → %s · %s · Seat %s · ₱%s via %s',
                $pnr, $trip['origin'], $trip['destination'], date('M d, Y', strtotime($trip['travel_date'])),
                $checkoutData['seat_number'], number_format($totalAmount, 2), $paymentMethod));
            header("Location: confirmation.php?pnr=" . urlencode($pnr));
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            $errorMsg = "Payment could not be processed. Please try again.";
        }
    }
}

include 'includes/header.php';
?>

<?php if ($errorMsg): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
<?php endif; ?>

<div class="checkout-layout">
    <section class="panel">
        <div class="panel-head"><h2>Passenger Details</h2></div>
        <form method="POST" action="checkout.php" id="checkoutForm">
            <label>Full Name</label>
            <input type="text" name="passenger_name" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" required>

            <label>Valid ID Number</label>
            <input type="text" name="id_number" placeholder="e.g. Driver's License, UMID, School ID" required>

            <label>Phone Number</label>
            <input type="text" name="phone" placeholder="09XX-XXX-XXXX" required>

            <label>Email Address</label>
            <input type="email" name="email" placeholder="For e-ticket delivery" required>

            <h3 class="checkout-subhead">Add-ons</h3>
            <label class="checkbox-label" style="margin: 10px 0;">
                <input type="checkbox" name="add_luggage"> Extra Luggage (+₱<?php echo number_format($LUGGAGE_FEE, 2); ?>)
            </label>
            <label class="checkbox-label" style="margin: 10px 0;">
                <input type="checkbox" name="add_insurance"> Trip Insurance (+₱<?php echo number_format($INSURANCE_FEE, 2); ?>)
            </label>

            <label>Promo Code</label>
            <input type="text" name="promo_code" placeholder="Optional (e.g. WELCOME10)">

            <div class="payment-method-box">
                <h3 class="checkout-subhead">Payment Method</h3>
                <p class="muted" style="font-size:13px;">This is a simulated secure payment gateway for demo purposes.</p>
                <div class="payment-options">
                    <label class="checkbox-label"><input type="radio" name="payment_method" value="GCash" checked> GCash</label>
                    <label class="checkbox-label"><input type="radio" name="payment_method" value="Card"> Credit/Debit Card</label>
                    <label class="checkbox-label"><input type="radio" name="payment_method" value="Cash"> Cash on Terminal</label>
                </div>
            </div>

            <button type="submit" name="confirm_payment" class="btn-primary" style="width:100%;margin-top:20px;">Pay & Confirm Booking</button>
        </form>
    </section>

    <section class="panel order-summary-panel">
        <div class="panel-head"><h2>Order Summary</h2></div>
        <div class="summary-trip">
            <p class="summary-route"><?php echo htmlspecialchars($trip['origin'] . ' → ' . $trip['destination']); ?></p>
            <p class="muted"><?php echo date('M d, Y', strtotime($trip['travel_date'])); ?> · <?php echo date('h:i A', strtotime($trip['departure_time'])); ?></p>
            <p class="muted"><?php echo htmlspecialchars(seatLabel($checkoutData['seat_number'], $trip['seat_layout'], $trip['decks'], (int) $trip['seat_capacity'])); ?></p>
            <p class="muted">Pickup: <?php echo htmlspecialchars($checkoutData['pickup_point']); ?></p>
        </div>

        <div class="fare-line"><span>Base Fare</span><span>₱<?php echo number_format($trip['fare'], 2); ?></span></div>
        <div class="fare-line"><span>Luggage Add-on</span><span id="summaryLuggage">₱0.00</span></div>
        <div class="fare-line"><span>Trip Insurance</span><span id="summaryInsurance">₱0.00</span></div>
        <div class="fare-line fare-total"><span>Total</span><span id="summaryTotal">₱<?php echo number_format($trip['fare'], 2); ?></span></div>
    </section>
</div>

<script>
(function() {
    const baseFare = <?php echo (float) $trip['fare']; ?>;
    const luggageFee = <?php echo $LUGGAGE_FEE; ?>;
    const insuranceFee = <?php echo $INSURANCE_FEE; ?>;
    const luggageCheckbox = document.querySelector('[name=add_luggage]');
    const insuranceCheckbox = document.querySelector('[name=add_insurance]');

    function updateSummary() {
        const lug = luggageCheckbox.checked ? luggageFee : 0;
        const ins = insuranceCheckbox.checked ? insuranceFee : 0;
        document.getElementById('summaryLuggage').textContent = '₱' + lug.toFixed(2);
        document.getElementById('summaryInsurance').textContent = '₱' + ins.toFixed(2);
        document.getElementById('summaryTotal').textContent = '₱' + (baseFare + lug + ins).toFixed(2);
    }
    luggageCheckbox.addEventListener('change', updateSummary);
    insuranceCheckbox.addEventListener('change', updateSummary);
})();
</script>

<?php include 'includes/footer.php'; ?>
