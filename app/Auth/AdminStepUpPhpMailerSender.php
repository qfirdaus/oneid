<?php

declare(strict_types=1);

namespace OneId\App\Auth;

use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

require_once dirname(__DIR__, 2) . '/lib/src/Exception.php';
require_once dirname(__DIR__, 2) . '/lib/src/PHPMailer.php';
require_once dirname(__DIR__, 2) . '/lib/src/SMTP.php';
require_once dirname(__DIR__, 2) . '/lib/config.php';
require_once dirname(__DIR__, 2) . '/lib/secrets.php';
require_once dirname(__DIR__) . '/Mail/OneIdEmailTemplate.php';
require_once __DIR__ . '/AdminStepUpEmailSenderInterface.php';

final class AdminStepUpPhpMailerSender implements AdminStepUpEmailSenderInterface
{
    public function send(string $otp, string $email, string $displayName): bool
    {
        if (preg_match('/\A[0-9]{6}\z/', $otp) !== 1
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            return false;
        }

        $body = \OneId\App\Mail\OneIdEmailTemplate::otp(
            $displayName,
            'Administrator Security',
            'PENGESAHAN 2FA',
            'Sahkan akses Administrator',
            'Kami menerima permintaan untuk mengakses fungsi keselamatan Administrator OneID. Gunakan kod pengesahan berikut:',
            $otp
        );

        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->isSMTP();
            $mail->SMTPDebug = 0;
            $mail->Timeout = 10;
            $mail->Host = (string) \oneid_config('ONEID_SMTP_HOST');
            $mail->Port = (int) \oneid_config('ONEID_SMTP_PORT');
            $mail->SMTPSecure = (string) \oneid_config('ONEID_SMTP_ENCRYPTION');
            $mail->SMTPAuth = true;
            $mail->Username = \oneid_secret('ONEID_SMTP_USERNAME');
            $mail->Password = \oneid_secret('ONEID_SMTP_PASSWORD');
            $mail->setFrom(
                \oneid_secret('ONEID_SMTP_USERNAME'),
                (string) \oneid_config('ONEID_SMTP_FROM_NAME')
            );
            $mail->addAddress($email, $displayName);
            $mail->Subject = 'Kod Pengesahan Administrator OneID@UPNM';
            $mail->msgHTML($body);
            $mail->AltBody = \OneId\App\Mail\OneIdEmailTemplate::otpPlainText(
                'Kod pengesahan akses Administrator OneID anda',
                $otp
            );
            return (bool) $mail->send();
        } catch (Throwable) {
            return false;
        }
    }
}
