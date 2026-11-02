# Bus Reservation System

A PHP/MySQL bus ticket booking website built for XAMPP local host.

## User Roles
- **User** (default, created via Sign Up) — books trips, cancels reservations, views their own payment history.
- **Admin** — manages trips (add/remove) and sees system-wide stats across all users.

**Default admin login** (seeded in the SQL file):
- Email: `admin@brs.com`
- Password: `admin123`

Anyone who signs up through the Sign Up form is automatically a `user`. To make someone an admin, open **phpMyAdmin → bus_reservation_db → users table**, edit their row, and change `role` from `user` to `admin`.

## Public Pages (Marketing Site — no login required)
1. **Homepage** (`index.php`) — hero section, features, popular routes, call-to-action
2. **About Us** (`about.php`) — company story, mission, values
3. **Services** (`services.php`) — what the platform offers
4. **Terminal & Station Directory** (`terminals.php`) — searchable terminal list, bay numbers, popular routes, embedded map per terminal
5. **Live Bus Tracking** (`live_tracking.php`) — simulated real-time trip progress and delay status
6. **Manage Booking / PNR Lookup** (`manage_booking.php`) — guests or users look up a booking with PNR + phone, resend e-ticket, or change travel date
7. **Baggage & Boarding Policy** (`baggage_policy.php`) — weight limits, fees, prohibited items, child ticket policy, ID requirements
8. **Contact Us** (`contact.php`) — working contact form (saved to `contact_messages` table)
9. **Privacy Policy** (`privacy.php`) / **Terms & Conditions** (`terms.php`)
10. **FAQ** (`faq.php`) — expandable accordion
11. **Blog** (`blog.php`) — sample travel articles
12. **Testimonials** (`testimonials.php`) — customer reviews
13. **Custom 404 Page** (`404.php`) — auto-triggered via `.htaccess`

## Booking Flow (After Login)
1. **Login Page** (`login.php`) — Sign In / Sign Up, Remember Me, Forgot Password, Gmail/Facebook placeholders. Routes to Dashboard or Admin Dashboard by role.
2. **Dashboard** (`dashboard.php`) — stats overview and recent reservations with a link to view each e-ticket
3. **Reservation / Search Results** (`reservation.php`) — search & filter trips by origin, destination, date, bus type (Regular/Express/Sleeper/Luxury), and max price; shows amenities per trip
4. **Seat & Stop Selector** (`seat_selection.php`) — interactive clickable seat map (available/selected/taken), plus pickup and drop-off point dropdowns
5. **Passenger Details & Checkout** (`checkout.php`) — traveler name, ID number, phone, email, optional luggage add-on (+₱100) and trip insurance (+₱50), promo code field, and a simulated payment method selector
6. **Booking Confirmation & E-Ticket** (`confirmation.php`) — unique PNR, scannable QR code, fare breakdown, terminal map, and a "Download / Print PDF" button (uses the browser's print dialog — choose "Save as PDF")
7. **Cancelation & Refund Portal** (`cancelation.php`) — refund percentage is calculated automatically based on hours remaining before departure (90% if 48+ hrs, 70% if 24–48 hrs, 40% if 6–24 hrs, 0% if under 6 hrs)
8. **Inquiry Page** (`inquiry.php`) — quick search trips by origin, destination, date
9. **Accounting Page** (`accounting.php`) — payment history and totals (personal for users, system-wide for admin)
10. **Admin Dashboard** (`admin_dashboard.php`) — system-wide stats: users, trips, reservations, revenue
11. **Manage Trips** (`manage_trips.php`) — admin-only: add/remove trips, set bus type, amenities, pickup/drop-off points, and link terminals for the e-ticket map

## Promo Codes (seeded for testing)
- `WELCOME10` — 10% off base fare
- `SAVE50` — ₱50 off flat

## Setup (XAMPP)
1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Copy the `bus-reservation-system` folder into `C:\xampp\htdocs\` (Windows) or `/Applications/XAMPP/htdocs/` (Mac).
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
4. If you have an older version of this database, **drop it first** (Databases → select `bus_reservation_db` → Drop), since this version adds several new tables/columns.
5. Click **Import**, choose `sql/bus_reservation_db.sql`, and run it. This creates the database, tables, and sample data (buses, trips, terminals, promo codes, admin account).
6. Open `http://localhost/bus-reservation-system/index.php` in your browser — this is now the homepage.
7. Click **Sign Up** (or use the Login page) to create an account, then **Sign In**.

## Database config
Connection settings are in `includes/db.php`. Default XAMPP credentials (`root` / no password) are already set — change them if your MySQL setup differs.

## Notes
- Passwords are hashed with `password_hash()` (bcrypt) — never stored in plain text.
- Social login (Gmail/Facebook) is a placeholder in `social_login.php` — wire up real OAuth credentials there if needed.
- QR codes are generated via a free public QR API (`api.qrserver.com`) — requires internet access to display.
- The e-ticket's terminal map and the Terminal Directory page use Google Maps embeds (no API key required).
- "Live Bus Tracking" is simulated (randomized progress/status) since real GPS tracking requires hardware/telemetry from actual buses.
- Sample trips, terminals, and promo codes are included in the SQL file so you can test the full flow right away.
