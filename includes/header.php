<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($pageTitle) ? $pageTitle . ' - Bus Reservation System' : 'Bus Reservation System'; ?></title>
<link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
<script>
// Restore the collapsed desktop sidebar before the page paints, so it doesn't flash open
try { if (localStorage.getItem('brsSidebarCollapsed') === '1') document.documentElement.classList.add('sidebar-collapsed'); } catch (e) {}
</script>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-brand">🚌 BRS</div>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Menu" aria-expanded="false">☰</button>
        <div class="sidebar-menu" id="sidebarMenu">
        <div class="sidebar-menu-head">🚌 BRS <span><?php echo isStaff() ? roleLabel(currentRole()) : 'Passenger'; ?></span>
            <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close menu">✕</button>
        </div>
        <nav>
            <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
            <?php if (isStaff()): ?>
                <a href="admin_dashboard.php" class="<?php echo $currentPage == 'admin_dashboard.php' ? 'active' : ''; ?>">Admin Dashboard</a>
                <a href="manage_trips.php" class="<?php echo $currentPage == 'manage_trips.php' ? 'active' : ''; ?>">Manage Trips</a>
                <a href="inquiry.php" class="<?php echo $currentPage == 'inquiry.php' ? 'active' : ''; ?>">Inquiry</a>
                <a href="accounting.php" class="<?php echo $currentPage == 'accounting.php' ? 'active' : ''; ?>">Accounting</a>
                <a href="reports.php" class="<?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>">Reports</a>
                <?php if (isSuperAdmin()): ?>
                    <a href="user_management.php" class="<?php echo $currentPage == 'user_management.php' ? 'active' : ''; ?>">User Management</a>
                    <a href="audit_log.php" class="<?php echo $currentPage == 'audit_log.php' ? 'active' : ''; ?>">Audit Log</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="dashboard.php" class="<?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
                <a href="reservation.php" class="<?php echo $currentPage == 'reservation.php' ? 'active' : ''; ?>">Reservation</a>
                <a href="cancelation.php" class="<?php echo $currentPage == 'cancelation.php' ? 'active' : ''; ?>">Cancelation</a>
                <a href="inquiry.php" class="<?php echo $currentPage == 'inquiry.php' ? 'active' : ''; ?>">Inquiry</a>
                <a href="accounting.php" class="<?php echo $currentPage == 'accounting.php' ? 'active' : ''; ?>">Accounting</a>
            <?php endif; ?>
        </nav>
        <a href="logout.php" class="logout-link" id="logoutLink">Log Out</a>
        </div>
    </aside>
    <div class="modal-overlay" id="logoutModal" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle" aria-hidden="true">
        <div class="modal-box">
            <h2 id="logoutModalTitle">Log Out</h2>
            <p class="modal-subtitle">Are you sure you want to log out?</p>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="cancelLogoutBtn">Cancel</button>
                <a href="logout.php" class="btn-primary" id="confirmLogoutBtn">Log Out</a>
            </div>
        </div>
    </div>
    <main class="content">
        <header class="topbar">
            <h1><?php echo isset($pageTitle) ? $pageTitle : ''; ?></h1>
            <span class="user-chip">👤 <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?> <span class="role-tag"><?php echo roleLabel(currentRole()); ?></span></span>
        </header>
