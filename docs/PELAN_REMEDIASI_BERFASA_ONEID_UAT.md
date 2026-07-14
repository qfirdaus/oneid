# Pelan Remediasi Berfasa OneID-UAT

**Disediakan berdasarkan:** `AUDIT_PROJEK_ONEID_UAT_2026-07-13.md`  
**Pendekatan:** Selesaikan perubahan ringan dan berisiko rendah dahulu, kemudian bergerak kepada perubahan yang menyentuh authentication, data dan architecture.  
**Nota penting:** Susunan ini berdasarkan effort dan risiko implementasi, bukan severity semata-mata. Isu kritikal yang boleh ditutup dengan perubahan kecil tetap diletakkan pada fasa awal.

## Cara Menggunakan Pelan Ini

Setiap fasa perlu melalui aliran berikut:

1. Ambil backup atau snapshot yang boleh dipulihkan.
2. Rekod keadaan semasa dan acceptance criteria.
3. Buat perubahan dalam skop kecil.
4. Jalankan checklist ujian fasa.
5. Pantau log sebelum masuk ke fasa seterusnya.

Jangan gabungkan semua fasa dalam satu deployment. Authentication, token dan password khususnya perlu dibuat secara berasingan supaya punca masalah mudah dikenal pasti jika berlaku regression.

## Ringkasan Fasa

| Fasa | Fokus | Anggaran effort | Risiko perubahan |
|---|---|---:|---:|
| 0 | Baseline, backup dan observability | Rendah | Rendah |
| 1 | Quick wins dan kurangkan exposure | Rendah | Rendah |
| 2 | Konfigurasi, error handling dan cookie asas | Rendah–Sederhana | Rendah–Sederhana |
| 3 | Secrets dan permission | Sederhana | Sederhana |
| 4 | Authentication, authorization dan CSRF | Sederhana–Tinggi | Tinggi |
| 5 | Password, OTP, session dan token SSO | Tinggi | Tinggi |
| 6 | API, data peribadi, SQL dan TLS | Tinggi | Tinggi |
| 7 | Dependency, cleanup besar dan refactor | Tinggi | Sederhana–Tinggi |

---

## Fasa 0 — Baseline dan Safety Net

**Rekod pelaksanaan:** Lihat `docs/FASA_0_BASELINE_DAN_ROLLBACK.md` untuk baseline checksum, rollback, smoke-test dan gap yang perlu disahkan pada host UAT.

**Matlamat:** Pastikan setiap perubahan selepas ini boleh diuji dan dipulihkan.

### Task

- [ ] Ambil backup source, database dan konfigurasi web server.
- [ ] Rekod owner bagi OneID, IDMS, SKP, monitoring dan setiap service provider SSO.
- [ ] Dapatkan access log Apache sekurang-kurangnya 30 hari; sasaran lebih selamat ialah 90 hari.
- [x] Rekod scheduled task daripada source dan runtime lokal; konfigurasi host UAT sebenar masih perlu disahkan.
- [x] Wujudkan smoke-test checklist manual untuk:
  - Login staf.
  - Login pelajar.
  - First-time password change.
  - Forgot password dan OTP.
  - Dashboard pengguna.
  - Dashboard administrator.
  - Launch aplikasi SSO dan non-SSO.
  - Logout.
  - Scheduled user sync.
- [x] Rekod checksum, permission, HTTP exposure status dan flow source semasa sebagai baseline; screenshot login berakaun masih perlu dibuat pada host UAT.
- [ ] Jika boleh, masukkan deployment ini ke Git tanpa memasukkan secrets baharu.

### Definition of done

- Backup restore telah diuji atau sekurang-kurangnya prosedur restore telah disahkan.
- Semua integration owner utama dikenal pasti.
- Access log dan error log boleh diperiksa selepas deployment.
- Smoke-test checklist telah dipersetujui.

---

## Fasa 1 — Quick Wins dan Kurangkan Exposure

**Rekod pelaksanaan:** Lihat `docs/FASA_1_PELAKSANAAN_DAN_ROLLBACK.md` untuk checksum, perubahan upload, HTTP verification, rollback dan item yang ditangguhkan.

**Matlamat:** Kurangkan attack surface tanpa mengubah business logic login atau database.

### 1.1 Sekat fail sensitif daripada web

