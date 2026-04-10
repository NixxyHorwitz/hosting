<?php
require_once __DIR__ . '/library/admin_session.php';
if (!defined('NS1')) include __DIR__ . '/../config/database.php';

if (isset($_GET['update_status']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $new_status = mysqli_real_escape_string($conn, $_GET['update_status']);
    $update = mysqli_query($conn, "UPDATE invoices SET status = '$new_status' WHERE id = '$id'");
    if ($update) header("Location: invoices?res=success&msg=" . urlencode("Status Invoice #$id diubah ke $new_status"));
    else         header("Location: invoices?res=error&msg=" . urlencode("Gagal mengubah status invoice."));
    exit();
}

$result = mysqli_query($conn, "SELECT invoices.*, users.nama as nama_user, users.email 
          FROM invoices LEFT JOIN users ON invoices.user_id = users.id 
          ORDER BY invoices.date_created DESC");

// Stats
$stats_q = mysqli_fetch_assoc(mysqli_query($conn, "SELECT 
    COUNT(*) total,
    SUM(status='paid') s_paid,
    SUM(status='unpaid') s_unpaid,
    SUM(status='cancelled') s_cancelled,
    SUM(CASE WHEN status='unpaid' AND date_due < NOW() THEN 1 ELSE 0 END) s_overdue,
    SUM(CASE WHEN status='paid' THEN total_tagihan ELSE 0 END) rev_paid,
    SUM(CASE WHEN status='unpaid' THEN total_tagihan ELSE 0 END) rev_pending
    FROM invoices"));

$page_title = "Data Invoices";
include __DIR__ . '/library/header.php';
?>

<style>
.stat-mini { display:flex; align-items:center; gap:10px; padding:14px 18px; background:var(--card); border:1px solid var(--border); border-radius:10px; }
.stat-mini-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.stat-mini-val { font-size:20px; font-weight:800; line-height:1; }
.stat-mini-lbl { font-size:10px; color:var(--mut); font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }
.quick-badge { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:5px; font-size:10px; font-weight:700; border:1px solid; white-space:nowrap; }
.qb-ok  { color:var(--ok);   background:var(--oks); border-color:var(--ob); }
.qb-err { color:var(--err);  background:var(--es);  border-color:#3d1a1a; }
.qb-warn{ color:var(--warn); background:var(--ws);  border-color:#3d2e0a; }
.qb-muted{ color:var(--sub); background:var(--surface); border-color:var(--border); }
.inv-num { font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:700; color:var(--accent); }
.user-link { font-weight:600; cursor:pointer; transition:color .15s; }
.user-link:hover { color:var(--accent) !important; }
.due-overdue { color:var(--err) !important; font-weight:700; }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="ph-fill ph-receipt me-2" style="color:var(--accent);"></i> Manajemen Invoice</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb bc">
            <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>">Admin</a></li>
            <li class="breadcrumb-item active">Invoices</li>
        </ol></nav>
    </div>
    <?php if($stats_q['s_overdue'] > 0): ?>
    <div class="quick-badge qb-err" style="padding:6px 12px; font-size:12px;">
        <i class="ph-fill ph-warning me-1"></i> <?= $stats_q['s_overdue'] ?> Invoice Jatuh Tempo!
    </div>
    <?php endif; ?>
</div>

<!-- Stats Bar -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--as);color:var(--accent);"><i class="ph-fill ph-receipt"></i></div>
            <div><div class="stat-mini-val"><?= $stats_q['total'] ?></div><div class="stat-mini-lbl">Total Invoice</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-check-circle"></i></div>
            <div><div class="stat-mini-val" style="color:var(--ok);"><?= $stats_q['s_paid'] ?></div><div class="stat-mini-lbl">Lunas</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--ws);color:var(--warn);"><i class="ph-fill ph-clock"></i></div>
            <div><div class="stat-mini-val" style="color:var(--warn);"><?= $stats_q['s_unpaid'] ?></div><div class="stat-mini-lbl">Belum Lunas</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-money"></i></div>
            <div>
                <div class="stat-mini-val" style="color:var(--ok);font-size:14px;">Rp <?= number_format($stats_q['rev_paid'],0,',','.') ?></div>
                <div class="stat-mini-lbl">Revenue Lunas</div>
            </div>
        </div>
    </div>
</div>

<div class="card-c">
    <div class="ch d-flex align-items-center justify-content-between">
        <h3 class="ct"><i class="ph-fill ph-list-bullets me-2 text-primary"></i> Semua Data Tagihan</h3>
        <div class="d-flex gap-2">
            <span class="quick-badge qb-muted"><?= $stats_q['total'] ?> invoice</span>
            <?php if($stats_q['rev_pending'] > 0): ?>
            <span class="quick-badge qb-warn"><i class="ph ph-clock me-1"></i> Rp <?= number_format($stats_q['rev_pending'],0,',','.') ?> pending</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="cb p-0">
        <div class="table-responsive">
            <table class="tbl table-hover w-100" id="tableInvoices" data-order='[[0,"desc"]]' style="font-size:12.5px;">
                <thead>
                    <tr>
                        <th style="width:100px;">Invoice #</th>
                        <th>Pelanggan</th>
                        <th>Jenis Tagihan</th>
                        <th>Total</th>
                        <th>Terbit</th>
                        <th>Jatuh Tempo</th>
                        <th class="text-center">Status</th>
                        <th style="width:70px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)):
                        $is_overdue = ($row['date_due'] && time() > strtotime($row['date_due']) && $row['status'] == 'unpaid');
                    ?>
                    <tr onclick="if(event.target.closest('.dropdown') || event.target.closest('.view-user-detail')) return; window.open('<?= base_url('hosting/invoice/' . $row['id']) ?>', '_blank')" style="cursor:pointer;">
                        <td>
                            <span class="inv-num">INV-<?= str_pad($row['id'],6,'0',STR_PAD_LEFT) ?></span>
                            <?php if($row['order_id']): ?>
                            <div style="font-size:10px;color:var(--mut);margin-top:2px;"><i class="ph ph-shopping-cart"></i> #<?= $row['order_id'] ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="user-link view-user-detail" data-userid="<?= $row['user_id'] ?>" style="color:var(--text);">
                                <?= htmlspecialchars($row['nama_user'] ?? 'Guest') ?>
                            </div>
                            <div style="font-size:11px;color:var(--mut);margin-top:2px;">
                                <i class="ph-fill ph-envelope" style="color:var(--accent);"></i> <?= htmlspecialchars($row['email'] ?? '') ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:12px;font-weight:600;text-transform:capitalize;">
                                <?= str_replace('_', ' ', htmlspecialchars($row['jenis_tagihan'])) ?>
                            </div>
                            <?php if($row['reference_id']): ?>
                            <div style="font-size:10px;color:var(--mut);margin-top:2px;font-family:monospace;">
                                <?= htmlspecialchars($row['reference_id']) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-bold" style="color:var(--ok);font-size:13px;">Rp <?= number_format($row['total_tagihan'],0,',','.') ?></div>
                        </td>
                        <td>
                            <div style="font-size:12px;"><?= date('d M Y', strtotime($row['date_created'])) ?></div>
                            <div style="font-size:10px;color:var(--mut);"><?= date('H:i', strtotime($row['date_created'])) ?></div>
                        </td>
                        <td>
                            <?php if($row['date_due']): ?>
                                <div style="font-size:12px;" class="<?= $is_overdue ? 'due-overdue' : '' ?>">
                                    <?= date('d M Y', strtotime($row['date_due'])) ?>
                                </div>
                                <?php if($is_overdue): ?>
                                <span class="quick-badge qb-err" style="margin-top:2px;"><i class="ph-fill ph-warning"></i> Lewat!</span>
                                <?php endif; ?>
                            <?php else: ?><span style="color:var(--mut);">—</span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($row['status'] == 'paid'): ?>
                                <span class="quick-badge qb-ok"><i class="ph-fill ph-check-circle"></i> PAID</span>
                            <?php elseif($row['status'] == 'cancelled'): ?>
                                <span class="quick-badge qb-err"><i class="ph-fill ph-x-circle"></i> BATAL</span>
                            <?php else: ?>
                                <span class="quick-badge <?= $is_overdue ? 'qb-err' : 'qb-warn' ?>"><i class="ph ph-clock"></i> UNPAID</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="ab py-1 px-2" type="button" data-bs-toggle="dropdown">
                                    <i class="ph ph-dots-three-outline-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-dark p-1" style="background:var(--surface);border:1px solid var(--border);box-shadow:0 8px 24px rgba(0,0,0,.5);font-size:12px;min-width:155px;">
                                    <li><a class="dropdown-item rounded py-1 mb-1" href="?update_status=paid&id=<?= $row['id'] ?>" onclick="return confirm('Tandai LUNAS?')" style="color:var(--ok);">
                                        <i class="ph-fill ph-check-circle me-2"></i> Lunas (Paid)</a></li>
                                    <li><a class="dropdown-item rounded py-1 mb-1" href="?update_status=unpaid&id=<?= $row['id'] ?>" onclick="return confirm('Ubah ke UNPAID?')" style="color:var(--warn);">
                                        <i class="ph-fill ph-clock me-2"></i> Belum Lunas</a></li>
                                    <li><hr class="dropdown-divider my-1" style="border-color:var(--border);"></li>
                                    <li><a class="dropdown-item rounded py-1" href="?update_status=cancelled&id=<?= $row['id'] ?>" onclick="return confirm('Batalkan invoice ini?')" style="color:var(--err);">
                                        <i class="ph-fill ph-x-circle me-2"></i> Batalkan</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php if(isset($_GET['res'])): ?>
        <?php if($_GET['res'] == 'success'): ?>
        Swal.fire({ icon:'success', title:'Berhasil!', text:'<?= htmlspecialchars($_GET['msg'] ?? '') ?>', timer:2500, showConfirmButton:false, background:'var(--card)', color:'var(--text)' });
        <?php elseif($_GET['res'] == 'error'): ?>
        Swal.fire({ icon:'error', title:'Gagal!', text:'<?= htmlspecialchars($_GET['msg'] ?? '') ?>', background:'var(--card)', color:'var(--text)', confirmButtonColor:'var(--accent)' });
        <?php endif; ?>
    <?php endif; ?>
</script>

<?php include __DIR__ . '/library/footer.php'; ?>
