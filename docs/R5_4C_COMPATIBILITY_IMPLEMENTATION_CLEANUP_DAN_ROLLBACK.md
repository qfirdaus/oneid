# R5.4C — Compatibility Implementation Cleanup dan Rollback

Tarikh: 14 Julai 2026  
Change ID: `R5-4C-20260714-104304`  
Status: `PASS`

## Objektif

R5.4C mengurangkan implementation berganda di belakang public wrapper tanpa mengubah URL awam. Slice pertama dihadkan kepada logout kerana `admin/logout.php` dan `page/logout.php` byte-identical serta behavior telah diextract ke `app/Auth/LogoutHandler.php` dalam R5.2B1.

## Caller map dan sempadan

Semua 12 wrapper dipetakan dalam `docs/R5_4C_COMPATIBILITY_IMPLEMENTATION_MAP.tsv`.

Fasa ini tidak memindahkan:

- login/reset/OTP entry point;
- admin atau user dashboard;
- `admin/user_list.php`;
- API, IDMS atau SKP;
- `lib/q_func.php`;
- external SSO compatibility endpoints.

## Baseline logout

Kedua-dua implementation mempunyai checksum sama:

```text
3bd15f46fc360b3dd5fe759e2adf26f51d6607ef9296a76e150fbb4e708949a9
```

URL yang mesti kekal:

```text
/admin/logout.php
/page/logout.php
```

## Perubahan

1. `app/Auth/LogoutEndpoint.php` menjadi satu-satunya bootstrap endpoint logout.
2. Kedua-dua public wrapper memanggil endpoint bersama itu.
3. Endpoint mengekalkan urutan session bootstrap, config/database, SSO compatibility dan `LogoutHandler` legacy.
4. `admin/logout.php` dan `page/logout.php` dipindahkan ke quarantine, bukan dipadam kekal.
5. Wrapper characterization dikemas kini kepada shared target.
6. `tools/r54_compatibility_contract.php` mengunci shared wiring serta memastikan 10 wrapper lain tidak berubah.

## Quarantine

Payload rollback:

```text
storage/quarantine/R5-4C-20260714-104304/payload/admin/logout.php
storage/quarantine/R5-4C-20260714-104304/payload/page/logout.php
```

## Verification

Keputusan automatik selepas implementation:

| Semakan | Keputusan |
| --- | --- |
| PHP lint endpoint dan dua wrapper | PASS |
| R5.4C compatibility contract | 24/24 PASS |
| R5.4B asset regression | 27/27 PASS |
| R5.4A structure regression | 31/31 PASS |
| R5.2 dashboard regression | 21/21 PASS |
| Full characterization `oneid.local` | 69/69 PASS |
| Full characterization `oneid-next.local` | 69/69 PASS |
| Anonymous `/admin/logout.php` dan `/page/logout.php` | 302 ke landing page pada kedua-dua hostname |
| Authenticated admin logout `oneid.local` | 14/14 PASS |
| Authenticated admin logout `oneid-next.local` | 14/14 PASS |

Characterization berubah daripada 70 kepada 69 kerana kedua-dua wrapper kini
berkongsi target `app/Auth/LogoutEndpoint.php` dan target PHP yang sama dilint
sekali sahaja. Kedua-dua URL awam masih diuji secara berasingan; ini bukan
pengurangan endpoint coverage.

```bash
php tools/r54_compatibility_contract.php
php tools/r52_characterization.php https://oneid.local --insecure
php tools/r52_characterization.php https://oneid-next.local
php tools/r52_authenticated_logout.php https://oneid.local admin --insecure
```

Authenticated test memerlukan credential admin melalui environment seperti R5.2B0. Static/anonymous tests tidak membuktikan token/cookie invalidation bagi authenticated session.

Closure gate:

```bash
cd /var/www/app/oneid-uat

read -rp "Test admin: " ONEID_R52_ADMIN_USERNAME
read -rsp "Admin password: " ONEID_R52_ADMIN_PASSWORD; echo
export ONEID_R52_ADMIN_USERNAME ONEID_R52_ADMIN_PASSWORD

php tools/r52_authenticated_logout.php https://oneid.local admin --insecure
php tools/r52_authenticated_logout.php https://oneid-next.local admin

unset ONEID_R52_ADMIN_USERNAME ONEID_R52_ADMIN_PASSWORD
```

Expected result ialah `checks=14 failed=0` bagi setiap hostname. R5.4C hanya
boleh ditutup sebagai PASS selepas kedua-dua run memenuhi keputusan itu.

Closure gate telah dijalankan oleh owner pada 14 Julai 2026. Kedua-dua hostname
mendapat `checks=14 failed=0`, termasuk session ID rotation, SSO cookie issue dan
clear, role authorization, logout redirect, logged-out cookie rejection serta
pre-logout session replay rejection. R5.4C ditutup sebagai PASS.

## Rollback

Selepas commit:

```bash
git revert <R5.4C-commit-hash>
```

Emergency physical restore sebelum commit:

```bash
cd /var/www/app/oneid-uat
mv storage/quarantine/R5-4C-20260714-104304/payload/admin/logout.php admin/logout.php
mv storage/quarantine/R5-4C-20260714-104304/payload/page/logout.php page/logout.php
```

Kemudian restore dua public wrapper dan characterization contract daripada Git.
