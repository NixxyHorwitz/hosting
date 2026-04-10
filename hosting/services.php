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
        <div class="row g-4 justify-content-center">
        <?php foreach ($products as $p):
            $icon_map = [
                0 => 'bi-window-stack',
                1 => 'bi-window-stack',
                2 => 'bi-wordpress',
                3 => 'bi-cloud-check',
            ];
            $icons = array_values($icon_map);
            $icon_idx = array_search($p['id'], array_column($products,'id'));
            $icon = $icons[min($icon_idx, count($icons)-1)] ?? 'bi-hdd-stack';
        ?>
        <div class="col-lg-4 col-md-6">
            <div class="product-card card h-100 p-4 border shadow-sm position-relative text-center hover-card" style="border-radius: 4px; border-color: #eaedf1 !important;">

                <div class="mb-2">
                    <i class="bi <?= $icon ?>" style="font-size: 3.5rem; color: #48cae4; font-weight: 300;"></i>
                </div>

                <div class="d-flex justify-content-center gap-2 mb-3">
                    <a href="<?= base_url('hosting/order/'.$p['id']) ?>" class="btn btn-sm text-white px-3 d-flex align-items-center" style="background: #20c997; border-radius: 20px; font-size: 0.70rem; font-weight: 600;">
                        <i class="bi bi-cart me-1"></i> Pesan
                    </a>
                </div>

                <h6 class="fw-bold text-secondary mb-1" style="font-size: 1rem;"><?= htmlspecialchars($p['nama_paket']) ?></h6>
                <div class="text-muted small mb-4">Mulai <span class="fw-bold text-dark">Rp <?= number_format($p['harga_per_bulan'], 0, ',', '.') ?></span> / bln</div>

                <div class="features-text text-start mt-auto text-secondary" style="font-size: 0.75rem; border-top: 1px solid #eaedf1; padding-top: 15px;">
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="bi bi-database me-1 text-muted"></i> Storage:</span>
                        <span class="fw-bold text-dark"><?= htmlspecialchars($p['disk_limit'] ?? '-') ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="bi bi-activity me-1 text-muted"></i> Bandwidth:</span>
                        <span class="fw-bold text-dark"><?= htmlspecialchars($p['bandwidth_limit'] ?? '-') ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><i class="bi bi-shield-check me-1 text-muted"></i> Fitur:</span>
                        <span class="fw-bold text-dark">SSL & Backup</span>
                    </div>
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
                <a href="<?= base_url('tickets/create') ?>" class="btn btn-sm btn-primary px-4 fw-medium" style="border-radius: 4px;">Buka Tiket Baru</a>
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
