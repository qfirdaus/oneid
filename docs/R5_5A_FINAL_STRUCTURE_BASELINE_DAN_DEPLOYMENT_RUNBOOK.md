# R5.5A — Final Structure Baseline dan Deployment Runbook

Tarikh: 14 Julai 2026
Status: `PASS`

## Objektif

R5.5A menetapkan sempadan struktur selepas R5.4 tanpa memindahkan endpoint
berisiko tinggi. Ia menjawab kekeliruan bahawa directory bernama sama semestinya
duplikasi dan menyediakan kontrak yang menghalang deployment daripada membuka
semula project root kepada web.

## Hasil audit directory berulang

Classification penuh berada dalam `docs/R5_5A_DIRECTORY_CLASSIFICATION.tsv`.

Kesimpulan:

- `admin/public-admin`, `page/public-page` dan `lib/public-lib` ialah
  wrapper-to-private-implementation boundary, bukan dua aplikasi aktif;
- root `vendors` ialah dependency PHP, `public/vendors` ialah dependency browser;
- root `src` ialah build source SCSS, `lib/src` ialah PHPMailer runtime;
- `admin/const` dan `page/const` mempunyai caller aktif dan kandungan berbeza;
- nama `dist`, `css`, `js`, `src` dan `docs` berulang di bawah
  `public/vendors/bower_components` dimiliki package vendor dan tidak boleh
  dianggap duplicate project directory;
- tiada symlink aktif dan tiada path world-writable di luar quarantine.

Tiada directory runtime dipadam dalam R5.5A.

## Keputusan verification

| Semakan | Keputusan |
| --- | --- |
| PHP lint structure-boundary contract | PASS |
| R5.5A structure-boundary contract | 60/60 PASS |
| R5.4A structure regression | 31/31 PASS |
| R5.4B asset regression | 27/27 PASS |
| R5.4C compatibility regression | 24/24 PASS |
| Smoke `oneid.local` | 10/10 PASS |
| Smoke `oneid-next.local` | 10/10 PASS |
| Full characterization `oneid.local` | 69/69 PASS |
| Full characterization `oneid-next.local` | 69/69 PASS |
| Private URL boundary | `README.md`, `package.json`, `sso_db.sql` dan `docs/` semuanya 404 |

R5.5A ditutup sebagai PASS. Tiada duplicate project directory yang selamat
untuk dibuang secara pukal ditemui; semua pasangan yang relevan mempunyai
caller, ownership atau deployment purpose yang berbeza.

## Struktur deployment

```text
public/                 document root tunggal
app/                    application layer
admin/, page/, lib/     private compatibility implementation
bootstrap/, config/     bootstrap dan non-secret configuration target
resources/, src/        non-public resource/build source
tests/, tools/, docs/   engineering/operations only
storage/                runtime-local; tidak boleh disajikan
vendors/                private PHP runtime dependency
```

## Permission baseline

Baseline portable:

- file source/config bukan secret: tidak world-writable;
- directory source: tidak world-writable;
- `.private/runtime.php` dan database dump local: tiada access untuk `other`;
- `storage/` dan `public/public_img/` hanya boleh diberi write kepada account
  PHP-FPM yang sebenar, bukan mode `0777`;
- Nginx hanya membaca `public/`; ia tidak memerlukan read access kepada `docs/`,
  `tests/`, `tools/` atau quarantine melalui HTTP.

Owner/group berbeza antara host dibenarkan. Contract mengunci capability
berisiko (`world-writable` dan symlink), bukan nama user tertentu.

## Deployment verification

Konfigurasi semasa menggunakan default committed di `config/runtime.php` dan
override server-local di `.private/runtime.php`. Fail private itu tidak masuk
Git dan mesti disediakan sebelum PHP-FPM menerima trafik. Template Nginx,
PHP-FPM dan cron berada di `deployment/`.

```bash
cd /var/www/app/oneid-uat

php tools/r54_structure_contract.php
php tools/r54_asset_contract.php
php tools/r54_compatibility_contract.php
php tools/r55_structure_boundary_contract.php

php tools/restructure_smoke.php https://oneid.local --insecure
php tools/restructure_smoke.php https://oneid-next.local
```

Web-server boundary juga mesti disemak:

```bash
curl -I https://oneid.local/README.md
curl -I https://oneid.local/package.json
curl -I https://oneid.local/sso_db.sql
curl -I https://oneid.local/docs/
```

Semua URL private mesti memberi 404, bukan kandungan fail dan bukan halaman
login hasil front-controller fallback.

## Gate dan kerja seterusnya

R5.5A tidak memberi kebenaran untuk:

- membuang `public/lib/sso_IDP_index.php` atau `sso_IDP_sub.php` sebelum registry
  consumer Fasa 6B dan evidence observation lengkap;
- memindahkan dashboard, `q_func`, API, IDMS atau SKP secara bulk;
- menggabungkan `vendors` private dan public;
- membuang upload lama tanpa retention/database reconciliation.

Selepas R5.5A lulus, R5.5B boleh menilai artefak transitional kecil seperti
root `.htaccess`, constant `LEGACY_PUBLIC_PATH`, placeholder directory dan
parallel hostname. Setiap item memerlukan caller/rollback decision tersendiri.

## Rollback

R5.5A hanya menambah dokumentasi, README dan verification contract. Selepas
commit, rollback ialah:

```bash
git revert <R5.5A-commit-hash>
```

Tiada database, upload, Nginx atau runtime endpoint diubah dalam fasa ini.