- [x] Tambah kawalan Apache `.htaccess` dan containment permission lokal untuk menyekat akses HTTP kepada:
  - `*.sql`
  - `*.log`
  - `*.bat`
  - `*.ps1`
  - `.DS_Store`
  - `Thumbs.db`
  - Fail bernama `*_old.php`, `* copy.php` dan snapshot bertarikh
- [ ] Pindahkan `sso_db.sql` keluar daripada document root.
- [ ] Pindahkan `cron/logs/` keluar daripada document root atau sekat akses web sepenuhnya.
- [ ] Sekat directory listing jika belum dimatikan pada server.

### 1.2 Lindungi direktori upload

- [x] Tambah deny rule Apache bagi PHP/script execution dalam `public_img/`; snippet Nginx telah disediakan untuk pemasangan oleh administrator server.
- [ ] Pastikan hanya fail imej boleh diserve dari folder tersebut.
- [x] Tambah application-level MIME/content validation sebagai compensating control kerana runtime Nginx lokal tidak membaca `.htaccess`.

### 1.3 Tutup endpoint ujian dan diagnostic yang tidak diperlukan

- [x] Semak access log lokal untuk `test.php` dan `atest.php`; tiada penggunaan ditemui dalam tempoh log yang tersedia.
- [x] Tutup response web kedua-dua endpoint dengan HTTP 404; physical quarantine menunggu tempoh log yang mencukupi.
- [ ] Hadkan `diag/agent.php` dan `diagnostic/index.php` kepada IP monitoring yang sebenar.
- [ ] Jika salah satu monitoring agent tidak digunakan, quarantine dahulu dan pantau sebelum delete.

### 1.4 Bersihkan metadata yang jelas tidak diperlukan

- [ ] Quarantine `.DS_Store` dan `Thumbs.db`.
- [ ] Jangan padam `public_img` atau vendor assets secara pukal pada fasa ini.

### 1.5 Betulkan reference aset kecil

- [x] Sahkan penggunaan `img/mock1.png`.
- [x] Tukar reference dashboard aktif kepada aset `img/mock1.jpg` yang benar-benar wujud.

### Ujian selepas Fasa 1

- Login page boleh dibuka.
- Dashboard user dan admin masih memuatkan CSS, JavaScript dan imej.
- Icon aplikasi masih dipaparkan.
- `public_img/*.php` tidak boleh dieksekusi.
- Fail SQL, log dan diagnostic yang disekat memberikan `403` atau `404`.
- Scheduled sync masih berjalan.

### Definition of done

- Fail sensitif tidak boleh dicapai melalui browser.
- Folder upload tidak boleh menjalankan script.
- Tiada regression pada flow utama.

---

## Fasa 2 — Konfigurasi dan Hardening Ringan

**Rekod pelaksanaan:** Lihat `docs/FASA_2_PELAKSANAAN_DAN_ROLLBACK.md` untuk baseline, environment variables, security headers, verification dan rollback.

**Matlamat:** Kurangkan information leakage dan jadikan environment lebih konsisten.

### 2.1 Error handling

- [x] Matikan `display_errors` secara default; `ONEID_APP_DEBUG` disediakan untuk environment terkawal.
- [x] Jangan pulangkan mesej exception mentah kepada browser.
- [x] Log error teknikal melalui server error log di luar document root.
- [x] Pulangkan generic error response kepada pengguna dan API client.

### 2.2 Pisahkan konfigurasi environment

- [x] Keluarkan domain `oneid.local` daripada hardcoded redirect flow aktif; fallback lokal kekal dalam satu lokasi konfigurasi.
- [x] Gunakan satu nilai `APP_URL` melalui `ONEID_APP_URL`.
- [x] Sokong nilai berasingan untuk local, UAT dan production melalui environment.
- [x] Pastikan logout, dashboard dan SSO aktif menggunakan sumber konfigurasi yang sama; Apache menggunakan `SERVER_NAME`.

### 2.3 Security headers asas

- [x] Tambah `X-Content-Type-Options: nosniff`.
- [x] Tambah frame protection melalui CSP report-only dan `X-Frame-Options: SAMEORIGIN`.
- [x] Tambah `Referrer-Policy: strict-origin-when-cross-origin`.
- [ ] Tambah HSTS hanya selepas HTTPS dan semua subdomain berkaitan disahkan.
- [x] Mulakan Content Security Policy dalam report-only mode sebelum enforcement.

