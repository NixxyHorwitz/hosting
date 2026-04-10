<?php
require_once __DIR__ . '/library/admin_session.php';
if(!defined('NS1')) include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/WHMClient.php';
require_once __DIR__ . '/../core/mailer.php';

// ─── AJAX HANDLER ────────────────────────────────────────────────
if (!empty($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['ajax_action'];
    $resp   = ['ok' => false, 'msg' => 'Aksi tidak dikenal.'];

    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID tidak valid.']); exit(); }

    // ── Create WHM & Aktifkan
    if ($action === 'create_whm') {
        $ord_q = mysqli_query($conn, "SELECT o.*, u.email, u.nama FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = '$id' LIMIT 1");
        $order = mysqli_fetch_assoc($ord_q);
        if (!$order) { echo json_encode(['ok'=>false,'msg'=>'Order tidak ditemukan.']); exit(); }
        if (!empty($order['username'])) { echo json_encode(['ok'=>false,'msg'=>'Akun WHM sudah pernah dibuat.']); exit(); }

        $whm_id  = (int)$order['whm_id'];
        $whmQ    = mysqli_query($conn, "SELECT * FROM whm_servers WHERE id = '$whm_id' LIMIT 1");
        $whm_srv = mysqli_fetch_assoc($whmQ);
        if (!$whm_srv) { echo json_encode(['ok'=>false,'msg'=>'Server WHM tidak ditemukan.']); exit(); }

        $whm      = new WHMClient($whm_srv['whm_host'], $whm_srv['whm_username'], $whm_srv['whm_token']);
        $username = 'client' . rand(100, 999);
        $password = bin2hex(random_bytes(8)) . rand(10, 99) . '!';
        $email    = $order['email'];
        $domain   = $order['domain'];
        $package  = $order['whm_package_name'];

        try {
            $stmt = $conn->prepare("UPDATE orders SET status='active', username=?, password=? WHERE id=?");
            $stmt->bind_param("ssi", $username, $password, $id);
            $stmt->execute(); $stmt->close();

            $whm->createAccount(['username'=>$username,'domain'=>$domain,'password'=>$password,'contactemail'=>$email,'plan'=>$package]);

            $mail = sendEmailTemplate($email, $order['nama'], 'order_hosting', ['nama'=>$order['nama'],'domain'=>$domain,'username'=>$username,'password'=>$password]);
            $mail_note = ($mail === true) ? ' Email terkirim.' : ' (Email gagal: ' . (is_string($mail) ? $mail : 'SMTP error') . ')';

            $resp = ['ok'=>true, 'msg'=>"WHM berhasil dibuat! User: $username, Domain: $domain.$mail_note", 'username'=>$username, 'status'=>'active'];
        } catch (Exception $e) {
            mysqli_query($conn, "UPDATE orders SET status='pending', username=NULL, password=NULL WHERE id='$id'");
            $resp = ['ok'=>false, 'msg'=>'Gagal buat WHM: ' . $e->getMessage()];
        }

    // ── Update Status (suspend / aktifkan)
    } elseif ($action === 'update_status') {
        $new_status = in_array($_POST['status'] ?? '', ['active','suspended']) ? $_POST['status'] : '';
        if (!$new_status) { echo json_encode(['ok'=>false,'msg'=>'Status tidak valid.']); exit(); }

        $ord_q = mysqli_query($conn, "SELECT o.username, o.whm_id, o.domain, u.email, u.nama FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = '$id' LIMIT 1");
        $order = mysqli_fetch_assoc($ord_q);
        if (!$order) { echo json_encode(['ok'=>false,'msg'=>'Order tidak ditemukan.']); exit(); }

        $whmQ   = mysqli_query($conn, "SELECT * FROM whm_servers WHERE id = '{$order['whm_id']}' LIMIT 1");
        $whmSrv = mysqli_fetch_assoc($whmQ);
        if (!$whmSrv) { echo json_encode(['ok'=>false,'msg'=>'Server WHM tidak ditemukan.']); exit(); }

        $whm = new WHMClient($whmSrv['whm_host'], $whmSrv['whm_username'], $whmSrv['whm_token']);
        try {
            if ($new_status === 'active') {
                $whm->unsuspendAccount($order['username']);
                $mail = sendEmailTemplate($order['email'], $order['nama'], 'unsuspend_hosting', ['nama'=>$order['nama'],'domain'=>$order['domain']]);
            } else {
                $whm->suspendAccount($order['username'], 'Aksi Admin');
                $mail = sendEmailTemplate($order['email'], $order['nama'], 'suspend_hosting', ['nama'=>$order['nama'],'domain'=>$order['domain']]);
            }
            mysqli_query($conn, "UPDATE orders SET status='$new_status' WHERE id='$id'");
            $mail_note = ($mail === true) ? ' Email terkirim.' : ' (Email gagal)';
            $resp = ['ok'=>true, 'msg'=>"Status diubah ke $new_status.$mail_note", 'status'=>$new_status];
        } catch (Exception $e) {
            $resp = ['ok'=>false, 'msg'=>$e->getMessage()];
        }

    // ── Delete Order
    } elseif ($action === 'delete_order') {
        $ord_q = mysqli_query($conn, "SELECT o.username, o.whm_id FROM orders o WHERE o.id = '$id' LIMIT 1");
        $order = mysqli_fetch_assoc($ord_q);
        if (!$order) { echo json_encode(['ok'=>false,'msg'=>'Order tidak ditemukan.']); exit(); }

        if (!empty($order['username'])) {
            $whmQ = mysqli_query($conn, "SELECT * FROM whm_servers WHERE id = '{$order['whm_id']}' LIMIT 1");
            $whmSrv = mysqli_fetch_assoc($whmQ);
            if ($whmSrv) {
                try {
                    $whm = new WHMClient($whmSrv['whm_host'], $whmSrv['whm_username'], $whmSrv['whm_token']);
                    $whm->terminateAccount($order['username']);
                } catch (Exception $e) {
                    error_log('WHM terminate (order '.$id.'): '.$e->getMessage());
                }
            }
        }
        mysqli_query($conn, "DELETE FROM orders WHERE id = '$id'");
        $resp = ['ok'=>true, 'msg'=>"Order #$id berhasil dihapus."];
    }

    echo json_encode($resp);
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
                    <tr id="order-row-<?= $row['id'] ?>">
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
                                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end p-1" style="background:var(--surface);border:1px solid var(--border);box-shadow:0 8px 24px rgba(0,0,0,.5);font-size:12px;min-width:200px;">
                                    
                                    <?php /* ── Create WHM & Aktifkan (hanya jika belum ada username) ── */ ?>
                                    <?php if(empty($row['username'])): ?>
                                    <li>
                                        <a class="dropdown-item rounded py-1 mb-1" href="#"
                                           onclick="doCreateWhm(<?= $row['id'] ?>, this)" style="color:#a78bfa;">
                                            <i class="ph-fill ph-cloud-arrow-up me-2"></i> Create WHM & Aktifkan
                                        </a>
                                    </li>
                                    <?php else: ?>
                                    <li>
                                        <span class="dropdown-item rounded py-1 mb-1" style="color:var(--mut);opacity:.5;cursor:not-allowed;">
                                            <i class="ph-fill ph-cloud-check me-2"></i> WHM Sudah Dibuat
                                        </span>
                                    </li>
                                    <?php endif; ?>

                                    <?php /* ── Suspend / Aktifkan ── */ ?>
                                    <?php if(!empty($row['username'])): ?>
                                    <li>
                                        <a class="dropdown-item rounded py-1 mb-1" href="#"
                                           onclick="doAction(<?= $row['id'] ?>, 'update_status', 'active', this)" style="color:var(--ok);">
                                            <i class="ph-fill ph-play-circle me-2"></i> Aktifkan
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item rounded py-1 mb-1" href="#"
                                           onclick="doAction(<?= $row['id'] ?>, 'update_status', 'suspended', this)" style="color:var(--warn);">
                                            <i class="ph-fill ph-pause-circle me-2"></i> Suspend
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                    <li><hr class="dropdown-divider my-1" style="border-color:var(--border);"></li>

                                    <?php /* ── Intip Akun cPanel ── */ ?>
                                    <li>
                                        <a class="dropdown-item rounded py-1 mb-1" href="#" onclick="viewDetail('<?= htmlspecialchars(addslashes($row['username'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($row['password'] ?? '')) ?>')">
                                            <i class="ph-fill ph-key me-2"></i> Intip Akun cPanel
                                        </a>
                                    </li>

                                    <?php /* ── Delete Order ── */ ?>
                                    <li>
                                        <a class="dropdown-item rounded py-1" href="#"
                                           onclick="doDeleteOrder(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['domain'] ?? '')) ?>', this)" style="color:var(--err);">
                                            <i class="ph-fill ph-trash me-2"></i> Hapus Order
                                        </a>
                                    </li>
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

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const ORDERS_URL = window.location.pathname + window.location.search.replace(/[?&](res|msg)=[^&]*/g,'');

// ── Disable/enable tombol aksi di row
function lockRow(id, lock) {
    const btn = $(`#order-row-${id} button[data-bs-toggle='dropdown']`);
    btn.prop('disabled', lock);
    if (lock) btn.addClass('opacity-50');
    else btn.removeClass('opacity-50');
}

// ── Animasi baris saat create WHM berjalan
function setRowCreating(id, creating) {
    const statusCell = $(`#order-row-${id} td:nth-child(6)`);
    if (creating) {
        statusCell.data('original', statusCell.html());
        let dots = 0;
        statusCell.data('interval', setInterval(() => {
            dots = (dots + 1) % 4;
            statusCell.html(`<span style="color:#a78bfa;font-weight:700;"><i class="ph ph-spinner-gap" style="animation:spin .8s linear infinite;display:inline-block;"></i> Creating WHM${'.'.repeat(dots)}</span>`);
        }, 400));
    } else {
        clearInterval(statusCell.data('interval'));
        statusCell.html(statusCell.data('original') || '');
    }
}

// ── Core AJAX function
function ajaxAction(payload, onSuccess, onFail) {
    const id = payload.id;
    lockRow(id, true);
    $.post(window.location.pathname, payload, 'json')
        .done(data => {
            if (data.ok) onSuccess(data);
            else onFail(data.msg || 'Terjadi kesalahan.');
        })
        .fail(() => onFail('Koneksi gagal.'))
        .always(() => lockRow(id, false));
}

// ── Create WHM & Aktifkan
function doCreateWhm(id, el) {
    event.preventDefault();
    Swal.fire({
        icon: 'question', title: 'Create WHM & Aktifkan?',
        html: `Buat akun cPanel WHM untuk order <b>#${id}</b>?`,
        showCancelButton: true,
        confirmButtonText: '<i class="ph-fill ph-cloud-arrow-up me-1"></i> Ya, Buat',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#a78bfa', cancelButtonColor: 'var(--surface)',
        background: 'var(--card)', color: 'var(--text)', reverseButtons: true,
    }).then(r => {
        if (!r.isConfirmed) return;
        setRowCreating(id, true);
        ajaxAction(
            { ajax_action: 'create_whm', id: id },
            data => {
                setRowCreating(id, false);
                Swal.fire({ icon:'success', title:'WHM Dibuat!', text: data.msg,
                    background:'var(--card)', color:'var(--text)', confirmButtonColor:'var(--ok)'
                }).then(() => location.reload());
            },
            msg => {
                setRowCreating(id, false);
                Swal.fire({ icon:'error', title:'Gagal!', text: msg,
                    background:'var(--card)', color:'var(--text)', confirmButtonColor:'var(--err)'
                });
            }
        );
    });
}

// ── Helper: animasi loading generik di status cell
function setRowLoading(id, text, color) {
    const cell = $(`#order-row-${id} td:nth-child(6)`);
    cell.data('orig', cell.html());
    let dots = 0;
    cell.data('iv', setInterval(() => {
        dots = (dots + 1) % 4;
        cell.html(`<span style="color:${color};font-weight:700;"><i class="ph ph-spinner-gap" style="animation:spin .8s linear infinite;display:inline-block;"></i> ${text}${'.'.repeat(dots)}</span>`);
    }, 400));
}
function clearRowLoading(id) {
    const cell = $(`#order-row-${id} td:nth-child(6)`);
    clearInterval(cell.data('iv'));
    cell.html(cell.data('orig') || '');
}

// ── Suspend / Aktifkan
function doAction(id, action, status, el) {
    event.preventDefault();
    const label     = status === 'active' ? 'Aktifkan' : 'Suspend';
    const btnColor  = status === 'active' ? 'var(--ok)' : 'var(--warn)';
    const animText  = status === 'active' ? 'Activating' : 'Suspending';
    const animColor = status === 'active' ? '#10b981' : '#f59e0b';
    const icon      = status === 'active' ? 'question' : 'warning';
    Swal.fire({
        icon, title: label + ' Hosting?',
        html: `Order <b>#${id}</b> akan di-<b>${label.toLowerCase()}</b>.`,
        showCancelButton: true,
        confirmButtonText: label, cancelButtonText: 'Batal',
        confirmButtonColor: btnColor, cancelButtonColor: 'var(--surface)',
        background: 'var(--card)', color: 'var(--text)', reverseButtons: true,
    }).then(r => {
        if (!r.isConfirmed) return;
        setRowLoading(id, animText, animColor);
        ajaxAction(
            { ajax_action: 'update_status', id: id, status: status },
            data => {
                clearRowLoading(id);
                const badge = status === 'active'
                    ? `<span class="quick-badge qb-ok"><i class="ph-fill ph-check-circle"></i> ACTIVE</span>`
                    : `<span class="quick-badge qb-err"><i class="ph-fill ph-pause-circle"></i> SUSPEND</span>`;
                $(`#order-row-${id} td:nth-child(6)`).html(badge);
                const icon2 = data.msg.includes('gagal') ? 'warning' : 'success';
                Swal.fire({ icon: icon2, title: icon2 === 'success' ? 'Berhasil!' : 'Perhatian',
                    text: data.msg, timer: 2500, showConfirmButton: false,
                    background: 'var(--card)', color: 'var(--text)'
                });
            },
            msg => {
                clearRowLoading(id);
                Swal.fire({ icon:'error', title:'Gagal!', text: msg,
                    background:'var(--card)', color:'var(--text)', confirmButtonColor:'var(--err)'
                });
            }
        );
    });
}

// ── Hapus Order
function doDeleteOrder(id, domain, el) {
    event.preventDefault();
    Swal.fire({
        icon: 'warning', title: 'Hapus Order?',
        html: `Order <b>#${id}</b> domain <code style="color:var(--accent)">${domain || '-'}</code> akan dihapus permanen.<br><small style="color:var(--mut);">Akun cPanel di WHM turut dihapus jika sudah dibuat.</small>`,
        showCancelButton: true,
        confirmButtonText: '<i class="ph-fill ph-trash me-1"></i> Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: 'var(--err)', cancelButtonColor: 'var(--surface)',
        background: 'var(--card)', color: 'var(--text)', reverseButtons: true,
    }).then(r => {
        if (!r.isConfirmed) return;
        setRowLoading(id, 'Deleting', '#ef4444');
        $(`#order-row-${id}`).css('opacity', '0.5');
        ajaxAction(
            { ajax_action: 'delete_order', id: id },
            data => {
                clearRowLoading(id);
                $(`#order-row-${id}`).fadeOut(400, function(){ $(this).remove(); });
                Swal.fire({ icon:'success', title:'Terhapus!', text: data.msg,
                    timer: 2000, showConfirmButton: false,
                    background: 'var(--card)', color: 'var(--text)'
                });
            },
            msg => {
                clearRowLoading(id);
                $(`#order-row-${id}`).css('opacity', '1');
                Swal.fire({ icon:'error', title:'Gagal!', text: msg,
                    background:'var(--card)', color:'var(--text)', confirmButtonColor:'var(--err)'
                });
            }
        );
    });
}

// ── Intip Akun cPanel
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

// ── CSS spinner inline
$('<style>@keyframes spin{to{transform:rotate(360deg)}}</style>').appendTo('head');
</script>

<?php include __DIR__ . '/library/footer.php'; ?>