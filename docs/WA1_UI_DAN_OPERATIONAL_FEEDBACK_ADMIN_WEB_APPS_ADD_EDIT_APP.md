# WA1 — UI dan Operational Feedback Admin Web Apps Add/Edit App

**Tarikh:** 17 Julai 2026  
**Status:** IMPLEMENTED — AUTOMATED CONTRACT LULUS, MANUAL UAT BERBAKI  
**Dependency:** WA0 baseline/caller map  
**Tidak termasuk:** validation service, atomic persistence, schema aset dan cleanup

## 1. Objektif

WA1 membetulkan penerangan UI dan memastikan admin melihat keputusan operasi
melalui SweetAlert yang konsisten dengan antaramuka pentadbir.

## 2. Perubahan

- label `65x65` dibuang kerana backend semasa tidak menguatkuasakan dimensi itu;
- Add menerangkan icon sebagai optional, format yang diterima dan had 5 MB;
- Edit menerangkan bahawa tiada fail baharu bermaksud icon sedia ada dikekalkan;
- checkbox menerangkan dengan jelas bahawa checked bermaksud Direct link/tidak
  menyokong OneID SSO;
- Add dan Edit memerlukan confirmation sebelum request dihantar;
- butang menunjukkan `Adding...`/`Saving...`, disabled dan `aria-busy` sepanjang
  request untuk menghalang double-submit;
- semua keputusan Add/Edit dipaparkan melalui SweetAlert dengan code dan
  correlation reference; tiada panel mesej khusus dimasukkan ke dalam modal;
- network/invalid response tidak lagi senyap;
- response backend membezakan icon `stored`, `retained`, `not_requested` dan
  `rejected`;
- audit event 13/14 kini menyertakan outcome, icon outcome dan correlation ID.

## 3. Sempadan penting

WA1 belum menjadikan fail dan database atomic. Untuk behavior legacy semasa:

- Add dengan metadata sah tetapi icon ditolak masih boleh mencipta app tanpa
  custom icon;
- Edit dengan metadata berubah tetapi icon ditolak masih boleh menyimpan
  metadata dan mengekalkan icon lama.

Perbezaannya ialah UI sekarang menyatakan partial outcome secara jelas melalui
`WA1_APP_ADDED_ICON_REJECTED` atau `WA1_APP_UPDATED_ICON_REJECTED`. Behavior ini
akan dibetulkan secara atomic dalam WA3, selepas validation service WA2.

**Superseded oleh WA3:** partial outcome ini tidak lagi dibenarkan. Upload pilihan
yang ditolak kini menggagalkan operasi dengan `WA3_ICON_REJECTED` sebelum
metadata mutation.

## 4. Response code

| Code | Maksud |
| --- | --- |
| `WA1_APP_ADDED` | App dicipta; icon disimpan atau tidak diminta. |
| `WA1_APP_ADDED_ICON_REJECTED` | App dicipta tetapi icon pilihan ditolak. |
| `WA1_APP_ADD_FAILED` | Rekod app tidak dicipta. |
| `WA1_APP_UPDATED` | Metadata berubah; icon disimpan atau dikekalkan. |
| `WA1_APP_UPDATED_ICON_REJECTED` | Metadata berubah tetapi icon baharu ditolak dan icon lama dikekalkan. |
| `WA1_APP_UNCHANGED` | Tiada row database berubah. |
| `WA1_REQUEST_FAILED` | UI tidak menerima response sah daripada server. |

## 5. Verification

```bash
php -l admin/dashboard.php
php -l lib/q_func.php
php tools/wa1_web_app_ui_contract.php
```

## 6. Manual UAT berbaki

Gunakan app pilot terkawal:

1. buka Add App dan batalkan confirmation; sahkan tiada request/mutation;
2. submit Add sekali dan cuba klik semula ketika loading; sahkan satu request;
3. Add tanpa icon; sahkan SweetAlert success memaparkan code dan reference;
4. Add dengan fail bukan imej; sahkan partial warning tepat;
5. Edit tanpa icon; sahkan existing icon retained;
6. Edit dengan fail bukan imej; sahkan metadata/icon outcome diterangkan tepat;
7. simulasi network failure; sahkan SweetAlert error dipaparkan;
8. semak Audit Log event 13/14 menggunakan correlation ID.

App pilot yang dicipta semasa UAT perlu diarkib melalui flow rasmi; jangan padam
row/fail secara manual.

## 7. Rollback

Rollback WA1 memulihkan markup/JavaScript Add/Edit dan response/audit detail
handler sahaja. Tiada rollback database diperlukan kerana WA1 tidak mengubah
schema atau data sedia ada.
