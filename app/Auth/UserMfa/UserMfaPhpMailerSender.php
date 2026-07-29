<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use OneId\App\Mail\OneIdEmailTemplate;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

require_once dirname(__DIR__, 3) . '/lib/src/Exception.php';
require_once dirname(__DIR__, 3) . '/lib/src/PHPMailer.php';
require_once dirname(__DIR__, 3) . '/lib/src/SMTP.php';
require_once dirname(__DIR__, 3) . '/lib/config.php';
require_once dirname(__DIR__, 3) . '/lib/secrets.php';
require_once dirname(__DIR__, 2) . '/Mail/OneIdEmailTemplate.php';
require_once __DIR__ . '/UserMfaEmailSenderInterface.php';

final class UserMfaPhpMailerSender implements UserMfaEmailSenderInterface
{
    public function send(string $otp, string $email, string $displayName, string $locale): bool
    {
        $locale = in_array($locale, ['ms', 'en'], true) ? $locale : 'ms';
        if (preg_match('/\A[0-9]{6}\z/', $otp) !== 1
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            return false;
        }
        $body = OneIdEmailTemplate::otp(
            $displayName,
            \oneid_translate('email.user_mfa.context', [], $locale),
            \oneid_translate('email.user_mfa.badge', [], $locale),
            \oneid_translate('email.user_mfa.headline', [], $locale),
            \oneid_translate('email.user_mfa.intro', [], $locale),
            $otp,
            null,
            $locale
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
            $mail->Subject = \oneid_translate('email.user_mfa.subject', [], $locale);
            $mail->msgHTML($body);
            $mail->AltBody = OneIdEmailTemplate::otpPlainText(
                \oneid_translate('email.user_mfa.headline', [], $locale),
                $otp,
                $locale
            );
            return (bool) $mail->send();
        } catch (Throwable) {
            return false;
        }
    }
}
