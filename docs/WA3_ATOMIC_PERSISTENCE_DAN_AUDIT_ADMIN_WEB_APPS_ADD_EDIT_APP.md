# WA3 — Atomic Persistence dan Audit Admin Web Apps Add/Edit App

**Tarikh:** 17 Julai 2026  
**Status:** IMPLEMENTED — AUTOMATED CONTRACT/FAILURE REGRESSION LULUS, MANUAL UAT BERBAKI  
**Schema migration:** Tiada

## 1. Objektif

WA3 menghapuskan partial success bagi upload yang diminta dan mengikat metadata,
mandatory success audit serta publish fail baharu dalam transaction dengan
filesystem compensation.

## 2. Aliran baharu

### Add

1. Validate metadata/actor.
2. Jika icon dipilih, validate dan pindahkan ke
   `storage/runtime/app-icon-staging` dengan permission `0600`.
3. Mulakan database transaction dan lock category.
4. Jana App ID, insert metadata dan tulis event audit 13.
5. Publish staged icon melalui atomic `rename()` ke `public/public_img`.
6. Commit transaction.

### Edit

1. Validate metadata/actor dan stage icon pilihan.
2. Mulakan transaction; lock category dan app target.
3. Pastikan app masih aktif dan ambil existing icon daripada row yang dilock.
4. Update metadata/image reference dan tulis event audit 14.
5. Publish staged icon, kemudian commit.

## 3. Failure contract

- icon yang dipilih tetapi gagal validation menghasilkan `WA3_ICON_REJECTED`;
  tiada metadata mutation dibuat;
- insert/update count luar jangka menggagalkan operasi;
- event audit mesti berjaya; audit gagal menyebabkan rollback;
- publish gagal menyebabkan rollback database;
- commit/exception selepas publish menyebabkan fail final baharu dipadam;
- staged file sentiasa dibuang apabila operasi gagal;
- icon lama tidak dipadam dalam WA3 dan kekal tersedia untuk rollback/retention.

Database dan filesystem tidak boleh mempunyai distributed transaction sebenar.
WA3 mencapai consistency melalui database transaction, ordered publish dan
explicit compensation. Kegagalan OS yang menghalang `unlink()` akan direkod ke
error log dan kemudian dikesan oleh reconciliation WA6.

## 4. Response code

| Code | Maksud |
| --- | --- |
| `WA3_APP_CREATED_ATOMIC` | Metadata, audit dan optional icon Add selesai. |
| `WA3_APP_UPDATED_ATOMIC` | Metadata, audit dan optional icon Edit selesai. |
| `WA3_APP_UNCHANGED` | Tiada perubahan disimpan. |
| `WA3_ICON_REJECTED` | Icon pilihan gagal sebelum mutation. |
| `WA3_APP_NOT_CREATED` | Insert tidak menghasilkan tepat satu row. |
| `WA3_APP_UPDATE_COUNT_INVALID` | Update count tidak sah. |
| `WA3_AUDIT_NOT_WRITTEN` | Mandatory audit gagal; mutation dirollback. |
| `WA3_APP_CREATE_FAILED` | Create gagal secara dalaman dan di-compensate. |
| `WA3_APP_UPDATE_FAILED` | Update gagal secara dalaman dan di-compensate. |

## 5. Sempadan

- `sp_list.sp_image` masih global; isolation environment ialah WA4;
- format/dimensi/re-encode ialah WA5;
- icon lama dan orphan sedia ada tidak dibersihkan; reconciliation ialah WA6;
- validation rejection yang berlaku sebelum mutation tidak ditulis sebagai
  event kejayaan Add/Edit. Correlation masih dipulangkan kepada UI.

## 6. Verification

```bash
php -l lib/upload_security.php
php -l app/Admin/WebAppService.php
php tools/wa1_web_app_ui_contract.php
php tools/wa2_web_app_service_contract.php
php tools/wa3_web_app_atomic_contract.php
```

Failure regression menggunakan fake operation untuk membuktikan mutation/audit
failure memanggil rollback dan tidak commit. Ia tidak menulis database atau fail
runtime sebenar.

