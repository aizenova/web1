<?php
// Manually include PHPMailer classes from PHPMailer-master folder
require_once __DIR__ . '/../PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendConfirmationEmail($to, $confirmation_code, $type = 'register') {
    $mail = new PHPMailer(true);

    try {
        // Debug level
        $mail->SMTPDebug = 0; // Set to 2 for debugging, 0 for production
        $mail->Debugoutput = 'error_log';

        //Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rifqirifky2@gmail.com'; // Ganti dengan email Gmail Anda
        $mail->Password   = 'Rifqiandrian@123'; // Ganti dengan App Password yang sudah dibuat
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        
        // Additional settings for troubleshooting
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        //Recipients
        $mail->setFrom('rifqirifky2@gmail.com', 'Modern Auth System');
        $mail->addAddress($to);
        
        // Character encoding
        $mail->CharSet = 'UTF-8';

        // Content
        $mail->isHTML(true);
        
        if ($type === 'reset_password') {
            $mail->Subject = 'Reset Password Confirmation Code';
            $mail->Body    = '
                <html>
                <body style="font-family: Arial, sans-serif; padding: 20px;">
                    <h2 style="color: #4f46e5;">Reset Password</h2>
                    <p>Anda telah meminta untuk mereset password. Kode konfirmasi Anda adalah:</p>
                    <h3 style="background-color: #f3f4f6; padding: 10px; text-align: center; font-size: 24px;">' . $confirmation_code . '</h3>
                    <p>Masukkan kode ini untuk melanjutkan proses reset password.</p>
                    <p>Jika Anda tidak meminta reset password, abaikan email ini.</p>
                </body>
                </html>';
            $mail->AltBody = "Kode konfirmasi reset password Anda adalah: " . $confirmation_code;
        } else {
            $mail->Subject = 'Email Confirmation Code';
            $mail->Body    = '
                <html>
                <body style="font-family: Arial, sans-serif; padding: 20px;">
                    <h2 style="color: #4f46e5;">Email Verification</h2>
                    <p>Terima kasih telah mendaftar! Kode konfirmasi Anda adalah:</p>
                    <h3 style="background-color: #f3f4f6; padding: 10px; text-align: center; font-size: 24px;">' . $confirmation_code . '</h3>
                    <p>Masukkan kode ini untuk memverifikasi email Anda.</p>
                    <p>Jika Anda tidak mendaftar di sistem kami, abaikan email ini.</p>
                </body>
                </html>';
            $mail->AltBody = "Kode konfirmasi Anda adalah: " . $confirmation_code;
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log detailed error information
        error_log("SMTP ERROR - Sending to: " . $to);
        error_log("Error Message: " . $e->getMessage());
        error_log("Mailer Error: " . $mail->ErrorInfo);
        error_log("Debug Output: " . print_r($mail->SMTPDebug, true));
        return false;
    }
}
?>
