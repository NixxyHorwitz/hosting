<?php
require_once __DIR__ . '/../../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../library/session.php';

$user_id = $_SESSION['user_id'];
$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$ticket_query = mysqli_query($conn, "SELECT * FROM tickets WHERE id = '$ticket_id' AND user_id = '$user_id'");
$ticket = mysqli_fetch_assoc($ticket_query);

if (!$ticket) {
    header("Location: /hosting/tickets");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket'])) {
    if($ticket['status'] == 'Closed') {
        $redirect_url = base_url("hosting/tickets/detail/$ticket_id");
        echo "<script>alert('Tiket sudah ditutup, tidak bisa membalas.'); window.location='$redirect_url';</script>";
        exit;
    }
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    mysqli_query($conn, "INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES ('$ticket_id', '$user_id', '$message')");
    $reply_id = mysqli_insert_id($conn);
    
    // Update ticket status to Customer-Reply
    mysqli_query($conn, "UPDATE tickets SET status = 'Customer-Reply' WHERE id = '$ticket_id'");
    
    // Handle attachments
    if (!empty($_FILES['attachments']['name'][0])) {
        $base_dir = dirname(__DIR__, 2); // Menghasilkan C:\laragon\www\hosting
        $upload_dir = $base_dir . '/uploads/tickets/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $allowed_exts = ['jpg','jpeg','png','pdf','zip','rar','txt'];
        foreach ($_FILES['attachments']['name'] as $key => $name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['attachments']['tmp_name'][$key];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $size = $_FILES['attachments']['size'][$key];
                
                if (in_array($ext, $allowed_exts) && $size <= 5242880) { // Max 5MB
                    $new_name = uniqid('t_') . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                        $secure_name = mysqli_real_escape_string($conn, $name);
                        $secure_path = mysqli_real_escape_string($conn, 'uploads/tickets/' . $new_name);
                        mysqli_query($conn, "INSERT INTO ticket_attachments (ticket_id, reply_id, file_name, file_path, file_size) VALUES ('$ticket_id', '$reply_id', '$secure_name', '$secure_path', '$size')");
                    }
                }
            }
        }
    }
    
    header("Location: " . base_url("hosting/tickets/detail/$ticket_id"));
    exit();
}

