# Halaman khas 404 OneID

## Tujuan

Halaman statik `public/errors/404.html` menggantikan badan respons lalai
`404 Not Found nginx` dengan paparan OneID yang kemas. Status HTTP mesti kekal
`404`; halaman ini bukan redirect dan bukan respons berjaya `200`.

Halaman sengaja statik dan dwibahasa supaya masih tersedia jika PHP atau
aplikasi menghadapi masalah. Ia tidak memaparkan URL yang diminta dan tidak
menggunakan JavaScript atau aset luaran.

## Konfigurasi Nginx staging

Dalam blok HTTPS bagi `server_name oneid-uat.upnm.edu.my`, tambah arahan berikut
berhampiran tetapan log:

```nginx
error_page 404 /errors/404.html;

location = /errors/404.html {
    internal;
}
```

`internal` menghalang fail handler daripada digunakan sebagai halaman biasa.
Request terus ke `/errors/404.html` akan menerima `404`.

Arahan `error_page` hendaklah diletakkan pada aras `server`, bukan di dalam
blok `location`. Blok `location = /errors/404.html` hendaklah berada sebelum
blok PHP generik.

## Apply dan pengesahan

```bash
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl is-active nginx
```

Gunakan URL canary yang pasti tidak wujud:

```bash
CANARY_PATH="/oneid-404-test-$(date +%s)"

curl -sS -D /tmp/oneid-404.headers \
  -o /tmp/oneid-404.body \
  "https://oneid-uat.upnm.edu.my${CANARY_PATH}"

head -n 1 /tmp/oneid-404.headers
grep -F "Halaman tidak ditemui" /tmp/oneid-404.body
grep -F "The address may be incorrect" /tmp/oneid-404.body
```

Expected:

- status ialah `HTTP/2 404`;
- kandungan halaman khas tersedia;
- tiada teks badan lalai `404 Not Found nginx`;
- halaman utama dan aliran login kekal berfungsi.

Padam fail ujian tempatan selepas semakan:

```bash
rm /tmp/oneid-404.headers /tmp/oneid-404.body
```

## Rollback

Buang dua konfigurasi `error_page`/`location` di atas, kemudian:

```bash
sudo nginx -t
sudo systemctl reload nginx
```
