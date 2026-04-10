<?php
require_once __DIR__ . '/library/admin_session.php';
if (!defined('NS1')) include __DIR__ . '/../config/database.php';

// Hanya admin yang bisa akses
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$user_id = (int)($_GET['user_id'] ?? 0);
if (!$user_id) {
    header("Location: orders?res=error&msg=" . urlencode("User ID tidak valid."));
    exit();
}

$q = mysqli_query($conn, "SELECT id, nama, email, username FROM users WHERE id = '$user_id' LIMIT 1");
$user = mysqli_fetch_assoc($q);
if (!$user) {
    header("Location: orders?res=error&msg=" . urlencode("User tidak ditemukan."));
    exit();
}

// Set session sebagai user (login paksa tanpa password)
$_SESSION['user_id']       = $user['id'];
$_SESSION['user_nama']     = $user['nama'];
$_SESSION['user_email']    = $user['email'];
$_SESSION['user_username'] = $user['username'];
$_SESSION['admin_impersonate'] = $_SESSION['admin_id']; // simpan admin_id untuk kembali

// Redirect ke halaman user melalui halaman blank  
header("Location: /admin/impersonate_redirect.php");
exit();
