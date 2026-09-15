# Audit dan Pelan Pembangunan Kebolehcapaian Visual OneID

## Status dokumen

- **Tarikh:** 15 September 2026
- **Status:** Draf untuk semakan dan kelulusan
- **Skop diluluskan:** Login awam, portal pengguna, User MFA, maintenance,
  halaman ralat, modal pengguna, notifikasi dan kandungan bantuan
- **Di luar skop:** Dashboard, laporan, modal dan Step-Up pentadbir
- **Sasaran awal:** WCAG 2.2 Level AA bagi perkara yang berada dalam skop
- **Perubahan database Fasa 0 hingga Fasa 3:** Tidak diperlukan

## 1. Tujuan

Dokumen ini merekodkan audit awal dan pelan pembangunan berfasa bagi membantu
pengguna yang sukar membaca atau berinteraksi dengan antara muka OneID,
terutamanya:

- warga emas;
- pengguna rabun atau low vision;
- pengguna yang mempunyai sensitiviti kontras rendah atau buta warna;
- pengguna yang memerlukan pembesaran browser atau screen magnifier;
- pengguna yang menggunakan papan kekunci; dan
- pengguna yang sensitif kepada animasi atau mempunyai kawalan motor terhad.

Penambahbaikan tidak terhad kepada satu butang membesarkan font. Baseline UI,
browser zoom, reflow, kontras, fokus, saiz sasaran interaktif dan pilihan paparan
pengguna perlu dibangunkan sebagai satu keupayaan yang konsisten.

## 2. Ringkasan audit awal

Audit ini ialah audit statik terhadap source semasa. Ia belum menggantikan ujian
browser, assistive technology atau penerimaan pengguna sebenar.

### 2.1 Dapatan utama

1. Banyak teks first-party ditetapkan pada `8px` hingga `13px`, termasuk label,
   metadata, FAQ, pilihan bahasa, status sesi dan teks bantuan.
2. `page/dashboard.php` dan `admin/dashboard.php` menggunakan viewport yang
   mengandungi `maximum-scale=1.0, user-scalable=no`; ini menghalang pinch zoom
   pada browser tertentu.
3. Banyak saiz tipografi menggunakan nilai `px` tetap dan override `!important`,
   menyebabkan skala teks global sukar dikawal secara konsisten.
4. Beberapa teks kecil menggunakan warna kelabu pucat. Kontras perlu diuji pada
   setiap kombinasi foreground/background sebenar.
5. Beberapa komponen membuang `outline`; tidak semua mempunyai focus indicator
   pengganti yang jelas.
6. Sebahagian kawalan berasaskan ikon atau label ringkas berpotensi mempunyai
   target sentuhan yang terlalu kecil.
7. Sebahagian komponen telah mempunyai asas yang baik seperti `aria-label`,
   `aria-live`, label OTP dan `prefers-reduced-motion`. Asas ini perlu dikekalkan
   dan diperluas, bukan diganti.

### 2.2 Rujukan penerimaan

Pelaksanaan hendaklah menggunakan WCAG 2.2 Level AA sebagai baseline, khususnya:

- 1.4.3 Contrast (Minimum): teks biasa sekurang-kurangnya `4.5:1`, tertakluk
  kepada pengecualian rasmi;
- 1.4.4 Resize Text: teks boleh dibesarkan sehingga 200% tanpa kehilangan
  kandungan atau fungsi;
- 1.4.10 Reflow: kandungan biasa boleh digunakan pada lebar 320 CSS pixels tanpa
  scroll dua dimensi;
- 1.4.11 Non-text Contrast: sempadan/kawalan dan focus indicator boleh dilihat;
- 2.4.7 Focus Visible dan 2.4.11 Focus Not Obscured; dan
- 2.5.8 Target Size (Minimum): target sekurang-kurangnya 24 × 24 CSS pixels atau
  memenuhi pengecualian spacing yang sah.

Rujukan rasmi:

- <https://www.w3.org/TR/WCAG22/>
- <https://www.w3.org/WAI/WCAG22/Understanding/resize-text.html>
- <https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html>
- <https://www.w3.org/WAI/WCAG22/Understanding/reflow.html>
- <https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html>

## 3. Prinsip reka bentuk