### 2.4 Kurangkan debug frontend

- [x] Buang semua `console.log()` daripada tiga page aktif, termasuk login, OTP, response dan token-related state.
- [x] Rekod bahawa logging pembangunan baharu mesti dikawal melalui environment dan tidak boleh mencetak data sensitif.

### Ujian selepas Fasa 2

- Error aplikasi tidak memaparkan stack trace atau credential.
- Redirect login/logout menuju domain UAT yang betul.
- Semua asset masih dimuatkan tanpa CSP violation yang memecahkan UI.
- Flow SSO masih kembali ke domain yang betul.

---

## Fasa 3 — Secrets dan Permission

**Matlamat:** Keluarkan rahsia daripada source dan hadkan siapa boleh mengubah fail.

Fasa ini nampak ringkas pada kod tetapi memerlukan koordinasi dengan database, SMTP, monitoring dan integration owner.

### 3.1 Inventori dan rotation secrets

- [x] Senaraikan semua database, SMTP, ODBC dan monitoring credentials.
- [x] Tentukan owner fungsi dan consumer bagi setiap credential; individu sebenar masih perlu disahkan dalam change ticket.
- [ ] Cipta credential baharu dengan privilege minimum.
- [x] Sediakan centralized secret loader, environment override dan runtime secret store di luar document root.
- [ ] Deploy dan sahkan connectivity.
- [ ] Revoke credential lama selepas semua consumer berpindah.

### 3.2 Pindahkan secrets keluar daripada source

- [x] Gunakan environment variable atau secret file terkawal sebagai peralihan ke secret manager.
- [x] Secret store runtime diletakkan di luar document root, bukan dalam `.env` web-accessible.
- [x] Sediakan template konfigurasi tanpa nilai sebenar.
- [x] Loader dan error response tidak mencetak nilai secrets.

### 3.3 Betulkan ownership dan permission

- [x] Kenal pasti OS user untuk deployment, Nginx dan PHP-FPM.
- [x] Tukar fail PHP/config daripada `777` kepada permission minimum yang diperlukan.
- [x] Hadkan write access kepada owner; direktori upload dan log yang sah kekal owner-writable.
- [ ] Pisahkan runtime PHP-FPM daripada deployment owner supaya runtime tidak boleh mengubah source PHP.
- [x] Sahkan scheduled sync owner masih boleh menulis log.

Dokumentasi pelaksanaan, status rotation dan rollback: `docs/FASA_3_PELAKSANAAN_DAN_ROLLBACK.md`.

### Ujian selepas Fasa 3

- Sambungan database utama berjaya.
- Sambungan staf/pelajar berjaya.
- Email OTP berjaya dihantar.
- Monitoring yang diluluskan masih berfungsi.
- Upload icon masih berfungsi dengan permission baharu.
- Scheduled sync masih berjalan.

---

## Fasa 4 — Authentication, Authorization dan CSRF

**Matlamat:** Pastikan setiap action sensitif hanya boleh dilakukan oleh pengguna yang sah dan role yang betul.

### 4.1 Klasifikasikan semua action

Bahagikan 52 action dalam `lib/q_func.php` kepada:

- Public: login dan permulaan forgot-password yang diperlukan.
- Authenticated user: lihat aplikasi sendiri, tukar password sendiri, sign-off session sendiri.
- Administrator: user management, ACL, configuration, sync, audit, token global dan application management.

Status pelaksanaan: [x] 48 action semasa telah dikenal pasti dan dipetakan kepada 3 public, 7 authenticated-user dan 38 administrator action. Angka 52 dalam audit awal termasuk nested/auxiliary POST checks yang bukan dispatcher action tersendiri.

### 4.2 Tambah guard server-side

- [x] Tolak action authenticated jika session tidak sah.
- [x] Tolak action admin jika `login_user_type` bukan administrator.
- [x] Tambah role guard pada permulaan `admin/dashboard.php` dan `admin/user_list.php`.
- [x] Gunakan server-side guard dan tidak bergantung pada link yang disembunyikan dalam UI.
- [x] Ikat user action kepada session identity; arbitrary user/application/ACL operations memerlukan admin privilege.
- [x] Gunakan HTTP status `401` dan `403` secara konsisten.

