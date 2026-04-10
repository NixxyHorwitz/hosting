<?php
if(!defined('NS1')) include __DIR__ . '/../config/database.php';
include __DIR__ . '/../core/WHMClient.php';


// 1. AMBIL KONFIGURASI DARI DATABASE SETTINGS
$query_settings = mysqli_query($conn, "SELECT * FROM settings LIMIT 1");
$settings = mysqli_fetch_assoc($query_settings);

$api_url    = "https://asteelass.icu/api/deposit.php";
$api_key    = $settings['payment_api_key'];
$secret_key = $settings['payment_secret_key'];

// 2. Ambil ID paket hosting & Nama Paket WHM
$plan_id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';
$query_plan = mysqli_query($conn, "SELECT * FROM hosting_plans WHERE id = '$plan_id'");
$plan_data = mysqli_fetch_assoc($query_plan);

if (!$plan_data) { die("Paket tidak ditemukan."); }

$whm_package = $plan_data['whm_package_name']; 

$error_popup = "";

// 3. Logika Proses Checkout
if (isset($_POST['proses_checkout'])) {
    $durasi = (int)$_POST['durasi_hosting'];
    $harga_per_bulan = (int)$plan_data['harga_per_bulan'];
    $amount_to_request = $harga_per_bulan * $durasi;
    
    $domain = mysqli_real_escape_string($conn, $_POST['domain_tujuan_sendiri']);

    if (empty($domain)) {
        echo "<script>alert('Mohon masukkan nama domain Anda!'); window.history.back();</script>";
        exit();
    }

    // 4. Registrasi User Baru (Jika Belum Login)
    if (!isset($_SESSION['user_id'])) {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $cek_email = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
        
        if (mysqli_num_rows($cek_email) > 0) {
            $error_popup = "email_terdaftar";
        } else {
            $nama = mysqli_real_escape_string($conn, $_POST['nama']);
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $whatsapp = mysqli_real_escape_string($conn, $_POST['whatsapp']);
            $provinsi = mysqli_real_escape_string($conn, $_POST['provinsi']);
            $kota = mysqli_real_escape_string($conn, $_POST['kota']);

            $ins_user = "INSERT INTO users (nama, email, password, no_whatsapp, provinsi, kota, role) 
                         VALUES ('$nama', '$email', '$password_hash', '$whatsapp', '$provinsi', '$kota', 'client')";
            
            if (mysqli_query($conn, $ins_user)) {
                $_SESSION['user_id'] = mysqli_insert_id($conn);
                $_SESSION['user_nama'] = $nama;
            }
        }
    }

    // 5. Integrasi Payment Gateway & Simpan Order
    if (isset($_SESSION['user_id']) && $error_popup == "") {
        $current_user_id = $_SESSION['user_id'];
        $expiry_date = date('Y-m-d H:i:s', strtotime("+$durasi months"));

        // REQUEST KE PAYMENT GATEWAY
        $data_post = json_encode(['amount' => $amount_to_request]);
        
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_post);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-KEY: ' . $api_key,
            'X-SECRET-KEY: ' . $secret_key
        ]);

        $response = curl_exec($ch);
        $curl_err = curl_error($ch);
        $result = json_decode($response, true);
        curl_close($ch);

        if (isset($result['status']) && $result['status'] == true) {
            $reference_id  = $result['reference_id'];
            $final_amount  = $result['total_payment']; 
            // Ambil QR Code URL dari respon API
            $qr_url        = isset($result['qr_code_url']) ? $result['qr_code_url'] : '';

            // CARI WHM SERVER TERSEDIA (LOAD BALANCING)
            $whm_av_query = mysqli_query($conn, "
                SELECT w.id, w.limit_cpanel, 
                (SELECT COUNT(id) FROM orders WHERE whm_id = w.id AND status IN ('active', 'suspended')) as used_cpanel
                FROM whm_servers w
                HAVING used_cpanel < w.limit_cpanel
                ORDER BY used_cpanel ASC
                LIMIT 1
            ");
            
            if (mysqli_num_rows($whm_av_query) == 0) {
                echo "<script>alert('Saat ini server sedang penuh'); window.history.back();</script>";
                exit();
            }
            
            $whm_server = mysqli_fetch_assoc($whm_av_query);
            $whm_id = $whm_server['id'];

            mysqli_begin_transaction($conn);
            try {
                // INSERT TERMASUK whm_package_name DAN whm_id
                $sql_order = "INSERT INTO orders 
                (user_id, domain, hosting_plan_id, whm_package_name, total_harga, durasi, status, expiry_date, status_pembayaran, whm_id) 
                VALUES 
                ('$current_user_id', '$domain', '$plan_id', '$whm_package', '$final_amount', '$durasi', 'pending', '$expiry_date', 'pending', '$whm_id')";
                
                if (!mysqli_query($conn, $sql_order)) {
                    throw new Exception(mysqli_error($conn));
                }

                $order_id = mysqli_insert_id($conn);
                
                $sql_invoice = "INSERT INTO invoices
                (user_id, order_id, jenis_tagihan, total_tagihan, status, reference_id, qr_code_url, date_due)
                VALUES
                ('$current_user_id', '$order_id', 'baru', '$final_amount', 'unpaid', '$reference_id', '$qr_url', DATE_ADD(NOW(), INTERVAL 1 DAY))";
                
                if (!mysqli_query($conn, $sql_invoice)) {
                    throw new Exception(mysqli_error($conn));
                }
                
                $invoice_id = mysqli_insert_id($conn);

                mysqli_commit($conn);
                
                header("Location: /hosting/invoice/$invoice_id?msg=success");
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                die("Kesalahan database: " . $e->getMessage());
            }
        } else {
            $api_error = isset($result['message']) ? addslashes($result['message']) : 'Gagal menghubungi Payment Gateway.';
            if ($curl_err) {
                $api_error .= " (cURL Error: " . addslashes($curl_err) . ")";
            }
            echo "<script>alert('Payment API Error: " . $api_error . "'); window.history.back();</script>";
            exit();
        }
    }
}
include __DIR__ . '/../library/header.php';
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold text-dark m-0" style="font-size: 1.2rem;">Konfigurasi Layanan Baru</h4>
    </div>