1. **Accessible secara default.** Pengguna tidak sepatutnya perlu mengaktifkan
   mod khas untuk membaca teks biasa atau menggunakan browser zoom.
2. **Pilihan paparan ialah tambahan.** Toolbar tidak boleh digunakan untuk
   menutup kelemahan baseline.
3. **Progressive enhancement.** Login dan fungsi keselamatan mesti kekal boleh
   digunakan jika JavaScript atau penyimpanan browser gagal.
4. **Tidak mengubah authorization.** Tetapan paparan tidak boleh mempengaruhi
   sesi, ACL, Step-Up, CSRF, MFA atau keputusan authentication.
5. **Tidak bergantung pada warna sahaja.** Status mesti turut menggunakan teks,
   ikon atau bentuk yang bermakna.
6. **Bilingual dan konsisten.** Label, bantuan dan announcement mesti tersedia
   dalam BM dan English.
7. **Privacy minimum.** Preference paparan bukan data perubatan dan tidak boleh
   dinamakan atau dilog sebagai diagnosis pengguna.

## 4. Pelan pembangunan berfasa

### Fasa 0 — Baseline, inventori dan characterization

**Objektif:** Membina baseline yang boleh diukur sebelum CSS atau UI diubah.

**Skop kerja:**

- inventori semua entry point dan stylesheet first-party;
- senaraikan teks di bawah 14px mengikut fungsi, bukan semata-mata carian nilai;
- audit viewport, overflow, fixed height/width, modal dan jadual;
- inventori kombinasi warna teks, border, focus dan latar;
- audit urutan tab, focus modal dan butang ikon;
- ambil screenshot baseline bagi viewport desktop dan mobile;
- tambah characterization contract bagi asset loading, toolbar placeholder dan
  larangan viewport yang menyekat zoom; dan
- sediakan matriks manual bagi login, user, admin, MFA, maintenance dan error.

**Deliverable:** Laporan baseline, matriks halaman/komponen, test contract dan
senarai isu mengikut severity.

**Kriteria selesai:** Semua laluan kritikal mempunyai owner, bukti baseline dan
senarai acceptance test.

**Database migration:** Tidak.

### Fasa 1 — Remediasi baseline visual dan browser zoom

**Objektif:** Menjadikan paparan asal lebih mudah dibaca sebelum preference
pengguna diperkenalkan.

**Skop kerja:**

- buang `user-scalable=no` dan `maximum-scale=1.0`;
- perkenalkan token CSS global seperti `--oneid-font-body`,
  `--oneid-font-small`, `--oneid-line-height` dan warna teks accessible;
- gunakan `rem` untuk tipografi first-party yang perlu diskalakan;
- sasarkan body/input/button pada kira-kira 16px dan teks sekunder penting tidak
  kurang daripada 14px, kecuali kes dekoratif yang diluluskan;
- naik taraf line-height dan spacing supaya teks lebih mudah diikuti;
- pastikan label input kekal kelihatan dan tidak bergantung pada placeholder;
- betulkan kontras teks, link, placeholder, border, disabled state dan status;
- sediakan `:focus-visible` yang jelas serta buang `outline: none` tanpa
  pengganti;
- besarkan target kawalan kritikal; dan
- pastikan modal, alert, header dan sidebar tidak clip pada 200% zoom.

**Kriteria selesai:**

- browser zoom 200% tidak menghilangkan kandungan atau fungsi;
- tiada zoom restriction pada laluan kritikal;
- laluan biasa reflow pada 320 CSS pixels;
- kontras dan focus indicator melepasi acceptance matrix; dan
- login password, MyDigital ID, recovery, MFA dan session renewal kekal lulus.

**Database migration:** Tidak.

#### Status pelaksanaan lokal — 15 September 2026

**Status:** `IMPLEMENTED_LOCALLY / MANUAL_UAT_PENDING`

Perubahan berikut telah dilaksanakan dalam working copy lokal:

- sekatan browser zoom dibuang daripada dashboard pengguna dan pentadbir;
- stylesheet baseline first-party diwujudkan selepas layer legacy;
- body, operational copy, input, button, jadual dan teks bantuan menerima
  baseline tipografi relatif yang lebih mudah dibaca;
