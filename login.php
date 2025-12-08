<?php
require_once 'auth.php';
require_once 'database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'All fields are required';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                $passwordValid = false;
                $needsHashing = false;
                
                // Check if stored password looks like a hash (starts with $2y$, $2a$, $2b$, etc.)
                $isHashed = preg_match('/^\$2[ayb]\$.{56}$/', $user['password']);
                
                if ($isHashed) {
                    // Password is hashed, use password_verify
                    if (password_verify($password, $user['password'])) {
                        $passwordValid = true;
                    }
                } else {
                    // Password appears to be plain text, compare directly
                    // This handles cases where password was updated directly in database
                    if ($user['password'] === $password) {
                        $passwordValid = true;
                        $needsHashing = true;
                    }
                }
                
                if ($passwordValid) {
                    // If password needs to be hashed (was plain text), update it now
                    if ($needsHashing) {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $updateStmt->execute([$hashedPassword, $user['id']]);
                    }
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header('Location: admin_dashboard.php');
                    } else {
                        header('Location: user_auctions.php');
                    }
                    exit();
                } else {
                    $error = 'Invalid email or password';
                }
            } else {
                $error = 'Invalid email or password';
            }
        } catch(PDOException $e) {
            $error = 'Login failed. Please try again.';
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('Login error: ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Auction Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); width: 100%; max-width: 400px; }
        h2 { color: #333; margin-bottom: 30px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        input:focus { outline: none; border-color: #667eea; }
        .btn { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; transition: 0.3s; }
        .btn:hover { background: #764ba2; }
        .error { background: #fed7d7; color: #c53030; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .links { text-align: center; margin-top: 20px; }
        .links a { color: #667eea; text-decoration: none; }
        .links a:hover { text-decoration: underline; }
        .demo-info { background: #bee3f8; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Login</h2>
        
        <div class="demo-info">
            <strong>Demo Credentials:</strong><br>
            Admin: admin@nixi.in / admin123
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
        
        <div class="links">
            Don't have an account? <a href="register.php">Register here</a><br>
            <a href="index.php">Back to Home</a>
        </div>
    </div>
</body>
</html>