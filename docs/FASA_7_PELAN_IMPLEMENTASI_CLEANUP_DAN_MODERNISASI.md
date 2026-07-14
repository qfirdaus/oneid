# Fasa 7 — Pelan Implementasi Cleanup dan Modernisasi OneID-UAT

**Tarikh dokumen:** 14 Julai 2026  
**Status:** Rujukan implementasi; **belum dilaksanakan**  
**Prasyarat utama:** Boundary integrasi Fasa 6 stabil dan consumer legacy dikenal pasti.

**Kemaskini 14 Julai 2026, 03:01 +0800:** R0–R4, R5.0 dan R5.1A–R5.1D lengkap. Semua aset, upload, favicon dan subset vendor dalam public-root kini fizikal; symlink public ialah 0. Owner mengesahkan hard refresh/visual normal dan log tiada asset regression. Source lama serta rollback evidence dikekalkan sepanjang observation. R5.2 wrapper/application refactor dan cleanup executable PHP kekal tertakluk kepada characterization, observation dan consumer evidence.

## 1. Tujuan

Fasa 7 mengurangkan attack surface dan technical debt tanpa mengubah fungsi OneID secara serentak. Ia meliputi:

- quarantine dan pembuangan page/controller lama;
- pemindahan fail operasi keluar document root;
- cleanup imej, aset template, demo dan metadata;
- pengurusan dependency yang reproducible;
- pengurangan monolith PHP/JavaScript;
- penyelesaian baki XSS, CSP dan encoding secara berperingkat;
- permanent deletion hanya selepas tempoh pemerhatian dan approval.

Fasa ini bukan satu operasi “padam semua fail lama”. Setiap batch mesti mempunyai evidence, smoke test dan rollback sendiri.

## 2. Perkara Yang Tidak Akan Digabungkan Dalam Satu Change

Perubahan berikut tidak boleh dibuat serentak:

- quarantine PHP legacy dan upgrade Bootstrap/jQuery;
- dependency upgrade dan refactor business logic;
- cleanup `public_img` dan perubahan rekod `sp_list`;
- migrasi encoding database dan perubahan authentication/SSO;
- CSP enforcement dan pemindahan semua inline JavaScript dalam change yang sama;
- deletion source dan deletion quarantine pada hari yang sama.

Tujuannya ialah memastikan punca masalah boleh dikenal pasti dan rollback tidak menjadi kabur.

## 3. Entry Gate Fasa 7

### 3.1 Gate wajib sebelum cleanup executable PHP

- [ ] Fasa 6B sudah mempunyai mode `legacy/dual/new` per consumer.
- [ ] Owner dan PIC teknikal consumer integrasi telah disahkan.
- [ ] Access log tersedia sekurang-kurangnya 30 hari; 90 hari bagi endpoint/vendor berkala.
- [ ] Log membezakan trafik sebenar, reverse proxy, health check dan smoke test dalaman.
- [ ] Tiada consumer sah menggunakan calon yang hendak di-quarantine.
- [ ] Backup database, source, konfigurasi dan senarai permission telah disahkan boleh dipulihkan.
- [ ] Baseline login, logout, reset password, dashboard, admin, SSO, API dan sync direkodkan.
- [ ] Change owner dan rollback owner tersedia dalam maintenance window.

### 3.2 Gate sebelum dependency upgrade

- [ ] Senarai aset yang dimuatkan oleh page aktif telah lengkap.
- [ ] Browser minimum yang perlu disokong telah dipersetujui.
- [ ] Automated smoke/characterization test tersedia.
- [ ] Build boleh dihasilkan semula daripada lockfile.
- [ ] UAT mempunyai salinan data atau fixture yang selamat.

### 3.3 Keadaan semasa

Gate di atas **belum lengkap** kerana Fasa 6 masih berada pada 6A `observe`. Kerja inventori dan penyediaan test boleh bermula, tetapi quarantine endpoint integrasi dan permanent deletion belum boleh bermula.

## 4. Snapshot Repository Pada 14 Julai 2026

| Item | Snapshot |
|---|---:|
| Saiz keseluruhan workspace | Kira-kira 158 MB |
| Jumlah fail | 6,842 |
| `vendors/` | 73 MB |
| `vendors/bower_components/` | 70 MB, 3,898 fail |
| `assetsM/` | 33 MB |
| `dist/` | 5.8 MB |
| `public_img/` | 4.0 MB, 86 fail imej |
| Aplikasi aktif dalam `sp_list` | 34 |
| Imej unik dirujuk aplikasi aktif | 34; semuanya wujud |
| Imej tidak dirujuk aplikasi aktif | 52 |
| Duplicate-content dalam `public_img` | 22 kumpulan, 31 salinan tambahan |
| Root dependency lockfile | Tiada |

Monolith utama:

