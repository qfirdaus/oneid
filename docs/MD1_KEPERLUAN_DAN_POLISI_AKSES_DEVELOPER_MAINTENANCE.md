# MD1 — Keperluan dan Polisi Akses Developer Semasa Maintenance

**Tarikh:** 4 September 2026  
**Rujukan perubahan:** `ONEID-MAINTENANCE-DEVELOPER-20260904-01`  
**Fasa:** 1 — pemuktamadan keperluan dan polisi  
**Status:** diluluskan owner pada 4 September 2026; Fasa 2 dibenarkan

## 1. Tujuan

Dokumen ini ialah baseline canonical bagi fungsi yang membenarkan developer
yang diluluskan mengakses portal OneID ketika maintenance mode aktif.

Fasa ini tidak mengubah schema, data, konfigurasi runtime, login, sesi,
authorization atau paparan aplikasi. Sebarang pembangunan Fasa 2 dan
seterusnya hanya boleh bermula selepas owner mengesahkan dokumen ini.

## 2. Hasil yang dikehendaki

Apabila maintenance aktif:

1. pengguna biasa kekal menerima halaman maintenance dan HTTP `503`;
2. developer yang mempunyai grant sah boleh menggunakan login maintenance;
3. developer mesti melengkapkan primary authentication dan MFA sebelum sesi
   atau token SSO diwujudkan;
4. developer dibawa ke `/page/dashboard` dan melihat paparan pengguna biasa;
5. developer tidak mendapat akses, menu, endpoint atau grant pentadbir; dan
6. grant boleh dijadualkan, tamat sendiri atau direvoke dengan audit lengkap.

Apabila maintenance tidak aktif, semua akaun menggunakan aliran login biasa.
Laluan login maintenance developer tidak boleh digunakan sebagai laluan login
alternatif.

## 3. Keputusan seni bina yang dikunci

### MD1-D01 — Capability berasingan

Akses dilaksanakan sebagai capability `MAINTENANCE_ACCESS`, bukan role
pentadbir. Akaun developer kekal `u_type=0` dan kategori pengguna asal tidak
diubah.

### MD1-D02 — Jangan perluaskan `u_type`

Nilai baharu seperti `u_type=2` tidak akan diperkenalkan. Kod semasa menggunakan
`u_type=1` sebagai sempadan admin dan `u_type=0` bagi beberapa polisi pengguna.
Menambah semantik ketiga pada medan itu berisiko memberi kesan kepada login,
MFA, menu, laporan dan integrasi SSO.

### MD1-D03 — Allowlist server-side

Kelayakan developer datang daripada rekod grant server-side yang berasingan.
Hidden input, cookie atau session flag daripada klien bukan bukti kelayakan.

### MD1-D04 — Least privilege

Capability hanya membenarkan sesi melepasi maintenance gate. Semua permission
selepas itu kekal berdasarkan permission pengguna sedia ada. Capability tidak
memberi akses `/admin/*`, fungsi konfigurasi, laporan admin atau Admin Step-Up.

### MD1-D05 — MFA wajib

Login maintenance developer sentiasa memerlukan MFA walaupun polisi MFA login
pengguna biasa berada dalam mode yang lebih longgar. Token SSO, cookie SSO dan
authenticated session hanya boleh diwujudkan selepas MFA berjaya.

### MD1-D06 — Kata laluan sahaja untuk keluaran pertama

Keluaran pertama menyokong primary authentication menggunakan ID pengguna dan
kata laluan. MyDigital ID tidak termasuk sehingga satu threat model dan aliran
pending-login khusus diluluskan.

### MD1-D07 — Fail closed

Kegagalan membaca konfigurasi maintenance, grant, status akaun atau keadaan MFA
mesti menolak akses. Sistem tidak boleh menganggap developer layak apabila
dependency gagal.

### MD1-D08 — Pengurusan oleh admin dengan Step-Up

Grant dan revoke hanya boleh dibuat oleh pentadbir aktif selepas Admin Step-Up
untuk purpose perubahan konfigurasi keselamatan. Semua perubahan memerlukan
sebab dan optimistic concurrency.

### MD1-D09 — Revocation berkuat kuasa pada sesi aktif

Grant tidak boleh dipercayai daripada session sahaja. Server mesti mengesahkan
grant pada request developer semasa maintenance supaya revoke, expiry atau
account suspension menyekat request berikutnya dan menamatkan sesi/token.

### MD1-D10 — Feature flag dormant

Implementasi mesti diperkenalkan dengan feature flag yang default kepada
`false`, dicadangkan sebagai:

```text
ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED=false
```

Schema atau kod yang telah dideploy tidak mengaktifkan fungsi secara automatik.

## 4. Definisi kelayakan

Developer layak hanya apabila semua syarat berikut benar pada masa request:

