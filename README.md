# Bus Reservation System

A PHP/MySQL bus ticket booking website built for XAMPP local host.
Made for ITLEL1-18 (IT Elective / Web Development), New Era University.

**Tech:** PHP 8.2 (procedural, mysqli prepared statements), MySQL / MariaDB 10.4, HTML, CSS, vanilla JavaScript. Fully responsive (desktop, tablet, phone).

## User Roles
| Role | Who | What they can do |
|---|---|---|
| **User** (Passenger) | Anyone who signs up | Book trips, choose seats, cancel bookings, change seat/date, view own e-tickets and payment history |
| **Admin** (Staff) | Created by the Super Admin | Manage trips, Inquiry, system-wide Accounting, Reports |
| **Super Admin** (Owner) | One seeded account | Everything an Admin can do, plus **User Management** (create admin accounts, change roles) and the **Audit Log** |

**Seeded accounts** (from the SQL file; both use the default password `admin123`):
- Admin: `admin@brs.com`
- Super Admin: `teresita.alcantara@neu.edu.ph`

> Change these passwords after setup, especially on a hosted/live copy.

Anyone who signs up through the Sign Up form is automatically a `user`. The Super Admin can create staff accounts or promote/demote users between **User** and **Admin** on `user_management.php`. Role changes take effect on the account's next page load.

## Public Pages (no login required)
1. **Homepage** (`index.php`): hero section, **Available Trips** (upcoming trips with open seats and a "Select Seat" button), features, popular routes
2. **About Us** (`about.php`): company story, mission, values
3. **Services** (`services.php`): what the platform offers and add-on pricing
4. **Terminal & Station Directory** (`terminals.php`): searchable terminal list, bay numbers, popular routes, embedded map per terminal
5. **Live Bus Tracking** (`live_tracking.php`): simulated real-time trip progress and delay status
6. **Manage Booking / PNR Lookup** (`manage_booking.php`): look up a booking with PNR + phone, resend e-ticket, change seat or travel date
7. **Baggage & Boarding Policy** (`baggage_policy.php`): weight limits, fees, prohibited items, child ticket policy, ID requirements
8. **Contact Us** (`contact.php`): working contact form (saved to the `contact_messages` table)
9. **Privacy Policy** (`privacy.php`) / **Terms & Conditions** (`terms.php`)
10. **FAQ** (`faq.php`): expandable accordion
11. **Blog** (`blog.php`): sample travel articles
12. **Testimonials** (`testimonials.php`): customer reviews
13. **Custom 404 Page** (`404.php`): auto-triggered via `.htaccess`

## Login & Account Security
- **Sign In with username, email or mobile number** (`09XXXXXXXXX`; `+63` and spaces are accepted and normalized)
- **CAPS LOCK warning** while typing a password
- **Password policy**: at least 8 characters with an uppercase letter, a lowercase letter and a number, with a live checklist
- **Show/hide password** toggle
- **Remember Me**: stays signed in for 30 days (secure random token; only its hash is stored in `users.remember_token`)
- **CAPTCHA**: simple math question on Sign In and Sign Up (one-time use)
- **Forgot / Reset Password** (`forgot_password.php`, `reset_password.php`): reset link valid for 15 minutes. *Demo mode:* the link is shown on screen instead of being emailed.
- After signing in, users return to the page they were trying to open (e.g. a seat picked from the homepage)
- Passwords are hashed with `password_hash()` (bcrypt), never stored in plain text

## Booking Flow (User)
1. **Dashboard** (`dashboard.php`): stats overview and recent reservations with links to each e-ticket
2. **Reservation / Search Results** (`reservation.php`): search and filter trips by origin, destination, date, bus type, departure time, amenities and max price
3. **Seat & Stop Selector** (`seat_selection.php`): seat map with window/aisle labels and upper/lower decks for double-deckers, plus pickup and drop-off points
4. **Passenger Details & Checkout** (`checkout.php`): traveler info, optional luggage (+₱100) and trip insurance (+₱50), promo code, payment method (Cash / GCash / Card, simulated)
5. **Booking Confirmation & E-Ticket** (`confirmation.php`, `print_ticket.php`): unique PNR, **QR code containing the full ticket details** (PNR, passenger, route, date/time, bus, seat, stops, amount, payment), fare breakdown, terminal map, Print / Save as PDF
6. **Cancelation & Refund Portal** (`cancelation.php`): refund is calculated from hours before departure: 90% (48+ hrs), 70% (24–48 hrs), 40% (6–24 hrs), 0% (under 6 hrs)
7. **Inquiry** (`inquiry.php`): quick trip search
8. **Accounting** (`accounting.php`): personal payment history and totals

