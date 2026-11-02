<?php
// ============================================
// Shared Helper Functions
// ============================================

/**
 * Generates a unique 8-character alphanumeric PNR (Passenger Name Record).
 */
function generatePNR($conn) {
    do {
        $pnr = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $check = $conn->prepare("SELECT id FROM reservations WHERE pnr = ?");
        $check->bind_param("s", $pnr);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
    } while ($exists);
    return $pnr;
}

/**
 * Calculates refund percentage based on how many hours remain before departure.
 * More than 48 hours  -> 90% refund
 * 24 to 48 hours       -> 70% refund
 * 6 to 24 hours        -> 40% refund
 * Less than 6 hours     -> 0% refund (or trip already departed)
 */
function calculateRefundPercent($hoursBeforeDeparture) {
    if ($hoursBeforeDeparture >= 48) return 90;
    if ($hoursBeforeDeparture >= 24) return 70;
    if ($hoursBeforeDeparture >= 6) return 40;
    return 0;
}

/**
 * Password policy shared by Sign Up and Reset Password.
 * Returns the missing requirements (empty array = password is strong enough).
 */
function passwordPolicyErrors($password) {
    $missing = [];
    if (strlen($password) < 8) $missing[] = "at least 8 characters";
    if (!preg_match('/[A-Z]/', $password)) $missing[] = "an uppercase letter";
    if (!preg_match('/[a-z]/', $password)) $missing[] = "a lowercase letter";
    if (!preg_match('/[0-9]/', $password)) $missing[] = "a number";
    return $missing;
}

/**
 * Normalizes a Philippine mobile number to 09XXXXXXXXX.
 * Accepts "0917-123-4567", "0917 123 4567", "+63 917 123 4567", "639171234567", "9171234567".
 * Returns null when it isn't a valid PH mobile number.
 */
function normalizePhone($input) {
    $digits = preg_replace('/\D/', '', (string) $input);
    if (strlen($digits) === 12 && str_starts_with($digits, '63')) $digits = '0' . substr($digits, 2);
    if (strlen($digits) === 10 && str_starts_with($digits, '9')) $digits = '0' . $digits;
    return preg_match('/^09\d{9}$/', $digits) ? $digits : null;
}

/**
 * Creates an account after checking every rule (used by Sign Up and by the super admin's User Management).
 * $phone may be empty (optional). Returns an error message, or null on success.
 */