- feature flag diaktifkan;
- maintenance policy berada dalam keadaan `ACTIVE`;
- akaun wujud, `avail_status=1` dan `u_type=0`;
- grant milik user ID yang sama berada dalam keadaan `ACTIVE`;
- waktu semasa tidak lebih awal daripada `valid_from`;
- `valid_until` belum berlalu jika ia ditetapkan;
- token SSO dan sesi masih aktif;
- MFA bagi transaksi login maintenance telah disahkan; dan
- grant terikat kepada identiti pengguna dalam sesi, bukan nilai yang dihantar
  oleh browser.

Kegagalan mana-mana syarat menghasilkan penolakan. Perubahan kategori pengguna
tidak dengan sendirinya menambah atau membuang capability.

## 5. Lifecycle grant

Status domain yang diluluskan:

| Status efektif | Maksud |
|---|---|
| `SCHEDULED` | Grant wujud tetapi `valid_from` belum tiba |
| `ACTIVE` | Grant diluluskan dan berada dalam tempoh sah |
| `EXPIRED` | `valid_until` telah berlalu |
| `REVOKED` | Admin telah membatalkan grant |

`ACTIVE` ialah satu-satunya status yang membenarkan login atau meneruskan sesi
semasa maintenance. Rekod tidak dipadam ketika revoke supaya jejak audit kekal.

Untuk Fasa 2, `valid_until` dicadangkan wajib bagi mengelakkan akses kekal tanpa
semakan. Had maksimum sebenar akan diputuskan bersama reka bentuk schema.

## 6. Matriks authorization

| Subjek | Maintenance OFF | Maintenance ACTIVE | Portal user | Portal admin |
|---|---|---|---|---|
| Pengguna biasa | Login biasa | Disekat `503` | Mengikut login biasa | Tidak |
| Developer tanpa grant sah | Login biasa | Disekat `503` | Tidak ketika maintenance | Tidak |
| Developer dengan grant + MFA | Login biasa | Dibenarkan | Ya, permission user asal | Tidak |
| Admin maintenance + MFA | Login biasa | Dibenarkan | Mengikut fungsi sedia ada | Ya |
| Akaun suspended | Ditolak | Ditolak | Tidak | Tidak |

Developer tidak boleh menjadi admin hanya dengan memalsukan parameter login,
cookie, session payload, URL, user category atau grant ID milik pengguna lain.

## 7. Aliran login yang diluluskan

1. Developer memilih `Log Masuk Developer` pada halaman maintenance.
2. Server mengesahkan feature dan maintenance sedang aktif.
3. Server memproses ID pengguna dan kata laluan melalui authentication sedia ada
   serta rate limit server-side.
4. Server mengesahkan akaun aktif `u_type=0` dan grant sah.
5. Server mewujudkan pending MFA transaction yang terikat kepada PHP session,
   browser digest, IP audit dan user ID.
6. Developer melengkapkan faktor yang dibenarkan oleh polisi MFA pengguna.
7. Server mengesahkan semula maintenance, akaun dan grant sebelum finalization.
8. Hanya selepas semua semakan lulus, server mencipta token/cookie/session.
9. Server merekod kejayaan dan redirect ke `/page/dashboard`.

Semua penolakan sebelum langkah 8 tidak boleh menghasilkan authenticated
session, token SSO atau cookie SSO.

## 8. Polisi sesi dan penamatan

- Sesi developer menggunakan timeout portal pengguna sedia ada.
- Sesi membawa rujukan grant dan versi maintenance, bukan kuasa admin.
- Sesi tidak boleh bertukar menjadi sesi maintenance melalui refresh halaman.
- Apabila maintenance tamat, capability khas tidak lagi diperlukan; sesi tidak
  mendapat permission tambahan selepas maintenance.
- Apabila grant expired/revoked atau akaun suspended ketika maintenance,
  request berikutnya mesti revoke token, membersihkan authenticated session dan
  kembali ke halaman maintenance.
- Logout menggunakan aliran logout pusat sedia ada.
- Pengaktifan maintenance tidak mengubah akaun atau kategori pengguna.

Keputusan sama ada sesi developer kekal sebagai sesi pengguna biasa selepas
maintenance tamat akan diuji dalam Fasa integrasi. Baseline pilihan ialah
**kekalkan sesi jika token dan akaun masih sah**, kerana capability tambahan
secara efektif telah hilang apabila gate tidak aktif.

## 9. Kod keputusan canonical

| Kod | HTTP dicadangkan | Penggunaan |
|---|---:|---|
| `MAINTENANCE_NOT_ACTIVE` | 409 | Laluan khas dipanggil di luar maintenance |
| `MAINTENANCE_DEVELOPER_FEATURE_DISABLED` | 404 | Feature belum diaktifkan |
| `MAINTENANCE_ACCESS_DENIED` | 403 | Respons umum bagi grant tiada/tidak sah |
| `MAINTENANCE_ACCESS_NOT_YET_VALID` | 403 | Diagnostik admin/audit sahaja |
| `MAINTENANCE_ACCESS_EXPIRED` | 403 | Diagnostik admin/audit sahaja |
| `MAINTENANCE_ACCESS_REVOKED` | 403 | Diagnostik admin/audit sahaja |
| `MAINTENANCE_MFA_REQUIRED` | 200 | Primary auth lulus; challenge diperlukan |
| `MAINTENANCE_MFA_UNAVAILABLE` | 503 | Faktor/persistence MFA gagal atau tiada |
| `MAINTENANCE_ACCESS_REVALIDATION_FAILED` | 401 | Grant sesi aktif tidak lagi sah |

