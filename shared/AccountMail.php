<?php
declare(strict_types=1);

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

function send_account_link(string $email, string $subject, string $url): void
{
    $host = getenv('SMTP_HOST');
    $from = getenv('SMTP_FROM');
    if (!$host || !$from) {
        throw new RuntimeException('SMTP_HOST and SMTP_FROM are required.');
    }
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->Port = (int) (getenv('SMTP_PORT') ?: 587);
    $mail->SMTPAuth = (getenv('SMTP_USER') ?: '') !== '';
    $mail->Username = getenv('SMTP_USER') ?: '';
    $mail->Password = getenv('SMTP_PASSWORD') ?: '';
    $security = getenv('SMTP_SECURITY') ?: 'tls';
    if (!in_array($security, ['tls', 'ssl'], true)) {
        throw new RuntimeException('SMTP_SECURITY must be tls or ssl.');
    }
    $mail->SMTPSecure = $security;
    $mail->setFrom($from, 'Smart Assets');
    $mail->addAddress($email);
    $mail->Subject = $subject;
    $isVerification = $subject === 'Verify your Smart Assets account';
    $heading = $isVerification ? 'Welcome to Smart Assets!' : 'Reset your Smart Assets password';
    $instruction = $isVerification ? 'Please use the link below to verify your email address.' : 'Please use the link below to choose a new password.';
    $button = $isVerification ? 'Verify My Email Address' : 'Reset My Password';
    $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mail->isHTML(true);
    $mail->Body = '<h3>' . $heading . '</h3><p>' . $instruction . '</p>'
        . '<p><a href="' . $safeUrl . '" style="background:#0076ff;color:#fff;padding:10px;text-decoration:none;border-radius:4px;display:inline-block">'
        . $button . '</a></p>';
    $mail->AltBody = "$heading\n\n$instruction\n$url";
    $mail->send();
}
