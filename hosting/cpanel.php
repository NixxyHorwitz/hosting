<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/WHMClient.php';

// =========================
// VALIDASI
// =========================
if (!isset($_SESSION['user_id'])) {
    die("❌ Belum login");
}

$id = (int) $_GET['id'];

$data = $conn->query("SELECT * FROM orders WHERE id = $id")->fetch_assoc();

if (!$data) die("❌ Data tidak ditemukan");

if ($data['user_id'] != $_SESSION['user_id']) {
    die("❌ Akses ditolak");
}

$username = $data['username'];

if (empty($username)) {
    die("❌ Username kosong");
}

// =========================
// WHM
// =========================
$whm_id = (int)$data['whm_id'];
$whmQuery = mysqli_query($conn, "SELECT * FROM whm_servers WHERE id = '$whm_id'");
$whm_server = mysqli_fetch_assoc($whmQuery);
if(!$whm_server) { die("❌ Server WHM tidak ditemukan."); }
$whm = new WHMClient($whm_server['whm_host'], $whm_server['whm_username'], $whm_server['whm_token']);

try {

    $session = $whm->createCpanelSession($username);

    // =========================
    // WAJIB ADA
    // =========================
    if (!isset($session['data']['url'])) {
        echo "<pre>";
        print_r($session);
        die("❌ URL tidak ditemukan");
    }

    // =========================
    // 🔥 PAKSA URL MENGGUNAKAN HOST DARI DATABASE 
    // Mencegah WHM me-return hostname internal yang bertabrakan dengan master panel
    // =========================
    $original_url = $session['data']['url'];
    
    $parsed_original = parse_url($original_url);
    $parsed_whm = parse_url($whm_server['whm_host']);
    
    $scheme = isset($parsed_whm['scheme']) ? $parsed_whm['scheme'] : 'https';
    $host = $parsed_whm['host'];
    $port = '2083'; // Port default CPanel
    
    $path = isset($parsed_original['path']) ? $parsed_original['path'] : '';
    $query = isset($parsed_original['query']) ? '?' . $parsed_original['query'] : '';
    
    $forced_url = $scheme . '://' . $host . ':' . $port . $path . $query;

    header("Location: " . $forced_url);
    exit;

} catch (Exception $e) {
    echo "❌ " . $e->getMessage();
}
