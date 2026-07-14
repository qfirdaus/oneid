# U1 — Dashboard Carian, Favourite dan NON SSO

**Tarikh:** 14 Julai 2026  
**Release:** OneID v2.0.4  
**Status:** IMPLEMENTED — AUTOMATED CONTRACT PASS; BROWSER UAT PENDING

## Tujuan

U1 menaik taraf direktori aplikasi pengguna tanpa mengubah polisi akses:

- carian nama dan fungsi aplikasi merentas semua kategori;
- tab Favourite berikon bintang pada kedudukan pertama;
- preference Favourite yang kekal mengikut akaun;
- aplikasi Favourite kekal berada dalam kategori asal;
- tab NON SSO mempunyai identiti visual berbeza;
- aplikasi SSO menggunakan tindakan `Login`, manakala NON SSO menggunakan `Akses`.

## Sempadan keselamatan

`user_app_favourite` ialah preference paparan sahaja. Ia tidak menambah rekod
`acl_group`, `acl_single` atau membuang `acl_blacklist`. Server menerima pemilik
Favourite daripada session aktif. Ketika menambah Favourite, server mengesahkan
aplikasi masih berada dalam effective ACL pengguna.

Semakan ACL sebenar masih dibuat semula oleh `go_to_service_provider` setiap kali
pengguna menekan `Login` atau `Akses`. Oleh itu, rekod Favourite lama tidak boleh
digunakan untuk memintas deny atau perubahan kategori.

## Deployment

1. Semak migration belum/sudah dipasang:

   `php tools/u1_user_app_favourites_migrate.php --check`

2. Pasang jadual preference baharu:

   `php tools/u1_user_app_favourites_migrate.php --apply`

3. Jalankan contract:

   `npm run check:user-dashboard`

4. Hard refresh dashboard pengguna.

## UAT manual

1. Cari sebahagian nama aplikasi yang berada dalam kategori lain.
2. Pastikan kategori yang mengandungi padanan dipilih dan senarai ditapis.
3. Kosongkan carian dan pastikan semua aplikasi muncul semula.
4. Pilih bintang pada satu aplikasi; pastikan ia muncul dalam tab Favourite dan
   kekal dalam kategori asal.
5. Logout/login dan pastikan Favourite masih ada untuk akaun yang sama.
6. Buang bintang dan pastikan ia hilang daripada Favourite sahaja.
7. Pastikan aplikasi kategori NON SSO memaparkan `Akses` dan tabnya mempunyai
   warna berbeza; aplikasi SSO memaparkan `Login`.
8. Pastikan kedua-dua tindakan masih membuka aplikasi yang betul dan ACL deny
   masih menghasilkan penolakan.

## Rollback

Rollback kod boleh dilakukan tanpa menyentuh ACL. Jika preference turut mahu
dibuang, jalankan SQL `docs/migrations/U1_USER_APP_FAVOURITES_DOWN.sql` selepas
backup. Tindakan itu hanya memadam pilihan Favourite pengguna.
