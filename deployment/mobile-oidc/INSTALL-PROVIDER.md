# Provider dan migrasi OneID staging — observer OFF

Skop khusus staging `oneid-uat.upnm.edu.my`. Pemilik sudah mengesahkan database OneID berasingan daripada production. Tiada pengaktifan endpoint awam atau client Flutter dalam langkah ini.

## 1. Pasang provider melalui terminal pentadbir

```bash
cd /var/www/oneid-uat
python3 tools/mobile-oidc-hosted/install-provider-staging.py
sudo python3 tools/mobile-oidc-hosted/install-provider-staging.py --apply
```

Skrip memeriksa checksum artifak Hydra/PostgreSQL mengikut lock sedia ada, tiada target pemasangan terdahulu, port bebas dan routing dormant. Ia tidak memasang pakej global melalui apt atau menggunakan cluster fixture.

- Binary root-owned di `/opt/oneid-mobile-uat`; PostgreSQL 16.15 diekstrak daripada pakej yang dipin, Hydra v26.2.0. Pembaharuan keselamatan binary perlu dirancang sebagai operasi servis khusus; apt tidak mengurus salinan ini.
- Akaun OS berasingan `oneid-mobile-pg` dan `oneid-mobile-uat`, tanpa shell login.
- Cluster baharu di `/var/lib/oneid-mobile-pg/data`, hanya loopback port 24146. Role database provider bukan superuser, tidak boleh create database/role, dan menggunakan SCRAM. Tiada database aplikasi OneID dalam cluster ini.
- Provider public/admin hanya loopback 24144/24145, tanpa `--dev`. HTTPS issuer menggunakan hostname staging; Nginx terus memberi 404 bagi semua endpoint mobile.
- Secret rawak persisten di `/etc/oneid-mobile-uat/hydra.json`, root-owned dan hanya kumpulan servis boleh membaca. Jangan salin fail atau log pemasangan ke chat/Git. Hook key perlu dipadankan dengan adapter kemudian tanpa menjana semula system secret.
- Servis `oneid-mobile-postgres-uat` dan `oneid-mobile-hydra-uat` dimulakan dan di-enable selepas readiness lulus. Tiada client didaftarkan.

Skrip fresh-install sahaja. Jika gagal, ia cuba menghentikan kedua-dua servis khusus, mengekalkan data/config/log untuk pemeriksaan, dan tidak membuang database atau akaun. Ia tidak mengundurkan sistem dengan memadam data. Jangan ulang atau buang directory secara membuta tuli. Log pentadbir: `/etc/oneid-mobile-uat/installation.log` (mungkin mengandungi material sensitif). Servis web lama tidak direload oleh skrip ini.

## 2. Migrasi OneID selepas provider SUCCESS

Jalankan sebagai akaun `iqs`, tidak memerlukan sudo:

```bash
cd /var/www/oneid-uat
php tools/mobile-oidc-hosted/migrate-staging.php --check
php tools/mobile-oidc-hosted/migrate-staging.php --apply
```

Target dipin kepada database `oneiddb` pada server `mysql8-DEV`, environment staging, hosted OFF. Langkah apply memerlukan provider private ready. Skrip menyemak kolum sumber, menolak skema mobile sedia ada/partial, mengambil named migration lock dan mengehadkan metadata lock wait kepada 5 saat.

Skrip menambah empat jadual dan 28 trigger; observer kekal OFF. Ia menyalin ID pengguna ke jadual epoch baharu tetapi tidak mengubah rekod sumber pengguna/password/MFA. Sebelum DDL, metadata skema sumber/trigger dan pelan tepat disimpan dalam `.private/mobile-schema-*` berpermission persendirian. Ini backup metadata skema, **bukan full backup data OneID**. Progress setiap statement turut direkodkan. DDL MySQL auto-commit: kegagalan mungkin meninggalkan pemasangan separa; skrip tidak mendakwa rollback transaksi atau menggugurkan data automatik.

Walaupun observer OFF, trigger terpasang tetap membaca control guard pada penulisan sumber. Periksa latensi/error penulis web dan sync selepas migrasi. Sebelum observer ON kelak, reconcile/seed semula epoch bagi perubahan sepanjang tempoh OFF dan jalankan rehearsal locking serta regresi web. Jangan enable observer melalui dokumen ini.

## Ujian sebelum penyerahan

Preflight read-only provider dan database staging lulus. Rehearsal `tests/mobile-oidc-hosted/provider-install.py` berjaya pada cluster sintetik: penciptaan role/database baharu, SCRAM, migrasi Hydra, startup tanpa dev dengan HTTPS issuer, readiness dan tiada clients; proses sementara dihentikan. Ia bukan ujian pemasangan root/systemd sebenar. SQL lifecycle yang sama sudah diuji dalam rehearsal Fasa C; apply kepada database staging sebenar belum dijalankan pada masa dokumen ini ditulis.

Selepas dua langkah SUCCESS, semak status servis, observer=0, jumlah trigger dan endpoint awam kekal 404. Penyambungan adapter, SMTP/keyring, callback Flutter, operasi backup provider dan pengaktifan pilot ialah langkah berikutnya.