</div>

<style>
    /* Desain Kartu Checkout (Clean White) */
    .checkout-card { border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); background: white; margin-bottom: 1.5rem; overflow: hidden; }
    .card-header-custom { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); background: white; }
    .card-body-custom { padding: 1.5rem; background: #fdfdfd; }

    /* Opsi Durasi */
    .duration-option { border: 1px solid var(--border-color); border-radius: 4px; padding: 1.2rem; cursor: pointer; transition: 0.2s; position: relative; background: white; }
    .duration-option:hover { border-color: #bbd6f5; background: #f8fbff; }
    .duration-option.active { border-color: #007bff; background: #ebf5ff; }
    .duration-option .check-mark { position: absolute; top: 12px; right: 12px; color: #007bff; display: none; }
    .duration-option.active .check-mark { display: block; }

    /* Ringkasan */
    .summary-card { background: white; border: 1px solid var(--border-color); border-radius: 4px; padding: 1.5rem; position: sticky; top: 2rem; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
    .btn-checkout { background: #20c997; border: none; border-radius: 4px; padding: 12px; font-weight: 600; width: 100%; transition: 0.3s; color: white; }
    .btn-checkout:hover { background: #17a97e; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(32, 201, 151, 0.3); }
    
    .form-control { border-radius: 4px; border: 1px solid var(--border-color); box-shadow: none; padding: 0.6rem 1rem;}
    .form-control:focus { border-color: #007bff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
    .input-group-text { border-radius: 4px 0 0 4px; border-color: var(--border-color); }
</style>

<form action="" method="POST" id="checkoutForm" class="w-100">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="checkout-card">
                <div class="card-header-custom d-flex align-items-center">
                    <i class="bi bi-hdd-network me-2 fs-5" style="color: #48cae4;"></i>
                    <h6 class="mb-0 fw-bold text-dark">Hubungkan Domain</h6>
                </div>
                <div class="card-body-custom">
                    <label class="form-label fw-bold text-muted" style="font-size: 0.75rem;">NAMA DOMAIN ANDA</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-globe"></i></span>
                        <input type="text" name="domain_tujuan_sendiri" class="form-control border-start-0" placeholder="contoh: websiteku.com" required>
                    </div>
                    <div class="mt-4 p-3 border" style="background: #f8fbff; border-radius: 4px; border-color: #bbd6f5 !important;">
                        <div class="d-flex">
                            <i class="bi bi-info-circle-fill text-primary me-2"></i>
                            <div>
                                <small class="d-block fw-bold text-dark">Instruksi Nameserver:</small>
                                <small class="text-secondary">Arahkan domain Anda ke nameserver berikut agar bisa terhubung dengan hosting:</small>
                                <div class="mt-2">
                                    <code class="bg-white px-2 py-1 border text-dark" style="border-radius: 3px;"><?= htmlspecialchars($settings['ns1'] ?? 'ns1.sobathosting.com') ?></code>
                                    <code class="bg-white px-2 py-1 border text-dark ms-1" style="border-radius: 3px;"><?= htmlspecialchars($settings['ns2'] ?? 'ns2.sobathosting.com') ?></code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="checkout-card">
                <div class="card-header-custom d-flex align-items-center">
                    <i class="bi bi-clock-history me-2 fs-5" style="color: #48cae4;"></i>
                    <h6 class="mb-0 fw-bold text-dark">Pilih Durasi Berlangganan</h6>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="duration-option active" onclick="updatePrice(1, this)">
                                <i class="bi bi-check-circle-fill check-mark fs-5"></i>
                                <h6 class="fw-bold mb-1 text-dark">1 Bulan</h6>
                                <div class="text-muted small">Rp <?= number_format($plan_data['harga_per_bulan']*1, 0, ',', '.'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="duration-option" onclick="updatePrice(6, this)">
                                <i class="bi bi-check-circle-fill check-mark fs-5"></i>
                                <h6 class="fw-bold mb-1 text-dark">6 Bulan</h6>
                                <div class="text-muted small">Rp <?= number_format($plan_data['harga_per_bulan']*6, 0, ',', '.'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="duration-option" onclick="updatePrice(12, this)">
                                <i class="bi bi-check-circle-fill check-mark fs-5"></i>
                                <h6 class="fw-bold mb-1 text-dark">1 Tahun</h6>
                                <div class="text-muted small">Rp <?= number_format($plan_data['harga_per_bulan']*12, 0, ',', '.'); ?></div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="durasi_hosting" id="durasi_val" value="1">
                </div>
            </div>

            <div class="checkout-card">
                <div class="card-header-custom d-flex align-items-center">
                    <i class="bi bi-person-badge me-2 fs-5" style="color: #48cae4;"></i>
                    <h6 class="mb-0 fw-bold text-dark">Detail Akun Pelanggan</h6>
                </div>
                <div class="card-body-custom">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-bold" style="font-size: 0.75rem;">NAMA LENGKAP</label>
                                <input type="text" name="nama" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-bold" style="font-size: 0.75rem;">EMAIL</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-bold" style="font-size: 0.75rem;">WHATSAPP</label>
                                <input type="number" name="whatsapp" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-bold" style="font-size: 0.75rem;">PASSWORD</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-bold" style="font-size: 0.75rem;">PROVINSI</label>
                                <input type="text" name="provinsi" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-bold" style="font-size: 0.75rem;">KOTA</label>
                                <input type="text" name="kota" class="form-control" required>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-center p-3 border" style="background: #f8fbff; border-radius: 4px; border-color: #bbd6f5 !important;">
                            <i class="bi bi-check-circle-fill text-primary fs-3 me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Anda sudah masuk sebagai <?= $_SESSION['user_nama']; ?></h6>
                                <small class="text-secondary">Pesanan ini akan langsung dikaitkan dengan akun Anda saat ini.</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div> <!-- end col-8 -->

        <div class="col-lg-4">
            <div class="summary-card">
                <h6 class="fw-bold mb-4 text-dark fs-5">Ringkasan Pesanan</h6>
                <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                    <span class="text-secondary fw-medium">Paket Hosting</span>
                    <span class="fw-bold text-dark"><?= $plan_data['nama_paket']; ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                    <span class="text-secondary fw-medium">Siklus Tagihan</span>
                    <span class="fw-bold text-dark" id="label_durasi">1 Bulan</span>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mt-4 pt-2">
                    <span class="text-secondary fw-medium">Total Bayar</span>
                    <h4 class="fw-bold mb-0 text-primary" id="total_text">Rp <?= number_format($plan_data['harga_per_bulan'], 0, ',', '.'); ?></h4>
                </div>
                
                <button type="submit" name="proses_checkout" class="btn btn-checkout mt-4 d-flex justify-content-center align-items-center">
                    Selesaikan Pembayaran <i class="bi bi-shield-lock ms-2 opacity-75"></i>
                </button>
                <div class="mt-4 text-center">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a2/Logo_QRIS.svg/1200px-Logo_QRIS.svg.png" height="25" class="opacity-75" alt="QRIS">
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    const pricePerMonth = <?= (int)$plan_data['harga_per_bulan']; ?>;
    function updatePrice(month, el) {
        document.getElementById('durasi_val').value = month;
        document.querySelectorAll('.duration-option').forEach(opt => opt.classList.remove('active'));
        el.classList.add('active');

        const total = pricePerMonth * month;
        document.getElementById('total_text').innerText = "Rp " + new Intl.NumberFormat('id-ID').format(total);
        document.getElementById('label_durasi').innerText = (month === 12) ? "1 Tahun" : month + " Bulan";
    }
</script>

<?php include __DIR__ . '/../library/footer.php'; ?>
