# R5.4A — Build dan Dependency Reproducibility

Tarikh: 14 Julai 2026  
Status: `PASS_WITH_BUILD_ENV_GATE`

## Objektif

R5.4A mencegah build tooling lama daripada mencipta semula directory root yang telah dibersihkan dan merekodkan dependency yang masih berada di luar Git. Fasa ini tidak memasang package, tidak membuang vendor, tidak mengubah URL dan tidak menyentuh login, SSO atau database.

## Baseline

- `Gruntfile.js` menulis CSS ke `dist/css/style.css`, yang akan mencipta semula root `dist/`.
- Grunt merujuk tiga SCSS variant dan `src/js/` yang tidak wujud.
- Grunt development server menggunakan project root sebagai document root.
- `.bowerrc` menunjuk ke `../vendors/bower_components`, iaitu di luar root projek.
- Node, npm, Grunt, Bower dan Sass tidak tersedia pada host semasa; package installation dan build sebenar tidak dijalankan.
- `docs/migrations/FASA_5_AUTH_HARDENING.sql` sanitised tetapi terhalang oleh rule global `*.sql`.
- Frontend dan dua server-side dependency masih di-ignore Git. Detail direkodkan dalam `docs/R5_4A_DEPENDENCY_REGISTER.tsv`.

## Perubahan

1. Grunt CSS output ditukar kepada `public/dist/css/style.css`.
2. Grunt development server kini menggunakan `public/` sebagai base.
3. Task dark/RTL dan `src/js` yang sumbernya tiada dikeluarkan daripada active build contract.
4. `grunt build` menjadi deterministic finite task; `grunt serve` digunakan untuk server/watch.
5. Bower target ditukar kepada `public/vendors/bower_components`.
6. Package metadata ditandakan private dan dependency Grunt yang tidak digunakan dibuang.
7. Migration sanitised di bawah `docs/migrations/` dikecualikan daripada ignore global SQL.
8. `tools/r54_structure_contract.php` ditambah untuk mencegah root duplicate dan missing structural dependency.

## Sempadan dan keputusan dependency

R5.4A belum mendakwa full clean-room reproducibility:

- 59 dependency Bower menggunakan range dan satu wildcard; exact resolution tidak lengkap.
- Hanya 53 daripada 76 physical Bower component mempunyai `.bower.json` resolution metadata.
- `public/vendors/vectormap`, `public/vendors/typeahead.js`, Device Detector dan Spyc ialah runtime dependency tetapi kekal ignored.
- Tiada dependency diteka atau dimuat turun dalam fasa ini.

Sebelum deployment daripada fresh clone, dependency tersebut mesti datang daripada artifact/backup yang disahkan atau dimigrasikan kepada package manager dengan lock file dalam fasa berasingan.

## Verification

Jalankan:

```bash
php tools/r54_structure_contract.php
php tools/restructure_smoke.php https://oneid.local --insecure
php tools/restructure_smoke.php https://oneid-next.local
```

Build sebenar hanya boleh diuji pada host yang mempunyai runtime Node legacy yang serasi:

```bash
npm install
npm run check:structure
npm run build
```

Sebelum `npm run build`, backup `public/dist/css/style.css`. Jangan jalankan `bower install` pada production/UAT tanpa artifact diff kerana version range lama boleh mendapatkan dependency berbeza.

Keputusan pelaksanaan:

| Verification | Keputusan |
|---|---|
| PHP lint structure contract | PASS |
| R5.4A structure contract | PASS — `31/31` |
| Restructure smoke `oneid.local` | PASS — `10/10` |
| Restructure smoke `oneid-next.local` | PASS — `10/10` |
| R5.2 characterization `oneid.local` | PASS — `70/70` |
| R5.2 characterization `oneid-next.local` | PASS — `70/70` |
| JSON parse `.bowerrc`, `package.json`, `bower.json` | PASS |
| Root duplicate directory absent | PASS — 6/6 |
| Actual `npm run build` | NOT RUN — Node/npm/Grunt/Sass tiada pada host |

`PASS_WITH_BUILD_ENV_GATE` bermaksud application/runtime regression lulus dan build path contract telah dibetulkan, tetapi output compiler belum boleh disahkan byte-for-byte sehingga toolchain yang serasi disediakan.

## Rollback

Selepas perubahan dikomit, rollback paling selamat ialah merevert commit R5.4A:

```bash
git revert <R5.4A-commit-hash>
```

Tiada rollback database diperlukan kerana migration hanya mula ditrack; ia tidak dijalankan oleh fasa ini.

## Gate seterusnya

R5.4B hanya boleh bermula selepas:

- structure contract dan smoke kedua-dua hostname lulus;
- perubahan Git menunjukkan migration sahaja, bukan `sso_db.sql`;
- tiada root `dist/` atau `vendors/bower_components/` dicipta;
- pemilik menerima bahawa exact frontend dependency lock masih pending.
