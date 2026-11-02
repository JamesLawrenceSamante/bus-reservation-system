<?php
$pageTitle = "Testimonials";
include 'includes/public_header.php';

$testimonials = [
    ["name" => "Maria Santos", "role" => "Regular Commuter", "quote" => "Sobrang dali gamitin! Ilang taps lang, may seat na agad ako. Hindi na ako pumipila sa terminal.", "initial" => "M"],
    ["name" => "Jun Dela Cruz", "role" => "Weekend Traveler", "quote" => "Nag-cancel ako ng booking ko at mabilis lang naproseso yung refund. Solid yung system nila.", "initial" => "J"],
    ["name" => "Angela Reyes", "role" => "Business Traveler", "quote" => "Nakikita ko agad kung ilan pang available na seats bago ako mag-book. Super convenient para sa mga last-minute trips.", "initial" => "A"],
];
?>

<section class="page-header">
    <h1>What Our Riders Say</h1>
    <p>Real feedback from passengers who booked with us.</p>
</section>

<section class="section">
    <div class="testimonial-grid">
        <?php foreach ($testimonials as $t): ?>
            <div class="testimonial-card">
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-quote">"<?php echo htmlspecialchars($t['quote']); ?>"</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar"><?php echo $t['initial']; ?></div>
                    <div>
                        <div class="testimonial-name"><?php echo htmlspecialchars($t['name']); ?></div>
                        <div class="testimonial-role"><?php echo htmlspecialchars($t['role']); ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="cta-band">
    <h2>Join thousands of happy travelers</h2>
    <p>Book your trip today and experience it yourself.</p>
    <a href="login.php#signup" class="btn-primary btn-large">Get Started</a>
</section>

<?php include 'includes/public_footer.php'; ?>