| Fail | Baris semasa |
|---|---:|
| `admin/dashboard.php` | 3,905 |
| `page/dashboard.php` | 1,261 |
| `lib/q_func.php` | 1,107 |
| `lib/Database.php` | 1,040 |
| `index.php` | 1,095 |

Snapshot ini perlu dijana semula pada hari implementasi; ia bukan approval untuk delete.

## 5. Batasan Evidence Access Log Semasa

Log Nginx yang tersedia ketika dokumen ini ditulis hanya meliputi **11–14 Julai 2026**. Tempoh ini terlalu pendek untuk keputusan deletion.

Beberapa hit ke `dashboard_old`, `dashboard2`, `q_func_old`, `test.php` dan `atest.php` berasal atau berkemungkinan berasal daripada audit/smoke test remediation. Oleh itu hit tersebut perlu ditanda menggunakan request ID, source IP dan change window supaya tidak disalah anggap sebagai consumer sebenar.

Sebelum quarantine, kumpulkan:

- access log 30–90 hari;
- PHP/web error log;
- reverse proxy dan load balancer route;
- cron/systemd/Task Scheduler configuration;
- monitoring dan health-check configuration;
- source search di semua consumer yang berada dalam kawalan;
- pengesahan bertulis vendor/PIC bagi consumer luar.

“Tiada reference dalam repository” tidak sama dengan “tidak digunakan”.

## 6. Strategi Quarantine

### 6.1 Lokasi

Gunakan lokasi di luar document root, contohnya:

```text
/var/www/app/oneid-quarantine/<change-id>/<relative-source-path>
```

Direktori quarantine:

- tidak dipetakan oleh Nginx/Apache;
- hanya boleh dibaca oleh deployment owner;
- tidak world-writable;
- mempunyai tarikh luput dan owner;
- disertakan manifest checksum.

### 6.2 Manifest wajib

Setiap fail yang dipindahkan mesti direkodkan dengan:

| Medan | Tujuan |
|---|---|
| Change ID dan tarikh | Jejak change |
| Source path | Lokasi asal tepat |
| Quarantine path | Lokasi pemulihan |
| SHA-256, saiz dan permission | Sahkan integriti rollback |
| Sebab klasifikasi | Duplicate, legacy, orphan atau operational |
| Evidence penggunaan | Reference search, log window dan owner response |
| Replacement | Fail/route aktif yang menggantikan calon |
| Rollback owner | PIC pemulihan |
| Earliest delete date | Minimum selepas observation window |

### 6.3 Lifecycle

```text
Inventori → Evidence → Block/410 → Quarantine → Smoke test
          → Observation 30–90 hari → Approval → Permanent delete
```

Jika request sah muncul semasa observation, pulihkan fail atau route dahulu, kemudian buat semula analisis consumer. Jangan beri compatibility bypass kekal tanpa owner dan expiry date.

## 7. Subfasa 7.0 — Baseline dan Characterization Test

**Risiko:** Rendah  
**Tujuan:** Wujudkan safety net sebelum file movement atau refactor.

### Kerja

1. Capture route/status/header baseline bagi login, dashboard, logout dan endpoint API.
2. Wujudkan akaun fixture UAT untuk user dan admin; jangan gunakan akaun sebenar.
3. Automasi flow minimum:
   - login berjaya/gagal;
   - forced password change;
   - forgot password/OTP dengan mailbox UAT;
   - user dan admin authorization;
   - tambah/edit aplikasi menggunakan fixture;
   - SSO pilot login/logout/error callback;
   - API `401/403/429` dan scope;
   - sync dry-run atau fixture tanpa menyentuh sumber sebenar.
4. Capture response contract bagi action aktif `q_func.php` dan API.
5. Rekod baseline saiz, checksum, permission dan URL map.

### Exit criteria

- [ ] Test boleh dijalankan berulang kali tanpa meninggalkan data ujian.
- [ ] Test failure menghasilkan bukti route/action yang rosak.
- [ ] Semua flow kritikal mempunyai rollback smoke test.

## 8. Subfasa 7.1 — Metadata dan Fail Operasi Ringan

**Risiko:** Rendah  
**Tujuan:** Buang fail yang tiada nilai runtime dan rapikan boundary deployment.

### Calon metadata

- `.DS_Store` di root, `admin/`, `lib/`, `page/` dan `vendors/`;
- `Thumbs.db` di `img/` dan `assetsM/images/`;
- source map, docs, tests dan examples vendor yang disahkan tidak diperlukan runtime;
- fail build temp atau editor backup selepas reference scan.

### Fail operasi

- log cron mesti kekal di luar document root;
- script `.bat`/`.ps1` hanya disimpan dalam deployment tooling jika masih diperlukan;
- dump SQL tidak boleh berada dalam web release artifact;
- diagnostic output dan backup tidak boleh disimpan bersama aplikasi.

### Guardrail

