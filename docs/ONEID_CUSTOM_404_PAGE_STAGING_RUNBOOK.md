# Halaman ralat selamat OneID

## Tujuan

Halaman statik dalam `public/errors/` menggantikan badan respons lalai Nginx
dengan paparan OneID yang generik dan dwibahasa:

- `404.html`: permintaan tidak sah, dilarang atau tidak ditemui;
- `429.html`: permintaan terlalu kerap;
- `50x.html`: gangguan perkhidmatan atau upstream.

Nombor kod tidak dipaparkan kepada pengguna, tetapi status HTTP sebenar mesti
dikekalkan untuk pemantauan, caching dan tingkah laku client yang betul. Halaman
tidak memaparkan URL, exception, stack trace, path fail atau nama upstream.

## Konfigurasi Nginx

Dalam blok HTTPS OneID, tambah:

```nginx
error_page 400 401 403 404 405 408 413 414 /errors/404.html;
error_page 429 /errors/429.html;
error_page 500 502 503 504 /errors/50x.html;

location = /errors/404.html { internal; }
location = /errors/429.html { internal; }
location = /errors/50x.html { internal; }
```

Kekalkan arahan berikut dalam PHP location:

```nginx
fastcgi_intercept_errors off;
```

Ini memastikan endpoint API/SSO terus mengeluarkan JSON dan status asalnya.
Halaman HTML ini hanya menggantikan ralat yang dijana oleh Nginx. Jangan hidupkan
interception FastCGI secara menyeluruh.

## Urutan staging

1. Salin source halaman ralat ke UAT.
2. Tambah pemetaan di atas pada virtual host HTTPS UAT.
3. Jalankan `sudo nginx -t`.
4. Hanya jika ujian sintaks lulus, jalankan `sudo systemctl reload nginx`.
5. Sahkan halaman utama, URL tidak wujud dan endpoint API.

Contoh semakan selamat:

```bash
CANARY_PATH="/oneid-error-test-$(date +%s).php"
curl -sS -D /tmp/oneid-error.headers -o /tmp/oneid-error.body \
  "https://oneid-uat.upnm.edu.my${CANARY_PATH}"
head -n 1 /tmp/oneid-error.headers
grep -F "Halaman ini tidak tersedia" /tmp/oneid-error.body
curl -sS -o /dev/null -w '%{http_code}\n' https://oneid-uat.upnm.edu.my/
```

Expected: canary kekal HTTP 404 dengan paparan OneID, dan halaman utama kekal
HTTP 200. Uji endpoint API menggunakan payload ujian sedia ada dan pastikan
`Content-Type` kekal JSON.

## Batasan WAF

Ralat yang ditolak oleh WAF sebelum sampai ke origin tidak boleh disesuaikan oleh
Nginx OneID. Pemetaan halaman generik pada WAF perlu dibuat oleh pemilik WAF dalam
perubahan berasingan selepas semakan pusat data.

## Rollback

Pulihkan backup virtual host, jalankan `sudo nginx -t`, kemudian graceful reload.
Rollback konfigurasi tidak memerlukan perubahan aplikasi, database atau PHP-FPM.
