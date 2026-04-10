<?php
/**
 * admin/media.php
 * Media Library — Upload, browse & manage files as CDN
 * 
 * Public URL: /uploads/cms/{stored_name}
 * AJAX upload endpoint: POST ?ajax=upload → JSON
 */
require_once __DIR__ . '/library/admin_session.php';
require_once __DIR__ . '/../config/database.php';

/* ── Auto-create table ───────────────────────────────────────── */
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS cms_files (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_name VARCHAR(255)  NOT NULL,
    stored_name   VARCHAR(255)  NOT NULL UNIQUE,
    mime_type     VARCHAR(120)  NOT NULL,
    file_size     BIGINT        NOT NULL DEFAULT 0,
    file_type     ENUM('image','video','audio','document','other') NOT NULL DEFAULT 'other',
    title         VARCHAR(255)  DEFAULT NULL,
    folder        VARCHAR(100)  DEFAULT 'uncategorized',
    uploaded_by   VARCHAR(100)  DEFAULT NULL,
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* ── Config ──────────────────────────────────────────────────── */
$upload_dir = __DIR__ . '/../uploads/cms/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$proto    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $proto . '://' . $_SERVER['HTTP_HOST'];
$cdn_base = $base_url . '/cms/';

/* ── Helpers ─────────────────────────────────────────────────── */
function media_detect_mime(string $tmp, string $name): string {
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $m  = finfo_file($fi, $tmp); finfo_close($fi);
        if ($m && $m !== 'application/octet-stream') return $m;
    }
    $map = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif',
            'webp'=>'image/webp','svg'=>'image/svg+xml','ico'=>'image/x-icon',
            'mp4'=>'video/mp4','webm'=>'video/webm','mov'=>'video/quicktime',
            'mp3'=>'audio/mpeg','ogg'=>'audio/ogg','wav'=>'audio/wav',
            'pdf'=>'application/pdf','doc'=>'application/msword',
            'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt'=>'text/plain','csv'=>'text/csv','zip'=>'application/zip'];
    return $map[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
}
function media_detect_type(string $mime): string {
    if (str_starts_with($mime, 'image/'))  return 'image';
    if (str_starts_with($mime, 'video/'))  return 'video';
    if (str_starts_with($mime, 'audio/'))  return 'audio';
    foreach (['application/pdf','application/msword','application/vnd.','text/'] as $d)
        if (str_starts_with($mime, $d)) return 'document';
    return 'other';
}
function media_fmt_size(int $b): string {
    if ($b >= 1048576) return number_format($b/1048576, 1).' MB';
    if ($b >= 1024)    return number_format($b/1024, 1).' KB';
    return $b.' B';
}
function media_type_icon(string $type): string {
    return match($type) {
        'image'    => 'ph-image',
        'video'    => 'ph-video',
        'audio'    => 'ph-music-note',
        'document' => 'ph-file-text',
        default    => 'ph-file',
    };
}
function media_type_color(string $type): string {
    return match($type) {
        'image'    => 'var(--accent)',
        'video'    => 'var(--pur)',
        'audio'    => '#ec4899',
        'document' => 'var(--warn)',
        default    => 'var(--sub)',
    };
}

