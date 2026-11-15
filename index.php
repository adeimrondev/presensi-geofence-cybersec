<?php
// index.php - Gatekeeper & Pengarah Alur Sesi
require_once __DIR__ . '/config/database.php';

// Jika belum login, lempar ke halaman login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Jika sudah login, arahkan sesuai peran (RBAC)
if ($_SESSION['role'] === 'admin') {
    header("Location: admin/dashboard.php");
} else {
    header("Location: employee/attendance.php");
}
exit;