UI awam menggunakan mesej umum untuk `MAINTENANCE_ACCESS_DENIED` dan tidak
mendedahkan sama ada username, role atau grant tertentu wujud. Kod terperinci
hanya masuk audit/admin diagnostics.

Kod authentication sedia ada seperti `AUTH_CREDENTIALS_INVALID`,
`AUTH_ACCOUNT_SUSPENDED` dan `AUTH_RATE_LIMITED` kekal digunakan.

## 10. Keperluan audit dan privasi

Event minimum:

- `MAINTENANCE_DEVELOPER_ACCESS_GRANTED`;
- `MAINTENANCE_DEVELOPER_ACCESS_REVOKED`;
- `MAINTENANCE_DEVELOPER_LOGIN_ACCEPTED`;
- `MAINTENANCE_DEVELOPER_LOGIN_REJECTED`;
- `MAINTENANCE_DEVELOPER_MFA_VERIFIED`;
- `MAINTENANCE_DEVELOPER_SESSION_TERMINATED`.

Setiap event membawa actor/subject yang bersesuaian, reason code, timestamp,
IP, correlation ID dan reference grant. Audit tidak boleh menyimpan password,
raw OTP/TOTP, secret MFA, raw token, cookie atau data peribadi berlebihan.

## 11. Acceptance criteria keseluruhan

Feature hanya dianggap siap apabila semua perkara ini terbukti:

- [ ] Developer bergrant aktif boleh login ketika maintenance selepas MFA.
- [ ] Developer diarahkan ke `/page/dashboard`.
- [ ] `login_user_type` developer kekal `0`.
- [ ] Paparan dan permission developer sama dengan akaun pengguna asal.
- [ ] Developer ditolak daripada setiap halaman dan endpoint admin.
- [ ] Pengguna biasa dan developer tanpa grant kekal menerima maintenance `503`.
- [ ] Parameter atau session flag palsu tidak boleh memintas semakan DB.
- [ ] Tiada token/cookie/authenticated session dicipta sebelum MFA lulus.
- [ ] Grant scheduled, expired dan revoked ditolak.
- [ ] Revoke atau suspension menamatkan akses sesi aktif pada request berikutnya.
- [ ] Login maintenance ditolak apabila maintenance OFF.
- [ ] Rate limit, CSRF, session binding dan token revocation kekal berfungsi.
- [ ] Kedua-dua layout document root disokong.
- [ ] Audit berjaya dan gagal mempunyai correlation ID tanpa secret.
- [ ] Semua kontrak maintenance sedia ada dan regression suite berkaitan lulus.
- [ ] Feature kekal dormant selagi flag belum diaktifkan.
- [ ] Migration forward/rollback dan rehearsal UAT tersedia sebelum activation.

## 12. Di luar skop keluaran pertama

- memberi developer permission pentadbir;
- menambah `u_type` baharu;
- mengubah kategori atau sumber induk akaun;
- login maintenance melalui MyDigital ID;
- memintas MFA berdasarkan IP/VPN;
- mewujudkan akaun developer tempatan secara automatik;
- memberi akses automatik kepada semua ahli unit ICT; dan
- membuka aplikasi/service provider luar hanya kerana grant maintenance wujud.

## 13. Gate kelulusan Fasa 1

**Keputusan owner:** diluluskan pada 4 September 2026 melalui arahan untuk
memulakan Fasa 2. Semua keputusan MD1-D01 hingga MD1-D10 menjadi baseline
terkawal untuk reka bentuk schema Fasa 2.

Owner perlu mengesahkan perkara berikut sebelum Fasa 2:

1. capability berasingan dengan akaun kekal `u_type=0`;
2. MFA wajib dan password-only bagi keluaran pertama;
3. grant mempunyai tempoh sah dan boleh direvoke;
4. Admin Step-Up diperlukan untuk grant/revoke;
5. developer hanya mendapat portal dan permission pengguna biasa;
6. revalidation server-side menamatkan akses yang sudah tidak sah;
7. feature flag default OFF; dan
8. baseline sesi selepas maintenance tamat seperti Seksyen 8.

Kelulusan hendaklah direkodkan menggunakan rujukan perubahan dokumen ini.

## 14. Verifikasi Fasa 1

Jalankan:

```bash
php tools/maintenance_developer_phase1_contract.php
```

Kontrak tersebut read-only. Ia mengesahkan baseline kod semasa serta kewujudan
keputusan dan acceptance criteria dokumen ini. Ia tidak membuktikan feature
sudah dibangunkan.
