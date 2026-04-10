<?php
require_once __DIR__ . '/library/admin_session.php';
if(!defined('NS1')) include __DIR__ . '/../config/database.php';

// ─── Hapus User ───────────────────────────────────────────────
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    mysqli_query($conn, "DELETE FROM users WHERE id = $id");
    header("Location: users?res=deleted"); exit();
}

// ─── Tambah User ──────────────────────────────────────────────
if (isset($_POST['add_user'])) {
    $nama  = mysqli_real_escape_string($conn, $_POST['nama']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $wa    = mysqli_real_escape_string($conn, $_POST['no_whatsapp']);
    $role  = mysqli_real_escape_string($conn, $_POST['role']);
    $cek   = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
    if(mysqli_num_rows($cek) > 0) { header("Location: users?res=exists"); exit(); }
    mysqli_query($conn, "INSERT INTO users (nama, email, password, role, no_whatsapp, status) VALUES ('$nama','$email','$pass','$role','$wa','active')");
    header("Location: users?res=added"); exit();
}

// ─── Edit User ────────────────────────────────────────────────
if (isset($_POST['edit_user'])) {
    $id    = (int)$_POST['id'];
    $nama  = mysqli_real_escape_string($conn, $_POST['nama']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $wa    = mysqli_real_escape_string($conn, $_POST['no_whatsapp']);
    $role  = mysqli_real_escape_string($conn, $_POST['role']);
    $status= mysqli_real_escape_string($conn, $_POST['status']);
    $extra = "";
    if(!empty($_POST['password'])) {
        $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $extra = ", password='$pass'";
    }
    // Disable 2FA if requested
    if(!empty($_POST['disable_2fa'])) {
        $extra .= ", gauth_secret=NULL, is_2fa_enabled=0";
    }
    mysqli_query($conn, "UPDATE users SET nama='$nama',email='$email',role='$role',no_whatsapp='$wa',status='$status'$extra WHERE id=$id");
    header("Location: users?res=edited"); exit();
}

// ─── AJAX: Detail User (untuk modal) ───────────────────────────
if (isset($_GET['ajax_user']) && isset($_GET['uid'])) {
    header('Content-Type: application/json');
    ob_end_clean();
    $uid = (int)$_GET['uid'];
    $u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$uid"));
    if(!$u) { echo json_encode(['error' => 'Not found']); exit; }
    
    // Orders / Hosting
    $orders = [];
    $oq = mysqli_query($conn, "SELECT o.*, hp.nama_paket FROM orders o LEFT JOIN hosting_plans hp ON o.hosting_plan_id=hp.id WHERE o.user_id=$uid ORDER BY o.id DESC");
    while($row = mysqli_fetch_assoc($oq)) $orders[] = $row;
    
    $u['orders'] = $orders;
    echo json_encode($u);
    exit;
}

// ─── Query utama ──────────────────────────────────────────────
$query = mysqli_query($conn, "SELECT u.*,
    (SELECT COUNT(id) FROM orders WHERE user_id=u.id) as total_hosting,
    (SELECT COUNT(id) FROM orders WHERE user_id=u.id AND status='active') as active_hosting,
    (SELECT COUNT(id) FROM invoices WHERE user_id=u.id AND status='unpaid') as unpaid_inv
    FROM users u ORDER BY u.role ASC, u.id DESC");

$stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total,
    SUM(role='client') clients, SUM(role='admin') admins,
    (SELECT COUNT(DISTINCT user_id) FROM orders WHERE status='active') active_users,
    SUM(is_2fa_enabled=1) users_2fa
    FROM users"));

$page_title = "Data Users";
include __DIR__ . '/library/header.php';
?>

<style>
.stat-mini { display:flex; align-items:center; gap:10px; padding:14px 18px; background:var(--card); border:1px solid var(--border); border-radius:10px; }
.stat-mini-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.stat-mini-val { font-size:20px; font-weight:800; line-height:1; }
.stat-mini-lbl { font-size:10px; color:var(--mut); font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }
.quick-badge { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:5px; font-size:10px; font-weight:700; border:1px solid; white-space:nowrap; }
.qb-ok   { color:var(--ok);   background:var(--oks); border-color:var(--ob); }
.qb-err  { color:var(--err);  background:var(--es);  border-color:#3d1a1a; }
.qb-warn { color:var(--warn); background:var(--ws);  border-color:#3d2e0a; }
.qb-muted{ color:var(--sub);  background:var(--surface); border-color:var(--border); }
.qb-acc  { color:var(--accent); background:var(--as); border-color:var(--ba); }
.qb-blue { color:#48cae4; background:rgba(72,202,228,.12); border-color:rgba(72,202,228,.25); }
.user-ava { width:34px; height:34px; border-radius:10px; background:var(--as); color:var(--accent); font-weight:800; font-size:13px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.user-ava.admin { background:var(--ws); color:var(--warn); }
.user-ava.lg { width:48px; height:48px; border-radius:12px; font-size:18px; }
.detail-row { display:flex; flex-direction:column; gap:3px; padding:10px 0; border-bottom:1px solid var(--border); font-size:12.5px; }
.detail-row:last-child { border-bottom:none; }
.detail-lbl { font-size:10px; font-weight:700; color:var(--mut); text-transform:uppercase; letter-spacing:.4px; }
.detail-val { color:var(--text); font-weight:500; }
.hosting-row { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:10px 12px; margin-bottom:8px; font-size:12px; }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="ph-fill ph-users me-2" style="color:var(--accent);"></i> Manajemen Pengguna</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb bc">
            <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>">Admin</a></li>
            <li class="breadcrumb-item active">Users</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addModal" style="background:var(--accent);border:none;font-size:13px;padding:8px 16px;border-radius:8px;">
        <i class="ph ph-user-plus me-1"></i> Tambah User
    </button>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--as);color:var(--accent);"><i class="ph-fill ph-users"></i></div>
            <div><div class="stat-mini-val"><?= $stats['total'] ?></div><div class="stat-mini-lbl">Total User</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-user-check"></i></div>
            <div><div class="stat-mini-val" style="color:var(--ok);"><?= $stats['clients'] ?></div><div class="stat-mini-lbl">Clients</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-hard-drives"></i></div>
            <div><div class="stat-mini-val" style="color:var(--ok);"><?= $stats['active_users'] ?></div><div class="stat-mini-lbl">Punya Hosting</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:rgba(72,202,228,.15);color:#48cae4;"><i class="ph-fill ph-shield-check"></i></div>
            <div><div class="stat-mini-val" style="color:#48cae4;"><?= $stats['users_2fa'] ?></div><div class="stat-mini-lbl">Aktifkan 2FA</div></div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card-c">
    <div class="ch d-flex align-items-center justify-content-between">
        <h3 class="ct"><i class="ph-fill ph-list-bullets me-2 text-primary"></i> Semua Pengguna Terdaftar</h3>
        <div class="d-flex gap-2 align-items-center">
            <input type="text" id="searchUser" class="fc" style="font-size:12px;padding:5px 10px;width:180px;" placeholder="Cari nama / email...">
            <span style="font-size:11px;color:var(--mut);"><?= $stats['total'] ?> user</span>
        </div>
    </div>
    <div class="cb p-0">
        <div class="table-responsive">
            <table class="tbl table-hover w-100 mb-0" id="tableUsers" style="font-size:12.5px;">
                <thead>
                    <tr>
                        <th>Pengguna</th>
                        <th>Kontak</th>
                        <th class="text-center">Hosting</th>
                        <th class="text-center">Tagihan</th>
                        <th class="text-center">2FA</th>
                        <th>Role / Status</th>
                        <th>Bergabung</th>
                        <th class="text-center" style="width:100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = mysqli_fetch_assoc($query)): 
                        $is_admin = ($user['role'] == 'admin');
                        $initial  = strtoupper(substr($user['nama'], 0, 1));
                        $is_active = ($user['status'] ?? 'active') === 'active';
                        $has_2fa   = !empty($user['is_2fa_enabled']);
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-ava <?= $is_admin ? 'admin' : '' ?>"><?= $initial ?></div>
                                <div>
                                    <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($user['nama']) ?></div>
                                    <div style="font-size:10px;color:var(--mut);">ID #<?= $user['id'] ?> · <?= date('d M Y', strtotime($user['created_at'] ?? 'now')) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:12px;"><?= htmlspecialchars($user['email']) ?></div>
                            <?php if($user['no_whatsapp']): ?>
                            <div style="font-size:11px;color:#25d366;margin-top:2px;">
                                <i class="ph-fill ph-whatsapp-logo"></i> <?= htmlspecialchars($user['no_whatsapp']) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($user['total_hosting'] > 0): ?>
                                <span class="quick-badge qb-ok" title="<?= $user['active_hosting'] ?> aktif dari <?= $user['total_hosting'] ?> total">
                                    <i class="ph-fill ph-hard-drives"></i> <?= $user['active_hosting'] ?>/<?= $user['total_hosting'] ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--mut);font-size:11px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($user['unpaid_inv'] > 0): ?>
                                <span class="quick-badge qb-warn"><i class="ph ph-clock"></i> <?= $user['unpaid_inv'] ?> unpaid</span>
                            <?php else: ?>
                                <span class="quick-badge qb-ok"><i class="ph-fill ph-check"></i> Clear</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($has_2fa): ?>
                                <span class="quick-badge qb-blue"><i class="ph-fill ph-shield-check"></i> ON</span>
                            <?php else: ?>
                                <span class="quick-badge qb-muted"><i class="ph ph-shield"></i> OFF</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($is_admin): ?>
                                <span class="quick-badge qb-warn"><i class="ph-fill ph-shield-check"></i> ADMIN</span>
                            <?php else: ?>
                                <span class="quick-badge qb-muted"><i class="ph ph-user"></i> CLIENT</span>
                            <?php endif; ?>
                            <br>
                            <?php if($is_active): ?>
                                <span class="quick-badge qb-ok mt-1"><i class="ph-fill ph-circle"></i> Aktif</span>
                            <?php else: ?>
                                <span class="quick-badge qb-err mt-1"><i class="ph ph-x-circle"></i> Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(!empty($user['last_login'])): ?>
                            <div style="font-size:11px;">Login: <?= date('d M y', strtotime($user['last_login'])) ?></div>
                            <?php endif; ?>
                            <div style="font-size:10px;color:var(--mut);">Daftar: <?= date('d M y', strtotime($user['created_at'] ?? 'now')) ?></div>
                        </td>
                        <td class="text-center">
                            <button type="button" class="ab py-1 px-2 me-1" title="Detail & Edit"
                                onclick="openUserDetail(<?= $user['id'] ?>)">
                                <i class="ph ph-eye"></i>
                            </button>
                            <button type="button" class="ab py-1 px-2 red" title="Hapus" onclick="confirmDelete(<?= $user['id'] ?>)">
                                <i class="ph ph-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL TAMBAH USER ═══════════════ -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content mc">
            <div class="modal-header mh py-2">
                <h5 class="modal-title fs-6 fw-bold"><i class="ph-fill ph-user-plus me-2" style="color:var(--accent);"></i> Tambah Pengguna</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body py-3">
                    <div class="mb-2"><label class="fl mb-1">Nama Lengkap</label>
                        <input type="text" name="nama" class="fc w-100" placeholder="John Doe" required></div>
                    <div class="mb-2"><label class="fl mb-1">Email</label>
                        <input type="email" name="email" class="fc w-100" placeholder="user@gmail.com" required></div>
                    <div class="mb-2"><label class="fl mb-1">Password</label>
                        <input type="password" name="password" class="fc w-100" placeholder="Min. 8 karakter" required></div>
                    <div class="mb-2"><label class="fl mb-1">No. WhatsApp <span style="color:var(--mut);">(opsional)</span></label>
                        <input type="text" name="no_whatsapp" class="fc w-100" placeholder="628xxx"></div>
                    <div class="mb-2"><label class="fl mb-1">Hak Akses</label>
                        <select name="role" class="fc w-100">
                            <option value="client">Client</option>
                            <option value="admin">Admin</option>
                        </select></div>
                </div>
                <div class="modal-footer mf py-2">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="color:var(--mut);">Batal</button>
                    <button type="submit" name="add_user" class="btn btn-sm btn-primary fw-bold" style="background:var(--accent);border:none;">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL DETAIL & EDIT USER ═══════════════ -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content mc">
            <div class="modal-header mh py-2">
                <h5 class="modal-title fs-6 fw-bold d-flex align-items-center gap-2">
                    <div class="user-ava lg" id="detail_ava" style="font-size:16px;">?</div>
                    <div>
                        <div id="detail_name" style="font-size:14px;font-weight:700;"></div>
                        <div id="detail_email_hdr" style="font-size:11px;color:var(--mut);font-weight:400;"></div>
                    </div>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body py-0 px-0">
                <!-- Nav Tabs -->
                <ul class="nav nav-tabs px-3 pt-2" id="detailTabs" role="tablist" style="border-color:var(--border);">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#dtab-info" style="font-size:12px;">
                            <i class="ph ph-user me-1"></i> Informasi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#dtab-hosting" style="font-size:12px;">
                            <i class="ph ph-hard-drives me-1"></i> Hosting <span class="quick-badge qb-ok ms-1" id="hosting_count_badge">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#dtab-edit" style="font-size:12px;">
                            <i class="ph ph-pencil-simple me-1"></i> Edit
                        </a>
                    </li>
                </ul>

                <div class="tab-content px-3 py-3" id="detailTabsContent">

                    <!-- TAB INFO -->
                    <div class="tab-pane fade show active" id="dtab-info">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="detail-row"><div class="detail-lbl">Nama Lengkap</div><div class="detail-val" id="di-nama">—</div></div>
                                <div class="detail-row"><div class="detail-lbl">Alamat Email</div><div class="detail-val" id="di-email">—</div></div>
                                <div class="detail-row"><div class="detail-lbl">No. WhatsApp</div><div class="detail-val" id="di-wa">—</div></div>
                                <div class="detail-row"><div class="detail-lbl">Role</div><div class="detail-val" id="di-role">—</div></div>
                                <div class="detail-row"><div class="detail-lbl">Status Akun</div><div class="detail-val" id="di-status">—</div></div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-row"><div class="detail-lbl">Tanggal Daftar</div><div class="detail-val" id="di-created">—</div></div>
                                <div class="detail-row"><div class="detail-lbl">Login Terakhir</div><div class="detail-val" id="di-last_login">—</div></div>
                                <div class="detail-row"><div class="detail-lbl">IP Terakhir</div><div class="detail-val" id="di-last_ip">—</div></div>
                                <div class="detail-row">
                                    <div class="detail-lbl">2FA (Two-Factor Auth)</div>
                                    <div class="detail-val" id="di-2fa">—</div>
                                </div>
                                <div class="detail-row"><div class="detail-lbl">Domisili</div><div class="detail-val" id="di-location">—</div></div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB HOSTING -->
                    <div class="tab-pane fade" id="dtab-hosting">
                        <div id="hosting-list-wrap">
                            <div class="text-center text-muted py-4" style="font-size:13px;"><i class="ph ph-cloud"></i> Loading...</div>
                        </div>
                    </div>

                    <!-- TAB EDIT -->
                    <div class="tab-pane fade" id="dtab-edit">
                        <form action="" method="POST" id="editForm">
                            <input type="hidden" name="id" id="edit_id">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="fl mb-1">Nama Lengkap</label>
                                    <input type="text" name="nama" id="edit_nama" class="fc w-100" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="fl mb-1">Email</label>
                                    <input type="email" name="email" id="edit_email" class="fc w-100" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="fl mb-1">No. WhatsApp</label>
                                    <input type="text" name="no_whatsapp" id="edit_wa" class="fc w-100">
                                </div>
                                <div class="col-md-3">
                                    <label class="fl mb-1">Role</label>
                                    <select name="role" id="edit_role" class="fc w-100">
                                        <option value="client">Client</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="fl mb-1">Status</label>
                                    <select name="status" id="edit_status" class="fc w-100">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="fl mb-1">Password Baru <span style="color:var(--mut);">(kosongkan jika tidak diganti)</span></label>
                                    <input type="password" name="password" class="fc w-100" placeholder="••••••••">
                                </div>
                                <!-- 2FA Reset -->
                                <div class="col-12" id="wrap_2fa_reset" style="display:none;">
                                    <div class="p-3 rounded" style="background:rgba(255,90,90,.07);border:1px solid rgba(255,90,90,.2);">
                                        <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-size:13px;">
                                            <input type="checkbox" name="disable_2fa" value="1" id="edit_disable_2fa" style="width:14px;height:14px;">
                                            <span><i class="ph-fill ph-shield-warning me-1" style="color:var(--err);"></i> Nonaktifkan 2FA untuk user ini</span>
                                        </label>
                                        <div style="font-size:11px;color:var(--mut);margin-top:4px;padding-left:22px;">User akan kehilangan perlindungan Autentikasi Dua Faktor dan harus konfigurasi ulang.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                                <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="color:var(--mut);">Batal</button>
                                <button type="submit" name="edit_user" class="btn btn-sm btn-primary fw-bold" style="background:var(--accent);border:none;">
                                    <i class="ph ph-check me-1"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Search filter
    document.getElementById('searchUser').addEventListener('keyup', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#tableUsers tbody tr').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    });

    function openUserDetail(uid) {
        // Reset UI
        document.getElementById('detail_ava').innerText = '...';
        document.getElementById('detail_name').innerText = 'Memuat...';
        document.getElementById('detail_email_hdr').innerText = '';
        document.getElementById('hosting-list-wrap').innerHTML = '<div class="text-center text-muted py-4" style="font-size:13px;"><i class="ph ph-circle-notch"></i> Loading...</div>';

        new bootstrap.Modal(document.getElementById('detailModal')).show();

        fetch('users?ajax_user=1&uid=' + uid)
            .then(r => r.json())
            .then(u => {
                const initial = (u.nama || '?').charAt(0).toUpperCase();
                document.getElementById('detail_ava').innerText = initial;
                document.getElementById('detail_name').innerText = u.nama || '—';
                document.getElementById('detail_email_hdr').innerText = u.email || '—';

                // Info tab
                document.getElementById('di-nama').innerText   = u.nama || '—';
                document.getElementById('di-email').innerText  = u.email || '—';
                document.getElementById('di-wa').innerText     = u.no_whatsapp || '—';
                document.getElementById('di-role').innerHTML   = u.role === 'admin'
                    ? '<span class="quick-badge qb-warn"><i class="ph-fill ph-shield-check"></i> ADMIN</span>'
                    : '<span class="quick-badge qb-muted"><i class="ph ph-user"></i> CLIENT</span>';
                document.getElementById('di-status').innerHTML = (u.status === 'active' || !u.status)
                    ? '<span class="quick-badge qb-ok"><i class="ph-fill ph-circle"></i> Aktif</span>'
                    : '<span class="quick-badge qb-err"><i class="ph ph-x-circle"></i> Nonaktif</span>';
                document.getElementById('di-created').innerText    = u.created_at ? u.created_at.split(' ')[0] : '—';
                document.getElementById('di-last_login').innerText = u.last_login || '—';
                document.getElementById('di-last_ip').innerHTML    = u.last_ip ? `<code style="font-size:11px;">${u.last_ip}</code>` : '—';
                document.getElementById('di-2fa').innerHTML    = parseInt(u.is_2fa_enabled)
                    ? '<span class="quick-badge qb-blue"><i class="ph-fill ph-shield-check"></i> Aktif</span>'
                    : '<span class="quick-badge qb-muted"><i class="ph ph-shield"></i> Tidak Aktif</span>';

                const parts = [u.kota, u.provinsi, u.negara].filter(Boolean);
                document.getElementById('di-location').innerText = parts.length ? parts.join(', ') : '—';

                // Hosting tab
                const orders = u.orders || [];
                document.getElementById('hosting_count_badge').innerText = orders.length;
                if(orders.length === 0) {
                    document.getElementById('hosting-list-wrap').innerHTML = '<div class="text-center text-muted py-4" style="font-size:13px;"><i class="ph ph-cloud-slash"></i> Belum ada hosting</div>';
                } else {
                    let html = '';
                    orders.forEach(o => {
                        const statusBadge = o.status === 'active'
                            ? `<span class="quick-badge qb-ok"><i class="ph-fill ph-circle"></i> Active</span>`
                            : o.status === 'suspended'
                                ? `<span class="quick-badge qb-err"><i class="ph ph-x-circle"></i> Suspended</span>`
                                : `<span class="quick-badge qb-warn">${o.status}</span>`;
                        html += `<div class="hosting-row">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-1 mb-1">
                                <div>
                                    <span style="font-weight:700;">${o.domain || '—'}</span>
                                    <span style="color:var(--mut);margin-left:6px;font-size:11px;">Paket: ${o.nama_paket || o.plan_id || '—'}</span>
                                </div>
                                <div>${statusBadge}</div>
                            </div>
                            <div class="d-flex gap-3 flex-wrap" style="font-size:11px;color:var(--mut);">
                                <span><i class="ph ph-tag"></i> Username: <code>${o.username || '—'}</code></span>
                                <span><i class="ph ph-calendar"></i> Aktif: ${o.created_at ? o.created_at.split(' ')[0] : '—'}</span>
                                <span><i class="ph ph-warning-circle"></i> Exp: ${o.expiry_date || o.end_date || '—'}</span>
                            </div>
                        </div>`;
                    });
                    document.getElementById('hosting-list-wrap').innerHTML = html;
                }

                // Edit Tab
                document.getElementById('edit_id').value      = u.id;
                document.getElementById('edit_nama').value    = u.nama || '';
                document.getElementById('edit_email').value   = u.email || '';
                document.getElementById('edit_wa').value      = u.no_whatsapp || '';
                document.getElementById('edit_role').value    = u.role || 'client';
                document.getElementById('edit_status').value  = u.status || 'active';

                // Show 2FA disable checkbox only if user has 2FA
                document.getElementById('wrap_2fa_reset').style.display = parseInt(u.is_2fa_enabled) ? 'block' : 'none';
                document.getElementById('edit_disable_2fa').checked = false;
            });
    }

    function confirmDelete(id) {
        Swal.fire({ title:'Hapus Pengguna?', text:'Data tidak bisa dikembalikan!', icon:'warning',
            showCancelButton:true, confirmButtonColor:'var(--err)', cancelButtonColor:'var(--hover)',
            confirmButtonText:'Ya, Hapus!', background:'var(--card)', color:'var(--text)'
        }).then(r => { if(r.isConfirmed) location.href='users?delete_id='+id; });
    }

    <?php if(isset($_GET['res'])): ?>
        <?php $msgs = ['deleted'=>['success','Terhapus!','Data pengguna dihapus.'],'added'=>['success','Berhasil!','Pengguna baru ditambahkan.'],'edited'=>['success','Tersimpan!','Data pengguna diperbarui.'],'exists'=>['error','Gagal!','Email sudah terdaftar.']]; ?>
        <?php $m = $msgs[$_GET['res']] ?? null; if($m): ?>
        Swal.fire({ icon:'<?= $m[0] ?>', title:'<?= $m[1] ?>', text:'<?= $m[2] ?>', background:'var(--card)', color:'var(--text)', timer:2200, showConfirmButton:false });
        <?php endif; ?>
    <?php endif; ?>
</script>

<?php include __DIR__ . '/library/footer.php'; ?>