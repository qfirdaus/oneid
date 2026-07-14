# Laporan Audit Projek OneID-UAT

**Tarikh audit:** 13 Julai 2026  
**Lokasi projek:** `/var/www/app/oneid-uat`  
**Jenis audit:** Audit statik dan read-only  
**Status:** Tiada kod aplikasi, konfigurasi atau data diubah semasa audit

## 1. Ringkasan Eksekutif

Projek OneID-UAT lulus semakan syntax PHP, tetapi mempunyai risiko keselamatan yang tinggi dan sejumlah besar fail lama, salinan kod, aset template serta endpoint diagnostik yang masih berada dalam document root.

Empat risiko paling kritikal ialah:

1. Operasi pentadbiran boleh dipanggil tanpa kawalan authorization yang mencukupi.
2. Fungsi upload berpotensi membenarkan fail PHP dimasukkan ke folder yang boleh dicapai melalui web.
3. Kredensial database, SMTP, integrasi dan token monitoring disimpan terus dalam source code.
4. Password pengguna menggunakan MD5 serta default/reset password yang berasaskan nombor IC atau ID pengguna.

Cleanup fail lama memang diperlukan, tetapi containment keselamatan perlu didahulukan. Fail lama yang tidak mempunyai rujukan dalaman masih boleh dicapai terus melalui URL dan menambahkan attack surface.

## 2. Skop dan Kaedah Audit

Audit merangkumi:

- Struktur direktori dan inventori fail.
- Konfigurasi Apache dan aplikasi.
- Authentication, authorization, session, cookie dan token SSO.
- Password, OTP dan proses reset password.
- Endpoint API dan integrasi data luar.
- Upload fail, SQL injection dan cross-site scripting.
- Permission fail dan pendedahan fail sensitif.
- Dependency serta aset frontend.
- Pemetaan page aktif, page lama dan fail yang tiada rujukan dalaman.
- Semakan syntax semua fail PHP first-party.

Audit ini tidak merangkumi:

- Penetration test aktif.
- Login menggunakan akaun sebenar.
- Perubahan atau query terhadap live database.
- Pelaksanaan sync pengguna.
- Ujian upload fail.
- Analisis access log Apache kerana log tersebut tidak tersedia dalam direktori projek.
- Pengesahan consumer luar bagi endpoint integrasi.

## 3. Gambaran Projek

- Saiz projek: kira-kira 126 MB.
- Jumlah fail: 5,319.
- Jumlah kod PHP first-party: kira-kira 28,700 baris.
- `vendors/`: kira-kira 73 MB.
- `vendors/bower_components`: 3,898 fail.
- Semua 5,318 fail yang diperiksa mempunyai permission world-writable `777`.
- Direktori deployment ini bukan Git working tree.
- Tiada test suite automatik ditemui.
- Tiada root Composer configuration atau dependency lockfile ditemui.

## 4. Penemuan Kritikal

### AUD-001: Endpoint operasi tidak mempunyai authorization menyeluruh

**Severity:** Kritikal  
**Fail utama:** `lib/q_func.php`

`lib/q_func.php` mengandungi 52 blok operasi yang diaktifkan berdasarkan kewujudan field POST. Operasi tersebut termasuk:

- Ubah konfigurasi SSO.
- Tambah, edit dan padam aplikasi.
- Upload icon aplikasi.
- Tambah, deactivate, reactivate dan reset pengguna.
- Ubah ACL kategori dan pengguna.
- Jalankan sync pengguna.
- Lihat active session, token dan audit log.
- Ubah password.

Tiada pemeriksaan menyeluruh pada permulaan endpoint untuk memastikan caller:

- Telah login.
- Mempunyai session yang sah.
- Mempunyai role administrator.
- Dibenarkan menjalankan action tertentu.

`admin/dashboard.php` juga tidak mempunyai role guard pada permulaan fail. Semakan `login_user_type` di dashboard pengguna hanya mengawal paparan link Administrator, bukan akses sebenar.

**Kesan:** Pengguna biasa atau request tanpa session berpotensi memanggil operasi sensitif secara terus menggunakan HTTP POST.

**Cadangan:**

