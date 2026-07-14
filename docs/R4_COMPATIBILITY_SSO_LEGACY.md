# R4 Compatibility — Endpoint SSO Legacy

**Tarikh:** 14 Julai 2026  
**Status:** Dilaksanakan untuk public-root transition  
**Owner keputusan:** Pemilik sistem/penyelaras change R4  
**Tujuan:** Elakkan outage consumer yang mungkin masih memanggil URL legacy ketika `oneid.local` bertukar ke `public/`.

## 1. Endpoint Dikekalkan

| URL | Wrapper public | Implementasi asal |
|---|---|---|
| `/lib/sso_IDP_index.php` | `public/lib/sso_IDP_index.php` | `lib/sso_IDP_index.php` |
| `/lib/sso_IDP_sub.php` | `public/lib/sso_IDP_sub.php` | `lib/sso_IDP_sub.php` |

Wrapper tidak menyalin business logic. Ia merekod audit minimum dan memuatkan implementasi asal di luar document root.

## 2. Perubahan Path

Kedua-dua implementasi asal ditukar daripada:

```php
require_once 'config.php';
```

kepada:

```php
require_once __DIR__ . '/config.php';
```

Ini diperlukan supaya config resolution tidak bergantung pada working directory wrapper/PHP-FPM. Tiada response schema, query parameter atau redirect contract ditukar.

## 3. Audit Compatibility

Setiap request wrapper merekodkan structured event:

```json
{
  "component": "oneid_integration",
  "event": "legacy_compat_route",
  "request_id": "generated-per-request",
  "ip": "caller-ip",
  "endpoint": "sso_IDP_index-or-sub",
  "method": "GET-or-POST"
}
```

Audit tidak merekod token, cookie, query string atau request body. Log ini digunakan untuk mengenal pasti penggunaan sebenar sebelum Fasa 6B menamatkan compatibility route.

## 4. Keputusan Ujian

| Host/route tanpa cookie | Expected | Keputusan |
|---|---:|---|
| `oneid.local/lib/sso_IDP_index.php` | 200 | Lulus |
| `oneid.local/lib/sso_IDP_sub.php` | 302 ke `https://oneid.local/` | Lulus |
| `oneid-next.local/lib/sso_IDP_index.php` | 200 | Lulus |
| `oneid-next.local/lib/sso_IDP_sub.php` | 302 ke `https://oneid-next.local/` | Lulus |

Tambahan:

- empat fail PHP berkaitan lulus `php -l`;
- smoke `oneid-next.local` kekal 10/10;
- sensitive paths yang diuji kekal 404;
- structured compatibility event muncul dalam Nginx/PHP error log seperti direka.

Ujian tanpa token tidak menggantikan end-to-end consumer UAT. Sekurang-kurangnya satu SSO consumer sebenar telah disahkan oleh pemilik sistem dalam R3.

## 5. Risiko Diketahui

Compatibility wrapper mengekalkan implementasi legacy, termasuk technical debt sedia ada seperti aliran cookie lama dan cURL TLS verification yang dilumpuhkan dalam dua implementasi tersebut. Wrapper tidak menambah bypass baharu tetapi memanjangkan tempoh exposure asal.

Oleh itu:

- route kekal sementara sahaja;
- audit event mesti dipantau;
- jangan anggap R4 sebagai penyelesaian Fasa 6B;
- setiap consumer perlu dimigrasikan ke flow yang diluluskan;
- tarikh sunset hanya ditetapkan selepas registry consumer dan evidence 30–90 hari tersedia.

## 6. Rollback

Jika wrapper menyebabkan regression sebelum R4, buang kedua-dua wrapper public dan public-root kembali memberi 404; `oneid.local` legacy root kekal menggunakan implementasi asal.

Selepas R4, jangan buang wrapper sebagai rollback spontan kerana consumer mungkin bergantung padanya. Rollback R4 hendaklah memulihkan Nginx root lama mengikut runbook. Penamatan wrapper selepas R4 ialah change Fasa 6B berasingan dengan evidence consumer.

Path normalization `__DIR__` boleh dikekalkan walaupun R4 di-rollback kerana ia resolve ke fail yang sama dan mengurangkan kebergantungan pada current working directory.

## 7. Exit Criteria Compatibility

- [ ] Semua consumer mempunyai owner dan endpoint registry.
- [ ] Minimum 30 hari log; 90 hari bagi vendor/batch berkala.
- [ ] Tiada caller sah atau semua caller telah dimigrasikan.
- [ ] Owner consumer dan rollback contact mengesahkan sunset.
- [ ] Wrapper di-quarantine sebelum permanent deletion.

## 8. Checksum Selepas Implementasi

| Fail | SHA-256 |
|---|---|
| `lib/sso_IDP_index.php` | `124ab97a11af57bcdb54e021bcce127d16f83140782e7b89e1c08ba40497a199` |
| `lib/sso_IDP_sub.php` | `9f249bbba4b4a522470471c84c1738616af41eb2a1b3938df2f60fa0fc29bad6` |
| `public/lib/sso_IDP_index.php` | `f349fd1cb61b4336b9cc35e207cb8fbf03d35d54957cdaa5a6fb4bc5adc826bd` |
| `public/lib/sso_IDP_sub.php` | `fc8dc63248143ab2de775341b41a5b642e04cb56a6765f44c97f68d2d42c2a16` |
