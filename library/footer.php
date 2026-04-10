<?php
// Load site settings from DB for footer
$_footer_settings = [];
if (isset($conn)) {
    $_fs = @mysqli_query($conn, "SELECT site_name, contact_email, contact_whatsapp, contact_phone, address FROM settings LIMIT 1");
    if ($_fs) $_footer_settings = mysqli_fetch_assoc($_fs) ?? [];
}
$_f_name  = htmlspecialchars($_footer_settings['site_name'] ?? 'SobatHosting');
$_f_email = htmlspecialchars($_footer_settings['contact_email'] ?? '');
$_f_wa    = htmlspecialchars($_footer_settings['contact_whatsapp'] ?? '');
?>
    </div> <!-- End of content-container -->

    <!-- Footer -->
    <footer class="mt-auto py-3 px-4 bg-white" style="border-top: 1px solid var(--border-color); font-size: 0.75rem; color: #888888;">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div>
                <?= date("Y") ?> &copy; <?= $_f_name ?> &mdash; All rights reserved.
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if($_f_email): ?>
                <a href="mailto:<?= $_f_email ?>" class="text-muted text-decoration-none hover-blue" style="font-size:0.75rem;">
                    <i class="bi bi-envelope me-1"></i><?= $_f_email ?>
                </a>
                <?php endif; ?>
                <?php if($_f_wa): ?>
                <a href="https://wa.me/<?= preg_replace('/\D/','',$_f_wa) ?>" target="_blank" class="text-muted text-decoration-none hover-blue" style="font-size:0.75rem;">
                    <i class="bi bi-whatsapp me-1"></i><?= $_f_wa ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </footer>
</div> <!-- End of main-content -->

<style>
    .hover-blue:hover { color: #007bff !important; }
</style>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