- Tambah pattern ke `.gitignore` dan deployment exclude list sebelum cleanup.
- Jangan padam `sso_db.sql` sehingga tujuan sebagai reference/migration disahkan dan salinan terkawal tersedia.
- Jangan pindahkan script scheduler sebelum inventori cron/systemd/Windows Task Scheduler selesai.

## 9. Subfasa 7.2 — Quarantine Page dan Controller Legacy

**Risiko:** Sederhana hingga tinggi  
**Strategi:** Batch kecil, maksimum 3–5 laluan setiap change.

### 9.1 Calon berkeyakinan tinggi selepas log gate

| Calon | Pengganti/keadaan | Status HTTP snapshot | Keputusan belum dibuat |
|---|---|---:|---|
| `index1.php` | `index.php` | 302 | Quarantine selepas 30 hari tiada trafik sah |
| `index_.php` | `index.php` | 302 | Sama |
| `index_20250806.php` | `index.php` | 302 | Sama |
| `page/dashboard2.php` | `page/dashboard.php` | 302 | Pastikan sidebar lama tiada consumer |
| `page/dashboard_old.php` | `page/dashboard.php` | 302 | Quarantine batch dashboard user |
| `admin/dashboard_old.php` | `admin/dashboard.php` | 302 | Quarantine berasingan daripada user dashboard |
| `lib/SSO_IDP_INC - Copy.php` | `lib/SSO_IDP_INC.php` | Tidak perlu route awam | Kandungan sudah diverge; jangan anggap byte-identical |
| `page/const/left copy.php` | Sidebar legacy | Tidak berkenaan | Quarantine bersama dashboard2 selepas reference scan |
| `page/const/left.php` | Hanya dipanggil dashboard2 | Tidak berkenaan | Quarantine selepas dashboard2 |
| `admin/const/left.php` | Include aktif dikomen | Tidak berkenaan | Sahkan tiada include dinamik |

### 9.2 Calon yang memerlukan consumer/route verification lebih ketat

| Calon | Risiko |
|---|---|
| `api_old.php` | Snapshot GET masih HTTP 200; mungkin dipanggil consumer POST lama |
| `lib/q_func_old.php` | Telah dikawal tetapi mungkin masih dipanggil frontend/bookmark lama |
| `lib/sso_IDP_index.php` | Snapshot direct request HTTP 200; mungkin implementasi SSO alternatif |
| `lib/sso_IDP_sub.php` | Redirect ke IDP; mungkin digunakan oleh aplikasi lama |
| `lib/skp_api.php` | Helper luar yang tiada reference jelas tetapi berkaitan integrasi data |
| `diag/agent.php` | HTTP 401 menunjukkan containment, bukan bukti tiada monitoring consumer |
| `test.php`, `atest.php` | HTTP 404 semasa snapshot; masih perlu dikeluarkan daripada release artifact |

`diagnostic/index.php` tidak wujud dalam snapshot semasa. Rekod sejarah penghapusannya perlu dikekalkan; jangan cipta semula sebagai compatibility route.

**Keputusan owner 14 Julai 2026:** `diag/agent.php` disahkan tidak digunakan dan scheduled sync/cron tidak lagi diperlukan. Keputusan ini membenarkan kedua-duanya tidak dibawa ke public-root R4. Ia bukan arahan permanent delete serta-merta; fail berkaitan perlu melalui manifest quarantine dan observation/approval cleanup yang sama seperti calon lain.

**Compatibility R4:** Oleh sebab caller dua endpoint SSO lama belum dapat disahkan, `public/lib/sso_IDP_index.php` dan `public/lib/sso_IDP_sub.php` diwujudkan sebagai wrapper sementara dengan structured audit tanpa token. Wrapper tidak boleh dihapuskan dalam cleanup Fasa 7 sehingga Fasa 6B dan exit criteria dalam `R4_COMPATIBILITY_SSO_LEGACY.md` selesai.

### Batch disyorkan

1. **7.2A:** tiga login snapshot.
2. **7.2B:** dashboard user lama dan sidebar berkaitan.
3. **7.2C:** dashboard admin lama dan sidebar.
4. **7.2D:** test/experimental endpoint.
5. **7.2E:** old controller/API selepas Fasa 6B consumer evidence.
6. **7.2F:** old SSO implementation selepas semua consumer berada dalam registry.

Setiap batch mesti mempunyai deployment, smoke test dan observation window sendiri.

## 10. Subfasa 7.3 — Cleanup `public_img`

**Risiko:** Sederhana  
**Prinsip:** Database reference mengatasi duplicate hash.

### Snapshot semasa

- 86 fail imej;
- 34 filename unik dirujuk oleh 34 aplikasi aktif;
- semua 34 reference wujud;
- 52 fail tidak dirujuk oleh aplikasi aktif;
- 22 kumpulan duplicate-content, menghasilkan 31 salinan tambahan.

### Algoritma keputusan

