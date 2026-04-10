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

$host = "kerjasama.my.id";
$user = "kerw2623_host";
$pass = "kerw2623_host";
$db   = "kerw2623_host";
// $host = "localhost";
// $user = "root";
// $pass = "";
// $db   = "hosting";
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
    if (!defined('WHM_HOST'))     define('WHM_HOST',     $set['whm_host']);
    if (!defined('WHM_USERNAME')) define('WHM_USERNAME', $set['whm_username']);
    if (!defined('WHM_TOKEN'))    define('WHM_TOKEN',    $set['whm_token']);

    // =========================
    // SITE INFO (Dinamis dari DB)
    // =========================
    if (!defined('SITE_NAME'))  define('SITE_NAME',  $set['site_name']);
    if (!defined('SITE_TITLE')) define('SITE_TITLE', $set['site_title']);
    if (!defined('SITE_DESC'))  define('SITE_DESC',  $set['site_description']);

    // =========================
    // CONTACT INFO (Dinamis dari DB)
    // =========================
    if (!defined('CONTACT_PHONE'))   define('CONTACT_PHONE',   $set['contact_phone']);
    if (!defined('CONTACT_BILLING')) define('CONTACT_BILLING', $set['contact_billing']);
    if (!defined('CONTACT_INFO'))    define('CONTACT_INFO',    $set['contact_info']);
    if (!defined('CONTACT_SUPPORT')) define('CONTACT_SUPPORT', $set['contact_support']);
}

// =========================
// DEFAULT NAMESERVER (Static)
// =========================
if (!defined('NS1')) define('NS1', $set['ns1']);
if (!defined('NS2')) define('NS2', $set['ns2']);
if (!defined('NS3')) define('NS3', $set['ns3'] ?? '');
if (!defined('NS4')) define('NS4', $set['ns4'] ?? '');