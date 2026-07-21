# F7.0 Change Record — ONEID-F7-2FA-20260720-01

**Direkod:** 20 Julai 2026  
**Jenis:** Readiness dan planning; zero runtime/schema mutation  
**Status:** F7.1 APPLIED / VERIFIED / ACCEPTED — FEATURE OFF

## Owner dan window

- Executing admin dan rollback owner: staff reference `0530-09`.
- Admin pilot: `ADMIN-PILOT-01`, dipetakan secara operasi kepada reference yang sama.
- DBA/backup dan monitoring owner: staff reference `0530-09`.
- Security review dan acceptance: staff reference `0530-09` sebagai pemilik
  tunggal pembangunan/UAT; tiada admin kedua terlibat.
- Maintenance: 22 Julai 2026, 20:30–22:30 MYT.
- Observation: 24 jam utama, diikuti 7 hari enhanced monitoring.

Audit read-only mengesahkan staff reference tersebut memetakan kepada satu akaun
admin aktif dan format e-mel sah. Canonical login ID serta alamat e-mel tidak
direkod dalam fail ini.

## Polisi diluluskan

- Tiada faktor default global; setiap admin memilih default sendiri daripada
  `EMAIL_OTP` atau `TOTP` yang tersedia dan confirmed.
- OTP e-mel: 5 permintaan sejam, 10 sehari, cooldown 60 saat, sah 5 minit dan
  maksimum 5 cubaan bagi setiap challenge.
- Step-up grant: 15 minit.
- TOTP: tempoh 30 saat dan toleransi ±1 time step.
- Default preference bukan grant dan tidak boleh menghasilkan bypass.

## Encryption decision

Secret manager organisasi kekal pilihan utama. Fallback UAT diluluskan pada:

```text
/etc/oneid/keys/admin-totp-keyring.php
```

Keyring mesti berada di luar repository/database dan menggunakan versioned
32-byte libsodium key. PHP runtime mempunyai extension libsodium dan fungsi
`secretbox`. Nilai key tidak boleh direkod dalam dokumen, log atau change
evidence.

Pada 20 Julai 2026, `/etc/oneid` tidak boleh ditulis oleh deployment owner.
Operational UAT keyring lalu dicipta di lokasi luar repo dan web root:

```text
/home/iqs/.config/oneid/keys/admin-totp-keyring.php
```

PHP-FPM berjalan sebagai `iqs`; directory ialah `0700` dan keyring `0600`, owner
serta group `iqs`, active version `v1`. Setup idempotent kedua mengesahkan fail
sedia ada sah tanpa mengganti key. Rotation, secure backup dan recovery rehearsal
masih pending. Path ini ialah deviation UAT daripada fallback `/etc` asal.

## Baki NO-GO

- purpose matrix 48 action dan 2 page;
- fresh backup/checksum/isolated restore F7;
- key rotation, secure backup dan recovery rehearsal;
- break-glass berapproval dan auto-expiry;
- alert channel dan thresholds bagi pemantauan owner tunggal;
- reset/recovery identity-proof procedure; dan
- zero-mutation rejection contract.

F7.1 live schema apply tidak dibenarkan sehingga semua baki ini ditutup dan
`GO F7.1 APPLY` direkod oleh owner.

Pada 20 Julai 2026 owner memberikan limited GO untuk membina dan menguji F7.1
secara dormant. Limited GO ini membenarkan source, migration dan isolated
database rehearsal sahaja. Ia tidak membenarkan apply kepada schema OneID live,
feature activation atau permulaan F7.2. Rekod teknikal berada dalam
`docs/F7_1_SCHEMA_DAN_ENCRYPTION_FOUNDATION.md`.

Owner kemudian merekod arahan `GO F7.1 APPLY`. Apply belum dijalankan kerana
masa arahan, 20 Julai 2026 sekitar 15:52 MYT, berada di luar maintenance window
yang diluluskan, 22 Julai 2026 20:30–22:30 MYT. Arahan GO kekal direkod, tetapi
fresh backup/restore dan schema mutation mesti berlaku dalam window tersebut,
melainkan owner merekod window gantian secara eksplisit.

Owner seterusnya menggantikan window dan mengarahkan execution segera pada
20 Julai 2026. Fresh backup `S4D-20260720-160133` lulus checksum, isolated
restore 19 table, exact row-count digest dan cleanup. F7.1 migration kemudian
diaplikasi. Verification selesai sekitar 16:04 MYT dengan 4 table, 4 foreign
key, 9 CHECK constraint, flag `admin_2fa_enabled=0` dan 0 row dalam semua store
baharu. Regression lulus; keputusan F7.1 ialah `ACCEPT`, feature masih `OFF`.

F7.2 kemudian diteruskan dalam window gantian yang sama. Migration menambah
`sent_at`, dua rate-limit index dan audit event 37-43. Forward/down isolated
rehearsal lulus dan live check mengesahkan column 1/1, index 2/2 serta event 7/7.
Email OTP service contract lulus 15/15. Live disabled contract mengesahkan flag
`0`, sender call 0 dan challenge row kekal 0. Tiada e-mel sebenar dihantar.

F7.3 diteruskan dalam window gantian yang sama. Primitive RFC 6238 lulus semua
vektor ujian, migration rehearsal forward/down lulus, dan live schema mengesahkan
3 kolum lifecycle, 1 indeks anti-replay serta 6 event audit. Persistence contract
dirollback kepada 0 row. Feature kekal OFF dan akaun `0530-09` belum didaftarkan.

F7.4 menambah enforcement server-side tanpa migration baharu. Dua halaman dan
48 action admin dipetakan kepada purpose; grant disemak tepat mengikut admin,
session, browser, purpose, expiry dan revocation. Direct-bypass 5/5 serta
authorization persistence rollback lulus. Feature kekal OFF dan tiada row live
baharu dihasilkan.

F7.5 membina UI/API challenge, local QR enrollment, preference per-admin,
session/CSRF rotation, grant rebind dan controlled-bootstrap gate. Event audit
50–53 dipasang. Contract 9/9 lulus tetapi automated test tidak menghantar e-mel,
mendaftarkan faktor pilot atau mengaktifkan feature. Owner browser UAT masih
diperlukan; live flag kekal `0`.

Owner kemudian melaksanakan controlled bootstrap pada 20 Julai 2026 19:11:29
MYT. History merekod `ADMIN_2FA_BOOTSTRAP_ENABLED`, configuration version 4 dan
live flag `1`. TOTP `ADMIN_ACCESS` verification serta kemasukan dashboard lulus.
Pembetulan UAT selepas activation memastikan read-only settings menggunakan
`ADMIN_ACCESS` dan grant yang sama digunakan semula sehingga tamat 15 minit.

## Model pemilik tunggal

Owner mengesahkan bahawa keseluruhan pembangunan dan UAT Fasa 7 diuji oleh
staff reference `0530-09`. Individu yang sama memegang peranan executing admin,
rollback owner, DBA/backup, monitoring, security review, pilot dan acceptance.
Tiada admin kedua terlibat.

Risiko concentration-of-duty diterima untuk environment pembangunan/UAT dengan
kawalan pampasan berikut:

- automated contract, negative test dan bypass test;
- evidence serta checksum yang boleh diulang semak;
- fresh backup dan isolated restore rehearsal;
- feature flag kekal fail-closed sehingga activation;
- bounded pilot, audit log dan observation window; dan
- keputusan eksplisit `GO`, `ACCEPT` atau `ROLLBACK` oleh owner.

Model ini tidak dilanjutkan kepada production tanpa keputusan owner production
yang berasingan.
