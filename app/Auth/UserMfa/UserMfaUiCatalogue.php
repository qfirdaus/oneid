<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

final class UserMfaUiCatalogue
{
    /** @return array<string, string> */
    public static function forLocale(string $locale): array
    {
        $catalogues = [
            'ms' => [
                'skip' => 'Langkau ke kandungan utama',
                'title.challenge' => 'Pengesahan tambahan',
                'title.security' => 'Keselamatan Akaun',
                'title.admin' => 'Konfigurasi MFA Pengguna',
                'title.recovery' => 'Pemulihan faktor pengguna',
                'factor.legend' => 'Pilih kaedah pengesahan',
                'factor.email' => 'Kod OTP melalui e-mel',
                'factor.totp' => 'Microsoft Authenticator',
                'factor.unavailable' => 'Microsoft Authenticator tidak tersedia. Gunakan OTP e-mel.',
                'email.sent' => 'Jika transaksi ini sah, kod telah dihantar ke alamat e-mel berdaftar.',
                'email.label' => 'Kod OTP 6 digit',
                'email.hint' => 'Masukkan enam digit tanpa ruang.',
                'email.resend' => 'Hantar semula kod',
                'totp.label' => 'Kod Microsoft Authenticator 6 digit',
                'totp.enroll' => 'Daftar Microsoft Authenticator',
                'totp.revoke' => 'Batalkan Microsoft Authenticator',
                'totp.qr' => 'Kod QR pendaftaran Microsoft Authenticator',
                'totp.qr_help' => 'Imbas kod QR dengan aplikasi Microsoft Authenticator, kemudian masukkan kod 6 digit.',
                'preference' => 'Kaedah pilihan',
                'submit.verify' => 'Sahkan',
                'submit.save' => 'Simpan',
                'submit.confirm' => 'Sahkan pendaftaran',
                'state.loading' => 'Sedang memproses. Sila tunggu.',
                'state.empty' => 'Tiada Microsoft Authenticator yang aktif.',
                'state.success' => 'Perubahan berjaya disimpan.',
                'state.error' => 'Permintaan tidak dapat diselesaikan. Sila cuba semula atau gunakan kaedah lain.',
                'recovery.safe' => 'Permintaan pemulihan akan disemak melalui saluran rasmi. Tiada kod atau rahsia akan diminta.',
                'admin.mode' => 'Mod User MFA',
                'admin.email' => 'OTP e-mel (wajib apabila MFA aktif)',
                'admin.totp' => 'Benarkan Microsoft Authenticator',
                'admin.reason' => 'Sebab perubahan',
                'admin.reference' => 'Rujukan kelulusan',
                'admin.not_authorized' => 'Pengaktifan global belum dibenarkan.',
            ],
            'en' => [
                'skip' => 'Skip to main content',
                'title.challenge' => 'Additional verification',
                'title.security' => 'Account Security',
                'title.admin' => 'User MFA Configuration',
                'title.recovery' => 'User factor recovery',
                'factor.legend' => 'Choose a verification method',
                'factor.email' => 'One-time code by e-mail',
                'factor.totp' => 'Microsoft Authenticator',
                'factor.unavailable' => 'Microsoft Authenticator is unavailable. Use e-mail OTP.',
                'email.sent' => 'If this transaction is valid, a code has been sent to the registered e-mail address.',
                'email.label' => '6-digit one-time code',
                'email.hint' => 'Enter six digits without spaces.',
                'email.resend' => 'Resend code',
                'totp.label' => '6-digit Microsoft Authenticator code',
                'totp.enroll' => 'Set up Microsoft Authenticator',
                'totp.revoke' => 'Remove Microsoft Authenticator',
                'totp.qr' => 'Microsoft Authenticator enrollment QR code',
                'totp.qr_help' => 'Scan the QR code with Microsoft Authenticator, then enter the 6-digit code.',
                'preference' => 'Preferred method',
                'submit.verify' => 'Verify',
                'submit.save' => 'Save',
                'submit.confirm' => 'Confirm enrollment',
                'state.loading' => 'Processing. Please wait.',
                'state.empty' => 'No active Microsoft Authenticator.',
                'state.success' => 'The change was saved.',
                'state.error' => 'The request could not be completed. Try again or use another method.',
                'recovery.safe' => 'Recovery requests are reviewed through an official channel. No code or secret will be requested.',
                'admin.mode' => 'User MFA mode',
                'admin.email' => 'E-mail OTP (required while MFA is active)',
                'admin.totp' => 'Allow Microsoft Authenticator',
                'admin.reason' => 'Change reason',
                'admin.reference' => 'Approval reference',
                'admin.not_authorized' => 'Global activation has not been authorized.',
            ],
        ];
        return $catalogues[in_array($locale, ['ms', 'en'], true) ? $locale : 'ms'];
    }
}