function createAccount($conn, $username, $email, $phone, $password, $confirmPassword, $role = 'user') {
    $username = trim($username);
    $email = trim($email);
    if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Please enter a username and a valid email address.";
    }

    $normalizedPhone = null;
    if (trim((string) $phone) !== '') {
        $normalizedPhone = normalizePhone($phone);
        if (!$normalizedPhone) return "Please enter a valid PH mobile number (e.g. 0917 123 4567).";
    }

    $missing = passwordPolicyErrors($password);
    if ($missing) return "Password must have " . implode(', ', $missing) . ".";
    if ($password !== $confirmPassword) return "Passwords do not match.";

    $check = $conn->prepare("SELECT email, phone FROM users WHERE email = ? OR (phone IS NOT NULL AND phone = ?)");
    $phoneParam = $normalizedPhone ?? '';
    $check->bind_param("ss", $email, $phoneParam);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    if ($existing) {
        return strcasecmp($existing['email'], $email) === 0 ? "Email already registered." : "Mobile number already registered.";
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $insert = $conn->prepare("INSERT INTO users (username, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
    $insert->bind_param("sssss", $username, $email, $normalizedPhone, $hashed, $role);
    return $insert->execute() ? null : "Something went wrong. Please try again.";
}

// ---------- Audit log ----------
// action code => [label, group]; groups drive the filter on the Audit Log page
const AUDIT_ACTIONS = [
    'LOGIN'                  => ['Signed in', 'account'],
    'LOGIN_FAILED'           => ['Failed sign-in', 'account'],
    'LOGOUT'                 => ['Signed out', 'account'],
    'SIGNUP'                 => ['Created an account', 'account'],
    'PASSWORD_RESET_REQUEST' => ['Requested password reset', 'account'],
    'PASSWORD_RESET'         => ['Reset password', 'account'],
    'BOOKING_CREATED'        => ['Booked a seat', 'booking'],
    'BOOKING_CANCELLED'      => ['Cancelled a booking', 'booking'],
    'SEAT_CHANGED'           => ['Changed seat', 'booking'],
    'DATE_CHANGED'           => ['Changed travel date', 'booking'],
    'TRIP_ADDED'             => ['Added a trip', 'trips'],
    'TRIP_REMOVED'           => ['Removed a trip', 'trips'],
    'ADMIN_CREATED'          => ['Created an admin account', 'accounts'],
    'ROLE_CHANGED'           => ['Changed a role', 'accounts'],
];

/**
 * Records who did what and when. By default it's the signed-in user;
 * pass $actor = ['id' => .., 'username' => .., 'role' => ..] when acting for someone else
 * (e.g. a failed sign-in, or a guest managing a booking that belongs to a user).
 * Logging must never break the page, so errors are ignored.
 */
function logActivity($conn, $action, $details = '', $actor = null) {
    try {
        $id = $actor['id'] ?? $_SESSION['user_id'] ?? null;
        $username = $actor['username'] ?? $_SESSION['username'] ?? null;
        $role = $actor['role'] ?? $_SESSION['role'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $details = mb_substr((string) $details, 0, 255);
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, username, role, action, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $id, $username, $role, $action, $details, $ip);
        $stmt->execute();
    } catch (Throwable $e) {
        // ignore: an audit failure shouldn't stop a booking or a login
    }
}

/**
 * Text encoded in the boarding QR code: everything a conductor needs when they scan it.
 * $t is a reservation row joined with its trip, bus and payment.
 */
function ticketQrText($t) {
    $lines = [
        'BUS RESERVATION SYSTEM - E-TICKET',
        'PNR: ' . $t['pnr'],
        'Status: ' . $t['status'],
        '',
        'Passenger: ' . $t['passenger_name'],
        'Valid ID: ' . ($t['passenger_id_number'] ?: '-'),
        'Mobile: ' . ($t['passenger_phone'] ?: '-'),
        'Email: ' . ($t['passenger_email'] ?: '-'),
        '',
        'Route: ' . $t['origin'] . ' to ' . $t['destination'],
        'Travel Date: ' . date('M d, Y', strtotime($t['travel_date'])),
        'Departure: ' . date('h:i A', strtotime($t['departure_time'])) . '  Arrival: ' . date('h:i A', strtotime($t['arrival_time'])),
        'Bus: ' . $t['bus_name'] . ' (' . $t['operator'] . ')',
        'Bus Type: ' . $t['bus_type'],
        'Seat: ' . seatLabel($t['seat_number'], $t['seat_layout'], $t['decks'], (int) $t['seat_capacity']),
        'Pickup: ' . ($t['pickup_point'] ?: '-'),
        'Drop-off: ' . ($t['dropoff_point'] ?: '-'),
        '',
        'Amount Paid: PHP ' . number_format($t['amount'], 2),
        'Payment: ' . ($t['payment_method'] ?? '-') . ' (' . ($t['payment_status'] ?? '-') . ')',
        'Booked: ' . date('M d, Y h:i A', strtotime($t['reservation_date'])),
    ];
    return implode("\n", $lines);
}

function ticketQrUrl($t) {
    // ecc=M keeps the larger amount of text easy to scan
    return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&ecc=M&data=" . urlencode(ticketQrText($t));
}

/**
 * Returns amenity emoji for display.
 */
function amenityIcon($amenity) {
    $map = [
        'AC' => '❄️', 'WiFi' => '📶', 'USB Charging' => '🔌', 'Restroom' => '🚻',
        'Blanket' => '🛏️', 'Reclining Seats' => '💺',
    ];
    return $map[$amenity] ?? '✔️';
}

// Add-on fees used by checkout and shown on the Services page
const LUGGAGE_FEE = 100.00;
const INSURANCE_FEE = 50.00;

// Amenities passengers can filter by on the search page
const FILTER_AMENITIES = ['AC', 'WiFi', 'USB Charging', 'Restroom', 'Blanket', 'Reclining Seats'];

/**
 * Departure time windows for the search filter: key => [label, start, end).
 */
function departureWindows() {
    return [
        'early'     => ['Early Morning (12 AM – 6 AM)', '00:00:00', '06:00:00'],
        'morning'   => ['Morning (6 AM – 12 PM)',       '06:00:00', '12:00:00'],
        'afternoon' => ['Afternoon (12 PM – 6 PM)',     '12:00:00', '18:00:00'],
        'evening'   => ['Evening (6 PM – 12 AM)',       '18:00:00', '24:00:00'],
    ];
}

/**
 * Describes where a seat sits in the bus.
 * Seats are numbered row by row; a 2x2 row is [W A | A W], a 2x1 row is [W A | W].
 * Double-deck buses put the first half of the seats on the lower deck.
 */
function seatInfo($seatNum, $layout, $decks, $capacity) {
    $perRow = $layout === '2x1' ? 3 : 4;
    $deckSize = $decks === 'Double' ? (int) ceil($capacity / 2) : $capacity;
    $deck = null;
    $indexInDeck = $seatNum - 1;
    if ($decks === 'Double') {
        $deck = $seatNum <= $deckSize ? 'Lower' : 'Upper';
        if ($deck === 'Upper') $indexInDeck -= $deckSize;
    }
    $col = $indexInDeck % $perRow;
    $isWindow = $col === 0 || $col === $perRow - 1;
    return ['deck' => $deck, 'position' => $isWindow ? 'Window' : 'Aisle'];
}

/**
 * Human-readable seat label, e.g. "Seat 12 · Window · Upper Deck".
 */
function seatLabel($seatNum, $layout, $decks, $capacity) {
    $info = seatInfo((int) $seatNum, $layout, $decks, $capacity);
    $label = "Seat $seatNum · {$info['position']}";
    if ($info['deck']) $label .= " · {$info['deck']} Deck";
    return $label;
}

/**
 * Renders the clickable seat map (one grid per deck).
 * $currentSeat is highlighted as the passenger's existing seat (used when changing seats).
 */
function renderSeatMap($layout, $decks, $capacity, array $takenSeats, $currentSeat = null) {
    $perRow = $layout === '2x1' ? 3 : 4;
    $aisleAfter = 2; // aisle sits after the 2nd seat in both layouts
    $deckSize = $decks === 'Double' ? (int) ceil($capacity / 2) : $capacity;
    $deckList = $decks === 'Double'
        ? [['Lower Deck', 1, $deckSize], ['Upper Deck', $deckSize + 1, $capacity]]
        : [[null, 1, $capacity]];

    echo '<div class="bus-decks' . ($decks === 'Double' ? ' is-double' : '') . '">';
    foreach ($deckList as [$deckName, $first, $last]) {
        echo '<div class="bus-diagram">';
        if ($deckName) echo '<div class="deck-title">' . $deckName . '</div>';
        echo '<div class="bus-front">' . ($deckName === 'Upper Deck' ? '⬆️ Front' : '🚌 Driver') . '</div>';
        echo '<div class="seat-grid" style="grid-template-columns: repeat(' . ($perRow + 1) . ', auto);">';
        for ($seat = $first; $seat <= $last; $seat += $perRow) {
            for ($c = 0; $c < $perRow; $c++) {
                if ($c === $aisleAfter) echo '<div class="seat-aisle"></div>';
                $num = $seat + $c;
                if ($num > $last) { echo '<div></div>'; continue; }

                $info = seatInfo($num, $layout, $decks, $capacity);
                $label = seatLabel($num, $layout, $decks, $capacity);
                $isCurrent = $currentSeat !== null && (string) $num === (string) $currentSeat;
                $isTaken = !$isCurrent && in_array((string) $num, $takenSeats, true);
                $classes = 'seat-btn ' . ($isTaken ? 'taken' : 'available') . ($info['position'] === 'Window' ? ' window' : '') . ($isCurrent ? ' current' : '');
                echo '<button type="button" class="' . $classes . '" data-seat="' . $num . '" data-label="' . htmlspecialchars($label) . '" title="' . htmlspecialchars($label) . '"' . ($isTaken ? ' disabled' : '') . '>' . $num . '</button>';
            }
        }
        echo '</div></div>';
    }
    echo '</div>';
}

/**
 * Returns the confirmed seat numbers already taken on a trip (as strings).
 */
function takenSeatsForTrip($conn, $tripId) {
    $stmt = $conn->prepare("SELECT seat_number FROM reservations WHERE trip_id = ? AND status = 'Confirmed'");
    $stmt->bind_param("i", $tripId);
    $stmt->execute();
    $result = $stmt->get_result();
    $seats = [];
    while ($row = $result->fetch_assoc()) $seats[] = (string) $row['seat_number'];
    return $seats;
}
?>
