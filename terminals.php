<?php
require_once 'includes/db.php';

$pageTitle = "Terminal Directory";
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM terminals";
if ($search !== '') {
    $stmt = $conn->prepare("SELECT * FROM terminals WHERE name LIKE ? OR city LIKE ? OR popular_routes LIKE ? ORDER BY name ASC");
    $like = "%$search%";
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $terminals = $stmt->get_result();
} else {
    $terminals = $conn->query($sql . " ORDER BY name ASC");
}

include 'includes/public_header.php';
?>

<section class="page-header">
    <h1>Terminal &amp; Station Directory</h1>
    <p>Find terminal locations, bay numbers, and popular routes.</p>
</section>

<section class="section narrow">
    <form method="GET" action="terminals.php" class="inline-form" style="margin-bottom: 30px;">
        <div class="form-field">
            <label>Search by terminal, city, or route</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="e.g. Cubao, Baguio, Manila-Bicol">
        </div>
        <button type="submit" class="btn-primary">Search</button>
    </form>

    <?php if ($terminals->num_rows === 0): ?>
        <p class="empty-row">No terminals match your search.</p>
    <?php else: while ($t = $terminals->fetch_assoc()): ?>
        <div class="terminal-card">
            <div class="terminal-info">
                <h3><?php echo htmlspecialchars($t['name']); ?></h3>
                <p class="muted">📍 <?php echo htmlspecialchars($t['address']); ?></p>
                <div class="terminal-meta">
                    <span>🚏 <?php echo $t['bay_count']; ?> boarding bays</span>
                    <span>🛣️ <?php echo htmlspecialchars($t['popular_routes'] ?: 'Various routes'); ?></span>
                </div>
            </div>
            <iframe
                width="100%" height="180" style="border:0; border-radius:10px; margin-top:14px;"
                loading="lazy"
                src="https://www.google.com/maps?q=<?php echo $t['latitude']; ?>,<?php echo $t['longitude']; ?>&output=embed">
            </iframe>
        </div>
    <?php endwhile; endif; ?>
</section>

<?php include 'includes/public_footer.php'; ?>
