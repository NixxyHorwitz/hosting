<?php
if(!defined('NS1')) include __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../library/session.php';

$user_id = $_SESSION['user_id'];
$query_user = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$u = mysqli_fetch_assoc($query_user);

// Ambil paket dari DB
$plans_q = mysqli_query($conn, "SELECT * FROM hosting_plans ORDER BY harga_per_bulan ASC");
$products = mysqli_fetch_all($plans_q, MYSQLI_ASSOC);

$page_title = "Pesan Hosting";
include __DIR__ . '/../library/header.php';
?>

<!-- Clean Wrapper exact like image -->
<div class="card border-0 shadow-sm mb-4" style="background: white; border-radius: 4px;">
    
    <div class="card-header bg-white d-flex align-items-center" style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
        <i class="bi bi-hdd-stack text-primary me-2 fs-5"></i>
        <span class="fw-bold fs-6 text-dark">Layanan Baru (Hosting)</span>
    </div>
    
    <div class="card-body p-4" style="background: #fdfdfd;">
        <div class="row g-3 justify-content-center">
        <?php foreach ($products as $p):
            $icon_map = [0 => 'bi-server', 1 => 'bi-hdd-network', 2 => 'bi-cloud-check', 3 => 'bi-rocket-takeoff']; // Updated modern icons
            $icons = array_values($icon_map);
            $icon_idx = array_search($p['id'], array_column($products,'id'));
            $icon = $icons[min($icon_idx, count($icons)-1)] ?? 'bi-server';
        ?>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
            <div class="card h-100 hover-card d-flex flex-column" style="border: 1px solid #eaedf1; border-radius: 8px; overflow: hidden; background: #fff;">
                <div class="p-3 text-center border-bottom position-relative" style="background: linear-gradient(180deg, #f8fbfd 0%, #ffffff 100%);">
                    <i class="bi <?= $icon ?>" style="font-size: 2.2rem; color: #007bff;"></i>
                    <h6 class="fw-bold text-dark mt-2 mb-1" style="font-size:15px; letter-spacing: -0.3px;"><?= htmlspecialchars($p['nama_paket']) ?></h6>
                    <div class="text-primary fw-bold" style="font-size:18px;">Rp <?= number_format($p['harga_per_bulan'], 0, ',', '.') ?> <span class="text-muted fw-normal" style="font-size:11px;">/bln</span></div>
                </div>
                
                <div class="p-3 flex-grow-1 text-secondary" style="font-size: 12.5px; line-height: 1.6;">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-check-circle-fill text-success me-2" style="font-size:14px;"></i> 
                        <span><strong class="text-dark"><?= htmlspecialchars($p['disk_limit'] ?? '-') ?></strong> Storage</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-check-circle-fill text-success me-2" style="font-size:14px;"></i> 
                        <span><strong class="text-dark"><?= htmlspecialchars($p['bandwidth_limit'] ?? '-') ?></strong> Bandwidth</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-check-circle-fill text-success me-2" style="font-size:14px;"></i> 
                        <span><strong class="text-dark"><?= htmlspecialchars($p['whm_package_name'] ?? 'Dasar') ?></strong> Package</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill text-success me-2" style="font-size:14px;"></i> 
                        <span>Gratis SSL & Backup</span>
                    </div>
                </div>

                <div class="p-3 pt-0 mt-auto">
                    <a href="<?= base_url('hosting/order/'.$p['id']) ?>" class="btn btn-primary w-100 fw-bold shadow-sm" style="font-size: 13px; border-radius: 6px; transition: all 0.2s;">
                        Pilih Paket <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Bottom table representation -->
<div class="card border-0 shadow-sm" style="background: white; border-radius: 4px;">
    <div class="card-header bg-white" style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
        <span class="fw-bold fs-6 text-dark">Bantuan Ahli</span>
    </div>
    <div class="card-body p-4">
        
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center">
                <i class="bi bi-headset text-muted" style="font-size: 2.5rem; opacity: 0.5;"></i>
                <div class="ms-3">
                    <span class="fw-bold text-dark d-block">Kesulitan atau butuh bantuan teknis?</span>
                    <span class="text-secondary small">Jangan ragu buat tiket dukungan. Tim ahli kami siap membantu Anda 24/7.</span>
                </div>
            </div>
            
            <div>
                <a href="<?= base_url('hosting/tickets/create') ?>" class="btn btn-sm btn-primary px-4 fw-medium" style="border-radius: 4px;">Buka Tiket Baru</a>
            </div>
        </div>
        
    </div>
</div>

<style>
    .hover-card { transition: all 0.2s ease; box-shadow: 0 2px 5px rgba(0,0,0,0.02) !important; }
    .hover-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.05) !important; border-color: #ccd4df !important; }
    .btn-outline-primary { border-color: #007bff; color: #007bff; }
    .btn-outline-primary:hover { background: #007bff; color: white; }
</style>

<?php include __DIR__ . '/../library/footer.php'; ?>
