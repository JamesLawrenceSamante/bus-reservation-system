<?php
require_once 'includes/db.php';

$pageTitle = "Live Bus Tracking";

// Trips happening today, treated as "in transit" for simulation purposes
$trips = $conn->query("SELECT t.id, t.origin, t.destination, t.travel_date, t.departure_time, t.arrival_time, b.bus_name, b.operator
                        FROM trips t JOIN buses b ON t.bus_id = b.id
                        WHERE t.travel_date >= CURDATE()
                        ORDER BY t.travel_date ASC, t.departure_time ASC
                        LIMIT 6");

include 'includes/public_header.php';
?>

<section class="page-header">
    <h1>Live Bus Tracking</h1>
    <p>Simulated real-time trip progress and schedule status.</p>
</section>

<section class="section">
    <p class="muted" style="text-align:center;margin-bottom:24px;font-size:13px;">
        ⚠️ Note: This is a simulated tracker for demo purposes — actual GPS tracking would require live location data from each bus.
    </p>

    <div class="tracking-grid">
        <?php
        $statuses = ['On Time', 'On Time', 'Slight Delay', 'On Time', 'Boarding', 'Departed'];
        $i = 0;
        while ($t = $trips->fetch_assoc()):
            $status = $statuses[$i % count($statuses)];
            $progress = rand(5, 95);
            $statusClass = $status === 'Slight Delay' ? 'delay' : ($status === 'Boarding' ? 'boarding' : 'ontime');
            $i++;
        ?>
        <div class="tracking-card">
            <div class="tracking-head">
                <div>
                    <strong><?php echo htmlspecialchars($t['origin'] . ' → ' . $t['destination']); ?></strong>
                    <p class="muted" style="margin-top:2px;"><?php echo htmlspecialchars($t['bus_name']); ?> · <?php echo htmlspecialchars($t['operator']); ?></p>
                </div>
                <span class="tracking-status <?php echo $statusClass; ?>"><?php echo $status; ?></span>
            </div>
            <div class="tracking-progress-bar">
                <div class="tracking-progress-fill" style="width: <?php echo $progress; ?>%;"></div>
            </div>
            <div class="tracking-progress-labels">
                <span><?php echo htmlspecialchars($t['origin']); ?></span>
                <span><?php echo $progress; ?>% of route</span>
                <span><?php echo htmlspecialchars($t['destination']); ?></span>
            </div>
            <p class="muted" style="font-size:12.5px;margin-top:10px;">
                Departed <?php echo date('h:i A', strtotime($t['departure_time'])); ?> · ETA <?php echo date('h:i A', strtotime($t['arrival_time'])); ?>
            </p>
        </div>
        <?php endwhile; ?>
    </div>
</section>

<?php include 'includes/public_footer.php'; ?>
