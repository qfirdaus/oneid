# Runbook Aktivasi R3 — `oneid-next.local`

**Tarikh:** 14 Julai 2026  
**Tujuan:** Mengaktifkan public-root secara selari tanpa menukar `oneid.local`.  
**Document root baharu:** `/var/www/app/oneid-uat/public`

## 1. Keadaan Semasa

Aktivasi tidak dibuat secara automatik kerana perubahan berikut memerlukan pentadbir server:

- `/etc/nginx` dimiliki `root` dan deployment user tidak mempunyai `sudo` tanpa interaksi;
- `oneid-next.local` belum wujud dalam DNS atau `/etc/hosts`;
- sijil `/home/iqs/ssl/local-dev.pem` tidak mempunyai SAN `DNS:oneid-next.local`;
- menjalankan `nginx -t` sebagai user biasa gagal ketika membuka `/run/nginx.pid`, walaupun syntax parsing berjaya.

Login yang telah diuji pada `oneid.local` mengesahkan root lama masih stabil. Ia belum mengesahkan login melalui `/var/www/app/oneid-uat/public`.

## 2. Perubahan Yang Dibenarkan Dalam R3

R3 hanya menambah hos selari:

| Hos | Document root | Status semasa R3 |
|---|---|---|
| `oneid.local` | `/var/www/app/oneid-uat` | Kekal, jangan ubah |
| `oneid-next.local` | `/var/www/app/oneid-uat/public` | Akan ditambah |

Jangan menukar `root` bagi `oneid.local` dalam langkah ini. Perubahan tersebut ialah R4 dan hanya boleh dibuat selepas semua gate R3 lulus.

## 3. Prasyarat Pentadbir

1. Sediakan rekod DNS UAT `oneid-next.local` ke server yang sama. Untuk ujian workstation tempatan sahaja, pemetaan `/etc/hosts` boleh digunakan.
2. Sediakan certificate dan private key berikut:

   - `/etc/nginx/ssl/oneid-next.local.crt`;
   - `/etc/nginx/ssl/oneid-next.local.key`.

3. Certificate mesti mempunyai `DNS:oneid-next.local` dalam Subject Alternative Name.
4. Pastikan PHP-FPM socket yang digunakan ialah `/run/php/php8.3-fpm.sock`.

Semak certificate sebelum aktivasi:

```bash
sudo openssl x509 \
  -in /etc/nginx/ssl/oneid-next.local.crt \
  -noout -subject -issuer -dates -ext subjectAltName
```

Private key hendaklah dihadkan kepada root:

```bash
sudo chown root:root /etc/nginx/ssl/oneid-next.local.key
sudo chmod 600 /etc/nginx/ssl/oneid-next.local.key
```

## 4. Aktivasi Nginx

Arahan ini mesti dijalankan oleh pentadbir server:

```bash
cd /var/www/app/oneid-uat

sudo install -o root -g root -m 0644 \
  docs/nginx/oneid-next.local.conf \
  /etc/nginx/conf.d/oneid-next.local.conf

sudo nginx -t
sudo systemctl reload nginx
```

Jika server tidak memuatkan `/etc/nginx/conf.d/*.conf`, pasang fail ke mekanisme `sites-available`/`sites-enabled` yang digunakan oleh server tersebut. Jangan duplicate server block di kedua-dua lokasi.

## 5. Smoke Test Selepas Aktivasi

Jalankan dari project root:

```bash
php tools/restructure_smoke.php https://oneid-next.local
```

Jika menggunakan local CA yang belum dipercayai pada workstation, `--insecure` boleh digunakan untuk UAT sahaja:

```bash
php tools/restructure_smoke.php https://oneid-next.local --insecure
```

Kemudian sahkan boundary berikut menghasilkan HTTP 404:

```bash
for path in \
  README.md package.json sso_db.sql test.php atest.php \
  lib/config.php lib/secrets.php cron/run_sync.php \
  docs/AUDIT_PROJEK_ONEID_UAT_2026-07-13.md
do
  curl --silent --output /dev/null --write-out "%{http_code}  /$path\n" \
    "https://oneid-next.local/$path"
done
```

## 6. Business UAT Wajib Pada Hos Baharu

Gunakan browser/private session dan pastikan address bar menunjukkan `https://oneid-next.local` sepanjang ujian.

