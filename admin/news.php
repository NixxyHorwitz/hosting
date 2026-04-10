<?php
/**
 * Admin - News / Announcements Manager
 * Mengelola: Pengumuman yang tampil di dashboard user
 */
require_once __DIR__ . '/library/admin_session.php';
if (!defined('NS1')) include __DIR__ . '/../config/database.php';

// ─── Auto-create table ─────────────────────────────────────────
$chk = mysqli_query($conn, "SHOW TABLES LIKE 'announcements'");
if (mysqli_num_rows($chk) == 0) {
    mysqli_query($conn, "CREATE TABLE announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        category ENUM('info','warning','promo','maintenance') DEFAULT 'info',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");
    // Seed sample
    mysqli_query($conn, "INSERT INTO announcements (title, content, category) VALUES
        ('Selamat datang di Panel Hosting', 'Kelola semua layanan hosting Anda dengan mudah dari satu tempat.', 'info'),
        ('Maintenance Terjadwal', 'Pemeliharaan server dijadwalkan setiap Minggu pukul 02.00–04.00 WIB.', 'maintenance')");
}

// ─── Save Announcement (Add/Edit) ─────────────────────────────
if (isset($_POST['save_ann'])) {
    $aid   = (int)($_POST['ann_id'] ?? 0);
    $title = mysqli_real_escape_string($conn, $_POST['ann_title']);
    $cont  = mysqli_real_escape_string($conn, $_POST['ann_content']);
    $cat   = mysqli_real_escape_string($conn, $_POST['ann_category']);
    $act   = isset($_POST['ann_is_active']) ? 1 : 0;

    if ($aid > 0) {
        mysqli_query($conn, "UPDATE announcements SET title='$title', content='$cont', category='$cat', is_active=$act WHERE id=$aid");
    } else {
        mysqli_query($conn, "INSERT INTO announcements (title, content, category, is_active) VALUES ('$title','$cont','$cat',$act)");
    }
    header("Location: " . base_url('admin/news') . "?res=saved"); exit();
}

// ─── Toggle Active ─────────────────────────────────────────────
if (isset($_GET['toggle'])) {
    $tid = (int)$_GET['toggle'];
    mysqli_query($conn, "UPDATE announcements SET is_active = 1 - is_active WHERE id=$tid");
    header("Location: " . base_url('admin/news') . "?res=saved"); exit();
}

// ─── Delete ───────────────────────────────────────────────────
if (isset($_GET['del'])) {
    mysqli_query($conn, "DELETE FROM announcements WHERE id=" . (int)$_GET['del']);
    header("Location: " . base_url('admin/news') . "?res=deleted"); exit();
}

// ─── Fetch ────────────────────────────────────────────────────
$ann_list = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM announcements ORDER BY created_at DESC"), MYSQLI_ASSOC);
$total    = count($ann_list);
$total_active = count(array_filter($ann_list, fn($a) => $a['is_active']));

$page_title = "News & Announcements";
include __DIR__ . '/library/header.php';

$cat_config = [
    'info'        => ['label' => 'Info',       'icon' => 'ph-info',             'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,.12)'],
    'warning'     => ['label' => 'Warning',    'icon' => 'ph-warning',          'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,.12)'],
    'promo'       => ['label' => 'Promo',      'icon' => 'ph-tag',              'color' => '#10b981', 'bg' => 'rgba(16,185,129,.12)'],
    'maintenance' => ['label' => 'Maintenance','icon' => 'ph-wrench',           'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,.12)'],
];
?>

<style>
.ann-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px 18px;
    transition: box-shadow .2s;
    position: relative;
}
.ann-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.15); }
.ann-cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 10.5px;
    font-weight: 700;
}
.stat-mini { background:var(--card);border:1px solid var(--border);border-radius:10px;padding:14px 18px;display:flex;align-items:center;gap:12px; }
.stat-mini-icon { width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px; }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="ph-fill ph-megaphone me-2" style="color:var(--accent);"></i> News &amp; Announcements</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb bc">
            <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>">Admin</a></li>
            <li class="breadcrumb-item active">News</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary fw-bold" onclick="openAnnModal(0)" style="background:var(--accent);border:none;font-size:13px;padding:8px 16px;border-radius:8px;">
        <i class="ph ph-plus me-1"></i> Tambah Pengumuman
    </button>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--as);color:var(--accent);"><i class="ph-fill ph-newspaper"></i></div>
            <div>
                <div style="font-size:20px;font-weight:800;"><?= $total ?></div>
                <div style="font-size:10px;color:var(--mut);font-weight:600;text-transform:uppercase;">Total</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:var(--oks);color:var(--ok);"><i class="ph-fill ph-check-circle"></i></div>
            <div>
                <div style="font-size:20px;font-weight:800;color:var(--ok);"><?= $total_active ?></div>
                <div style="font-size:10px;color:var(--mut);font-weight:600;text-transform:uppercase;">Aktif</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:rgba(245,158,11,.15);color:#f59e0b;"><i class="ph-fill ph-warning"></i></div>
            <div>
                <div style="font-size:20px;font-weight:800;color:#f59e0b;"><?= count(array_filter($ann_list, fn($a) => $a['category'] === 'warning' || $a['category'] === 'maintenance')) ?></div>
                <div style="font-size:10px;color:var(--mut);font-weight:600;text-transform:uppercase;">Alert</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini">
            <div class="stat-mini-icon" style="background:rgba(16,185,129,.15);color:#10b981;"><i class="ph-fill ph-tag"></i></div>
            <div>
                <div style="font-size:20px;font-weight:800;color:#10b981;"><?= count(array_filter($ann_list, fn($a) => $a['category'] === 'promo')) ?></div>
                <div style="font-size:10px;color:var(--mut);font-weight:600;text-transform:uppercase;">Promo</div>
            </div>
        </div>
    </div>
