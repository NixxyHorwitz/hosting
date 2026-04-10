<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/WHMClient.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// =========================
// VALIDASI ID
// =========================
if (!isset($_GET['id'])) {
    die("ID tidak ditemukan");
}

$id = (int) $_GET['id'];

// =========================
// AMBIL DATA ORDER
// =========================
$query = $conn->query("
    SELECT * FROM orders 
    WHERE id='$id' 
    AND user_id='".$_SESSION['user_id']."'
");

$data = $query->fetch_assoc();

if (!$data) {
    die("Akses ditolak");
}

$username = $data['username'];

if (empty($username)) {
    die("Username hosting tidak ditemukan");
}

// =========================
// INIT WHM
// =========================
$whm_id = (int)$data['whm_id'];
$whmQuery = mysqli_query($conn, "SELECT * FROM whm_servers WHERE id = '$whm_id'");
$whm_server = mysqli_fetch_assoc($whmQuery);
if(!$whm_server) { die("❌ Server WHM tidak ditemukan."); }
$whm = new WHMClient($whm_server['whm_host'], $whm_server['whm_username'], $whm_server['whm_token']);

try {

    // =========================
    // CREATE SESSION WEBMAIL
    // =========================
    $session = $whm->createWebmailSession($username);

    if (!isset($session['data']['url'])) {
        throw new Exception("Gagal membuat session Webmail");
    }

    // =========================
    // REDIRECT KE WEBMAIL
    // =========================
    header("Location: " . $session['data']['url']);
    exit;

} catch (Exception $e) {

    echo "<h3>❌ Gagal login Webmail</h3>";
    echo $e->getMessage();
}