- Tambah authentication middleware pada semua action kecuali login dan proses reset password yang diperlukan.
- Laksanakan authorization server-side mengikut role dan action.
- Gunakan default-deny: action tidak dikenali atau tidak dibenarkan mesti ditolak.
- Pecahkan action admin dan user kepada controller berasingan.

### AUD-002: Upload fail tidak selamat

**Severity:** Kritikal  
**Lokasi:** `lib/q_func.php`, sekitar blok `action_add_new_app` dan `action_edit_app_info`

Extension fail diambil terus daripada nama asal dan fail disimpan ke `public_img/`. Tiada bukti kewujudan:

- Extension allowlist.
- MIME type validation.
- Pemeriksaan kandungan imej sebenar.
- Penukaran semula imej ke format selamat.
- Had saiz yang jelas pada peringkat aplikasi.
- Larangan PHP execution dalam `public_img`.

Nama fail menggunakan timestamp saat, yang juga boleh menyebabkan collision jika lebih daripada satu upload berlaku dalam saat yang sama.

**Kesan:** Jika fail `.php` boleh di-upload dan Apache mengeksekusi PHP dalam `public_img`, penyerang berpotensi mengambil alih server. Risiko menjadi lebih tinggi kerana action upload tidak dilindungi oleh authorization menyeluruh.

**Cadangan:**

- Simpan upload di luar document root.
- Hanya benarkan format imej yang diperlukan.
- Semak MIME dan kandungan menggunakan image library.
- Jana nama fail menggunakan random cryptographic.
- Re-encode imej sebelum disimpan.
- Tetapkan web server supaya tidak mengeksekusi script dalam direktori upload.

### AUD-003: Secrets dan kredensial berada dalam source code

**Severity:** Kritikal

Kredensial atau token ditemui dalam:

- `lib/config.php`
- `lib/q_func.php`
- `lib/external_data_source_API.php`
- `idms.php`
- `skp_api.php`
- `lib/skp_api.php`
- `diag/agent.php`
- `diagnostic/index.php`
- Beberapa fail lama dan komen kod

Jenis rahsia termasuk:

- Database username dan password.
- SMTP username dan password.
- ODBC/Sybase credentials.
- Monitoring agent token.

Nilai rahsia sengaja tidak disalin ke dalam laporan ini.

**Kesan:** Sesiapa yang mendapat akses kepada source, backup, web-served file atau snapshot deployment boleh mendapatkan akses kepada sistem berkaitan.

**Cadangan:**

- Rotate semua rahsia yang ditemui; jangan sekadar memindahkannya.
- Pindahkan konfigurasi sensitif ke secret manager atau environment variables di luar document root.
- Pastikan fail konfigurasi sensitif tidak boleh dimuat turun melalui web.
- Hapuskan kredensial daripada salinan lama dan sejarah repository jika ada.

### AUD-004: Password menggunakan MD5 dan nilai default mudah dijangka

**Severity:** Kritikal  
**Lokasi utama:** `lib/q_func.php`, `lib/Database.php`, `lib/sync_user_runner.php`

Password dibandingkan sebagai hash MD5. Password awal dan reset pula dibina daripada nombor IC, ID pengguna atau nilai statik tertentu.

**Kesan:**

- MD5 terlalu pantas dan mudah diserang secara offline.
- Nombor IC dan ID pengguna boleh dijangka atau diketahui.
- Kebocoran database boleh membawa kepada pemulihan password dengan cepat.
- Reset password mengembalikan akaun kepada credential yang mudah diteka.

**Cadangan:**

- Gunakan `password_hash()` dengan Argon2id atau bcrypt.
- Gunakan `password_verify()` semasa login.
- Migrasi hash secara berperingkat selepas login berjaya.
- Hentikan penggunaan nombor IC atau ID sebagai password.
- Gunakan temporary reset token satu kali dengan expiry pendek dan wajib tetapkan password baharu.

## 5. Penemuan Risiko Tinggi

### AUD-005: Login limiter hanya di browser

Login limiter dalam `index.php` menggunakan JavaScript dan cookie pengguna. Ia boleh dipintas dengan membuang cookie atau menghantar request terus menggunakan tool HTTP.

**Cadangan:** Laksanakan rate limiting server-side berdasarkan gabungan akaun, IP dan risk signal. Rekod serta hadkan percubaan login dan OTP.

