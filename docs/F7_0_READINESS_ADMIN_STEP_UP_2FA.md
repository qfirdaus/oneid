# F7.0 Readiness Admin Step-Up 2FA

**Tarikh mula:** 20 Julai 2026  
**Status:** READINESS RECORDED — F7.1 APPLIED/ACCEPTED, REMAINING F7 GATES OPEN  
**Skop:** Read-only discovery, owner decisions dan operational readiness  
**Runtime/schema mutation:** NONE

## 1. Objektif

F7.0 menutup semua prerequisite sebelum schema atau kod runtime 2FA dibina.
Fasa ini tidak memasang table, tidak menambah feature flag dan tidak mengubah
guard, session, e-mel atau Authentication Policy.

Subfasa penuh Fasa 7 ialah:

1. F7.0 — readiness dan keputusan keselamatan;
2. F7.1 — schema dan encryption foundation;
3. F7.2 — challenge serta OTP e-mel;
4. F7.3 — Microsoft Authenticator/TOTP;
5. F7.4 — enforcement server-side;
6. F7.5 — UI, controlled bootstrap dan recovery; dan
7. F7.6 — UAT, monitoring, pilot dan rollout.

## 2. Evidence baseline

| Pemeriksaan | Evidence 20 Julai 2026 | Status |
|---|---|---|
| Guard halaman admin | `oneid_require_admin_page()` memeriksa authenticated dan role sahaja | PASS baseline; 2FA belum ada |
| Halaman admin | `admin/dashboard.php` dan `admin/user_list.php` | INVENTORIED |
| Action gateway | Satu gateway `lib/q_func.php` melalui `oneid_guard_q_func_request()` | PASS baseline |
| Action map | 4 public, 8 user dan 48 admin action | INVENTORIED |
| Purpose awal | `ADMIN_ACCESS`, `SECURITY_CONFIGURATION_CHANGE`, `ACTIVE_SESSION_REVOCATION` | APPROVED IN PRINCIPLE |
| Admin | 6 jumlah; 4 aktif; 1 daripada jumlah admin tiada e-mel | ACTION REQUIRED |
| Database | 19 base table; singleton `sys_config` mempunyai 1 row | PASS baseline |
| Schema 2FA | Tiada `admin_2fa_enabled`, challenge, grant atau factor table | EXPECTED |
| Backup terdahulu | Dump S4D 14 Julai 2026, 73,881,422 bait, checksum lulus | REFERENCE ONLY |
| Fresh F7 backup/restore | Belum dibuat | PENDING |
| Encryption-key facility | Tiada key TOTP khusus ditemui dalam runtime | PENDING |
| Faktor default | Tiada default global; setiap admin memilih `EMAIL_OTP` atau `TOTP` sendiri selepas faktor confirmed | OWNER APPROVED |
| Crypto runtime | PHP libsodium dan `secretbox` tersedia; key size 32 byte | PASS |

Tiada identifier admin, alamat e-mel, credential, token atau session ID direkod
dalam evidence ini.

## 3. Endpoint dan purpose inventory awal

| Surface | Purpose minimum | Status F7.0 |
|---|---|---|
| Semua page/action admin biasa | `ADMIN_ACCESS` | 2 page dan 44 action termasuk read-only settings dipetakan serta diguard dalam F7.4 |
| Authentication Policy preview/apply | `SECURITY_CONFIGURATION_CHANGE` | 4 action preview/mutation dipetakan serta diguard dalam F7.4 |
| Password Recovery configuration/test | `SECURITY_CONFIGURATION_CHANGE` | Keputusan purpose khusus perlu disahkan |
| Enrollment/revoke faktor TOTP | `SECURITY_CONFIGURATION_CHANGE` | Endpoint belum wujud |
| Revoke satu/semua active session | `ACTIVE_SESSION_REVOCATION` | Endpoint mutation belum wujud |
| Request/verify challenge | Authenticated-admin pre-step-up tier | Endpoint belum wujud |

Kesemua 48 action admin telah diklasifikasikan dalam F7.4 sebagai
`ADMIN_ACCESS` atau `SECURITY_CONFIGURATION_CHANGE`. Endpoint revocation yang
belum wujud akan gagal tertutup sehingga dipetakan kepada
`ACTIVE_SESSION_REVOCATION`.

## 4. Readiness register

