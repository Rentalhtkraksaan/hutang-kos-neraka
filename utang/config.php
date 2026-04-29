<?php
session_start();

// =============================================
// DATABASE CONFIGURATION - InfinityFree Hosting
// =============================================
$host   = 'sql311.infinityfree.com';
$user   = 'if0_41774071';
$pass   = 'LYi2uX7gnlhJglj';
$dbname = 'if0_41774071_u';

// Ensure timezone is set to local
date_default_timezone_set('Asia/Jakarta');

// Connect langsung ke database yang sudah ada
// (Shared hosting tidak mengizinkan CREATE DATABASE via PHP)
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 10,
        ]
    );

    // Create settings table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Create transactions table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATETIME NOT NULL,
        type VARCHAR(255) NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        current_saldo DECIMAL(15,2) NOT NULL,
        split_details TEXT DEFAULT NULL,
        hidden TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add hidden column if not exists (for existing installs)
    try {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN hidden TINYINT(1) NOT NULL DEFAULT 0");
    } catch (PDOException $e) { /* column already exists, skip */ }

    // Initialize default settings if empty
    $defaults = [
        'saldo'          => '2065000',
        'fee_admin'      => '0',
        'fee_nama_baik'  => '0',
        'fee_akomodasi'  => '0',
        'admin_username' => 'admin',
        'admin_password' => 'admin123',
    ];

    foreach ($defaults as $key => $value) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
        $chk->execute([$key]);
        if ($chk->fetchColumn() == 0) {
            $ins = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $ins->execute([$key, $value]);
        }
    }

} catch(PDOException $e) {
    // Tampilkan error yang lebih user-friendly di production
    die("<h2 style='font-family:sans-serif;color:red;text-align:center;margin-top:50px'>
         ⚠️ Database Connection Error<br>
         <small style='color:#666;font-size:14px'>" . htmlspecialchars($e->getMessage()) . "</small>
         </h2>");
}

// Function to get current balance
function getSaldo($pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'saldo'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (float)$row['setting_value'] : 0;
}

// Function to update balance
function updateSaldo($pdo, $amount) {
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'saldo'");
    $stmt->execute([$amount]);
}

// Function to get a specific fee
function getFee($pdo, $key) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (float)$row['setting_value'] : 0;
}

// Function to update a specific fee
function updateFee($pdo, $key, $amount) {
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->execute([$amount, $key]);
}

// Get all fees at once
function getAllFees($pdo) {
    return [
        'fee_admin'     => getFee($pdo, 'fee_admin'),
        'fee_nama_baik' => getFee($pdo, 'fee_nama_baik'),
        'fee_akomodasi' => getFee($pdo, 'fee_akomodasi'),
    ];
}

// Check Auto Bunga (Setiap Tgl 7)
function checkAutoBunga($pdo) {
    $day   = (int)date('d');
    $month = (int)date('m');
    $year  = (int)date('Y');

    if ($day >= 7) {
        $key = "auto_bunga_{$month}_{$year}";
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        
        if ($stmt->fetchColumn() == 0) {
            $currentSaldo = getSaldo($pdo);
            $newSaldo = $currentSaldo + 180000;
            
            updateSaldo($pdo, $newSaldo);
            
            $stmt = $pdo->prepare("INSERT INTO transactions (date, type, amount, current_saldo, split_details) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                date('Y-m-d H:i:s'),
                'Bunga Bulanan Otomatis',
                180000,
                $newSaldo,
                null
            ]);

            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, 'true')");
            $stmt->execute([$key]);
        }
    }
}

// Run auto bunga check every time config is loaded
checkAutoBunga($pdo);

// Helper function to format Rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}
?>
