<?php
require_once __DIR__ . '/library/admin_session.php';
if(!defined('NS1')) include __DIR__ . '/../config/database.php';

if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    mysqli_query($conn, "DELETE FROM whm_servers WHERE id = $id");
    header("Location: whm_servers?res=deleted"); exit();
}
if (isset($_POST['add_whm'])) {
    $host  = mysqli_real_escape_string($conn, $_POST['whm_host']);
    $user  = mysqli_real_escape_string($conn, $_POST['whm_username']);
    $token = mysqli_real_escape_string($conn, $_POST['whm_token']);
    $limit = (int)$_POST['limit_cpanel'];
    mysqli_query($conn, "INSERT INTO whm_servers (whm_host, whm_username, whm_token, limit_cpanel) VALUES ('$host','$user','$token',$limit)");
    header("Location: whm_servers?res=added"); exit();
}
if (isset($_POST['edit_whm'])) {
    $id    = (int)$_POST['id'];
    $host  = mysqli_real_escape_string($conn, $_POST['whm_host']);
    $user  = mysqli_real_escape_string($conn, $_POST['whm_username']);
    $token = mysqli_real_escape_string($conn, $_POST['whm_token']);
    $limit = (int)$_POST['limit_cpanel'];
    if(!empty(trim($token)))
        mysqli_query($conn, "UPDATE whm_servers SET whm_host='$host',whm_username='$user',whm_token='$token',limit_cpanel=$limit WHERE id=$id");
    else
        mysqli_query($conn, "UPDATE whm_servers SET whm_host='$host',whm_username='$user',limit_cpanel=$limit WHERE id=$id");
    header("Location: whm_servers?res=edited"); exit();
}