Baseline read-only selepas automated verification menunjukkan 74 app, 36 aktif,
62 row bergambar, 92 local icon files, zero missing referenced file dan 30 calon
orphan. Pertambahan row berbanding WA0 datang daripada aktiviti pilot yang telah
diarkib melalui flow rasmi; automated WA3 contract tidak menulis database.
Direktori staging WA3 belum wujud selepas automated test, membuktikan test tidak
meninggalkan staged upload.

## 7. Manual pilot UAT

1. Add pilot tanpa icon; jangka `WA3_APP_CREATED_ATOMIC`.
2. Edit pilot tanpa icon; jangka `WA3_APP_UPDATED_ATOMIC`, icon retained.
3. Cuba Edit dengan fail bukan imej; jangka `WA3_ICON_REJECTED` dan metadata lama
   kekal selepas refresh.
4. Add pilot kedua dengan fail imej sah; jangka atomic success dan fail wujud.
5. Semak event 13/14 menggunakan correlation ID.
6. Archive semua app pilot melalui flow rasmi.

Simulasi audit/publish/commit failure tidak dilakukan melalui UI production-like;
ia diliputi automated failure contract.

### Pemerhatian UAT awal

- Edit tanpa icon dilaporkan berjaya oleh owner.
- Percubaan fail bukan imej pada 17 Julai 2026 14:58:13 menerima HTTP `413`
  daripada Nginx sebelum request sampai kepada PHP. UI ketika itu memaparkan
  fallback `WA1_REQUEST_FAILED` tanpa reference.
- Punca: active Nginx menggunakan default request-body limit yang lebih rendah
  daripada polisi backend 5 MB.
- Pembetulan aplikasi menambah MIME/5 MB client preflight melalui SweetAlert dan
  memetakan sebarang HTTP 413 kepada `WA3_UPLOAD_REQUEST_TOO_LARGE` bersama
  client correlation reference.
- Template Nginx WSL/staging/production kini menetapkan
  `client_max_body_size 6m`; konfigurasi live perlu dipasang dan direload oleh
  administrator server sebelum fail imej 1–5 MB boleh diuji secara konsisten.

### Evidence UAT selepas pembetulan 413

| Ujian | Status | Evidence |
| --- | --- | --- |
| Edit tanpa memilih icon | OWNER-REPORTED PASS | Owner melaporkan Edit melalui UI berjaya; success code/reference masih belum dibekalkan. |
| Tolak fail bukan imej — percubaan 1 | PASS | SweetAlert `WA3_CLIENT_ICON_TYPE_REJECTED`; client reference `b51f3efbf2cea9be`; request/mutation tidak dihantar. |
| Tolak fail bukan imej — percubaan 2 | PASS | SweetAlert `WA3_CLIENT_ICON_TYPE_REJECTED`; client reference `8fa96e7d0cfa3294`; request/mutation tidak dihantar. |
| Edit dengan replacement icon PNG sah | PASS | `WA3_APP_UPDATED_ATOMIC`; app `2WJ4USYRS9`; event 14 outcome `success`, icon `stored`; reference `682380de230c5ea4`; 17 Julai 2026 15:08:15. |
| Publish filesystem replacement icon | PASS | `app_icon_e4d7802cbca3ac33e90af6e6bb904a36.png`, MIME `image/png`, 37,825 byte, mode `0644`; referenced oleh `sp_list.sp_image`. |
| Paparan replacement icon selepas refresh | PASS | Owner mengesahkan gambar baharu dipaparkan dengan betul melalui UI. |
| Staging cleanup selepas atomic success | PASS | `storage/runtime/app-icon-staging` mengandungi 0 fail selepas commit. |

Client reference digunakan untuk troubleshooting UI sahaja dan tidak dijangka
wujud dalam Audit Log database kerana validation berlaku sebelum request. UAT
backend MIME/content mismatch masih boleh dilakukan menggunakan fail kecil yang
berekstensi imej tetapi kandungannya bukan imej; outcome dijangka
`WA3_ICON_REJECTED` dengan server correlation reference.

## 8. Rollback

Pulihkan service WA2 dan helper upload sebelumnya. Tiada schema rollback. Fail
icon lama jangan dipadam; icon baharu yang telah committed kekal sah dan masih
dirujuk oleh `sp_list.sp_image`.
