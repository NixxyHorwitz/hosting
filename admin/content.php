<?php
/**
 * Admin - Content Manager
 * Mengelola: Banner Slider + Running Text
 */
require_once __DIR__ . '/library/admin_session.php';
if (!defined('NS1')) include __DIR__ . '/../config/database.php';

// ─── Auto-create tables ────────────────────────────────────────
$chk_ban = mysqli_query($conn, "SHOW TABLES LIKE 'banners'");
if (mysqli_num_rows($chk_ban) == 0) {
    mysqli_query($conn, "CREATE TABLE banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255),
        subtitle VARCHAR(255),
        image VARCHAR(255),
        button_text VARCHAR(100) DEFAULT 'Pesan Sekarang',
        button_url VARCHAR(255) DEFAULT '/hosting/services',
        bg_color VARCHAR(150) DEFAULT 'linear-gradient(135deg,#0a1628,#1e3a6e)',
        is_active TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0
    )");
}

// Auto-add running_text columns if missing
$chk_rt = mysqli_query($conn, "SHOW COLUMNS FROM settings LIKE 'running_text'");
if (mysqli_num_rows($chk_rt) == 0) {
    mysqli_query($conn, "ALTER TABLE settings ADD COLUMN running_text TEXT DEFAULT NULL, ADD COLUMN running_text_enabled TINYINT(1) DEFAULT 0");
}

// ─── Update Running Text ───────────────────────────────────────
if (isset($_POST['update_running_text'])) {
    $rt    = mysqli_real_escape_string($conn, $_POST['running_text']);
    $rt_en = isset($_POST['running_text_enabled']) ? 1 : 0;
    mysqli_query($conn, "UPDATE settings SET running_text='$rt', running_text_enabled=$rt_en WHERE id=1");
    header("Location: " . base_url('admin/content') . "?res=saved"); exit();
}

// ─── Save Banner (Add/Edit) ────────────────────────────────────
if (isset($_POST['save_banner'])) {
    $bid    = (int)($_POST['banner_id'] ?? 0);
    $btitle = mysqli_real_escape_string($conn, $_POST['banner_title']);
    $bsub   = mysqli_real_escape_string($conn, $_POST['banner_subtitle']);
    $bbtn   = mysqli_real_escape_string($conn, $_POST['banner_button_text']);
    $burl   = mysqli_real_escape_string($conn, $_POST['banner_button_url']);
    $bbg    = mysqli_real_escape_string($conn, $_POST['banner_bg_color']);
    $bact   = isset($_POST['banner_is_active']) ? 1 : 0;
    $border = (int)($_POST['banner_sort_order'] ?? 0);
    $bimg   = '';

    if (!empty($_FILES['banner_image']['name'])) {
        $ext  = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
        $bimg = 'banner_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['banner_image']['tmp_name'], __DIR__ . '/../uploads/' . $bimg);
    }

    if ($bid > 0) {
        $img_sql = !empty($bimg) ? ", image='$bimg'" : '';
        mysqli_query($conn, "UPDATE banners SET title='$btitle', subtitle='$bsub', button_text='$bbtn', button_url='$burl', bg_color='$bbg', is_active=$bact, sort_order=$border$img_sql WHERE id=$bid");
    } else {
        mysqli_query($conn, "INSERT INTO banners (title,subtitle,image,button_text,button_url,bg_color,is_active,sort_order) VALUES ('$btitle','$bsub','$bimg','$bbtn','$burl','$bbg',$bact,$border)");
    }
    header("Location: " . base_url('admin/content') . "?res=saved"); exit();
}

// ─── Delete Banner ─────────────────────────────────────────────
if (isset($_GET['del_banner'])) {
    mysqli_query($conn, "DELETE FROM banners WHERE id=" . (int)$_GET['del_banner']);
    header("Location: " . base_url('admin/content') . "?res=deleted"); exit();
}

// ─── Fetch Data ────────────────────────────────────────────────
$set          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT running_text, running_text_enabled FROM settings WHERE id=1"));
$banners_list = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM banners ORDER BY sort_order ASC, id DESC"), MYSQLI_ASSOC);

$page_title = "Content Manager";
include __DIR__ . '/library/header.php';
?>

