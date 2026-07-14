# R4 Gate dan Acceptance Register — OneID Public-Root Cutover

**Tarikh penilaian:** 14 Julai 2026  
**Change:** Tukar document root `oneid.local` daripada `/var/www/app/oneid-uat` kepada `/var/www/app/oneid-uat/public`  
**Status keseluruhan:** **CLOSED — semua gate R4 lulus; isu e-Prestasi diasingkan**

## 1. Kaedah Keputusan

| Status | Maksud |
|---|---|
| PASS | Bukti teknikal atau pengesahan pemilik sistem tersedia |
| PENDING | Ujian/approval belum direkodkan |
| BLOCKED | Risiko outage nyata; jangan cutover tanpa pembetulan atau acceptance owner |
| ACCEPTED RISK | Owner berkuasa menerima risiko secara bertulis dengan tempoh dan rollback trigger |

Codex tidak boleh menukar `PENDING` atau `BLOCKED` kepada `ACCEPTED RISK`. Keputusan tersebut mesti dibuat oleh pemilik sistem/operasi yang mempunyai autoriti.

## 2. Gate Register

| ID | Gate | Status | Bukti/keputusan |
|---|---|---|---|
| R4-G00 | Baseline `oneid.local` tersedia | PASS | Block dipulihkan dengan root `/var/www/app/oneid-uat`; smoke 10/10 dan title OneID disahkan |
| R4-G01 | Public-root Nginx selari aktif | PASS | `oneid-next.local` melayan `/var/www/app/oneid-uat/public` |
| R4-G02 | TLS hostname selari | PASS | SAN `oneid-next.local`, sah sehingga 13 Oktober 2028 |
| R4-G03 | Route smoke | PASS | 10/10 lulus |
| R4-G04 | Public boundary | PASS | 13/13 sensitive path menghasilkan 404 |
| R4-G05 | User login dan navigasi | PASS | Disahkan pemilik sistem |
| R4-G06 | Admin login | PASS | Disahkan pemilik sistem |
| R4-G07 | Admin logout/session invalidation | PASS | Pemilik sistem mengesahkan logout admin berjaya |
| R4-G08 | OTP/reset password | PASS | Disahkan pemilik sistem |
| R4-G09 | Upload icon dan target | PASS | Icon baharu ditulis ke `public_img`; symlink public resolve dengan betul |
| R4-G10 | Semua 34 icon aplikasi | PASS dengan evidence inventori | Snapshot: 34 reference unik, semuanya wujud; pemilik telah berjaya navigasi. Visual 34/34 boleh diulang dalam change window |
| R4-G11 | API/IDMS/SKP live credential | PASS | Disahkan pemilik sistem |
| R4-G12 | SSO pilot consumer pada hostname utama | PASS | IQS-Framework `BTOG4WZNQP` berjaya end-to-end pada 02:31; OneID 302 ke consumer diikuti API 200 tanpa loop. `DYYOWQGYLE` kekal defect consumer berasingan |
| R4-G13 | SSO endpoint legacy | PASS — compatibility sementara | Dua wrapper public mengekalkan contract 200/302 dan merekod audit tanpa token; Fasa 6B tetap wajib |
| R4-G14 | Scheduled sync | PASS — retired by owner | Pemilik sistem mengesahkan cron/sync tidak diperlukan lagi; tiada scheduler akan dibawa ke public-root |
| R4-G15 | Monitoring `diag/agent.php` | PASS — retired by owner | Pemilik sistem mengesahkan endpoint tidak digunakan dan menerima 404 selepas R4 |
| R4-G16 | Access/error log | PASS | Log diperhatikan; tiada Nginx `emerg/alert/crit`; audit missing credential datang daripada smoke test |
| R4-G17 | Rollback evidence | PASS with documented deviation | Post-cutover snapshot checksum disahkan; tested legacy restore template menjadi rollback source kerana pre-backup tiada |
| R4-G18 | Change owner dan window | PASS | Pemilik sistem ialah change owner; window bermula 14 Julai 2026, 01:40:57 +0800 |

## 3. Bukti Cron

