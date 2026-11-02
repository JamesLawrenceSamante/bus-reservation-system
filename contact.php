<?php
$pageTitle = "Contact Us";
require_once 'includes/db.php';

$message = "";
$messageType = "";

if (isset($_POST['send_message'])) {
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $body = trim($_POST['message']);

    if ($fullName === '' || $email === '' || $body === '') {
        $message = "Please fill in your name, email, and message.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO contact_messages (full_name, email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $fullName, $email, $subject, $body);
        if ($stmt->execute()) {
            $message = "Thanks for reaching out! We'll get back to you within 1-2 business days.";
            $messageType = "success";
        } else {
            $message = "Something went wrong. Please try again.";
            $messageType = "error";
        }
    }
}

include 'includes/public_header.php';
?>

<section class="page-header">
    <h1>Contact Us</h1>
    <p>Questions, feedback, or partnership inquiries — we'd love to hear from you.</p>
</section>

<section class="section narrow">
    <div class="contact-grid">
        <div class="contact-info">
            <h3>Get in Touch</h3>
            <p>📍 EDSA cor. Quezon Ave, Quezon City, Philippines</p>
            <p>📞 (02) 8888-0000</p>
            <p>✉️ support@busreservation.ph</p>
            <p>🕐 Mon–Sun, 6:00 AM – 10:00 PM</p>
        </div>
        <div class="contact-form-wrapper">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form method="POST" action="contact.php">
                <label>Full Name</label>
                <input type="text" name="full_name" required>

                <label>Email Address</label>
                <input type="email" name="email" required>

                <label>Subject</label>
                <input type="text" name="subject" placeholder="Optional">

                <label>Message</label>
                <textarea name="message" rows="5" required></textarea>

                <button type="submit" name="send_message" class="btn-primary" style="width:100%;margin-top:18px;">Send Message</button>
            </form>
        </div>
    </div>
</section>

<?php include 'includes/public_footer.php'; ?>