<style>
.preview-banner {
    border-radius: 8px;
    overflow: hidden;
    height: 90px;
    display: flex;
    align-items: center;
    padding: 14px 18px;
    position: relative;
}
.preview-banner h6 { color: #fff; margin: 0 0 4px; font-size: 14px; }
.preview-banner p  { color: rgba(255,255,255,.75); font-size: 11px; margin: 0; }
.preview-banner .preview-btn {
    margin-top: 8px;
    display: inline-block;
    background: #fff;
    color: #1e3a6e;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 12px;
    border-radius: 20px;
}
.rt-preview {
    background: linear-gradient(90deg, var(--accent), #0ea5e9);
    border-radius: 6px;
    height: 32px;
    display: flex;
    align-items: center;
    overflow: hidden;
    margin-top: 10px;
}
.rt-label {
    background: rgba(0,0,0,.25);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .5px;
    padding: 0 10px;
    height: 32px;
    display: flex;
    align-items: center;
    white-space: nowrap;
    flex-shrink: 0;
}
.rt-track { flex:1; overflow:hidden; position:relative; height:32px; }
.rt-inner {
    display: inline-flex;
    white-space: nowrap;
    animation: rtRun 20s linear infinite;
    height: 32px;
    align-items: center;
    gap: 40px;
    color: #fff;
    font-size: 12px;
}
@keyframes rtRun { from { transform: translateX(0); } to { transform: translateX(-50%); } }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="ph-fill ph-images me-2" style="color:var(--accent);"></i> Content Manager</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb bc">
            <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>">Admin</a></li>
            <li class="breadcrumb-item active">Content Manager</li>
        </ol></nav>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     SECTION 1: RUNNING TEXT
════════════════════════════════════════════════════════════ -->
<div class="card-c mb-4">
    <div class="ch d-flex align-items-center justify-content-between">
        <h3 class="ct"><i class="ph-fill ph-megaphone me-2" style="color:var(--accent);"></i> Running Text</h3>
        <span style="font-size:11px;color:var(--mut);">Ditampilkan sebagai ticker di atas dashboard user</span>
    </div>
    <div class="cb">
        <form action="" method="POST">
            <div class="mb-3 d-flex align-items-center gap-3">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-size:13px;color:var(--text);">
                    <input type="checkbox" name="running_text_enabled" value="1"
                        <?= !empty($set['running_text_enabled']) ? 'checked' : '' ?>
                        style="width:15px;height:15px;" id="rt_toggle"
                        onchange="document.getElementById('rt_preview_wrap').style.opacity=this.checked?'1':'.3'">
                    <span class="fw-medium">Aktifkan running text di dashboard user</span>
                </label>
            </div>
            <div class="mb-3">
                <label class="fl mb-1">Teks Berjalan</label>
                <input type="text" name="running_text" id="rt_input" class="fc w-100"
                    value="<?= htmlspecialchars($set['running_text'] ?? '') ?>"
                    placeholder="🚀 Hosting cepat &amp; terpercaya | ✅ SSL Gratis | 💬 Support 24/7"
                    oninput="updatePreview(this.value)">
                <div style="font-size:11px;color:var(--mut);margin-top:4px;">
                    Pisahkan poin dengan <code style="color:var(--accent);">|</code>. Emoji dan HTML entities didukung.
                </div>
            </div>

            <!-- Live Preview -->
            <div id="rt_preview_wrap" style="opacity:<?= !empty($set['running_text_enabled']) ? '1' : '.3' ?>;">
                <div style="font-size:11px;color:var(--mut);margin-bottom:4px;">Preview:</div>
                <div class="rt-preview">
                    <div class="rt-label"><i class="ph-fill ph-megaphone me-1"></i> INFO</div>
                    <div class="rt-track">
                        <div class="rt-inner" id="rt_preview_text">
                            <span><?= htmlspecialchars($set['running_text'] ?? 'Teks berjalan akan tampil di sini...') ?></span>
                            <span><?= htmlspecialchars($set['running_text'] ?? 'Teks berjalan akan tampil di sini...') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" name="update_running_text" class="btn btn-sm btn-primary" style="background:var(--accent);border:none;font-size:13px;">
                    <i class="ph ph-floppy-disk me-1"></i> Simpan Running Text
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     SECTION 2: BANNER SLIDER
════════════════════════════════════════════════════════════ -->
<div class="card-c">
    <div class="ch d-flex align-items-center justify-content-between">
        <h3 class="ct"><i class="ph-fill ph-slideshow me-2" style="color:var(--accent);"></i> Banner Slider</h3>
        <button class="btn btn-sm" style="background:var(--accent);border:none;color:#fff;font-size:12px;border-radius:6px;padding:6px 14px;" onclick="showBannerForm(0)">
            <i class="ph ph-plus me-1"></i> Tambah Banner
        </button>
    </div>
    <div class="cb p-0">

        <!-- List Banners -->
        <?php if (empty($banners_list)): ?>
        <div class="text-center py-5 text-muted" style="font-size:13px;">
            <i class="ph ph-image-broken d-block mb-2" style="font-size:36px;"></i>
            Belum ada banner. Klik "Tambah Banner" untuk membuat yang pertama.
        </div>
        <?php else: ?>
        <div class="p-3">
            <div class="row g-3" id="banner-list">
                <?php foreach($banners_list as $b): ?>
                <div class="col-md-6" id="banner-card-<?= $b['id'] ?>">
                    <div class="p-0 rounded overflow-hidden" style="border:1px solid var(--border);">
                        <!-- Mini preview -->
                        <div class="preview-banner" style="background:<?= htmlspecialchars($b['bg_color']) ?>;">
                            <?php if(!empty($b['image'])): ?>
                            <img src="<?= base_url('uploads/'.$b['image']) ?>" style="position:absolute;right:0;top:0;height:100%;opacity:.15;object-fit:cover;" alt="">
                            <?php endif; ?>
                            <div style="position:relative;z-index:1;">
                                <h6><?= htmlspecialchars($b['title']) ?></h6>
                                <?php if(!empty($b['subtitle'])): ?>
                                <p><?= htmlspecialchars($b['subtitle']) ?></p>
                                <?php endif; ?>
                                <span class="preview-btn"><?= htmlspecialchars($b['button_text']) ?></span>
                            </div>
                        </div>
                        <!-- Controls -->
                        <div class="d-flex align-items-center justify-content-between px-3 py-2" style="background:var(--surface);">
                            <div class="d-flex align-items-center gap-2">
                                <span style="font-size:10px;color:var(--mut);">Urutan: <?= $b['sort_order'] ?></span>
                                <?php if($b['is_active']): ?>
                                <span style="font-size:10px;background:var(--oks);color:var(--ok);padding:1px 7px;border-radius:4px;">Aktif</span>
                                <?php else: ?>
                                <span style="font-size:10px;background:var(--surface);color:var(--mut);border:1px solid var(--border);padding:1px 7px;border-radius:4px;">Nonaktif</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-1">
                                <button class="ab py-1 px-2" title="Edit" onclick='showBannerForm(<?= $b["id"] ?>, <?= json_encode($b) ?>)'>
                                    <i class="ph ph-pencil-simple"></i>
                                </button>
                                <a href="content?del_banner=<?= $b['id'] ?>" class="ab py-1 px-2 red" title="Hapus" onclick="return confirm('Hapus banner ini?')">
                                    <i class="ph ph-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Form Banner (Slide-in) ── -->
        <div id="banner-form-wrap" style="display:none;" class="p-4 border-top" style="border-color:var(--border);">
            <h6 class="fw-bold mb-3" id="bf_label" style="color:var(--text);font-size:13px;">✚ Tambah Banner Baru</h6>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="banner_id" id="bf_id" value="0">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="fl mb-1">Judul Banner <span style="color:var(--err);">*</span></label>
                        <input type="text" name="banner_title" id="bf_title" class="fc w-100" required placeholder="Hosting Unlimited Terpercaya">
                    </div>
                    <div class="col-md-6">
                        <label class="fl mb-1">Subtitle</label>
                        <input type="text" name="banner_subtitle" id="bf_subtitle" class="fc w-100" placeholder="Uptime 99.9% · SSL Gratis · Migrasi Gratis">
                    </div>
                    <div class="col-md-4">
                        <label class="fl mb-1">Teks Tombol</label>
                        <input type="text" name="banner_button_text" id="bf_btn" class="fc w-100" value="Pesan Sekarang" placeholder="Pesan Sekarang">
                    </div>
                    <div class="col-md-5">
                        <label class="fl mb-1">URL Tombol</label>
                        <input type="text" name="banner_button_url" id="bf_url" class="fc w-100" value="/hosting/services" placeholder="/hosting/services">
                    </div>
                    <div class="col-md-1">
                        <label class="fl mb-1">Urutan</label>
                        <input type="number" name="banner_sort_order" id="bf_order" class="fc w-100" value="1" min="0">
                    </div>
                    <div class="col-md-2 d-flex align-items-end pb-1">
                        <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-size:13px;color:var(--text);">
                            <input type="checkbox" name="banner_is_active" id="bf_active" value="1" checked style="width:14px;height:14px;">
                            <span class="fw-medium">Aktif</span>
                        </label>
                    </div>
                    <div class="col-md-8">
                        <label class="fl mb-1">Warna / Gradient Background</label>
                        <input type="text" name="banner_bg_color" id="bf_bg" class="fc w-100"
                            value="linear-gradient(135deg,#0a1628,#1e3a6e)"
                            placeholder="linear-gradient(135deg,#0a1628,#1e3a6e)"
                            oninput="document.getElementById('bg_preview').style.background=this.value">
                        <div style="font-size:10px;color:var(--mut);margin-top:3px;">
                            Bisa CSS gradient atau warna solid. <span id="bg_preview" style="display:inline-block;width:20px;height:10px;border-radius:3px;vertical-align:middle;background:linear-gradient(135deg,#0a1628,#1e3a6e);"></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="fl mb-1">Gambar Background <span style="color:var(--mut);font-size:10px;">(opsional, maks 2MB)</span></label>
                        <input type="file" name="banner_image" class="fc w-100" accept="image/png,image/jpg,image/jpeg,image/webp">
                        <div id="bf_current_img" style="font-size:10px;color:var(--mut);margin-top:3px;"></div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3 pt-3 border-top" style="border-color:var(--border)!important;">
                    <button type="submit" name="save_banner" class="btn btn-sm btn-primary fw-bold" style="background:var(--accent);border:none;font-size:13px;">
                        <i class="ph ph-floppy-disk me-1"></i> Simpan Banner
                    </button>
                    <button type="button" class="btn btn-sm" style="color:var(--mut);font-size:13px;" onclick="hideBannerForm()">
                        <i class="ph ph-x me-1"></i> Batal
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Live preview running text
function updatePreview(val) {
    const p = document.getElementById('rt_preview_text');
    if (!p) return;
    const safe = val || 'Teks berjalan akan tampil di sini...';
    p.innerHTML = `<span>${safe}</span><span>${safe}</span>`;
}

// Banner Form
function showBannerForm(id, data) {
    document.getElementById('banner-form-wrap').style.display = 'block';
    document.getElementById('banner-form-wrap').scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    if (id > 0 && data) {
        document.getElementById('bf_label').textContent   = '✎ Edit Banner';
        document.getElementById('bf_id').value            = data.id;
        document.getElementById('bf_title').value         = data.title || '';
        document.getElementById('bf_subtitle').value      = data.subtitle || '';
        document.getElementById('bf_btn').value           = data.button_text || '';
        document.getElementById('bf_url').value           = data.button_url || '';
        document.getElementById('bf_bg').value            = data.bg_color || '';
        document.getElementById('bf_order').value         = data.sort_order || 0;
        document.getElementById('bf_active').checked      = data.is_active == 1;
        document.getElementById('bg_preview').style.background = data.bg_color || '';
        if (data.image) {
            document.getElementById('bf_current_img').textContent = 'Gambar saat ini: ' + data.image;
        }
    } else {
        document.getElementById('bf_label').textContent   = '✚ Tambah Banner Baru';
        document.getElementById('bf_id').value            = 0;
        document.getElementById('bf_title').value         = '';
        document.getElementById('bf_subtitle').value      = '';
        document.getElementById('bf_btn').value           = 'Pesan Sekarang';
        document.getElementById('bf_url').value           = '/hosting/services';
        document.getElementById('bf_bg').value            = 'linear-gradient(135deg,#0a1628,#1e3a6e)';
        document.getElementById('bf_order').value         = 1;
        document.getElementById('bf_active').checked      = true;
        document.getElementById('bf_current_img').textContent = '';
        document.getElementById('bg_preview').style.background = 'linear-gradient(135deg,#0a1628,#1e3a6e)';
    }
}

function hideBannerForm() {
    document.getElementById('banner-form-wrap').style.display = 'none';
}

<?php if(isset($_GET['res'])): ?>
const resMap = { saved: ['success','Tersimpan!','Data berhasil disimpan.'], deleted: ['warning','Dihapus!','Banner telah dihapus.'] };
const r = resMap['<?= $_GET['res'] ?>'];
if (r) Swal.fire({ icon: r[0], title: r[1], text: r[2], background: 'var(--card)', color: 'var(--text)', timer: 2000, showConfirmButton: false });
<?php endif; ?>
</script>

<?php include __DIR__ . '/library/footer.php'; ?>