- focus indicator `:focus-visible`, forced-colors fallback, target kawalan,
  local table overflow dan reduced-motion fallback ditambah;
- login, dashboard pengguna, User MFA, maintenance dan halaman 404 memuatkan
  baseline yang sama;
- kawalan Lupa Kata Laluan ditukar kepada semantic button;
- refresh sesi pengguna menerima target 44px dan accessible name bilingual; dan
- paparan pentadbir kekal menggunakan baseline asal supaya layout operasi admin
  tidak terjejas.

Contract lokal `php tools/a11y_phase1_contract.php` serta regression berkaitan
telah lulus. Ujian visual browser pada 200% zoom, 320 CSS pixels, mobile sebenar,
keyboard-only dan kombinasi kontras masih perlu dilaksanakan sebelum status
Fasa 1 boleh ditukar kepada `UAT_ACCEPTED`.

### Fasa 2 — Tetapan Paparan global

**Objektif:** Memberi kawalan mudah kepada pengguna yang tidak biasa menggunakan
tetapan zoom browser atau OS.

**Cadangan kawalan:**

- kecilkan teks (`A−`);
- saiz asal (`A` / Reset);
- besarkan teks (`A+`), dengan tahap yang didokumenkan;
- kontras tinggi;
- kurangkan animasi; dan
- gariskan pautan.

**Skop kerja:**

- bina satu komponen shared `Tetapan Paparan / Display Settings`;
- gunakan ikon bersama label teks, bukan ikon sahaja;
- sediakan markup server-rendered yang selamat dan enhancement JavaScript;
- simpan preference dalam `localStorage` menggunakan key berversi;
- apply preference seawal mungkin untuk mengurangkan flash paparan asal;
- gunakan class/data attribute pada root, bukan inline style pada setiap elemen;
- sediakan reset yang jelas dan keyboard-accessible;
- hormati `prefers-reduced-motion` walaupun pengguna belum memilih preference;
- pastikan preference tidak dimasukkan dalam URL, audit log atau authentication
  payload; dan
- gunakan katalog translation BM/English sedia ada.

**Skala dilaksanakan:** `100%`, `108%`, `115%`, `123%`, `130%` dengan browser
zoom kekal dibenarkan sehingga 200% atau lebih. Paparan `100%` menggunakan
baseline visual 90% daripada baseline teknikal Fasa 1. Empat kenaikan dibahagi
hampir sekata dalam julat 100% hingga maksimum 130%.

**Kriteria selesai:**

- preference kekal selepas refresh pada browser/peranti yang sama;
- kegagalan storage tidak menghalang login atau navigasi;
- semua kawalan boleh digunakan melalui keyboard dan mempunyai accessible name;
- saiz teks, modal, toast dan layout berubah secara konsisten; dan
- reset memulihkan baseline tanpa data yatim.

**Database migration:** Tidak. `localStorage` digunakan untuk versi pertama.

#### Status pelaksanaan lokal — 15 September 2026

**Status:** `IMPLEMENTED_LOCALLY / MANUAL_UAT_PENDING`

Komponen shared `Tetapan Paparan / Display Settings` telah dilaksanakan untuk
login pengguna, dashboard pengguna, User MFA, maintenance dan halaman 404.
Pilihan yang tersedia ialah skala paparan `100%`, `108%`, `115%`, `123%`, `130%`, kontras
tinggi, kurangkan animasi, gariskan pautan dan reset.

Preference menggunakan key browser berversi `oneid.display-settings.v3`,
divalidasi melalui allowlist dan gagal secara selamat jika storage tidak tersedia
atau mengandungi JSON rosak. Login maintenance admin, MFA maintenance admin,
dashboard admin, report admin, user list admin dan Admin Step-Up dikecualikan.

Contract lokal `php tools/a11y_phase2_display_settings_contract.php` telah lulus.
Penerimaan visual pada setiap tahap skala, mobile, high contrast dan interaksi
keyboard masih menjadi gate sebelum status ditukar kepada `UAT_ACCEPTED`.

### Fasa 3 — Hardening, automation dan UAT pengguna

**Objektif:** Mengesahkan bahawa penambahbaikan benar-benar boleh digunakan dan
tidak merosakkan aliran keselamatan.

**Skop kerja:**

