<?php
$pageTitle = "Blog";
include 'includes/public_header.php';

$posts = [
    ["title" => "5 Tips for a Comfortable Long-Distance Bus Ride", "excerpt" => "From choosing the right seat to packing smart, here's how to make your next long trip more comfortable.", "date" => "September 10, 2026", "emoji" => "🧳"],
    ["title" => "Top 5 Weekend Getaways You Can Reach by Bus", "excerpt" => "No car? No problem. These destinations are perfect for a quick weekend escape by bus.", "date" => "August 28, 2026", "emoji" => "🏞️"],
    ["title" => "How to Avoid Fully Booked Trips During Peak Season", "excerpt" => "Holidays and long weekends fill up fast. Here's how to plan ahead and still get your preferred seat.", "date" => "August 15, 2026", "emoji" => "📅"],
    ["title" => "Understanding Our Cancellation & Refund Policy", "excerpt" => "Plans change — here's exactly how refunds work when you cancel a reservation online.", "date" => "August 2, 2026", "emoji" => "💳"],
];
?>

<section class="page-header">
    <h1>Blog</h1>
    <p>Travel tips, route guides, and updates from the Bus Reservation System team.</p>
</section>

<section class="section">
    <div class="blog-grid">
        <?php foreach ($posts as $post): ?>
            <article class="blog-card">
                <div class="blog-card-thumb"><?php echo $post['emoji']; ?></div>
                <div class="blog-card-body">
                    <span class="blog-date"><?php echo $post['date']; ?></span>
                    <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                    <p><?php echo htmlspecialchars($post['excerpt']); ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php include 'includes/public_footer.php'; ?>