## Staff Pages (Admin & Super Admin)
- **Admin Dashboard** (`admin_dashboard.php`): system-wide stats (users, trips, reservations, revenue)
- **Manage Trips** (`manage_trips.php`): add/remove trips, set bus type, amenities, pickup/drop-off points, and terminals for the e-ticket map
- **Inquiry** (`inquiry.php`) and **Accounting** (`accounting.php`, system-wide)
- **Reports** (`reports.php`): report period filter, sales by route, trip occupancy, payment methods, bus type & operator, cancellations & refunds (printable)

## Super Admin Pages
- **User Management** (`user_management.php`): create Admin (staff) accounts, list/filter all accounts, change a role between User and Admin. The Super Admin account itself can't be changed here. Forms are CSRF-protected.
- **Audit Log** (`audit_log.php`): who did what and when, with IP address. Filter by activity type, role, date range and keyword; 30 records per page; today's sign-ins, failed sign-ins and bookings at a glance.

Recorded actions: sign in, failed sign in, sign out, sign up, password reset request, password reset, booking created, booking cancelled, seat changed, date changed, trip added, trip removed, admin created, role changed.

## Promo Codes (seeded for testing)
- `WELCOME10`: 10% off base fare
- `SAVE50`: ₱50 off flat

## Setup (XAMPP)
1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Copy the `bus-reservation-system` folder into `C:\xampp\htdocs\` (Windows) or `/Applications/XAMPP/htdocs/` (Mac).
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
4. If you have an older version of this database, **drop it first** (Databases → select `bus_reservation_db` → Drop), since newer versions add tables/columns (`users.phone`, `superadmin` role, `audit_logs`).
5. Click **Import**, choose `sql/bus_reservation_db.sql`, and run it. This creates the database, tables, and sample data (buses, trips, terminals, promo codes, admin and super admin accounts).
6. Open `http://localhost/bus-reservation-system/` in your browser.
7. Sign up for a passenger account, or sign in with one of the seeded accounts above.

## Database
Connection settings are in `includes/db.php`. Default XAMPP credentials (`root` / no password) are already set; change them if your MySQL setup differs (for example, on a web host).

Tables: `users`, `buses`, `trips`, `reservations`, `payments`, `cancellations`, `promo_codes`, `terminals`, `contact_messages`, `audit_logs`.

## Project Structure
```
bus-reservation-system/
├── includes/      auth.php (sessions, roles, Remember Me), db.php, helpers.php,
│                  header/footer (app layout), public_header/public_footer (public site)
├── css/style.css  all styles, including responsive layouts
├── js/script.js   menus/drawers, password tools, logout modal, seat map, etc.
├── img/           images
├── sql/           bus_reservation_db.sql (schema + sample data)
├── .htaccess      custom 404 page
└── *.php          pages
```

## Notes
- If the site is not in `/bus-reservation-system/`, update the `ErrorDocument` path in `.htaccess`.
- Social login (Gmail/Facebook) is a placeholder in `social_login.php`; wire up real OAuth credentials there if needed.
- QR codes are generated via a free public QR API (`api.qrserver.com`) and need internet access to display.
- The e-ticket's terminal map and the Terminal Directory page use Google Maps embeds (no API key required).
- "Live Bus Tracking" is simulated (randomized progress/status), since real GPS tracking needs hardware on actual buses.
- Password reset is in demo mode (no email server), so the reset link appears on screen.
- Sample trips are dated relative to the import date (`CURDATE()`), so there are always upcoming trips to test with.
