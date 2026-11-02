-- ============================================
-- Bus Reservation System - Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS bus_reservation_db;
USE bus_reservation_db;

-- ----------------------------
-- Table: users
-- ----------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL UNIQUE,          -- PH mobile, stored as 09XXXXXXXXX; optional, can be used to sign in
    password VARCHAR(255) NOT NULL,
    role ENUM('user','admin','superadmin') NOT NULL DEFAULT 'user',   -- user = passenger, admin = staff, superadmin = owner (manages staff accounts)
    remember_token VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------
-- Table: buses
-- ----------------------------
CREATE TABLE IF NOT EXISTS buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_number VARCHAR(50) NOT NULL,
    bus_name VARCHAR(100) NOT NULL,
    operator VARCHAR(100) NOT NULL DEFAULT 'Bus Reservation System',
    seat_capacity INT NOT NULL DEFAULT 45,
    seat_layout ENUM('2x2','2x1') NOT NULL DEFAULT '2x2',
    decks ENUM('Single','Double') NOT NULL DEFAULT 'Single'
);

-- ----------------------------
-- Table: trips (schedules)
-- ----------------------------
CREATE TABLE IF NOT EXISTS trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    origin VARCHAR(100) NOT NULL,
    destination VARCHAR(100) NOT NULL,
    travel_date DATE NOT NULL,
    departure_time TIME NOT NULL,
    arrival_time TIME NOT NULL,
    fare DECIMAL(10,2) NOT NULL,
    available_seats INT NOT NULL,
    bus_type ENUM('Express','Sleeper','Luxury','Regular') NOT NULL DEFAULT 'Regular',
    amenities VARCHAR(255) DEFAULT 'AC,USB Charging',
    pickup_points VARCHAR(255) NOT NULL DEFAULT 'Main Terminal',
    dropoff_points VARCHAR(255) NOT NULL DEFAULT 'Main Terminal',
    origin_terminal_id INT DEFAULT NULL,
    destination_terminal_id INT DEFAULT NULL,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
);

-- ----------------------------
-- Table: reservations
-- ----------------------------
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pnr VARCHAR(12) NOT NULL UNIQUE,
    user_id INT DEFAULT NULL,
    trip_id INT NOT NULL,
    passenger_name VARCHAR(150) NOT NULL,
    passenger_id_number VARCHAR(50) DEFAULT NULL,
    passenger_phone VARCHAR(20) DEFAULT NULL,
    passenger_email VARCHAR(150) DEFAULT NULL,
    seat_number VARCHAR(10) NOT NULL,
    pickup_point VARCHAR(150) DEFAULT NULL,
    dropoff_point VARCHAR(150) DEFAULT NULL,
    base_fare DECIMAL(10,2) NOT NULL DEFAULT 0,
    luggage_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    insurance_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    promo_code VARCHAR(30) DEFAULT NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('Confirmed','Cancelled') DEFAULT 'Confirmed',
    reservation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
);

-- ----------------------------
-- Table: cancellations
-- ----------------------------
CREATE TABLE IF NOT EXISTS cancellations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    hours_before_departure DECIMAL(10,2) DEFAULT NULL,
    refund_percent INT DEFAULT NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    cancel_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
);

-- ----------------------------
-- Table: payments (accounting)
-- ----------------------------
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash','GCash','Card') DEFAULT 'Cash',
    payment_status ENUM('Paid','Refunded','Pending') DEFAULT 'Paid',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
);

-- ----------------------------
-- Table: promo_codes
-- ----------------------------
CREATE TABLE IF NOT EXISTS promo_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1
);

-- ----------------------------
-- Table: terminals
-- ----------------------------
CREATE TABLE IF NOT EXISTS terminals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    city VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,6) NOT NULL,
    longitude DECIMAL(10,6) NOT NULL,
    bay_count INT NOT NULL DEFAULT 4,
    popular_routes VARCHAR(255) DEFAULT NULL
);

-- ----------------------------
-- Table: contact_messages
-- ----------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    subject VARCHAR(200) DEFAULT NULL,
    message TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Audit log: who did what and when (viewed by the super admin on audit_log.php).
