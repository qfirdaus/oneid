# Standard Reka Bentuk E-mel OneID@UPNM

## Bahasa Melayu

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

## English

**Inventory date:** 20 July 2026
**Status:** IMPLEMENTED / AUTOMATED CONTRACT PASS / MAILBOX VISUAL UAT PENDING

### Coverage

The active source inventory identifies two PHPMailer senders and three message
variations:

| Flow | Subject | Standard template |
|---|---|---|
| Administrator Step-Up OTP | `OneID@UPNM Administrator Verification Code` | OTP security |
| Forgot Password OTP | `OneID@UPNM Password Reset OTP` | OTP recovery |
| Password Recovery test | `OneID@UPNM - Password Recovery Test` | Delivery test without a false code |

No application sender uses PHP `mail()` outside the PHPMailer library/vendor.
All active flows use `app/Mail/OneIdEmailTemplate.php`.

### Visual and content standard

- table-based layout with inline CSS for Outlook and webmail;
- corporate red strip, OneID@UPNM text wordmark and flow-specific context/badge;
- consistent headline, greeting and information hierarchy;
- prominent six-digit OTP with validity and single-use information;
- warning not to share the OTP;
- `Pusat Teknologi Maklumat & Komunikasi, UPNM` footer;
- no JavaScript, form, iframe, tracking pixel or remote image;
- all dynamic content is HTML-escaped;
- plain-text fallback for every variation; and
- test e-mail never displays `TEST` as an OTP.

The template does not alter delivery policy, OTP expiry, rate limiting,
challenge, audit or SMTP configuration. A sender treats `send()` as SMTP
acceptance, not evidence of mailbox delivery.

### Gate for future changes

Every new sender must:

1. use `OneIdEmailTemplate` or add a safe variation to it;
2. provide a subject and plain-text fallback;
3. exclude secrets, passwords, tokens and provisioning URIs;
4. pass `php tools/oneid_email_template_contract.php`; and
5. complete visual UAT in an approved UPNM Outlook mailbox and on mobile.

### Manual visual UAT

Send one e-mail for each variation to an approved UAT mailbox and verify:

- desktop Outlook/webmail and mobile;
- OTP is not truncated or wrapped;
- long names do not break the layout;
- Inbox/Junk/Quarantine placement;
- correct subject and footer; and
- comprehensible plain-text view.

Do not record an OTP, full recipient address or actual e-mail HTML in evidence.
Commands, template identifiers, OTP values and technical identifiers remain
canonical and invariant across both language sections.
