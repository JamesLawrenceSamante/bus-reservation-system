<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($pageTitle) ? $pageTitle . ' - Bus Reservation System' : 'Bus Reservation System'; ?></title>
<meta name="description" content="Book bus tickets online, check schedules, and travel across the Philippines with the Bus Reservation System.">
<link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
</head>
<body class="public-body">

<header class="public-nav">
    <div class="public-nav-inner">
        <a href="index.php" class="public-brand">🚌 Bus Reservation System</a>
        <div class="public-nav-menu" id="publicNavMenu">
        <div class="public-nav-menu-head">🚌 Bus Reservation System
            <button type="button" class="public-nav-close" id="publicNavClose" aria-label="Close menu">✕</button>
        </div>
        <nav class="public-nav-links" id="publicNavLinks">
            <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Home</a>
            <a href="about.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>">About</a>
            <a href="services.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">Services</a>
            <a href="terminals.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'terminals.php' ? 'active' : ''; ?>">Terminals</a>
            <a href="live_tracking.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'live_tracking.php' ? 'active' : ''; ?>">Track Bus</a>
            <a href="faq.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'faq.php' ? 'active' : ''; ?>">FAQ</a>
            <a href="contact.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">Contact</a>
        </nav>
        <div class="public-nav-actions">
            <a href="manage_booking.php" class="btn-secondary btn-small">Manage Booking</a>
            <a href="login.php" class="btn-secondary btn-small">Log In</a>
            <a href="login.php#signup" class="btn-primary btn-small">Sign Up</a>
        </div>
        </div>
        <button type="button" class="public-nav-toggle" id="publicNavToggle" aria-label="Menu" aria-expanded="false">☰</button>
    </div>
</header>
