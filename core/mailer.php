<?php
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmailTemplate($to_email, $to_name, $template_name, $data = []) {
    global $conn;

    // Get SMTP settings
    $q_settings = mysqli_query($conn, "SELECT smtp_host, smtp_port, smtp_user, smtp_pass, smtp_from_name FROM settings WHERE id = 1");
    $settings = mysqli_fetch_assoc($q_settings);

    // Get Template
    $q_template = mysqli_query($conn, "SELECT subject, body FROM email_templates WHERE name = '$template_name' LIMIT 1");
    if (mysqli_num_rows($q_template) == 0) return false;
    $template = mysqli_fetch_assoc($q_template);

    $subject = $template['subject'];
    $body = $template['body'];

    // Replace variables
    foreach ($data as $key => $value) {
        $subject = str_replace(':' . $key . ':', $value, $subject);
        $body = str_replace(':' . $key . ':', $value, $body);
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'];
        $mail->SMTPAuth   = true;
        // Gunakan SMTPSecure sesuai port
        if ($settings['smtp_port'] == 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port       = $settings['smtp_port'];
        $mail->Username   = $settings['smtp_user'];
        $mail->Password   = $settings['smtp_pass'];

        // Recipients
        $mail->setFrom($settings['smtp_user'], $settings['smtp_from_name']);
        $mail->addAddress($to_email, $to_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // file_put_contents('mail_error.log', $mail->ErrorInfo . "\n", FILE_APPEND);
        return false;
    }
}
