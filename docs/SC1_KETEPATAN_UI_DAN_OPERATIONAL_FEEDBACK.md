# SC1 — Ketepatan UI dan Operational Feedback SSO Configuration

**Tarikh pelaksanaan:** 16 Julai 2026  
**Skop:** Administrator — Authentication & SSO Token Policy  
**Status:** IMPLEMENTED / LOCAL VERIFICATION COMPLETE  
**Database atau backend mutation:** Tiada

## 1. Objektif

Fasa 1 memastikan admin memahami kesan sebenar tiga setting dan menerima
feedback yang jelas semasa membaca atau menyimpan polisi. Fasa ini tidak
mengubah token lifecycle, database schema, endpoint contract atau enforcement
backend.

## 2. Perubahan UI

| Sebelum | Selepas |
|---|---|
| SSO Configuration | Authentication & SSO Token Policy |
| Session timeout | SSO token lifetime |
| Multiple sessions | Allow multiple active SSO tokens |
| OTP email delivery | Send password-reset OTP by email |

Penerangan baharu menjelaskan bahawa:

- editor ini bukan konfigurasi penuh Identity Provider atau service provider;
- token SSO berasingan daripada PHP session 30 minit idle/8 jam absolute;
- mematikan multiple token hanya berkuat kuasa pada login berikutnya dalam
  behavior backend semasa;
- e-mel OTP hanya untuk Forgot Password, bukan login MFA/Admin Step-Up; dan
- token satu minggu serta OTP email OFF mempunyai risiko operasi.

## 3. Operational Feedback

Save flow baharu:

```text
Load current values
    -> simpan client-side baseline
    -> admin mengubah pilihan
    -> jika tiada perubahan: jangan hantar request
    -> bina before/after summary
    -> paparkan confirmation dan warning berkaitan
    -> lock control dan Save button
    -> hantar satu request
    -> bezakan Saved / No changes / Failed / Unexpected result
```

Kawalan yang ditambah:

- Save disabled sehingga nilai semasa berjaya dimuatkan;
- `aria-busy` dan live operational status;
- loading label `Saving...`;
- guard `ssoConfigSaving` untuk mencegah double-submit;
- input dikunci semasa request;
- client-side unchanged detection tanpa request;
- ringkasan nilai lama dan baharu sebelum confirmation;
- warning khusus bagi token satu minggu dan OTP email OFF;
- kegagalan load menyebabkan Save kekal disabled;
- HTTP failure tidak dianggap berjaya; dan
- response selain row count `0` atau `1` dianggap status tidak diketahui.

## 4. Sempadan Fasa

Fasa ini sengaja mengekalkan:

- `lib/q_func.php` dan request payload sedia ada;
- `lib/Database.php` dan untargeted update sedia ada;
- nilai `sys_config` semasa;
- legacy one-hour refresh buffer;
- next-login multiple-token enforcement;
- Forgot Password OTP behavior;
- PHP session policy; dan
- satu role admin `login_user_type = 1`.

Kelemahan validation backend, targeted database update dan audit trail akan
ditangani dalam fasa seterusnya, bukan disembunyikan sebagai perubahan UI.

## 5. Verification

Verification tempatan:

- `php -l admin/dashboard.php`;
- `php tools/sc0_sso_configuration_contract.php`;
- `php tools/sc1_sso_configuration_ui_contract.php`; dan
- `git diff --check`.

Contract SC1 mengesahkan ketepatan istilah, ringkasan/confirmation, loading dan
double-submit guard, empat outcome feedback serta kenyataan bahawa token
lifecycle tidak berubah.

## 6. UAT Manual Dicadangkan

1. Buka tab policy dan sahkan Save disabled ketika loading.
2. Sahkan nilai live `0.5`, `1`, `1` dipaparkan dengan betul.
3. Klik Save tanpa perubahan dan sahkan tiada network update request.
4. Ubah satu setting dan sahkan dialog menunjukkan before/after.
5. Batalkan dialog dan sahkan tiada request dihantar.
6. Pilih satu minggu dan sahkan warning dipaparkan.
7. Matikan OTP email dan sahkan warning Forgot Password dipaparkan.
8. Simpan satu perubahan terkawal dan sahkan butang/control dikunci.
9. Sahkan outcome Saved atau No changes dipaparkan dengan tepat.
10. Simulasikan HTTP failure dan sahkan UI tidak menganggap operasi berjaya.
11. Reload halaman dan sahkan nilai database sebenar dipaparkan.

UAT yang mengubah nilai hendaklah memulihkan nilai baseline selepas ujian:

```text
token_timeout = 0.5
multi_session = 1
email_OTP = 1
```

## 7. Rollback

Rollback Fasa 1 hanya melibatkan:

- pulihkan blok markup/JavaScript/CSS policy dalam `admin/dashboard.php`;
- buang contract dan dokumen SC1 jika perubahan dibatalkan; dan
- jalankan semula contract SC0.

Tiada rollback database diperlukan kerana Fasa 1 tidak melakukan migration atau
mengubah nilai live semasa pelaksanaan.

## 8. Keputusan

Fasa 1 telah dilaksanakan dalam skop UI dan operational feedback. Pelaksanaan
tidak mendakwa menyelesaikan validation backend, audit trail, database singleton
enforcement, revocation atau Admin Step-Up. Perkara tersebut kekal sebagai kerja
fasa berikutnya.
