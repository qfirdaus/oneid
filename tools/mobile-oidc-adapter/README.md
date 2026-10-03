# Fasa B — adapter identiti mobile OneID (dormant UAT)

Kod domain berada dalam `app/Auth/MobileOidc/`. Tiada HTTP route, Nginx,
bootstrap login, `.env`, service kekal atau database aplikasi diubah oleh
penambahan fail ini. Factory memerlukan pengaktifan eksplisit dan environment
`staging`; tiada pemanggil factory ditambah pada sistem semasa.

## Ulang ujian

```bash
cd /var/www/oneid-uat
php tests/mobile-oidc/adapter.php
PYTHONDONTWRITEBYTECODE=1 python3 tools/mobile-oidc-poc/prepare.py --uat-only
PYTHONDONTWRITEBYTECODE=1 python3 tools/mobile-oidc-adapter/provider-test.py --uat-only
PYTHONDONTWRITEBYTECODE=1 python3 tools/mobile-oidc-adapter/sql-test.py --uat-only
```

Provider rehearsal menggunakan Hydra/PostgreSQL yang dipin dalam Fasa A.
PHP adapter benar-benar menerima login challenge daripada Hydra, mengesahkan
password dan faktor fixture, menerima login melalui private Admin API,
kemudian runner melakukan consent, PKCE exchange dan menyemak subject/AMR.
Consent dan token hook runner masih fixture Fasa A; ia belum handler operasi.

SQL rehearsal memuat turun versi/checksum dalam `mysql-lock.json` melalui APT,
mengekstrak MySQL serta tiga dependency ke `.private/mobile-oidc-poc/mysql/`,
dan membuat datadir baharu setiap kali. Tiada pakej sistem di-install.
MySQL hanya menggunakan UNIX socket dalam direktori private mod 0700, tanpa
TCP/MySQLX. Root fixture tanpa password hanya digunakan untuk datadir ujian
tersebut. Jangan gunakan konfigurasi ini untuk database aplikasi atau deployment.

Semua runner menutup proses sementara selepas selesai. Jika SIGKILL/power loss,
semak proses tertinggal sebelum membersihkan direktori. Log, kunci dan datadir
private tidak boleh di-commit. Bukti sanitised berada di
`docs/integration/bukti-fasa-b/`. `accept-fixture.php` ialah CLI sahaja dan
menggunakan credential rekaan, bukan endpoint login.

## Komposisi untuk Fasa C

`bootstrap.php` hanya memuat library. Ia tidak membaca `lib/config.php`.
`UatAdapterFactory::create()` memerlukan:

- PDO MySQL **UAT yang telah disahkan pengasingannya**, DSN `charset=utf8mb4`.
  Factory memastikan source dan state store berkongsi transaksi/PDO.
- `enabled=true`, `environment=staging`, MFA runtime mode dan activation state
  yang disahkan daripada konfigurasi OneID; semua ini konfigurasi server sahaja.
- Admin URL private, issuer HTTPS, allowlist client ID → redirect URI tepat.
- `binding_key` rawak sekurang-kurangnya 32 byte, stabil merentas restart,
  disimpan dalam secret store; jangan guna kunci contoh.
- `UserMfaTotpPrimitive` dengan keyring OneID yang dikawal dan
  `OneIdOtpDelivery` yang membalut `UserMfaEmailSenderInterface` sebenar.
- Callback readiness yang menyemak maintenance, feature state dan dependency;
  kegagalan tidak boleh dianggap tersedia.

Schema additive belum dipasang pada database aplikasi. Semak
`docs/migrations/mobile-oidc-state.sql` sebelum Fasa C. Mutex global
ialah implementasi konservatif untuk adapter dormant; ukur contention dan
deadlock/retry sebelum memberi trafik sebenar. Password verification memegang
lock transaksi, jadi ujian beban diperlukan sebelum operasi.

`begin()` hanya menerima challenge yang diperoleh daripada private Hydra API.
Controller hosted login nanti mesti menjana browser secret berentropi tinggi,
cookie mobile khusus, validasi CSRF/Origin dan trusted client IP. Jangan ambil
browser secret, identity, `amr`, admin URL atau konfigurasi daripada body Flutter.
Domain service tidak menggantikan perlindungan HTTP itu.

`password()` → `sendEmail()/verify()` jika perlu → `complete()` ialah laluan
login. `complete()` mengembalikan redirect provider hanya selepas sesi adapter
aktif. Hasil penerimaan provider yang tidak pasti tidak dicuba semula;
mulakan authorization baharu. Provider `skip=true` tidak melangkau pengesahan.

`session(sid, client, sub)` ialah fungsi **dalaman** bagi consent/token hook,
bukan endpoint public. Consent handler mesti menyemak client/challenge/context,
memanggil fungsi ini dan hanya menerbitkan claim yang dibenarkan. Hook mesti
mengautentikasi provider dan mengikat sid/client/subject daripada sesi provider.
Public session endpoint nanti mesti mengesahkan access token, issuer, audience
dan scopes sebelum memanggil fungsi ini. Jangan menerima sid sahaja sebagai
bukti pengguna telah login.

`invalidateAccount()` / `revokeSession()` ialah primitif lifecycle server,
bukan API yang boleh dipanggil dengan user ID daripada pengguna biasa.
Wiring writer/outbox dan invalidation atomik masih kerja sebelum pengaktifan.
`invalidateAccount()` membuka transaksi sendiri; jangan panggil secara nested
dalam transaksi writer sedia ada. Fasa C perlu menyatukan update version ke
transaksi writer atau menyediakan mekanisme fail-closed yang dibuktikan.

Laporan lengkap dan batas: [Hasil Fasa B](../../docs/integration/Hasil-Fasa-B-OneID-Mobile-OIDC.md).
