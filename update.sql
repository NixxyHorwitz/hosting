USE hosting;

ALTER TABLE settings ADD COLUMN smtp_host VARCHAR(255) DEFAULT 'smtp.gmail.com';
ALTER TABLE settings ADD COLUMN smtp_port INT DEFAULT 465;
ALTER TABLE settings ADD COLUMN smtp_user VARCHAR(255) DEFAULT '';
ALTER TABLE settings ADD COLUMN smtp_pass VARCHAR(255) DEFAULT '';
ALTER TABLE settings ADD COLUMN smtp_from_name VARCHAR(255) DEFAULT 'SobatHosting';

ALTER TABLE users ADD COLUMN status ENUM('pending', 'active') DEFAULT 'active';
ALTER TABLE users ADD COLUMN otp_code VARCHAR(10) DEFAULT NULL;
ALTER TABLE users ADD COLUMN otp_expiry DATETIME DEFAULT NULL;
ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN reset_expiry DATETIME DEFAULT NULL;

CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    description VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO email_templates (name, subject, body, description) VALUES 
('register_otp', 'Kode OTP Pendaftaran - SobatHosting', '<h2>Halo :nama:,</h2><p>Terima kasih telah mendaftar. Berikut adalah kode OTP Anda: <b>:otp:</b></p><p>Berlaku selama 15 menit.</p>', 'Email pengiriman OTP untuk pendaftaran'),
('order_hosting', 'Detail Akun Hosting Anda - SobatHosting', '<h2>Halo :nama:,</h2><p>Layanan hosting Anda telah aktif.</p><p>Domain: :domain:</p><p>Username cPanel: :username:</p><p>Password cPanel: :password:</p><br><p>Terima kasih!</p>', 'Email yang dikirim setelah hosting berhasil dibuat di WHM'),
('suspend_hosting', 'Pemberitahuan Penangguhan Layanan - SobatHosting', '<h2>Halo :nama:,</h2><p>Layanan hosting Anda dengan domain :domain: telah ditangguhkan (suspend).</p>', 'Email pemberitahuan ketika hosting di-suspend'),
('unsuspend_hosting', 'Pemberitahuan Pengaktifan Kembali Layanan - SobatHosting', '<h2>Halo :nama:,</h2><p>Layanan hosting Anda dengan domain :domain: telah diaktifkan kembali.</p>', 'Email pemberitahuan ketika hosting di-unsuspend'),
('grace_period', 'Peringatan Masa Tenggang - SobatHosting', '<h2>Halo :nama:,</h2><p>Layanan hosting Anda dengan domain :domain: akan segera berakhir.</p><p>Silakan lakukan perpanjangan.</p>', 'Email pengingat sebelum hosting expired'),
('forgot_password', 'Reset Password - SobatHosting', '<h2>Halo :nama:,</h2><p>Silakan klik link berikut untuk mereset password Anda: <a href=":reset_link:">Reset Password</a></p>', 'Email untuk mereset password');
