# R5.0 — Baseline Physical Restructuring dan Rollback

**Tarikh:** 14 Julai 2026, 02:34 +0800  
**Status:** BASELINE COMPLETE — R5.1A kemudian dilaksanakan dalam change berasingan  
**Prasyarat:** R4 `R4-20260714-014057` telah ditutup

## 1. Objektif

R5 menukar public-root transitional kepada struktur fizikal yang lebih kemas
tanpa memutuskan URL lama. R5.0 hanya merekod baseline, dependency dan urutan
change. Ia tidak memindahkan, memadam atau quarantine fail.

Struktur sasaran jangka sederhana:

```text
oneid-uat/
├── app/                 # domain/service/repository dalaman
├── bootstrap/           # bootstrap dan path resolver
├── config/              # konfigurasi bukan secret
├── database/            # migration/reference terkawal
├── docs/                # audit, runbook dan change record
├── public/              # satu-satunya web document root
│   ├── admin/
│   ├── assets/
│   ├── uploads/
│   ├── index.php
│   └── endpoint awam yang diluluskan
├── resources/           # view/source frontend bukan terus awam
├── storage/             # log/cache/quarantine bukan awam
├── tests/               # characterization dan integration test
└── tools/               # CLI/deployment tooling
```

## 2. Baseline R5.0

| Item | Keputusan |
|---|---:|
| Fail fizikal dalam `public/` | 13 |
| Symlink dalam `public/` | 12 |
| Broken symlink | 0 |
| PHP wrapper lint | 12/12 lulus |
| Smoke `oneid.local` selepas pilot | 10/10 lulus |
| Nginx config SHA-256 | `7dbe68cef80ff6e1d7cb03bac8daece3717b67588b61d20ecf1fdb6c5d5480e4` |

Manifest baseline:

- `docs/R5_0_PUBLIC_ROOT_MANIFEST.sha256` — checksum 13 fail fizikal;
- `docs/R5_0_PUBLIC_ROOT_SYMLINKS.tsv` — source dan target 12 symlink.

Public-root masih menggunakan wrapper ke source lama untuk semua entry point
utama. Aset pula dicapai melalui symlink:

| Target lama | Saiz anggaran | Public exposure transitional |
|---|---:|---|
| `assetsM/` | 33 MB | `public/assetsM` |
| `dist/` | 5.8 MB | `public/dist` |
| `img/` | 4.2 MB | `public/img` |
| `public_docs/` | 1.6 MB | `public/public_docs` |
| `public_img/` | 4.1 MB | `public/public_img` |
| `vendors/` | 73 MB | tiga symlink terpilih di `public/vendors/` |
| `videos/` | 4.4 MB | `public/videos` |

Jumlah aset lama yang masih menjadi dependency public ialah kira-kira 126 MB.
Nilai ini bukan saiz sasaran akhir kerana `vendors/` hanya didedahkan secara
terpilih.

## 3. Urutan R5 Yang Diluluskan Secara Teknikal

### R5.0 — Baseline dan stabilization

- tutup R4 dan simpan checksum konfigurasi;
- inventori wrapper, symlink, route dan saiz;
- kekalkan smoke 10/10;
- tiada file movement.

### R5.1 — Physicalize aset awam

- bina inventori request aset daripada browser/network dan source scan;
- salin aset yang digunakan ke struktur sasaran dalam `public/`;
- banding checksum sebelum pertukaran;
- tukar satu kumpulan symlink setiap batch;
- kekalkan source lama sepanjang observation;
- jangan upgrade versi dependency dalam change ini.

Cadangan batch: `public_docs/videos`, kemudian `img/public_img`, kemudian
`assetsM/dist`, dan akhir sekali subset `vendors`.

### R5.2 — Pisahkan application layer

- bina `app/`, `config/`, `resources/` dan `tests/` hanya apabila caller map
  tersedia;
- extract domain secara kecil tanpa mengubah response contract;
- wrapper public kekal sebagai entry point nipis;
- jangan gabungkan query/schema migration dengan file movement.

### R5.3 — Quarantine fail retired

- mula dengan metadata, `diag/agent.php` dan cron yang telah retired oleh owner;
- gunakan quarantine di luar document root dan manifest SHA-256;
- page/controller legacy menunggu access evidence 30 hari;
- endpoint vendor/berkala menunggu 90 hari.

### R5.4 — Sunset compatibility SSO

- hanya selepas registry Fasa 6B lengkap;
- wrapper `public/lib/sso_IDP_index.php` dan `sso_IDP_sub.php` tidak boleh dibuang
  berdasarkan source scan sahaja;
