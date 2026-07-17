# WA2 — Validation dan Application Service Admin Web Apps Add/Edit App

**Tarikh:** 17 Julai 2026  
**Status:** COMPLETE — AUTOMATED CONTRACT DAN OWNER MANUAL UAT LULUS  
**Schema migration:** Tiada

## 1. Perubahan

Add dan Edit kini melalui `OneId\App\Admin\WebAppService`. Handler `q_func` tidak
lagi menormalisasi URL, menjana App ID atau memanggil mutation database secara
terus.

Service menguatkuasakan:

- nama wajib, maksimum 150 aksara dan tiada control character;
- penerangan wajib, maksimum 2,000 aksara;
- URL absolute HTTPS, mempunyai hostname, maksimum 2,048 byte, tanpa credential
  atau fragment;
- category ID berbentuk integer dan mesti wujud;
- Edit App ID sah, target mesti wujud dan masih aktif;
- admin ID dan IP address sah;
- nilai SSO ditentukan secara konsisten daripada pilihan Direct link;
- App ID 10 aksara menggunakan `random_int()` dan alphabet tanpa aksara kabur,
  dengan collision check sehingga 10 percubaan;
- existing icon Edit diambil semula daripada database, bukan dipercayai daripada
  hidden field browser.

Validation metadata berlaku sebelum fail dipindahkan ke upload directory.

## 2. Polisi duplicate

WA2 tidak menolak duplicate nama atau domain dan tidak memasang unique
constraint. Baseline mempunyai 16 kumpulan nama dan 6 kumpulan domain duplicate.
Polisi ini kekal menunggu keputusan WA0-D10 supaya data legacy tidak disekat atau
diubah melalui andaian implementation.

## 3. Sempadan WA2

Fail, mutation database dan audit masih belum berada dalam satu transaction.
Upload gagal masih boleh menghasilkan partial outcome yang diterangkan oleh
SweetAlert. Compensation fail, mandatory atomic audit dan lifecycle gambar lama
ialah skop WA3.

**Superseded oleh WA3:** upload gagal kini menghentikan mutation; database,
mandatory success audit dan publish fail menggunakan transaction/compensation.

WA2 tidak mengubah `sp_list.sp_image` kepada aset khusus environment; itu skop
WA4.

## 4. Code utama

- `WA2_APP_CREATED`
- `WA2_APP_CREATED_ICON_REJECTED`
- `WA2_APP_UPDATED`
- `WA2_APP_UPDATED_ICON_REJECTED`
- `WA2_APP_UNCHANGED`
- `WA2_APP_NAME_INVALID`
- `WA2_APP_DESCRIPTION_INVALID`
- `WA2_APP_URL_INVALID`
- `WA2_APP_URL_NOT_ALLOWED`
- `WA2_CATEGORY_ID_INVALID`
- `WA2_CATEGORY_NOT_FOUND`
- `WA2_APP_ID_INVALID`
- `WA2_APP_NOT_FOUND`
- `WA2_APP_INACTIVE`

Semua rejection mempunyai correlation ID dan tidak mendedahkan exception dalaman.

## 5. Verification

```bash
php -l app/Admin/WebAppService.php
php -l lib/Database.php
php -l lib/q_func.php
php tools/wa1_web_app_ui_contract.php
php tools/wa2_web_app_service_contract.php
```

## 6. Manual UAT berbaki

1. Add menggunakan URL `http://`; mesti ditolak `WA2_APP_URL_NOT_ALLOWED`.
2. Add menggunakan nama kosong/description kosong; mesti ditolak tanpa fail
   baharu pada filesystem.
3. Add menggunakan category tidak sah melalui request terkawal; mesti ditolak.
4. Add valid tanpa icon dan simpan App ID/reference.
5. Edit app pilot menggunakan URL HTTPS valid.
6. Cuba Edit app inactive/tidak wujud; mesti ditolak.
7. Semak event audit 13/14 berdasarkan correlation ID.

### Evidence manual UAT owner — 17 Julai 2026

| Ujian | Status | Evidence |
| --- | --- | --- |
| Tolak URL HTTP | PASS | `WA2_APP_URL_NOT_ALLOWED`; reference `1a2cb62df70f66a0`; app tidak dicipta. |
| Tolak nama kosong | PASS | `WA2_APP_NAME_INVALID`. |
| Tolak description kosong | PASS | `WA2_APP_DESCRIPTION_INVALID`. |
| Tolak URL kosong | PASS | `WA2_APP_URL_INVALID`. |
| Tolak category ID tidak sah melalui UI terkawal | PASS | Owner mengesahkan submit menggunakan category `999999999` berjaya ditolak oleh backend; tiada mutation Add berlaku. |
| Add valid tanpa icon | PASS | `WA2_APP_CREATED`; Site API Code `JNJ7NU3HFR`; icon `not_requested`; reference `0f3160f43a821cc5`. |
| Edit valid | PASS | `WA2_APP_UPDATED`; existing icon retained; reference `8e008c8c8e505fc3`. |
| Site API Code kekal selepas Edit | PASS | Kekal `JNJ7NU3HFR`. |
| Correlation audit Add | PASS | Event 13 ditemui: actor `820705025923`, app `JNJ7NU3HFR`, outcome `success`, icon `not_requested`, IP `127.0.0.1`, 17 Julai 2026 14:37:56. |
| Correlation audit Edit | PASS | Event 14 ditemui: actor `820705025923`, app `JNJ7NU3HFR`, outcome `success`, icon `retained`, IP `127.0.0.1`, 17 Julai 2026 14:42:51. |
| Archive app pilot | PASS | Event 15/reference `1f3f6c1ef63470b8`; app `JNJ7NU3HFR` menjadi inactive, category `0`, ACL/blacklist/favourite cleanup semuanya `0`. |

Manual gate WA2 ditutup selepas owner mengesahkan category ID tidak sah ditolak
melalui UI terkawal. Edit, correlated Audit Log dan archive pilot turut lulus
serta disahkan terus daripada database. Rejection validation berlaku sebelum
mutation; oleh itu ia tidak menghasilkan event kejayaan Add.

## 7. Pemerhatian drift semasa pelaksanaan

Audit read-only akhir pada 17 Julai 2026 mengesan state runtime berubah berbanding
snapshot WA0 walaupun automated WA2 contract menggunakan fake operation dan
tidak melakukan upload sebenar:

- jumlah app kekal 72;
- rujukan gambar unik meningkat daripada 61 kepada 62;
- local icon files meningkat daripada 90 kepada 92;
- calon orphan meningkat daripada 29 kepada 30;
- fail baharu bertimestamp 13:38:03 dan 13:41:26 +08:00.

Ini direkod sebagai perubahan serentak/runtime dan bukan dibersihkan atau
diatribusikan secara andaian. Fail tersebut mesti kekal sehingga reconciliation
WA6 menentukan rujukan setiap environment.

## 8. Rollback

Pulihkan handler Add/Edit legacy dan keluarkan method create/update WA2 daripada
`WebAppService` serta dua lookup read-only database. Tiada rollback schema atau
data diperlukan.