- automated accessibility scan pada halaman yang boleh diautomasi;
- manual keyboard-only test;
- screen reader smoke test bagi login, ralat, modal, OTP dan status dinamik;
- zoom 200%, reflow 400%/320 CSS pixels dan mobile landscape/portrait;
- ujian kontras normal, high contrast dan OS forced-colors;
- ujian reduced motion;
- ujian kandungan BM dan English pada skala besar kerana panjang teks berbeza;
- regression bagi login, logout, timeout, renewal, MFA, Step-Up dan recovery;
- UAT bersama sampel warga emas/low-vision jika peserta tersedia; dan
- rekod defect, severity, keputusan go/no-go dan evidence release.

**Kriteria selesai:**

- tiada defect accessibility/sekuriti kritikal terbuka;
- semua aliran authentication utama lulus pada baseline dan tetapan terbesar;
- hasil automated scan telah ditriage secara manual;
- UAT owner menerima pengalaman desktop dan mobile; dan
- rollback rehearsal atau asset rollback check lulus.

**Database migration:** Tidak.

#### Status pelaksanaan lokal — 15 September 2026

**Status:** `AUTOMATED_PASS / DESKTOP_REVIEW_PASS / MANUAL_MATRIX_PENDING`

Hardening contract, runtime smoke dan regression pengguna utama telah lulus.
Runtime lokal menghidangkan login serta semua asset accessibility dengan betul.
Owner turut menerima paparan desktop panel secara iteratif. Browser automation
binary tidak tersedia dalam WSL; oleh itu mobile, keyboard-only, zoom 200% dan
kombinasi preference belum ditandakan lulus.

Bukti dan senarai manual yang masih terbuka direkodkan dalam
`docs/A11Y_FASA3_LOCAL_UAT_EVIDENCE_20260915.md`.

### Fasa 4 — Preference merentas peranti (opsyen, keputusan berasingan)

**Objektif:** Menyelaraskan preference bagi pengguna yang login pada lebih
daripada satu browser atau peranti.

Fasa ini hanya dilaksanakan jika UAT/penggunaan menunjukkan nilai yang jelas.
Ia bukan dependency kepada Fasa 0 hingga Fasa 3.

**Pilihan reka bentuk:**

- kekalkan `localStorage` sahaja; atau
- tambah preference store server-side dengan fallback kepada `localStorage`.

Jika server-side dipilih, elakkan menambah beberapa column terus pada jadual
pengguna legacy. Gunakan jadual preference khusus atau mekanisme preference
sedia ada jika ditemui semasa design review. Contoh konsep:

```text
user_accessibility_preferences
- user_id
- font_scale
- high_contrast
- reduce_motion
- underline_links
- created_at
- updated_at
```

Nama schema, key, foreign key, retention, concurrency dan authorization mesti
melalui design review. Tetapan ini hendaklah dianggap preference UI, bukan rekod
keadaan kesihatan.

**Database migration:** Mungkin, hanya selepas kelulusan Fasa 4.

## 5. Dependency dan urutan pelaksanaan

```text
Fasa 0: Baseline
   ↓
Fasa 1: Accessible default dan zoom
   ↓
Fasa 2: Tetapan Paparan
   ↓
Fasa 3: Hardening dan UAT
   ↓
Fasa 4: Sync merentas peranti (opsyen)
```

Fasa 2 tidak patut didahulukan kerana scaling toolbar di atas baseline 8px hingga
11px masih menghasilkan teks yang kecil dan boleh menyembunyikan masalah reflow.

## 6. Matriks skop minimum

| Kawasan | Baseline font/contrast | Zoom/reflow | Toolbar | Keyboard/screen reader |
|---|---:|---:|---:|---:|
| Login password | Ya | Ya | Ya | Ya |
| MyDigital ID | Ya | Ya | Ya | Ya |
| Recovery dan OTP | Ya | Ya | Ya | Ya |
| Dashboard pengguna | Ya | Ya | Ya | Ya |
| Dashboard pentadbir | Di luar skop | Di luar skop | Di luar skop | Di luar skop |
| User MFA | Ya | Ya | Ya | Ya |
| Modal, FAQ dan alert | Ya | Ya | Ya | Ya |
| Maintenance dan ralat | Ya | Ya | Ya | Ya |
| Laporan/jadual kompleks | Ya | Pengecualian terkawal | Ya | Ya |

