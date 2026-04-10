<?php
require_once __DIR__ . '/library/admin_session.php';
require_once __DIR__ . '/../config/database.php';

// Auto-migration
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS landing_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_name VARCHAR(50) NOT NULL UNIQUE,
    content_json LONGTEXT NOT NULL
);");

// Seed defaults if empty
$check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM landing_content"));
if ($check['c'] == 0) {
    $defaults = [
        'hero' => ['heading' => '#ThinkBig #GrowBigger', 'subheading' => 'Onlinekan Bisnismu Sekarang dengan Web Hosting Indonesia', 'description' => 'Buat website dan email untuk bisnismu dan mulai mendunia dengan layanan web hosting Indonesia. Dapatkan hosting dengan kecepatan dan keamanan terbaik hanya Rp 99.000 setahun.', 'button_text' => 'MULAI', 'image_url' => 'https://www.rumahweb.com/assets/img/hero/orang-rumahweb-v2.webp'],
        'navbar' => ['site_name' => 'sobathosting', 'tagline' => 'Painless hosting solution', 'logo_url' => '', 'nav_links' => [['label' => 'DOMAIN', 'url' => '#'], ['label' => 'HOSTING', 'url' => '#hosting'], ['label' => 'WEBSITE', 'url' => '#'], ['label' => 'PROMO', 'url' => '#']]],
        'domain_bar' => ['title' => 'Website beken berawal dari domain keren', 'tlds' => [['ext' => '.com', 'price' => 'Rp119.900'], ['ext' => '.id', 'price' => 'Rp219.000'], ['ext' => '.xyz', 'price' => 'Rp35.500'], ['ext' => '.online', 'price' => 'Rp27.500']]],
        'trust' => ['title' => '155.000+ Pelanggan memilih kami karena ...', 'logos' => [['name' => 'ICANN Accredited Registrar', 'icon' => 'fa-certificate'], ['name' => 'Google Cloud Partner', 'icon' => 'fa-google'], ['name' => 'cPanel NOC Partner', 'icon' => 'fa-c'], ['name' => 'Litespeed Partner', 'icon' => 'fa-bolt'], ['name' => 'Imunify360 Partner', 'icon' => 'fa-shield-halved']]],
        'features' => [['title' => 'Pembuatan Website', 'description' => 'Jasa pembuatan website instan hanya dalam 2 x 24 jam.', 'icon' => 'fa-table-columns'], ['title' => 'VPS', 'description' => 'VPS dengan SSD/NVMe untuk performa terbaik.', 'icon' => 'fa-server'], ['title' => 'Email Bisnis', 'description' => 'Email bisnis kapasitas besar yg dilengkapi tools kolaborasi.', 'icon' => 'fa-envelope-open-text'], ['title' => 'Dedicated Server', 'description' => 'Layanan sewa server branded dengan spesifikasi terbaik.', 'icon' => 'fa-microchip']],
        'styles' => ['color_primary' => '#0d6efd', 'color_secondary' => '#0dcaf0', 'color_accent' => '#f39c12', 'color_domain_bar' => '#8cc63f', 'font_family' => "'Montserrat', sans-serif", 'hero_bg_pattern' => 'none'],
        'footer' => ['company_name' => 'sobathosting', 'description' => 'Memberikan layanan infrastruktur web terbaik sejak 2020 dengan teknologi server paling mutakhir di Indonesia.', 'email' => 'support@sobathosting.com', 'phone' => '+62 812 3456 789'],
    ];
    foreach ($defaults as $key => $val) {
        $j = mysqli_real_escape_string($conn, json_encode($val));
        mysqli_query($conn, "INSERT IGNORE INTO landing_content (section_name, content_json) VALUES ('$key', '$j')");
    }
}

// Ensure all sections exist (migration)
$sections_needed = ['hero', 'navbar', 'domain_bar', 'trust', 'features', 'styles', 'footer'];
foreach ($sections_needed as $s) {
    mysqli_query($conn, "INSERT IGNORE INTO landing_content (section_name, content_json) VALUES ('$s', '{}')");
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_section'])) {
    $section = $_POST['save_section'];

    $data = match($section) {
        'hero'       => ['heading' => $_POST['hero_heading'], 'subheading' => $_POST['hero_subheading'], 'description' => $_POST['hero_desc'], 'button_text' => $_POST['hero_btn_text'], 'image_url' => $_POST['hero_image_url']],
        'styles'     => ['color_primary' => $_POST['color_primary'], 'color_secondary' => $_POST['color_secondary'], 'color_accent' => $_POST['color_accent'], 'color_domain_bar' => $_POST['color_domain_bar'], 'font_family' => $_POST['font_family']],
        'navbar'     => ['site_name' => $_POST['site_name'], 'tagline' => $_POST['nav_tagline'], 'logo_url' => $_POST['logo_url'], 'nav_links' => json_decode($_POST['nav_links_json'], true) ?? []],
        'domain_bar' => ['title' => $_POST['db_title'], 'tlds' => json_decode($_POST['tlds_json'], true) ?? []],
        'trust'      => ['title' => $_POST['trust_title'], 'logos' => json_decode($_POST['logos_json'], true) ?? []],
        'features'   => json_decode($_POST['features_json'], true) ?? [],
        'footer'     => ['company_name' => $_POST['footer_company'], 'description' => $_POST['footer_desc'], 'email' => $_POST['footer_email'], 'phone' => $_POST['footer_phone']],
        default      => []
    };

    $j = mysqli_real_escape_string($conn, json_encode($data));
    mysqli_query($conn, "UPDATE landing_content SET content_json = '$j' WHERE section_name = '$section'");
    header("Location: landing_settings?res=ok&section=$section");
    exit();
}

// Load all data
$db_data = [];
$q = mysqli_query($conn, "SELECT * FROM landing_content");
while ($r = mysqli_fetch_assoc($q)) {
    $db_data[$r['section_name']] = json_decode($r['content_json'], true);
}

$hero       = $db_data['hero'] ?? [];
$navbar     = $db_data['navbar'] ?? [];
$domain_bar = $db_data['domain_bar'] ?? [];
$trust      = $db_data['trust'] ?? [];
$features   = $db_data['features'] ?? [];
$styles     = $db_data['styles'] ?? [];
$footer_d   = $db_data['footer'] ?? [];

$page_title = "Landing Editor";
include __DIR__ . '/library/header.php';
?>

