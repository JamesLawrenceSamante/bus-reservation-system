<?php
$pageTitle = "Page Not Found";
include 'includes/public_header.php';
?>

<section class="error-page">
    <div class="error-code">404</div>
    <h1>Oops, this page took a wrong turn.</h1>
    <p>The page you're looking for doesn't exist or may have been moved.</p>
    <div class="hero-actions">
        <a href="index.php" class="btn-primary btn-large">Back to Home</a>
        <a href="contact.php" class="btn-secondary btn-large">Contact Support</a>
    </div>
</section>

<?php include 'includes/public_footer.php'; ?>