- gunakan log 30–90 hari dan approval setiap consumer.

### R5.5 — Finalize struktur

- buang symlink transitional yang telah tiada caller;
- nilai penamatan `oneid-next.local` dalam change Nginx berasingan;
- hasilkan tree, permission manifest dan deployment runbook akhir.

## 4. Gate Sebelum R5.1

- [x] R4 ditutup dan rollback source tersedia.
- [x] `oneid.local` smoke 10/10.
- [x] Sekurang-kurangnya satu SSO consumer berjaya end-to-end.
- [x] Wrapper dan symlink R5.0 diinventori.
- [ ] Tempoh stabilization public-root dipersetujui dan tamat tanpa regression.
- [ ] Browser asset coverage user/admin direkodkan.
- [ ] Manifest checksum bagi batch aset pertama disediakan.
- [ ] Ruang disk untuk copy-before-switch disahkan.
- [ ] Rollback owner mengesahkan source lama tidak akan dipadam semasa R5.1.

Defect e-Prestasi bukan blocker kepada R5.0, tetapi perubahan R5 tidak boleh
digunakan untuk membetulkan consumer tersebut. Diagnosis e-Prestasi ialah change
berasingan.

## 5. Strategi Rollback R5.1

R5.1 menggunakan **copy → verify → switch → observe**, bukan `mv` terus.

Jika aset gagal:

1. pulihkan symlink batch kepada target lama;
2. sahkan checksum target lama;
3. jalankan smoke OneID 10/10;
4. uji login user/admin dan SSO IQS-Framework;
5. rekod 404/console error dan batalkan batch;
6. jangan padam salinan baharu sehingga diagnosis selesai.

Rollback R5 tidak mengubah Nginx public-root R4 kecuali terdapat bukti bahawa
konfigurasi Nginx sendiri rosak.

## 6. Keputusan R5.0

R5.0 selesai sebagai baseline dokumentasi. R5.1 belum dilaksanakan. Langkah
selamat seterusnya ialah menetapkan stabilization window, kemudian menghasilkan
browser asset coverage dan manifest batch pertama `public_docs/videos`.

**Kemaskini 02:38 +0800:** Owner mengarahkan proceed sebelum stabilization 24
jam tamat. R5.1A `public_docs/videos` telah dilaksanakan melalui copy-before-switch
dan validation kedua-dua hostname lulus. Rujuk
`R5_1A_PUBLIC_DOCS_VIDEOS_PELAKSANAAN_DAN_ROLLBACK.md`. Baseline 12 symlink di
dokumen ini kekal sebagai snapshot sebelum R5.1A; keadaan selepas change ialah
10 symlink.

**Kemaskini 02:44 +0800:** R5.1B `img/public_img` technical validation lulus.
Semua 35 icon aktif, write-path dan security probe lulus; manual upload UI masih
pending. Rujuk `R5_1B_IMG_PUBLIC_IMG_PELAKSANAAN_DAN_ROLLBACK.md`. Keadaan selepas
change ialah 8 symlink.

**Kemaskini 02:49 +0800:** Upload UI R5.1B disahkan berjaya dan hanya menulis ke
physical public-root. R5.1C `assetsM/dist` kemudian dilaksanakan; 1,021 checksum,
50 request aset merentas dua host dan security probe lulus. Hard-refresh visual
dan console owner masih pending. Keadaan selepas change ialah 6 symlink.

**Kemaskini 02:53 +0800:** Owner mengarahkan continuation ke R5.1D. Tiga favicon
dan tiga subset vendor diphysicalize; 3,910 checksum, vendor/security contract
dan smoke dua host lulus. Public-root kini mempunyai 0 symlink. Final visual,
console dan observation gate masih pending sebelum R5.2.

**Kemaskini 03:01 +0800:** Owner mengesahkan hard refresh dan visual normal.
Access/error log menunjukkan aset 200/304, dashboard user 200 dan tiada 404/5xx
runtime atau fatal/permission error baharu. R5.1 implementation serta
visual/network validation ditutup; console evidence berasingan dan observation
window masih terbuka sebelum R5.2.

**Kemaskini 03:06 +0800:** R5.2A characterization/caller map dilaksanakan tanpa
memindahkan PHP runtime. Dua hostname lulus 70/70 checks setiap satu, rangka
`app/`, `config/`, `resources/` dan `tests/` disediakan, dan 12 public wrapper
dipetakan. Rujuk `R5_2A_CHARACTERIZATION_CALLER_MAP_DAN_ROLLBACK.md`. Langkah
yang diluluskan seterusnya hanyalah R5.2B0 authenticated logout characterization.