<!-- Icon Libraries -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.3.0/dist/tabler-icons.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
<script src="https://unpkg.com/feather-icons/dist/feather.min.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<style>
    .editor-tabs { display: flex; gap: 4px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 0; }
    .etab { 
        padding: 10px 18px; font-size: 12.5px; font-weight: 600; border-radius: 8px 8px 0 0; 
        cursor: pointer; color: var(--sub); border: 1px solid transparent; border-bottom: none;
        transition: all .2s; display: flex; align-items: center; gap: 7px; margin-bottom: -1px;
    }
    .etab:hover { color: var(--text); background: var(--hover); }
    .etab.active { background: var(--card); color: var(--accent); border-color: var(--border); border-bottom-color: var(--card); }
    .etab i { font-size: 16px; }
    
    .section-panel { display: none; }
    .section-panel.active { display: block; }
    
    .field-group { margin-bottom: 18px; }
    .field-label { display: block; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: var(--sub); margin-bottom: 6px; }
    .field-hint { font-size: 11px; color: var(--mut); margin-top: 5px; }
    
    .field-input, .field-textarea, .field-select {
        width: 100%; background: var(--hover); border: 1px solid var(--border); color: var(--text);
        border-radius: var(--rs); padding: 9px 13px; font-size: 13px; font-family: inherit;
        outline: none; transition: all .2s;
    }
    .field-input:focus, .field-textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--ag); }
    .field-textarea { resize: vertical; min-height: 90px; }

    .color-row { display: flex; align-items: center; gap: 10px; }
    .color-swatch { 
        width: 42px; height: 42px; border-radius: 10px; border: 2px solid var(--border); 
        cursor: pointer; flex-shrink: 0; transition: border-color .2s; overflow:hidden;
    }
    .color-swatch input[type=color] { width: 100%; height: 100%; border: none; padding: 0; cursor: pointer; opacity:0; position:absolute; }
    .color-swatch-wrap { position: relative; width: 42px; height: 42px; border-radius: 10px; border: 2px solid var(--border); overflow: hidden; cursor: pointer; flex-shrink: 0; }
    .color-swatch-wrap .preview-color { width: 100%; height: 100%; display: block; }
    .color-hex { font-family: 'JetBrains Mono', monospace; font-size: 12px; background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 5px 10px; color: var(--text); flex: 1; }
    
    /* Image Input with Preview */
    .img-field-wrap { position: relative; }
    .img-preview-box { 
        background: var(--surface); border: 1px dashed var(--border); border-radius: 10px; 
        padding: 12px; display: flex; align-items: center; gap: 14px; margin-bottom: 8px;
        transition: border-color .2s;
    }
    .img-preview-box:hover { border-color: var(--accent); }
    .img-thumb { width: 72px; height: 52px; object-fit: cover; border-radius: 6px; background: var(--hover); flex-shrink: 0; }
    .img-thumb-placeholder { width: 72px; height: 52px; background: var(--hover); border-radius: 6px; display: flex; align-items:center; justify-content: center; color: var(--mut); font-size: 22px; flex-shrink: 0; }
    
    /* Feature / Logo Cards */
    .item-card { 
        background: var(--surface); border: 1px solid var(--border); border-radius: 10px; 
        padding: 14px 16px; margin-bottom: 10px; display: flex; gap: 12px; align-items: flex-start;
    }
    .item-card-icon { 
        width: 40px; height: 40px; background: var(--as); border-radius: 9px; 
        display: flex; align-items: center; justify-content: center; color: var(--accent); 
        font-size: 18px; flex-shrink: 0;
    }
    .item-card-body { flex: 1; display: flex; flex-direction: column; gap: 6px; }
    .item-card-row { display: flex; gap: 8px; }
    .item-card-row .field-input { margin: 0; }
    .item-card-del { width: 30px; height: 30px; background: none; border: 1px solid var(--border); border-radius: 7px; color: var(--sub); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .2s; flex-shrink: 0; font-size: 14px; }
    .item-card-del:hover { background: var(--es); border-color: var(--err); color: var(--err); }
    .item-card-drag { cursor: grab; color: var(--mut); font-size: 16px; padding-top: 3px; }
    .item-card-drag:hover { color: var(--sub); }

    .add-item-btn { 
        width: 100%; padding: 10px; border: 1px dashed var(--border); border-radius: 10px; 
        background: none; color: var(--mut); font-size: 13px; font-weight: 600; cursor: pointer; 
        transition: all .2s; display: flex; align-items: center; justify-content: center; gap: 7px; margin-top: 6px;
    }
    .add-item-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--as); }

    /* Font Preview */
    .font-preview { margin-top: 10px; padding: 12px 16px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; }
    .font-preview-text { font-size: 14px; color: var(--text); }

    /* Icon Picker Popup */
    .icon-picker-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    .icon-picker-box { background: var(--card); border: 1px solid var(--border); border-radius: 16px; width: 640px; max-width: 95vw; max-height: 85vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 24px 60px rgba(0,0,0,0.5); }
    .icon-picker-head { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px; }
    .icon-picker-head h6 { margin: 0; font-size: 14px; font-weight: 700; color: var(--text); }
    .icon-picker-search { flex: 1; background: var(--hover); border: 1px solid var(--border); color: var(--text); border-radius: 8px; padding: 7px 14px; font-size: 13px; outline: none; font-family: inherit; }
    .icon-picker-search:focus { border-color: var(--accent); }
    .icon-lib-tabs { display: flex; gap: 4px; padding: 12px 20px; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
    .icon-lib-tab { padding: 5px 14px; background: var(--hover); border: 1px solid var(--border); color: var(--sub); border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all .2s; }
    .icon-lib-tab:hover { color: var(--text); }
    .icon-lib-tab.active { background: var(--as); color: var(--accent); border-color: var(--ba); }
    .icon-picker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(70px,1fr)); gap: 8px; padding: 16px 20px; overflow-y: auto; flex: 1; }
    .icon-picker-item { display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 10px 6px; border-radius: 10px; border: 1px solid transparent; cursor: pointer; transition: all .15s; text-align: center; }
    .icon-picker-item:hover { background: var(--hover); border-color: var(--border); }
    .icon-picker-item.selected { background: var(--as); border-color: var(--ba); }
    .icon-picker-item .ip-icon { font-size: 22px; color: var(--text); }
    .icon-picker-item .ip-label { font-size: 9px; color: var(--mut); line-height: 1.2; word-break: break-all; }
    .icon-picker-foot { padding: 14px 20px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 10px; justify-content: space-between; }
    .ip-current { display: flex; align-items: center; gap: 10px; }
    .ip-current-icon { width: 36px; height: 36px; background: var(--as); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px; color: var(--accent); }
    .ip-current-text { font-size: 12px; color: var(--sub); font-family: 'JetBrains Mono', monospace; }
    
    /* Save section bar */
    .section-save-bar { display: flex; align-items: center; justify-content: space-between; padding-top: 16px; border-top: 1px solid var(--border); margin-top: 6px; }
    
    /* Live Preview */
    .preview-badge { 
        display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; 
        padding: 4px 12px; border-radius: 99px; background: var(--oks); color: var(--ok); 
    }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="ph-fill ph-magic-wand me-2" style="color: var(--accent);"></i> Landing Editor</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb bc">
            <li class="breadcrumb-item"><a href="<?= base_url('admin/index') ?>">Admin</a></li>
            <li class="breadcrumb-item active">Landing Editor</li>
        </ol></nav>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="preview-badge"><i class="ph-fill ph-circle" style="font-size:8px;"></i> CMS Mode</span>
        <a href="/" target="_blank" class="btn btn-outline-light btn-sm fw-bold"><i class="ph ph-eye me-1"></i> Lihat Website</a>
    </div>
