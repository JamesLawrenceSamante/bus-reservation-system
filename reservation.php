<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$pageTitle = "Reservation";

// ---------- FILTERS ----------
$origin = $_GET['origin'] ?? '';
$destination = $_GET['destination'] ?? '';
$date = $_GET['date'] ?? '';
$busType = $_GET['bus_type'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$operator = $_GET['operator'] ?? '';
$departWindow = $_GET['depart_time'] ?? '';
$selectedAmenities = array_values(array_intersect((array) ($_GET['amenities'] ?? []), FILTER_AMENITIES));
$sortBy = $_GET['sort'] ?? 'date';

$windows = departureWindows();
$operators = $conn->query("SELECT DISTINCT operator FROM buses ORDER BY operator ASC");

$sql = "SELECT t.id, b.bus_name, b.operator, t.origin, t.destination, t.travel_date, t.departure_time, t.arrival_time,
               t.fare, t.available_seats, t.bus_type, t.amenities
        FROM trips t JOIN buses b ON t.bus_id = b.id
        WHERE t.travel_date >= CURDATE()";
$params = [];
$types = "";

if ($origin !== '') { $sql .= " AND t.origin LIKE ?"; $params[] = "%$origin%"; $types .= "s"; }
if ($destination !== '') { $sql .= " AND t.destination LIKE ?"; $params[] = "%$destination%"; $types .= "s"; }
if ($date !== '') { $sql .= " AND t.travel_date = ?"; $params[] = $date; $types .= "s"; }
if ($busType !== '') { $sql .= " AND t.bus_type = ?"; $params[] = $busType; $types .= "s"; }
if ($maxPrice !== '') { $sql .= " AND t.fare <= ?"; $params[] = $maxPrice; $types .= "d"; }
if ($operator !== '') { $sql .= " AND b.operator = ?"; $params[] = $operator; $types .= "s"; }
if (isset($windows[$departWindow])) {
    $sql .= " AND t.departure_time >= ? AND t.departure_time < ?";
    $params[] = $windows[$departWindow][1];
    $params[] = $windows[$departWindow][2];
    $types .= "ss";
}
foreach ($selectedAmenities as $amenity) {
    // Trip must offer every checked amenity
    $sql .= " AND FIND_IN_SET(?, REPLACE(t.amenities, ', ', ',')) > 0";
    $params[] = $amenity;
    $types .= "s";
}

switch ($sortBy) {
    case 'price_low': $sql .= " ORDER BY t.fare ASC"; break;
    case 'price_high': $sql .= " ORDER BY t.fare DESC"; break;
    case 'departure': $sql .= " ORDER BY t.departure_time ASC"; break;
    default: $sql .= " ORDER BY t.travel_date ASC, t.departure_time ASC";
}

$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$trips = $stmt->get_result();

include 'includes/header.php';
?>

<section class="panel">
    <div class="panel-head"><h2>Search Trips</h2></div>
    <form method="GET" action="reservation.php" class="inline-form">
        <div class="form-field">
            <label>From</label>
            <input type="text" name="origin" value="<?php echo htmlspecialchars($origin); ?>" placeholder="e.g. Manila">
        </div>
        <div class="form-field">
            <label>To</label>
            <input type="text" name="destination" value="<?php echo htmlspecialchars($destination); ?>" placeholder="e.g. Baguio">
        </div>
        <div class="form-field">
            <label>Date</label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>">
        </div>
        <div class="form-field">
            <label>Bus Type</label>
            <select name="bus_type">
                <option value="">Any</option>
                <?php foreach (['Regular','Express','Sleeper','Luxury'] as $type): ?>
                    <option value="<?php echo $type; ?>" <?php echo $busType === $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label>Max Price (₱)</label>
            <input type="number" name="max_price" value="<?php echo htmlspecialchars($maxPrice); ?>" placeholder="e.g. 1000">
        </div>
        <div class="form-field">
            <label>Operator</label>
            <select name="operator">
                <option value="">Any</option>
                <?php while ($op = $operators->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($op['operator']); ?>" <?php echo $operator === $op['operator'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($op['operator']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-field">
            <label>Departure Time</label>
            <select name="depart_time">
                <option value="">Any time</option>
                <?php foreach ($windows as $key => $w): ?>
                    <option value="<?php echo $key; ?>" <?php echo $departWindow === $key ? 'selected' : ''; ?>><?php echo $w[0]; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label>Sort By</label>
            <select name="sort">
                <option value="date" <?php echo $sortBy === 'date' ? 'selected' : ''; ?>>Departure Date</option>
                <option value="price_low" <?php echo $sortBy === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                <option value="price_high" <?php echo $sortBy === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                <option value="departure" <?php echo $sortBy === 'departure' ? 'selected' : ''; ?>>Departure Time</option>
            </select>
        </div>
        <div class="form-field amenity-filter">
            <label>Amenities</label>
            <div class="amenity-options">
                <?php foreach (FILTER_AMENITIES as $a): ?>
                    <label class="amenity-option">
                        <input type="checkbox" name="amenities[]" value="<?php echo htmlspecialchars($a); ?>" <?php echo in_array($a, $selectedAmenities, true) ? 'checked' : ''; ?>>
                        <?php echo amenityIcon($a); ?> <?php echo htmlspecialchars($a); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="submit" class="btn-primary">Search</button>
        <a href="reservation.php" class="btn-secondary">Clear</a>
    </form>
</section>

<section class="panel">
    <div class="panel-head"><h2>Available Trips (<?php echo $trips->num_rows; ?>)</h2></div>
    <div class="trip-grid">
        <?php if ($trips->num_rows === 0): ?>
            <p class="empty-row">No trips match your search. Try adjusting your filters.</p>
        <?php else: while ($t = $trips->fetch_assoc()): ?>
            <div class="trip-card">
                <div class="trip-type-badge"><?php echo htmlspecialchars($t['bus_type']); ?></div>
                <div class="trip-route"><?php echo htmlspecialchars($t['origin']); ?> → <?php echo htmlspecialchars($t['destination']); ?></div>
                <div class="trip-meta">
                    <span><?php echo htmlspecialchars($t['bus_name']); ?> · <?php echo htmlspecialchars($t['operator']); ?></span>
                    <span><?php echo date('M d, Y', strtotime($t['travel_date'])); ?></span>
                    <span><?php echo date('h:i A', strtotime($t['departure_time'])); ?> - <?php echo date('h:i A', strtotime($t['arrival_time'])); ?></span>
                </div>
                <div class="trip-amenities">
                    <?php foreach (array_filter(explode(',', $t['amenities'])) as $a): ?>
                        <span class="amenity-chip"><?php echo amenityIcon(trim($a)); ?> <?php echo htmlspecialchars(trim($a)); ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="trip-card-footer">
                    <div class="trip-fare">₱<?php echo number_format($t['fare'], 2); ?></div>
                    <div class="trip-seats"><?php echo $t['available_seats']; ?> seats available</div>
                    <?php if ($t['available_seats'] > 0): ?>
                        <a href="seat_selection.php?trip_id=<?php echo $t['id']; ?>" class="btn-primary btn-small">Select Seat</a>
                    <?php else: ?>
                        <button class="btn-primary btn-small" disabled>Fully Booked</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
