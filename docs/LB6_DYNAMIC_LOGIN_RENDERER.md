# LB6 — Dynamic Login Banner Renderer

**Status:** LOCAL PASS / FEATURE FLAG DEFAULT OFF / MIGRATION NOT APPLIED

LB6 menambah reader awam read-only kepada halaman login. Capability dikawal
oleh `ONEID_LOGIN_BANNER_ENABLED`, yang committed default-nya `false`. Oleh itu
deployment tidak berubah sehingga schema, environment dan activation diluluskan.

## Resolution contract

Apabila flag aktif, reader:

1. menerima locale tepat `ms` atau `en`, environment explicit dan masa UTC;
2. memastikan semua jadual LB1 tersedia;
3. membaca maksimum lima banner `PUBLISHED` yang effective bagi locale tersebut;
4. mengekalkan urutan database `display_order`, kemudian `banner_id`;
5. menolak duplicate banner ID, alt text tidak sah atau filename bukan immutable;
6. mengesahkan realpath berada dalam direktori environment, bukan symlink,
   WebP 1600×800, 1–512000 bait serta SHA-256 sepadan;
7. menghasilkan URL relatif immutable tanpa query timestamp.

Mapping English `SAME_AS_MS` telah diselesaikan secara explicit oleh mapping
locale LB1/LB3. Reader tidak membuat silent cross-locale fallback.

## Fail-safe rendering

`banner6.png` dan `banner7.png` dibina sebagai manifest awal pada setiap request.
Manifest itu hanya diganti apabila sekurang-kurangnya satu row dinamik lulus
semua validation. Schema tiada, environment salah, DB unavailable, asset missing,
checksum mismatch atau exception lain tidak menghalang borang password/MyDigital
ID; exception hanya direkod bersama correlation ID tanpa SQL/path kepada browser.

Carousel menetapkan hanya item pertama `active`, interval 6 saat, menyembunyikan
controls apabila hanya satu banner, menggunakan eager/high priority untuk imej
pertama dan lazy loading untuk seterusnya. `src`, `alt`, width dan height semuanya
di-escape atau cast sebelum output.

## Verification

- `php tests/characterization/lb6_login_banner_public_resolver.php`
- `php tools/lb6_login_banner_public_contract.php`
- regression LB0–LB5 dan PHP syntax.

LB6 tidak mengaplikasi migration dan tidak menghidupkan feature flag. LB7
seterusnya meliputi reconciliation, backup, quarantine dan rollback operasi.
