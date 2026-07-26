# MyDigital ID Fasa 7 — Rejection UX, Account Switching dan Log Hardening

Tarikh: 26 Julai 2026  
Skop: staging `https://oneid-uat.upnm.edu.my/`  
Status aplikasi: implemented, automated verification required  
Status Nginx: arahan infra; belum dianggap applied sehingga diuji di staging

## Objektif

Fasa ini menutup jurang apabila identiti berjaya disahkan oleh MyDigital ID
tetapi tidak mempunyai akaun OneID aktif. OneID kekal fail-closed, tidak
auto-register dan tidak mengubah profil. Pengguna menerima mesej generik serta
boleh menamatkan sesi provider sebelum mencuba akaun MyDigital ID lain.

## Aliran aplikasi

1. Callback mengesahkan state, nonce, PKCE, token dan UserInfo seperti biasa.
2. Account matching merekod rejection tanpa menyimpan NRIC, nama atau token
   mentah dalam database.
3. ID token yang telah diverifikasi disimpan sementara dalam server-side PHP
   session sahaja, dengan TTL maksimum 300 saat.
4. Halaman login memaparkan mesej generik dan dua tindakan:
   `Cuba akaun MyDigital ID lain` atau `Guna ID Pengguna`.
5. Account-switch endpoint hanya menerima POST serta CSRF token yang sah.
6. State/nonce/PKCE dan token rejection dibuang secara one-use.
7. Browser diarahkan ke logout rasmi:
   `https://sso.digital-id.my/realms/upnm/protocol/openid-connect/logout`
   menggunakan `id_token_hint` dan registered `post_logout_redirect_uri`.
8. Jika state hilang, tamat atau tidak sah, endpoint fail-closed dan kembali
   ke OneID dengan mesej generik.

Raw ID token tidak ditulis ke database, log aplikasi atau output pengguna.
Provider logout tidak menggantikan local authorization gate.

## Nginx callback access-log hardening

Access log semasa merekod query callback termasuk authorization `code`,
`state` dan `session_state`. Team infra perlu menggunakan log format tanpa
`$request` dan `$args`.

Contoh dalam blok `http`:

```nginx
log_format oneid_no_query
    '$remote_addr - $remote_user [$time_local] '
    '"$request_method $uri $server_protocol" $status $body_bytes_sent '
    '"$http_referer" "$http_user_agent"';
```

Dalam server block `oneid-uat.upnm.edu.my`:

```nginx
access_log /var/log/nginx/oneid-uat.access.log oneid_no_query;
```

Pilihan ini membuang query string untuk keseluruhan virtual host dan
mengelakkan konfigurasi `location` PHP berganda. Team infra perlu menyesuaikan
format dengan metadata operasi yang diluluskan tanpa memasukkan `$request_uri`,
`$request` atau `$args`.

Apply terkawal:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Verification selepas satu authorization request:

```bash
sudo tail -n 50 /var/log/nginx/oneid-uat.access.log \
  | grep '/auth/mydigitalid/callback.php'
```

Baris callback mesti berakhir pada path `.php` dan tidak mengandungi `?`,
`code=`, `state=` atau `session_state=`.

Log lama sudah mengandungi authorization code. Team infra/security perlu
menilai retention, access restriction dan rotation menggunakan proses operasi
yang diluluskan. Jangan memadam log secara ad hoc.

## Staging acceptance

- pengguna OneID aktif masih boleh login;
- pengguna tidak layak menerima mesej generik dan tidak mendapat sesi OneID;
- `Cuba akaun MyDigital ID lain` logout daripada provider dan menghasilkan QR
  atau pilihan identiti baharu;
- token rejection tidak boleh diguna selepas 300 saat atau selepas satu
  account-switch;
- password login kekal berfungsi;
- `federated_auth_event` merekod rejection tanpa raw PII/token;
- callback access log tidak mempunyai query string; dan
- full security/regression suite lulus tanpa local mutation.