1. Export `sp_id`, `sp_image`, status aktif/tidak aktif dan tarikh kemas kini.
2. Cross-check semua environment, bukan UAT sahaja.
3. Cari reference filename dalam PHP, JavaScript, CSS, HTML, database dan access log.
4. Fail yang dirujuk **tidak boleh dipadam** walaupun hash sama dengan fail lain.
5. Fail tidak dirujuk dipindah ke quarantine dengan manifest.
6. Jalankan dashboard user/admin dan semak semua icon.
7. Pantau 30–90 hari sebelum delete.

### Rollback

Pulihkan filename yang sama ke path asal. Jangan menggantikan dengan nama fail duplicate lain tanpa mengemas kini reference secara transactional.

## 11. Subfasa 7.4 — Cleanup Aset Template dan Demo

**Risiko:** Sederhana  
**Sasaran utama:** `vendors/`, `dist/`, `assetsM/`, `img/` dan demo JavaScript.

### Kaedah

1. Crawl page aktif selepas login sebagai user dan admin.
2. Capture network requests bagi semua tab/modal/action.
3. Gabungkan browser coverage dengan static reference scan.
4. Tandakan aset sebagai:
   - runtime-critical;
   - lazy/dynamic;
   - build-only;
   - docs/test/example;
   - unknown.
5. Hanya quarantine kategori docs/test/example yang mempunyai evidence mencukupi.
6. Uji cache-busted deployment dan hard refresh.

Dynamic filename, CSS `url()`, icon font dan plugin lazy-load tidak boleh dinilai menggunakan `rg` sahaja.

### Exit criteria

- [ ] Tiada 404 aset pada login, user dan admin flow.
- [ ] Font, icon, modal, upload, table dan date picker berfungsi.
- [ ] Tiada console error baharu.
- [ ] Release artifact boleh dibina semula dengan saiz yang direkodkan.

## 12. Subfasa 7.5 — Dependency dan Build Modernization

**Risiko:** Tinggi  
**Keadaan semasa:** Bower, Grunt lama, dependency disalin ke repository dan tiada lockfile root.

### Urutan

1. Hasilkan Software Bill of Materials bagi PHP dan frontend bundle.
2. Kenal pasti dependency sebenar yang digunakan oleh page aktif.
3. Pisahkan dependency runtime daripada template/demo/build-only.
4. Pilih satu package manager frontend dan commit lockfile.
5. Perkenalkan build reproducible tanpa menukar versi library dahulu.
6. Bandingkan hash/output dan jalankan regression test.
7. Upgrade satu keluarga dependency setiap change:
   - mail/API library;
   - jQuery dan plugin yang bergantung padanya;
   - Bootstrap dan komponen UI;
   - chart/table/editor yang benar-benar digunakan.
8. Buang Bower hanya selepas semua dependency runtime telah dipindahkan.

### Guardrail

- Jangan menjalankan `npm audit fix --force` atau bulk major upgrade tanpa review.
- Jangan mengambil library daripada CDN production tanpa integrity, availability dan privacy decision.
- Semak advisory semasa pada hari implementasi; versi dalam audit 2026 bukan sumber kekal.
- Build tool tidak boleh memerlukan akses internet pada runtime server.

### Exit criteria

- [ ] Fresh checkout menghasilkan aset yang sama melalui command terdokumen.
- [ ] Lockfile wujud dan dependency transitif diketahui.
- [ ] Vulnerability exception mempunyai owner dan expiry.
- [ ] Bower/template dependency yang tidak digunakan telah dikeluarkan.

## 13. Subfasa 7.6 — Refactor Monolith

**Risiko:** Tinggi  
**Prinsip:** Characterize dahulu, extract tanpa mengubah contract, kemudian simplify.

### Sasaran `lib/q_func.php`

Extract mengikut boundary sedia ada:

- public authentication/reset actions;
- user self-service actions;
- admin user management;
- service provider dan ACL;
- upload;
- sync;
- audit/session/token.

`request_security.php` action map mesti kekal default-deny. Setiap extraction perlu mengekalkan status HTTP dan response schema sehingga frontend turut dimigrasikan.

### Sasaran `lib/Database.php`

Pecahkan repository/service mengikut domain:

- authentication/password/OTP;
- user;
- service provider/ACL;
- token/session;
- audit/configuration;
- external sync.

Jangan mencampurkan query extraction dengan schema migration dalam change yang sama.

### Sasaran dashboard dan `index.php`

- pindahkan inline JavaScript ke module/file mengikut feature;
- gunakan data attribute atau JSON bootstrap yang di-encode dengan selamat;
- asingkan view, request handling dan business logic;
- gantikan HTML string untuk data tidak dipercayai dengan DOM API/text rendering;
- kekalkan selector dan response contract semasa extraction awal.

### Exit criteria setiap extraction

- [ ] Characterization test sebelum dan selepas adalah sama.
- [ ] Tiada action terkeluar daripada authorization map.
- [ ] Tiada duplicate implementation aktif.
- [ ] Fail lama di-quarantine hanya selepas caller terakhir berpindah.