-- username/role are copied at the time of the action so records stay readable even if the account changes or is deleted.
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    username VARCHAR(100) DEFAULT NULL,
    role VARCHAR(20) DEFAULT NULL,
    action VARCHAR(40) NOT NULL,
    details VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_action (action),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ----------------------------
-- Sample data
-- ----------------------------

-- Default admin account (login: admin@brs.com / password: admin123)
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@brs.com', '$2b$10$MZJ0vWJX4qPwku30HXv.FuIaG2.e2eiVMhglS0SN7X2hNpMMmnk4m', 'admin');

-- Super admin account (login: teresita.alcantara@neu.edu.ph, same password as the default admin)
INSERT INTO users (username, email, password, role) VALUES
('Teresita Alcantara', 'teresita.alcantara@neu.edu.ph', '$2y$10$3wmvJG1XFW0VX/PJTWNbhu2IeW5CcbMU7nNFFhjn9wZUmaejxauVy', 'superadmin');

INSERT INTO buses (bus_number, bus_name, operator, seat_capacity, seat_layout, decks) VALUES
('BUS-001', 'Golden Coach', 'Golden Line Trans', 45, '2x2', 'Single'),
('BUS-002', 'Silver Express', 'Silver Star Bus Co.', 45, '2x2', 'Single'),
('BUS-003', 'Rapid Liner', 'Rapid Transit Inc.', 30, '2x1', 'Single'),
('BUS-004', 'Dream Sleeper', 'Golden Line Trans', 32, '2x1', 'Double');

INSERT INTO terminals (name, city, address, latitude, longitude, bay_count, popular_routes) VALUES
('Cubao Terminal', 'Quezon City', 'Aurora Blvd, Cubao, Quezon City', 14.6197, 121.0533, 8, 'Manila-Baguio, Manila-Bicol'),
('Pasay Terminal', 'Pasay City', 'EDSA cor. Taft Ave, Pasay City', 14.5378, 121.0014, 6, 'Manila-Tagaytay, Manila-Batangas'),
('Baguio Terminal', 'Baguio City', 'Governor Pack Rd, Baguio City', 16.4023, 120.5960, 4, 'Baguio-Manila'),
('Legazpi Terminal', 'Legazpi City', 'Rizal St, Legazpi City, Albay', 13.1391, 123.7438, 3, 'Bicol-Manila'),
('Tagaytay Terminal', 'Tagaytay City', 'Tagaytay-Nasugbu Hwy, Tagaytay City', 14.0968, 120.9377, 2, 'Tagaytay-Manila');

INSERT INTO trips (bus_id, origin, destination, travel_date, departure_time, arrival_time, fare, available_seats, bus_type, amenities, pickup_points, dropoff_points, origin_terminal_id, destination_terminal_id) VALUES
(1, 'Manila', 'Baguio', CURDATE() + INTERVAL 1 DAY, '06:00:00', '11:00:00', 550.00, 45, 'Express', 'AC,WiFi,USB Charging,Restroom', 'Cubao Terminal,Pasay Terminal', 'Baguio Terminal', 1, 3),
(2, 'Manila', 'Bicol', CURDATE() + INTERVAL 1 DAY, '20:00:00', '06:00:00', 850.00, 45, 'Sleeper', 'AC,WiFi,Blanket,Restroom', 'Cubao Terminal', 'Legazpi Terminal', 1, 4),
(3, 'Manila', 'Tagaytay', CURDATE() + INTERVAL 2 DAY, '08:00:00', '10:30:00', 250.00, 30, 'Regular', 'AC,USB Charging', 'Pasay Terminal', 'Tagaytay Terminal', 2, 5),
(4, 'Manila', 'Baguio', CURDATE() + INTERVAL 3 DAY, '22:00:00', '04:00:00', 950.00, 32, 'Luxury', 'AC,WiFi,Blanket,Reclining Seats,Restroom', 'Cubao Terminal', 'Baguio Terminal', 1, 3);

INSERT INTO promo_codes (code, discount_type, discount_value, active) VALUES
('WELCOME10', 'percent', 10.00, 1),
('SAVE50', 'fixed', 50.00, 1);
