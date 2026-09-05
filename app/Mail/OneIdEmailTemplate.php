<?php

declare(strict_types=1);

namespace OneId\App\Mail;

use InvalidArgumentException;

require_once dirname(__DIR__, 2) . '/lib/locale.php';

final class OneIdEmailTemplate
{
    public static function otp(
        string $displayName,
        string $contextLabel,
        string $badge,
        string $headline,
        string $introduction,
        string $otp,
        ?string $validity = null,
        string $locale = 'ms'
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
            $validity ?? \oneid_translate('email.otp.validity', [], $locale),
            '<strong>' . self::escape(\oneid_translate('email.otp.warning', [], $locale)) . '</strong>',
            $locale
        );
    }

    public static function deliveryTest(string $displayName, string $locale = 'ms'): string
    {
        return self::render(
            $displayName,
            \oneid_translate('email.test.context', [], $locale),
            \oneid_translate('email.test.badge', [], $locale),
            \oneid_translate('email.test.headline', [], $locale),
            \oneid_translate('email.test.intro', [], $locale),
            null,
            null,
            '<strong>' . self::escape(\oneid_translate('email.test.notice', [], $locale)) . '</strong>',
            $locale
        );
    }

    public static function notice(
        string $displayName,
        string $contextLabel,
        string $badge,
        string $headline,
        string $introduction,
        string $notice,
        string $locale = 'ms'
    ): string {
        return self::render(
            $displayName,
            $contextLabel,
            $badge,
            $headline,
            $introduction,
            null,
            null,
            '<strong>' . self::escape($notice) . '</strong>',
            $locale
        );
    }

    /** @param array<string, string> $details */
    public static function notification(
        string $displayName,
        string $contextLabel,
        string $badge,
        string $headline,
        string $introduction,
        array $details,
        string $notice,
        string $locale = 'ms'
    ): string {
        $userRows = '';
        $technicalRows = '';
        foreach ($details as $label => $value) {
            $label = trim((string) $label);
            $value = trim((string) $value);
            if ($label === '' || $value === '') {
                continue;
            }
            $row = '<tr><td style="padding:9px 12px;border-bottom:1px solid #e5edf3;'
                . 'font-size:12px;font-weight:700;color:#6b7280;vertical-align:top;width:28%">'
                . self::escape($label) . '</td><td style="padding:9px 12px;border-bottom:1px solid #e5edf3;'
                . 'font-size:13px;font-weight:600;color:#172033;vertical-align:top">'
                . self::escape($value) . '</td></tr>';
            $isTechnical = preg_match('/(?:correlation|korelasi|diagnostic|diagnostik|result code|kod keputusan|header id|id header|reference|rujukan)/i', $label) === 1;
            $isTechnical ? $technicalRows .= $row : $userRows .= $row;
        }
        $section = static function (string $title, string $rows, bool $technical = false): string {
            if ($rows === '') return '';
            $color = $technical ? '#64748b' : '#087ca8';
            return '<div style="margin-top:18px;font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:' . $color . '">' . self::escape($title) . '</div>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:7px;border:1px solid #dce8ef;border-radius:8px">' . $rows . '</table>';
        };
        $detailsHtml = $section($locale === 'en' ? 'Information for you' : 'Maklumat untuk anda', $userRows)
            . $section($locale === 'en' ? 'Technical reference for support' : 'Rujukan teknikal untuk sokongan', $technicalRows, true);
        return self::render(
            $displayName,
            $contextLabel,
            $badge,
            $headline,
            '<div style="padding:14px 16px;background:#eef8fc;border-left:4px solid #11a8d8;border-radius:7px;color:#294b5c"><strong>'
                . self::escape($locale === 'en' ? 'What happened' : 'Apa yang berlaku') . '</strong><br>'
                . self::escape($introduction) . '</div>' . $detailsHtml,
            null,
            null,
            '<strong>' . self::escape($notice) . '</strong>',
            $locale,
            true,
            true
        );
    }

    public static function otpPlainText(string $headline, string $otp, string $locale = 'ms'): string
    {
        if (preg_match('/\A[0-9]{6}\z/', $otp) !== 1) {
            throw new InvalidArgumentException('EMAIL_OTP_INVALID');
        }
        return $headline . ': ' . $otp . '. '
            . \oneid_translate('email.otp.validity', [], $locale) . '. '
            . \oneid_translate('email.otp.warning', [], $locale);
    }

    public static function deliveryTestPlainText(string $locale = 'ms'): string
    {
        return \oneid_translate('email.test.plain', [], $locale);
    }

    private static function render(
        string $displayName,
        string $contextLabel,
        string $badge,
        string $headline,
        string $introduction,
        ?string $otp,
        ?string $validity,
        string $noticeHtml,
        string $locale = 'ms',
        bool $introductionContainsSafeHtml = false,
        bool $showUpnmLogo = false
    ): string {
        $rawName = trim($displayName) !== ''
            ? trim($displayName)
            : \oneid_translate('email.default_user', [], $locale);
        $context = self::escape($contextLabel);
        $safeBadge = self::escape($badge);
        $safeHeadline = self::escape($headline);
        $intro = $introductionContainsSafeHtml ? $introduction : self::escape($introduction);
        $safeOtp = $otp === null ? null : self::escape($otp);
        $safeValidity = $validity === null ? null : self::escape($validity);
        $codeBlock = $safeOtp === null ? ''
            : '<tr><td align="center" style="padding:12px 34px 22px"><div style="padding:22px 18px;border:1px solid #e3e8ef;border-radius:12px;background:#f7f9fc">'
            . '<div style="font-size:11px;font-weight:700;letter-spacing:1.4px;color:#7b8494;text-transform:uppercase">' . self::escape(\oneid_translate('email.otp.label', [], $locale)) . '</div>'
            . '<div style="margin-top:9px;font-family:Consolas,Monaco,monospace;font-size:38px;line-height:46px;font-weight:700;letter-spacing:10px;color:#172033">' . $safeOtp . '</div>'
            . '<div style="margin-top:8px;font-size:13px;color:#087ca8;font-weight:700">' . $safeValidity . '</div></div></td></tr>';

        $brandLogo = $showUpnmLogo
            ? '<td width="58" style="width:58px;padding:0 14px 0 0;vertical-align:middle"><img src="cid:oneid-upnm-logo" width="58" alt="UPNM" style="display:block;width:58px;max-width:58px;height:auto;border:0"></td>'
            : '';

        return '<!doctype html><html lang="' . self::escape($locale) . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $safeHeadline . '</title></head>'
            . '<body style="margin:0;padding:0;background:#eef2f7;font-family:Arial,Helvetica,sans-serif;color:#172033">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7"><tr><td align="center" style="padding:32px 12px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:760px;background:#fff;border:1px solid #dfe6ef;border-radius:14px;overflow:hidden">'
            . '<tr><td style="height:6px;background:linear-gradient(90deg,#123f6d,#087ca8,#11a8d8);font-size:0">&nbsp;</td></tr>'
            . '<tr><td style="padding:28px 34px 22px;border-bottom:1px solid #edf0f4"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
            . '<td><table role="presentation" cellpadding="0" cellspacing="0"><tr>' . $brandLogo
            . '<td style="vertical-align:middle"><div style="font-size:25px;font-weight:800;letter-spacing:-.5px;color:#172033">OneID<span style="color:#087ca8">@UPNM</span></div>'
            . '<div style="margin-top:5px;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#6b7280">' . $context . '</div></td></tr></table></td>'
            . '<td align="right"><div style="display:inline-block;padding:9px 12px;border-radius:20px;background:#e8f6fb;color:#086b91;font-size:12px;font-weight:700">' . $safeBadge . '</div></td>'
            . '</tr></table></td></tr>'
            . '<tr><td style="padding:32px 34px 16px"><div style="font-size:22px;font-weight:700;color:#172033">' . $safeHeadline . '</div>'
            . '<p style="margin:14px 0 0;font-size:15px;line-height:24px;color:#4b5563">' . self::escape(\oneid_translate('email.greeting', ['name' => $rawName], $locale)) . '<br>' . $intro . '</p></td></tr>'
            . $codeBlock
            . '<tr><td style="padding:0 34px 28px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff8e7;border-left:4px solid #e3a008;border-radius:6px"><tr><td style="padding:14px 16px;font-size:13px;line-height:20px;color:#5f4b16">' . $noticeHtml . '</td></tr></table></td></tr>'
            . '<tr><td style="padding:22px 34px;background:#f8fafc;border-top:1px solid #edf0f4;font-size:12px;line-height:19px;color:#737d8c">' . self::escape(\oneid_translate('email.ignore', [], $locale)) . '<br><br><strong style="color:#4b5563">Portal OneID@UPNM</strong><br>Pusat Teknologi Maklumat &amp; Komunikasi, UPNM<br>ask.oneid@upnm.edu.my</td></tr>'
            . '</table><div style="max-width:760px;padding:16px 8px 0;text-align:center;font-size:11px;line-height:17px;color:#8a94a3">' . self::escape(\oneid_translate('email.automated', [], $locale)) . '</div>'
            . '</td></tr></table></body></html>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