$replies_query = mysqli_query($conn, "SELECT tr.*, u.nama as user_name, a.username as admin_name 
                                    FROM ticket_replies tr 
                                    LEFT JOIN users u ON tr.user_id = u.id 
                                    LEFT JOIN admins a ON tr.admin_id = a.id 
                                    WHERE tr.ticket_id = '$ticket_id' ORDER BY tr.created_at ASC");

$page_title = "Detail Tiket " . $ticket['ticket_number'];
include __DIR__ . '/../../library/header.php';
?>

<div class="row g-4 mt-2">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 6px;">
            <div class="card-header bg-white p-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold m-0 text-dark" style="font-size: 15px;"><?= htmlspecialchars($ticket['title']) ?></h5>
                <div>
                    <?php if($ticket['status'] != 'Closed'): ?>
                        <button id="toggleReplyBtn" class="btn text-white btn-sm px-3 fw-medium shadow-sm py-1" style="background-color: #477aee; border-radius: 4px; font-size: 12.5px;"><i class="bi bi-reply-fill me-1"></i> Balas</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0 bg-white" style="border-radius: 0 0 6px 6px;">
                
                <?php if($ticket['status'] != 'Closed'): ?>
                <!-- Inline Reply Box -->
                <div id="replyBoxWrapper" style="max-height: 0; opacity: 0; overflow: hidden; transition: max-height 0.4s ease-in-out, opacity 0.3s ease-in-out;">
                    <div id="replyBox" class="border-bottom">
                        <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
                        <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
                        
                        <form action="" method="POST" id="replyFormClient" enctype="multipart/form-data" class="p-3 px-4 bg-light">
                            <input type="hidden" name="message" id="messageInputClient">
                            
                            <!-- Quill Editor -->
                            <div id="editor-container" style="height: 180px; background: white; border-radius: 4px;"></div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div>
                                    <input type="file" name="attachments[]" id="replyAttachments" multiple class="d-none" accept=".jpg,.jpeg,.png,.pdf,.zip,.rar,.txt">
                                    <button type="button" onclick="document.getElementById('replyAttachments').click()" class="btn text-white px-3 py-1 shadow-sm" style="background-color: #5b71db; border-radius: 4px; font-size: 12.5px;"><i class="bi bi-paperclip me-1"></i> Tambah Lampiran</button>
                                    <div id="replyFileList" class="mt-1 text-muted" style="font-size: 11px;">Maks 5MB per file (JPG, PNG, PDF, ZIP, RAR, TXT)</div>
                                </div>
                                <button type="submit" name="reply_ticket" class="btn text-white px-4 py-1 shadow-sm fw-bold" style="background-color: #0bbb86; border-radius: 4px; font-size: 13px;">Kirim</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    if(document.getElementById('replyAttachments')) {
                        document.getElementById('replyAttachments').addEventListener('change', function(e) {
                            let files = e.target.files;
                            let fileList = document.getElementById('replyFileList');
                            if(files.length > 0) {
                                fileList.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> ' + files.length + ' file terpilih</span>';
                            } else {
                                fileList.innerHTML = 'Maks 5MB per file (JPG, PNG, PDF, ZIP, RAR, TXT)';
                            }
                        });
                    }

                    const toggleBtn = document.getElementById('toggleReplyBtn');
                    const wrapper = document.getElementById('replyBoxWrapper');
                    
                    if(toggleBtn) {
                        toggleBtn.addEventListener('click', function() {
                            if (wrapper.style.maxHeight === '0px' || wrapper.style.maxHeight === '') {
                                wrapper.style.maxHeight = '800px';
                                wrapper.style.opacity = '1';
                                toggleBtn.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Batal';
                                toggleBtn.style.backgroundColor = '#f39c12';
                                toggleBtn.style.color = '#fff';
                            } else {
                                wrapper.style.maxHeight = '0px';
                                wrapper.style.opacity = '0';
                                toggleBtn.innerHTML = '<i class="bi bi-reply-fill me-1"></i> Balas';
                                toggleBtn.style.backgroundColor = '#477aee';
                            }
                        });
                        
                        var quillClient = new Quill('#editor-container', {
                            theme: 'snow',
                            modules: { toolbar: [ [{ 'header': [1, 2, 3, false] }], ['bold', 'italic', 'underline', 'link'], [{'list': 'ordered'}, {'list': 'bullet'}], [{ 'align': [] }], ['blockquote', 'code-block'], ['clean'] ] }
                        });
                        
                        document.getElementById('replyFormClient').onsubmit = function() {
                            var html = document.querySelector('#editor-container .ql-editor').innerHTML;
                            var fileInput = document.getElementById('replyAttachmentsClient');
                            if ((html === '<p><br></p>' || html.trim() === '') && fileInput.files.length === 0) { 
                                alert('Pesan teks atau lampiran tidak boleh kosong sama sekali!'); 
                                return false; 
                            }
                            if (html === '<p><br></p>') html = '';
                            document.getElementById('messageInputClient').value = html;
                        };
                    }
                });
                </script>
                <?php endif; ?>
            </div> <!-- End of main card body -->
        </div> <!-- End of main card -->
        
        <h6 class="fw-bold text-muted mt-4 mb-3 text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;"><i class="bi bi-chat-left-text me-2"></i>Riwayat Percakapan</h6>
        
        <?php while($reply = mysqli_fetch_assoc($replies_query)): 
            $is_admin = !empty($reply['admin_id']);
        ?>
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 6px;">
            <div class="card-body p-3 px-4">
                <div class="d-flex gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-muted border shadow-sm" style="min-width: 38px; width: 38px; height: 38px; background: <?= $is_admin ? '#f0f5ff' : '#f8f9fa' ?>; font-size: 16px;">
                        <?= $is_admin ? '<i class="bi bi-headset text-primary"></i>' : strtoupper(substr($reply['user_name'], 0, 1)) ?>
                    </div>
                    <div class="flex-grow-1" style="min-width: 0;">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <div class="d-flex align-items-center">
                                <h6 class="fw-bold m-0 text-dark text-truncate" style="font-size: 13.5px; max-width: 250px;">
                                    <?= $is_admin ? htmlspecialchars($reply['admin_name']) : htmlspecialchars($reply['user_name']) ?>
                                </h6>
                                <?php if($is_admin): ?>
                                    <span class="badge ms-2" style="background: rgba(71, 122, 238, 0.1); color: #477aee; border: 1px solid rgba(71, 122, 238, 0.3); font-size: 9px; letter-spacing: 0.5px;">SUPPORT STAFF</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted" style="font-size: 11px;"><?= date('d M Y, H:i', strtotime($reply['created_at'])) ?></div>
                        </div>
                        
                        <div class="text-dark ticket-content" style="line-height: 1.5; font-size: 13.5px;">
                            <?= $reply['message'] ?>
                        </div>
                        
                        <?php 
                        $reply_id = $reply['id'];
                        $atts_query = mysqli_query($conn, "SELECT * FROM ticket_attachments WHERE reply_id = '$reply_id'");
                        if(mysqli_num_rows($atts_query) > 0): 
                        ?>
                        <div class="mt-3 pt-2 d-flex flex-wrap gap-2" style="border-top: 1px dashed #e9ecef;">
                            <?php while($att = mysqli_fetch_assoc($atts_query)): 
                                $ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                                $icon = 'bi-file-earmark-text';
                                if(in_array($ext, ['jpg','jpeg','png','gif'])) $icon = 'bi-file-image';
                                elseif($ext == 'pdf') $icon = 'bi-file-pdf';
                                elseif(in_array($ext, ['zip','rar'])) $icon = 'bi-file-zip';
                                $size_kb = number_format($att['file_size'] / 1024, 0) . ' KB';
                            ?>
                            <div class="d-flex align-items-center p-2 rounded shadow-sm bg-white" style="border: 1px solid #e9ecef; width: 280px; transition: border-color 0.2s;" onmouseover="this.style.borderColor='#dee2e6'" onmouseout="this.style.borderColor='#e9ecef'">
                                <div class="d-flex align-items-center justify-content-center rounded" style="width: 36px; height: 36px; background: #f8f9fa;">
                                    <i class="bi <?= $icon ?> text-primary" style="font-size: 18px;"></i>
                                </div>
                                <div class="ms-2 flex-grow-1" style="min-width:0;">
                                    <div class="text-dark text-truncate fw-bold" style="font-size: 11.5px;" title="<?= htmlspecialchars($att['file_name']) ?>"><?= htmlspecialchars($att['file_name']) ?></div>
                                    <div style="font-size: 9.5px; color: #6c757d;"><?= strtoupper($ext) ?> &bull; <?= $size_kb ?></div>
                                </div>
                                <div class="ms-2 d-flex gap-1">
                                    <?php if(in_array($ext, ['jpg','jpeg','png','gif','pdf'])): ?>
                                    <button type="button" onclick="previewAttachment('<?= base_url($att['file_path']) ?>', '<?= $ext ?>', '<?= htmlspecialchars(addslashes($att['file_name'])) ?>')" class="btn btn-sm btn-light border p-1 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" title="Preview"><i class="bi bi-eye"></i></button>
                                    <?php endif; ?>
                                    <a href="<?= base_url($att['file_path']) ?>" download="<?= htmlspecialchars($att['file_name']) ?>" class="btn btn-sm btn-primary p-1 d-flex align-items-center justify-content-center border-0" style="width: 26px; height: 26px;" title="Download"><i class="bi bi-download"></i></a>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
        
    </div>
    <div class="col-lg-4">
        <div class="card border-0 p-4 shadow-sm sticky-top" style="top: 80px; border-radius: 6px; background: #ffffff;">
            
            <table class="table table-borderless mb-0 w-100" style="font-size: 12.5px;">
                <tr class="border-bottom">
                    <td class="px-0 py-2 text-uppercase fw-bold text-muted" style="width: 110px; letter-spacing: 0.5px; font-size: 11px;">Tiket</td>
                    <td class="px-0 py-2 text-dark fw-bold"><?= $ticket['ticket_number'] ?></td>
                </tr>
                <tr class="border-bottom">
                    <td class="px-0 py-2 text-uppercase fw-bold text-muted" style="letter-spacing: 0.5px; font-size: 11px;">Departemen</td>
                    <td class="px-0 py-2 text-dark"><?= htmlspecialchars($ticket['department']) ?></td>
                </tr>
                <tr class="border-bottom">
                    <td class="px-0 py-2 text-uppercase fw-bold text-muted" style="letter-spacing: 0.5px; font-size: 11px;">Status</td>
                    <td class="px-0 py-2">
                        <?php 
                        if($ticket['status'] == 'Open') echo '<span class="badge" style="background-color: rgba(32, 201, 151, 0.1); color: #20c997; border: 1px solid rgba(32, 201, 151, 0.3); border-radius: 4px; padding: 4px 8px;">OPEN</span>';
                        elseif($ticket['status'] == 'Customer-Reply') echo '<span class="badge" style="background-color: rgba(243, 156, 18, 0.1); color: #f39c12; border: 1px solid rgba(243, 156, 18, 0.3); border-radius: 4px; padding: 4px 8px;">REPLY</span>';
                        elseif($ticket['status'] == 'Answered') echo '<span class="badge" style="background-color: rgba(71, 122, 238, 0.1); color: #477aee; border: 1px solid rgba(71, 122, 238, 0.3); border-radius: 4px; padding: 4px 8px;">ANSWERED</span>';
                        else echo '<span class="badge" style="background-color: #f1f2f6; color: #576574; border: 1px solid #dcdde1; border-radius: 4px; padding: 4px 8px;">CLOSED</span>';
                        ?>
                    </td>
                </tr>
                <tr class="border-bottom">
                    <td class="px-0 py-2 text-uppercase fw-bold text-muted" style="letter-spacing: 0.5px; font-size: 11px;">Dibuat</td>
                    <td class="px-0 py-2 text-dark"><?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?></td>
                </tr>
                <tr>
                    <td class="px-0 py-2 text-uppercase fw-bold text-muted" style="letter-spacing: 0.5px; font-size: 11px;">L. Update</td>
                    <td class="px-0 py-2 text-dark">
                        <?php 
                        $lr_query = mysqli_query($conn, "SELECT created_at FROM ticket_replies WHERE ticket_id = '{$ticket['id']}' ORDER BY id DESC LIMIT 1");
                        $lr = mysqli_fetch_assoc($lr_query);
                        echo $lr ? date('d M Y, H:i', strtotime($lr['created_at'])) : '-';
                        ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>