Jadual data yang sememangnya dua dimensi boleh menggunakan horizontal scroll
setempat, tetapi keseluruhan halaman tidak boleh dipaksa scroll dua dimensi.

## 7. Strategi teknikal awal

### 7.1 Token dan root state

Gunakan satu stylesheet first-party khusus yang dimuatkan selepas stylesheet
legacy. Preference dipetakan kepada attribute/class root, contohnya secara
konsep:

```text
data-oneid-font-scale="120"
data-oneid-contrast="high"
data-oneid-motion="reduce"
data-oneid-links="underline"
```

Nama akhir mesti distabilkan melalui contract. Elakkan dependency kepada nama
class vendor atau mengubah fail minified vendor secara langsung.

### 7.2 Penyimpanan browser

- gunakan satu JSON object dengan schema version;
- validate semua nilai terhadap allowlist sebelum digunakan;
- pulihkan default jika JSON rosak atau versi tidak dikenali;
- jangan simpan identifier, token, locale sensitif atau data authentication;
- tangkap kegagalan `localStorage` supaya private browsing/storage policy tidak
  mematahkan halaman.

### 7.3 Content Security Policy

Implementasi awal perlu mengelakkan inline script baharu jika boleh. Jika
preference perlu digunakan sebelum paint, pendekatan mesti disemak bersama CSP
semasa supaya tidak memperkenalkan `unsafe-inline` atau pengecualian baharu.

## 8. Risiko dan mitigasi

| Risiko | Kesan | Mitigasi |
|---|---|---|
| Teks besar clip dalam card/modal | Fungsi tidak kelihatan | Fluid height, wrap, ujian 200% |
| Dashboard legacy pecah | Operasi user/admin terganggu | Override kecil berfasa dan characterization |
| Jadual terlalu lebar | Scroll seluruh halaman | Scroll container setempat dan caption |
| Flash saiz asal | Pengalaman tidak stabil | Apply preference awal yang patuh CSP |
| Kontras tinggi hilangkan status | Salah tafsir status | Ikon + teks + border, bukan warna sahaja |
| Toolbar gagal JS | Login terhalang | Progressive enhancement dan default accessible |
| CSS vendor terjejas | Regression luas | Jangan edit vendor; gunakan layer override first-party |
| Preference dianggap data kesihatan | Risiko privasi | Nama neutral dan tiada diagnosis/log analytics |

## 9. Deployment dan rollback

- deploy setiap fasa sebagai perubahan kecil dan boleh dibalikkan;
- jangan gabungkan Fasa 1 dan Fasa 2 dalam satu perubahan besar;
- cachebuster asset perlu dikemas kini secara terkawal;
- rollback Fasa 2 mesti membuang toolbar dan preference application tanpa
  membuang remediasi baseline Fasa 1;
- key `localStorage` lama mesti diabaikan dengan selamat selepas rollback; dan
- perubahan Fasa 4, jika diluluskan, memerlukan migration up/down dan rollback
  runbook berasingan.

## 10. Definition of Done keseluruhan

Inisiatif dianggap lengkap apabila:

- baseline default boleh dibaca tanpa mengaktifkan toolbar;
- browser zoom tidak disekat;
- teks boleh dibesarkan 200% tanpa kehilangan fungsi;
- reflow, kontras, fokus dan target size memenuhi acceptance matrix;
- Tetapan Paparan konsisten pada semua laluan kritikal;
- BM dan English lulus;
- regression authentication/security lulus;
- bukti automated dan manual direkodkan;
- owner UAT memberi penerimaan; dan
- keputusan sama ada Fasa 4 diperlukan direkodkan secara eksplisit.

## 11. Keputusan yang diperlukan sebelum pembangunan

1. Sahkan WCAG 2.2 Level AA sebagai sasaran baseline.
2. Sahkan label produk `Tetapan Paparan / Display Settings`.
3. Sahkan skala maksimum toolbar selepas prototype reflow.
4. Tentukan halaman pilot: dicadangkan login, dashboard pengguna dan satu modal.
5. Namakan owner UAT dan kumpulan pengguna sasaran.
6. Tangguhkan keputusan database sehingga hasil Fasa 3 tersedia.
