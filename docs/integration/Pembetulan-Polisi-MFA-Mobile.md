# Pembetulan polisi MFA login mobile

Login mobile ialah login pengguna. Peranan admin pada akaun staf tidak lagi memaksa MFA berasingan daripada polisi pengguna. Pengesahan panel admin dan faktor admin tidak diubah.

Perubahan:

- Runtime `ONEID_USER_MFA_MODE=OFF` mengatasi keperluan MFA mobile walaupun polisi database masih ENFORCED. Polisi database yang OFF juga tidak memerlukan MFA.
- ENFORCED memerlukan MFA bagi kategori yang diaktifkan, tertakluk kepada pengecualian pengguna sedia ada.
- PILOT_ENFORCED memerlukan MFA hanya bagi ahli pilot aktif dalam kategori yang diaktifkan.
- ENROLLMENT tidak memaksa MFA login.
- Selain override OFF yang diminta pemilik, runtime ceiling/authorization dan kegagalan membaca polisi kekal menolak operasi yang tidak sah; konfigurasi rosak bukan alasan untuk bypass MFA.
- Entry point membaca runtime MFA semasa pada setiap permintaan non-fixture. Snapshot konfigurasi pilot tidak lagi membekukan mode/activation authorization.

Polisi faktor pengguna dan pengecualian admin sedia ada tidak digabungkan. Pengecualian sementara pengguna terus mengikut pembaca polisi OneID asal. Tidak mengubah password, status akaun, MFA panel admin, atau nilai config/database aplikasi.

Perubahan polisi ketika transaksi sedang berjalan mungkin membatalkan transaksi/sesi kerana security stamp berubah. Mulakan login baharu selepas menukar config untuk menguji polisi baharu; jangan gunakan borang MFA lama.

Semakan baca sahaja pada masa pembetulan menunjukkan runtime dan polisi database kedua-duanya ENFORCED. Penguatkuasaan kategori tetap menentukan sama ada akaun perlu MFA. Catatan lama mengenai paksaan MFA konservatif untuk admin-staf dalam Fasa B/C telah digantikan oleh keputusan ini.
