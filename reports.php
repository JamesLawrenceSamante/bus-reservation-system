<?php
require_once 'includes/auth.php';
requireAdmin();
require_once 'includes/db.php';

$pageTitle = "Reports";

// ---------- DATE RANGE ----------
// Empty "from"/"to" means no limit on that side (All Time).
function validDate($d) {
    return $d !== '' && DateTime::createFromFormat('Y-m-d', $d) !== false;
}
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
if (!validDate($from)) $from = '';
if (!validDate($to)) $to = '';
if ($from !== '' && $to !== '' && $from > $to) [$from, $to] = [$to, $from];

$rangeStart = ($from !== '' ? $from : '1000-01-01') . ' 00:00:00';
$rangeEnd = date('Y-m-d', strtotime(($to !== '' ? $to : '9998-12-31') . ' +1 day')) . ' 00:00:00';
$dateStart = $from !== '' ? $from : '1000-01-01';
$dateEnd = $to !== '' ? $to : '9999-12-31';

$rangeLabel = ($from === '' && $to === '') ? 'All Time'
    : (($from !== '' ? date('M d, Y', strtotime($from)) : 'Beginning') . ' – ' . ($to !== '' ? date('M d, Y', strtotime($to)) : 'Today'));

// Runs a prepared query bound to the date range and returns all rows
function fetchRows($conn, $sql, $start, $end) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ---------- 1. SALES SUMMARY ----------
$bookingStats = fetchRows($conn, "SELECT COUNT(*) AS bookings, COALESCE(SUM(status = 'Cancelled'), 0) AS cancelled
                                  FROM reservations WHERE reservation_date >= ? AND reservation_date < ?", $rangeStart, $rangeEnd)[0];
$salesStats = fetchRows($conn, "SELECT COUNT(*) AS transactions, COALESCE(SUM(amount), 0) AS gross
                                FROM payments WHERE payment_date >= ? AND payment_date < ?", $rangeStart, $rangeEnd)[0];
$refundStats = fetchRows($conn, "SELECT COUNT(*) AS refunds, COALESCE(SUM(refund_amount), 0) AS refunded
                                 FROM cancellations WHERE cancel_date >= ? AND cancel_date < ?", $rangeStart, $rangeEnd)[0];
$grossSales = (float) $salesStats['gross'];
$totalRefunds = (float) $refundStats['refunded'];
$netRevenue = $grossSales - $totalRefunds;
$averageTicket = $salesStats['transactions'] > 0 ? $grossSales / $salesStats['transactions'] : 0;

// ---------- 2. SALES BY ROUTE ----------
$byRoute = fetchRows($conn, "SELECT t.origin, t.destination, COUNT(*) AS bookings,
                                    SUM(r.status = 'Cancelled') AS cancelled,
                                    SUM(r.amount) AS gross, COALESCE(SUM(c.refund_amount), 0) AS refunds
                             FROM reservations r
                             JOIN trips t ON r.trip_id = t.id
                             LEFT JOIN cancellations c ON c.reservation_id = r.id
                             WHERE r.reservation_date >= ? AND r.reservation_date < ?
                             GROUP BY t.origin, t.destination
                             ORDER BY (SUM(r.amount) - COALESCE(SUM(c.refund_amount), 0)) DESC", $rangeStart, $rangeEnd);

// ---------- 3. TRIP OCCUPANCY ----------
// Seats offered = seats still available + confirmed seats sold
$occupancy = fetchRows($conn, "SELECT t.id, b.bus_name, t.origin, t.destination, t.travel_date, t.departure_time, t.available_seats,
                                      (SELECT COUNT(*) FROM reservations r WHERE r.trip_id = t.id AND r.status = 'Confirmed') AS sold
                               FROM trips t JOIN buses b ON t.bus_id = b.id
                               WHERE t.travel_date >= ? AND t.travel_date <= ?
                               ORDER BY t.travel_date ASC, t.departure_time ASC", $dateStart, $dateEnd);

// ---------- 4. PAYMENT METHODS ----------
$byMethod = fetchRows($conn, "SELECT payment_method, COUNT(*) AS transactions, SUM(amount) AS total
                              FROM payments WHERE payment_date >= ? AND payment_date < ?
                              GROUP BY payment_method ORDER BY total DESC", $rangeStart, $rangeEnd);

// ---------- 5. CANCELLATIONS & REFUNDS ----------
$cancellations = fetchRows($conn, "SELECT c.cancel_date, r.pnr, r.passenger_name, t.origin, t.destination, r.amount,
                                          c.hours_before_departure, c.refund_percent, c.refund_amount, c.reason
                                   FROM cancellations c
                                   JOIN reservations r ON c.reservation_id = r.id
                                   JOIN trips t ON r.trip_id = t.id
                                   WHERE c.cancel_date >= ? AND c.cancel_date < ?
                                   ORDER BY c.cancel_date DESC", $rangeStart, $rangeEnd);

// ---------- 6. BUS TYPE & OPERATOR ----------
$byBusType = fetchRows($conn, "SELECT t.bus_type, b.operator, COUNT(*) AS bookings,
                                      SUM(r.amount) - COALESCE(SUM(c.refund_amount), 0) AS net
                               FROM reservations r
                               JOIN trips t ON r.trip_id = t.id
                               JOIN buses b ON t.bus_id = b.id
                               LEFT JOIN cancellations c ON c.reservation_id = r.id
                               WHERE r.reservation_date >= ? AND r.reservation_date < ?
                               GROUP BY t.bus_type, b.operator
                               ORDER BY net DESC", $rangeStart, $rangeEnd);

// ---------- CSV EXPORT ----------
if (($_GET['export'] ?? '') === 'csv') {
    $filename = 'brs-report-' . ($from ?: 'start') . '-to-' . ($to ?: 'today') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows characters correctly

    fputcsv($out, ['Bus Reservation System - Report']);
    fputcsv($out, ['Period', $rangeLabel]);
    fputcsv($out, ['Generated', date('M d, Y h:i A')]);
    fputcsv($out, []);

    fputcsv($out, ['SALES SUMMARY']);
    fputcsv($out, ['Bookings', $bookingStats['bookings']]);
    fputcsv($out, ['Cancelled', $bookingStats['cancelled']]);
    fputcsv($out, ['Gross Sales', number_format($grossSales, 2, '.', '')]);
    fputcsv($out, ['Refunds', number_format($totalRefunds, 2, '.', '')]);
    fputcsv($out, ['Net Revenue', number_format($netRevenue, 2, '.', '')]);
    fputcsv($out, ['Average Ticket', number_format($averageTicket, 2, '.', '')]);
    fputcsv($out, []);

    fputcsv($out, ['SALES BY ROUTE']);
    fputcsv($out, ['Route', 'Bookings', 'Cancelled', 'Gross', 'Refunds', 'Net']);
    foreach ($byRoute as $r) {
        fputcsv($out, [$r['origin'] . ' - ' . $r['destination'], $r['bookings'], $r['cancelled'], $r['gross'], $r['refunds'], number_format($r['gross'] - $r['refunds'], 2, '.', '')]);
    }
    fputcsv($out, []);

    fputcsv($out, ['TRIP OCCUPANCY']);
    fputcsv($out, ['Trip #', 'Bus', 'Route', 'Travel Date', 'Departure', 'Seats Sold', 'Seats Offered', 'Occupancy %']);
    foreach ($occupancy as $o) {
        $offered = $o['sold'] + $o['available_seats'];
        fputcsv($out, [$o['id'], $o['bus_name'], $o['origin'] . ' - ' . $o['destination'], $o['travel_date'], date('h:i A', strtotime($o['departure_time'])),
                       $o['sold'], $offered, $offered > 0 ? round($o['sold'] / $offered * 100, 1) : 0]);
    }
    fputcsv($out, []);

    fputcsv($out, ['PAYMENT METHODS']);
    fputcsv($out, ['Method', 'Transactions', 'Total']);
    foreach ($byMethod as $m) fputcsv($out, [$m['payment_method'], $m['transactions'], $m['total']]);
    fputcsv($out, []);

    fputcsv($out, ['CANCELLATIONS & REFUNDS']);
    fputcsv($out, ['Date', 'PNR', 'Passenger', 'Route', 'Amount Paid', 'Hours Before Departure', 'Refund %', 'Refund Amount', 'Reason']);
    foreach ($cancellations as $c) {
        fputcsv($out, [$c['cancel_date'], $c['pnr'], $c['passenger_name'], $c['origin'] . ' - ' . $c['destination'], $c['amount'],
                       $c['hours_before_departure'], $c['refund_percent'], $c['refund_amount'], $c['reason']]);
    }
    fputcsv($out, []);

    fputcsv($out, ['BUS TYPE & OPERATOR']);
    fputcsv($out, ['Bus Type', 'Operator', 'Bookings', 'Net Revenue']);
    foreach ($byBusType as $b) fputcsv($out, [$b['bus_type'], $b['operator'], $b['bookings'], $b['net']]);

    fclose($out);
    exit();
}

$maxRouteNet = 0;
foreach ($byRoute as $r) $maxRouteNet = max($maxRouteNet, $r['gross'] - $r['refunds']);
$exportQuery = http_build_query(['from' => $from, 'to' => $to, 'export' => 'csv']);

include 'includes/header.php';
?>

<section class="panel no-print">
    <div class="panel-head"><h2>Report Period</h2></div>
    <form method="GET" action="reports.php" class="inline-form report-form">
        <div class="form-field">
            <label>From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
        </div>
        <div class="form-field">
            <label>To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
        </div>
        <button type="submit" class="btn-primary">Generate</button>
        <a href="reports.php?from=<?php echo date('Y-m-d'); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn-secondary">Today</a>
        <a href="reports.php?from=<?php echo date('Y-m-01'); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn-secondary">This Month</a>
        <a href="reports.php" class="btn-secondary">All Time</a>
    </form>
    <div class="report-actions">
        <button type="button" class="btn-secondary" onclick="window.print()">🖨️ Print / Save as PDF</button>
        <a href="reports.php?<?php echo htmlspecialchars($exportQuery); ?>" class="btn-primary">⬇️ Download CSV (Excel)</a>
    </div>
</section>

<div class="report-period">
    <strong>Bus Reservation System — Sales &amp; Operations Report</strong>
    <span>Period: <?php echo htmlspecialchars($rangeLabel); ?> · Generated <?php echo date('M d, Y h:i A'); ?></span>
</div>

<!-- 1. Sales Summary -->
<section class="stat-grid">
    <div class="stat-card">
        <span class="stat-label">Bookings</span>
        <span class="stat-value"><?php echo $bookingStats['bookings']; ?></span>
        <span class="stat-note"><?php echo $bookingStats['cancelled']; ?> cancelled</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Gross Sales</span>
        <span class="stat-value">₱<?php echo number_format($grossSales, 2); ?></span>
        <span class="stat-note"><?php echo $salesStats['transactions']; ?> payments</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Refunds</span>
        <span class="stat-value">₱<?php echo number_format($totalRefunds, 2); ?></span>
        <span class="stat-note"><?php echo $refundStats['refunds']; ?> refunded bookings</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Net Revenue</span>
        <span class="stat-value">₱<?php echo number_format($netRevenue, 2); ?></span>
        <span class="stat-note">Avg. ticket ₱<?php echo number_format($averageTicket, 2); ?></span>
    </div>
</section>

<!-- 2. Sales by Route -->
<section class="panel">
    <div class="panel-head"><h2>Sales by Route</h2></div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Route</th><th>Bookings</th><th>Cancelled</th><th>Gross</th><th>Refunds</th><th>Net</th><th class="bar-col">Share</th></tr></thead>
        <tbody>
        <?php if (!$byRoute): ?>
            <tr><td colspan="7" class="empty-row">No bookings in this period.</td></tr>
        <?php else: foreach ($byRoute as $r): $net = $r['gross'] - $r['refunds']; ?>
            <tr>
                <td><?php echo htmlspecialchars($r['origin'] . ' → ' . $r['destination']); ?></td>
                <td><?php echo $r['bookings']; ?></td>
                <td><?php echo $r['cancelled']; ?></td>
                <td>₱<?php echo number_format($r['gross'], 2); ?></td>
                <td>₱<?php echo number_format($r['refunds'], 2); ?></td>
                <td><strong>₱<?php echo number_format($net, 2); ?></strong></td>
                <td class="bar-col"><div class="report-bar"><span style="width:<?php echo $maxRouteNet > 0 ? round($net / $maxRouteNet * 100) : 0; ?>%"></span></div></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</section>

<!-- 3. Trip Occupancy -->
<section class="panel">
    <div class="panel-head"><h2>Trip Occupancy</h2><span class="muted">By travel date</span></div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Trip</th><th>Route</th><th>Travel Date</th><th>Sold / Seats</th><th class="bar-col">Occupancy</th></tr></thead>
        <tbody>
        <?php if (!$occupancy): ?>
            <tr><td colspan="5" class="empty-row">No trips scheduled in this period.</td></tr>
        <?php else: foreach ($occupancy as $o):
            $offered = $o['sold'] + $o['available_seats'];
            $pct = $offered > 0 ? round($o['sold'] / $offered * 100) : 0; ?>
            <tr>
                <td>#<?php echo $o['id']; ?> · <?php echo htmlspecialchars($o['bus_name']); ?></td>
                <td><?php echo htmlspecialchars($o['origin'] . ' → ' . $o['destination']); ?></td>
                <td><?php echo date('M d, Y', strtotime($o['travel_date'])); ?> · <?php echo date('h:i A', strtotime($o['departure_time'])); ?></td>
                <td><?php echo $o['sold']; ?> / <?php echo $offered; ?></td>
                <td class="bar-col"><div class="report-bar"><span style="width:<?php echo $pct; ?>%"></span></div><small><?php echo $pct; ?>%</small></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</section>

<div class="report-two-col">
    <!-- 4. Payment Methods -->
    <section class="panel">
        <div class="panel-head"><h2>Payment Methods</h2></div>
        <table class="data-table">
            <thead><tr><th>Method</th><th>Transactions</th><th>Total</th></tr></thead>
            <tbody>
            <?php if (!$byMethod): ?>
                <tr><td colspan="3" class="empty-row">No payments in this period.</td></tr>
            <?php else: foreach ($byMethod as $m): ?>
                <tr>
                    <td><?php echo $m['payment_method'] === 'Card' ? 'Credit/Debit Card' : ($m['payment_method'] === 'Cash' ? 'Cash on Terminal' : 'GCash'); ?></td>
                    <td><?php echo $m['transactions']; ?></td>
                    <td>₱<?php echo number_format($m['total'], 2); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>

    <!-- 6. Bus Type & Operator -->
    <section class="panel">
        <div class="panel-head"><h2>Bus Type &amp; Operator</h2></div>
        <table class="data-table">
            <thead><tr><th>Bus Type</th><th>Operator</th><th>Bookings</th><th>Net</th></tr></thead>
            <tbody>
            <?php if (!$byBusType): ?>
                <tr><td colspan="4" class="empty-row">No bookings in this period.</td></tr>
            <?php else: foreach ($byBusType as $b): ?>
                <tr>
                    <td><?php echo htmlspecialchars($b['bus_type']); ?></td>
                    <td><?php echo htmlspecialchars($b['operator']); ?></td>
                    <td><?php echo $b['bookings']; ?></td>
                    <td>₱<?php echo number_format($b['net'], 2); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>
</div>

<!-- 5. Cancellations & Refunds -->
<section class="panel">
    <div class="panel-head"><h2>Cancellations &amp; Refunds</h2></div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Date</th><th>PNR</th><th>Passenger</th><th>Route</th><th>Paid</th><th>Hrs Before</th><th>Refund</th><th>Reason</th></tr></thead>
        <tbody>
        <?php if (!$cancellations): ?>
            <tr><td colspan="8" class="empty-row">No cancellations in this period.</td></tr>
        <?php else: foreach ($cancellations as $c): ?>
            <tr>
                <td><?php echo date('M d, Y', strtotime($c['cancel_date'])); ?></td>
                <td><?php echo htmlspecialchars($c['pnr']); ?></td>
                <td><?php echo htmlspecialchars($c['passenger_name']); ?></td>
                <td><?php echo htmlspecialchars($c['origin'] . ' → ' . $c['destination']); ?></td>
                <td>₱<?php echo number_format($c['amount'], 2); ?></td>
                <td><?php echo $c['hours_before_departure']; ?></td>
                <td>₱<?php echo number_format($c['refund_amount'], 2); ?> <span class="muted">(<?php echo $c['refund_percent']; ?>%)</span></td>
                <td><?php echo htmlspecialchars($c['reason'] ?: '—'); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
