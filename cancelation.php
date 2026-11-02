<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = "Cancelation";
$userId = $_SESSION['user_id'];
$message = "";
$messageType = "";

// ---------- HANDLE CANCELLATION ----------
if (isset($_POST['cancel_reservation'])) {
    $reservationId = (int) $_POST['reservation_id'];
    $reason = trim($_POST['reason']);

    $stmt = $conn->prepare("SELECT r.trip_id, r.pnr, r.amount, r.status, t.travel_date, t.departure_time
                             FROM reservations r JOIN trips t ON r.trip_id = t.id
                             WHERE r.id = ? AND r.user_id = ?");
    $stmt->bind_param("ii", $reservationId, $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if (!$res) {
        $message = "Reservation not found.";
        $messageType = "error";
    } elseif ($res['status'] === 'Cancelled') {
        $message = "This reservation is already cancelled.";
        $messageType = "error";
    } else {
        $departureDateTime = strtotime($res['travel_date'] . ' ' . $res['departure_time']);
        $hoursBefore = ($departureDateTime - time()) / 3600;
        $refundPercent = calculateRefundPercent($hoursBefore);
        $refund = round($res['amount'] * ($refundPercent / 100), 2);

        $conn->begin_transaction();
        try {
            $update = $conn->prepare("UPDATE reservations SET status = 'Cancelled' WHERE id = ?");
            $update->bind_param("i", $reservationId);
            $update->execute();

            $cancel = $conn->prepare("INSERT INTO cancellations (reservation_id, reason, hours_before_departure, refund_percent, refund_amount) VALUES (?, ?, ?, ?, ?)");
            $hoursBeforeRounded = round(max($hoursBefore, 0), 1);
            $cancel->bind_param("isdid", $reservationId, $reason, $hoursBeforeRounded, $refundPercent, $refund);
            $cancel->execute();

            $seatUpdate = $conn->prepare("UPDATE trips SET available_seats = available_seats + 1 WHERE id = ?");
            $seatUpdate->bind_param("i", $res['trip_id']);
            $seatUpdate->execute();

            $payUpdate = $conn->prepare("UPDATE payments SET payment_status = 'Refunded' WHERE reservation_id = ?");
            $payUpdate->bind_param("i", $reservationId);
            $payUpdate->execute();

            $conn->commit();
            logActivity($conn, 'BOOKING_CANCELLED', sprintf('PNR %s · refund %d%% (₱%s)%s',
                $res['pnr'], $refundPercent, number_format($refund, 2), $reason !== '' ? ' · reason: ' . $reason : ''));
            if ($refundPercent > 0) {
                $message = "Reservation cancelled. Since it's " . round($hoursBefore, 1) . " hours before departure, you're eligible for a $refundPercent% refund: ₱" . number_format($refund, 2) . ".";
            } else {
                $message = "Reservation cancelled. Since departure is less than 6 hours away (or has passed), no refund applies.";
            }
            $messageType = "success";
        } catch (Throwable $e) {
            $conn->rollback();
            $message = "Cancellation failed. Please try again.";
            $messageType = "error";
        }
    }
}

// ---------- ACTIVE RESERVATIONS ----------
$reservations = $conn->query("SELECT r.id, r.pnr, t.origin, t.destination, t.travel_date, t.departure_time, r.seat_number, r.amount, r.status
                               FROM reservations r JOIN trips t ON r.trip_id = t.id
                               WHERE r.user_id = $userId
                               ORDER BY r.reservation_date DESC");

include 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<section class="panel">
    <div class="panel-head"><h2>Refund Policy</h2></div>
    <div class="refund-policy-grid">
        <div class="refund-tier"><strong>48+ hrs</strong> before departure<span>90% refund</span></div>
        <div class="refund-tier"><strong>24–48 hrs</strong> before departure<span>70% refund</span></div>
        <div class="refund-tier"><strong>6–24 hrs</strong> before departure<span>40% refund</span></div>
        <div class="refund-tier"><strong>&lt; 6 hrs</strong> before departure<span>No refund</span></div>
    </div>
</section>

<section class="panel">
    <div class="panel-head"><h2>My Reservations</h2></div>
    <table class="data-table">
        <thead>
            <tr>
                <th>PNR</th>
                <th>Route</th>
                <th>Travel Date</th>
                <th>Seat</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($reservations->num_rows === 0): ?>
                <tr><td colspan="7" class="empty-row">No reservations found.</td></tr>
            <?php else: while ($r = $reservations->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['pnr']); ?></td>
                    <td><?php echo htmlspecialchars($r['origin'] . ' → ' . $r['destination']); ?></td>
                    <td><?php echo date('M d, Y h:i A', strtotime($r['travel_date'] . ' ' . $r['departure_time'])); ?></td>
                    <td>#<?php echo $r['seat_number']; ?></td>
                    <td>₱<?php echo number_format($r['amount'], 2); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($r['status']); ?>"><?php echo $r['status']; ?></span></td>
                    <td>
                        <?php if ($r['status'] === 'Confirmed'): ?>
                            <button type="button" class="btn-danger btn-small cancel-btn" data-id="<?php echo $r['id']; ?>">Cancel</button>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>
</section>

<!-- Cancellation Modal -->
<div class="modal-overlay" id="cancelModal">
    <div class="modal-box">
        <h2>Cancel Reservation</h2>
        <p class="modal-subtitle">Refund amount depends on how many hours remain before departure (see policy above).</p>
        <form method="POST" action="cancelation.php">
            <input type="hidden" name="reservation_id" id="cancelReservationId">

            <label>Reason for Cancellation</label>
            <textarea name="reason" rows="3" placeholder="Optional"></textarea>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="closeCancelModalBtn">Back</button>
                <button type="submit" name="cancel_reservation" class="btn-danger">Confirm Cancellation</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
