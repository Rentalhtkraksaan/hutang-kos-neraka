<?php
require_once '../config.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usernameInput = $_POST['username'] ?? '';
    $passwordInput = $_POST['password'] ?? '';

    // Fetch credentials from DB
    $stmtU = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_username'");
    $stmtU->execute();
    $dbUser = $stmtU->fetchColumn();

    $stmtP = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_password'");
    $stmtP->execute();
    $dbPass = $stmtP->fetchColumn();

    if ($usernameInput === $dbUser && $passwordInput === $dbPass) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Login - Tagihan Hoirul</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
    <style>
        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            width: 100%;
            background-color: #f7f8f9;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            text-align: center;
            background: #fff;
            padding: 30px 20px;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .login-card h2 {
            margin-bottom: 24px;
            color: #333;
            font-size: 22px;
            font-weight: 700;
        }
        .error-msg {
            color: #d32f2f;
            background: #ffebee;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <h2>Dashboard Admin</h2>
        
        <?php if($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group" style="text-align: left;">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" required autocomplete="off">
            </div>
            <div class="form-group" style="text-align: left;">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn-primary" style="margin-top: 10px;">Masuk</button>
        </form>
        
        <div style="margin-top: 25px;">
            <a href="../index.php" style="color: #666; text-decoration: none; font-size: 14px;">Kembali ke Halaman Utama</a>
        </div>
    </div>
</div>

</body>
</html>
