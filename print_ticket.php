<?php
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = "E-Ticket";
$pnr = $_GET['pnr'] ?? '';
$phone = $_GET['phone'] ?? '';

$stmt = $conn->prepare("SELECT r.*, t.origin, t.destination, t.travel_date, t.departure_time, t.arrival_time, t.bus_type,
                                b.bus_name, b.operator, b.seat_layout, b.decks, b.seat_capacity,
                                p.payment_method, p.payment_status, tm.name AS terminal_name, tm.address AS terminal_address,
                                tm.latitude, tm.longitude
                         FROM reservations r
                         JOIN trips t ON r.trip_id = t.id
                         JOIN buses b ON t.bus_id = b.id
                         LEFT JOIN payments p ON p.reservation_id = r.id
                         LEFT JOIN terminals tm ON t.origin_terminal_id = tm.id
                         WHERE r.pnr = ? AND r.passenger_phone = ?");
$stmt->bind_param("ss", $pnr, $phone);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    header("Location: manage_booking.php");
    exit();
}

$qrUrl = ticketQrUrl($ticket);   // full ticket details: passenger, trip, bus, seat, payment

include 'includes/public_header.php';
?>

<div class="ticket-actions no-print" style="max-width:700px;margin:20px auto 0;">
    <a href="manage_booking.php" class="btn-secondary btn-small">← Back</a>
    <button onclick="window.print()" class="btn-primary btn-small">🖨️ Download / Print PDF</button>
</div>

<div class="e-ticket" id="eTicket" style="margin:20px auto 60px;">
    <div class="e-ticket-header">
        <div>
            <div class="e-ticket-brand">🚌 Bus Reservation System</div>
            <p class="muted">E-Ticket / Boarding Pass</p>
        </div>
        <div class="e-ticket-pnr">
            <span>PNR</span>
            <strong><?php echo htmlspecialchars($ticket['pnr']); ?></strong>
        </div>
    </div>

    <div class="e-ticket-body">
        <div class="e-ticket-main">
            <div class="e-ticket-route">
                <div><span class="muted">From</span><h3><?php echo htmlspecialchars($ticket['origin']); ?></h3></div>
                <div class="e-ticket-arrow">→</div>
                <div><span class="muted">To</span><h3><?php echo htmlspecialchars($ticket['destination']); ?></h3></div>
            </div>

            <div class="e-ticket-grid">
                <div><span class="muted">Passenger</span><p><?php echo htmlspecialchars($ticket['passenger_name']); ?></p></div>
                <div><span class="muted">Seat</span><p>#<?php echo htmlspecialchars($ticket['seat_number']); ?></p></div>
                <div><span class="muted">Bus</span><p><?php echo htmlspecialchars($ticket['bus_name']); ?> (<?php echo htmlspecialchars($ticket['operator']); ?>)</p></div>
                <div><span class="muted">Travel Date</span><p><?php echo date('M d, Y', strtotime($ticket['travel_date'])); ?></p></div>
                <div><span class="muted">Departure</span><p><?php echo date('h:i A', strtotime($ticket['departure_time'])); ?></p></div>
                <div><span class="muted">Arrival</span><p><?php echo date('h:i A', strtotime($ticket['arrival_time'])); ?></p></div>
                <div><span class="muted">Pickup Point</span><p><?php echo htmlspecialchars($ticket['pickup_point']); ?></p></div>
                <div><span class="muted">Drop-off Point</span><p><?php echo htmlspecialchars($ticket['dropoff_point']); ?></p></div>
            </div>

            <div class="e-ticket-fare-breakdown">
                <div class="fare-line"><span>Base Fare</span><span>₱<?php echo number_format($ticket['base_fare'], 2); ?></span></div>
                <?php if ($ticket['luggage_fee'] > 0): ?><div class="fare-line"><span>Luggage Add-on</span><span>₱<?php echo number_format($ticket['luggage_fee'], 2); ?></span></div><?php endif; ?>
                <?php if ($ticket['insurance_fee'] > 0): ?><div class="fare-line"><span>Trip Insurance</span><span>₱<?php echo number_format($ticket['insurance_fee'], 2); ?></span></div><?php endif; ?>
                <?php if ($ticket['discount_amount'] > 0): ?><div class="fare-line"><span>Promo (<?php echo htmlspecialchars($ticket['promo_code']); ?>)</span><span>-₱<?php echo number_format($ticket['discount_amount'], 2); ?></span></div><?php endif; ?>
                <div class="fare-line fare-total"><span>Total Paid</span><span>₱<?php echo number_format($ticket['amount'], 2); ?></span></div>
            </div>
        </div>

        <div class="e-ticket-qr">
            <img src="<?php echo $qrUrl; ?>" alt="Boarding QR Code with full ticket details" width="200" height="200">
            <p class="muted">Scan at boarding</p>
        </div>
    </div>

    <?php if ($ticket['terminal_name']): ?>
    <div class="e-ticket-terminal no-print">
        <h3>📍 Terminal Location — <?php echo htmlspecialchars($ticket['terminal_name']); ?></h3>
        <p class="muted"><?php echo htmlspecialchars($ticket['terminal_address']); ?></p>
        <iframe width="100%" height="220" style="border:0; border-radius:10px;" loading="lazy"
            src="https://www.google.com/maps?q=<?php echo $ticket['latitude']; ?>,<?php echo $ticket['longitude']; ?>&output=embed"></iframe>
    </div>
    <?php endif; ?>

    <div class="e-ticket-footer">
        Please arrive at least 30 minutes before departure. Present a valid ID matching your booking.
    </div>
</div>

<?php include 'includes/public_footer.php'; ?>
