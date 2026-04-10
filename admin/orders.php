<?php
require_once __DIR__ . '/library/admin_session.php';
if(!defined('NS1')) include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/WHMClient.php';

if (isset($_GET['update_status']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $new_status = mysqli_real_escape_string($conn, $_GET['update_status']);
    
    $check_order = mysqli_query($conn, "SELECT o.username, o.whm_id, o.domain, u.email, u.nama 
                                        FROM orders o LEFT JOIN users u ON o.user_id = u.id 
                                        WHERE o.id = '$id'");
    $order_data = mysqli_fetch_assoc($check_order);
    if(!$order_data) { header("Location: orders?res=error&msg=" . urlencode("Pesanan tidak ditemukan.")); exit(); }
    
    $cp_user = $order_data['username'];
    $whm_id = (int)$order_data['whm_id'];
    $u_email = $order_data['email'];
    $u_nama = $order_data['nama'];
    $domain = $order_data['domain'];

    $whmQuery = mysqli_query($conn, "SELECT * FROM whm_servers WHERE id = '$whm_id'");
    $whm_server = mysqli_fetch_assoc($whmQuery);
    if(!$whm_server) { header("Location: orders?res=error&msg=" . urlencode("Database Node Server WHM tidak ditemukan.")); exit(); }
    
    require_once __DIR__ . '/../core/mailer.php';
    $whm = new WHMClient($whm_server['whm_host'], $whm_server['whm_username'], $whm_server['whm_token']);
    
    try {
        if ($new_status == 'active') {
            $whm->unsuspendAccount($cp_user);
            sendEmailTemplate($u_email, $u_nama, 'hosting_unsuspended', ['nama' => $u_nama, 'domain' => $domain]);
        } elseif ($new_status == 'suspended') {
            $whm->suspendAccount($cp_user, "Aksi Admin");
            sendEmailTemplate($u_email, $u_nama, 'hosting_suspended', ['nama' => $u_nama, 'domain' => $domain]);
        }
        mysqli_query($conn, "UPDATE orders SET status = '$new_status' WHERE id = '$id'");
        mysqli_query($conn, "UPDATE user_hosting SET status = '" . ($new_status == 'active' ? 'aktif' : 'suspended') . "' WHERE order_id = '$id'");
        
        header("Location: orders?res=success&msg=" . urlencode("Status $cp_user diubah ke $new_status"));
    } catch (Exception $e) {
        header("Location: orders?res=error&msg=" . urlencode($e->getMessage()));
    }
    exit();
}

$query = mysqli_query($conn, "SELECT orders.*, users.nama as nama_user, hosting_plans.nama_paket, whm_servers.whm_host, whm_servers.whm_username as whm_root 
                              FROM orders 
                              LEFT JOIN users ON orders.user_id = users.id 
                              LEFT JOIN hosting_plans ON orders.hosting_plan_id = hosting_plans.id 
                              LEFT JOIN whm_servers ON orders.whm_id = whm_servers.id 
                              ORDER BY orders.created_at DESC");

// Stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
    COUNT(*) total,
    SUM(status='active') s_active,
    SUM(status='suspended') s_suspended,
    SUM(status='pending') s_pending,
    SUM(status_pembayaran='success') s_paid,
    SUM(total_harga) total_rev
    FROM orders"));

$page_title = "Data Pesanan";
include __DIR__ . '/library/header.php';
?>

<style>
.stat-mini { display: flex; align-items: center; gap: 10px; padding: 14px 18px; background: var(--card); border: 1px solid var(--border); border-radius: 10px; }
.stat-mini-icon { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.stat-mini-val { font-size: 20px; font-weight: 800; line-height: 1; }
.stat-mini-lbl { font-size: 10px; color: var(--mut); font-weight: 600; text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }
.quick-badge { display: inline-flex; align-items: center; gap: 3px; padding: 2px 7px; border-radius: 5px; font-size: 10px; font-weight: 700; border: 1px solid; white-space: nowrap; }
.qb-ok  { color: var(--ok);   background: var(--oks); border-color: var(--ob); }
.qb-err { color: var(--err);  background: var(--es);  border-color: #3d1a1a; }
.qb-warn{ color: var(--warn); background: var(--ws);  border-color: #3d2e0a; }
.qb-muted{ color: var(--sub); background: var(--surface); border-color: var(--border); }
.domain-chip { font-size: 11px; color: var(--accent); font-family: 'JetBrains Mono', monospace; background: var(--as); border: 1px solid var(--ba); border-radius: 4px; padding: 1px 6px; display: inline-block; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.user-link { font-weight: 700; cursor: pointer; transition: color .15s; }
.user-link:hover { color: var(--accent) !important; }
.node-pill { font-size: 10px; font-family: 'JetBrains Mono', monospace; background: var(--surface); border: 1px solid var(--border); border-radius: 4px; padding: 1px 6px; color: var(--sub); }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="ph-fill ph-shopping-cart me-2" style="color:var(--accent);"></i> Manajemen Pesanan</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb bc">
            <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>">Admin</a></li>
            <li class="breadcrumb-item active">Orders</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        <span class="quick-badge qb-muted"><i class="ph ph-clock me-1"></i><?= $stats['s_pending'] ?> Pending</span>
        <span class="quick-badge qb-ok"><i class="ph-fill ph-check-circle me-1"></i><?= $stats['s_active'] ?> Active</span>
        <span class="quick-badge qb-err"><i class="ph-fill ph-pause-circle me-1"></i><?= $stats['s_suspended'] ?> Suspended</span>
    </div>
</div>

<!-- Summary Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--as);color:var(--accent);"><i class="ph-fill ph-shopping-cart"></i></div>
            <div><div class="stat-mini-val"><?= number_format($stats['total']) ?></div><div class="stat-mini-lbl">Total Order</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-check-circle"></i></div>
            <div><div class="stat-mini-val" style="color:var(--ok);"><?= $stats['s_active'] ?></div><div class="stat-mini-lbl">Aktif</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--ws);color:var(--warn);"><i class="ph-fill ph-pause-circle"></i></div>
            <div><div class="stat-mini-val" style="color:var(--warn);"><?= $stats['s_suspended'] ?></div><div class="stat-mini-lbl">Suspended</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-money"></i></div>
            <div><div class="stat-mini-val" style="color:var(--ok); font-size:15px;">Rp <?= number_format($stats['total_rev'],0,',','.') ?></div><div class="stat-mini-lbl">Total Revenue</div></div>
        </div>
    </div>
</div>

<div class="card-c">
    <div class="ch d-flex align-items-center justify-content-between">
        <h3 class="ct"><i class="ph-fill ph-list-bullets me-2 text-primary"></i> Semua Transaksi Hosting</h3>
        <span style="font-size:11px;color:var(--mut);"><?= $stats['total'] ?> order · <?= $stats['s_paid'] ?> lunas</span>
    </div>
    <div class="cb p-0">
        <div class="table-responsive">
            <table class="tbl table-hover w-100" id="tableOrders" style="font-size: 12.5px;">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>Pelanggan / Domain</th>
                        <th>Node WHM</th>
                        <th>Paket</th>
                        <th>Total</th>
                        <th>Layanan</th>
                        <th>Bayar</th>
                        <th style="width:80px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($query)): ?>
                    <tr>
                        <td class="fw-bold" style="color:var(--mut); font-size:11px;">#<?= $row['id'] ?></td>
                        <td>
                            <div class="user-link view-user-detail" data-userid="<?= $row['user_id'] ?>" style="color:var(--text);">
                                <?= htmlspecialchars($row['nama_user'] ?? 'Guest') ?>
                            </div>
                            <?php if($row['domain']): ?>
                            <div class="mt-1"><span class="domain-chip"><i class="ph ph-link"></i> <?= htmlspecialchars($row['domain']) ?></span></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['whm_id']): ?>
                                <div style="font-size:11px; color:var(--text);">
                                    <i class="ph-fill ph-hard-drives" style="color:var(--accent);"></i>
                                    <?= htmlspecialchars($row['whm_host']) ?>
                                </div>
                                <div class="mt-1">
                                    <span class="node-pill">Node #<?= $row['whm_id'] ?></span>
                                    <span class="node-pill ms-1" style="color:var(--warn);"><?= htmlspecialchars($row['username'] ?? '-') ?></span>
                                </div>
                            <?php else: ?>
                                <span style="font-size:11px;color:var(--mut);">— Belum Assign</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-size:12px;font-weight:600;"><?= htmlspecialchars($row['nama_paket'] ?? 'N/A') ?></div>
                            <div style="font-size:10px;color:var(--mut);margin-top:2px;"><i class="ph ph-clock"></i> <?= $row['durasi'] ?> Bulan</div>
                        </td>
                        <td>
                            <div class="fw-bold" style="color:var(--ok);font-size:12px;">Rp <?= number_format($row['total_harga'],0,',','.') ?></div>
                        </td>
                        <td>
                            <?php if($row['status'] == 'active'): ?>
                                <span class="quick-badge qb-ok"><i class="ph-fill ph-check-circle"></i> ACTIVE</span>
                            <?php elseif($row['status'] == 'suspended'): ?>
                                <span class="quick-badge qb-err"><i class="ph-fill ph-pause-circle"></i> SUSPEND</span>
                            <?php else: ?>
                                <span class="quick-badge qb-warn"><?= strtoupper($row['status'] ?? 'PENDING') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['status_pembayaran'] == 'success'): ?>
                                <span class="quick-badge qb-ok"><i class="ph-fill ph-check"></i> Lunas</span>
                            <?php else: ?>
                                <span class="quick-badge qb-warn"><i class="ph ph-clock"></i> <?= ucfirst($row['status_pembayaran']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="ab py-1 px-2" type="button" data-bs-toggle="dropdown" title="Kelola">
                                    <i class="ph ph-dots-three-outline-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-dark p-1" style="background:var(--surface);border:1px solid var(--border);box-shadow:0 8px 24px rgba(0,0,0,.5);font-size:12px;min-width:160px;">
                                    <li><a class="dropdown-item rounded py-1 mb-1" href="?update_status=active&id=<?= $row['id'] ?>" style="color:var(--ok);">
                                        <i class="ph-fill ph-play-circle me-2"></i> Aktifkan</a></li>
                                    <li><a class="dropdown-item rounded py-1 mb-1" href="?update_status=suspended&id=<?= $row['id'] ?>" style="color:var(--warn);">
                                        <i class="ph-fill ph-pause-circle me-2"></i> Suspend</a></li>
                                    <li><hr class="dropdown-divider my-1" style="border-color:var(--border);"></li>
                                    <li><a class="dropdown-item rounded py-1" href="#" onclick="viewDetail('<?= htmlspecialchars(addslashes($row['username'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($row['password'] ?? '')) ?>')">
                                        <i class="ph-fill ph-key me-2"></i> Intip Akun</a></li>
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
    function viewDetail(user, pass) {
        Swal.fire({
            title: '<i class="ph-fill ph-key" style="color:var(--accent);"></i> Kredensial cPanel',
            html: `<div class="text-start mt-2">
                <div class="mb-3 p-3 rounded" style="background:var(--surface);border:1px solid var(--border);">
                    <div style="font-size:10px;color:var(--mut);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Username cPanel</div>
                    <div style="font-family:monospace;font-size:14px;color:var(--text);">${user}</div>
                </div>
                <div class="p-3 rounded" style="background:var(--surface);border:1px solid var(--border);">
                    <div style="font-size:10px;color:var(--mut);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Password cPanel</div>
                    <div style="font-family:monospace;font-size:14px;color:var(--text);">${pass}</div>
                </div>
            </div>`,
            background: 'var(--card)', color: 'var(--text)',
            confirmButtonColor: 'var(--accent)', showCloseButton: true, showConfirmButton: false,
        });
    }
    <?php if(isset($_GET['res'])): ?>
        <?php if($_GET['res'] == 'success'): ?>
        Swal.fire({ icon:'success', title:'Berhasil!', text:'<?= htmlspecialchars($_GET['msg'] ?? '') ?>', timer:2500, showConfirmButton:false, background:'var(--card)', color:'var(--text)' });
        <?php elseif($_GET['res'] == 'error'): ?>
        Swal.fire({ icon:'error', title:'Gagal!', text:'<?= htmlspecialchars($_GET['msg'] ?? '') ?>', background:'var(--card)', color:'var(--text)', confirmButtonColor:'var(--accent)' });
        <?php endif; ?>
    <?php endif; ?>
</script>

<?php include __DIR__ . '/library/footer.php'; ?>