<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'payu.php';
require_once 'functions.php';

// Restore session from cookies if session is lost (PayU redirect clears session)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['payment_user_id'])) {
    $_SESSION['user_id'] = $_COOKIE['payment_user_id'];
    $_SESSION['name'] = $_COOKIE['payment_user_name'] ?? '';
    $_SESSION['email'] = $_COOKIE['payment_user_email'] ?? '';
    $_SESSION['role'] = $_COOKIE['payment_user_role'] ?? 'user';
}

// Get all parameters from PayU (both GET and POST)
$payuResponse = array_merge($_GET, $_POST);

// Verify hash
$hashVerified = verifyPayuHash($payuResponse);

// Get transaction ID
$txnid = $payuResponse['txnid'] ?? '';
$status = $payuResponse['status'] ?? '';
$mihpayid = $payuResponse['mihpayid'] ?? '';

// Get auction ID from UDF1
$udf1 = $payuResponse['udf1'] ?? '';
$auctionId = 0;
if (preg_match('/AUCTION_(\d+)/', $udf1, $matches)) {
    $auctionId = (int)$matches[1];
}

// Get user ID from UDF2
$userId = isset($payuResponse['udf2']) ? (int)$payuResponse['udf2'] : getCurrentUserId();

if (!$hashVerified) {
    header('Location: user_won_auctions.php?error=hash_verification_failed');
    exit;
}

if ($status !== 'success') {
    header('Location: user_won_auctions.php?error=payment_failed&status=' . urlencode($status));
    exit;
}

// Prepare response message
$responseMessage = 'Payment successful. Transaction ID: ' . $mihpayid;
if (isset($payuResponse['bank_ref_num']) && !empty($payuResponse['bank_ref_num'])) {
    $responseMessage .= ', Bank Reference: ' . $payuResponse['bank_ref_num'];
}
if (isset($payuResponse['bankcode']) && !empty($payuResponse['bankcode'])) {
    $responseMessage .= ', Payment Mode: ' . $payuResponse['bankcode'];
}

// Store full response data as JSON
$responseDataJson = json_encode($payuResponse);

// Update payment transaction
try {
    $pdo->beginTransaction();
    
    // Update payment transaction with response message and data
    $stmt = $pdo->prepare("UPDATE payment_transactions 
                          SET status = 'success', 
                              payu_transaction_id = ?,
                              response_message = ?,
                              response_data = ?,
                              updated_at = NOW()
                          WHERE transaction_id = ?");
    $stmt->execute([$mihpayid, $responseMessage, $responseDataJson, $txnid]);
    
    // Update auction payment status
    if ($auctionId > 0) {
        $stmt = $pdo->prepare("UPDATE auctions 
                              SET payment_status = 'paid',
                                  payment_date = NOW()
                              WHERE id = ? AND winner_user_id = ?");
        $stmt->execute([$auctionId, $userId]);
    }
    
    $pdo->commit();
    
    // Clear payment cookies
    setcookie('payment_user_id', '', time() - 3600, '/');
    setcookie('payment_user_name', '', time() - 3600, '/');
    setcookie('payment_user_email', '', time() - 3600, '/');
    setcookie('payment_user_role', '', time() - 3600, '/');
    setcookie('payment_transaction_id', '', time() - 3600, '/');
    setcookie('payment_auction_id', '', time() - 3600, '/');
    
    // Redirect to success page
    header('Location: user_won_auctions.php?payment=success&auction_id=' . $auctionId);
    exit;
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Payment success update error: " . $e->getMessage());
    header('Location: user_won_auctions.php?error=payment_update_failed');
    exit;
}
?>

