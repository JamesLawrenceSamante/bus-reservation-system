<?php
$pageTitle = "FAQ";

$faqs = [
    ["How do I book a bus ticket?", "Sign up for a free account, log in, go to the Reservation page, choose your trip, and confirm your seat. You'll see your booking right away in your Dashboard."],
    ["Can I cancel my reservation?", "Yes. Go to the Cancelation page in your account, select the reservation, and confirm. You'll receive an 80% refund of the ticket fare."],
    ["How will I know my booking is confirmed?", "Once payment goes through, your reservation status will show as \"Confirmed\" in your Dashboard and Cancelation page."],
    ["What if the trip I want is fully booked?", "Fully booked trips will show \"Fully Booked\" on the Reservation page. Check back later or try the Inquiry page to search for alternative schedules."],
    ["Do I need to create an account to book?", "Yes, an account lets us keep track of your bookings, refunds, and payment history securely."],
    ["Is my payment information safe?", "Yes. We use secure, encrypted processes to handle your account and transactions. Read more on our Privacy Policy page."],
];

include 'includes/public_header.php';
?>

<section class="page-header">
    <h1>Frequently Asked Questions</h1>
    <p>Quick answers to common questions about booking with us.</p>
</section>

<section class="section narrow">
    <div class="faq-list">
        <?php foreach ($faqs as $i => $faq): ?>
            <div class="faq-item">
                <button type="button" class="faq-question" data-faq="<?php echo $i; ?>">
                    <?php echo htmlspecialchars($faq[0]); ?>
                    <span class="faq-toggle">+</span>
                </button>
                <div class="faq-answer" id="faq-<?php echo $i; ?>">
                    <p><?php echo htmlspecialchars($faq[1]); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="cta-band">
    <h2>Still have questions?</h2>
    <p>Our support team is happy to help.</p>
    <a href="contact.php" class="btn-primary btn-large">Contact Us</a>
</section>

<?php include 'includes/public_footer.php'; ?>
