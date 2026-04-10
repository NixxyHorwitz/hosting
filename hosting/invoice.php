<?php
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$order_id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';

if (empty($order_id)) {
    die("Akses Ditolak: ID Invoice tidak disertakan.");
}

// Ambil data order hosting 
$sql = "SELECT i.*, o.domain, o.durasi, hp.nama_paket, hp.harga_per_bulan, u.nama, u.email, u.provinsi, u.kota, u.no_whatsapp 
        FROM invoices i 
        LEFT JOIN orders o ON i.order_id = o.id 
        LEFT JOIN hosting_plans hp ON o.hosting_plan_id = hp.id 
        LEFT JOIN users u ON i.user_id = u.id 
        WHERE i.id = '$order_id'";

$query = mysqli_query($conn, $sql);
$inv = mysqli_fetch_assoc($query);

if (!$inv) {
    die("Invoice #$order_id tidak ditemukan.");
}

$item_name     = ($inv['jenis_tagihan'] == 'baru' ? "Pendaftaran " : "Perpanjangan ") . "Paket Hosting " . $inv['nama_paket'];
$item_detail   = $inv['domain'];
$harga_bulanan = $inv['harga_per_bulan'];
$durasi        = $inv['durasi'];
$total_harga   = $inv['total_tagihan'];
$status        = $inv['status']; 
$tanggal       = $inv['date_created'];
$qr_code_url   = $inv['qr_code_url'];

$page_title = "Invoice #" . str_pad($inv['id'], 6, '0', STR_PAD_LEFT);
include __DIR__ . '/../library/header.php';
?>