| Gate | Owner diperlukan | Status | Evidence/keputusan diperlukan |
|---|---|---|---|
| F7.0-G01 Change ID | Change owner | APPROVED | `ONEID-F7-2FA-20260720-01` |
| F7.0-G02 Executing/rollback owner | System owner | OWNER ACCEPTED | Staff reference `0530-09` ialah pemilik tunggal pembangunan/UAT dan memegang executing, rollback, DBA, monitoring, security review serta acceptance |
| F7.0-G03 Maintenance/observation window | Operations owner | APPROVED | 22 Julai 2026, 20:30–22:30 MYT; 24 jam utama dan 7 hari enhanced monitoring |
| F7.0-G04 Endpoint-purpose matrix | Security + system owner | PASS F7.4 | Semua 48 action dan 2 page dipetakan |
| F7.0-G05 Admin pilot/mailbox | Identity owner | PASS | `ADMIN-PILOT-01`, staff reference `0530-09`: canonical account dipadankan secara read-only, admin aktif dan format e-mel sah; mailbox delivery masih diuji semula sebelum activation |
| F7.0-G06 Fresh backup/restore | DBA/backup owner | PASS | `S4D-20260720-160133`: 81,881,933 bait, SHA-256 disahkan, 19 table exact row-count reconciliation, restore target dibuang |
| F7.0-G07 Encryption-key custody | Security/operations owner | PARTIAL | `/etc` tidak writable; operational UAT keyring dicipta di `/home/iqs/.config/oneid/keys/admin-totp-keyring.php`, di luar repo/web root, owner `iqs`, directory `0700`, fail `0600`, version `v1`; rotation/backup/recovery masih perlu diuji |
| F7.0-G08 Break-glass | Security owner | BLOCKED | Approval, actor, reason, incident ID, scope, expiry, audit dan review |
| F7.0-G09 Monitoring | Operations/security owner | PARTIAL | Staff reference `0530-09` ialah monitoring dan security owner tunggal; alert channel dan thresholds masih pending |
| F7.0-G10 Rate/recovery policy | Security owner | PARTIAL | 5/jam, 10/hari, cooldown 60 saat, validity 5 minit, 5 cubaan dan default per-admin diluluskan; reset identity proof masih pending |
| F7.0-G11 Zero-mutation rejection contract | Engineering/security | PENDING | Purpose mismatch, expiry, replay, limit dan unavailable-factor cases |
| F7.0-G12 Owner GO F7.1 | Change + security owner | APPLIED / ACCEPTED | Owner memberi `GO F7.1 APPLY`, menggantikan window kepada execution segera; schema disahkan lengkap dan flag kekal `0` |

## 5. Keputusan yang diminta daripada owner

1. Tentukan alert channel dan thresholds yang akan dipantau oleh owner tunggal.
2. Jalankan fresh backup, checksum dan isolated restore rehearsal F7.
3. Cipta keyring di lokasi diluluskan dan uji access, rotation, backup serta recovery.
4. Luluskan prosedur break-glass yang auto-expire dan diaudit.
5. Sahkan proses identity proof bagi reset/recovery Authenticator.
6. Lengkapkan purpose classification bagi 48 action admin dan 2 page.

## 6. Keputusan faktor default

Owner menetapkan pada 20 Julai 2026 bahawa:

- sistem tidak mempunyai atau memaksa faktor default global;
- admin memilih `EMAIL_OTP` atau `TOTP` sebagai default sendiri;
- pilihan hanya boleh menunjuk kepada faktor yang tersedia dan confirmed;
- admin boleh memilih faktor lain yang tersedia ketika challenge tanpa
  mengubah default;
- kegagalan faktor default tidak memberikan bypass—faktor alternatif masih
  mesti diverifikasi sepenuhnya; dan
- jika tiada faktor yang tersedia, akses admin ditolak secara fail-closed.

Preference ini bukan grant keselamatan. Ia hanya menentukan kaedah yang
dipaparkan dahulu dan tidak boleh mengubah purpose, expiry, attempt limit,
session binding atau audit requirement.

## 7. Keputusan operasi diterima

| Perkara | Keputusan |
|---|---|
| Change ID | `ONEID-F7-2FA-20260720-01` |
| Executing admin | Staff reference `0530-09` |
| Rollback owner | Staff reference `0530-09` |
| Admin pilot | `ADMIN-PILOT-01`, dipetakan secara operasi kepada staff reference `0530-09` |
| DBA/backup owner | Staff reference `0530-09` |
| Monitoring/security owner | Staff reference `0530-09` |
| Maintenance | 22 Julai 2026, 20:30–22:30, `Asia/Kuala_Lumpur` |
| Observation | 24 jam utama dan 7 hari enhanced monitoring |
| Step-up grant | 15 minit |
| TOTP | 30 saat, toleransi ±1 time step |

`0530-09` ialah staff reference, bukan canonical `user_tbl.u_id`. Audit read-only
mengesahkan ia memetakan kepada satu akaun admin aktif dengan format e-mel sah.
Canonical login ID dan alamat e-mel tidak direkod dalam repository.

Owner menetapkan bahawa executing admin, rollback owner, DBA, monitoring,
security review, pilot dan acceptance bagi pembangunan/UAT semuanya dikendalikan
oleh individu yang sama, staff reference `0530-09`, tanpa penglibatan admin
kedua. Concentration-of-duty ini diterima secara eksplisit untuk pembangunan/UAT.

Kawalan pampasan wajib ialah automated contract dan negative test, checksum
evidence, fresh backup/restore rehearsal, fail-closed feature flag, bounded
pilot, audit log dan rollback yang telah diuji. Owner mesti merekod sendiri
keputusan `GO`, `ACCEPT` atau `ROLLBACK`; ketiadaan reviewer kedua tidak boleh
menjadikan gate lulus secara tersirat. Keputusan ini tidak memberi kelulusan
automatik untuk menggunakan model pemilik tunggal di production.

## 8. Exit criteria F7.0

F7.0 hanya boleh ditutup apabila G01 hingga G11 mempunyai evidence
`PASS/APPROVED`, tiada blocker terbuka dan owner merekod keputusan `GO F7.1`.
Backup S4D lama tidak menggantikan fresh backup yang diperlukan sebelum migration
F7.1. Sepanjang status `NO-GO`, jangan cipta schema separa atau aktifkan 2FA.