### AUD-006: Token SSO tidak cukup kuat dan terdedah

Token dijana menggunakan `uniqid()` dan bukannya random cryptographic. Token juga dihantar menggunakan query parameter `new_sso_cre`.

Risiko:

- Token lebih mudah dijangka berbanding `random_bytes()`.
- Query string boleh masuk browser history, access log dan referrer.
- Token disimpan plaintext dalam database.
- Endpoint API memulangkan user packet berdasarkan token.

**Cadangan:**

- Jana token menggunakan `random_bytes()`.
- Simpan hash token dalam database.
- Jangan pindahkan token melalui URL.
- Gunakan short-lived authorization code untuk pertukaran server-to-server.
- Rotate token selepas login dan perubahan privilege.

### AUD-007: Cookie mengandungi token dan data pengguna tanpa security flags

`sso_cre` menyimpan token bersama keseluruhan user packet. Cookie tidak ditetapkan secara jelas dengan `HttpOnly`, `Secure` dan `SameSite`.

Tiada penggunaan `session_regenerate_id()` ditemui selepas login.

**Cadangan:**

- Cookie hanya perlu menyimpan opaque session identifier.
- Tetapkan `Secure`, `HttpOnly` dan `SameSite=Lax` atau `Strict` mengikut flow SSO.
- Regenerate session ID selepas login dan perubahan role.
- Minimumkan data peribadi di browser.

### AUD-008: TLS verification dimatikan

Beberapa integrasi cURL menetapkan:

- `CURLOPT_SSL_VERIFYHOST` kepada false.
- `CURLOPT_SSL_VERIFYPEER` kepada false.

Lokasi termasuk `lib/SSO_IDP_INC.php`, `lib/sso_IDP_sub.php`, `lib/sso_IDP_index.php`, `lib/external_data_source_API.php`, `lib/skp_api.php` dan diagnostic agent.

**Kesan:** Komunikasi HTTPS terdedah kepada man-in-the-middle.

### AUD-009: Endpoint data peribadi tanpa authentication yang jelas

`idms.php` boleh memulangkan nama, nombor staf, email, jabatan dan jawatan.

`skp_api.php` boleh memulangkan maklumat pelajar yang lebih sensitif, termasuk nombor IC, alamat, telefon dan maklumat keluarga.

Tiada authentication atau client authorization yang jelas pada endpoint tersebut.

**Cadangan:**

- Hadkan endpoint kepada consumer berdaftar.
- Gunakan service authentication dan authorization.
- Hadkan field kepada data minimum yang diperlukan.
- Tambah audit log dan rate limiting.
- Pertimbangkan network allowlist sebagai lapisan tambahan, bukan kawalan tunggal.

### AUD-010: SQL injection dalam integrasi ODBC

Query dalam `skp_api.php`, `lib/skp_api.php` dan `lib/external_data_source_API.php` mencantum input pengguna secara terus ke SQL.

`idms.php` melakukan manual quote escaping, tetapi pendekatan parameterized query masih lebih selamat dan konsisten.

**Cadangan:** Gunakan parameterized ODBC query atau stored procedure dengan parameter yang divalidasi.

### AUD-011: Cross-site scripting

Contoh yang dikenal pasti:

- `admin/user_list.php` mencetak `category_name` daripada query string tanpa output encoding.
- `admin/dashboard.php` dan dashboard user membina HTML menggunakan nilai response database dan `.html()`.
- Data nama pengguna, aplikasi, kategori, URL serta log dimasukkan ke DOM melalui string concatenation.

**Kesan:** Reflected atau stored XSS berpotensi mencuri token/session dan menjalankan action sebagai pengguna.

**Cadangan:**

- Gunakan output encoding mengikut konteks.
- Gunakan `textContent` atau jQuery `.text()` untuk data biasa.
- Elakkan membina HTML menggunakan string yang mengandungi data tidak dipercayai.
- Tambah Content Security Policy sebagai lapisan pertahanan tambahan.

### AUD-012: Semua fail world-writable

Semua 5,318 fail yang diperiksa mempunyai permission `777`.

**Kesan:** Jika satu proses atau akaun pada server dikompromi, attacker berpotensi mengubah PHP, JavaScript, konfigurasi dan aset aplikasi.

**Cadangan:**

