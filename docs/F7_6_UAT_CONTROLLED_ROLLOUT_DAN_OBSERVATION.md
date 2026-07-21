# F7.6 — UAT, Controlled Rollout dan Observation

**Change ID:** `ONEID-F7-2FA-20260720-01`  
**Owner, pilot, tester, monitoring dan rollback owner:** `0530-09`  
**Environment:** OneID UAT  
**Status:** ACCEPTED / CLOSED — CONTINUOUS OPERATIONAL MONITORING

## 1. Keputusan semasa

Functional UAT untuk pilot tunggal lulus pada 20 Julai 2026. Feature
`admin_2fa_enabled` kekal ON dan rollout kekal dibataskan kepada owner/pilot
`0530-09`. Observation utama 24 jam yang bermula daripada controlled bootstrap
pada `2026-07-20 19:11:29 MYT` selesai tanpa hard-stop.

Pada 21 Julai 2026, owner mengesahkan semakan UI dan fungsi lulus serta menerima
F7.6. Keperluan enhanced monitoring tujuh hari dikecualikan sebagai exit gate
atas keputusan owner; pemantauan tidak dihentikan dan diteruskan sebagai proses
operasi berkala.

Semasa observation, kawalan konfigurasi tempoh grant ditambah dengan default
live kekal 15 minit. Perubahan ini tidak mengubah atau memanjangkan grant sedia
ada. Contract khusus dan regresi F7.2–F7.5 lulus; final gate perlu menggunakan
snapshot pada code baseline semasa.

Arahan snapshot read-only:

```bash
php tools/f7_6_uat_snapshot.php
```

Final gate selepas 24 jam:

```bash
php tools/f7_6_uat_snapshot.php --final
```

`--final` mesti gagal sebelum 24 jam atau apabila mana-mana functional/runtime
gate gagal. Ia tidak membuat mutation.

## 2. Bukti manual owner UAT

| Ujian | Keputusan | Bukti/nota |
|---|---|---|
| Bootstrap terkawal | PASS | Event `ADMIN_2FA_BOOTSTRAP_ENABLED`; configuration version 4 |
| OTP e-mel diterima di mailbox rasmi | PASS | SMTP accepted dan owner mengesahkan mailbox delivery |
| OTP salah | PASS | Ditolak tanpa grant; audit `invalid_code` |
| Resend cooldown | PASS | Klik berulang ditolak `STEP_UP_RESEND_COOLDOWN` |
| OTP e-mel `ADMIN_ACCESS` | PASS | Dashboard Administrator boleh dimasuki |
| OTP e-mel `SECURITY_CONFIGURATION_CHANGE` | PASS | Purpose berasingan dan enrollment/configuration dibenarkan selepas verification |
| Enrollment Microsoft Authenticator | PASS | Secret encrypted, QR lokal, confirmation code diterima |
| Reset dan enrollment semula | PASS | Faktor ACTIVE/PENDING lama direvoke secara beraudit; QR baharu berjaya |
| QR berjenama UPNM | PASS | Owner mengesahkan QR dengan logo tengah berjaya diimbas |
| TOTP `ADMIN_ACCESS` | PASS | Kod 30 saat diterima dan dashboard boleh dimasuki |
| Grant 15 minit dan loader entry | PASS | Grant sah digunakan semula tanpa flash halaman challenge |
| Session/CSRF rotation | PASS selepas pembetulan | Semua grant aktif bagi exact session/browser dimigrasi tanpa mencampur purpose |
| Preference per-admin | PASS selepas pembetulan | Pertukaran e-mel/TOTP melalui Configuration → Admin 2FA dan step-up exact-purpose |
| Logout/login semula | PASS | Step-up diminta semula selepas authentication lifecycle baharu |
| UI desktop | PASS | Owner menerima flow, reka bentuk, e-mel dan guideline enrollment |

## 3. Bukti automated dan live snapshot

Regression yang sesuai selepas activation:

- F7.1 foundation/crypto: 14/14 PASS;
- F7.2 email OTP service: 15/15 PASS;
- F7.2 persistence rollback: PASS, 0 persistent mutation;
- F7.3 RFC 6238/Base32/anti-replay: 10/10 PASS;
- F7.3 lifecycle/recovery dan persistence rollback: PASS;
- F7.4 authorization persistence: PASS;
- F7.4 direct bypass: 5/5 PASS;
- F7.4 server enforcement: 13/13 PASS;
- F7.5 UI/bootstrap: 9/9 PASS; dan
- F7.5 entry loader/grant reuse: 7/7 PASS.

Kontrak `f7_1_live_schema_contract.php` mempunyai dua assertion pra-activation
yang sengaja menjangka feature OFF dan store kosong. Ia tidak lagi digunakan
sebagai F7.6 regression selepas pilot ON; pelaksanaan semasa menggantikannya
dengan `f7_6_uat_snapshot.php`. Percubaan menjalankannya pada 20 Julai adalah
read-only dan tidak menghasilkan mutation.

Snapshot pertama pada kira-kira 23:32 MYT merekod:

- feature ON, configuration version 4;
- satu admin pilot aktif dengan e-mel sah;
- satu TOTP ACTIVE dan sifar PENDING;
- preference sah;
- empat table dan 17 audit event F7 lengkap;
- bukti e-mel verified/rejected/rate-limited, TOTP confirmed/verified/revoked,
  preference dan bootstrap semuanya wujud;
- sifar orphan factor/grant aktif;
- sifar `otpauth://` atau `secret=` dalam audit F7;
- keyring wujud di luar project dengan mode `0600`; dan
- status `FUNCTIONAL_PASS_OBSERVATION_ACTIVE`.

Log Nginx yang boleh dibaca tidak mengandungi fatal/uncaught/SQLSTATE berkaitan
F7 pada snapshot ini. Nginx dan PHP 8.3 FPM aktif.

## 4. Skop controlled rollout

Rollout UAT kekal untuk satu individu sahaja. Owner telah menetapkan bahawa
admin kedua tidak terlibat dalam pembangunan atau UAT Fasa 7. Oleh itu:

- ujian dua admin serentak dikecualikan melalui keputusan owner;
- isolation admin/session/browser diliputi automated persistence dan
  direct-bypass contracts;
- sistem secara teknikal menyimpan factor, preference, challenge dan grant
  mengikut canonical admin ID;
- peluasan kepada admin lain memerlukan change/acceptance baharu; dan
- keputusan ini tidak dibawa terus ke production.

Ujian mutation menggunakan maksimum satu Apply bagi setiap confirmation.
Scheduler SC5 dan Cron External Sync tidak termasuk dalam rollout F7.6.

## 5. Monitoring 24 jam dan operasi berterusan

Observation wajib 24 jam telah selesai. Selepas acceptance, owner menjalankan
snapshot mengikut keperluan operasi, sekurang-kurangnya:

1. selepas functional UAT;
2. pada atau selepas 24 jam;
3. selepas perubahan configuration, factor, keyring atau authentication code;
4. secara berkala mengikut operasi; dan
5. segera selepas laporan lockout, kegagalan SMTP/TOTP atau HTTP 5xx.

Perhatikan:

- kenaikan `ADMIN_2FA_FAILED`, purpose mismatch, replay dan rate limit;
- `ADMIN_2FA_DELIVERY_FAILED` atau e-mel tidak diterima;
- `ADMIN_TOTP_FAILED`, decrypt/key-version error atau clock drift;
- admin tanpa e-mel sah atau TOTP ACTIVE;
- PENDING factor yang tidak selesai;
- orphan factor/grant dan secret material dalam audit;
- PHP-FPM, Nginx dan database fatal/error; dan
- kegagalan masuk admin, configuration save atau grant reuse.

Threshold hard-stop pilot:

- sebarang bypass server-side atau grant cross-admin/session/browser/purpose;
- secret/OTP/URI muncul dalam log atau database audit;
- owner terkunci daripada kedua-dua e-mel dan TOTP;
- decrypt/keyring failure;
- repeated HTTP 5xx atau database error pada authentication; atau
- rollback tidak boleh dijalankan secara terkawal.

## 6. Rollback decision

Jika hard-stop berlaku:

1. hentikan ujian mutation;
2. rekod masa, reason dan correlation ID tanpa OTP/secret;
3. set `admin_2fa_enabled=0` melalui transaksi/audit yang diluluskan;
4. revoke challenge dan grant terbuka;
5. kekalkan encrypted factor row untuk forensic/possible recovery;
6. sahkan baseline admin access ketika feature OFF;
7. jangan jalankan schema-down kecuali restore/schema rollback diluluskan; dan
8. gunakan backup/restore evidence `storage/backups/S4D-20260720-160133/EVIDENCE.txt`
   jika rollback data/schema diperlukan.

Rollback biasa ialah feature rollback, bukan pemadaman keyring atau factor.
Pemadaman factor/keyring boleh menyebabkan recovery mustahil dan memerlukan
change berasingan.

## 7. Exit gate F7.6 — ditutup

Final gate ditutup selepas:

- `php tools/f7_6_uat_snapshot.php --final` lulus;
- 24 jam observation tanpa hard-stop;
- owner mengesahkan tiada lockout atau regression kritikal;
- owner merekod keputusan eksplisit `ACCEPT F7.6`; dan
- owner menukar monitoring tujuh hari daripada exit gate kepada continuous
  operational monitoring.

Final snapshot pada `2026-07-21 23:05:21 MYT` merekod observation selama 27.90
jam, semua pemeriksaan `PASS` dan `F7.6_STATUS ACCEPT_READY`. Owner kemudian
menerima hasil tersebut. Status akhir ialah **ACCEPTED / CLOSED**.

Controlled Active-Session Revocation dan Revocation Scheduler tidak termasuk
dalam acceptance ini. Kedua-duanya direkod sebagai task berasingan dalam
`docs/AS3_CONTROLLED_ACTIVE_SESSION_REVOCATION_BACKLOG.md` dan
`docs/SC8_REVOCATION_SCHEDULER_DECISION_BACKLOG.md`.