- `cron/logs/sync_cron.log` merekodkan run harian berjaya 16 Jun–13 Julai 2026.
- Run terakhir: 13 Julai 2026, bermula 00:00:01 dan tamat 00:00:08.
- Pada semakan 14 Julai 2026 selepas 01:20, tiada run 14 Julai.
- `storage/logs/sync_cron.log` belum wujud walaupun kod semasa menulis ke lokasi tersebut.
- Tiada crontab bagi user `iqs`, tiada unit/timer OneID yang dapat dikenal pasti dalam konfigurasi sistem yang boleh dibaca.
- Fail `.bat` dan PowerShell menunjukkan reka bentuk Windows Task Scheduler, tetapi tidak membuktikan task aktif pada host Linux ini.

**Keputusan owner:** Pemilik sistem mengesahkan scheduled sync tidak diperlukan lagi. Ketiadaan run 14 Julai diterima sebagai retirement, bukan kegagalan yang perlu dibaiki untuk R4. Fail cron tidak dipadam dalam R4; ia menjadi calon quarantine cleanup berasingan supaya rekod sejarah dan rollback kekal jelas.

## 4. Endpoint Yang Akan Berubah Selepas R4

| Endpoint | `oneid.local` sebelum R4 | Selepas R4 | Keputusan diperlukan |
|---|---:|---:|---|
| `/diag/agent.php` | Tersedia dengan token | 404 | Sahkan tiada monitor atau bina replacement terkawal |
| `/lib/sso_IDP_index.php` | Legacy implementation | 404 | Sahkan tiada consumer atau buat transition Fasa 6B |
| `/lib/sso_IDP_sub.php` | Legacy redirect | 404 | Sahkan tiada consumer atau buat transition Fasa 6B |
| `/cron/run_sync.php` melalui HTTP | 403/CLI-only | 404 | Tiada kesan jika scheduler benar-benar menggunakan CLI |

Hit log yang ditemui untuk tiga endpoint legacy datang daripada `127.0.0.1` semasa audit pada 14 Julai. Evidence ini tidak mencukupi untuk menyimpulkan tiada external consumer kerana retention log hanya beberapa hari.

## 5. Pilihan Resolusi Gate BLOCKED

### R4-G13 — SSO legacy

Keputusan:

- [ ] Evidence Fasa 6B dan owner mengesahkan tiada consumer; kekalkan 404 selepas R4.
- [ ] Tangguhkan R4 sehingga consumer legacy dimigrasikan.
- [x] Compatibility route sementara diluluskan owner dan dilaksanakan dengan logging khusus tanpa token.

Wrapper menutup risiko outage restructuring tetapi tidak menghapuskan security debt. Rujuk `R4_COMPATIBILITY_SSO_LEGACY.md`.

### R4-G14 — Cron

Keputusan:

- [x] Pemilik sistem mengesahkan sync tidak diperlukan lagi dan menerima retirement scheduler.
- [ ] Fail/script cron di-quarantine dalam change cleanup berasingan; bukan R4.

### R4-G15 — Monitoring

Keputusan:

- [x] Pemilik sistem mengesahkan `diag/agent.php` tidak digunakan dan menerima 404 selepas R4.
- [ ] Fail dipindah ke quarantine dalam change cleanup berasingan sebelum permanent deletion.
- [ ] Replacement health endpoint/monitoring disediakan sebelum R4.

## 6. Acceptance Bertulis

Lengkapkan sebelum R4:

- **Change owner:** individu yang bertanggungjawab meluluskan GO/NO-GO, menyelaras masa cutover dan memastikan checklist dijalankan.
- **Rollback owner:** individu yang memerhati hasil selepas cutover dan mempunyai autoriti mengarahkan serta melaksanakan pemulihan Nginx jika trigger rollback berlaku.
- Dalam UAT, kedua-dua peranan boleh dipegang oleh orang yang sama jika individu tersebut mempunyai akses `sudo`, memahami runbook dan diberi autoriti membuat keputusan.

```text
Change ID:
Tarikh/masa change window:
Change owner:
Rollback owner:

R4-G07 admin logout:
R4-G13 SSO legacy decision:
R4-G14 cron decision:
R4-G15 monitoring decision:
R4-G17 rollback backup path/checksum:

Accepted residual risk:
Risk owner:
Acceptance expiry:
Rollback trigger:

Keputusan akhir: GO / NO-GO
Diluluskan oleh:
Tarikh/masa:
```

