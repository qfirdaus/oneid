<?php

declare(strict_types=1);

namespace OneId\App\Mail;

use InvalidArgumentException;

final class OneIdEmailTemplate
{
    public static function otp(
        string $displayName,
        string $contextLabel,
        string $badge,
        string $headline,
        string $introduction,
        string $otp,
        string $validity = 'Sah selama 5 minit | Satu kali penggunaan'
    ): string {
        if (preg_match('/\A[0-9]{6}\z/', $otp) !== 1) {
            throw new InvalidArgumentException('EMAIL_OTP_INVALID');
        }

        return self::render(
            $displayName,
            $contextLabel,
            $badge,
            $headline,
            $introduction,
            $otp,
            $validity,
            '<strong>Jangan kongsikan kod ini.</strong> OneID atau pentadbir UPNM tidak akan meminta kod OTP anda melalui panggilan atau mesej.'
        );
    }

    public static function deliveryTest(string $displayName): string
    {
        return self::render(
            $displayName,
            'Account Recovery',
            'UJIAN E-MEL',
            'Ujian penghantaran berjaya',
            'Ini ialah ujian penghantaran e-mel Password Recovery yang dimulakan oleh pentadbir OneID.',
            null,
            null,
            '<strong>Tiada tindakan diperlukan.</strong> Penerimaan mesej ini mengesahkan pelayan e-mel menerima penghantaran ujian OneID.'
        );
    }

    public static function otpPlainText(string $headline, string $otp): string
    {
        if (preg_match('/\A[0-9]{6}\z/', $otp) !== 1) {
            throw new InvalidArgumentException('EMAIL_OTP_INVALID');
        }
        return $headline . ': ' . $otp
            . ". Kod sah selama 5 minit dan hanya boleh digunakan sekali."
            . ' Jangan kongsikan kod ini.';
    }

    public static function deliveryTestPlainText(): string
    {
        return 'Ujian penghantaran Password Recovery OneID@UPNM berjaya diterima. Tiada tindakan diperlukan.';
    }

    private static function render(
        string $displayName,
        string $contextLabel,
        string $badge,
        string $headline,
        string $introduction,
        ?string $otp,
        ?string $validity,
        string $noticeHtml
    ): string {
        $name = self::escape(trim($displayName) !== '' ? trim($displayName) : 'Pengguna OneID');
        $context = self::escape($contextLabel);
        $safeBadge = self::escape($badge);
        $safeHeadline = self::escape($headline);
        $intro = self::escape($introduction);
        $safeOtp = $otp === null ? null : self::escape($otp);
        $safeValidity = $validity === null ? null : self::escape($validity);
        $codeBlock = $safeOtp === null ? ''
            : '<tr><td align="center" style="padding:12px 34px 22px"><div style="padding:22px 18px;border:1px solid #e3e8ef;border-radius:12px;background:#f7f9fc">'
            . '<div style="font-size:11px;font-weight:700;letter-spacing:1.4px;color:#7b8494;text-transform:uppercase">Kod pengesahan sekali guna</div>'
            . '<div style="margin-top:9px;font-family:Consolas,Monaco,monospace;font-size:38px;line-height:46px;font-weight:700;letter-spacing:10px;color:#172033">' . $safeOtp . '</div>'
            . '<div style="margin-top:8px;font-size:13px;color:#a71930;font-weight:700">' . $safeValidity . '</div></div></td></tr>';

        return '<!doctype html><html lang="ms"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $safeHeadline . '</title></head>'
            . '<body style="margin:0;padding:0;background:#eef2f7;font-family:Arial,Helvetica,sans-serif;color:#172033">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7"><tr><td align="center" style="padding:32px 12px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border:1px solid #dfe6ef;border-radius:14px;overflow:hidden">'
            . '<tr><td style="height:6px;background:#a71930;font-size:0">&nbsp;</td></tr>'
            . '<tr><td style="padding:28px 34px 22px;border-bottom:1px solid #edf0f4"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
            . '<td><div style="font-size:25px;font-weight:800;letter-spacing:-.5px;color:#172033">OneID<span style="color:#a71930">@UPNM</span></div>'
            . '<div style="margin-top:5px;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#6b7280">' . $context . '</div></td>'
            . '<td align="right"><div style="display:inline-block;padding:9px 12px;border-radius:20px;background:#fbecef;color:#8f1529;font-size:12px;font-weight:700">' . $safeBadge . '</div></td>'
            . '</tr></table></td></tr>'
            . '<tr><td style="padding:32px 34px 16px"><div style="font-size:22px;font-weight:700;color:#172033">' . $safeHeadline . '</div>'
            . '<p style="margin:14px 0 0;font-size:15px;line-height:24px;color:#4b5563">Salam ' . $name . ',<br>' . $intro . '</p></td></tr>'
            . $codeBlock
            . '<tr><td style="padding:0 34px 28px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff8e7;border-left:4px solid #e3a008;border-radius:6px"><tr><td style="padding:14px 16px;font-size:13px;line-height:20px;color:#5f4b16">' . $noticeHtml . '</td></tr></table></td></tr>'
            . '<tr><td style="padding:22px 34px;background:#f8fafc;border-top:1px solid #edf0f4;font-size:12px;line-height:19px;color:#737d8c">Jika anda tidak membuat permintaan ini, abaikan e-mel ini dan maklumkan kepada pentadbir sistem.<br><br><strong style="color:#4b5563">Portal OneID@UPNM</strong><br>Pusat Teknologi Maklumat &amp; Komunikasi, UPNM<br>ask.oneid@upnm.edu.my</td></tr>'
            . '</table><div style="max-width:600px;padding:16px 8px 0;text-align:center;font-size:11px;line-height:17px;color:#8a94a3">E-mel automatik OneID. Sila jangan balas e-mel ini.</div>'
            . '</td></tr></table></body></html>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