### 4.3 CSRF protection

- [x] Jana CSRF token 256-bit untuk session.
- [x] Hantar token melalui header pada semua AJAX request page aktif.
- [x] Tolak POST tanpa token yang sah.
- [x] Endpoint action hanya menerima POST; GET ditolak dengan `405`.

### 4.4 Pecahkan endpoint secara minimum

Jika refactor penuh terlalu besar, mulakan dengan tiga endpoint:

- `auth_actions.php`
- `user_actions.php`
- `admin_actions.php`

Tujuan awal ialah memudahkan guard, bukan menulis semula semua logic.

Central dispatcher guard dilaksanakan dahulu dalam `lib/request_security.php`. Pemecahan fizikal tiga endpoint ditangguhkan sehingga authenticated smoke test selesai supaya perubahan controller legacy kekal kecil dan boleh dirollback.

Dokumentasi pelaksanaan dan rollback: `docs/FASA_4_PELAKSANAAN_DAN_ROLLBACK.md`.

### Ujian selepas Fasa 4

- Request tanpa login ditolak.
- User biasa tidak boleh membuka admin page.
- User biasa tidak boleh memanggil action admin secara POST.
- Admin masih boleh mengurus aplikasi, user, ACL dan sync.
- CSRF request palsu ditolak.
- Pengguna hanya boleh sign-off token sendiri.

---

## Fasa 5 — Password, OTP, Session dan Token SSO

**Matlamat:** Gantikan mekanisme credential dan session yang lemah tanpa memutuskan semua pengguna serentak.

### 5.1 Migrasi password secara berperingkat

- [x] Tambah sokongan `password_hash()` dan `password_verify()`.
- [x] Benarkan login hash MD5 lama secara terkawal sehingga deadline.
- [x] Selepas login MD5 berjaya, rehash password kepada format baharu.
- [x] Rekod status melalui format hash dan `password_change_required` tanpa menyimpan password asal.
- [x] Tetapkan default tarikh tamat MD5 pada 13 Oktober 2026, boleh dioverride melalui environment.

### 5.2 Ubah first-time dan reset-password flow

- [x] Hentikan reset kepada nombor IC, ID pengguna atau nilai statik.
- [x] Gunakan OTP one-time yang diikat kepada server-side reset session.
- [x] Selepas verification, paksa pengguna menetapkan password baharu.
- [x] Jangan login pengguna secara automatik berdasarkan OTP.

### 5.3 OTP hardening

- [x] Gunakan `random_int()`.
- [x] Simpan hash OTP.
- [x] Hadkan kepada lima percubaan.
- [x] Tambah resend cooldown 60 saat dan daily limit lima request.
- [x] Gunakan generic response yang tidak mendedahkan kewujudan akaun.
- [x] Invalidasi OTP selepas digunakan atau attempt limit dicapai.

### 5.4 Session dan cookie

- [x] Panggil `session_regenerate_id(true)` selepas login.
- [x] Tetapkan cookie `Secure`, `HttpOnly` dan `SameSite=Lax`.
- [x] Jangan simpan keseluruhan user packet dalam cookie.
- [x] Simpan hanya opaque PHP session ID dan opaque SSO token.
- [x] Standardkan idle timeout 30 minit dan absolute timeout 8 jam.

### 5.5 Token SSO

- [x] Gantikan `uniqid()` dengan `random_bytes()` pada active token path.
- [x] Simpan hash token di database dan migrasikan token legacy.
- [ ] Hentikan penghantaran token melalui `new_sso_cre` dalam query string.
- [ ] Gunakan short-lived authorization code atau pertukaran server-to-server.
- [x] Rotate/revoke token selepas login, password event dan perubahan privilege/status.

Dokumentasi pelaksanaan, DB migration dan rollback: `docs/FASA_5_PELAKSANAAN_DAN_ROLLBACK.md`.

### Ujian selepas Fasa 5

- Akaun MD5 lama boleh login sekali dan dimigrasi.
- Akaun baharu menggunakan hash moden.
- Forgot-password tidak mendedahkan kewujudan akaun.
- OTP tamat tempoh, one-time dan mempunyai attempt limit.
- Cookie tidak boleh dibaca melalui JavaScript.
- Token tidak muncul dalam URL atau access log.
- Semua service provider SSO yang diluluskan masih berfungsi.