</div>

<?php if(isset($_GET['res']) && $_GET['res'] == 'ok'): ?>
<div class="alert border-0 mb-4 d-flex align-items-center gap-2" style="background: var(--oks); color: var(--ok); border-radius: 10px; font-size: 13px;">
    <i class="ph-fill ph-check-circle fs-5"></i>
    <strong>Section "<?= htmlspecialchars($_GET['section'] ?? '') ?>" berhasil disimpan!</strong>
    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="alert" style="filter: none; background: none; color: var(--ok); font-size: 14px;" onclick="this.parentElement.remove()">✕</button>
</div>
<?php endif; ?>

<!-- Editor Tabs -->
<div class="editor-tabs">
    <div class="etab active" onclick="switchTab('hero')"><i class="ph-fill ph-monitor-play"></i> Hero</div>
    <div class="etab" onclick="switchTab('navbar')"><i class="ph-fill ph-navigation-arrow"></i> Navbar</div>
    <div class="etab" onclick="switchTab('features')"><i class="ph-fill ph-squares-four"></i> Features</div>
    <div class="etab" onclick="switchTab('trust')"><i class="ph-fill ph-shield-check"></i> Trust Logos</div>
    <div class="etab" onclick="switchTab('footer_s')"><i class="ph-fill ph-layout"></i> Footer</div>
    <div class="etab" onclick="switchTab('styles')"><i class="ph-fill ph-palette"></i> Theme &amp; Colors</div>
</div>

