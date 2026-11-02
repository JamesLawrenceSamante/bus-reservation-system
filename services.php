<?php
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = "Services";

// Starting fare per bus class, across all trips on record
$fareRanges = [];
$fareResult = $conn->query("SELECT bus_type, MIN(fare) AS min_fare, MAX(fare) AS max_fare
                            FROM trips GROUP BY bus_type");
while ($row = $fareResult->fetch_assoc()) $fareRanges[$row['bus_type']] = $row;

$busClasses = [
    'Regular' => ['🚌', 'Air-conditioned seating for short and budget-friendly trips.', ['AC', 'USB Charging']],
    'Express' => ['⚡', 'Fewer stops for faster arrival on popular long-distance routes.', ['AC', 'WiFi', 'USB Charging', 'Restroom']],
    'Sleeper' => ['🛏️', 'Overnight trips with extra legroom and blankets so you arrive rested.', ['AC', 'WiFi', 'Blanket', 'Restroom']],
    'Luxury'  => ['✨', 'Premium reclining seats, fewer passengers per row, and full amenities.', ['AC', 'WiFi', 'Blanket', 'Reclining Seats', 'Restroom']],
];

include 'includes/public_header.php';
?>

<section class="page-header">
    <h1>Our Services</h1>
    <p>Everything you need to plan, book, and manage your bus trips.</p>
</section>

<section class="section">
    <div class="feature-grid">
        <div class="feature-card">
            <div class="feature-icon">🔍</div>
            <h3>Trip Search &amp; Inquiry</h3>
            <p>Search by origin, destination, and travel date to find the schedule and fare that fits your plans.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">🪑</div>
            <h3>Seat Reservation</h3>
            <p>Reserve your seat online with real-time seat availability — no more guessing at the terminal.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">💳</div>
            <h3>Online Payment</h3>
            <p>Pay securely for your booking and get your reservation confirmed instantly.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">↩️</div>
            <h3>Cancellation &amp; Refunds</h3>
            <p>Cancel a trip when plans change and receive your eligible refund through our automated policy.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">📊</div>
            <h3>Booking Dashboard</h3>
            <p>Track your upcoming trips, past bookings, and spending in one simple dashboard.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">🧾</div>
            <h3>Payment History</h3>
            <p>View a full record of your transactions — payments, refunds, and receipts — anytime.</p>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="section-head">
        <h2>Bus Classes &amp; Fares</h2>
        <p>Fares vary by route and date. Prices below are the lowest fares on record for each class.</p>
    </div>
    <div class="pricing-grid">
        <?php foreach ($busClasses as $type => [$icon, $desc, $amenities]): ?>
            <div class="pricing-card">
                <div class="feature-icon"><?php echo $icon; ?></div>
                <h3><?php echo $type; ?></h3>
                <div class="pricing-amount">
                    <?php if (isset($fareRanges[$type])): ?>
                        <span>from</span> ₱<?php echo number_format($fareRanges[$type]['min_fare'], 0); ?>
                    <?php else: ?>
                        <span>No trips scheduled</span>
                    <?php endif; ?>
                </div>
                <p><?php echo $desc; ?></p>
                <ul class="pricing-features">
                    <?php foreach ($amenities as $a): ?>
                        <li><?php echo amenityIcon($a); ?> <?php echo $a; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>

    <h3 class="pricing-addons-title">Optional Add-ons</h3>
    <div class="pricing-addons">
        <div><strong>🧳 Extra Luggage</strong><span>₱<?php echo number_format(LUGGAGE_FEE, 2); ?> per booking</span></div>
        <div><strong>🛡️ Trip Insurance</strong><span>₱<?php echo number_format(INSURANCE_FEE, 2); ?> per passenger</span></div>
        <div><strong>🎟️ Promo Codes</strong><span>Enter at checkout for discounts</span></div>
    </div>
</section>

<section class="section narrow">
    <h2>For Bus Operators</h2>
    <p>We also work with bus operators who want a simple way to publish schedules, manage seat inventory, and track reservations — all from one admin dashboard.</p>
</section>

<section class="cta-band">
    <h2>Ready to book your next trip?</h2>
    <p>Sign up and search available routes in seconds.</p>
    <a href="login.php#signup" class="btn-primary btn-large">Book a Trip</a>
</section>

<?php include 'includes/public_footer.php'; ?>