Acceptance tanpa owner, expiry dan rollback trigger tidak sah. Jika keputusan akhir masih kosong, status kekal **NO-GO**.

## 7. Regression Baseline Ditemui Semasa Penyediaan Runbook

Pada 14 Julai 2026 sekitar 01:23, pemeriksaan mendapati:

- `/etc/nginx/sites-available/local-projects` mengandungi `oneid-next.local` tetapi tidak lagi mengandungi `server_name oneid.local`;
- request `https://oneid.local` jatuh ke default TLS vhost dan memaparkan login e-BDR;
- smoke `oneid.local`: 1 lulus, 9 gagal;
- smoke `oneid-next.local`: 10 lulus, 0 gagal.

Ini bukan kegagalan source OneID. Ia ialah kehilangan server block lama semasa edit Nginx. Pulihkan block menggunakan `docs/nginx/oneid-legacy-restore.server-block.conf`, jalankan `sudo nginx -t`, reload, kemudian pastikan smoke `oneid.local` kembali 10/10. Jangan gunakan template R4 public-root untuk pemulihan ini kerana keputusan R4 masih NO-GO.

### 7.1 Keputusan pemulihan

Pemilik sistem telah memulihkan kedua-dua server block. Verifikasi selepas pemulihan:

- `oneid.local` menggunakan root `/var/www/app/oneid-uat`;
- `oneid-next.local` menggunakan root `/var/www/app/oneid-uat/public`;
- kedua-dua hostname memaparkan title OneID yang betul;
- smoke `oneid.local`: 10 lulus, 0 gagal;
- smoke `oneid-next.local`: 10 lulus, 0 gagal.

R4-G00 ditutup sebagai PASS. Template emergency restore dikekalkan sebagai bukti dan alat rollback.

## 8. Keputusan Owner Tambahan

Pemilik sistem mengesahkan:

- admin logout berjaya;
- `diag/agent.php` tidak digunakan dan boleh ditamatkan;
- scheduled sync/cron tidak diperlukan lagi;
- penggunaan langsung `/lib/sso_IDP_index.php` dan `/lib/sso_IDP_sub.php` **belum disahkan**.

Keputusan monitoring dan cron menutup gate R4 tanpa melakukan deletion. Oleh sebab penggunaan dua endpoint SSO belum dapat disahkan, pemilik sistem meluluskan compatibility wrapper sementara. R4-G13 ditutup bagi tujuan cutover, tetapi penamatan route kekal tertakluk kepada Fasa 6B, registry consumer dan evidence log 30–90 hari.

Pemilik sistem turut menerima peranan change owner dan rollback owner bagi UAT. Nama rasmi, Change ID dan masa window perlu diisi pada rekod change sebelum arahan cutover dijalankan.

## 9. Status Cutover R4

Cutover teknikal dilaksanakan dalam change `R4-20260714-014057`. Active root `oneid.local` ialah `/var/www/app/oneid-uat/public`; smoke 10/10, boundary 13/13 dan compatibility contract lulus. Access log mengesahkan dashboard/logout user dan admin, q_func POST, API POST dan icon request berjaya.

Pilot `DYYOWQGYLE` pada hostname utama kemudian gagal akibat redirect loop. Diagnosis OneID mengesahkan token aktif, akaun aktif, ACL dibenarkan, tiada blacklist dan respons API konsisten dengan token sah. Oleh itu R4-G12 dibuka semula sebagai `PENDING`, change tidak ditutup, dan rollback tidak dicetuskan setakat bukti semasa. Log callback/login pada runtime e-Prestasi perlu diperoleh sebelum keputusan pembetulan atau rollback dibuat.

Control pilot seterusnya menggunakan IQS-Framework (`BTOG4WZNQP`) berjaya
end-to-end pada 02:31. Access log menunjukkan redirect OneID HTTP 302 diikuti
validasi API HTTP 200 dan tiada redirect kembali ke OneID. R4-G12 ditutup sebagai
`PASS`; ini mengasingkan kegagalan `DYYOWQGYLE` kepada integrasi e-Prestasi dan
bukan regression umum public-root. Semua gate R4 lulus dan change ditutup pada
14 Julai 2026, 02:34 +0800. R5 mesti menggunakan baseline baharu dan tidak boleh
menganggap defect e-Prestasi sebagai alasan untuk rollback public-root.
