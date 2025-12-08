<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireAdmin();

$auctionId = intval($_GET['id'] ?? 0);

// Get auction details
$stmt = $pdo->prepare("SELECT * FROM auctions WHERE id = ?");
$stmt->execute([$auctionId]);
$auction = $stmt->fetch();

if (!$auction) {
    header('Location: admin_dashboard.php');
    exit();
}

// Get all bids
$stmt = $pdo->prepare("SELECT b.*, u.name as bidder_name, u.email as bidder_email 
                       FROM bids b 
                       JOIN users u ON b.user_id = u.id 
                       WHERE b.auction_id = ? 
                       ORDER BY b.amount DESC, b.created_at ASC");
$stmt->execute([$auctionId]);
$bids = $stmt->fetchAll();

renderHeader('View Bids', true);
?>

<div class="card">
    <h2>Bids for: <?php echo htmlspecialchars($auction['title']); ?></h2>
    
    <div style="background: #f7fafc; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <p><strong>Status:</strong> <span class="badge badge-<?php echo $auction['status']; ?>"><?php echo strtoupper($auction['status']); ?></span></p>
        <p><strong>Base Price:</strong> <?php echo formatINR($auction['base_price']); ?></p>
        <?php if ($auction['winner_user_id']): ?>
            <p><strong>Winner:</strong> User ID <?php echo $auction['winner_user_id']; ?></p>
            <p><strong>Winning Amount:</strong> <?php echo formatINR($auction['final_price']); ?></p>
            <p><strong>Payment Status:</strong> <span class="badge badge-<?php echo $auction['payment_status']; ?>"><?php echo strtoupper($auction['payment_status']); ?></span></p>
        <?php endif; ?>
    </div>
    
    <?php if (empty($bids)): ?>
        <p style="color: #718096;">No bids placed yet.</p>
    <?php else: ?>
        <h3>All Bids (<?php echo count($bids); ?>)</h3>
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Bidder Name</th>
                    <th>Bidder Email</th>
                    <th>Bid Amount</th>
                    <th>Bid Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bids as $index => $bid): ?>
                <tr style="<?php echo $index === 0 ? 'background: #c6f6d5;' : ''; ?>">
                    <td><?php echo $index + 1; ?><?php echo $index === 0 ? ' 🏆' : ''; ?></td>
                    <td><?php echo htmlspecialchars($bid['bidder_name']); ?></td>
                    <td><?php echo htmlspecialchars($bid['bidder_email']); ?></td>
                    <td><strong><?php echo formatINR($bid['amount']); ?></strong></td>
                    <td><?php echo date('d-M-Y H:i:s', strtotime($bid['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <div style="margin-top: 20px;">
        <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</div>

<?php renderFooter(); ?>