## 14. Subfasa 7.7 — XSS, CSP dan Frontend Security Debt

**Risiko:** Tinggi jika dibuat sekaligus.

1. Inventori semua `.html()`, `innerHTML`, inline event handler dan server echo ke JavaScript.
2. Betulkan output encoding mengikut konteks.
3. Gunakan `.text()`/`textContent` untuk data biasa.
4. Pindahkan inline script/style secara feature-by-feature.
5. Semak CSP report-only violation dan bezakan first-party daripada browser extension.
6. Buang `'unsafe-eval'` dahulu jika dependency mengizinkan.
7. Gunakan nonce/hash untuk baki inline script yang diluluskan.
8. Tukar CSP kepada enforcement pada UAT dahulu.

CSP tidak menggantikan pembetulan XSS. Ia ialah lapisan tambahan selepas sink berbahaya dibaiki.

## 15. Subfasa 7.8 — Encoding dan Data Compatibility

**Risiko:** Sangat tinggi  
**Cadangan:** Change stream berasingan selepas cleanup/refactor stabil.

Keadaan `latin1`/`utf8mb4` perlu dinilai pada connection, table, column, import ODBC dan JSON output. Sebelum migrasi:

- ambil sample aksara Melayu, nama beraksen dan data legacy;
- kesan mojibake dan invalid byte;
- tentukan source-of-truth encoding setiap integration;
- buat dry-run pada salinan database;
- sediakan reversible backup dan row-count/hash validation.

Jangan menggunakan bulk `CONVERT TO CHARACTER SET` pada database hidup tanpa dry-run dan owner data.

## 16. Subfasa 7.9 — Permanent Deletion

Permanent deletion ialah langkah terakhir, bukan sebahagian daripada quarantine change.

### Gate deletion

- [ ] Minimum 30 hari tanpa request sah; 90 hari bagi vendor/batch job.
- [ ] Tiada error/reference baharu selepas quarantine.
- [ ] Owner aplikasi dan operasi memberi approval.
- [ ] Release sekurang-kurangnya satu cycle telah stabil.
- [ ] Backup retention melebihi rollback window.
- [ ] Manifest dan keputusan approval disimpan dalam docs/change record.

Selepas gate, delete quarantine mengikut satu change ID pada satu masa dan rekod jumlah fail serta saiz yang dibuang.

## 17. Ujian Wajib Selepas Setiap Batch

### Public/authentication

- root login HTTP 200;
- login berjaya/gagal;
- reset password/OTP;
- forced password change;
- logout dan session invalidation.

### User

- dashboard dan senarai aplikasi;
- launch aplikasi non-SSO;
- launch SSO pilot;
- tukar password;
- lihat/revoke session sendiri.

### Admin

- role guard dan CSRF;
- user/category/application/ACL;
- upload icon fixture;
- audit log dan active sessions;
- sync dry-run/fixture.

### Integration

- `api.php`, `idms.php`, `skp_api.php` mengikut scope;
- legacy/dual/new consumer yang masih diluluskan;
- TLS verification;
- `401`, `403`, `429` dan invalid input.

### Operational

- PHP lint dan automated test;
- tiada 404/5xx baharu;
- tiada PHP warning/fatal baharu;
- permission dan owner kekal betul;
- release artifact tidak mengandungi SQL dump, log atau secret.

## 18. Rollback Matrix

| Subfasa | Trigger rollback | Tindakan |
|---|---|---|
| 7.1 metadata/operational | Scheduler/logging gagal | Pulihkan path dan permission asal; betulkan deployment exclude secara berasingan |
| 7.2 PHP legacy | Request sah/5xx/consumer gagal | Pulihkan fail dari quarantine dengan checksum dan permission asal |
| 7.3 `public_img` | Icon hilang/404 | Pulihkan filename exact; clear cache jika perlu |
| 7.4 aset | UI/font/plugin rosak | Pulihkan batch aset dan release manifest sebelumnya |
| 7.5 dependency | Regression/build gagal | Deploy lockfile dan compiled artifact versi sebelumnya |
| 7.6 refactor | Contract/authorization berubah | Revert extraction batch sahaja; jangan rollback security phase terdahulu |
| 7.7 CSP | Resource/script sah diblok | Kembali ke report-only atau pulihkan polisi terdahulu sementara sink dibaiki |
| 7.8 encoding | Data rosak/mojibake/count tidak sama | Stop write, restore backup mengikut runbook database dan reconcile transaksi |

Rollback tidak boleh menghidupkan semula hardcoded secret, MD5-only authentication, insecure cookie, TLS verification disabled atau authorization bypass.

## 19. Dokumen Yang Perlu Dihasilkan Semasa Implementasi

Setiap subfasa perlu menghasilkan dokumen seperti fasa terdahulu:

- `FASA_7_0_BASELINE_DAN_CHARACTERIZATION.md`;
- `FASA_7_1_METADATA_DAN_OPERATIONAL_FILES.md`;
- `FASA_7_2_QUARANTINE_LEGACY.md`;
- `FASA_7_3_PUBLIC_IMG.md`;
- `FASA_7_4_ASSET_CLEANUP.md`;
- `FASA_7_5_DEPENDENCY_MODERNIZATION.md`;
- `FASA_7_6_REFACTOR.md`;
- `FASA_7_7_CSP_XSS.md`;
- `FASA_7_8_ENCODING.md`;
- `FASA_7_9_PERMANENT_DELETION.md`.

Setiap dokumen mesti mengandungi before/after inventory, checksum, fail berubah, ujian, isu terbuka, rollback dan approval.

## 20. Definition of Done Fasa 7

- [ ] Tiada page/controller legacy executable dalam document root tanpa owner.
- [ ] Tiada dump, log, diagnostic output atau backup dalam release artifact.
- [ ] `public_img` hanya mengandungi fail yang dirujuk atau masih dalam retention.
- [ ] Dependency runtime mempunyai package manager, lockfile dan build reproducible.
- [ ] Demo/docs/tests vendor tidak dihantar ke production release.
- [ ] Controller/database/dashboard monolith telah dipecahkan di belakang test.
- [ ] XSS sink kritikal dibetulkan dan CSP enforcement sesuai diaktifkan.
- [ ] Encoding mempunyai keputusan terdokumen dan tiada data rosak.
- [ ] Quarantine melepasi observation window dan deletion approval.
- [ ] Semua flow authentication, admin, SSO, API dan sync lulus regression test.

## 21. Cadangan Titik Mula Apabila Ready

Mulakan dengan **Fasa 7.0**, bukan deletion:

1. stabilkan Fasa 6B;
2. kumpul log 30–90 hari;
3. bina characterization test;
4. sediakan quarantine storage dan manifest;
5. lakukan 7.1 metadata ringan;
6. quarantine tiga snapshot login sebagai batch PHP pertama.

## 22. Kemaskini Restructuring R5.2A — 14 Julai 2026

R5.2A telah menyediakan application-layer baseline tanpa memindahkan PHP
runtime. Dua hostname lulus keseluruhan 140/140 characterization checks dan 12
public wrapper telah dipetakan kepada source target, dependency serta caller
diketahui.

Keputusan urutan extraction:

1. tambah authenticated user/admin logout characterization;
2. consolidate dua logout handler yang kecil di belakang URL/wrapper sedia ada;
3. extract pure service/helper secara kecil;
4. dashboard, integration endpoint dan `q_func` hanya selepas contract khusus;
5. legacy SSO compatibility wrapper kekal tertakluk kepada registry/sunset R5.4.

Rujukan implementasi dan rollback:

- `R5_2A_CHARACTERIZATION_CALLER_MAP_DAN_ROLLBACK.md`;
- `R5_2A_CALLER_MAP.tsv`;
- `tests/characterization/r52_contracts.php`;
- `tools/r52_characterization.php`.

R5.2B0 tooling authenticated logout turut tersedia dalam
`tools/r52_authenticated_logout.php`. Ia menguji session rotation, role guard,
logout dan penolakan replay session tanpa mendedahkan credential/token. Empat
run user/admin pada dua hostname mesti lulus sebelum R5.2B1 dimulakan.

Keputusan 03:22 +0800: admin lulus penuh pada kedua-dua hostname. Endpoint
`page/logout.php` dan `admin/logout.php`, cookie clearing serta session replay
rejection turut lulus. Run user belum memenuhi role contract kerana akaun admin
yang sama digunakan; akaun user biasa atau acceptance pengecualian masih menjadi
gate R5.2B1.

Keputusan 03:30 +0800: owner memaklumkan akaun user biasa tidak tersedia dan
mengarahkan continuation; pengecualian normal-user direkodkan. R5.2B1 kemudian
mengekstrak logik logout bersama ke `app/Auth/LogoutHandler.php` tanpa mengubah
URL atau public wrapper. Static regression dua hostname lulus 70/70; authenticated
admin post-change test masih menjadi closure gate.

Keputusan 03:35 +0800: closure gate R5.2B1 lulus. Authenticated admin test
selepas extraction mendapat 14/14 pada kedua-dua hostname; cookie/token/session
invalidation dan replay rejection kekal betul. Tiada 5xx atau PHP fatal/warning
dalam test window. R5.2B1 ditutup sebagai PASS.

Keputusan R5.2C0: pure-helper/caller map disediakan tanpa runtime movement.
Characterization sync transformation lulus 21/21. Enam helper sync dikenal pasti
sebagai calon C1; helper `q_func`, dashboard dan integration ditangguhkan. Empat
weakness legacy hash/change detection/duplicate/exclusion direkodkan tetapi tidak
dibetulkan dalam restructuring.