<!-- Attachment Preview Modal -->
<div class="modal fade" id="attachmentPreviewModalClient" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header d-flex justify-content-between align-items-center p-3 text-white" style="background: #2a3042;">
                <h6 class="modal-title m-0 fw-bold d-flex align-items-center" style="font-size: 13.5px;">
                    <i class="bi bi-zoom-in me-2 text-info"></i> <span id="previewModalTitleClient">Preview</span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="font-size: 10px;"></button>
            </div>
            <div class="modal-body p-0 text-center d-flex justify-content-center align-items-center bg-light" style="min-height: 200px; max-height: 70vh; overflow: auto; background-image: radial-gradient(#dcdde1 1px, transparent 0); background-size: 20px 20px;">
                <div id="previewModalBodyClient" class="w-100 h-100 d-flex justify-content-center align-items-center"></div>
            </div>
        </div>
    </div>
</div>

<script>
function previewAttachment(url, ext, title) {
    document.getElementById('previewModalTitleClient').innerText = title;
    let body = document.getElementById('previewModalBodyClient');
    body.innerHTML = '<div class="spinner-border text-primary" role="status"></div>';
    
    let modal = window.bootstrap ? new bootstrap.Modal(document.getElementById('attachmentPreviewModalClient')) : new bootstrap.Modal(document.getElementById('attachmentPreviewModalClient'));
    modal.show();
    
    setTimeout(() => {
        if(['jpg','jpeg','png','gif'].includes(ext)) {
            body.innerHTML = '<img src="' + url + '" class="img-fluid border shadow-sm" style="max-height: 65vh; object-fit: contain; background: #fff;">';
        } else if(ext === 'pdf') {
            body.innerHTML = '<iframe src="' + url + '" width="100%" height="500px" style="border: none;"></iframe>';
        } else {
            body.innerHTML = '<div class="text-muted p-5 bg-white border rounded shadow-sm m-4"><h5>Preview Tidak Tersedia</h5><p class="mb-0 text-secondary">Format file <b>.' + ext + '</b> tidak didukung untuk ditampilkan langsung di browser.<br>Silakan tekan tombol Download.</p></div>';
        }
    }, 300);
}
</script>

<?php include __DIR__ . '/../../library/footer.php'; ?>
