<?php
require_once __DIR__ . '/library/admin_session.php';
if(!defined('NS1')) include __DIR__ . '/../config/database.php';

if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    mysqli_query($conn, "DELETE FROM users WHERE id = $id");
    header("Location: users?res=deleted"); exit();
}
if (isset($_POST['add_user'])) {
    $nama  = mysqli_real_escape_string($conn, $_POST['nama']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $wa    = mysqli_real_escape_string($conn, $_POST['no_whatsapp']);
    $role  = mysqli_real_escape_string($conn, $_POST['role']);
    $cek   = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
    if(mysqli_num_rows($cek) > 0) { header("Location: users?res=exists"); exit(); }
    mysqli_query($conn, "INSERT INTO users (nama, email, password, role, no_whatsapp) VALUES ('$nama','$email','$pass','$role','$wa')");
    header("Location: users?res=added"); exit();
}
if (isset($_POST['edit_user'])) {
    $id   = mysqli_real_escape_string($conn, $_POST['id']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $email= mysqli_real_escape_string($conn, $_POST['email']);
    $wa   = mysqli_real_escape_string($conn, $_POST['no_whatsapp']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    if(!empty($_POST['password'])) {
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET nama='$nama',email='$email',password='$pass',role='$role',no_whatsapp='$wa' WHERE id='$id'");
    } else {
        mysqli_query($conn, "UPDATE users SET nama='$nama',email='$email',role='$role',no_whatsapp='$wa' WHERE id='$id'");
    }
    header("Location: users?res=edited"); exit();
}

$query = mysqli_query($conn, "SELECT u.*,
    (SELECT COUNT(id) FROM orders WHERE user_id=u.id) as total_hosting,
    (SELECT COUNT(id) FROM invoices WHERE user_id=u.id AND status='unpaid') as unpaid_inv
    FROM users u ORDER BY u.role ASC, u.id DESC");

$stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total,
    SUM(role='client') clients, SUM(role='admin') admins,
    (SELECT COUNT(DISTINCT user_id) FROM orders WHERE status='active') active_users
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
.user-ava { width:34px; height:34px; border-radius:10px; background:var(--as); color:var(--accent); font-weight:800; font-size:13px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.user-ava.admin { background:var(--ws); color:var(--warn); }
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
            <div class="stat-mini-icon" style="background:var(--ws);color:var(--warn);"><i class="ph-fill ph-shield-check"></i></div>
            <div><div class="stat-mini-val" style="color:var(--warn);"><?= $stats['admins'] ?></div><div class="stat-mini-lbl">Admin</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-check-circle"></i></div>
            <div><div class="stat-mini-val" style="color:var(--ok);"><?= $stats['active_users'] ?></div><div class="stat-mini-lbl">Punya Hosting</div></div>
        </div>
    </div>
</div>

<div class="card-c">
    <div class="ch d-flex align-items-center justify-content-between">
        <h3 class="ct"><i class="ph-fill ph-list-bullets me-2 text-primary"></i> Semua Pengguna Terdaftar</h3>
        <span style="font-size:11px;color:var(--mut);"><?= $stats['total'] ?> user · <?= $stats['clients'] ?> client</span>
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
                        <th>Role</th>
                        <th>Bergabung</th>
                        <th class="text-center" style="width:80px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = mysqli_fetch_assoc($query)): 
                        $is_admin = ($user['role'] == 'admin');
                        $initial = strtoupper(substr($user['nama'], 0, 1));
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-ava <?= $is_admin ? 'admin' : '' ?>"><?= $initial ?></div>
                                <div>
                                    <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($user['nama']) ?></div>
                                    <div style="font-size:10px;color:var(--mut);">ID #<?= $user['id'] ?></div>
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
                                <span class="quick-badge qb-ok"><i class="ph-fill ph-hard-drives"></i> <?= $user['total_hosting'] ?></span>
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
                        <td>
                            <?php if($is_admin): ?>
                                <span class="quick-badge qb-warn"><i class="ph-fill ph-shield-check"></i> ADMIN</span>
                            <?php else: ?>
                                <span class="quick-badge qb-muted"><i class="ph ph-user"></i> CLIENT</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(!empty($user['created_at'])): ?>
                            <div style="font-size:11px;"><?= date('d M Y', strtotime($user['created_at'])) ?></div>
                            <?php else: ?><span style="color:var(--mut);">—</span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button type="button" class="ab py-1 px-2 me-1" title="Edit"
                                onclick="openEditModal(<?= $user['id'] ?>, '<?= addslashes($user['nama']) ?>', '<?= addslashes($user['email']) ?>', '<?= addslashes($user['no_whatsapp'] ?? '') ?>', '<?= $user['role'] ?>')">
                                <i class="ph ph-pencil-simple"></i>
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

<!-- Modal Tambah User -->
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

<!-- Modal Edit User -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content mc">
            <div class="modal-header mh py-2">
                <h5 class="modal-title fs-6 fw-bold"><i class="ph-fill ph-pencil-simple me-2" style="color:var(--accent);"></i> Edit Pengguna</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body py-3">
                    <div class="mb-2"><label class="fl mb-1">Nama Lengkap</label>
                        <input type="text" name="nama" id="edit_nama" class="fc w-100" required></div>
                    <div class="mb-2"><label class="fl mb-1">Email</label>
                        <input type="email" name="email" id="edit_email" class="fc w-100" required></div>
                    <div class="mb-2"><label class="fl mb-1">Password <span style="color:var(--mut);">(kosongkan jika tidak diganti)</span></label>
                        <input type="password" name="password" class="fc w-100" placeholder="••••••••"></div>
                    <div class="mb-2"><label class="fl mb-1">No. WhatsApp</label>
                        <input type="text" name="no_whatsapp" id="edit_wa" class="fc w-100"></div>
                    <div class="mb-2"><label class="fl mb-1">Hak Akses</label>
                        <select name="role" id="edit_role" class="fc w-100">
                            <option value="client">Client</option>
                            <option value="admin">Admin</option>
                        </select></div>
                </div>
                <div class="modal-footer mf py-2">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="color:var(--mut);">Batal</button>
                    <button type="submit" name="edit_user" class="btn btn-sm btn-primary fw-bold" style="background:var(--accent);border:none;">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function openEditModal(id, nama, email, wa, role) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nama').value = nama;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_wa').value = wa;
        document.getElementById('edit_role').value = role;
        new bootstrap.Modal(document.getElementById('editModal')).show();
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