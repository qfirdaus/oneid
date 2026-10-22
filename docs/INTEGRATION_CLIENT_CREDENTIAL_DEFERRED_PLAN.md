# Pelan tertangguh credential client integrasi OneID

## Status keputusan

Pelaksanaan credential client untuk integrasi SSO downstream ditangguhkan.
Dokumen ini ialah rekod keputusan dan panduan masa hadapan sahaja. Ia tidak
memberi kelulusan untuk mengubah kod, konfigurasi, credential, WAF atau mod
authentication di staging mahupun production.

## Latar belakang

OneID kini menerima permintaan SSO legacy daripada kira-kira 42 projek yang
diselenggara oleh lebih 20 pembangun. Trafik production melalui WAF yang
memaparkan alamat masking `172.16.4.65` kepada OneID. Oleh itu alamat tersebut
ialah identiti laluan WAF, bukan identiti aplikasi downstream sebenar.

Audit OneID merekod permintaan legacy sebagai `missing_credentials` dalam mod
`observe`. Permintaan masih diproses supaya integrasi sedia ada terus berfungsi.
Log semasa tidak mencukupi untuk mengaitkan setiap permintaan kepada projek
downstream tertentu kerana semua trafik kelihatan datang daripada alamat WAF
yang sama dan client ID belum digunakan.

## Keputusan operasi semasa

1. Kekalkan `ONEID_API_AUTH_MODE=observe` untuk endpoint SSO legacy.
2. Jangan aktifkan `enforce` di staging atau production sebagai sebahagian kerja
   ini.
3. Jangan mewajibkan 42 downstream sedia ada menambah client ID atau secret.
4. Jangan menyekat `172.16.4.65`; alamat itu digunakan oleh WAF untuk trafik
   OneID production.
5. Kekalkan HTTPS, validasi token, timeout, rate limit, audit dan kawalan WAF
   sedia ada.
6. Anggap `missing_credentials` sebagai observation bagi trafik legacy, bukan
   kegagalan operasi selagi mod masih `observe`.

## Model migrasi masa hadapan

Jika credential client dilaksanakan kemudian, gunakan migrasi berperingkat:

- Integrasi baharu menggunakan client ID dan secret unik sejak awal.
- Sistem lama dimigrasikan hanya ketika maintenance atau pembangunan versi
  baharu masing-masing.
- Credential staging dan production mesti berbeza serta disimpan di luar Git.
- Setiap client hanya menerima endpoint dan scope yang diperlukan.
- Audit mesti menunjukkan `authenticated` bagi client yang telah dimigrasikan.
- Endpoint legacy terus berada dalam mod compatibility sehingga semua pemilik
  sistem dikenal pasti dan ujian lengkap selesai.

## Syarat sebelum mempertimbangkan `enforce`

`Enforce` hanya boleh dicadangkan melalui perubahan berasingan selepas semua
syarat berikut dipenuhi:

- inventori lengkap semua downstream dan pemilik teknikal tersedia;
- setiap downstream telah mempunyai credential production unik;
- login, validasi token, refresh dan logout lulus di staging dan production;
- tiada `missing_credentials` yang tidak dikenal pasti sepanjang tempoh
  pemerhatian yang dipersetujui;
- prosedur rollback segera kepada `observe` telah diuji;
- maintenance window dan kelulusan pemilik operasi diperoleh.

Tanpa semua syarat tersebut, production mesti kekal dalam mod `observe`.

## Perubahan yang tidak dibuat

Kerja dokumentasi ini tidak:

- mencipta atau memutar secret;
- mendaftarkan client staging atau production;
- mengubah kod OneID atau downstream;
- mengubah `.private/runtime.php` atau environment variable;
- mengubah mod `observe` kepada `enforce`;
- mengubah WAF, Nginx, PHP-FPM, database atau servis;
- menjalankan deployment, reload atau restart.
