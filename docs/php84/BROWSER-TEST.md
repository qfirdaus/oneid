# Laluan browser PHP 8.4 — OneID UAT

Status: skrip dan preview telah disediakan; listener kekal belum dipasang. Ujian Nginx sementara tanpa root lulus untuk halaman web (200), CSS mobile (200), mobile/session tanpa token (401), discovery Hydra (200). Bukti: `phase4-http-smoke.json`. Proses sementara telah dihentikan. Discovery ialah Hydra, bukan bukti pelaksanaan PHP.

## Pasang pada server UAT

```sh
cd /var/www/oneid-uat
python3 -B tools/php84_browser_listener.py
sudo python3 -B tools/php84_browser_listener.py --apply
```

Skrip mencipta hanya `/etc/nginx/conf.d/oneid-php84-browser-test.conf`, listener TLS `127.0.0.1:24484`, menggunakan sijil UAT asal dan dua pool 8.4.26. Ia tidak menggantikan vhost/snippet asal. Ia menyemak konfigurasi dan reload Nginx untuk listener baharu; konfigurasi routing awam dan PHP 8.3 kekal. Kegagalan syntax/smoke memadam listener baharu dan reload semula. Backup/hash/smoke disimpan di `/var/backups/oneid-php84-browser-*`.

Ini pengasingan routing/runtime, BUKAN salinan database: kod, data UAT dan stor sesi masih dikongsi. Gunakan profil browser ujian baharu dan akaun ujian yang dibenarkan. Reset kata laluan/logout masih mempunyai kesan sebenar pada akaun UAT tersebut.

## Browser desktop melalui SSH

Pada komputer penguji, buka terminal dan kekalkan tunnel hidup (ganti IP_SERVER_UAT dengan IP server staging sebenar, bukan alamat production):

```sh
ssh -o ExitOnForwardFailure=yes -N -L 127.0.0.1:443:127.0.0.1:24484 iqs@IP_SERVER_UAT
```

Port 443 tempatan mesti kosong; sesetengah OS memerlukan keistimewaan pentadbir. Tambah sementara pada fail hosts KOMPUTER PENGUJI sahaja:

```text
127.0.0.1 oneid-uat.upnm.edu.my
```

Windows: `C:\Windows\System32\drivers\etc\hosts`; Linux/macOS: `/etc/hosts`. Jangan ubah hosts server, DNS bersama atau hostname MyDigital ID/downstream. Gunakan browser tanpa proxy yang mengatasi hosts setempat. Buka profil ujian baharu, kemudian `https://oneid-uat.upnm.edu.my/` (tanpa port). Dalam DevTools Network, respons PHP mesti mengandungi `X-OneID-Test-Runtime: php84-isolated`. Berhenti jika marker tiada; browser mungkin mencapai 8.3. Jangan abaikan amaran sijil.

Hostname/port asal dikekalkan supaya callback browser selepas MyDigital ID/SSO kembali melalui tunnel. Sijil TLS masih disahkan secara biasa.

## Apa yang boleh / belum boleh disahkan

- Login web/password/MyDigital ID dan callback browser, dashboard, renew sesi, logout dan reset password web boleh diuji melalui listener. Sahkan marker pada respons PHP utama/callback.
- Halaman login/consent/callback MyDigital ID mobile yang dilalui browser tunnel menggunakan 8.4.
- `/mobile-test/` masih harness sedia ada. Permintaan keluar harness untuk token, session dan logout menggunakan DNS server asal; ia TIDAK secara automatik bertukar kepada 8.4.
- Hydra sedia ada masih menghantar `/token-hook` ke route awam 8.3. Token endpoint sendiri bukan PHP.
- Sistem downstream masih membuat pengesahan back-channel ke route awam asal 8.3. Kejayaan launch dalam tunnel sahaja bukan bukti validator/API 8.4.
- Aplikasi Android/iOS pada telefon tidak menggunakan tunnel desktop secara automatik. Jangan laporkan ujian tersebut sebagai 8.4 tanpa laluan peranti yang disahkan.

Oleh itu keputusan ini ialah ujian web/front-channel terpencil. Ujian penuh mobile/back-channel memerlukan rehearsal provider/harness terpencil atau routing khusus penguji yang turut meliputi panggilan backend; jangan menukar semua `/token-hook` ke 8.4 sebagai jalan pintas.

Rekod masa, senario, runtime marker, berjaya/gagal dan aplikasi downstream. Jangan salin cookies, authorization code, token atau kata laluan ke laporan. Ujian authenticated/performance dan inventori remote masih gate sebelum cutover.

## Tamat ujian / rollback

Buang baris hosts pada komputer penguji, tutup profil browser ujian dan hentikan tunnel. Pada server:

```sh
sudo python3 -B tools/php84_browser_listener.py --remove
```

Arahan hanya membuang fail listener bertanda managed dan reload Nginx. Ia tidak mengubah data UAT atau membatalkan perubahan akaun yang dibuat semasa ujian.