Keputusan R5.2C1: enam sync transformer diextract ke
`app/Sync/SyncDataTransformer.php` dengan global function kekal sebagai wrapper.
Pure-helper/parity suite lulus 28/28 dan HTTP regression dua hostname kekal
70/70. Live sync tidak dijalankan kerana file movement tidak memberi justifikasi
untuk database/external-source side effect. Empat weakness legacy kekal tepat.

Keputusan R5.2D0: in-memory sync orchestration fixture lulus 18/18 dan dashboard
static characterization lulus 21/21 tanpa runtime movement. Transaction boundary
upstream, orchestration coupling dan dashboard monolith direkodkan sebagai
weakness. GO seterusnya terhad kepada design/interface dan seam preparation tanpa
production wiring.

Keputusan R5.2D1: empat sync interface dan immutable summary DTO disediakan di
`app/Sync` tanpa production wiring. Design validation lulus 18/18, method mapping
tersedia dan sync runner kekal checksum C1. Transaction weakness dan dashboard
movement tidak berubah.

Keputusan R5.2D2: empat adapter test-only disediakan di `tests/Support/Sync` dan
exact parity fixture lulus 21/21. Semua 15 persistence mapping, callable,
exclusion dan category behavior legacy telah dikunci. D1 dan D0 regression
masing-masing kekal 18/18; checksum sync runner, Database, `q_func` dan cron
tidak berubah. Tiada production wiring, database/network access atau live sync
dilakukan. Transaction weakness D0-W01 sengaja kekal untuk functional change
berasingan.

Keputusan R5.2D3: `TestSyncOrchestrator` dibina di bawah test support menggunakan
interface D1 dan adapter D2. Legacy-versus-projection parity lulus 17/17 untuk
success, empty source, mutation rollback dan upstream failure. Exact persistence
call trace dan legacy result adalah sama selepas hanya nilai initial-password
hash rawak dinormalisasi; kedua-dua path terlebih dahulu disahkan membekalkan
hash. Tiada production wiring dan empat checksum runtime kekal tidak berubah.
D0-W01 sengaja dikekalkan bagi parity.

Keputusan R5.2D4: production-wiring readiness plan dan 24-gate register telah
disediakan tanpa runtime change. Initial pilot diputuskan compatibility-first,
`legacy` kekal default, hanya satu writer dibenarkan dan shadow mesti berasaskan
pure plan daripada snapshot sama. Cron kekal retired; admin manual sync sahaja
calon pilot. Database backup diwajibkan kerana feature-flag rollback tidak boleh
mengundurkan committed data dan audit log semasa bukan full row restore image.
Production wiring kekal NO-GO sehingga dry-run zero-mutation, zero mismatch,
concurrency lock, monitoring, reconciliation dan database rollback gate lulus.

Keputusan R5.2D5: immutable `SyncPlan`, test-only pure planner dan dry-run telah
disediakan. Throwing mutation spy membuktikan dry-run hanya mengambil satu
external snapshot dan dua user-state reads; fixture lulus 25/25 dengan action,
audit, count, category serta UPDATE/INSERT hash parity. Safe projection tidak
mendedahkan nama/raw UID dan plan hash deterministik. Runtime checksum kekal.
D4-G08 ditutup untuk test-only readiness, manakala live UAT snapshot,
single-snapshot shadow dan production wiring kekal pending/NO-GO.

Keputusan R5.2D6: decision planner D5 telah diextract menjadi pure
`app/Sync/SyncPlanner.php` dan duplicate test planner dibuang. Purity guard lulus
17/17; D5 zero-mutation/parity kekal 25/25. Constructor hanya bergantung pada
`SyncPolicyInterface`, public API hanya constructor/plan dan tiada symbol I/O,
persistence, source, session, HTTP atau environment. Planner kekal dormant;
production adapter, feature flag dan caller wiring masih belum wujud.

Keputusan R5.2D7: empat production adapter dormant bagi external source, secure
initial password, legacy policy dan Database persistence telah dibina. Contract
suite lulus 32/32 termasuk exact 15-method persistence mapping, category,
exclusion, external error propagation dan non-deterministic password hash.
Adapter tidak bergantung pada test namespace dan tidak dirujuk runtime. D4-G07
ditutup; production orchestrator, feature flag dan live sync masih NO-GO.

Keputusan R5.2D8: dormant production `SyncOrchestrator` telah dibina menggunakan
pure planner dan production adapters. Full legacy parity lulus 18/18 bagi
success, empty source, no-pending, mutation rollback dan upstream failure.
Jumlah suite khusus D0–D8 ialah 166 checks PASS. Tiada runtime caller berubah;
ini ialah checkpoint selamat untuk review application-layer restructuring.
D9 live read-only shadow memerlukan arahan/authority berasingan.

