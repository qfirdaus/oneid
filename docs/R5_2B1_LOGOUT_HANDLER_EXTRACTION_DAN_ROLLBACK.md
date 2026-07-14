# R5.2B1 — Logout Handler Extraction dan Rollback

Tarikh: 14 Julai 2026

Change ID: `R5-2B1-20260714-033028`

Owner perubahan: Pemilik sistem OneID

Owner rollback: Pemilik sistem OneID

Status: **SELESAI — POST-CHANGE AUTHENTICATED TEST LULUS**

## 1. Tujuan dan Skop

R5.2B1 ialah application-layer extraction pertama. Logik yang sama daripada
`page/logout.php` dan `admin/logout.php` dipusatkan dalam:

```text
app/Auth/LogoutHandler.php
```

Entry point dan URL berikut kekal:

- `/page/logout.php`;
- `/admin/logout.php`;
- public wrapper masing-masing;
- redirect ke root `SSO_IDP_DOMAIN`.

Tiada perubahan dibuat pada login, dashboard, `q_func`, cookie policy, token
format, database schema, Nginx atau public-root.

## 2. Acceptance R5.2B0

Sebelum extraction:

- admin lulus 14/14 pada `oneid.local`;
- admin lulus 14/14 pada `oneid-next.local`;
- `page/logout.php` dan `admin/logout.php` kedua-duanya diuji;
- SSO cookie clearing dan server-side session replay rejection lulus;
- normal-user run menggunakan akaun admin dan tidak dapat membuktikan role `403`;
- owner memaklumkan tiada akaun user biasa dan mengarahkan continuation;
- arahan tersebut direkodkan sebagai acceptance pengecualian normal-user.

Risiko baki diterima: logout dengan akaun user biasa tidak diuji secara langsung.
Mitigasi ialah kedua-dua source logout asal mempunyai checksum sama, tiada branch
role dalam handler, dan endpoint page logout telah diuji dengan authenticated
session.

## 3. Before dan After

| Komponen | Sebelum | Selepas |
|---|---|---|
| `page/logout.php` | 16 baris, mengandungi semua logik | 8 baris, compatibility entry point |
| `admin/logout.php` | 16 baris, duplikasi tepat | 8 baris, compatibility entry point |
| Shared handler | Tiada | `app/Auth/LogoutHandler.php` |
| Public wrapper | Sedia ada | Tidak berubah |
| URL/redirect | Root host semasa | Tidak berubah |

Handler bersama mengekalkan urutan asal:

1. jika `sso_cre` wujud, token database dinyahaktifkan;
2. SSO cookie dibuang;
3. `$_SESSION` dikosongkan;
4. server-side session dimusnahkan;
5. response redirect ke `SSO_IDP_DOMAIN`.

## 4. Fail Berubah

- baharu: `app/Auth/LogoutHandler.php`;
- dikemas kini: `page/logout.php`;
- dikemas kini: `admin/logout.php`;
- dikemas kini: `app/README.md`;
- dikemas kini: caller map dan dokumen R5.2.

Public wrapper berikut disahkan tidak berubah:

- `public/page/logout.php`;
- `public/admin/logout.php`.

## 5. Validation Selepas Extraction

| Ujian | Keputusan |
|---|---|
| PHP lint handler | PASS |
| PHP lint page entry point | PASS |
| PHP lint admin entry point | PASS |
| R5.2 characterization `oneid.local` | 70/70 PASS |
| R5.2 characterization `oneid-next.local` | 70/70 PASS |
| Anonymous page logout redirect | `302` tepat ke host root |
| Anonymous admin logout redirect | `302` tepat ke host root |
| Public-root symlink | 0 |
| Authenticated admin logout `oneid.local` | 14/14 PASS |
| Authenticated admin logout `oneid-next.local` | 14/14 PASS |
| Access log | Tiada 5xx dalam test window |
| Error log | Tiada PHP fatal/warning atau `LogoutHandler` error |

Static/anonymous checks membuktikan include path, syntax dan redirect contract.
Authenticated post-change checks pada 03:34 +0800 membuktikan session rotation,
token/cookie clearing, redirect dan penolakan replay session pada kedua-dua
hostname.

## 6. Post-change Command

Hanya admin matrix perlu diulang kerana normal-user exception telah diterima:

```bash
read -rp "Test admin: " ONEID_R52_ADMIN_USERNAME
read -rsp "Admin password: " ONEID_R52_ADMIN_PASSWORD; echo
export ONEID_R52_ADMIN_USERNAME ONEID_R52_ADMIN_PASSWORD

php tools/r52_authenticated_logout.php https://oneid.local admin --insecure
php tools/r52_authenticated_logout.php https://oneid-next.local admin

unset ONEID_R52_ADMIN_USERNAME ONEID_R52_ADMIN_PASSWORD
```

Expected bagi setiap host:

```text
RESULT checks=14 failed=0
```

## 7. Rollback

Trigger rollback:

- login berjaya tetapi logout menghasilkan 5xx;
- SSO cookie tidak dibuang;
- token/session lama masih boleh digunakan;
- redirect tidak menuju root host semasa;
- PHP fatal/include error daripada handler baharu.

Langkah rollback:

1. pulihkan `page/logout.php` dan `admin/logout.php` kepada kandungan sebelum
   R5.2B1 menggunakan revision/change artifact;
2. kedua-dua fail asal mesti kembali kepada SHA-256
   `32c32afa0426b643cde72e06cf2d324a728a2b5a11e5e90fbb4e897b625cc117`;
3. buang `app/Auth/LogoutHandler.php` hanya selepas tiada caller;
4. lint kedua-dua entry point;
5. jalankan R5.2 characterization pada dua hostname;
6. ulang authenticated admin logout matrix.

Rollback batch ini tidak mengubah public wrapper, Nginx public-root atau
hardening Fasa 1–6.

## 8. Checksum

### Sebelum

| Fail | SHA-256 |
|---|---|
| `page/logout.php` | `32c32afa0426b643cde72e06cf2d324a728a2b5a11e5e90fbb4e897b625cc117` |
| `admin/logout.php` | `32c32afa0426b643cde72e06cf2d324a728a2b5a11e5e90fbb4e897b625cc117` |

### Selepas

| Fail | SHA-256 |
|---|---|
| `app/Auth/LogoutHandler.php` | `227806319e0d227cf84d88e4ac3ec4eea64a9420aabefd7f7ed55a0fd51aee11` |
| `page/logout.php` | `3bd15f46fc360b3dd5fe759e2adf26f51d6607ef9296a76e150fbb4e708949a9` |
| `admin/logout.php` | `3bd15f46fc360b3dd5fe759e2adf26f51d6607ef9296a76e150fbb4e708949a9` |
| `public/page/logout.php` tidak berubah | `735e6bad6e1f9f4afad9ebacc432c3623889acebc1f3cca9540e2a179656044b` |
| `public/admin/logout.php` tidak berubah | `0c3ab054be1f3329be886343dbd6dfbaa2fccbc97f9e67d4db37aee2869d92a3` |

## 9. Keputusan Semasa

R5.2B1 ditutup sebagai PASS pada 14 Julai 2026, 03:35 +0800. Technical extraction,
70/70 regression bagi setiap hostname dan 14/14 authenticated admin contract
bagi setiap hostname semuanya lulus. Access log tidak menunjukkan 5xx dan error
log tidak menunjukkan PHP fatal, warning atau handler error baharu.

Normal-user direct execution kekal sebagai accepted exception R5.2B0 dan bukan
regression yang diperkenalkan oleh extraction ini.
