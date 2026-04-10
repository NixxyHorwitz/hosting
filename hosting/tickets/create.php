<?php
require_once __DIR__ . '/../../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../library/session.php';

$user_id = $_SESSION['user_id'];

$orders_query = mysqli_query($conn, "SELECT o.id, o.domain, hp.nama_paket 
    FROM orders o JOIN hosting_plans hp ON o.hosting_plan_id=hp.id 
    WHERE o.user_id='$user_id' AND o.status='active'");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $dept      = mysqli_real_escape_string($conn, $_POST['department']);
    $priority  = mysqli_real_escape_string($conn, $_POST['priority']);
    $service_id = !empty($_POST['service_id']) ? "'".mysqli_real_escape_string($conn,$_POST['service_id'])."'" : "NULL";
    $title     = mysqli_real_escape_string($conn, $_POST['title']);
    $message   = mysqli_real_escape_string($conn, $_POST['message']);

    if (empty(trim(strip_tags($message)))) {
        $error = "Pesan tiket tidak boleh kosong!";
    } else {
        $ticket_num = "#".strtoupper(substr($dept,0,3))."-".rand(100000,999999);
        mysqli_query($conn, "INSERT INTO tickets (ticket_number,user_id,department,priority,service_id,title,status) VALUES ('$ticket_num','$user_id','$dept','$priority',$service_id,'$title','Open')");
        $ticket_id = mysqli_insert_id($conn);
        mysqli_query($conn, "INSERT INTO ticket_replies (ticket_id,user_id,message) VALUES ('$ticket_id','$user_id','$message')");
        $reply_id = mysqli_insert_id($conn);

        if (!empty($_FILES['attachments']['name'][0])) {
            $base_dir   = dirname(__DIR__, 2);
            $upload_dir = $base_dir.'/uploads/tickets/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $allowed = ['jpg','jpeg','png','pdf','zip','rar','txt'];
            foreach ($_FILES['attachments']['name'] as $k => $name) {
                if ($_FILES['attachments']['error'][$k] === UPLOAD_ERR_OK) {
                    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $size = $_FILES['attachments']['size'][$k];
                    if (in_array($ext, $allowed) && $size <= 5242880) {
                        $nn = uniqid('t_').'_'.time().'.'.$ext;
                        if (move_uploaded_file($_FILES['attachments']['tmp_name'][$k], $upload_dir.$nn)) {
                            $sn = mysqli_real_escape_string($conn, $name);
                            $sp = mysqli_real_escape_string($conn, 'uploads/tickets/'.$nn);
                            mysqli_query($conn, "INSERT INTO ticket_attachments (ticket_id,reply_id,file_name,file_path,file_size) VALUES ('$ticket_id','$reply_id','$sn','$sp','$size')");
                        }
                    }
                }
            }
        }
        header("Location: ".base_url("hosting/tickets/detail/$ticket_id"));
        exit();
    }
}

$page_title = "Kirim Tiket";
include __DIR__ . '/../../library/header.php';
?>