Keputusan R5.3: legacy-root cleanup dilaksanakan menggunakan quarantine, bukan
permanent deletion. Sebanyak 38 sumber, 5,222 fail/symlink dan 112,699,218 byte
dipindahkan ke `storage/quarantine/R5-3-20260714-094844/payload` dengan move map,
inventory dan checksum. Skop meliputi duplicate root assets, snapshot tanpa
caller, browser vendor tidak digunakan, metadata junk serta `diag`/`cron` yang
telah disahkan retired. PHP implementation yang masih dipanggil wrapper public,
termasuk root entry points, `admin`, `page` dan `lib`, kekal aktif. Selepas
cleanup, kedua-dua hostname lulus smoke 10/10 dan characterization 70/70;
sync regression D0–D8 lulus 166/166 serta 63 fail PHP aktif lulus lint. Dokumen
pelaksanaan dan rollback ialah
`docs/R5_3_LEGACY_ROOT_QUARANTINE_CLEANUP_DAN_ROLLBACK.md`.

Keputusan R5.4A: build dan dependency path diselaraskan dengan public-root.
Grunt kini hanya menulis ke `public/dist`, development server berakar pada
`public/`, task yang merujuk source tidak wujud dikeluarkan dan default build
menjadi finite. Bower target kini `public/vendors/bower_components`; migration
Fasa 5 sanitised dibenarkan masuk Git tanpa membenarkan `sso_db.sql`. Dependency
register dan structure guard ditambah. Structure contract lulus 31/31, smoke
dua hostname lulus 10/10 setiap satu dan characterization lulus 70/70 setiap
host. Build compiler sebenar belum dijalankan kerana Node/npm/Grunt/Sass tiada
pada host; status ialah PASS_WITH_BUILD_ENV_GATE. Rujuk
`docs/R5_4A_BUILD_DAN_DEPENDENCY_REPRODUCIBILITY.md`.

Keputusan R5.4B: caller/access inventory bagi tujuh public asset root telah
direkodkan. Broken `/app-assets` hanya digunakan oleh dead export JavaScript
dalam `admin/user_list.php`; dua script 404 dan handler tanpa butang dibuang
tanpa mencipta compatibility directory palsu. Windows `Thumbs.db` dipindahkan
ke quarantine dengan checksum. Tiada vendor, upload icon, manual atau video
dibuang kerana caller/database/retention evidence belum mencukupi. Asset
contract lulus 27/27 selepas URL routing guard, dashboard characterization 21/21, structure regression
31/31, full characterization 70/70 dan smoke dua hostname 10/10 setiap satu.
Status ialah PASS_WITH_MANUAL_ADMIN_PAGE_GATE kerana visual authenticated user
list masih perlu disahkan oleh owner. Rujuk
`docs/R5_4B_PUBLIC_ASSET_CLEANUP_DAN_ROLLBACK.md`.

Manual gate pertama R5.4B menemui caller URL `user_list.php/?...` yang salah.
Slash selepas `.php` menyebabkan fallback routing dan asset relatif dicari di
`/admin/user_list.php/assetsM/...`. Caller dibetulkan kepada
`user_list.php?category_id=...` dan category name kini URL-encoded. Contract
tambahan mencegah PATH_INFO route tersebut kembali; visual gate perlu diulang.

Manual gate kedua R5.4B lulus selepas PHP-FPM direstart dan URL baharu dimuatkan;
page serta data dipaparkan tanpa nested asset errors atau `$ is not defined`.
Warning Firefox bagi CSP Report-Only tanpa reporting endpoint tidak menyekat
resource dan direkodkan sebagai security-header backlog berasingan. Status akhir
R5.4B ialah PASS.

Keputusan R5.4C: dua implementation logout yang byte-identical telah digabungkan
ke `app/Auth/LogoutEndpoint.php`. URL awam `/admin/logout.php` dan
`/page/logout.php` dikekalkan sebagai thin compatibility wrapper, manakala dua
implementation root lama dipindahkan ke quarantine
`storage/quarantine/R5-4C-20260714-104304`. Sepuluh compatibility wrapper lain
tidak dipindahkan dalam slice ini. Compatibility contract lulus 24/24, asset
regression 27/27, structure regression 31/31, dashboard regression 21/21 dan
full characterization kedua-dua hostname lulus 69/69. Pengurangan kiraan
characterization daripada 70 kepada 69 berlaku kerana shared target dilint
sekali; kedua-dua URL logout masih diuji. Status sementara ialah
PASS_WITH_AUTHENTICATED_LOGOUT_GATE sehingga authenticated admin logout mendapat
14/14 pada `oneid.local` dan `oneid-next.local`. Rujuk
`docs/R5_4C_COMPATIBILITY_IMPLEMENTATION_CLEANUP_DAN_ROLLBACK.md`.

Closure gate R5.4C kemudian lulus pada 14 Julai 2026. Authenticated admin logout
mendapat 14/14 pada kedua-dua hostname, termasuk cookie clearing dan session
replay rejection. Status akhir R5.4C ialah PASS.