</div>

<!-- List Pengumuman -->
<div class="card-c">
    <div class="ch d-flex align-items-center justify-content-between">
        <h3 class="ct"><i class="ph-fill ph-list-bullets me-2 text-primary"></i> Daftar Pengumuman</h3>
        <div class="d-flex gap-2 align-items-center">
            <select id="filterCat" class="fc" style="font-size:12px;padding:4px 8px;" onchange="filterByCategory(this.value)">
                <option value="">Semua Kategori</option>
                <option value="info">ℹ️ Info</option>
                <option value="warning">⚠️ Warning</option>
                <option value="promo">🏷️ Promo</option>
                <option value="maintenance">🔧 Maintenance</option>
            </select>
        </div>
    </div>
    <div class="cb">
        <?php if(empty($ann_list)): ?>
        <div class="text-center py-5 text-muted" style="font-size:13px;">
            <i class="ph ph-bell-slash d-block mb-2" style="font-size:36px;"></i>
            Belum ada pengumuman. Klik "Tambah Pengumuman" untuk memulai.
        </div>
        <?php else: ?>
        <div class="row g-3" id="ann-list">
            <?php foreach($ann_list as $ann):
                $cfg = $cat_config[$ann['category']] ?? $cat_config['info'];
            ?>
            <div class="col-md-6 ann-item-wrap" data-cat="<?= $ann['category'] ?>">
                <div class="ann-card" style="<?= !$ann['is_active'] ? 'opacity:.55;' : '' ?>">
                    <!-- Category & Status badges -->
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:32px;height:32px;border-radius:8px;background:<?= $cfg['bg'] ?>;color:<?= $cfg['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:15px;">
                                <i class="ph-fill <?= $cfg['icon'] ?>"></i>
                            </div>
                            <span class="ann-cat-badge" style="background:<?= $cfg['bg'] ?>;color:<?= $cfg['color'] ?>;">
                                <?= strtoupper($cfg['label']) ?>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <a href="news?toggle=<?= $ann['id'] ?>" title="<?= $ann['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>"
                                class="ab py-1 px-2" style="color:<?= $ann['is_active'] ? 'var(--ok)' : 'var(--mut)' ?>;">
                                <i class="ph <?= $ann['is_active'] ? 'ph-eye' : 'ph-eye-slash' ?>"></i>
                            </a>
                            <button class="ab py-1 px-2" title="Edit" onclick='openAnnModal(<?= $ann["id"] ?>, <?= json_encode($ann) ?>)'>
                                <i class="ph ph-pencil-simple"></i>
                            </button>
                            <a href="news?del=<?= $ann['id'] ?>" class="ab py-1 px-2 red" title="Hapus" onclick="return confirm('Hapus pengumuman ini?')">
                                <i class="ph ph-trash"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="fw-bold mb-1" style="color:var(--text);font-size:13.5px;"><?= htmlspecialchars($ann['title']) ?></div>
                    <?php if(!empty($ann['content'])): ?>
                    <div style="font-size:12px;color:var(--mut);line-height:1.6;"><?= htmlspecialchars(mb_substr($ann['content'], 0, 120)) ?><?= mb_strlen($ann['content']) > 120 ? '...' : '' ?></div>
                    <?php endif; ?>

                    <!-- Footer -->
                    <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top" style="border-color:var(--border)!important;">
                        <span style="font-size:10.5px;color:var(--mut);"><i class="ph ph-clock me-1"></i><?= date('d M Y H:i', strtotime($ann['created_at'])) ?></span>
                        <?php if(!$ann['is_active']): ?>
                        <span style="font-size:10px;color:var(--mut);background:var(--surface);border:1px solid var(--border);padding:1px 7px;border-radius:4px;">Tersembunyi</span>
                        <?php else: ?>
                        <span style="font-size:10px;color:var(--ok);background:var(--oks);padding:1px 7px;border-radius:4px;">Tampil</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════ MODAL ADD/EDIT ══════════════ -->
<div class="modal fade" id="annModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content mc">
            <div class="modal-header mh py-2">
                <h5 class="modal-title fs-6 fw-bold" id="annModalTitle">
                    <i class="ph-fill ph-megaphone me-2" style="color:var(--accent);"></i>
                    Tambah Pengumuman
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="ann_id" id="af_id" value="0">
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="fl mb-1">Judul Pengumuman <span style="color:var(--err);">*</span></label>
                        <input type="text" name="ann_title" id="af_title" class="fc w-100" required placeholder="Judul singkat dan jelas...">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="fl mb-1">Kategori</label>
                            <select name="ann_category" id="af_cat" class="fc w-100">
                                <option value="info">ℹ️ Info</option>
                                <option value="warning">⚠️ Warning</option>
                                <option value="promo">🏷️ Promo</option>
                                <option value="maintenance">🔧 Maintenance</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end pb-1">
                            <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-size:13px;color:var(--text);">
                                <input type="checkbox" name="ann_is_active" id="af_active" value="1" checked style="width:15px;height:15px;">
                                <span class="fw-medium">Tampilkan ke user</span>
                            </label>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="fl mb-1">Isi / Deskripsi <span style="color:var(--mut);font-size:10px;">(opsional)</span></label>
                        <textarea name="ann_content" id="af_content" class="fc w-100" rows="4" placeholder="Penjelasan lebih lanjut tentang pengumuman ini..."></textarea>
                    </div>
                </div>
                <div class="modal-footer mf py-2">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="color:var(--mut);">Batal</button>
                    <button type="submit" name="save_ann" class="btn btn-sm btn-primary fw-bold" style="background:var(--accent);border:none;">
                        <i class="ph ph-floppy-disk me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function openAnnModal(id, data) {
    const modal = new bootstrap.Modal(document.getElementById('annModal'));
    if (id > 0 && data) {
        document.getElementById('annModalTitle').innerHTML = '<i class="ph-fill ph-note-pencil me-2" style="color:var(--accent);"></i> Edit Pengumuman';
        document.getElementById('af_id').value      = data.id;
        document.getElementById('af_title').value   = data.title || '';
        document.getElementById('af_cat').value     = data.category || 'info';
        document.getElementById('af_content').value = data.content || '';
        document.getElementById('af_active').checked = data.is_active == 1;
    } else {
        document.getElementById('annModalTitle').innerHTML = '<i class="ph-fill ph-megaphone me-2" style="color:var(--accent);"></i> Tambah Pengumuman';
        document.getElementById('af_id').value      = 0;
        document.getElementById('af_title').value   = '';
        document.getElementById('af_cat').value     = 'info';
        document.getElementById('af_content').value = '';
        document.getElementById('af_active').checked = true;
    }
    modal.show();
}

function filterByCategory(cat) {
    document.querySelectorAll('.ann-item-wrap').forEach(el => {
        el.style.display = (!cat || el.dataset.cat === cat) ? '' : 'none';
    });
}

<?php if(isset($_GET['res'])): ?>
const msgs = { saved: ['success','Tersimpan!','Pengumuman berhasil disimpan.'], deleted: ['warning','Dihapus!','Pengumuman telah dihapus.'] };
const m = msgs['<?= $_GET['res'] ?>'];
if (m) Swal.fire({ icon:m[0], title:m[1], text:m[2], background:'var(--card)', color:'var(--text)', timer:2000, showConfirmButton:false });
<?php endif; ?>
</script>

<?php include __DIR__ . '/library/footer.php'; ?>
