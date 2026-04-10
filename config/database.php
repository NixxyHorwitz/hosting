<?php
// 1. Matikan output buffering agar tidak ada spasi yang bocor
ob_start();

// 2. Pengaturan Session yang Ketat & Universal
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_path', '/'); 
    ini_set('session.gc_maxlifetime', 3600);
    session_set_cookie_params(3600, '/', '', true, true);
    session_start();
}

// 3. Fungsi Base URL
if (!function_exists('base_url')) {
  function base_url(string $path = ''): string
  {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
      $scheme = 'https';
    }
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root   = rtrim($scheme . '://' . $host . '/', '/') . '/';
    return $root . ltrim($path, '/');
  }
}


// 4. Koneksi Database
$host = "localhost";
$user = "root";
$pass = "";
$db   = "hosting";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// 5. Penarikan Data Settings Otomatis
$query_settings = mysqli_query($conn, "SELECT * FROM settings WHERE id = 1");
$set = mysqli_fetch_assoc($query_settings);

if ($set) {
    // =========================
    // WHM CONFIG (Dinamis dari DB)
    // =========================
    define('WHM_HOST',     $set['whm_host']);
    define('WHM_USERNAME', $set['whm_username']);
    define('WHM_TOKEN',    $set['whm_token']);

    // =========================
    // SITE INFO (Dinamis dari DB)
    // =========================
    define('SITE_NAME', $set['site_name']);
    define('SITE_TITLE', $set['site_title']);
    define('SITE_DESC', $set['site_description']);
    
    // =========================
    // CONTACT INFO (Dinamis dari DB)
    // =========================
    define('CONTACT_PHONE', $set['contact_phone'] ?? '0274-892257');
    define('CONTACT_BILLING', $set['contact_billing'] ?? 'billing@rumahweb.com');
    define('CONTACT_INFO', $set['contact_info'] ?? 'info@rumahweb.com');
    define('CONTACT_SUPPORT', $set['contact_support'] ?? 'teknis@rumahweb.com');
}

// =========================
// DEFAULT NAMESERVER (Static)
// =========================
    define('NS1', $set['ns1']);
    define('NS2', $set['ns2']);
    define('NS3', $set['ns3'] ?? ''); 
    define('NS4', $set['ns4'] ?? '');