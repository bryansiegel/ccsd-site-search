<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Auth;
use CCSD\Search\Database;
use CCSD\Search\RateLimiter;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$auth = new Auth();
$rateLimiter = new RateLimiter();
$error = '';

if ($_POST) {
    if ($rateLimiter->isRateLimited('login')) {
        $timeLeft = $rateLimiter->getTimeUntilUnlock('login');
        $minutes = ceil($timeLeft / 60);
        $error = "Too many failed login attempts. Please try again in {$minutes} minutes.";
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($auth->login($username, $password)) {
            $rateLimiter->recordAttempt('login', true);
            header('Location: admin.php');
            exit;
        } else {
            $rateLimiter->recordAttempt('login', false);
            $remaining = $rateLimiter->getRemainingAttempts('login');
            
            if ($remaining > 0) {
                $error = "Invalid username or password. {$remaining} attempts remaining.";
            } else {
                $error = 'Too many failed attempts. Account temporarily locked.';
            }
        }
    }
}

if ($auth->isLoggedIn()) {
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CCSD Search Admin</title>
    <style>
        body {
            font-family: "proxima-nova", Helvetica, Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 400px;
            margin: 100px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1771b7;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            background-color: #1771b7;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #145a91;
        }
        .error {
            color: red;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>CCSD Search Admin</h1>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username or Email:</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>