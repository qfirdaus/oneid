# OneID 2.13.3 — Kebolehcapaian Visual Pengguna

## Ringkasan

Release 2.13.3 memperkenalkan baseline paparan pengguna yang lebih mudah dibaca
serta Tetapan Paparan untuk saiz teks, kontras, pergerakan dan pautan. Perubahan
dihadkan kepada halaman pengguna dan awam. Paparan operasi pentadbir tidak
diubah.

## Perubahan utama

- teks operasi, line-height, focus indicator dan target kawalan pengguna
  diperkemas;
- browser zoom dibenarkan pada dashboard pengguna;
- ikon gear Tetapan Paparan diletakkan bersebelahan language selector;
- lima skala teks tersedia: 100%, 108%, 115%, 123% dan maksimum 130%;
- pilihan kontras tinggi, kurangkan animasi dan gariskan pautan ditambah;
- preference disimpan dalam `localStorage` berversi dengan allowlist dan fallback
  yang selamat;
- panel menyokong keyboard, Escape, live region dan kedudukan viewport-bound; dan
- login maintenance admin, MFA maintenance admin serta semua halaman operasi
  pentadbir kekal dikecualikan.

## Data, keselamatan dan compatibility

Tiada migration database, runtime key atau network API baharu. Preference tidak
mengandungi identiti, token atau data kesihatan dan tidak dihantar ke server.
Aliran login password, MyDigital ID, MFA, sesi, password recovery, CSRF dan ACL
tidak diubah.

## Deployment

Deployment ialah fast-forward code biasa. Asset cachebuster Fasa 2 ialah
`20260915-4`. Ujian staging perlu meliputi BM/English, lima skala, desktop,
mobile, keyboard-only, zoom 200%, kombinasi kontras 130% dan reset.

## Verification

```bash
npm run check:accessibility-phase1
npm run check:accessibility-phase2
npm run check:accessibility-phase3
npm run check:accessibility-runtime
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
```
