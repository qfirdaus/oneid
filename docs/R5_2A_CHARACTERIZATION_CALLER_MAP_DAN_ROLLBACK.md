# R5.2A — Characterization, Caller Map dan Rollback

Tarikh: 14 Julai 2026

Change ID: `R5-2A-20260714-030555`

Owner perubahan: Pemilik sistem OneID

Owner rollback: Pemilik sistem OneID

Status: **SELESAI — BASELINE SAHAJA, TIADA PEMINDAHAN PHP RUNTIME**

## 1. Tujuan

R5.2 akan menyusun application layer ke struktur yang lebih jelas tanpa menukar
URL awam atau response contract. R5.2A sengaja dihadkan kepada:

- merekod wrapper awam, source target, dependency langsung dan caller diketahui;
- membina characterization test read-only bagi dua hostname;
- menyediakan rangka kosong `app/`, `config/`, `resources/` dan `tests/`;
- menentukan urutan extraction berdasarkan risiko;
- menyediakan rollback sebelum sebarang PHP runtime dipindahkan.

R5.2A **tidak** memindahkan controller, library, view, konfigurasi runtime atau
secret. Semua public wrapper dan source target masih berada pada lokasi asal.

## 2. Keadaan Sebelum dan Selepas

| Perkara | Sebelum R5.2A | Selepas R5.2A |
|---|---|---|
| Public document root | `public/` fizikal, 0 symlink | Tidak berubah |
| Public PHP wrapper | 12 wrapper | 12 wrapper, tidak berubah |
| PHP source target | Root, `admin/`, `page/`, `lib/` | Tidak berubah |
| Application directories | Belum mempunyai kontrak penggunaan | Rangka dan README sahaja |
| Automated application characterization | Smoke R4/R5 asas | 70 checks bagi setiap hostname |
| Runtime behavior | Baseline R5.1 | Tidak berubah |

## 3. Public Wrapper dan Source Target

Caller map mesin-baca penuh disimpan dalam
`docs/R5_2A_CALLER_MAP.tsv`. Ringkasan kumpulan ialah:

| Kumpulan | Entry point | Penilaian |
|---|---|---|
| Login | `public/index.php` | Besar, auth/OTP/reset dan AJAX; risiko tinggi |
| Integration | `public/api.php`, `idms.php`, `skp_api.php` | Consumer luaran/live; risiko kritikal |
| Admin | dashboard, logout, user list | Dashboard 3,905 baris dan bergantung pada `q_func` |
| User | dashboard, logout | Dashboard 1,261 baris dan bergantung pada `q_func` |
| AJAX | `public/lib/q_func.php` | Monolith 1,107 baris, banyak action/dependency; risiko kritikal |
| Legacy SSO | dua compatibility wrapper | Caller luaran belum lengkap; tertakluk kepada R5.4 |

Source scan mengesahkan `index.php`, `page/dashboard.php` dan
`admin/dashboard.php` memanggil URL `/lib/q_func` melalui JavaScript. Oleh sebab
itu `lib/q_func.php` ialah endpoint AJAX pusat, bukan library dalaman biasa.

Had caller map: scan repository hanya membuktikan caller yang berada dalam
repository. Ia tidak boleh membuktikan ketiadaan consumer luaran bagi API atau
legacy SSO. Access log, registry Fasa 6B dan pengesahan owner consumer masih
menjadi gate sebelum kumpulan tersebut diubah.

## 4. Characterization Test

Fail baharu:

- `tests/characterization/r52_contracts.php` — definisi contract;
- `tools/r52_characterization.php` — runner CLI read-only.

Setiap run melakukan 70 checks:

- 12 hubungan wrapper kepada source target;
- PHP lint wrapper dan target unik;
- public-root mesti mempunyai 0 symlink;
- 18 HTTP response contract;
- 15 public-boundary/security probe yang mesti menghasilkan `404`.

Arahan baseline:

```bash
php tools/r52_characterization.php https://oneid.local --insecure
php tools/r52_characterization.php https://oneid-next.local
```

Keputusan 14 Julai 2026:

| Host | Checks | Gagal | Keputusan |
|---|---:|---:|---|
| `https://oneid.local` | 70 | 0 | PASS |
| `https://oneid-next.local` | 70 | 0 | PASS |
| Jumlah | 140 | 0 | PASS |

Test ini tidak menggunakan credential, tidak mencipta session login dan tidak
menghantar request body. Ia sesuai sebagai fast regression gate, tetapi belum
menggantikan UAT authenticated atau live integration test.

## 5. Struktur Sasaran Yang Disediakan

Rangka berikut telah disediakan tanpa runtime wiring:

```text
app/          # service, repository dan domain logic masa hadapan
config/       # konfigurasi bukan secret masa hadapan
resources/    # view dan source resource bukan-public masa hadapan
tests/        # characterization dan regression test
```

README dalam setiap direktori menerangkan sempadan penggunaannya. Credential
kekal melalui environment atau `ONEID_SECRETS_FILE`; ia tidak boleh dipindahkan
ke `config/`.

## 6. Susunan Batch Yang Dicadangkan

### R5.2B0 — Tambah characterization authenticated

Sebelum extraction pertama:

- capture login user dan admin menggunakan akaun ujian;
- capture cookie/session sebelum dan selepas logout;
- buktikan session lama tidak boleh membuka dashboard selepas logout;
- sahkan redirect destination dan cookie invalidation;
- elakkan credential ditulis ke output atau repository.