<style>
/* Form styles */
.tk-card { background:#fff; border:1px solid var(--border-color); border-radius:12px; overflow:hidden; margin-bottom:16px; }
.tk-head { padding:12px 16px; border-bottom:1px solid var(--border-color); display:flex; align-items:center; justify-content:space-between; }
.tk-body { padding:20px; }
.fl { font-size:11.5px; font-weight:700; color:#6c757d; text-transform:uppercase; letter-spacing:.4px; display:block; margin-bottom:5px; }
.fc-field { width:100%; padding:9px 12px; border:1px solid var(--border-color); border-radius:7px; font-size:13.5px; outline:none; background:#fff; transition:border .15s; font-family:inherit; }
.fc-field:focus { border-color:#007bff88; }
select.fc-field { cursor:pointer; }

/* Toolbar */
.ed-toolbar { display:flex; gap:3px; padding:8px 10px; border-bottom:1px solid var(--border-color); background:#f8f9fa; border-radius:8px 8px 0 0; align-items:center; flex-wrap:wrap; }
.tb-btn { width:30px; height:28px; display:flex; align-items:center; justify-content:center; border-radius:5px; border:1px solid transparent; background:transparent; color:#555; cursor:pointer; font-size:13px; font-weight:700; transition:all .15s; }
.tb-btn:hover { background:#e9ecef; color:#333; }
.tb-sep { width:1px; height:18px; background:var(--border-color); margin:0 3px; }
#editorBody { min-height:160px; max-height:320px; overflow-y:auto; padding:12px 14px; background:#fff; outline:none; font-size:13.5px; line-height:1.7; color:#333; border-radius:0 0 8px 8px; }
#editorBody:empty::before { content:attr(data-placeholder); color:#adb5bd; pointer-events:none; display:block; }

/* File chip */
.file-chip { display:inline-flex; align-items:center; gap:6px; background:#ebf5ff; border:1px solid #bbd6f5; border-radius:6px; padding:4px 10px; font-size:11.5px; color:#007bff; }

/* Breadcrumb */
.cbc { display:flex; align-items:center; gap:6px; font-size:12.5px; color:var(--text-muted); margin-bottom:14px; flex-wrap:wrap; }
.cbc a { color:var(--text-muted); text-decoration:none; }
.cbc a:hover { color:#007bff; }
.cbc .sep { font-size:10px; opacity:.5; }
</style>

<!-- Breadcrumb -->
<div class="cbc">
    <a href="<?= base_url('hosting') ?>"><i class="bi bi-house-door me-1"></i>Dashboard</a>
    <span class="sep">›</span>
    <a href="<?= base_url('hosting/tickets') ?>">Trouble Ticket</a>
    <span class="sep">›</span>
    <span style="color:#007bff;font-weight:600;">Buat Tiket</span>
</div>

<?php if(isset($error)): ?>
<div class="d-flex align-items-center gap-2 p-3 mb-3" style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;color:#dc2626;font-size:13px;font-weight:600;">
    <i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?>
</div>
<?php endif; ?>

<div class="tk-card">
    <div class="tk-head">
        <div style="font-weight:700;font-size:14px;color:#333;display:flex;align-items:center;gap:8px;">
            <i class="bi bi-send-fill text-primary"></i> Kirim Tiket Baru
        </div>
        <a href="<?= base_url('hosting/tickets') ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;font-size:12px;">
            <i class="bi bi-chevron-left me-1"></i> Kembali
        </a>
    </div>
    <div class="tk-body">
        <form action="" method="POST" id="ticketForm" enctype="multipart/form-data">
            <input type="hidden" name="message" id="msgInput">

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="fl">Departemen <span style="color:#dc2626;">*</span></label>
                    <select name="department" class="fc-field" required>
                        <option value="" disabled selected>-- Pilih departemen --</option>
                        <option value="Technical Support">Technical Support</option>
                        <option value="Billing">Billing</option>
                        <option value="Sales">Sales</option>
                        <option value="Umum">Umum</option>
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="fl">Prioritas <span style="color:#dc2626;">*</span></label>
                    <select name="priority" class="fc-field" required>
                        <option value="" disabled selected>-- Pilih prioritas --</option>
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="fl">Layanan Terkait <span style="color:#adb5bd;">(opsional)</span></label>
                <select name="service_id" class="fc-field">
                    <option value="">-- Pilih layanan --</option>
                    <?php if(mysqli_num_rows($orders_query) > 0): ?>
                    <?php while($ord = mysqli_fetch_assoc($orders_query)): ?>
                    <option value="<?= $ord['id'] ?>"><?= htmlspecialchars($ord['nama_paket'].' — '.$ord['domain']) ?></option>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="fl">Judul Tiket <span style="color:#dc2626;">*</span></label>
                <input type="text" name="title" class="fc-field" placeholder="Ringkasan masalah Anda..." required>
            </div>

            <div class="mb-4">
                <label class="fl">Pesan <span style="color:#dc2626;">*</span></label>
                <div style="border:1px solid var(--border-color);border-radius:8px;overflow:hidden;">
                    <div class="ed-toolbar">
                        <button type="button" class="tb-btn" onclick="execCmd('bold')" title="Bold"><b>B</b></button>
                        <button type="button" class="tb-btn" onclick="execCmd('italic')" title="Italic"><i>I</i></button>
                        <button type="button" class="tb-btn" onclick="execCmd('underline')" title="Underline"><u>U</u></button>
                        <div class="tb-sep"></div>
                        <button type="button" class="tb-btn" onclick="execCmd('insertUnorderedList')" title="List"><i class="bi bi-list-ul"></i></button>
                        <button type="button" class="tb-btn" onclick="execCmd('insertOrderedList')" title="Ordered"><i class="bi bi-list-ol"></i></button>
                        <div class="tb-sep"></div>
                        <button type="button" class="tb-btn" onclick="execCmd('removeFormat')" title="Clear"><i class="bi bi-eraser"></i></button>
                    </div>
                    <div id="editorBody" contenteditable="true" data-placeholder="Jelaskan masalah Anda secara detail..."></div>
                </div>
            </div>

            <!-- Footer: attachment + submit -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-3" style="border-top:1px solid var(--border-color);">
                <div>
                    <input type="file" name="attachments[]" id="attachments" multiple class="d-none" accept=".jpg,.jpeg,.png,.pdf,.zip,.rar,.txt">
                    <button type="button" onclick="document.getElementById('attachments').click()"
                        style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#fff;border:1px solid var(--border-color);border-radius:7px;color:#555;font-size:12.5px;font-weight:600;cursor:pointer;transition:background .15s;"
                        onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='#fff'">
                        <i class="bi bi-paperclip"></i> Lampiran
                    </button>
                    <span id="fileInfo" style="font-size:11px;color:var(--text-muted);margin-left:8px;">JPG, PNG, PDF, ZIP · maks 5MB</span>
                </div>
                <button type="submit" name="create_ticket" class="btn text-white fw-bold px-4" style="background:#20c997;border:none;border-radius:7px;font-size:13px;">
                    <i class="bi bi-send-fill me-1"></i> Kirim Tiket
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function execCmd(cmd) { document.execCommand(cmd, false, null); document.getElementById('editorBody').focus(); }

document.getElementById('ticketForm').addEventListener('submit', function(e) {
    const html = document.getElementById('editorBody').innerHTML.trim();
    if (html === '' || html === '<br>') { e.preventDefault(); alert('Pesan tidak boleh kosong!'); return; }
    document.getElementById('msgInput').value = html;
});

document.getElementById('attachments').addEventListener('change', function() {
    const n = this.files.length;
    document.getElementById('fileInfo').innerHTML = n
        ? `<span style="color:#22c55e;"><i class="bi bi-check-circle-fill me-1"></i>${n} file dipilih</span>`
        : 'JPG, PNG, PDF, ZIP · maks 5MB';
});
</script>

<?php include __DIR__ . '/../../library/footer.php'; ?>
