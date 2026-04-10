<?php
if(!defined('NS1')) include __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../library/session.php';

$user_id = $_SESSION['user_id'];

// Ambil Data Hosting
$sql_hosting = "SELECT o.*, hp.nama_paket, hp.harga_per_bulan 
                FROM orders o 
                LEFT JOIN hosting_plans hp ON o.hosting_plan_id = hp.id 
                WHERE o.user_id = '$user_id' 
                ORDER BY o.id DESC";
$query_hosting = mysqli_query($conn, $sql_hosting);

$page_title = "Layanan Saya";
include __DIR__ . '/../library/header.php';
?>

<!-- Menyesuaikan dengan Breadcrumb Topbar / Container Style -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold text-dark m-0" style="font-size: 1.2rem;">Daftar Produk & Layanan</h4>
    </div>
</div>

<div class="card border-0 shadow-sm" style="background: white; border-radius: 4px;">
    
    <!-- Bagian filter dan search persis seperti di gambar -->
    <div class="card-header bg-white" style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center text-muted" style="font-size: 0.85rem;">
                <span class="me-2">Tampilkan</span>
                <select class="form-select form-select-sm shadow-none" style="width: 70px; border-radius: 4px;">
                    <option>5</option>
                    <option>10</option>
                    <option>25</option>
                </select>
                <span class="ms-2">Entri</span>
            </div>
            
            <div class="d-flex gap-2">
                <input type="text" class="form-control form-control-sm shadow-none border" placeholder="Cari nama domain" style="width: 180px; border-radius: 4px;">
                <select class="form-select form-select-sm shadow-none border" style="width: 120px; border-radius: 4px;">
                    <option>All Status</option>
                    <option>Active</option>
                    <option>Pending</option>
                </select>
                <button class="btn btn-sm text-white px-3 fw-medium" style="background: #4a7dff; border-radius: 4px;">Cari</button>
            </div>
        </div>
    </div>

    <!-- Tabel -->
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr style="border-bottom: 2px solid #eaedf1;">
                        <th class="border-0 bg-white text-muted fw-semibold" style="font-size: 0.8rem; padding: 15px 24px;">Produk atau Layanan <i class="bi bi-arrow-down-up ms-1" style="font-size: 0.65rem; opacity: 0.5;"></i></th>
                        <th class="border-0 bg-white text-muted fw-semibold" style="font-size: 0.8rem; padding: 15px 24px;">Harga</th>
                        <th class="border-0 bg-white text-muted fw-semibold text-center" style="font-size: 0.8rem; padding: 15px 24px;">Status <i class="bi bi-arrow-down-up ms-1" style="font-size: 0.65rem; opacity: 0.5;"></i></th>
                        <th class="border-0 bg-white text-muted fw-semibold text-center" style="font-size: 0.8rem; padding: 15px 24px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($query_hosting) > 0): ?>
                        <?php while($h = mysqli_fetch_assoc($query_hosting)): ?>
                        <tr class="hover-table-row" style="border-bottom: 1px solid #eaedf1;">
                            <td style="padding: 18px 24px;">
                                <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?= $h['nama_paket'] ?></div>
                                <div class="text-secondary mt-1" style="font-size: 0.8rem;"><?= $h['domain'] ?></div>
                            </td>
                            <td style="padding: 18px 24px;">
                                <span class="fw-bold text-dark" style="font-size: 0.9rem;">Rp <?= number_format($h['harga_per_bulan'], 0, ',', '.') ?></span>
                            </td>
                            <td style="padding: 18px 24px;" class="text-center">
                                <?php if($h['status'] == 'pending'): ?>
                                    <span class="badge bg-warning text-dark px-3 py-2 fw-medium shadow-sm" style="border-radius: 4px;">Pending</span>
                                <?php elseif($h['status'] == 'active'): ?>
                                    <span class="badge text-white px-3 py-2 fw-medium shadow-sm" style="background: #20c997; border-radius: 4px;">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger px-3 py-2 fw-medium shadow-sm" style="border-radius: 4px;"><?= ucfirst($h['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center" style="padding: 18px 24px;">
                                <?php if($h['status'] == 'pending'): ?>
                                    <a href="<?= base_url('hosting/invoice/'.$h['id'].'?type=hosting') ?>" class="btn btn-sm text-white px-3 fw-medium shadow-sm" style="background: #4a7dff; border-radius: 4px; font-size: 0.75rem;">
                                        Bayar Sekarang
                                    </a>
                                <?php elseif($h['status'] == 'suspended'): ?>
                                    <button class="btn btn-sm btn-outline-secondary px-3 fw-medium shadow-sm" style="border-radius: 4px; font-size: 0.75rem; opacity: 0.5; cursor: not-allowed;" disabled title="Akun disuspend">
                                        <i class="bi bi-lock me-1"></i> Suspended
                                    </button>
                                <?php else: ?>
                                    <a href="<?= base_url('hosting/manage/'.$h['id']) ?>" class="btn btn-sm btn-outline-primary px-3 fw-medium shadow-sm" style="border-radius: 4px; font-size: 0.75rem;">
                                        Manage
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center" style="padding: 3rem 1rem; background: #fafbfc;">
                                <div class="text-muted" style="font-size: 0.85rem;">Tidak ada Produk/Layanan yang dapat ditampilkan.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Footer table untuk pagination memanjang -->
    <?php if(mysqli_num_rows($query_hosting) > 0): ?>
    <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center" style="padding: 1.25rem 1.5rem; border-top: 1px solid var(--border-color);">
        <div class="d-flex align-items-center gap-2">
            <input class="form-check-input" type="checkbox" id="showCancelled" style="cursor:pointer;">
            <label class="form-check-label text-muted" for="showCancelled" style="font-size: 0.8rem; cursor:pointer;">
                Tampilkan layanan/domain dengan status Cancelled
            </label>
        </div>
        
        <nav>
            <ul class="pagination pagination-sm m-0">
                <li class="page-item disabled"><a class="page-link shadow-none border-0 text-muted" href="#">First</a></li>
                <li class="page-item disabled"><a class="page-link shadow-none border-0 text-muted" href="#">Prev</a></li>
                <li class="page-item active"><a class="page-link shadow-none border-0" href="#" style="background: #4a7dff; border-radius: 4px; color: white;">1</a></li>
                <li class="page-item disabled"><a class="page-link shadow-none border-0 text-muted" href="#">Next</a></li>
                <li class="page-item disabled"><a class="page-link shadow-none border-0 text-muted" href="#">Last</a></li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<style>
    .hover-table-row:hover { background-color: #fcfcfd; }
    .page-link.active { z-index: 3; background-color: #4a7dff; border-color: #4a7dff; }
</style>

<?php include __DIR__ . '/../library/footer.php'; ?>