<style>
    .invoice-card {
        background: #fff;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        padding: 30px 40px;
        color: #4a5568;
        font-size: 0.8rem;
    }
    .inv-header {
        border-bottom: 2px solid #f8fafc;
        padding-bottom: 20px;
        margin-bottom: 20px;
    }
    .text-unpaid { color: #f43f5e; font-weight: 800; font-size: 1.1rem; }
    .text-paid { color: #10b981; font-weight: 800; font-size: 1.1rem; }
    .text-cancelled { color: #9ca3af; font-weight: 800; font-size: 1.1rem; }
    
    .section-title {
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 8px;
        font-size: 0.8rem;
    }
    .info-box {
        color: #718096;
        line-height: 1.5;
    }
    
    .table-compact {
        border: 1px solid #edf2f7;
    }
    .table-compact th {
        background: #f8fafc;
        font-weight: 600;
        color: #4a5568;
        padding: 10px 15px;
        border-bottom: 1px solid #edf2f7;
        font-size: 0.75rem;
    }
    .table-compact td {
        padding: 12px 15px;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
        font-size: 0.75rem;
    }
    
    .total-box {
        width: 100%;
        max-width: 300px;
    }
    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        font-size: 0.75rem;
    }
    .total-row.final {
        border-top: 1px solid #e2e8f0;
        margin-top: 5px;
        padding-top: 8px;
        font-weight: 700;
        color: #2d3748;
        font-size: 0.85rem;
    }

    .qris-btn {
        display: inline-block;
        background: #fdfdfd;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        padding: 5px 15px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        cursor: pointer;
        transition: 0.2s;
    }
    .qris-btn:hover { background: #f8fafc; border-color: #94a3b8; }
    
    .qris-modal-img {
        width: 100%;
        max-width: 300px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px;
    }
</style>

<div class="row w-100 mx-0">
    <div class="col-12 p-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold m-0 text-dark" style="font-size: 1.1rem;">Invoice</h5>
            <ol class="breadcrumb m-0" style="font-size: 0.75rem;">
                <li class="breadcrumb-item"><a href="<?= base_url('hosting/billing') ?>" class="text-decoration-none">Invoices</a></li>
                <li class="breadcrumb-item active">INV-<?= str_pad($inv['id'], 6, '0', STR_PAD_LEFT); ?></li>
            </ol>
        </div>

        <div class="invoice-card mx-auto" style="max-width: 850px;">
            <!-- Header Section -->
            <div class="row align-items-center inv-header">
                <div class="col-6">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-clouds-fill fs-3 text-primary me-2"></i>
                        <div>
                            <h5 class="fw-bold text-primary m-0" style="letter-spacing: -0.5px;">sobathosting</h5>
                            <div style="font-size: 0.6rem; color: #9ca3af; margin-top:-3px;">Painless hosting solution</div>
                        </div>
                    </div>
                    <div class="text-dark fw-bold mt-3" style="font-size: 1.1rem;">INVOICE #<?= str_pad($inv['id'], 6, '0', STR_PAD_LEFT) ?></div>
                </div>
                <div class="col-6 text-end">
                    <div class="text-<?= $status ?>"><?= strtoupper($status) ?></div>
                    <div class="text-muted mt-1" style="font-size: 0.75rem;">
                        Jatuh Tempo: <span class="fw-medium text-dark"><?= date('d/m/Y', strtotime($inv['date_due'])) ?></span>
                    </div>
                    
                    <?php if($status == 'unpaid' && !empty($qr_code_url)): ?>
                    <div class="mt-2" onclick="showQRIS()">
                        <div class="qris-btn">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a2/Logo_QRIS.svg/1200px-Logo_QRIS.svg.png" height="20" alt="QRIS"><br>
                            <small class="text-muted" style="font-size:0.6rem; font-weight:600;">CLICK HERE TO PAY</small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Adresses Section -->
            <div class="row mb-4">
                <div class="col-6">
                    <div class="section-title">Penerima Invoice</div>
                    <div class="info-box">
                        <strong class="text-dark"><?= htmlspecialchars($inv['nama'] ?? '') ?></strong><br>
                        <?= htmlspecialchars($inv['email'] ?? '') ?><br>
                        <?= htmlspecialchars($inv['kota'] ?? '') . ', ' . htmlspecialchars($inv['provinsi'] ?? '') ?><br>
                        <?= htmlspecialchars($inv['no_whatsapp'] ?? '') ?>
                    </div>
                </div>
                <div class="col-6 text-end">
                    <div class="section-title">Dibayarkan Kepada</div>
                    <div class="info-box">
                        <strong class="text-dark">SobatHosting Indonesia</strong><br>
                        Jl. Teknologi Modern No. 8<br>
                        Jakarta Selatan, Indonesia 12430<br>
                        billing@sobathosting.com
                    </div>
                </div>
            </div>

            <!-- Meta Section -->
            <div class="row mb-4">
                <div class="col-6">
                    <div class="section-title">Tanggal Invoice</div>
                    <div class="info-box"><?= date('d/m/Y', strtotime($tanggal)) ?></div>
                </div>
                <div class="col-6 text-end">
                    <div class="section-title">Metode Pembayaran</div>
                    <select class="form-select form-select-sm d-inline-block shadow-none w-auto float-end" style="font-size: 0.75rem;" disabled>
                        <option>QRIS (Otomatis)</option>
                    </select>
                </div>
            </div>

            <!-- Items Table -->
            <div class="section-title mt-4">Item Invoice</div>
            <div class="table-responsive mb-4">
                <table class="table table-compact w-100">
                    <thead>
                        <tr>
                            <th style="width: 75%;">Deskripsi</th>
                            <th class="text-end">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="text-dark fw-medium mb-1"><?= $item_name ?></div>
                                <div class="text-muted" style="font-size: 0.70rem;">
                                    <?= $item_detail ?> - <?= $durasi ?> Bulan<br>
                                    (<?= date('d/m/Y', strtotime($tanggal)) ?> - <?= date('d/m/Y', strtotime($inv['date_due'] . " + $durasi month")) ?>)
                                </div>
                            </td>
                            <td class="text-end fw-medium">Rp <?= number_format($total_harga, 0, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="d-flex justify-content-end mb-5">
                <div class="total-box">
                    <div class="total-row">
                        <span>Sub Total</span>
                        <span>Rp <?= number_format($total_harga, 0, ',', '.') ?></span>
                    </div>
                    <!-- PPN Dummy like RumahWeb -->
                    <div class="total-row">
                        <span>11.00% VAT Out (11% x 0)</span>
                        <span>Rp 0</span>
                    </div>
                    <div class="total-row">
                        <span>Kredit</span>
                        <span>Rp 0</span>
                    </div>
                    <div class="total-row final">
                        <span>Total Tagihan</span>
                        <span>Rp <?= number_format($total_harga, 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>

            <!-- Transaction History -->
            <div class="table-responsive mb-5 border-top pt-4">
                <table class="table table-borderless text-center m-0" style="font-size: 0.75rem;">
                    <thead class="border-bottom text-muted">
                        <tr>
                            <th class="fw-medium py-2">Tanggal Transaksi</th>
                            <th class="fw-medium py-2">Metode Pembayaran</th>
                            <th class="fw-medium py-2">ID Transaksi</th>
                            <th class="fw-medium py-2 text-end pe-3">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($status == 'paid'): ?>
                            <tr>
                                <td class="py-3"><?= date('d/m/Y H:i', strtotime($tanggal . ' + 5 minutes')) ?></td>
                                <td class="py-3">QRIS (Otomatis)</td>
                                <td class="py-3 text-secondary"><?= explode('-', $inv['reference_id'] ?? 'TRX')[0] ?>******</td>
                                <td class="py-3 text-end pe-3">Rp <?= number_format($total_harga, 0, ',', '.') ?></td>
                            </tr>
                            <tr class="border-top">
                                <td colspan="3" class="text-end fw-bold py-3 text-dark">Sisa Tagihan</td>
                                <td class="text-end fw-bold py-3 pe-3 text-success">Rp 0</td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="py-4 text-muted bg-light border-radius" style="border-radius: 4px;">Tidak ada transaksi terkait yang ditemukan</td>
                            </tr>
                            <tr class="border-top">
                                <td colspan="3" class="text-end fw-bold py-3 text-dark">Sisa Tagihan</td>
                                <td class="text-end fw-bold py-3 pe-3 text-danger">Rp <?= number_format($total_harga, 0, ',', '.') ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer Actions -->
            <div class="d-flex justify-content-between align-items-center mt-5 pt-3 border-top">
                <a href="<?= base_url('hosting/billing') ?>" class="text-decoration-none text-primary fw-medium" style="font-size: 0.75rem;">
                    <i class="bi bi-arrow-left"></i> Semua Invoice
                </a>
                <button onclick="window.print()" class="btn btn-light border shadow-sm btn-sm px-4 fw-medium" style="font-size: 0.75rem;">
                    <i class="bi bi-download me-1"></i> Unduh
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal QRIS -->
<?php if($status == 'unpaid' && !empty($qr_code_url)): ?>
<div class="modal fade" id="modalQRIS" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light border-0 py-2">
        <h6 class="modal-title fw-bold text-dark w-100 text-center" style="font-size: 0.85rem;">Scan untuk Membayar</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size: 0.7rem;"></button>
      </div>
      <div class="modal-body text-center py-4">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a2/Logo_QRIS.svg/1200px-Logo_QRIS.svg.png" width="100" alt="Logo QRIS" class="mb-3">
        
        <div class="p-2 bg-white d-inline-block rounded shadow-sm border mb-3">
            <img src="https://asteelass.icu/<?php echo $qr_code_url; ?>" class="qris-modal-img" alt="QRIS Pembayaran">
        </div>
        
        <div class="fw-bold text-danger mb-1 fs-5">Rp <?= number_format($total_harga, 0, ',', '.') ?></div>
        <div class="text-muted small" style="font-size: 0.7rem;">Bayar menggunakan E-Wallet atau M-Banking favoritmu</div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function showQRIS() {
    if (typeof bootstrap !== 'undefined') {
        var qrisModal = new bootstrap.Modal(document.getElementById('modalQRIS'));
        qrisModal.show();
    } else {
        alert("Bootstrap JS belum ter-load!");
    }
}
</script>

<?php include __DIR__ . '/../library/footer.php'; ?>