- [ ] Login user berjaya dan redirect ke dashboard user.
- [ ] Logout user memadam session dan kembali ke login.
- [ ] Login admin berjaya dan redirect ke dashboard admin.
- [ ] Logout admin berjaya.
- [ ] Forgot password dan OTP mailbox UAT berjaya.
- [ ] Add/edit application serta upload icon berjaya.
- [ ] Semua icon aplikasi dipaparkan tanpa 404.
- [ ] AJAX user/admin yang aktif berjaya.
- [ ] API, IDMS dan SKP mengekalkan response contract.
- [ ] Sekurang-kurangnya satu SSO pilot consumer berjaya tanpa menukar consumer lain.
- [ ] Access log dan error log tidak menunjukkan regression baharu.

Semak log semasa UAT:

```bash
sudo tail -F \
  /var/log/nginx/oneid-next.access.log \
  /var/log/nginx/oneid-next.error.log
```

Jangan uji cron/sync atau rotation credential di luar change window kerana ia boleh mengubah data atau memberi kesan kepada consumer.

## 7. Keputusan Gate

R3 hanya boleh ditanda lengkap selepas:

- Nginx config aktif dan `nginx -t` lulus;
- smoke test route lulus;
- public boundary kekal tertutup;
- login user dan admin diuji pada `oneid-next.local`, bukan `oneid.local`;
- fungsi upload/OTP/API/SSO yang berkaitan lulus;
- tiada error baharu yang material dalam log.

Jika mana-mana pemeriksaan gagal, kekalkan `oneid.local` pada root lama dan jangan mulakan R4.

## 8. Rollback R3

Hos selari boleh dibuang tanpa menjejaskan `oneid.local`:

```bash
sudo rm /etc/nginx/conf.d/oneid-next.local.conf
sudo nginx -t
sudo systemctl reload nginx
```

Selepas reload:

1. pastikan `https://oneid.local` masih boleh dicapai;
2. jalankan smoke test root lama;
3. arkibkan error/access log R3 untuk diagnosis;
4. jangan buang `public/`, `bootstrap/` atau `storage/` sehingga punca kegagalan dikenal pasti.

Certificate dan DNS tidak perlu dibuang serta-merta jika masih diperlukan untuk pembetulan dan ujian semula.

## 9. Bukti Yang Perlu Direkodkan

Rekodkan perkara berikut dalam dokumen pelaksanaan selepas pentadbir mengaktifkan hos:

- tarikh/masa dan nama pelaksana;
- output `sudo nginx -t`;
- SAN dan tarikh luput certificate tanpa merekod private key;
- keputusan smoke test;
- keputusan setiap business UAT;
- ringkasan error log;
- keputusan akhir: teruskan R4, baiki dan uji semula, atau rollback R3.

## 10. Rekod Ujian 14 Julai 2026

Selepas pemilik sistem mengaktifkan `https://oneid-next.local`:

- DNS/hosts resolve ke `127.0.0.1`;
- server block aktif ditempatkan dalam `/etc/nginx/sites-available/local-projects`;
- certificate mkcert mempunyai SAN `DNS:oneid-next.local` dan sah sehingga 13 Oktober 2028;
- smoke test route: **10 lulus, 0 gagal**;
- pemilik sistem mengesahkan login dan navigasi halaman berjaya;
- hardening explicit sensitive-path telah dipasang dan aplikasi masih boleh dicapai;
- boundary test akhir: **13 sensitive path menghasilkan `404`**;
- URL extensionless `/page/dashboard` dan `/admin/dashboard` menghasilkan redirect authentication `302` seperti dijangka;
- URL extensionless `/lib/q_func` menghasilkan `405` seperti dijangka bagi request GET ke endpoint POST-only.

Pengesahan business UAT oleh pemilik sistem pada hari yang sama:

- login admin berjaya;
- OTP dan reset password berjaya;
- upload icon berjaya;
- API, IDMS dan SKP menggunakan live credential berjaya;
- sekurang-kurangnya satu SSO consumer berjaya.

Pengesahan filesystem menunjukkan icon baharu `app_icon_270e8a10910cea38335c0a1cc42f174f.png` ditulis pada 14 Julai 2026, 01:15:42 ke transitional target `/var/www/app/oneid-uat/public_img`. Path `public/public_img` resolve ke target tersebut seperti direka untuk dual-root.

Access/error log turut diperhatikan. Tiada entri `emerg`, `alert` atau `crit`; 16 entri berlabel error ialah structured integration audit `missing_credentials` daripada smoke request tanpa credential dan bukan kegagalan live credential yang diuji oleh pemilik sistem.

Keputusan ini menutup gate teknikal Nginx dan business UAT utama R3. Gate operasi/governance yang belum selesai direkodkan dalam dokumen R0–R3. Jangan salin template penuh ke `conf.d` kerana server block aktif sudah berada dalam `sites-available/local-projects`.