/* ════════════════════════════════════════════
   AJAX UPLOAD  (?ajax=upload)
════════════════════════════════════════════ */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'upload') {
    header('Content-Type: application/json');
    $allowed_ext = ['jpg','jpeg','png','gif','webp','svg','ico','mp4','webm','mov','mp3','ogg','wav',
                    'pdf','doc','docx','xls','xlsx','txt','csv','zip'];
    $max_size = 30 * 1024 * 1024;

    $folder = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($_POST['folder'] ?? 'general')) ?: 'general';

    if (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        echo json_encode(['ok' => false, 'error' => 'Tidak ada file diterima.']); exit;
    }
    $f = $_FILES['file'];
    $orig_name = basename($f['name']);
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    
    if ($f['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => "Upload error (kode {$f['error']}).", 'name' => $orig_name]); exit;
    }
    if ($f['size'] > $max_size) {
        echo json_encode(['ok' => false, 'error' => 'File terlalu besar (maks 30 MB).', 'name' => $orig_name]); exit;
    }
    if (!in_array($ext, $allowed_ext)) {
        echo json_encode(['ok' => false, 'error' => "Ekstensi .{$ext} tidak diizinkan.", 'name' => $orig_name]); exit;
    }

    $mime    = media_detect_mime($f['tmp_name'], $orig_name);
    $type    = media_detect_type($mime);
    $stored  = date('Ymd_His') . '_' . substr(uniqid(), -6) . '.' . $ext;
    $dest    = $upload_dir . $stored;

    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        echo json_encode(['ok' => false, 'error' => 'Gagal memindahkan file.', 'name' => $orig_name]); exit;
    }

    $esc_orig   = mysqli_real_escape_string($conn, $orig_name);
    $esc_stored = mysqli_real_escape_string($conn, $stored);
    $esc_mime   = mysqli_real_escape_string($conn, $mime);
    $esc_type   = mysqli_real_escape_string($conn, $type);
    $esc_folder = mysqli_real_escape_string($conn, $folder);
    $esc_upby   = mysqli_real_escape_string($conn, $_SESSION['username'] ?? 'admin');
    $esc_size   = (int)$f['size'];

    $q = mysqli_query($conn, "INSERT INTO cms_files (original_name, stored_name, mime_type, file_size, file_type, folder, uploaded_by)
                               VALUES ('$esc_orig','$esc_stored','$esc_mime',$esc_size,'$esc_type','$esc_folder','$esc_upby')");
    if (!$q) { @unlink($dest); echo json_encode(['ok' => false, 'error' => 'Gagal simpan ke database.', 'name' => $orig_name]); exit; }

    $new_id  = mysqli_insert_id($conn);
    $pub_url = $cdn_base . $stored;
    echo json_encode(['ok'=>true,'id'=>$new_id,'name'=>$orig_name,'url'=>$pub_url,'size'=>media_fmt_size($esc_size),'type'=>$type,'stored'=>$stored]);
    exit;
}

/* ── AJAX Delete ─────────────────────────────────────────────── */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete' && !empty($_POST['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_POST['id'];
    $r  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stored_name FROM cms_files WHERE id = $id"));
    if ($r) {
        $path = $upload_dir . $r['stored_name'];
        if (file_exists($path)) unlink($path);
        mysqli_query($conn, "DELETE FROM cms_files WHERE id = $id");
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'File tidak ditemukan.']);
    }
    exit;
}

/* ── AJAX Copy URL (increment views) ─────────────────────────── */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'geturl' && !empty($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    $r  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stored_name FROM cms_files WHERE id = $id"));
    if ($r) echo json_encode(['ok'=>true,'url'=>$cdn_base.$r['stored_name']]);
    else echo json_encode(['ok'=>false]);
    exit;
}

/* ════════════════════════════════════════════
   REGULAR ACTIONS (edit / toggle / delete)
════════════════════════════════════════════ */
$toast   = '';
$toast_e = '';
$action  = $_POST['action'] ?? '';

if ($action === 'edit' && !empty($_POST['id'])) {
    $id     = (int)$_POST['id'];
    $title  = mysqli_real_escape_string($conn, trim($_POST['title'] ?? ''));
    $folder = mysqli_real_escape_string($conn, trim($_POST['folder'] ?? 'general'));
    mysqli_query($conn, "UPDATE cms_files SET title='$title', folder='$folder' WHERE id=$id");
    $toast  = 'Metadata file diperbarui.';
}
if ($action === 'toggle' && !empty($_POST['id'])) {
    $id = (int)$_POST['id'];
    mysqli_query($conn, "UPDATE cms_files SET is_active = NOT is_active WHERE id=$id");
    $toast = 'Status file diubah.';
}

/* ════════════════════════════════════════════
   FETCH / FILTER
════════════════════════════════════════════ */
$filter_type   = $_GET['type']   ?? '';
$filter_folder = $_GET['folder'] ?? '';
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 30;
$offset        = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];
if ($filter_type)   { $where[] = "file_type = '".mysqli_real_escape_string($conn,$filter_type)."'"; }
if ($filter_folder) { $where[] = "folder    = '".mysqli_real_escape_string($conn,$filter_folder)."'"; }
if ($search)        { $s = mysqli_real_escape_string($conn,$search); $where[] = "(original_name LIKE '%$s%' OR title LIKE '%$s%')"; }
$wsql = implode(' AND ', $where);

$total_count = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM cms_files WHERE $wsql"))['c'];
$total_pages = max(1, ceil($total_count / $per_page));
$files_rows  = mysqli_fetch_all(mysqli_query($conn,"SELECT * FROM cms_files WHERE $wsql ORDER BY created_at DESC LIMIT $per_page OFFSET $offset"), MYSQLI_ASSOC);

