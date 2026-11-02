<?php
$pageTitle = "Home";
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// Upcoming trips with open seats, shown to everyone (no login needed) so visitors can see what's available
$trips = $conn->query("SELECT t.id, t.origin, t.destination, t.travel_date, t.departure_time, t.arrival_time,
                              t.fare, t.available_seats, t.bus_type, t.amenities, b.bus_name, b.operator
                        FROM trips t JOIN buses b ON t.bus_id = b.id
                        WHERE TIMESTAMP(t.travel_date, t.departure_time) > NOW() AND t.available_seats > 0
                        ORDER BY t.travel_date ASC, t.departure_time ASC LIMIT 6");

include 'includes/public_header.php';
?>

<section class="hero">
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <h1>Travel Smarter.<br>Book Faster.</h1>
        <p>Reserve your bus seat online in minutes — real-time seat availability, secure payments, and instant e-tickets.</p>
        <div class="hero-actions">
            <a href="login.php#signup" class="btn-primary btn-large">Book a Trip</a>
            <a href="services.php" class="btn-secondary btn-large">Learn More</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <h2>Why Choose Us</h2>
        <p>Everything you need for a smooth, worry-free bus trip.</p>
    </div>
    <div class="feature-grid">
        <div class="feature-card">
            <div class="feature-icon">🎫</div>
            <h3>Easy Booking</h3>
            <p>Search, select your seat, and pay — all in a few clicks, no lining up at the terminal.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">🔒</div>
            <h3>Secure Payments</h3>
            <p>Your account and transactions are protected with encrypted, industry-standard security.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">⏱️</div>
            <h3>Real-Time Availability</h3>
            <p>See live seat counts and schedules so you always know what's actually available.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">↩️</div>
            <h3>Easy Cancellation</h3>
            <p>Plans change — cancel your trip online and get your eligible refund processed quickly.</p>
        </div>
    </div>
</section>

<section class="section section-alt" id="available-trips">
    <div class="section-head">
        <h2>Available Trips</h2>
        <p>Upcoming buses with open seats. Pick a trip — you'll be asked to sign in or create a free account before choosing your seat.</p>
    </div>
    <?php if ($trips->num_rows === 0): ?>
        <p class="empty-row" style="text-align:center;">No upcoming trips are open for booking right now. Please check back soon.</p>
    <?php else: ?>
    <div class="trip-grid">
        <?php while ($t = $trips->fetch_assoc()): ?>
            <div class="trip-card">
                <div class="trip-type-badge"><?php echo htmlspecialchars($t['bus_type']); ?></div>
                <div class="trip-route"><?php echo htmlspecialchars($t['origin']); ?> → <?php echo htmlspecialchars($t['destination']); ?></div>
                <div class="trip-meta">
                    <span><?php echo htmlspecialchars($t['bus_name']); ?> · <?php echo htmlspecialchars($t['operator']); ?></span>
                    <span><?php echo date('M d, Y', strtotime($t['travel_date'])); ?></span>
                    <span><?php echo date('h:i A', strtotime($t['departure_time'])); ?> - <?php echo date('h:i A', strtotime($t['arrival_time'])); ?></span>
                </div>
                <div class="trip-amenities">
                    <?php foreach (array_filter(array_map('trim', explode(',', $t['amenities']))) as $a): ?>
                        <span class="amenity-chip"><?php echo amenityIcon($a); ?> <?php echo htmlspecialchars($a); ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="trip-card-footer">
                    <div class="trip-fare">₱<?php echo number_format($t['fare'], 2); ?></div>
                    <div class="trip-seats"><?php echo $t['available_seats']; ?> seats available</div>
                    <a href="seat_selection.php?trip_id=<?php echo $t['id']; ?>" class="btn-primary btn-small">Select Seat</a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</section>

<section class="cta-band">
    <h2>Ready to hit the road?</h2>
    <p>Create your free account and book your first trip in minutes.</p>
    <a href="login.php#signup" class="btn-primary btn-large">Get Started</a>
</section>

<?php include 'includes/public_footer.php'; ?>