<div class="row g-4">
    <!-- Main Editor Column -->
    <div class="col-lg-8">

        <!-- HERO SECTION -->
        <div class="section-panel active card-c" id="panel-hero">
            <div class="ch"><h3 class="ct"><i class="ph-fill ph-monitor-play text-primary me-2"></i> Hero Section</h3></div>
            <div class="cb">
                <form method="POST">
                    <input type="hidden" name="save_section" value="hero">
                    <div class="field-group">
                        <label class="field-label">Main Tagline / Heading</label>
                        <input type="text" name="hero_heading" class="field-input" value="<?= htmlspecialchars($hero['heading'] ?? '') ?>" placeholder="#ThinkBig #GrowBigger">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Subheading</label>
                        <input type="text" name="hero_subheading" class="field-input" value="<?= htmlspecialchars($hero['subheading'] ?? '') ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Description</label>
                        <textarea name="hero_desc" class="field-textarea"><?= htmlspecialchars($hero['description'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="field-group mb-0">
                                <label class="field-label">Button Label</label>
                                <input type="text" name="hero_btn_text" class="field-input" value="<?= htmlspecialchars($hero['button_text'] ?? 'MULAI') ?>">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="field-group mb-0">
                                <label class="field-label">Hero Illustration Image URL</label>
                                <div class="img-preview-box">
                                    <?php $imgurl = $hero['image_url'] ?? ''; ?>
                                    <?php if($imgurl): ?>
                                        <img src="<?= htmlspecialchars($imgurl) ?>" class="img-thumb" id="hero_img_thumb" onerror="this.outerHTML='<div class=img-thumb-placeholder><i class=ph-fill ph-image-broken></i></div>'">
                                    <?php else: ?>
                                        <div class="img-thumb-placeholder"><i class="ph ph-image"></i></div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-size: 12px; color: var(--sub); margin-bottom: 4px;">URL Gambar Ilustrasi</div>
                                        <input type="text" name="hero_image_url" class="field-input" id="hero_img_input" value="<?= htmlspecialchars($imgurl) ?>" placeholder="https://..." onchange="updateImgPreview('hero_img_input','hero_img_thumb')" style="font-size: 12px; padding: 6px 10px;">
                                    </div>
                                </div>
                                <div class="field-hint">Masukkan link URL gambar. Rekomendasi: PNG transparan, min 600px lebar.</div>
                            </div>
                        </div>
                    </div>
                    <div class="section-save-bar">
                        <span style="font-size: 12px; color: var(--mut);">Mengubah teks dan gambar Halaman Utama (Hero)</span>
                        <button type="submit" class="btn btn-primary fw-bold px-4"><i class="ph-fill ph-floppy-disk me-2"></i>Simpan Hero</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- NAVBAR SECTION -->
        <div class="section-panel card-c" id="panel-navbar">
            <div class="ch"><h3 class="ct"><i class="ph-fill ph-navigation-arrow text-primary me-2"></i> Navbar / Header</h3></div>
            <div class="cb">
                <form method="POST">
                    <input type="hidden" name="save_section" value="navbar">
                    <input type="hidden" name="nav_links_json" id="nav_links_json" value='<?= htmlspecialchars(json_encode($navbar['nav_links'] ?? [])) ?>'>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="field-label">Nama Situs</label>
                            <input type="text" name="site_name" class="field-input" value="<?= htmlspecialchars($navbar['site_name'] ?? 'sobathosting') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label">Tagline (bawah nama)</label>
                            <input type="text" name="nav_tagline" class="field-input" value="<?= htmlspecialchars($navbar['tagline'] ?? 'Painless hosting solution') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label">Logo URL (opsional)</label>
                            <input type="text" name="logo_url" class="field-input" value="<?= htmlspecialchars($navbar['logo_url'] ?? '') ?>" placeholder="Kosongkan utk pakai ikon">
                        </div>
                    </div>
                    <label class="field-label mb-3">Menu Navigasi</label>
                    <div id="nav_links_list"></div>
                    <button type="button" class="add-item-btn" onclick="addNavLink()"><i class="ph ph-plus"></i> Tambah Menu</button>
                    <div class="section-save-bar mt-4">
                        <span style="font-size: 12px; color: var(--mut);">Atur link menu dan nama situs di navbar</span>
                        <button type="submit" class="btn btn-primary fw-bold px-4"><i class="ph-fill ph-floppy-disk me-2"></i>Simpan Navbar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- DOMAIN BAR -->
        <div class="section-panel card-c" id="panel-domain_bar">
            <div class="ch"><h3 class="ct"><i class="ph-fill ph-globe text-primary me-2"></i> Domain Search Bar</h3></div>
            <div class="cb">
                <form method="POST">
                    <input type="hidden" name="save_section" value="domain_bar">
                    <input type="hidden" name="tlds_json" id="tlds_json" value='<?= htmlspecialchars(json_encode($domain_bar['tlds'] ?? [])) ?>'>
                    <div class="field-group">
                        <label class="field-label">Judul Banner Domain</label>
                        <input type="text" name="db_title" class="field-input" value="<?= htmlspecialchars($domain_bar['title'] ?? '') ?>">
                    </div>
                    <label class="field-label mb-3">Ekstensi TLD yang ditampilkan</label>
                    <div id="tlds_list"></div>
                    <button type="button" class="add-item-btn" onclick="addTld()"><i class="ph ph-plus"></i> Tambah TLD</button>
                    <div class="section-save-bar mt-4">
                        <span style="font-size: 12px; color: var(--mut);">Atur display harga domain di bar hijau</span>
                        <button type="submit" class="btn btn-primary fw-bold px-4"><i class="ph-fill ph-floppy-disk me-2"></i>Simpan Domain Bar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- FEATURES -->
        <div class="section-panel card-c" id="panel-features">
            <div class="ch"><h3 class="ct"><i class="ph-fill ph-squares-four text-primary me-2"></i> Features Grid</h3></div>
            <div class="cb">
                <form method="POST">
                    <input type="hidden" name="save_section" value="features">
                    <input type="hidden" name="features_json" id="features_json" value='<?= htmlspecialchars(json_encode($features)) ?>'>
                    <div class="field-hint mb-3"><i class="ph ph-info me-1"></i> Icon: gunakan class FontAwesome 6 (misal: <code style="background:var(--surface); padding: 2px 6px; border-radius: 4px;">fa-server</code>, <code style="background:var(--surface); padding: 2px 6px; border-radius: 4px;">fa-globe</code>). Lihat di <a href="https://fontawesome.com/icons" target="_blank" style="color: var(--accent);">fontawesome.com/icons</a></div>
                    <div id="features_list"></div>
                    <button type="button" class="add-item-btn" onclick="addFeature()"><i class="ph ph-plus"></i> Tambah Feature Box</button>
                    <div class="section-save-bar mt-4">
                        <span style="font-size: 12px; color: var(--mut);">Kotak-kotak layanan di bawah domain bar</span>
                        <button type="submit" class="btn btn-primary fw-bold px-4" onclick="serializeFeatures()"><i class="ph-fill ph-floppy-disk me-2"></i>Simpan Features</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- TRUST LOGOS -->
        <div class="section-panel card-c" id="panel-trust">
            <div class="ch"><h3 class="ct"><i class="ph-fill ph-shield-check text-primary me-2"></i> Trust / Partner Logos</h3></div>
            <div class="cb">
                <form method="POST">
                    <input type="hidden" name="save_section" value="trust">
                    <input type="hidden" name="logos_json" id="logos_json" value='<?= htmlspecialchars(json_encode($trust['logos'] ?? [])) ?>'>
                    <div class="field-group">
                        <label class="field-label">Judul Trust Section</label>
                        <input type="text" name="trust_title" class="field-input" value="<?= htmlspecialchars($trust['title'] ?? '') ?>">
                    </div>
                    <div class="field-hint mb-3"><i class="ph ph-info me-1"></i> Setiap logo menggunakan ikon FontAwesome. Nama bisa multi-baris (tekan Enter).</div>
                    <div id="logos_list"></div>
                    <button type="button" class="add-item-btn" onclick="addLogo()"><i class="ph ph-plus"></i> Tambah Logo Partner</button>
                    <div class="section-save-bar mt-4">
                        <span style="font-size: 12px; color: var(--mut);">Section "partner kami" dengan background biru</span>
                        <button type="submit" class="btn btn-primary fw-bold px-4" onclick="serializeLogos()"><i class="ph-fill ph-floppy-disk me-2"></i>Simpan Trust</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="section-panel card-c" id="panel-footer_s">
            <div class="ch"><h3 class="ct"><i class="ph-fill ph-layout text-primary me-2"></i> Footer Info</h3></div>
            <div class="cb">
                <form method="POST">
                    <input type="hidden" name="save_section" value="footer">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="field-label">Nama Perusahaan</label>
                            <input type="text" name="footer_company" class="field-input" value="<?= htmlspecialchars($footer_d['company_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="field-label">Email Kontak</label>
                            <input type="text" name="footer_email" class="field-input" value="<?= htmlspecialchars($footer_d['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="field-label">Nomor CS / WhatsApp</label>
                            <input type="text" name="footer_phone" class="field-input" value="<?= htmlspecialchars($footer_d['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="field-label">Deskripsi Singkat</label>
                            <textarea name="footer_desc" class="field-textarea" style="min-height: 70px;"><?= htmlspecialchars($footer_d['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="section-save-bar mt-4">
                        <span style="font-size: 12px; color: var(--mut);">Informasi kontak dan deskripsi di Footer</span>
                        <button type="submit" class="btn btn-primary fw-bold px-4"><i class="ph-fill ph-floppy-disk me-2"></i>Simpan Footer</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- STYLES -->
        <div class="section-panel card-c" id="panel-styles">
            <div class="ch"><h3 class="ct"><i class="ph-fill ph-palette text-primary me-2"></i> Theme & Colors</h3></div>
            <div class="cb">
                <form method="POST">
                    <input type="hidden" name="save_section" value="styles">
                    <div class="row g-4">
                        <?php
                        $color_fields = [
                            'color_primary' => ['Gradient Primary (Kiri)', $styles['color_primary'] ?? '#0d6efd', 'Warna utama hero, button, dan lingkaran mitra'],
                            'color_secondary' => ['Gradient Secondary (Kanan)', $styles['color_secondary'] ?? '#0dcaf0', 'Warna ujung kanan gradient hero & trust section'],
                            'color_accent' => ['Button Accent / CTA', $styles['color_accent'] ?? '#f39c12', 'Warna tombol "MULAI" dan tombol pencarian domain'],
                            'color_domain_bar' => ['Domain Bar Background', $styles['color_domain_bar'] ?? '#8cc63f', 'Warna background bagian pencarian domain'],
                        ];
                        foreach ($color_fields as $key => [$label, $val, $hint]):
                        ?>
                        <div class="col-md-6">
                            <div class="field-group">
                                <label class="field-label"><?= $label ?></label>
                                <div class="color-row">
                                    <div class="color-swatch-wrap" id="swatch_<?= $key ?>" style="background: <?= htmlspecialchars($val) ?>;"
                                         onclick="document.getElementById('color_picker_<?= $key ?>').click()">
                                        <input type="color" id="color_picker_<?= $key ?>" name="<?= $key ?>" value="<?= htmlspecialchars($val) ?>" 
                                               oninput="updateColor('<?= $key ?>', this.value)">
                                    </div>
                                    <input type="text" class="color-hex" id="hex_<?= $key ?>" value="<?= htmlspecialchars($val) ?>" 
                                           onchange="syncColor('<?= $key ?>', this.value)" maxlength="7">
                                    <div style="width: 50px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, <?= htmlspecialchars($styles['color_primary'] ?? '#0d6efd') ?>, <?= htmlspecialchars($styles['color_secondary'] ?? '#0dcaf0') ?>);" id="grad_preview_<?= $key ?>" title="Preview gradient"></div>
                                </div>
                                <div class="field-hint"><?= $hint ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="col-12">
                            <div class="field-group mb-0">
                                <label class="field-label">Font Family</label>
                                <input type="text" name="font_family" class="field-input" id="font_family_input" value="<?= htmlspecialchars($styles['font_family'] ?? "'Montserrat', sans-serif") ?>" oninput="updateFontPreview(this.value)">
                                <div class="field-hint">Masukkan nama font dari Google Fonts (misal: <code style="background:var(--surface); padding: 2px 6px; border-radius: 4px;">'Montserrat', sans-serif</code>)</div>
                                <div class="font-preview mt-2">
                                    <div class="field-label mb-1">Preview Font:</div>
                                    <div class="font-preview-text" id="font_preview_el" style="font-family: <?= htmlspecialchars($styles['font_family'] ?? "'Montserrat', sans-serif") ?>">
                                        The quick brown fox jumps — Hosting Terbaik Indonesia 2026
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="section-save-bar mt-4">
                        <span style="font-size: 12px; color: var(--mut);">Semua warna & font bersifat global terhadap seluruh landing page</span>
                        <button type="submit" class="btn btn-primary fw-bold px-4"><i class="ph-fill ph-floppy-disk me-2"></i>Simpan Theme</button>
                    </div>
                </form>
            </div>
        </div>

    </div><!-- /col-md-8 -->

    <!-- Right Sidebar: Info & Quick Actions -->
    <div class="col-lg-4">
        <!-- Current Theme Preview -->
        <div class="card-c mb-4">
            <div class="ch"><h3 class="ct"><i class="ph-fill ph-eyedropper text-primary me-2"></i> Current Theme Preview</h3></div>
            <div class="cb">
                <div style="border-radius: 10px; overflow: hidden; border: 1px solid var(--border);">
                    <div style="background: linear-gradient(135deg, <?= $styles['color_primary'] ?? '#0d6efd' ?>, <?= $styles['color_secondary'] ?? '#0dcaf0' ?>); padding: 20px 16px; text-align: center;">
                        <div style="font-size: 11px; font-weight: 800; color: white; letter-spacing: 1px; opacity: .8;">HERO GRADIENT</div>
                        <div style="font-size: 18px; font-weight: 900; color: white; margin: 4px 0;">Sobathosting</div>
                        <div style="background: <?= $styles['color_accent'] ?? '#f39c12' ?>; display: inline-block; padding: 4px 16px; border-radius: 6px; font-size: 11px; font-weight: 700; color: white; margin-top: 6px;"><?= htmlspecialchars($hero['button_text'] ?? 'MULAI') ?></div>
                    </div>
                    <div style="background: <?= $styles['color_domain_bar'] ?? '#8cc63f' ?>; padding: 12px 16px;">
                        <div style="font-size: 11px; font-weight: 700; color: white;">Domain Search Bar</div>
                    </div>
                </div>
                <div class="mt-3 d-grid">
                    <a href="/" target="_blank" class="btn btn-outline-light btn-sm fw-bold"><i class="ph ph-arrow-square-out me-1"></i> Buka Landing Page</a>
                </div>
            </div>
        </div>

        <!-- Section Map -->
        <div class="card-c mb-4">
            <div class="ch"><h3 class="ct"><i class="ph-fill ph-map-trifold text-primary me-2"></i> Section Map</h3></div>
            <div class="cb p-0">
                <div style="padding: 8px 0;">
                    <?php 
                    $nav_items = [
                        ['hero', 'ph-monitor-play', 'Hero', 'Tagline, deskripsi, gambar'],
                        ['navbar', 'ph-navigation-arrow', 'Navbar', 'Menu & nama situs'],
                        ['domain_bar', 'ph-globe', 'Domain Bar', 'Pencarian & harga TLD'],
                        ['features', 'ph-squares-four', 'Features', 'Grid kotak layanan'],
                        ['trust', 'ph-shield-check', 'Trust Logos', 'Logo partner & mitra'],
                        ['footer_s', 'ph-layout', 'Footer', 'Kontak & deskripsi'],
                        ['styles', 'ph-palette', 'Theme & Colors', 'Warna & font global'],
                    ];
                    foreach($nav_items as [$tab, $icon, $label, $desc]):
                    ?>
                    <div onclick="switchTab('<?= $tab ?>')" style="padding: 10px 20px; display: flex; align-items: center; gap: 12px; cursor: pointer; border-bottom: 1px solid var(--border); transition: background .15s;" onmouseover="this.style.background='var(--hover)'" onmouseout="this.style.background=''">
                        <i class="ph-fill <?= $icon ?>" style="font-size: 18px; color: var(--accent); flex-shrink: 0;"></i>
                        <div>
                            <div style="font-size: 13px; font-weight: 600; color: var(--text);"><?= $label ?></div>
                            <div style="font-size: 11px; color: var(--mut);"><?= $desc ?></div>
                        </div>
                        <i class="ph ph-caret-right ms-auto" style="color: var(--mut);"></i>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tab switching
function switchTab(tab) {
    document.querySelectorAll('.section-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.etab').forEach(t => t.classList.remove('active'));
    const panel = document.getElementById('panel-' + tab);
    if(panel) panel.classList.add('active');
    document.querySelectorAll('.etab').forEach((t, i) => {
        if(t.getAttribute('onclick') && t.getAttribute('onclick').includes("'" + tab + "'")) t.classList.add('active');
    });
}

// Image preview
function updateImgPreview(inputId, imgId) {
    const url = document.getElementById(inputId).value;
    let el = document.getElementById(imgId);
    if(el) el.src = url;
}

// Color pickers
function updateColor(key, val) {
    document.getElementById('hex_' + key).value = val;
    document.getElementById('swatch_' + key).style.background = val;
}
function syncColor(key, val) {
    document.getElementById('color_picker_' + key).value = val;
    document.getElementById('swatch_' + key).style.background = val;
}

// Font preview
function updateFontPreview(val) {
    document.getElementById('font_preview_el').style.fontFamily = val;
}

// ──────────────────────── NAV LINKS ────────────────────────
let arrNavLinks = <?= json_encode($navbar['nav_links'] ?? []) ?>;
function renderNavLinks() {
    let html = '';
    arrNavLinks.forEach((l, i) => {
        html += `<div class="item-card">
            <div class="item-card-icon"><i class="ph-fill ph-link"></i></div>
            <div class="item-card-body">
                <div class="item-card-row">
                    <input type="text" class="field-input nl-label" placeholder="Label (e.g DOMAIN)" value="${esc(l.label)}" style="flex:1">
                    <input type="text" class="field-input nl-url" placeholder="URL (e.g /hosting)" value="${esc(l.url)}" style="flex:2">
                </div>
            </div>
            <button type="button" class="item-card-del" onclick="arrNavLinks.splice(${i},1); renderNavLinks();"><i class="ph ph-trash"></i></button>
        </div>`;
    });
    document.getElementById('nav_links_list').innerHTML = html;
}
function addNavLink() { arrNavLinks.push({label:'MENU', url:'#'}); renderNavLinks(); }
document.getElementById('nav_links_list')?.closest('form')?.addEventListener('submit', () => {
    arrNavLinks = [];
    document.querySelectorAll('#nav_links_list .item-card').forEach(c => {
        arrNavLinks.push({label: c.querySelector('.nl-label').value, url: c.querySelector('.nl-url').value});
    });
    document.getElementById('nav_links_json').value = JSON.stringify(arrNavLinks);
});

// ──────────────────────── TLD LIST ────────────────────────
let arrTlds = <?= json_encode($domain_bar['tlds'] ?? []) ?>;
function renderTlds() {
    let html = '';
    arrTlds.forEach((t, i) => {
        html += `<div class="item-card">
            <div class="item-card-icon"><i class="ph-fill ph-globe"></i></div>
            <div class="item-card-body">
                <div class="item-card-row">
                    <input type="text" class="field-input tld-ext" placeholder=".com" value="${esc(t.ext)}" style="flex:1">
                    <input type="text" class="field-input tld-price" placeholder="Rp119.900" value="${esc(t.price)}" style="flex:2">
                </div>
            </div>
            <button type="button" class="item-card-del" onclick="arrTlds.splice(${i},1); renderTlds();"><i class="ph ph-trash"></i></button>
        </div>`;
    });
    document.getElementById('tlds_list').innerHTML = html;
}
function addTld() { arrTlds.push({ext:'.xyz', price:'Rp50.000'}); renderTlds(); }
document.getElementById('tlds_list')?.closest('form')?.addEventListener('submit', () => {
    arrTlds = [];
    document.querySelectorAll('#tlds_list .item-card').forEach(c => {
        arrTlds.push({ext: c.querySelector('.tld-ext').value, price: c.querySelector('.tld-price').value});
    });
    document.getElementById('tlds_json').value = JSON.stringify(arrTlds);
});

// ──────────────────────── FEATURES ────────────────────────
let arrFeatures = <?= json_encode($features) ?>;
function renderFeatures() {
    let html = '';
    arrFeatures.forEach((f, i) => {
        html += `<div class="item-card">
            <div class="item-card-icon"><i class="fa-solid ${esc(f.icon)} f-live-icon-${i}"></i></div>
            <div class="item-card-body">
                <div class="item-card-row">
                    <input type="text" class="field-input f-icon" placeholder="fa-server" value="${esc(f.icon)}" style="flex:1.2" oninput="updateFeatureIcon(${i}, this.value)">
                    <input type="text" class="field-input f-title" placeholder="Judul" value="${esc(f.title)}" style="flex:2">
                </div>
                <input type="text" class="field-input f-desc" placeholder="Deskripsi singkat" value="${esc(f.description)}">
            </div>
            <button type="button" class="item-card-del" onclick="arrFeatures.splice(${i},1); renderFeatures();"><i class="ph ph-trash"></i></button>
        </div>`;
    });
    document.getElementById('features_list').innerHTML = html;
}
function addFeature() { arrFeatures.push({title:'New Feature', description:'Deskripsi layanan', icon:'fa-star'}); renderFeatures(); }
function updateFeatureIcon(i, val) {
    const el = document.querySelector(`.f-live-icon-${i}`);
    if(el) { el.className = `fa-solid ${val} f-live-icon-${i}`; }
}
function serializeFeatures() {
    arrFeatures = [];
    document.querySelectorAll('#features_list .item-card').forEach(c => {
        arrFeatures.push({icon: c.querySelector('.f-icon').value, title: c.querySelector('.f-title').value, description: c.querySelector('.f-desc').value});
    });
    document.getElementById('features_json').value = JSON.stringify(arrFeatures);
}

// ──────────────────────── LOGOS ────────────────────────
let arrLogos = <?= json_encode($trust['logos'] ?? []) ?>;
function renderLogos() {
    let html = '';
    arrLogos.forEach((l, i) => {
        html += `<div class="item-card">
            <div class="item-card-icon"><i class="fa-solid ${esc(l.icon)} lg-live-icon-${i}"></i></div>
            <div class="item-card-body">
                <div class="item-card-row">
                    <input type="text" class="field-input lg-icon" placeholder="fa-certificate" value="${esc(l.icon)}" style="flex:1.2" oninput="updateLogoIcon(${i}, this.value)">
                    <input type="text" class="field-input lg-name" placeholder="Nama Partner" value="${esc(l.name)}" style="flex:2">
                </div>
            </div>
            <button type="button" class="item-card-del" onclick="arrLogos.splice(${i},1); renderLogos();"><i class="ph ph-trash"></i></button>
        </div>`;
    });
    document.getElementById('logos_list').innerHTML = html;
}
function addLogo() { arrLogos.push({name:'New Partner', icon:'fa-check'}); renderLogos(); }
function updateLogoIcon(i, val) {
    const el = document.querySelector(`.lg-live-icon-${i}`);
    if(el) el.className = `fa-solid ${val} lg-live-icon-${i}`;
}
function serializeLogos() {
    arrLogos = [];
    document.querySelectorAll('#logos_list .item-card').forEach(c => {
        arrLogos.push({icon: c.querySelector('.lg-icon').value, name: c.querySelector('.lg-name').value});
    });
    document.getElementById('logos_json').value = JSON.stringify(arrLogos);
}

// Nav links form submission
document.getElementById('nav_links_json')?.closest('form')?.addEventListener('submit', () => {
    arrNavLinks = [];
    document.querySelectorAll('#nav_links_list .item-card').forEach(c => {
        arrNavLinks.push({label: c.querySelector('.nl-label').value, url: c.querySelector('.nl-url').value});
    });
    document.getElementById('nav_links_json').value = JSON.stringify(arrNavLinks);
});

// ──────────────────── SMART ICON RENDER ────────────────────
// Supports: fa-* (FontAwesome), ti-* (Tabler), material:* (Material Symbols), lucide:* (Lucide)
function renderIcon(iconStr, extraClass = '') {
    if (!iconStr) return '<i class="ph ph-square ' + extraClass + '"></i>';
    if (iconStr.startsWith('lucide:')) {
        const name = iconStr.replace('lucide:', '');
        return `<i data-lucide="${name}" class="${extraClass}" style="width:1em;height:1em;"></i>`;
    } else if (iconStr.startsWith('material:')) {
        const name = iconStr.replace('material:', '');
        return `<span class="material-symbols-outlined ${extraClass}" style="font-size:inherit;">${name}</span>`;
    } else if (iconStr.startsWith('ti-') || iconStr.startsWith('ti ')) {
        return `<i class="ti ${iconStr.replace(/^ti-/, 'ti-')} ${extraClass}"></i>`;
    } else {
        // FontAwesome default (fa-server, fa-check, etc.)
        const prefix = iconStr.startsWith('fa-brands') ? 'fa-brands' : 'fa-solid';
        const cls = iconStr.replace(/^(fa-solid|fa-brands|fa-regular)\s+/, '');
        return `<i class="${prefix} ${cls} ${extraClass}"></i>`;
    }
}

// Re-render feature icons using smart function
function updateFeatureIcon(i, val) {
    const wrap = document.querySelector(`.f-icon-wrap-${i}`);
    if(wrap) wrap.innerHTML = renderIcon(val);
}
function updateLogoIcon(i, val) {
    const wrap = document.querySelector(`.lg-icon-wrap-${i}`);
    if(wrap) wrap.innerHTML = renderIcon(val);
}

// Updated render functions using renderIcon
function renderFeatures() {
    let html = '';
    arrFeatures.forEach((f, i) => {
        html += `<div class="item-card">
            <div class="item-card-icon f-icon-wrap-${i}">${renderIcon(f.icon)}</div>
            <div class="item-card-body">
                <div class="item-card-row">
                    <div style="flex:1.2; display:flex; gap:4px;">
                        <input type="text" class="field-input f-icon" placeholder="fa-server" value="${esc(f.icon)}" style="flex:1; min-width:0;" oninput="updateFeatureIcon(${i}, this.value)">
                        <button type="button" class="item-card-del" title="Pilih Ikon" onclick="openIconPicker(this, '.f-icon')" style="width:34px; flex-shrink:0;"><i class="ph-fill ph-grid-four"></i></button>
                    </div>
                    <input type="text" class="field-input f-title" placeholder="Judul" value="${esc(f.title)}" style="flex:2">
                </div>
                <input type="text" class="field-input f-desc" placeholder="Deskripsi singkat" value="${esc(f.description)}">
            </div>
            <button type="button" class="item-card-del" onclick="arrFeatures.splice(${i},1); renderFeatures();"><i class="ph ph-trash"></i></button>
        </div>`;
    });
    document.getElementById('features_list').innerHTML = html;
    if(typeof lucide !== 'undefined') lucide.createIcons();
}

function renderLogos() {
    let html = '';
    arrLogos.forEach((l, i) => {
        html += `<div class="item-card">
            <div class="item-card-icon lg-icon-wrap-${i}">${renderIcon(l.icon)}</div>
            <div class="item-card-body">
                <div class="item-card-row">
                    <div style="flex:1.2; display:flex; gap:4px;">
                        <input type="text" class="field-input lg-icon" placeholder="fa-certificate" value="${esc(l.icon)}" style="flex:1; min-width:0;" oninput="updateLogoIcon(${i}, this.value)">
                        <button type="button" class="item-card-del" title="Pilih Ikon" onclick="openIconPicker(this, '.lg-icon')" style="width:34px; flex-shrink:0;"><i class="ph-fill ph-grid-four"></i></button>
                    </div>
                    <input type="text" class="field-input lg-name" placeholder="Nama Partner" value="${esc(l.name)}" style="flex:2">
                </div>
            </div>
            <button type="button" class="item-card-del" onclick="arrLogos.splice(${i},1); renderLogos();"><i class="ph ph-trash"></i></button>
        </div>`;
    });
    document.getElementById('logos_list').innerHTML = html;
    if(typeof lucide !== 'undefined') lucide.createIcons();
}

// ──────────────────── ICON PICKER MODAL ────────────────────
const ICON_DB = {
    'FontAwesome': [
        ['fa-server','server'],['fa-globe','globe'],['fa-cloud','cloud'],['fa-bolt','bolt'],
        ['fa-shield-halved','shield'],['fa-lock','lock'],['fa-envelope','envelope'],
        ['fa-paper-plane','paper-plane'],['fa-database','database'],['fa-hard-drive','hard-drive'],
        ['fa-microchip','microchip'],['fa-cpu','cpu'],['fa-network-wired','network'],
        ['fa-chart-bar','chart'],['fa-chart-line','chart-line'],['fa-users','users'],
        ['fa-user-gear','user-gear'],['fa-star','star'],['fa-certificate','certificate'],
        ['fa-check-circle','check'],['fa-arrow-up-right-dots','trending'],['fa-code','code'],
        ['fa-terminal','terminal'],['fa-bug','bug'],['fa-gears','gears'],
        ['fa-wrench','wrench'],['fa-screwdriver-wrench','tools'],['fa-headset','headset'],
        ['fa-handshake','handshake'],['fa-rocket','rocket'],['fa-fire','fire'],
        ['fa-crown','crown'],['fa-medal','medal'],['fa-trophy','trophy'],
        ['fa-table-columns','layout'],['fa-layer-group','layers'],['fa-sitemap','sitemap'],
        ['fa-c','cpanel'],['fa-google','google'],['fa-aws','aws'],
    ],
    'Tabler': [
        ['ti-server','server'],['ti-globe','globe'],['ti-cloud','cloud'],['ti-bolt','bolt'],
        ['ti-shield','shield'],['ti-lock','lock'],['ti-mail','mail'],
        ['ti-send','send'],['ti-database','database'],['ti-device-desktop','desktop'],
        ['ti-cpu','cpu'],['ti-network','network'],['ti-chart-bar','chart'],
        ['ti-chart-line','chart-line'],['ti-users','users'],['ti-user-cog','user-cog'],
        ['ti-star','star'],['ti-certificate','certificate'],['ti-circle-check','check'],
        ['ti-trending-up','trending'],['ti-code','code'],['ti-terminal','terminal'],
        ['ti-bug','bug'],['ti-settings','settings'],['ti-tool','tool'],
        ['ti-headset','headset'],['ti-handshake','handshake'],['ti-rocket','rocket'],
        ['ti-flame','flame'],['ti-crown','crown'],['ti-medal','medal'],
        ['ti-trophy','trophy'],['ti-layout-columns','layout'],['ti-layers','layers'],
        ['ti-hierarchy','hierarchy'],['ti-affiliate','affiliate'],
    ],
    'Lucide': [
        ['lucide:server','server'],['lucide:globe','globe'],['lucide:cloud','cloud'],
        ['lucide:zap','zap'],['lucide:shield','shield'],['lucide:lock','lock'],
        ['lucide:mail','mail'],['lucide:send','send'],['lucide:database','database'],
        ['lucide:hard-drive','hard-drive'],['lucide:cpu','cpu'],['lucide:monitor','monitor'],
        ['lucide:wifi','wifi'],['lucide:bar-chart','bar-chart'],['lucide:trending-up','trending'],
        ['lucide:users','users'],['lucide:star','star'],['lucide:check-circle','check'],
        ['lucide:code','code'],['lucide:terminal','terminal'],['lucide:settings','settings'],
        ['lucide:wrench','wrench'],['lucide:headphones','headphones'],['lucide:rocket','rocket'],
        ['lucide:flame','flame'],['lucide:crown','crown'],['lucide:award','award'],
        ['lucide:trophy','trophy'],['lucide:layout','layout'],['lucide:layers','layers'],
    ],
    'Material': [
        ['material:dns','dns'],['material:public','public'],['material:cloud','cloud'],
        ['material:bolt','bolt'],['material:security','security'],['material:lock','lock'],
        ['material:email','email'],['material:send','send'],['material:storage','storage'],
        ['material:computer','computer'],['material:memory','memory'],['material:router','router'],
        ['material:bar_chart','chart'],['material:trending_up','trending'],
        ['material:group','users'],['material:stars','stars'],['material:verified','verified'],
        ['material:check_circle','check'],['material:code','code'],['material:terminal','terminal'],
        ['material:settings','settings'],['material:build','build'],['material:support_agent','support'],
        ['material:handshake','handshake'],['material:rocket_launch','rocket'],['material:local_fire_department','fire'],
        ['material:workspace_premium','premium'],['material:emoji_events','trophy'],
    ],
};

let _pickerTarget = null;
let _pickerLib = 'FontAwesome';

function openIconPicker(btn, targetSelector) {
    const card = btn.closest('.item-card');
    _pickerTarget = card.querySelector(targetSelector);
    _pickerLib = 'FontAwesome';
    document.getElementById('iconPickerModal').style.display = 'flex';
    renderIconGrid();
}

function closeIconPicker() {
    document.getElementById('iconPickerModal').style.display = 'none';
    _pickerTarget = null;
}

function switchIconLib(lib) {
    _pickerLib = lib;
    document.querySelectorAll('.icon-lib-tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    renderIconGrid();
}

function renderIconGrid(filter = '') {
    const icons = ICON_DB[_pickerLib] || [];
    const q = filter.toLowerCase();
    const filtered = q ? icons.filter(([k, n]) => n.includes(q) || k.includes(q)) : icons;
    
    let html = filtered.map(([iconStr, name]) => {
        const iconHtml = renderIcon(iconStr);
        return `<div class="icon-picker-item" onclick="selectIcon('${iconStr}')" title="${iconStr}">
            <div class="ip-icon">${iconHtml}</div>
            <div class="ip-label">${name}</div>
        </div>`;
    }).join('');
    
    document.getElementById('iconGrid').innerHTML = html || '<div style="grid-column:1/-1;color:var(--mut);font-size:13px;text-align:center;padding:20px;">Tidak ada ikon ditemukan</div>';
    if(typeof lucide !== 'undefined') lucide.createIcons();
}

function selectIcon(iconStr) {
    if(_pickerTarget) {
        _pickerTarget.value = iconStr;
        _pickerTarget.dispatchEvent(new Event('input'));
        // Update current preview in footer
        document.getElementById('ipCurrentIcon').innerHTML = renderIcon(iconStr);
        document.getElementById('ipCurrentText').textContent = iconStr;
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }
}

function applyIconPicker() {
    closeIconPicker();
}

// Escape HTML helper
function esc(str) { return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// Init
document.addEventListener("DOMContentLoaded", () => {
    renderNavLinks();
    renderTlds();
    renderFeatures();
    renderLogos();
    if(typeof lucide !== 'undefined') lucide.createIcons();
    if(typeof feather !== 'undefined') feather.replace();
});

document.getElementById('iconPickerSearch')?.addEventListener('input', e => renderIconGrid(e.target.value));
</script>

<!-- Icon Picker Modal -->
<div class="icon-picker-modal" id="iconPickerModal" style="display:none;" onclick="if(event.target===this) closeIconPicker()">
    <div class="icon-picker-box">
        <div class="icon-picker-head">
            <h6><i class="ph-fill ph-grid-four me-2"></i> Pilih Ikon</h6>
            <input type="text" id="iconPickerSearch" class="icon-picker-search" placeholder="Cari ikon... (misal: cloud, server)">
            <button type="button" onclick="closeIconPicker()" style="background:none;border:none;color:var(--mut);font-size:18px;cursor:pointer;padding:4px;">✕</button>
        </div>
        <div class="icon-lib-tabs">
            <div class="icon-lib-tab active" onclick="switchIconLib('FontAwesome')">FontAwesome 6</div>
            <div class="icon-lib-tab" onclick="switchIconLib('Tabler')">Tabler Icons</div>
            <div class="icon-lib-tab" onclick="switchIconLib('Lucide')">Lucide</div>
            <div class="icon-lib-tab" onclick="switchIconLib('Material')">Material Symbols</div>
        </div>
        <div class="icon-picker-grid" id="iconGrid"></div>
        <div class="icon-picker-foot">
            <div class="ip-current">
                <div class="ip-current-icon" id="ipCurrentIcon"><i class="ph ph-square"></i></div>
                <span class="ip-current-text" id="ipCurrentText">Pilih ikon di atas</span>
            </div>
            <button type="button" class="btn btn-primary btn-sm fw-bold px-4" onclick="applyIconPicker()"><i class="ph-fill ph-check me-1"></i> Pilih</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/library/footer.php'; ?>
