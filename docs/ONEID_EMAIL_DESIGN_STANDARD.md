# Standard Reka Bentuk E-mel OneID@UPNM

**Tarikh inventori:** 20 Julai 2026  
**Status:** IMPLEMENTED / AUTOMATED CONTRACT PASS / MAILBOX VISUAL UAT PENDING

## Coverage

Inventori source aktif menemui dua sender PHPMailer dan tiga variasi mesej:

| Flow | Subject | Template standard |
|---|---|---|
| Admin Step-Up OTP | `Kod Pengesahan Administrator OneID@UPNM` | OTP security |
| Forgot Password OTP | `OneID@UPNM - OTP Lupa Kata Laluan` | OTP recovery |
| Password Recovery test | `OneID@UPNM - Ujian Password Recovery` | Delivery test tanpa kod palsu |

Tiada penggunaan PHP `mail()` atau sender aplikasi lain ditemui di luar
PHPMailer library/vendor. Semua flow aktif menggunakan
`app/Mail/OneIdEmailTemplate.php`.

## Standard visual dan kandungan

- table-based layout dengan inline CSS untuk Outlook dan webmail;
- jalur korporat merah, wordmark teks OneID@UPNM dan context/badge khusus;
- headline, greeting dan hierarki maklumat yang konsisten;
- OTP enam digit menonjol, tempoh sah dan single-use;
- amaran jangan kongsi OTP;
- footer `Pusat Teknologi Maklumat & Komunikasi, UPNM`;
- tiada JavaScript, form, iframe, tracking pixel atau remote image;
- semua kandungan dinamik di-HTML-escape;
- plain-text fallback bagi setiap variasi; dan
- e-mel ujian tidak memaparkan `TEST` sebagai OTP.

Template tidak mengubah polisi delivery, OTP expiry, rate limit, challenge,
audit atau SMTP configuration. Sender masih menganggap `send()` sebagai SMTP
accepted, bukan bukti mailbox delivery.

## Gate perubahan akan datang

Sebarang sender baharu mesti:

1. menggunakan `OneIdEmailTemplate` atau menambah variasi selamat padanya;
2. mempunyai subject dan plain-text fallback;
3. tidak memasukkan secret, password, token atau provisioning URI;
4. lulus `php tools/oneid_email_template_contract.php`; dan
5. menjalani visual UAT pada mailbox Outlook UPNM serta paparan telefon.

## Manual visual UAT

Hantar satu e-mel bagi setiap variasi kepada mailbox UAT yang diluluskan dan
semak:

- desktop Outlook/webmail dan telefon;
- OTP tidak terpotong atau wrap;
- nama panjang tidak merosakkan layout;
- Inbox/Junk/Quarantine;
- subject dan footer tepat; dan
- plain-text view masih boleh difahami.

Jangan rekod OTP, alamat penuh penerima atau HTML e-mel sebenar dalam evidence.
