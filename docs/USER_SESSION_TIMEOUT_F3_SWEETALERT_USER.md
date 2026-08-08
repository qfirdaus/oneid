# User Session Timeout F3 — SweetAlert User

**Tarikh:** 7 Ogos 2026  
**Status:** COMPLETE / STAGING ACTIVATED AND UAT PASSED

**UAT correction 8 Ogos 2026:** Ujian masa sebenar pertama menemui modal tukar
kata laluan yang tersembunyi tetapi masih mempunyai class Bootstrap `in`.
Controller terdahulu menganggap class tersebut sebagai dialog aktif dan menahan
popup sesi. Pengesanan kini turut mensyaratkan dialog benar-benar visible,
bersaiz dan bukan `aria-hidden=true`.

## Skop

Fasa 3 menambah presentation controller kepada:

- `page/dashboard.php` untuk pengguna authenticated;
- `page/user_mfa_security.php` untuk Account Security authenticated.

Controller tidak dimuatkan pada Administrator dashboard, Admin Step-Up,
challenge MFA pra-login, QR/image endpoint atau aplikasi service provider.
Dashboard yang sedang memaksa initial password change memuatkan konfigurasi
controller dalam keadaan disabled bagi mengelakkan konflik modal keselamatan.

## Aktivasi

Default committed ialah fail-closed:

```php
'ONEID_USER_SESSION_WARNING_ENABLED' => 'false'
```

Untuk UAT, tambah atau ubah nilai berikut dalam `.private/runtime.php`:

```php
'ONEID_USER_SESSION_WARNING_ENABLED' => 'true',
```

Kemudian validasi dan reload PHP-FPM. Rollback segera boleh dibuat dengan
mengembalikan nilai kepada `false`; endpoint Fasa 2 kekal selamat tetapi tiada
controller atau popup aktif.

## Behavior

1. Controller mengambil `effective_remaining_seconds` daripada endpoint status.
2. Tiada status interval berkala yang menghidupkan session.
3. Pada dua minit terakhir, controller revalidate dengan server sebelum membuka
   popup.
4. Countdown menggunakan deadline server sebenar.
5. **Kekal Bersambung / Stay Connected** memanggil endpoint renew Fasa 2.
6. **Tamatkan Sesi OneID / End OneID Session** menutup portal tanpa revoke token.
7. Apabila masa menjadi sifar, endpoint expiry dipanggil dan mesej tamat
   dipaparkan sebelum kembali ke landing OneID.
8. Kejayaan renewal kekal terbuka sehingga pengguna menekan `OK`.

Popup menyatakan dengan jelas bahawa aplikasi lain yang sedang digunakan tidak
ditutup oleh portal expiry ini.

## Keadaan Khas

- `BroadcastChannel` dan fallback `localStorage` menyelaraskan renewal/expiry
  antara tab OneID;
- tab yang kembali visible dan halaman daripada back-forward cache membuat
  revalidation authoritative;
- popup tidak menggantikan SweetAlert atau modal Bootstrap yang sedang aktif;
- password dan input OTP dibersihkan ketika portal tamat;
- `USER_SESSION_EXPIRED`, `SSO_TOKEN_REVOKED` dan `ACCOUNT_INACTIVE` dianggap
  terminal secara berbeza;
- `CSRF_INVALID` membuat satu controlled page revalidation sahaja;
- network/5xx tidak dianggap sebagai expiry dan dicuba semula selepas 15 saat;
- controller user tidak dimuatkan bersama controller Administrator.

## Aset dan Locale

- `public/dist/js/oneid-user-session.js`
- `public/dist/css/oneid-user-session.css`
- `lib/user_session_presentation.php`
- `config/locales/ms.php`
- `config/locales/en.php`

Popup mempunyai z-index khusus melepasi modal dashboard, layout mobile, countdown
tabular dan button yang boleh digunakan dengan keyboard melalui SweetAlert.

## Automated Contract

```bash
php tests/characterization/user_session_timeout_f3_presentation.php
```

Keputusan source: `RESULT checks=20 failed=0`.

## UAT

- setting 30 minit: popup sekitar minit ke-28;
- setting 1 jam: popup sekitar minit ke-58;
- Stay Connected menyambung idle mengikut setting dan memerlukan `OK`;
- End OneID Session membersihkan portal tetapi aplikasi lain kekal aktif;
- biarkan countdown sifar dan sahkan redirect terkawal;
- uji dua tab, background tab dan laptop sleep/wake;
- uji BM serta English;
- uji desktop dan mobile;
- uji FAQ/Bootstrap modal, favourite/launch SSO dan Account Security;
- sahkan tiada popup user pada Administrator dashboard atau MFA challenge;
- sahkan offline/5xx tidak menyebabkan logout automatik.

Fasa 4 masih bertanggungjawab menggantikan reload-on-error heartbeat lama pada
dashboard dan mengemaskan routing error legacy tersebut.