---

## Fasa 6 — API, Data Peribadi, SQL dan TLS

**Matlamat:** Lindungi integrasi luar dan kurangkan pendedahan data staf/pelajar.

**Status 13 Julai 2026:** Fasa 6A dilaksanakan dalam mode `observe`. Rujuk `FASA_6A_PELAKSANAAN_DAN_ROLLBACK.md` dan `FASA_6_INVENTORI_CONSUMER.md`. Enforcement dan token handoff baharu menunggu Fasa 6B onboarding per consumer.

### 6.1 Inventori consumer API

- [x] Kenal pasti endpoint `api.php`, `idms.php` dan `skp_api.php`, snapshot 34 aplikasi aktif, dan sediakan daftar pengesahan consumer.
- [ ] Dokumentasikan field yang benar-benar diperlukan oleh setiap consumer.
- [ ] Tutup endpoint yang tidak mempunyai owner atau penggunaan sah.

### 6.2 Service authentication dan authorization

- [ ] Gunakan client credential berasingan bagi setiap consumer. Foundation tersedia; onboarding belum selesai.
- [x] Sediakan kawalan scope dan endpoint bagi setiap client dalam compatibility mode.
- [x] Tambah rate limiting dan audit log foundation; blocking hanya dalam mode `enforce`.
- [ ] Pertimbangkan IP allowlist sebagai lapisan tambahan.

### 6.3 Data minimization

- [ ] Jangan pulangkan nombor IC, alamat, telefon atau maklumat keluarga jika tidak diperlukan.
- [ ] Elakkan response `SELECT *`.
- [ ] Bezakan endpoint carian ringkas daripada endpoint profil terperinci.

### 6.4 SQL hardening

- [x] Tukar concatenated ODBC query yang dikenal pasti pada IDMS/SKP/specific external lookup kepada parameterized query.
- [ ] Validate limit, identifier dan filter menggunakan allowlist.
- [ ] Jangan pulangkan SQL mentah atau ODBC error kepada client.

### 6.5 TLS

- [x] Aktifkan certificate verification bagi cURL aktif Fasa 6A dan helper sample; fail legacy dikuarantin kekal untuk cleanup Fasa 7.
- [ ] Pasang CA chain yang betul pada server.
- [x] Buang tetapan `CURLOPT_SSL_VERIFYPEER=false` dan `CURLOPT_SSL_VERIFYHOST=false` daripada fail aktif Fasa 6A selepas certificate UAT disahkan.

### Ujian selepas Fasa 6

- Consumer sah boleh menggunakan API.
- Consumer tanpa credential menerima `401` atau `403`.
- SQL injection payload tidak mempengaruhi query.
- Response tidak mengandungi data peribadi berlebihan.
- Semua TLS connection berjaya dengan certificate verification aktif.

---

## Fasa 7 — Cleanup Besar, Dependency dan Refactor

**Matlamat:** Kurangkan technical debt selepas security boundary stabil.

**Status 14 Julai 2026:** Pelan implementasi telah disediakan tetapi belum dijalankan. Rujuk `FASA_7_PELAN_IMPLEMENTASI_CLEANUP_DAN_MODERNISASI.md`. Fasa 7 executable cleanup menunggu Fasa 6B, evidence access log 30–90 hari dan characterization test.

**Restructuring R0–R3:** Parallel `public/` tree, path normalization dan local document-root smoke telah dilaksanakan. Tiada Nginx cutover dibuat. Rujuk `RESTRUCTURING_R0_R3_PELAKSANAAN_DAN_ROLLBACK.md`.

### 7.1 Quarantine page/fail lama

Selepas semakan access log, pindahkan ke luar document root terlebih dahulu:

- [ ] `index1.php`
- [ ] `index_.php`
- [ ] `index_20250806.php`
- [ ] `page/dashboard2.php`
- [ ] `page/dashboard_old.php`
- [ ] `admin/dashboard_old.php`
- [ ] `api_old.php`
- [ ] `lib/q_func_old.php`
- [ ] `lib/SSO_IDP_INC - Copy.php`
- [ ] `page/const/left copy.php`
- [ ] Sidebar template yang include-nya telah dikomen
- [ ] `test.php`
- [ ] `atest.php`