- PHP/config biasa: sekitar `640` atau `644` mengikut model ownership.
- Direktori biasa: sekitar `750` atau `755`.
- Direktori upload/log: beri write hanya kepada user/group servis yang memerlukan.
- Jangan beri execute bit kepada fail biasa tanpa keperluan.

### AUD-013: Fail sensitif dan diagnostic berada dalam document root

Fail `sso_db.sql` mengandungi dump lama termasuk data pengguna, password hash dan token. Fail lain yang berisiko:

- `cron/logs/sync_cron.log`
- `diag/agent.php`
- `diagnostic/index.php`
- `test.php`
- `atest.php`
- Fail bernama `_old`, `copy` dan snapshot bertarikh

`.htaccess` tidak menyekat extension `.sql`, `.log`, fail backup atau diagnostic.

**Cadangan:** Pindahkan fail operasi keluar daripada document root dan gunakan explicit deny rule untuk extension sensitif.

## 6. Penemuan Risiko Sederhana

### AUD-014: Error handling mendedahkan maklumat dalaman

`lib/config.php` menghidupkan `display_errors` dan global exception handler memulangkan mesej exception terus kepada browser.

**Cadangan:** Paparkan generic error kepada pengguna dan simpan butiran teknikal dalam log yang tidak boleh dicapai melalui web.

### AUD-015: Domain environment di-hardcode

`oneid.local` terdapat dalam:

- `.htaccess`
- `lib/config.php`
- `lib/SSO_IDP_INC.php`
- Logout admin dan user

Ini menjadikan deployment ke UAT/production mudah tersalah redirect dan sukar dikendalikan.

### AUD-016: Tiada security headers

Tiada konfigurasi jelas untuk:

- Content-Security-Policy.
- Strict-Transport-Security.
- X-Content-Type-Options.
- Frame protection.
- Referrer-Policy.
- Permissions-Policy.

### AUD-017: Dependency dan frontend legacy

- Bower masih digunakan.
- Bootstrap 3.3.7 masih dibundle.
- PHPMailer yang dibundle ialah 6.1.8.
- Device Detector yang dibundle ialah 5.0.1.
- Tiada root dependency lockfile.
- Dependency disalin terus ke repository/deployment.

Versi ini perlu melalui dependency vulnerability review berasingan sebelum upgrade kerana audit ini tidak menjalankan active online vulnerability resolution.

### AUD-018: Encoding tidak konsisten

Konfigurasi aplikasi menggunakan `latin1`, sedangkan dump database lama menggunakan `utf8mb4`.

**Kesan:** Risiko data rosak, character conversion error dan perbezaan validation/escaping.

### AUD-019: Struktur kod sukar diselenggara

- `admin/dashboard.php` hampir 4,000 baris.
- `lib/q_func.php` menggabungkan authentication, admin, upload, OTP, ACL, sync dan email.
- Banyak fungsi lama dan duplicate code masih berada dalam deployment.
- JavaScript dibina inline dalam page PHP yang besar.
- Banyak `console.log()` masih ada, termasuk logging input OTP pada frontend.

### AUD-020: Audit log mempercayai forwarded headers

IP pengguna dibaca daripada beberapa forwarded header tanpa bukti trusted proxy validation. Header ini boleh dipalsukan oleh client jika web server tidak membersihkannya.

## 7. Pemetaan Page dan Endpoint Aktif

### Dirujuk secara aktif dalam flow aplikasi

| Fail | Fungsi |
|---|---|
| `index.php` | Login page utama |
| `page/dashboard.php` | Dashboard pengguna |
| `admin/dashboard.php` | Dashboard pentadbir |
| `admin/user_list.php` | Senarai pengguna kategori; dipanggil dari dashboard admin |
| `page/logout.php` | Logout pengguna |
| `admin/logout.php` | Logout pentadbir |
| `api.php` | Token validation API |
| `lib/q_func.php` | Endpoint operasi AJAX utama |
| `lib/SSO_IDP_INC.php` | Pemeriksaan token/cookie SSO |
| `lib/config.php` | Konfigurasi dan bootstrap database |
| `lib/Database.php` | Data-access layer |
| `lib/external_data_source_API.php` | Integrasi data staf/pelajar |
| `lib/sync_user_runner.php` | Logic sync pengguna |
| `cron/run_sync.php` | Entry point CLI scheduled sync |
| `page/const/top.php` | Top navigation pengguna |
| `admin/const/top.php` | Top navigation pentadbir |

