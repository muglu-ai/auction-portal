<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

// Update auction statuses
updateAuctionStatuses($pdo);

// Redirect based on login status
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: user_auctions.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auction Portal - Home</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center; max-width: 500px; }
        h1 { color: #333; margin-bottom: 20px; }
        p { color: #666; margin-bottom: 30px; }
        .buttons { display: flex; gap: 20px; justify-content: center; }
        .btn { padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; transition: 0.3s; }
        .btn:hover { background: #764ba2; }
        .btn-secondary { background: #48bb78; }
        .btn-secondary:hover { background: #38a169; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 Auction Portal</h1>
        <p>Welcome to the online auction platform. Place bids and win amazing items!</p>
        <div class="buttons">
            <a href="login.php" class="btn">Login</a>
            <a href="register.php" class="btn btn-secondary">Register</a>
        </div>
    </div>
</body>
</html>