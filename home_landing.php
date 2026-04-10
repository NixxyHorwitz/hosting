<?php
require_once __DIR__ . '/config/database.php'; 
require_once __DIR__ . '/core/api_helper.php';

if (isset($_SESSION['user_id'])) {
    header("Location: /hosting"); 
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SobatHosting — Solusi Cloud & Domain Modern</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #64748b;
            --dark: #0f172a;
            --light: #f8fafc;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #fff; color: var(--dark); }
        
        /* Navbar Modern */
        .navbar { backdrop-filter: blur(10px); background: rgba(255,255,255,0.8) !important; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .navbar-brand { font-weight: 800; letter-spacing: -1px; color: var(--primary) !important; }
        
        /* Hero Section */
        .hero { 
            padding: 140px 0 100px;
            background: radial-gradient(circle at top right, #eff6ff, transparent), 
                        radial-gradient(circle at bottom left, #f5f3ff, transparent);
        }
        .search-box {
            background: white;
            padding: 10px;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .search-box input { border: none; padding-left: 20px; outline: none !important; box-shadow: none !important; }
        .search-box btn { border-radius: 15px; }

        /* Pricing Card */
        .card-pricing {
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .card-pricing:hover {
            transform: translateY(-12px);
            border-color: var(--primary);
            box-shadow: 0 25px 50px -12px rgba(37, 99, 235, 0.15);
        }
        .card-pricing.featured {
            background: var(--dark);
            color: white;
        }
        .card-pricing.featured .text-muted { color: #94a3b8 !important; }
        
        .badge-promo {
            background: #dbeafe;
            color: var(--primary);
            font-size: 0.75rem;
            padding: 5px 12px;
            border-radius: 100px;
            font-weight: 600;
        }

        .check-result {
            animation: fadeIn 0.5s ease;
            max-width: 600px;
            margin: 20px auto;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light sticky-top">
    <div class="container">
        <a class="navbar-brand fs-3" href="#">SOBATHOS<span class="text-dark">.</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link fw-semibold mx-2" href="#hosting">Hosting</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold mx-2" href="#domain">Domain</a></li>
                <li class="nav-item ms-lg-3"><a class="btn btn-outline-dark px-4 rounded-pill fw-semibold" href="/auth/login">Login</a></li>
                <li class="nav-item ms-2"><a class="btn btn-primary px-4 rounded-pill fw-semibold" href="/auth/register">Daftar</a></li>
            </ul>
        </div>
    </div>
</nav>

<header class="hero text-center">
    <div class="container">
        <span class="badge-promo mb-3 d-inline-block">🚀 Server Generasi Terbaru 2026</span>
        <h1 class="display-3 fw-800 mb-3" style="letter-spacing: -2px;">Mulai Website Impian<br><span class="text-primary">Tanpa Batas.</span></h1>
        <p class="lead text-secondary mb-5">Dapatkan domain premium dan hosting cloud tercepat dalam hitungan detik.</p>
        
        <div class="row justify-content-center">
            <div class="col-lg-8">
              

               <?php
if (isset($_POST['check_domain'])) {
    // 1. Bersihkan input
    $full_domain = mysqli_real_escape_string($conn, trim($_POST['domain_query']));
    
    // Pisahkan nama domain dan TLD (misal: bisnissaya dan com)
    $parts = explode('.', $full_domain);
    if (count($parts) < 2) {
        echo '<div class="check-result alert alert-warning border-0 shadow-sm rounded-4 p-3">⚠️ Format domain salah. Gunakan format: namadomain.com</div>';
    } else {
        $sld = $parts[0]; // namadomain
        $tld = strtolower(end($parts)); // com

        // 2. Panggil fungsi dari api_helper.php
        // Pastikan nama fungsi sesuai dengan yang ada di api_helper.php
        $res = checkDomainAvailability($sld, $tld); 

        // 3. Ambil harga dari database lokal Anda
        $query_harga = mysqli_query($conn, "SELECT harga_jual FROM domain_prices WHERE tld = '$tld'");
        
        if ($query_harga && mysqli_num_rows($query_harga) > 0) {
            $data_harga = mysqli_fetch_assoc($query_harga);
            $harga_final = $data_harga['harga_jual'];
            $harga_tampil = "Rp " . number_format($harga_final, 0, ',', '.');
        } else {
            // Fallback jika TLD tidak ada di database domain_prices
            $harga_tampil = "Harga Hubungi Admin";
        }

        // 4. Tampilkan Hasil
        echo '<div class="check-result alert '. ($res['status'] == 'available' ? 'alert-success' : 'alert-danger') .' border-0 shadow-sm rounded-4 p-3">';
        
        if ($res['status'] == 'available') {
            echo "✨ <strong>$full_domain</strong> tersedia! — <span class='fw-bold text-primary'>$harga_tampil</span> ";
            // Kirim ke halaman register/order dengan membawa data domain
            echo "<a href='/auth/register?domain=$full_domain' class='btn btn-sm btn-primary ms-3 rounded-pill'>Beli Sekarang</a>";
        } elseif ($res['status'] == 'notavailable') {
            echo "⚠️ <strong>$full_domain</strong> sudah terdaftar. Coba nama lain!";
        } else {
            // Menampilkan pesan error dari API jika ada (seperti saldo habis/IP belum whitelist)
            echo "❌ Terjadi kesalahan: " . ($res['message'] ?? 'Gagal terhubung ke provider.');
        }
        echo '</div>';
    }
}
?>
                <div class="mt-4 d-flex justify-content-center gap-3 text-muted opacity-75">
                    <small>.com Rp 125k</small> <small>.id Rp 200k</small> <small>.net Rp 140k</small> <small>.xyz Rp 25k</small>
                </div>
            </div>
        </div>
    </div>
</header>

<section id="hosting" class="py-5">
    <div class="container py-5">
        <div class="row align-items-end mb-5">
            <div class="col-md-6">
                <h2 class="fw-bold fs-1">Pilih Paket <span class="text-primary">Cloud Hosting</span></h2>
                <p class="text-secondary">Infrastruktur NVMe SSD untuk kecepatan akses maksimal.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="bg-light d-inline-flex p-1 rounded-pill">
                    <button class="btn btn-white shadow-sm rounded-pill px-4">Bulanan</button>
                    <button class="btn btn-transparent px-4">Tahunan <span class="badge bg-success">-20%</span></button>
                </div>
            </div>
        </div>

        <div class="row g-4">
        <?php
        $sql_hosting = mysqli_query($conn, "SELECT * FROM hosting_plans");
        if (mysqli_num_rows($sql_hosting) > 0) {
            $count = 0;
            while($h = mysqli_fetch_assoc($sql_hosting)) {
                $count++;
                $is_featured = ($count == 2); // Anggap paket ke-2 adalah 'Best Seller'
        ?>
            <div class="col-md-4">
                <div class="card card-pricing h-100 p-4 <?php echo $is_featured ? 'featured' : ''; ?>">
                    <?php if($is_featured) echo '<span class="position-absolute top-0 end-0 m-3 badge rounded-pill bg-primary">Best Seller</span>'; ?>
                    <h4 class="fw-bold mb-1"><?php echo $h['nama_paket']; ?></h4>
                    <p class="<?php echo $is_featured ? 'text-muted' : 'text-secondary'; ?> small">Cocok untuk startup & UMKM</p>
                    
                    <h2 class="fw-800 my-4">
                        <small class="fs-6 fw-normal">Rp</small> <?php echo number_format($h['harga_per_bulan'], 0, ',', '.'); ?> 
                        <small class="fs-6 fw-normal opacity-75">/bln</small>
                    </h2>

                    <hr class="opacity-10">

                    <ul class="list-unstyled my-4">
                        <li class="mb-3">✅ <strong><?php echo $h['disk_limit']; ?></strong> NVMe SSD Storage</li>
                        <li class="mb-3">✅ <strong><?php echo $h['bandwidth_limit']; ?></strong> Unmetered Bandwidth</li>
                        <li class="mb-3">✅ Free SSL & Daily Backup</li>
                        <li class="mb-3">✅ LiteSpeed Web Server</li>
                    </ul>
                    
                    <a href="<?php echo base_url('hosting/order/' . $h['id']); ?>" 
                       class="btn <?php echo $is_featured ? 'btn-primary' : 'btn-outline-primary'; ?> w-100 py-3 rounded-pill fw-bold mt-auto">
                        Pilih Paket Ini
                    </a>
                </div>
            </div>
        <?php 
            }
        } else {
            echo '<div class="col-12 text-center"><p class="text-muted">Layanan belum tersedia.</p></div>';
        }
        ?>
        </div>
    </div>
</section>

<footer class="bg-white border-top py-5">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h4 class="fw-bold text-primary">SOBATHOS.</h4>
                <p class="text-secondary w-75">Memberikan layanan infrastruktur web terbaik sejak 2020 dengan teknologi server paling mutakhir.</p>
            </div>
            <div class="col-md-3">
                <h6 class="fw-bold">Layanan</h6>
                <ul class="list-unstyled text-secondary">
                    <li>Cloud Hosting</li>
                    <li>Domain Registration</li>
                    <li>VPS Indonesia</li>
                </ul>
            </div>
            <div class="col-md-3 text-md-end">
                <h6 class="fw-bold">Bantuan</h6>
                <p class="text-secondary mb-0">support@sobathosting.com</p>
                <p class="text-secondary">WhatsApp: +62 812 3456 789</p>
            </div>
        </div>
        <hr class="my-4 opacity-5">
        <p class="text-center text-muted small">&copy; 2026 SobatHosting. All Rights Reserved. Powered by WHM API.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