## 8. Calon Cleanup Berkeyakinan Tinggi

Fail di bawah tiada import, redirect, link atau reference dalaman yang ditemui dan mempunyai pengganti yang lebih baharu atau nama yang jelas menunjukkan salinan lama.

| Calon | Sebab |
|---|---|
| `index1.php` | Login page versi lama; tiada reference dalaman |
| `index_.php` | Login page legacy/template lama |
| `index_20250806.php` | Snapshot bertarikh yang telah diganti `index.php` |
| `page/dashboard2.php` | Versi pertengahan sebelum dashboard semasa |
| `page/dashboard_old.php` | Dashboard pengguna lama |
| `admin/dashboard_old.php` | Dashboard pentadbir lama |
| `api_old.php` | Versi lama `api.php` dengan perbezaan kecil parsing JSON |
| `lib/q_func_old.php` | Controller lama sebelum perubahan sync/upload |
| `lib/SSO_IDP_INC - Copy.php` | Salinan byte-for-byte sama dengan fail aktif |
| `page/const/left copy.php` | Salinan sidebar template |
| `page/const/left.php` | Include sidebar dikomen pada dashboard semasa |
| `admin/const/left.php` | Include sidebar dikomen pada dashboard admin semasa |
| `test.php` | Endpoint test yang memaparkan host dan URL |
| `atest.php` | Skrip eksperimen database; berhenti selepas memaparkan 32/64-bit |

### Perhatian

"Tidak digunakan secara dalaman" tidak bermaksud selamat dipadam terus. Selagi fail PHP berada dalam document root, ia masih boleh dipanggil melalui URL. Access log perlu disemak sebelum deletion untuk memastikan tiada bookmark lama, automation atau consumer luar masih menggunakannya.

## 9. Endpoint yang Memerlukan Pengesahan Consumer Luar

Fail berikut tiada reference dalaman yang jelas tetapi mungkin digunakan oleh integrasi atau monitoring luar:

| Fail | Kemungkinan penggunaan |
|---|---|
| `idms.php` | API senarai staf/jabatan |
| `skp_api.php` | API rekod dan sync pelajar |
| `lib/skp_api.php` | Helper/integrasi SKP lama |
| `diag/agent.php` | Monitoring server agent |
| `diagnostic/index.php` | AI-NOC monitoring agent |
| `lib/sso_IDP_index.php` | Implementasi SSO lama/alternatif |
| `lib/sso_IDP_sub.php` | Implementasi SSO lama/alternatif |
| `api_old.php` | Mungkin masih dipanggil client lama walaupun mempunyai pengganti |

Sebelum cleanup, semak:

- Access log 30 hingga 90 hari.
- Scheduled task dan cron.
- Reverse proxy atau monitoring configuration.
- Dokumentasi integrasi.
- Consumer application dan pemilik sistem.

## 10. Aset dan Dependency Berlebihan

### Vendor bundle

- `vendors/` menggunakan kira-kira 73 MB.
- `vendors/bower_components` mempunyai 3,898 fail.
- Sekurang-kurangnya 711 fail atau sekitar 19.3 MB ialah docs, tests, examples, source maps dan executable pembangunan.
- Folder vendor mengandungi source dan demo yang tidak diperlukan untuk runtime biasa.

### JavaScript demo

`dist/js` mempunyai 59 fail. Page aktif hanya memuatkan sebahagian kecil, antaranya:

- `dropdown-bootstrap-extended.js`
- `form-file-upload-data.js`
- `init.js`
- `jquery.slimscroll.js`
- `widgets-data.js`

Fail demo chart, ecommerce, editor dan sample page lain ialah calon cleanup selepas asset-reference verification.

### Public image

- `public_img` mempunyai 86 fail.
- Terdapat 22 kumpulan duplicate-content.
- Sebanyak 31 fail ialah salinan tambahan bagi kandungan yang sama.

Jangan padam berdasarkan filename sahaja. Nama icon aplikasi disimpan secara dinamik dalam `sp_list.sp_image`; cross-check live database perlu dilakukan terlebih dahulu.