/* Stats */
$stats   = mysqli_fetch_all(mysqli_query($conn,"SELECT file_type, COUNT(*) cnt, SUM(file_size) total_sz FROM cms_files GROUP BY file_type"), MYSQLI_ASSOC);
$stat_map= [];
foreach ($stats as $s) $stat_map[$s['file_type']] = $s;
$all_count   = array_sum(array_column($stats,'cnt'));
$all_size    = array_sum(array_column($stats,'total_sz'));
$folders_all = mysqli_fetch_all(mysqli_query($conn,"SELECT DISTINCT folder FROM cms_files ORDER BY folder"), MYSQLI_ASSOC);

$page_title = "Media Library";
include __DIR__ . '/library/header.php';
?>

<style>
    /* ── Upload Zone ── */
    .drop-zone { border: 2px dashed var(--border); border-radius: 14px; padding: 40px 20px; text-align: center; cursor: pointer; transition: all .2s; background: var(--hover); position: relative; user-select: none; }
    .drop-zone:hover, .drop-zone.drag-over { border-color: var(--accent); background: var(--as); }
    .drop-zone input[type=file] { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 2; }
    .drop-icon { font-size: 48px; margin-bottom: 12px; opacity: .5; pointer-events: none; color: var(--accent); }
    .drop-txt  { font-weight: 700; font-size: 14px; margin-bottom: 4px; pointer-events: none; }
    .drop-sub  { font-size: 11px; color: var(--mut); pointer-events: none; }

    /* ── Upload Progress ── */
    .up-bar-bg   { height: 5px; border-radius: 99px; background: var(--hover); border: 1px solid var(--border); overflow: hidden; margin-top: 12px; }
    .up-bar-fill { height: 100%; border-radius: 99px; background: var(--accent); width: 0%; transition: width .3s; }
    .up-file-list { margin-top: 10px; display: flex; flex-direction: column; gap: 5px; max-height: 160px; overflow-y: auto; }
    .up-file-item { display: flex; align-items: center; gap: 8px; font-size: 12px; padding: 6px 10px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; }
    .ufi-name  { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ufi-sz    { color: var(--mut); flex-shrink: 0; font-size: 11px; }
    .ufi-ok    { color: var(--ok); font-size: 11px; font-weight: 700; }
    .ufi-fail  { color: var(--err); font-size: 11px; font-weight: 700; }
    .ufi-wait  { color: var(--mut); font-size: 11px; }
    
    /* ── Gallery Grid ── */
    .file-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(155px,1fr)); gap: 12px; }
    @media(max-width:576px) { .file-grid { grid-template-columns: repeat(auto-fill,minmax(130px,1fr)); } }

    /* ── File Card ── */
    .file-card { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--card); transition: all .2s; position: relative; }
    .file-card:hover { box-shadow: 0 8px 28px rgba(0,0,0,.25); transform: translateY(-3px); border-color: var(--ba); }
    .file-card.inactive { opacity: .45; }
    .fc-thumb-wrap { position: relative; overflow: hidden; }
    .fc-thumb { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; background: var(--hover); cursor: zoom-in; }
    .fc-thumb-icon { width: 100%; aspect-ratio: 1; display: flex; align-items: center; justify-content: center; font-size: 44px; background: var(--surface); }
    .fc-overlay { position: absolute; inset: 0; background: rgba(0,0,0,.6); backdrop-filter: blur(3px); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; opacity: 0; transition: opacity .18s; }
    .fc-thumb-wrap:hover .fc-overlay { opacity: 1; }
    .fc-ov-btn { display: flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 7px; border: 1px solid rgba(255,255,255,.25); background: rgba(255,255,255,.12); color: #fff; font-size: 11px; font-weight: 700; cursor: pointer; text-decoration: none; width: 110px; justify-content: center; transition: background .15s; }
    .fc-ov-btn:hover { background: rgba(255,255,255,.28); color: #fff; }
    .fc-ov-btn.red { background: rgba(239,68,68,.4); }
    .fc-ov-btn.red:hover { background: rgba(239,68,68,.7); }
    .fc-body { padding: 8px 10px 10px; border-top: 1px solid var(--border); }
    .fc-name { font-size: 11px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px; }
    .fc-meta { display: flex; align-items: center; justify-content: space-between; font-size: 10px; color: var(--mut); }
    .status-dot { position: absolute; top: 8px; left: 8px; width: 9px; height: 9px; border-radius: 50%; border: 1.5px solid rgba(0,0,0,.2); z-index: 2; }
    .dot-on { background: var(--ok); } .dot-off { background: var(--err); }
    
    /* ── Copy URL chip ── */
    .copy-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; font-family: 'JetBrains Mono',monospace; background: var(--as); border: 1px solid var(--ba); border-radius: 5px; padding: 2px 7px; cursor: pointer; color: var(--accent); transition: background .12s; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .copy-chip:hover { background: var(--ag); }

    /* ── Type filter tabs ── */
    .type-tabs { display: flex; gap: 5px; flex-wrap: wrap; }
    .ttab { padding: 5px 13px; border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; border: 1px solid var(--border); color: var(--sub); background: var(--hover); transition: all .15s; display: flex; align-items: center; gap: 5px; }
    .ttab:hover, .ttab.active { background: var(--accent); border-color: var(--accent); color: #fff; }

    /* ── View toggle ── */
    .vtbtn { width: 30px; height: 30px; border-radius: 7px; border: 1px solid var(--border); background: var(--hover); color: var(--mut); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 15px; transition: all .15s; }
    .vtbtn.active, .vtbtn:hover { background: var(--accent); border-color: var(--accent); color: #fff; }

    /* ── List view ── */
    .file-list-view { display: none; flex-direction: column; gap: 6px; }
    .file-list-view.show { display: flex; }
    .file-grid.hide { display: none; }
    .fl-row { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; background: var(--card); transition: all .15s; }
    .fl-row:hover { background: var(--hover); border-color: var(--ba); }
    .fl-thumb { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; flex-shrink: 0; background: var(--hover); display: flex; align-items: center; justify-content: center; overflow: hidden; font-size: 20px; }
    .fl-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .fl-info { flex: 1; min-width: 0; }
    .fl-name { font-size: 13px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fl-meta { font-size: 11px; color: var(--mut); display: flex; gap: 10px; margin-top: 2px; }
    
    /* ── Lightbox ── */
    .lightbox { position: fixed; inset: 0; background: rgba(0,0,0,.92); z-index: 9999; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(6px); }
    .lightbox img { max-width: 90vw; max-height: 86vh; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,.5); }
    .lightbox-close { position: absolute; top: 20px; right: 24px; color: white; font-size: 32px; cursor: pointer; opacity: .7; background: none; border: none; transition: opacity .2s; line-height:1; }
    .lightbox-close:hover { opacity: 1; }
    .lightbox-url { position: absolute; bottom: 24px; left: 50%; transform: translateX(-50%); background: rgba(255,255,255,.12); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,.2); border-radius: 10px; padding: 10px 18px; color: white; display: flex; align-items: center; gap: 10px; font-family: 'JetBrains Mono',monospace; font-size: 12px; white-space: nowrap; max-width: 90vw; overflow: hidden; }
    .lb-copy-btn { background: var(--accent); border: none; color: white; border-radius: 6px; padding: 5px 12px; font-size: 11px; font-weight: 700; cursor: pointer; white-space: nowrap; }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="ph-fill ph-images me-2" style="color: var(--accent);"></i> Media Library</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb bc">
            <li class="breadcrumb-item"><a href="<?= base_url('admin/index') ?>">Admin</a></li>
            <li class="breadcrumb-item active">Media Library</li>
        </ol></nav>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="bd bd-acc"><i class="ph ph-cloud me-1"></i> CDN Mode</span>
    </div>
</div>

<!-- Toast -->
<?php if ($toast): ?>
<div class="toast-wrap"><div class="toast-item toast-ok"><i class="ph ph-check-circle" style="font-size:18px;flex-shrink:0"></i><?= htmlspecialchars($toast) ?></div></div>
<?php endif; ?>

<div class="row g-4">

    <!-- LEFT: Upload + Sidebar -->
    <div class="col-lg-3">
        <!-- Upload Card -->
        <div class="card-c mb-3">
            <div class="ch pb-3 mb-3" style="border-bottom:1px solid var(--border);">
                <h3 class="ct"><i class="ph-fill ph-upload-simple text-primary me-2"></i> Upload File</h3>
            </div>
            <div class="cb">
                <div class="mb-3">
                    <label class="fl" for="uploadFolder">Folder</label>
                    <select class="field-select fc" id="uploadFolder" style="width:100%;">
                        <option value="general">general</option>
                        <option value="images">images</option>
                        <option value="documents">documents</option>
                        <option value="landing">landing</option>
                        <?php foreach($folders_all as $fol): ?>
                            <?php if(!in_array($fol['folder'],['general','images','documents','landing'])): ?>
                            <option value="<?= htmlspecialchars($fol['folder']) ?>"><?= htmlspecialchars($fol['folder']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="drop-zone" id="dropZone">
                    <input type="file" id="fileInput" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip">
                    <div class="drop-icon"><i class="ph ph-cloud-arrow-up"></i></div>
                    <div class="drop-txt">Drag & drop file di sini</div>
                    <div class="drop-sub">atau klik untuk pilih file<br>Maks 30 MB per file</div>
                </div>
                
                <div id="uploadProgress" style="display:none; margin-top:12px;">
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--sub);margin-bottom:4px;">
                        <span id="upLabel">Mengupload...</span>
                        <span id="upPercent">0%</span>
                    </div>
                    <div class="up-bar-bg"><div class="up-bar-fill" id="upBar"></div></div>
                    <div class="up-file-list" id="upFileList"></div>
                </div>
            </div>
        </div>

        <!-- Stats Card -->
        <div class="card-c mb-3">
            <div class="ch pb-3 mb-3" style="border-bottom:1px solid var(--border);">
                <h3 class="ct"><i class="ph-fill ph-chart-pie text-primary me-2"></i> Statistik</h3>
            </div>
            <div class="cb">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);">
                    <span style="font-size:12px;color:var(--sub);">Total File</span>
                    <span style="font-size:13px;font-weight:700;"><?= number_format($all_count) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);">
                    <span style="font-size:12px;color:var(--sub);">Total Ukuran</span>
                    <span style="font-size:13px;font-weight:700;"><?= media_fmt_size((int)$all_size) ?></span>
                </div>
                <?php foreach (['image','video','audio','document','other'] as $t): 
                    if (!isset($stat_map[$t])) continue;
                    $s = $stat_map[$t];
                ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border);">
                    <span style="font-size:11px;color:var(--sub);display:flex;align-items:center;gap:5px;">
                        <i class="ph-fill <?= media_type_icon($t) ?>" style="color:<?= media_type_color($t) ?>;"></i> <?= ucfirst($t) ?>
                    </span>
                    <span style="font-size:11px;font-weight:600;"><?= $s['cnt'] ?> file</span>
                </div>
                <?php endforeach; ?>
                
                <!-- Folders -->
                <?php if (!empty($folders_all)): ?>
                <div class="mt-3">
                    <div class="fl mb-2">Folder</div>
                    <?php foreach($folders_all as $fol): ?>
                    <a href="?folder=<?= urlencode($fol['folder']) ?>" class="ttab mb-1 d-inline-flex" style="<?= $filter_folder == $fol['folder'] ? 'background:var(--accent);color:#fff;border-color:var(--accent);' : '' ?>">
                        <i class="ph ph-folder-open"></i> <?= htmlspecialchars($fol['folder']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: File Browser -->
    <div class="col-lg-9">
        <div class="card-c">
            <!-- Toolbar -->
            <div class="cb" style="border-bottom:1px solid var(--border); padding-bottom:16px;">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Filter Tabs -->
                    <div class="type-tabs flex-grow-1">
                        <a href="?<?= $filter_folder ? 'folder='.urlencode($filter_folder).'&' : '' ?>q=<?= urlencode($search) ?>" class="ttab<?= !$filter_type ? ' active' : '' ?>">
                            Semua <span style="opacity:.7;"><?= $all_count ?></span>
                        </a>
                        <?php foreach(['image','video','audio','document','other'] as $t): ?>
                        <?php $tc = $stat_map[$t]['cnt'] ?? 0; if(!$tc && $filter_type !== $t) continue; ?>
                        <a href="?type=<?= $t ?>&<?= $filter_folder ? 'folder='.urlencode($filter_folder).'&' : '' ?>q=<?= urlencode($search) ?>" class="ttab<?= $filter_type==$t ? ' active' : '' ?>">
                            <i class="ph-fill <?= media_type_icon($t) ?>"></i> <?= ucfirst($t) ?> <?= $tc ? "<span style='opacity:.7;'>$tc</span>" : '' ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <!-- View toggle -->
                    <div class="d-flex gap-1">
                        <button class="vtbtn active" id="btnGrid" onclick="setView('grid')"><i class="ph ph-squares-four"></i></button>
                        <button class="vtbtn" id="btnList" onclick="setView('list')"><i class="ph ph-list"></i></button>
                    </div>
                    <!-- Search -->
                    <form method="GET" style="display:flex;gap:4px;min-width:180px;">
                        <?php if($filter_type)  echo "<input type='hidden' name='type' value='".htmlspecialchars($filter_type)."'>"; ?>
                        <?php if($filter_folder) echo "<input type='hidden' name='folder' value='".htmlspecialchars($filter_folder)."'>"; ?>
                        <input name="q" class="fc" placeholder="Cari file..." value="<?= htmlspecialchars($search) ?>" style="flex:1;padding:6px 10px;font-size:12px;">
                        <button class="btn btn-sm btn-outline-light"><i class="ph ph-magnifying-glass"></i></button>
                    </form>
                </div>
            </div>
            
            <div class="cb">
                <?php if (empty($files_rows)): ?>
                <div class="text-center py-5">
                    <div style="font-size:48px;color:var(--mut);margin-bottom:12px;"><i class="ph ph-image-broken"></i></div>
                    <div style="color:var(--sub);">Belum ada file. Upload sekarang!</div>
                </div>
                <?php else: ?>

                <!-- GRID VIEW -->
                <div class="file-grid" id="viewGrid">
                    <?php foreach ($files_rows as $f):
                        $ext_f    = strtolower(pathinfo($f['original_name'], PATHINFO_EXTENSION));
                        $pub_url  = $cdn_base . $f['stored_name'];
                        $is_img   = ($f['file_type'] === 'image');
                        $type_col = media_type_color($f['file_type']);
                        $type_ico = media_type_icon($f['file_type']);
                    ?>
                    <div class="file-card <?= $f['is_active'] ? '' : 'inactive' ?>" id="card-<?= $f['id'] ?>">
                        <div class="fc-thumb-wrap">
                            <div class="status-dot <?= $f['is_active'] ? 'dot-on' : 'dot-off' ?>"></div>
                            <?php if($is_img): ?>
                                <img src="<?= htmlspecialchars($pub_url) ?>" class="fc-thumb" 
                                     data-fallback-icon="<?= $type_ico ?>" data-fallback-color="<?= $type_col ?>"
                                     onclick="openLightbox('<?= htmlspecialchars($pub_url) ?>')"
                                     onerror="imgFallback(this)">
                            <?php else: ?>
                                <div class="fc-thumb-icon" style="color: <?= $type_col ?>;">
                                    <i class="ph-fill <?= $type_ico ?>"></i>
                                </div>
                            <?php endif; ?>
                            <div class="fc-overlay">
                                <button class="fc-ov-btn" onclick="copyUrl('<?= htmlspecialchars($pub_url) ?>', this)"><i class="ph ph-copy"></i> Copy URL</button>
                                <a class="fc-ov-btn" href="<?= htmlspecialchars($pub_url) ?>" target="_blank"><i class="ph ph-arrow-square-out"></i> Buka</a>
                                <button class="fc-ov-btn red" onclick="deleteFile(<?= $f['id'] ?>, 'card-<?= $f['id'] ?>')"><i class="ph ph-trash"></i> Hapus</button>
                            </div>
                        </div>
                        <div class="fc-body">
                            <div class="fc-name" title="<?= htmlspecialchars($f['original_name']) ?>">
                                <?= htmlspecialchars($f['title'] ?: $f['original_name']) ?>
                            </div>
                            <div class="fc-meta">
                                <span style="color: <?= $type_col ?>; font-weight: 700; font-size: 9px; text-transform: uppercase;"><?= $f['file_type'] ?></span>
                                <span><?= media_fmt_size($f['file_size']) ?></span>
                            </div>
                            <div class="mt-1">
                                <span class="copy-chip" onclick="copyUrl('<?= htmlspecialchars($pub_url) ?>', this)">
                                    <i class="ph ph-link"></i> <?= htmlspecialchars(substr($f['stored_name'], 0, 20)) ?>...
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- LIST VIEW -->
                <div class="file-list-view" id="viewList">
                    <?php foreach ($files_rows as $f):
                        $ext_f    = strtolower(pathinfo($f['original_name'], PATHINFO_EXTENSION));
                        $pub_url  = $cdn_base . $f['stored_name'];
                        $is_img   = ($f['file_type'] === 'image');
                        $type_col = media_type_color($f['file_type']);
                        $type_ico = media_type_icon($f['file_type']);
                    ?>
                    <div class="fl-row <?= $f['is_active'] ? '' : 'inactive' ?>" id="lrow-<?= $f['id'] ?>">
                        <div class="fl-thumb" style="color: <?= $type_col ?>;">
                            <?php if ($is_img): ?>
                                <img src="<?= htmlspecialchars($pub_url) ?>" alt="thumb"
                                     data-fallback-icon="<?= $type_ico ?>" data-fallback-color="<?= $type_col ?>"
                                     style="width:44px;height:44px;object-fit:cover;border-radius:8px;display:block;"
                                     onerror="imgFallback(this)">
                            <?php else: ?>
                                <i class="ph-fill <?= $type_ico ?>"></i>
                            <?php endif; ?>
                        </div>
                        <div class="fl-info">
                            <div class="fl-name"><?= htmlspecialchars($f['title'] ?: $f['original_name']) ?></div>
                            <div class="fl-meta">
                                <span style="color:<?= $type_col ?>;font-weight:700;"><?= strtoupper($f['file_type']) ?></span>
                                <span><?= media_fmt_size($f['file_size']) ?></span>
                                <span style="color:var(--mut);"><?= $f['folder'] ?></span>
                                <span style="color:var(--mut);"><?= date('d M Y', strtotime($f['created_at'])) ?></span>
                            </div>
                        </div>
                        <span class="copy-chip" onclick="copyUrl('<?= htmlspecialchars($pub_url) ?>', this)" style="max-width: 220px;">
                            <i class="ph ph-link"></i> <?= htmlspecialchars($f['stored_name']) ?>
                        </span>
                        <div class="d-flex gap-1">
                            <a href="<?= htmlspecialchars($pub_url) ?>" target="_blank" class="ab" title="Buka"><i class="ph ph-arrow-square-out"></i></a>
                            <?php if($is_img): ?>
                            <button class="ab" onclick="openLightbox('<?= htmlspecialchars($pub_url) ?>')" title="Preview"><i class="ph ph-eye"></i></button>
                            <?php endif; ?>
                            <button class="ab red" onclick="deleteFile(<?= $f['id'] ?>, 'lrow-<?= $f['id'] ?>')" title="Hapus"><i class="ph ph-trash"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3" style="border-top:1px solid var(--border);">
                    <span style="font-size:12px;color:var(--sub);">
                        <?= number_format($total_count) ?> file · Hal <?= $page ?> dari <?= $total_pages ?>
                    </span>
                    <div class="d-flex gap-1">
                        <?php if ($page > 1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="pg">‹</a><?php endif; ?>
                        <?php for ($p = max(1,$page-2); $p <= min($total_pages,$page+2); $p++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>" class="pg <?= $p==$page ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="pg">›</a><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" style="display:none;" onclick="if(event.target===this) closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">✕</button>
    <img src="" id="lightboxImg" alt="Preview">
    <div class="lightbox-url" id="lightboxUrl">
        <span id="lightboxUrlText" style="overflow:hidden;text-overflow:ellipsis;flex:1;"></span>
        <button class="lb-copy-btn" onclick="navigator.clipboard.writeText(document.getElementById('lightboxUrlText').textContent).then(()=>this.textContent='✓ Disalin!')">Copy URL</button>
    </div>
</div>

<!-- Delete confirm form (hidden) -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
    // ── Image fallback (replaces broken img with icon div safely) ─
    function imgFallback(img) {
        const icon  = img.dataset.fallbackIcon  || 'ph-image';
        const color = img.dataset.fallbackColor || 'var(--sub)';
        const isGrid = img.classList.contains('fc-thumb');
        if (isGrid) {
            const div = document.createElement('div');
            div.className = 'fc-thumb-icon';
            div.style.color = color;
            div.innerHTML = `<i class="ph-fill ${icon}"></i>`;
            img.replaceWith(div);
        } else {
            // List view — just replace img inside fl-thumb
            img.parentElement.style.color = color;
            img.parentElement.innerHTML = `<i class="ph-fill ${icon}"></i>`;
        }
    }

    // ── Upload ─────────────────────────────────────────────────
    const dropZone   = document.getElementById('dropZone');
    const fileInput  = document.getElementById('fileInput');
    const upProgress = document.getElementById('uploadProgress');
    const upBar      = document.getElementById('upBar');
    const upLabel    = document.getElementById('upLabel');
    const upPercent  = document.getElementById('upPercent');
    const upList     = document.getElementById('upFileList');

    dropZone.addEventListener('dragover',   e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave',  () => dropZone.classList.remove('drag-over'));
    dropZone.addEventListener('drop',       e => { e.preventDefault(); dropZone.classList.remove('drag-over'); uploadFiles(e.dataTransfer.files); });
    fileInput.addEventListener('change',    () => uploadFiles(fileInput.files));

    async function uploadFiles(files) {
        if (!files.length) return;
        upProgress.style.display = 'block';
        upList.innerHTML = '';
        upBar.style.width = '0%';

        const folder = document.getElementById('uploadFolder').value;
        let completed = 0;

        for (const file of files) {
            const item = document.createElement('div');
            item.className = 'up-file-item';
            item.innerHTML = `<i class="ph ph-file ufi-wait"></i><span class="ufi-name">${file.name}</span><span class="ufi-sz">${fmtBytes(file.size)}</span><span class="ufi-wait ufi-st">⏳</span>`;
            upList.appendChild(item);
            const st = item.querySelector('.ufi-st');
            const ico = item.querySelector('i');

            try {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('folder', folder);
                const res = await fetch('?ajax=upload', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok) {
                    st.textContent = '✓ OK'; st.className = 'ufi-ok ufi-st';
                    ico.className = 'ph-fill ph-check-circle ufi-ok';
                    // Reload after all done
                } else {
                    st.textContent = data.error || 'Gagal'; st.className = 'ufi-fail ufi-st';
                    ico.className = 'ph-fill ph-x-circle ufi-fail';
                }
            } catch(e) {
                st.textContent = 'Error'; st.className = 'ufi-fail ufi-st';
            }

            completed++;
            const pct = Math.round((completed / files.length) * 100);
            upBar.style.width = pct + '%';
            upPercent.textContent = pct + '%';
            upLabel.textContent = `Mengupload ${completed} / ${files.length}...`;
        }

        upLabel.textContent = `Selesai! ${completed} file diupload.`;
        upPercent.textContent = '100%';
        setTimeout(() => location.reload(), 1200);
    }

    function fmtBytes(b) {
        if (b >= 1048576) return (b/1048576).toFixed(1)+' MB';
        if (b >= 1024)    return (b/1024).toFixed(1)+' KB';
        return b+' B';
    }

    // ── Copy URL ───────────────────────────────────────────────
    function copyUrl(url, el) {
        navigator.clipboard.writeText(url).then(() => {
            const orig = el.innerHTML;
            el.innerHTML = '<i class="ph-fill ph-check"></i> Disalin!';
            el.style.background = 'var(--oks)'; el.style.color = 'var(--ok)';
            setTimeout(() => { el.innerHTML = orig; el.style.background = ''; el.style.color = ''; }, 2000);
        });
    }

    // ── Lightbox ───────────────────────────────────────────────
    function openLightbox(url) {
        document.getElementById('lightboxImg').src = url;
        document.getElementById('lightboxUrlText').textContent = url;
        document.getElementById('lightbox').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
        document.getElementById('lightbox').style.display = 'none';
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', e => { if(e.key==='Escape') closeLightbox(); });

    // ── Delete ─────────────────────────────────────────────────
    function deleteFile(id, elId) {
        if (!confirm('Yakin hapus file ini? Tidak bisa dikembalikan!')) return;
        fetch('?ajax=delete', { method: 'POST', body: new URLSearchParams({ id }) })
            .then(r => r.json()).then(d => {
                if (d.ok) {
                    const el = document.getElementById(elId);
                    if (el) { el.style.opacity = '0'; el.style.transition = '.3s'; setTimeout(()=>el.remove(),300); }
                } else { alert('Gagal: ' + d.error); }
            });
    }

    // ── View Toggle ────────────────────────────────────────────
    function setView(v) {
        const grid = document.getElementById('viewGrid');
        const list = document.getElementById('viewList');
        if (v === 'grid') {
            grid.classList.remove('hide'); list.classList.remove('show');
            document.getElementById('btnGrid').classList.add('active');
            document.getElementById('btnList').classList.remove('active');
            localStorage.setItem('mediaView', 'grid');
        } else {
            grid.classList.add('hide'); list.classList.add('show');
            document.getElementById('btnList').classList.add('active');
            document.getElementById('btnGrid').classList.remove('active');
            localStorage.setItem('mediaView', 'list');
        }
    }
    // Restore view
    const savedView = localStorage.getItem('mediaView');
    if (savedView === 'list') setView('list');
</script>

<?php include __DIR__ . '/library/footer.php'; ?>