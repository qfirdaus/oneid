<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

use PHPMailer\PHPMailer\PHPMailer;
use OneId\App\Mail\OneIdEmailTemplate;

final class ConfiguredOtpDelivery implements OtpDelivery
{
    public function __construct(private readonly array $smtp, private readonly bool $recovery = false) {}
    public function send(string $destination, string $displayName, string $otp): bool
    {
        // Deliberately do not load UserMfaPhpMailerSender: it imports legacy config/session bootstrap.
        foreach (['Exception','PHPMailer','SMTP'] as $file) require_once dirname(__DIR__, 3) . '/lib/src/' . $file . '.php';
        require_once dirname(__DIR__, 2) . '/Mail/OneIdEmailTemplate.php';
        if (!preg_match('/\A\d{6}\z/', $otp) || !filter_var($destination, FILTER_VALIDATE_EMAIL)) return false;
        $mail = new PHPMailer(true);
        $mail->isSMTP(); $mail->SMTPDebug = 0; $mail->Timeout = 10;
        $mail->CharSet = 'UTF-8'; $mail->Encoding = 'base64'; $mail->SMTPAuth = true;
        $mail->Host = (string) $this->smtp['host']; $mail->Port = (int) $this->smtp['port'];
        $mail->SMTPSecure = (string) $this->smtp['encryption'];
        if (!in_array($mail->SMTPSecure, ['tls','ssl'], true)) return false;
        $mail->Username = (string) $this->smtp['username']; $mail->Password = (string) $this->smtp['password'];
        $mail->setFrom($this->smtp['from'], 'OneID UPNM'); $mail->addAddress($destination, $displayName);
        $mail->Subject = $this->recovery ? 'Tetapan semula kata laluan OneID' : 'Kod pengesahan Login dengan OneID';
        $mail->addEmbeddedImage(dirname(__DIR__, 3) . '/public/img/logo_upnm_30.png', 'oneid-upnm-logo');
        $mail->msgHTML(OneIdEmailTemplate::otp($displayName, 'Login dengan OneID', 'Pengesahan identiti',
            $this->recovery ? 'Tetapkan semula kata laluan anda' : 'Sahkan log masuk anda', $this->recovery ? 'Gunakan kod ini untuk menetapkan semula kata laluan OneID. Kod sah selama 5 minit. Jika anda tidak meminta tetapan semula, abaikan emel ini.' : 'Gunakan kod ini untuk meneruskan log masuk aplikasi.', $otp, null, 'ms'));
        $mail->AltBody = 'Kod pengesahan OneID anda: ' . $otp . '. Jangan kongsikan kod ini.';
        return $mail->send();
    }
}
