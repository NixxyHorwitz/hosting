<?php
// Gunakan path absolut agar aman saat dijalankan di CRON JOB
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/WHMClient.php';
require_once __DIR__ . '/../core/mailer.php';

$now = date('Y-m-d H:i:s');

echo "--- Memulai Cron Unsuspend Otomatis: $now ---\n";

/**
 * ASUMSI: 
 * 1. Kolom 'status' adalah status cPanel (active, suspended)
 * 2. Kolom 'status_pembayaran' adalah tanda user sudah bayar (success, paid)
 */

// Cari order yang pembayarannya SUDAH lunas tapi status akunnya MASIH belum aktif
$query = mysqli_query($conn, "SELECT orders.id, orders.username, orders.durasi, orders.domain, orders.expiry_date, orders.whm_id, users.email, users.nama 
                              FROM orders 
                              LEFT JOIN users ON orders.user_id = users.id
                              WHERE orders.status_pembayaran = 'success' 
                              AND (orders.status = 'suspended' OR orders.status = 'pending')");

if (mysqli_num_rows($query) > 0) {
    while ($data = mysqli_fetch_assoc($query)) {
        $id = $data['id'];
        $cp_user = $data['username'];
        $whm_id = (int)$data['whm_id'];

        // Ambil Data WHM Node
        $whmQuery = mysqli_query($conn, "SELECT * FROM whm_servers WHERE id = '$whm_id'");
        $whm_server = mysqli_fetch_assoc($whmQuery);
        
        if(!$whm_server) {
            echo "[ERROR] Gagal memproses $cp_user: WHM Node $whm_id tidak ditemukan.\n";
            continue;
        }

        $whm = new WHMClient($whm_server['whm_host'], $whm_server['whm_username'], $whm_server['whm_token']);
        $durasi_tambah = $data['durasi'];

        // Hitung Tanggal Baru
        // Jika akun sudah expired, hitung dari hari ini. 
        // Jika belum expired (perpanjang dini), tambahkan dari tanggal expired lama.
        $base_date = (strtotime($data['expiry_date']) > time()) ? $data['expiry_date'] : date('Y-m-d H:i:s');
        $new_expiry = date('Y-m-d H:i:s', strtotime("+$durasi_tambah months", strtotime($base_date)));

        try {
            // 1. Perintah Unsuspend ke WHM
            $whm->unsuspendAccount($cp_user);

            // 2. Update status akun menjadi 'active' dan perbarui expiry_date
            $update = mysqli_query($conn, "UPDATE orders SET 
                status = 'active', 
                expiry_date = '$new_expiry' 
                WHERE id = '$id'");

            if ($update) {
                echo "[SUCCESS] Akun $cp_user berhasil di-unsuspend. Masa aktif baru: $new_expiry\n";
                sendEmailTemplate($data['email'], $data['nama'], 'unsuspend_hosting', [
                    'nama' => $data['nama'],
                    'domain' => $data['domain']
                ]);
            }
        } catch (Exception $e) {
            echo "[ERROR] Gagal unsuspend $cp_user: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "Tidak ada pembayaran baru yang butuh di-unsuspend.\n";
}

echo "--- Proses Selesai ---\n";