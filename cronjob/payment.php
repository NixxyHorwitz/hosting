<?php
// Sesuaikan path include dengan struktur folder Anda
include __DIR__ . '/../config/database.php';

// 1. AMBIL KONFIGURASI API DARI DATABASE
$query_settings = mysqli_query($conn, "SELECT payment_api_key, payment_secret_key FROM settings LIMIT 1");
$settings = mysqli_fetch_assoc($query_settings);

$api_key    = $settings['payment_api_key'];
$secret_key = $settings['payment_secret_key'];
$status_url = "https://asteelass.icu/api/status.php"; // Endpoint cek status sesuai instruksi

// 2. AMBIL SEMUA INVOICE YANG MASIH UNPAID
$query_invoices = mysqli_query($conn, "SELECT id, order_id, reference_id FROM invoices WHERE status = 'unpaid' AND reference_id IS NOT NULL");

echo "Memulai pengecekan status pembayaran tagihan...\n";

while ($inv = mysqli_fetch_assoc($query_invoices)) {
    $inv_id       = $inv['id'];
    $order_id     = $inv['order_id'];
    $reference_id = $inv['reference_id'];

    echo "Memeriksa Invoice ID: $inv_id (Ref: $reference_id)... ";

    // 3. REQUEST KE API STATUS
    $data_post = json_encode(['reference_id' => $reference_id]);

    $ch = curl_init($status_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_post);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-KEY: ' . $api_key,
        'X-SECRET-KEY: ' . $secret_key
    ]);

    $response = curl_exec($ch);
    $result   = json_decode($response, true);
    curl_close($ch);

    // 4. LOGIKA UPDATE DATABASE
    if (isset($result['status']) && $result['status'] == true) {
        $api_status = $result['data']['status']; // Status dari API: success, pending, atau failed

        if ($api_status == 'success') {
            // Update status invoice menjadi paid
            mysqli_query($conn, "UPDATE invoices SET status = 'paid' WHERE id = '$inv_id'");
            
            // Perbarui layanan di tabel orders 
            $update = mysqli_query($conn, "UPDATE orders SET 
                status_pembayaran = 'success', 
                status = 'active' 
                WHERE id = '$order_id'");
            
            if ($update) {
                echo "SUKSES: Pembayaran Invoice diterima, layanan diaktifkan.\n";
            }
        } elseif ($api_status == 'failed') {
            mysqli_query($conn, "UPDATE invoices SET status = 'cancelled' WHERE id = '$inv_id'");
            mysqli_query($conn, "UPDATE orders SET status_pembayaran = 'failed', status = 'cancelled' WHERE id = '$order_id'");
            echo "GAGAL: Transaksi kedaluwarsa/batal.\n";
        } else {
            echo "Masih Pending.\n";
        }
    } else {
        echo "Error API: " . ($result['message'] ?? 'Tidak ada respon') . "\n";
    }
}

echo "Proses selesai.";