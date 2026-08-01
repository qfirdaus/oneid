# LB1 — Schema Dormant dan Persistence Banner Login

**Tarikh:** 1 Ogos 2026  
**Status:** LOCAL PASS / CONTRACT 12 OF 12 PASS / MIGRATION NOT APPLIED  
**Runtime wiring:** Tiada  

## 1. Tujuan

LB1 menyediakan struktur database additive dan persistence boundary bagi modul
banner login. Semua komponen kekal dormant: halaman login masih merender
`banner6.png` dan `banner7.png`, dashboard Admin belum mempunyai tab banner dan
request action map belum menerima mutation banner.

## 2. Artefak

- `docs/migrations/20260801_lb1_login_banner_up.sql`;
- `docs/migrations/20260801_lb1_login_banner_down.sql`;
- `app/LoginBanner/LoginBannerPersistenceInterface.php`;
- `app/LoginBanner/PdoLoginBannerPersistence.php`;
- `app/LoginBanner/LoginBannerPersistenceException.php`; dan
- `tools/lb1_login_banner_contract.php`.

## 3. Model persistence

Lima jadual dipisahkan supaya lifecycle, locale dan filesystem ownership tidak
bercampur:

1. `login_banner` — logical campaign, status, order, schedule dan version.
2. `login_banner_translation` — alt text serta explicit locale policy.
3. `login_banner_asset` — immutable WebP metadata khusus environment.
4. `login_banner_locale_asset` — mapping locale/environment kepada asset.
5. `login_banner_history` — before/after, actor, outcome dan correlation.

`login_banner_locale_asset` membenarkan row BM dan English merujuk asset ID sama.
Foreign key tiga kolum `(asset_id, banner_id, environment)` memastikan mapping
tidak boleh mengambil asset banner lain atau environment lain.

## 4. Constraints

- locale hanya `ms` atau `en`;
- BM mesti `OWN_ASSET`;
- English boleh `OWN_ASSET` atau `SAME_AS_MS`;
- status banner: DRAFT, PUBLISHED, INACTIVE atau ARCHIVED;
- order DB range 1-5;
- schedule tamat mesti selepas mula;
- output asset tepat WebP 1600x800 dan maksimum 512,000 byte;
- filename immutable `login_banner_{32 hex}.webp`;
- SHA-256 lowercase 64 hex;
- environment menggunakan identifier explicit yang selamat;
- history correlation unik; dan
- history outcome hanya SUCCESS atau REJECTED.

Had maksimum lima banner overlap pada effective window masih perlu dikuatkuasa
dalam domain service LB3 di bawah transaction/lock. CHECK database sahaja tidak
dapat mengira overlap beberapa row.

## 5. Persistence contract

Repository menyediakan:

- detection semua lima jadual;
- transaction wrapper yang menolak nested transaction;
- published reader yang ditapis locale, environment, schedule dan asset
  AVAILABLE;
- `FOR UPDATE` banner read;
- insert draft banner, translation dan immutable asset;
- locale-asset mapping yang boleh berkongsi asset;
- versioned update dengan expected version; dan
- correlated history insert.

Repository tidak membuat authorization, CSRF, Step-Up, upload normalization,
domain invariant atau UI response. Tanggungjawab itu kekal bagi LB2-LB5.

## 6. Migration dan rollback

Migration up tidak dijalankan oleh code atau contract. Apply ke shared database
memerlukan arahan eksplisit, backup, preflight dan rehearsal berasingan.

Migration down bersifat destructive dan hanya boleh digunakan selepas:

- semua deployment kembali kepada static banners;
- tiada code merujuk jadual LB1;
- history dan manifest asset dieksport;
- backup disahkan; dan
- owner meluluskan exact rollback window.

Code/feature rollback perlu mendahului schema rollback.

## 7. Verification

```bash
php -l app/LoginBanner/LoginBannerPersistenceException.php
php -l app/LoginBanner/LoginBannerPersistenceInterface.php
php -l app/LoginBanner/PdoLoginBannerPersistence.php
php tools/lb1_login_banner_contract.php
```

Contract ialah source/structure characterization dan tidak connect atau mutate
database.

## 8. Gate LB1

LB1 lulus secara lokal apabila:

- up/down meliputi tepat lima jadual dalam dependency order;
- constraints multilingual, environment dan asset locked;
- public reader fail-closed kepada asset AVAILABLE;
- transaction dan optimistic concurrency contract wujud;
- static login output tidak berubah;
- tiada endpoint/admin wiring; dan
- semua syntax/contract/diff checks lulus.

LB1 local completion tidak sama dengan schema apply approval.

## 9. Handoff

Selepas LB1 local PASS, langkah yang disyorkan ialah LB2 secure banner image
pipeline. Migration apply boleh dijadualkan kemudian melalui gate database
berasingan; LB2 boleh dibangunkan dan diuji dengan filesystem temp serta fake
persistence tanpa mengaktifkan halaman login.

Syntax dan contract dijalankan pada 1 Ogos 2026. LB0 kekal `8/8 PASS` dan LB1
ialah `12/12 PASS`.

**Keputusan semasa:** LB1 LOCAL PASS; DATABASE AND RUNTIME UNCHANGED.
