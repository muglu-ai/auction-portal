
<?php
require_once 'database.php';

// Update auction statuses based on current time
function updateAuctionStatuses($pdo) {
    $now = date('Y-m-d H:i:s');
    
    // Update upcoming to active
    $stmt = $pdo->prepare("UPDATE auctions SET status = 'active' 
                          WHERE status = 'upcoming' AND start_datetime <= ?");
    $stmt->execute([$now]);
    
    // Update active to closed and determine winner
    $stmt = $pdo->prepare("SELECT id FROM auctions 
                          WHERE status = 'active' AND end_datetime <= ?");
    $stmt->execute([$now]);
    $expiredAuctions = $stmt->fetchAll();
    
    foreach ($expiredAuctions as $auction) {
        closeAuctionAndDetermineWinner($pdo, $auction['id']);
    }
}

// Close auction and determine winner
function closeAuctionAndDetermineWinner($pdo, $auctionId) {
    // Get highest bid
    $stmt = $pdo->prepare("SELECT user_id, amount FROM bids 
                          WHERE auction_id = ? ORDER BY amount DESC LIMIT 1");
    $stmt->execute([$auctionId]);
    $highestBid = $stmt->fetch();
    
    if ($highestBid) {
        // Update auction with winner
        $stmt = $pdo->prepare("UPDATE auctions SET status = 'closed', 
                              winner_user_id = ?, final_price = ? WHERE id = ?");
        $stmt->execute([$highestBid['user_id'], $highestBid['amount'], $auctionId]);
        
        // Get auction details
        $stmt = $pdo->prepare("SELECT title FROM auctions WHERE id = ?");
        $stmt->execute([$auctionId]);
        $auction = $stmt->fetch();
        
        // Send notification to winner
        $message = "Congratulations! You won the auction '{$auction['title']}' for ₹" . 
                   number_format($highestBid['amount'], 2) . 
                   ". Please complete payment within 7 days.";
        sendNotification($pdo, $highestBid['user_id'], $message);
        
        // Send email (basic implementation)
        sendWinnerEmail($pdo, $highestBid['user_id'], $auction['title'], $highestBid['amount']);
    } else {
        // No bids, just close
        $stmt = $pdo->prepare("UPDATE auctions SET status = 'closed' WHERE id = ?");
        $stmt->execute([$auctionId]);
    }
}

// Send notification
function sendNotification($pdo, $userId, $message) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->execute([$userId, $message]);
}

// Send winner email
function sendWinnerEmail($pdo, $userId, $auctionTitle, $amount) {
    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        $to = $user['email'];
        $subject = "You Won: " . $auctionTitle;
        $message = "Dear {$user['name']},\n\n" .
                   "Congratulations! You have won the auction '{$auctionTitle}' " .
                   "for ₹" . number_format($amount, 2) . ".\n\n" .
                   "Please complete your payment within 7 days.\n\n" .
                   "Thank you!";
        $headers = "From: noreply@auction.com";
        
        // Uncomment to enable actual email sending
        // mail($to, $subject, $message, $headers);
    }
}

// Get current highest bid for auction
function getCurrentHighestBid($pdo, $auctionId) {
    $stmt = $pdo->prepare("SELECT MAX(amount) as highest FROM bids WHERE auction_id = ?");
    $stmt->execute([$auctionId]);
    $result = $stmt->fetch();
    return $result['highest'] ?? 0;
}

// Check if user has bid on auction
function userHasBid($pdo, $auctionId, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bids 
                          WHERE auction_id = ? AND user_id = ?");
    $stmt->execute([$auctionId, $userId]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

// Format Indian Rupees
function formatINR($amount) {
    return '₹' . number_format($amount, 2);
}

// Rate limiting for bidding
function checkBidRateLimit($userId) {
    if (!isset($_SESSION['bid_attempts'])) {
        $_SESSION['bid_attempts'] = [];
    }
    
    $now = time();
    $_SESSION['bid_attempts'] = array_filter(
        $_SESSION['bid_attempts'], 
        function($timestamp) use ($now) {
            return ($now - $timestamp) < 60; // Last 60 seconds
        }
    );
    
    if (count($_SESSION['bid_attempts']) >= 10) {
        return false; // Too many attempts
    }
    
    $_SESSION['bid_attempts'][] = $now;
    return true;
}

// Common header for pages
function renderHeader($title, $isAdmin = false) {
    $homeLink = $isAdmin ? 'admin_dashboard.php' : 'user_auctions.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - Auction Portal</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; background: #f5f5f5; }
            .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .navbar-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 0 20px; }
            .navbar h1 { font-size: 24px; }
            .navbar nav a { color: white; text-decoration: none; margin-left: 20px; padding: 8px 15px; border-radius: 5px; transition: 0.3s; }
            .navbar nav a:hover { background: rgba(255,255,255,0.2); }
            .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
            .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
            .btn { padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border: none; border-radius: 5px; cursor: pointer; display: inline-block; transition: 0.3s; }
            .btn:hover { background: #764ba2; }
            .btn-danger { background: #e53e3e; }
            .btn-danger:hover { background: #c53030; }
            .btn-success { background: #48bb78; }
            .btn-success:hover { background: #38a169; }
            .btn-secondary { background: #718096; }
            .btn-secondary:hover { background: #4a5568; }
            table { width: 100%; border-collapse: collapse; }
            table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
            table th { background: #f7fafc; font-weight: bold; color: #2d3748; }
            table tr:hover { background: #f7fafc; }
            .form-group { margin-bottom: 20px; }
            .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #2d3748; }
            .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 5px; font-size: 14px; }
            .form-group textarea { min-height: 100px; resize: vertical; }
            .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .alert-error { background: #fed7d7; color: #c53030; }
            .alert-success { background: #c6f6d5; color: #22543d; }
            .alert-info { background: #bee3f8; color: #2c5282; }
            .badge { padding: 5px 10px; border-radius: 3px; font-size: 12px; font-weight: bold; }
            .badge-upcoming { background: #feebc8; color: #c05621; }
            .badge-active { background: #c6f6d5; color: #22543d; }
            .badge-closed { background: #e2e8f0; color: #4a5568; }
            .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
            .auction-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: 0.3s; }
            .auction-card:hover { transform: translateY(-5px); box-shadow: 0 5px 20px rgba(0,0,0,0.15); }
            .auction-card h3 { color: #2d3748; margin-bottom: 10px; }
            .auction-card p { color: #718096; margin-bottom: 10px; font-size: 14px; }
            .price { font-size: 24px; font-weight: bold; color: #667eea; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class="navbar">
            <div class="navbar-content">
                <h1>🎯 Auction Portal</h1>
                <nav>
                    <a href="<?php echo $homeLink; ?>">Home</a>
                    <?php if ($isAdmin): ?>
                        <a href="admin_add_auction.php">Add Auction</a>
                        <a href="admin_upload_excel.php">Upload Excel</a>
                        <a href="admin_completed.php">Completed</a>
                    <?php else: ?>
                        <a href="user_auctions.php">Auctions</a>
                        <a href="user_my_bids.php">My Bids</a>
                        <a href="user_won_auctions.php">Won Auctions</a>
                        <a href="user_notifications.php">Notifications</a>
                    <?php endif; ?>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </div>
        <div class="container">
    <?php
}

function renderFooter() {
    ?>
        </div>
    </body>
    </html>
    <?php
}
?>