
<?php
require_once 'auth.php';
requireLogin();
header('Location: user_auctions.php');
exit();
?>