$query = mysqli_query($conn, "
    SELECT w.*,
    (SELECT COUNT(id) FROM orders WHERE whm_id=w.id AND status IN ('active','suspended')) as used_cpanel,
    (SELECT COUNT(id) FROM orders WHERE whm_id=w.id AND status='active') as active_cpanel,
    (SELECT COUNT(id) FROM orders WHERE whm_id=w.id AND status='suspended') as susp_cpanel
    FROM whm_servers w ORDER BY w.id ASC");

// Aggregate stats
$all_servers = mysqli_fetch_all(mysqli_query($conn, "SELECT COUNT(*) total, SUM(limit_cpanel) total_limit,
    (SELECT COUNT(id) FROM orders WHERE status='active') total_active FROM whm_servers"), MYSQLI_ASSOC)[0] ?? [];

$page_title = "WHM Servers";
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
.server-progress { height:7px; border-radius:99px; background:var(--surface); border:1px solid var(--border); overflow:hidden; margin-top:5px; }
.server-progress-fill { height:100%; border-radius:99px; transition:width .3s; }
.server-host { font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:700; color:var(--text); }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="ph-fill ph-hard-drives me-2" style="color:var(--accent);"></i> WHM Server Nodes</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb bc">
            <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>">Admin</a></li>
            <li class="breadcrumb-item active">WHM Servers</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addModal" style="background:var(--accent);border:none;font-size:13px;padding:8px 16px;border-radius:8px;">
        <i class="ph ph-plus-circle me-1"></i> Tambah Server
    </button>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--as);color:var(--accent);"><i class="ph-fill ph-hard-drives"></i></div>
            <div><div class="stat-mini-val"><?= $all_servers['total'] ?? 0 ?></div><div class="stat-mini-lbl">Total Node</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-users"></i></div>
            <div><div class="stat-mini-val" style="color:var(--ok);"><?= $all_servers['total_active'] ?? 0 ?></div><div class="stat-mini-lbl">Akun Aktif</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--as);color:var(--accent);"><i class="ph-fill ph-gauge"></i></div>
            <div><div class="stat-mini-val"><?= $all_servers['total_limit'] ?? 0 ?></div><div class="stat-mini-lbl">Total Kapasitas</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-check-circle"></i></div>
            <?php 
                $used_total = $all_servers['total_active'] ?? 0;
                $cap_total  = $all_servers['total_limit'] ?? 1;
                $cap_pct    = min(100, round($used_total/$cap_total*100));
            ?>
            <div><div class="stat-mini-val" style="color:<?= $cap_pct > 80 ? 'var(--err)' : 'var(--ok)' ?>;"><?= $cap_pct ?>%</div><div class="stat-mini-lbl">Utilisasi</div></div>
        </div>
    </div>
</div>

<div class="card-c">
    <div class="ch d-flex align-items-center justify-content-between">
        <h3 class="ct"><i class="ph-fill ph-list-bullets me-2 text-primary"></i> Daftar Server WHM</h3>
        <span style="font-size:11px;color:var(--mut);">Load Balancer · <?= $all_servers['total'] ?? 0 ?> node</span>
    </div>
    <div class="cb p-0">
        <div class="table-responsive">
            <table class="tbl table-hover w-100 mb-0" id="tableWhmServers" style="font-size:12.5px;">
                <thead>
                    <tr>
                        <th style="width:50px;">Node</th>
                        <th>Hostname / Server</th>
                        <th>Root User</th>
                        <th style="width:220px;">Load / Kapasitas</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Distribusi</th>
                        <th class="text-center" style="width:100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($query) > 0):
                        while($row = mysqli_fetch_assoc($query)):
                            $used    = (int)$row['used_cpanel'];
                            $active  = (int)$row['active_cpanel'];
                            $susp    = (int)$row['susp_cpanel'];
                            $limit   = (int)$row['limit_cpanel'];
                            $pct     = $limit > 0 ? round($used/$limit*100) : 0;
                            $bar_col = $pct >= 90 ? 'var(--err)' : ($pct >= 70 ? 'var(--warn)' : 'var(--ok)');
                            $avail   = $limit - $used;
                    ?>
                    <tr>
                        <td class="fw-bold text-center" style="color:var(--accent); font-family:monospace;">#<?= $row['id'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:32px;height:32px;border-radius:8px;background:var(--as);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="ph-fill ph-hard-drives" style="color:var(--accent);font-size:16px;"></i>
                                </div>
                                <div>
                                    <div class="server-host"><?= htmlspecialchars($row['whm_host']) ?></div>
                                    <div style="font-size:10px;color:var(--mut);margin-top:1px;">
                                        <i class="ph ph-calendar"></i> Ditambahkan <?= date('d M Y', strtotime($row['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="font-family:monospace;font-size:12px;font-weight:600;"><?= htmlspecialchars($row['whm_username']) ?></span>
                        </td>
                        <td>
                            <div class="d-flex justify-content-between" style="font-size:10px;color:var(--mut);">
                                <span><span style="color:var(--text);font-weight:700;"><?= $used ?></span> terpakai</span>
                                <span style="color:<?= $bar_col ?>;font-weight:700;"><?= $pct ?>%</span>
                                <span><span style="color:var(--text);font-weight:700;"><?= $limit ?></span> limit</span>
                            </div>
                            <div class="server-progress">
                                <div class="server-progress-fill" style="width:<?= $pct ?>%; background:<?= $bar_col ?>;"></div>
                            </div>
                        </td>
                        <td class="text-center">
                            <?php if($used >= $limit): ?>
                                <span class="quick-badge qb-err"><i class="ph-fill ph-x-circle"></i> PENUH</span>
                            <?php elseif($pct >= 70): ?>
                                <span class="quick-badge qb-warn"><i class="ph-fill ph-warning"></i> HAMPIR</span>
                            <?php else: ?>
                                <span class="quick-badge qb-ok"><i class="ph-fill ph-check-circle"></i> TERSEDIA</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-1 justify-content-center">
                                <?php if($active > 0): ?>
                                <span class="quick-badge qb-ok"><i class="ph-fill ph-check"></i> <?= $active ?></span>
                                <?php endif; ?>
                                <?php if($susp > 0): ?>
                                <span class="quick-badge qb-warn"><i class="ph-fill ph-pause"></i> <?= $susp ?></span>
                                <?php endif; ?>
                                <?php if($active == 0 && $susp == 0): ?>
                                <span style="color:var(--mut);font-size:11px;">Kosong</span>
                                <?php endif; ?>
                                <span class="quick-badge qb-muted"><?= $avail ?> slot</span>
                            </div>
                        </td>
                        <td class="text-center">
                            <a href="<?= base_url('admin/whm_overview/' . $row['id']) ?>" class="ab py-1 px-2 me-1" title="Overview">
                                <i class="ph ph-eye"></i>
                            </a>
                            <button type="button" class="ab py-1 px-2 me-1" title="Edit"
                                onclick="openEditModal(<?= $row['id'] ?>, '<?= addslashes($row['whm_host']) ?>', '<?= addslashes($row['whm_username']) ?>', <?= $limit ?>)">
                                <i class="ph ph-pencil-simple"></i>
                            </button>
                            <button type="button" class="ab py-1 px-2 red" title="Hapus" onclick="confirmDelete(<?= $row['id'] ?>)">
                                <i class="ph ph-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="7" class="text-center py-5">
                        <div style="font-size:40px;color:var(--mut);margin-bottom:10px;"><i class="ph ph-hard-drives"></i></div>
                        <div style="color:var(--sub); font-weight:600;">Belum ada WHM Server terdaftar.</div>
                        <div style="font-size:12px;color:var(--mut);margin-top:4px;">Tambahkan server WHM untuk mulai menerima pesanan hosting.</div>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah WHM -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content mc">
            <div class="modal-header mh py-2">
                <h5 class="modal-title fs-6 fw-bold"><i class="ph-fill ph-plus-circle me-2" style="color:var(--accent);"></i> Tambah WHM Server</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-3">
                    <div class="mb-3 p-3 rounded" style="background:var(--as);border:1px solid var(--ba);">
                        <div style="font-size:11px;color:var(--accent);font-weight:600;"><i class="ph-fill ph-info me-1"></i> Limit CPanel adalah batas maksimal akun di node ini.</div>
                    </div>
                    <div class="mb-2"><label class="fl mb-1">Hostname WHM (Port 2087)</label>
                        <input type="url" name="whm_host" class="fc w-100" placeholder="https://ip:2087" required></div>
                    <div class="mb-2"><label class="fl mb-1">Username WHM (Root/Reseller)</label>
                        <input type="text" name="whm_username" class="fc w-100" placeholder="root" required></div>
                    <div class="mb-2"><label class="fl mb-1">API Token</label>
                        <textarea name="whm_token" class="fc w-100" rows="2" placeholder="Paste token WHM API..." required></textarea></div>
                    <div class="mb-2"><label class="fl mb-1">Batas Akun (Limit CPanel)</label>
                        <input type="number" name="limit_cpanel" class="fc w-100" value="25" min="1" required></div>
                </div>
                <div class="modal-footer mf py-2">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="color:var(--mut);">Batal</button>
                    <button type="submit" name="add_whm" class="btn btn-sm btn-primary fw-bold" style="background:var(--accent);border:none;">Simpan Server</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit WHM -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content mc">
            <div class="modal-header mh py-2">
                <h5 class="modal-title fs-6 fw-bold"><i class="ph-fill ph-pencil-simple me-2" style="color:var(--accent);"></i> Edit WHM Server</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body py-3">
                    <div class="mb-2"><label class="fl mb-1">Hostname WHM</label>
                        <input type="url" name="whm_host" id="edit_host" class="fc w-100" required></div>
                    <div class="mb-2"><label class="fl mb-1">Username WHM</label>
                        <input type="text" name="whm_username" id="edit_user" class="fc w-100" required></div>
                    <div class="mb-2"><label class="fl mb-1">API Token <span style="color:var(--mut);">(kosongkan jika tidak diubah)</span></label>
                        <textarea name="whm_token" class="fc w-100" rows="2"></textarea></div>
                    <div class="mb-2"><label class="fl mb-1">Limit Akun CPanel</label>
                        <input type="number" name="limit_cpanel" id="edit_limit" class="fc w-100" min="1" required></div>
                </div>
                <div class="modal-footer mf py-2">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="color:var(--mut);">Batal</button>
                    <button type="submit" name="edit_whm" class="btn btn-sm btn-primary fw-bold" style="background:var(--accent);border:none;">Update Server</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function openEditModal(id, host, user, limit) {
        document.getElementById('edit_id').value    = id;
        document.getElementById('edit_host').value  = host;
        document.getElementById('edit_user').value  = user;
        document.getElementById('edit_limit').value = limit;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }
    function confirmDelete(id) {
        Swal.fire({ title:'Cabut Server WHM?', 
            text:'Pastikan sudah tidak ada akun aktif. Tindakan ini tidak bisa dibatalkan!',
            icon:'warning', showCancelButton:true, confirmButtonColor:'var(--err)',
            cancelButtonColor:'var(--hover)', confirmButtonText:'Ya, Cabut Node!',
            background:'var(--card)', color:'var(--text)'
        }).then(r => { if(r.isConfirmed) location.href='whm_servers?delete_id='+id; });
    }
    <?php if(isset($_GET['res'])): ?>
        <?php $msgs=['deleted'=>['success','Dicabut!','Server WHM dihapus dari sistem.'],'added'=>['success','Tertambahkan!','Server masuk ke load balancer.'],'edited'=>['success','Diperbarui!','Kredensial server tersimpan.']]; ?>
        <?php $m=$msgs[$_GET['res']]??null; if($m): ?>
        Swal.fire({ icon:'<?= $m[0] ?>', title:'<?= $m[1] ?>', text:'<?= $m[2] ?>', background:'var(--card)', color:'var(--text)', timer:2200, showConfirmButton:false });
        <?php endif; ?>
    <?php endif; ?>
</script>

<?php include __DIR__ . '/library/footer.php'; ?>