Pantau sekurang-kurangnya satu release cycle sebelum delete kekal.

### 7.2 Aset dinamik

- [ ] Cross-check semua `public_img` dengan `sp_list.sp_image` dalam live database.
- [ ] Kenal pasti 31 duplicate-content files.
- [ ] Quarantine hanya fail yang tidak dirujuk database.
- [ ] Tambah lifecycle: delete icon lama selepas aplikasi menukar icon.

### 7.3 Vendor dan template assets

- [ ] Bina asset manifest berdasarkan page aktif.
- [ ] Buang docs, tests, examples, source maps dan executable daripada production deployment.
- [ ] Jangan padam berdasarkan nama folder sahaja kerana CSS mungkin mempunyai indirect reference.
- [ ] Uji browser console dan network tab selepas setiap batch cleanup.

### 7.4 Dependency modernization

- [ ] Perkenalkan Composer untuk dependency PHP.
- [ ] Perkenalkan package manager dan lockfile untuk frontend.
- [ ] Audit dependency sebelum upgrade.
- [ ] Upgrade dependency secara berperingkat, bukan serentak dengan refactor authentication.

### 7.5 Refactor architecture

- [ ] Pecahkan `lib/q_func.php` mengikut domain.
- [ ] Pecahkan `admin/dashboard.php` kepada view/component yang lebih kecil.
- [ ] Asingkan inline JavaScript daripada PHP view.
- [ ] Wujudkan request validation dan standard JSON response.
- [ ] Tambah unit test untuk password, OTP, ACL dan token.
- [ ] Tambah integration test untuk login, admin dan sync.

---

## Cadangan Sprint Praktikal

Jika kerja dibuat dalam sprint pendek, susunan berikut lebih mudah dikawal:

### Sprint 1 — Sangat ringan

- Baseline dan backup.
- Sekat `.sql`, `.log`, test dan backup files.
- Matikan script execution dalam `public_img`.
- Quarantine `test.php`, `atest.php`, `.DS_Store` dan `Thumbs.db` selepas log check.
- Betulkan broken image reference.

### Sprint 2 — Hardening konfigurasi

- Matikan display error.
- Standardkan environment URL.
- Tambah security headers asas.
- Buang debug log sensitif.
- Hadkan diagnostic agent kepada monitoring IP.

### Sprint 3 — Operasi server

- Rotate dan pindahkan secrets.
- Betulkan ownership/permission.
- Pindahkan log dan operational files keluar document root.

### Sprint 4 — Access control

- Role mapping bagi semua action.
- Login/admin guard.
- CSRF protection.
- Ownership check bagi token dan user-specific operation.

### Sprint 5 — Identity security

- Password migration.
- OTP hardening.
- Session/cookie hardening.
- Token SSO redesign.

### Sprint 6 — Integration security

- Lindungi API.
- Parameterized ODBC queries.
- Data minimization.
- Aktifkan TLS verification.

### Sprint 7 — Cleanup dan modernization

- Quarantine page lama.
- Bersihkan duplicate image dan vendor demo.
- Dependency upgrade.
- Refactor monolith dan tambah automated tests.

## Perkara yang Tidak Patut Dibuat Terlalu Awal

- Jangan delete semua fail `_old` sebelum semak access log.
- Jangan padam `public_img` berdasarkan duplicate hash sahaja; database mungkin masih merujuk filename tertentu.
- Jangan upgrade Bootstrap/Bower serentak dengan perubahan authentication.
- Jangan tukar semua password hash secara bulk tanpa migration dan recovery plan.
- Jangan aktifkan HSTS sebelum HTTPS seluruh environment benar-benar stabil.
- Jangan hidupkan CSP enforcement ketat tanpa melalui report-only mode.
- Jangan rotate credential lama sebelum semua consumer berpindah kepada credential baharu.

## Keutamaan Jika Masa Sangat Terhad

Jika hanya mampu buat lima perkara terlebih dahulu:

1. Sekat fail SQL, log, diagnostic, test dan backup daripada web.
2. Matikan execution PHP dalam `public_img`.
3. Tambah authentication dan role guard pada action admin.
4. Rotate semua hardcoded secrets.
5. Gantikan MD5/default-IC password melalui pelan migrasi terkawal.
