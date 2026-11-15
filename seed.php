<?php
// seed.php - Run once to populate demo test accounts
require_once __DIR__ . '/config/database.php';

$defaultPassword = 'Password123!';
$hashedPassword = password_hash($defaultPassword, PASSWORD_BCRYPT, ['cost' => 12]); // Secure Bcrypt Hash

try {
    // 1. Akun Admin
    $stmt = $pdo->prepare("INSERT INTO users (nama, email, password, role) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE password = VALUES(password)");
    $stmt->execute(['Administrator', 'admin@cybersec.local', $hashedPassword, 'admin']);

    // 2. Akun Employee
    $stmt->execute(['Ade Imron', 'pegawai@cybersec.local', $hashedPassword, 'employee']);

    echo "<h3>Demo Accounts Seeded Successfully!</h3>";
    echo "<ul>";
    echo "<li><b>Admin:</b> admin@cybersec.local | <b>Password:</b> Password123!</li>";
    echo "<li><b>Employee:</b> pegawai@cybersec.local | <b>Password:</b> Password123!</li>";
    echo "</ul>";
    echo "<p><a href='login.php'>Proceed to Login Page &rarr;</a></p>";
} catch (PDOException $e) {
    echo "Seeding failed: " . $e->getMessage();
}