<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use OneId\App\Mail\OneIdEmailTemplate;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

final class UserMfaRecoveryEmailSender
{
    public function sendOtp(
        string $otp,
        string $email,
        string $displayName,
        string $locale
    ): bool {
        if (preg_match('/\A[0-9]{6}\z/', $otp) !== 1) {
            return false;
        }
        return $this->send(
            $email,
            $displayName,
            oneid_translate('email.user_mfa_recovery.subject', [], $locale),
            OneIdEmailTemplate::otp(
                $displayName,
                oneid_translate('email.user_mfa_recovery.context', [], $locale),
                oneid_translate('email.user_mfa_recovery.badge', [], $locale),
                oneid_translate('email.user_mfa_recovery.headline', [], $locale),
                oneid_translate('email.user_mfa_recovery.intro', [], $locale),
                $otp,
                null,
                $locale
            ),
            OneIdEmailTemplate::otpPlainText(
                oneid_translate('email.user_mfa_recovery.headline', [], $locale),
                $otp,
                $locale
            )
        );
    }

    public function sendRevokedNotice(
        string $email,
        string $displayName,
        string $locale
    ): bool {
        $headline = oneid_translate('email.user_mfa_revoked.headline', [], $locale);
        $intro = oneid_translate('email.user_mfa_revoked.intro', [], $locale);
        return $this->send(
            $email,
            $displayName,
            oneid_translate('email.user_mfa_revoked.subject', [], $locale),
            OneIdEmailTemplate::notice(
                $displayName,
                oneid_translate('email.user_mfa_recovery.context', [], $locale),
                oneid_translate('email.user_mfa_revoked.badge', [], $locale),
                $headline,
                $intro,
                oneid_translate('email.user_mfa_revoked.notice', [], $locale),
                $locale
            ),
            $headline . '. ' . $intro . ' '
                . oneid_translate('email.user_mfa_revoked.notice', [], $locale)
        );
    }

    private function send(
        string $email,
        string $displayName,
        string $subject,
        string $html,
        string $plain
    ): bool {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->isSMTP();
            $mail->SMTPDebug = 0;
            $mail->Timeout = 10;
            $mail->Host = (string) oneid_config('ONEID_SMTP_HOST');
            $mail->Port = (int) oneid_config('ONEID_SMTP_PORT');
            $mail->SMTPSecure = (string) oneid_config('ONEID_SMTP_ENCRYPTION');
            $mail->SMTPAuth = true;
            $mail->Username = oneid_secret('ONEID_SMTP_USERNAME');
            $mail->Password = oneid_secret('ONEID_SMTP_PASSWORD');
            $mail->setFrom(
                oneid_secret('ONEID_SMTP_USERNAME'),
                (string) oneid_config('ONEID_SMTP_FROM_NAME')
            );
            $mail->addAddress($email, $displayName);
            $mail->Subject = $subject;
            $mail->addEmbeddedImage(
                dirname(__DIR__, 3) . '/public/img/logo_upnm_30.png',
                'oneid-upnm-logo',
                'logo_upnm_30.png',
                'base64',
                'image/png'
            );
            $mail->msgHTML($html);
            $mail->AltBody = $plain;
            return (bool) $mail->send();
        } catch (Throwable) {
            return false;
        }
    }
}