### Reference aset rosak

Dashboard merujuk `img/mock1.png`, tetapi fail yang tersedia ialah `img/mock1.jpg`.

### Fail sistem/metadata yang tidak diperlukan

Beberapa fail `.DS_Store` dan `Thumbs.db` turut berada dalam deployment.

## 11. Keputusan Verification

- Semua fail PHP first-party lulus `php -l`.
- Tiada syntax error ditemui.
- Tiada automated test suite ditemui.
- Tiada build/test command projek yang boleh mengesahkan business flow secara automatik.
- Audit tidak menjalankan request yang mengubah database, session pengguna, upload atau sync.
- Tiada fail aplikasi diubah semasa audit asal.

Lulus syntax tidak bermaksud aplikasi selamat atau business flow betul. Ia hanya mengesahkan parser PHP menerima source tersebut.

## 12. Urutan Remediation yang Disyorkan

Pelan implementasi yang lebih terperinci dan disusun daripada perubahan ringan kepada perubahan besar tersedia dalam `docs/PELAN_REMEDIASI_BERFASA_ONEID_UAT.md`.

### Fasa 0: Containment segera

1. Hadkan akses kepada `lib/q_func.php` dan action admin.
2. Matikan execution script dalam `public_img`.
3. Sekat akses web kepada `.sql`, `.log`, diagnostic, test dan fail lama.
4. Rotate semua database, SMTP, ODBC dan monitoring secrets.
5. Betulkan permission `777`.

### Fasa 1: Authentication dan authorization

1. Tambah server-side role guard.
2. Tambah CSRF protection pada semua state-changing request.
3. Migrasi password daripada MD5.
4. Tambah server-side login dan OTP rate limiting.
5. Betulkan session regeneration dan cookie flags.
6. Gantikan token generation serta flow token dalam URL.

### Fasa 2: API dan data protection

1. Lindungi `idms.php` dan `skp_api.php` dengan service authentication.
2. Kurangkan data peribadi yang dipulangkan.
3. Tukar query ODBC kepada parameterized query.
4. Aktifkan TLS certificate verification.
5. Tambah schema validation dan standard error response.

### Fasa 3: Cleanup terkawal

1. Kumpulkan access log 30 hingga 90 hari.
2. Sahkan scheduled task, monitoring dan consumer luar.
3. Pindahkan calon orphan ke quarantine di luar document root.
4. Jalankan smoke test login, dashboard, admin, SSO, reset password dan sync.
5. Pantau error/access log semasa tempoh quarantine.
6. Padam secara kekal hanya selepas tiada penggunaan ditemui.

### Fasa 4: Maintainability

1. Pecahkan `q_func.php` mengikut domain.
2. Pisahkan PHP view, JavaScript dan data-access layer.
3. Tambah dependency manager serta lockfile.
4. Tambah unit, integration dan security tests.
5. Standardkan environment configuration.
6. Migrasi encoding kepada `utf8mb4` secara terkawal.

## 13. Definition of Done untuk Cleanup

Cleanup dianggap selamat apabila:

- Access log menunjukkan tiada request kepada calon orphan sepanjang tempoh pemerhatian.
- Tiada scheduled task atau consumer luar merujuk fail tersebut.
- Fail telah melalui tempoh quarantine di luar document root.
- Login pengguna dan admin berfungsi.
- SSO ke service provider berfungsi.
- Reset password dan OTP berfungsi.
- Scheduled sync berjaya.
- Icon aplikasi masih dipaparkan selepas cross-check database.
- Tiada error baharu dalam web/PHP log.
- Backup dan rollback plan telah disediakan.

## 14. Kesimpulan

Projek ini mempunyai dua jenis hutang teknikal yang saling berkait:

1. **Security debt:** authorization, upload, secrets, password, token, API dan permission.
2. **Cleanup debt:** page lama, duplicate controller, diagnostic endpoint, vendor demo dan aset duplicate.

Keutamaan pertama bukan memadam fail lama secara rawak. Keutamaan pertama ialah mengurangkan exposure, memastikan authorization server-side dan rotate secrets. Selepas itu, cleanup perlu dilakukan menggunakan access log, quarantine dan smoke test supaya integrasi lama tidak terputus secara tidak sengaja.
