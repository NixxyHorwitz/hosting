<?php
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../library/session.php';

$user_id = $_SESSION['user_id'];
$user_nama = htmlspecialchars($_SESSION['user_nama'], ENT_QUOTES, 'UTF-8');

// 1. Ambil Statistik User
// Hitung Layanan Aktif (Status 'active' - Anda bisa sesuaikan statusnya di DB)
$query_active = mysqli_query($conn, "SELECT COUNT(id) as total FROM orders WHERE user_id = '$user_id' AND status = 'active'");
$data_active = mysqli_fetch_assoc($query_active);

// Hitung Invoice Pending
$query_pending = mysqli_query($conn, "SELECT COUNT(id) as total FROM orders WHERE user_id = '$user_id' AND status = 'pending'");
$data_pending = mysqli_fetch_assoc($query_pending);

// Hitung Tiket Open
$query_tickets = mysqli_query($conn, "SELECT COUNT(id) as total FROM tickets WHERE user_id = '$user_id' AND status IN ('Open','Customer-Reply')");
$data_tickets = mysqli_fetch_assoc($query_tickets);

// 2. Ambil 5 Pesanan Terakhir
$query_orders = mysqli_query($conn, "SELECT orders.*, hosting_plans.nama_paket 
                                     FROM orders 
                                     JOIN hosting_plans ON orders.hosting_plan_id = hosting_plans.id 
                                     WHERE orders.user_id = '$user_id' 
                                     ORDER BY orders.id DESC LIMIT 5");

include __DIR__ . '/../library/header.php';
?>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card stat-card p-3">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-primary text-white shadow-sm me-3">
                        <i class="bi bi-hdd-stack"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold m-0"><?php echo $data_active['total']; ?></h4>
                        <small class="text-muted">Hosting Aktif</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-3">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-warning text-white shadow-sm me-3">
                        <i class="bi bi-credit-card"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold m-0"><?php echo $data_pending['total']; ?></h4>
                        <small class="text-muted">Menunggu Pembayaran</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-3">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-success text-white shadow-sm me-3">
                        <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold m-0"><?php echo $data_tickets['total']; ?></h4>
                        <small class="text-muted">Tiket Support</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-custom border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold m-0"><i class="bi bi-clock-history me-2 text-primary"></i> Pesanan Terakhir</h5>
            <a href="orders.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">Lihat Semua</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Paket / Domain</th>
                        <th>Tanggal Pesan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($query_orders) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($query_orders)): ?>
                        <tr>
                            <td>
                                <span class="fw-bold text-dark d-block"><?php echo $row['nama_paket']; ?></span>
                                <small class="text-muted"><?php echo $row['domain']; ?></small>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['id'])); // Contoh, ganti dengan kolom tgl jika ada ?></td>
                            <td>
                                <?php if($row['status'] == 'pending'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill">Pending</span>
                                <?php elseif($row['status'] == 'active'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill"><?php echo $row['status']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= base_url('hosting/invoice/' . $row['id']) ?>" class="btn btn-light btn-sm rounded-pill px-3">Detail</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5">
                                <img src="https://illustrations.popsy.co/blue/online-shopping.svg" alt="Empty" style="width: 150px;" class="mb-3">
                                <p class="text-muted">Belum ada pesanan layanan.</p>
                                <a href="hosting.php" class="btn btn-primary rounded-pill">Pesan Sekarang</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleMenu() {
        document.getElementById('sidebar').classList.toggle('active');
    }
</script>
</body>
</html>