### R5.2B1 — Consolidate logout handler

Calon extraction pertama ialah logik duplikasi dalam `page/logout.php` dan
`admin/logout.php`, masing-masing 16 baris. Sasaran dicadangkan ialah handler
bersama seperti `app/Auth/LogoutHandler.php`.

Syarat batch:

- URL `/page/logout.php` dan `/admin/logout.php` kekal;
- public wrapper kekal;
- root compatibility entry point kekal nipis;
- session invalidation dan redirect mesti sama dengan baseline;
- satu batch, satu rollback.

### R5.2C dan seterusnya

1. extract pure helper/service kecil yang tidak mempunyai HTTP side effect;
2. pecahkan view/service dashboard secara incremental;
3. pindahkan integration controller hanya bersama registry dan consumer test;
4. pecahkan `q_func` berdasarkan action di belakang endpoint yang sama;
5. legacy SSO wrapper menunggu proses sunset R5.4.

Jangan gabungkan file movement dengan schema migration, dependency upgrade,
perubahan URL, token format atau authorization policy.

## 7. Gate Wajib Setiap Extraction

- [ ] Characterization dua hostname lulus sebelum perubahan.
- [ ] Contract khusus feature yang hendak diextract tersedia.
- [ ] URL, method, status, content type dan redirect tidak berubah.
- [ ] Authentication, authorization dan CSRF behavior tidak berubah.
- [ ] Public wrapper/source compatibility dikekalkan.
- [ ] PHP lint dan manual UAT berkaitan lulus.
- [ ] Tiada 404/5xx atau PHP fatal/warning baharu dalam log.
- [ ] Caller map dikemas kini.
- [ ] Rollback batch diuji atau sekurang-kurangnya disahkan boleh dilaksanakan.

## 8. Rollback R5.2A

Tiada rollback runtime diperlukan kerana application code, Nginx dan database
tidak berubah. Jika tooling R5.2A perlu dibatalkan, buang hanya fail baharu:

```text
app/README.md
config/README.md
resources/README.md
tests/README.md
tests/characterization/r52_contracts.php
tools/r52_characterization.php
docs/R5_2A_CALLER_MAP.tsv
docs/R5_2A_CHARACTERIZATION_CALLER_MAP_DAN_ROLLBACK.md
```

Jangan buang direktori jika kemudian telah mengandungi fail daripada batch
lain. Selepas rollback tooling, jalankan smoke R4/R5 sedia ada pada kedua-dua
hostname.

Rollback R5.2B dan batch seterusnya mesti memulihkan fail extraction batch itu
sahaja; ia tidak boleh mengembalikan Nginx kepada root lama atau membatalkan
hardening Fasa 1–6.

## 9. Checksum Evidence

| Fail | SHA-256 |
|---|---|
| `tools/r52_characterization.php` | `5ea0c15d83fe278ac4a37ccb27dac3a75e3efbe22efb215272a3673caf6852b9` |
| `tests/characterization/r52_contracts.php` | `36d9caa6feaae8331f173bc1b0ce336702a2d35a6851347f922fdc90e3615b32` |
| `app/README.md` | `fb797b1b949aa2d8f314e5b1dabe44236077c2e324a7c77c92939c946c747dd0` |
| `config/README.md` | `2e8499e336717e985b7c2d6b0595c5357435b887afa45d21ff8cfcc9bb5cc2ed` |
| `resources/README.md` | `232a6ff682812efad6529978c0db358a1d1640e4c56c6ac2b2fc2079ec6dd616` |
| `tests/README.md` | `ddb78693b19a7e91a730dafb07466046bcc5a9a92e4f5ab08ae557460e3b0906` |

Checksum dokumen ini dan caller map perlu diambil selepas dokumentasi ditutup,
jika ia hendak dimasukkan ke change-management artifact luaran.

## 10. Keputusan

R5.2A selesai dan baseline application-layer kini boleh diuji secara berulang.
Keputusan **GO hanya untuk R5.2B0**, iaitu menambah characterization authenticated
logout. Belum ada kelulusan untuk memindahkan endpoint integration, dashboard,
`q_func` atau legacy SSO.

**Kemaskini 03:12 +0800:** Tooling R5.2B0 telah disediakan tanpa runtime change.
Execution menunggu credential akaun ujian melalui environment. Rujuk
`R5_2B0_AUTHENTICATED_LOGOUT_CHARACTERIZATION.md`.

**Kemaskini 03:18 +0800:** Empat cubaan pertama R5.2B0 mencapai endpoint dan
melepasi anonymous/CSRF checks, tetapi authentication ditolak oleh aplikasi.
Tiada routing error atau 5xx. Runner dikemas kini untuk merekod reason code
selamat dan menerima JSON body dengan content type legacy semasa.

**Kemaskini 03:22 +0800:** Cubaan kedua R5.2B0 berjaya. Admin lulus 14/14 pada
kedua-dua hostname. Run user mendapat 13/14 kerana akaun admin yang sama
digunakan; semua logout/session invalidation checks tetap lulus. R5.2B0 berstatus
conditional PASS sehingga akaun user biasa diuji atau pengecualian diterima.

**Kemaskini 03:35 +0800:** Owner menerima pengecualian normal-user dan R5.2B1
dilaksanakan. Shared `app/Auth/LogoutHandler.php` kini digunakan oleh dua
compatibility entry point. Post-change regression 70/70 dan authenticated admin
14/14 lulus pada kedua-dua hostname; R5.2B1 ditutup.
