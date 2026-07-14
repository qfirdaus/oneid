# M1 — Safe User Resync dan Rollback

Tarikh: 14 Julai 2026
Owner perubahan: Pemilik sistem OneID
Owner rollback: Pemilik sistem OneID
Status: **IMPLEMENTED — STATIC/IN-MEMORY PASS, MANUAL UAT GATE PENDING**

## 1. Objektif

M1 menggantikan direct single-user Resync legacy dengan workflow:

```text
Admin pilih external-managed user
  → Preview (SELECT-only external lookup)
  → provenance + active-user gate
  → exactly-one identity gate
  → field diff + fingerprint
  → one-time server-side approval, TTL 5 minit
  → explicit admin confirmation
  → Apply refetch + row lock + fingerprint verification
  → transaction: profile update + audit
  → commit atau rollback keseluruhan
```

M1 tidak mengaktifkan full external sync S4E dan tidak mengubah scheduler.

## 2. Perubahan utama

### 2.1 External lookup sebenar dan read-only

`EXTERNAL_DATA_SOURCE_GET_SPECIFIC_USER()` kini:

- hanya mempunyai query `SELECT`;
- menggunakan identity allowlist ketat sebelum `odbc_exec()`;
- staf dicari melalui `nokp`, iaitu primary identity `data4`;
- pelajar dicari melalui `no_matrik`, iaitu primary identity `data4` yang sama
  dengan full sync;
- memetakan IC pelajar kepada `data2`, selaras dengan full sync;
- menutup kedua-dua connection melalui `finally`;
- melempar diagnostic exception dan tidak menggunakan `echo/exit`.

FreeTDS/Sybase pada host ini tidak menyokong `SQLDescribeParameter` dan
memulangkan ODBC `IM001` apabila `odbc_prepare()` digunakan. Oleh sebab itu,
single-user lookup menggunakan `odbc_exec()` dengan identity yang terlebih
dahulu diwajibkan memenuhi `^[A-Za-z0-9._@-]+$`, maksimum 20 aksara. Allowlist
tersebut menolak quote, whitespace dan SQL metacharacter sebelum literal dibina.

View student production mendedahkan `no_matrik` sebagai `INT`. Lookup menukar
kolum itu secara eksplisit kepada `VARCHAR(64)` untuk perbandingan supaya
identiti staff berbentuk teks yang turut melalui semakan student tidak
mencetuskan implicit `VARCHAR`-to-`INT` conversion. Ia masih query `SELECT`
sahaja dan nilai carian masih melalui allowlist yang sama.

Lookup M1 juga dirutekan mengikut bentuk rekod OneID semasa. Rekod staff yang
mempunyai `data3` hanya membaca sumber staff, manakala rekod student tanpa
`data3` tetapi mempunyai `data2` hanya membaca sumber student. Ini mengelakkan
kegagalan sumber yang tidak berkaitan daripada menutup Resync bagi akaun lain,
tanpa menurunkan polisi fail-closed untuk sumber authoritative akaun tersebut.
Jika kedua-dua identity marker tiada, request ditolak dengan
`RESYNC_SOURCE_IDENTITY_UNDETERMINED`.

Semasa verification 14 Julai 2026, credential legacy
`ONEID_STUDENT_LOOKUP_*` boleh connect tetapi menerima `SELECT permission
denied` pada `asisdb.dbo.v210_sso_student_aktif`. Credential
`ONEID_STUDENT_SYNC_*` menggunakan DSN yang sama dan berjaya menjalankan
`SELECT`; credential inilah yang turut menghasilkan full External Sync Preview.
M1 student Resync kini menggunakan set `STUDENT_SYNC` yang authoritative dan
telah dibuktikan boleh membaca view tersebut. Kod M1 masih hanya mengeluarkan
query `SELECT` berfilter. Credential di peringkat DBA tetap mesti read-only;
tiada privilege tulis diperlukan atau dibenarkan.

Credential external masih mesti kekal read-only pada peringkat DBA. Application
code tidak boleh menggantikan database privilege enforcement.

### 2.2 Provenance protection

Hanya akaun dengan `account_source=external` dan `avail_status=1` dibenarkan.
Akaun `manual`, termasuk `sync_protected=1`, ditolak sebelum external lookup.
Schema tanpa provenance juga fail-closed sebagai non-external.

### 2.3 Zero/multiple/identity gate

Preview ditolak tanpa mutation apabila:

- user OneID tidak ditemui;
- akaun tidak aktif;
- external source memulangkan kosong;
- lebih satu external row ditemui;
- `data4` external tidak sama dengan `u_id` OneID;
- connection/query external gagal.

### 2.4 Preview dan approval

Preview membandingkan `data1..data12`. Ia tidak mengubah database OneID.
Identity `data2`, `data3` dan `data4` dimask dalam response preview.

Jika ada perubahan:

- approval ID 256-bit dijana;
- hanya fingerprint, admin ID, user ID, correlation ID dan expiry disimpan
  dalam PHP session server-side;
- raw external row tidak disimpan dalam approval;
- hanya latest approval bagi pasangan admin/user dikekalkan;
- approval tamat dalam lima minit dan hanya boleh digunakan sekali.

Jika tiada perubahan, response `NO_CHANGES` tidak mengeluarkan approval.

### 2.5 Apply, transaction dan audit

Selepas admin mengesahkan preview:

1. approval dikonsumsi dahulu untuk mencegah replay/race;
2. external row dibaca semula;
3. transaction OneID dimulakan;
4. user row dibaca menggunakan `FOR UPDATE`;
5. provenance, status, exactly-one row dan fingerprint disemak semula;
6. hanya `data1..data12` serta change hash dikemas kini;
7. audit event `24` ditulis dengan changed-field names dan correlation ID;
8. commit hanya berlaku jika update dan audit kedua-duanya berjaya.

Mismatch, update failure, audit failure atau exception menyebabkan rollback.
Kategori, role, password, token, status dan ACL tidak diubah oleh Resync.

## 3. Action endpoint

Action admin baharu:

- `admin_preview_specific_user_resync`;
- `admin_apply_specific_user_resync`.

Action lama `admin_resync_specific_user` telah dikeluarkan daripada action map
dan controller. Kedua-dua action baharu mewarisi:

- POST-only;
- exactly-one recognized action;
- CSRF;
- authenticated session;
- administrator role.

Failure response tidak mendedahkan exception/database detail. Ia memulangkan
safe code dan correlation ID.

## 4. UI modal

Butang Resync kini:

- meminta preview dahulu;
- memaparkan field lama → baharu;
- memask field identiti;
- meminta explicit `Apply changes` confirmation;
- membezakan no-change, manual protected, inactive, not-found, ambiguous,
  source unavailable, expired dan fingerprint mismatch;
- tidak menganggap HTTP/AJAX failure sebagai success;
- refresh profil selepas Apply berjaya.

## 5. Artefak

- `app/User/UserResyncService.php`;
- `app/User/UserResyncException.php`;
- `app/User/Contracts/UserResyncApprovalStoreInterface.php`;
- `app/User/Adapters/SessionUserResyncApprovalStore.php`;
- `tests/characterization/m1_safe_user_resync.php`;
- `tools/m1_safe_user_resync_contract.php`.

Arahan verification:

```bash
php tests/characterization/m1_safe_user_resync.php
php tools/m1_safe_user_resync_contract.php
```

Fixture menggunakan fake operation, in-memory approval store dan injected
external rows. Ia tidak membuka database, network atau session production.

### Keputusan verification automatik

| Semakan | Keputusan |
| --- | --- |
| M1 in-memory behavior fixture | 31/31 PASS |
| M1 aggregate static contract | 45/45 PASS |
| S1 manual/provenance regression | 39/39 PASS |
| S2 external preview regression | 29/29 PASS |
| S3 transaction/safety regression | 26/26 PASS |
| S4A/S4B/S4C/S4D dormant full-sync gates | PASS |
| Release metadata/device regression | 10/10 PASS |
| Live external/OneID mutation semasa automated verification | Tidak dijalankan |

## 6. Manual UAT gate

Sebelum M1 dianggap production-verified:

1. Pilih satu akaun external UAT yang mempunyai perubahan tidak sensitif.
2. Buka modal User Info dan klik Resync.
3. Pastikan preview muncul sebelum sebarang perubahan.
4. Tekan Cancel dan sahkan data OneID tidak berubah.
5. Jana preview sekali lagi dan tekan Apply changes.
6. Pastikan hanya field yang dipreview berubah.
7. Semak Audit Log mempunyai event Resync/correlation.
8. Cuba gunakan approval yang sama semula; ia mesti ditolak.
9. Uji akaun manual `S1TEST-20260714`; ia mesti ditolak sebagai protected
   tanpa perubahan.
10. Uji no-change external user; ia mesti memaparkan `No changes` tanpa Apply.

Manual UAT pada 14 Julai 2026 mengesahkan no-change preview berjaya untuk satu
akaun staff dan satu akaun student. Kedua-duanya memulangkan correlation ID dan
tidak mengubah data. Gate Cancel, Apply sebenar, audit event dan replay approval
masih belum dilaksanakan kerana tiada akaun UAT dengan perubahan selamat dipilih.

Jangan gunakan akaun production untuk gate pertama. External connection boleh
membaca production source hanya jika credential telah disahkan DBA sebagai
SELECT-only; mutation M1 berlaku pada database OneID environment semasa sahaja.

## 7. Monitoring

Pantau:

- PHP/Nginx error log bagi correlation ID;
- Audit Log event 24;
- response code `RESYNC_SOURCE_UNAVAILABLE`, `RESYNC_PREVIEW_MISMATCH` dan
  `RESYNC_AUDIT_NOT_WRITTEN`;
- sebarang unexpected change pada `data1..data12`.

Tiada password, OTP, token atau raw external row dicatat dalam audit detail.

## 8. Rollback

### Sebelum commit

```bash
git restore admin/dashboard.php lib/q_func.php lib/request_security.php \
  lib/Database.php lib/external_data_source_API.php package.json \
  tools/m0_user_profile_modal_characterization.php

git clean -f \
  app/User/Contracts/UserResyncApprovalStoreInterface.php \
  app/User/Adapters/SessionUserResyncApprovalStore.php \
  app/User/UserResyncException.php \
  app/User/UserResyncService.php \
  tests/characterization/m1_safe_user_resync.php \
  tools/m1_safe_user_resync_contract.php \
  docs/M1_SAFE_USER_RESYNC_DAN_ROLLBACK.md
```

### Selepas commit

Gunakan `git revert <commit-M1>`. Tiada schema migration M1 untuk di-rollback.

Jika rollback kecemasan hanya melibatkan UI, sembunyikan butang Resync dahulu;
jangan pulihkan direct legacy handler yang menggunakan sample localhost.

## 9. Keputusan

Legacy direct Resync telah diganti dengan preview-confirm-apply yang
fail-closed. M1 belum menandakan live manual UAT sebagai PASS sehingga owner
menjalankan gate dalam Seksyen 6.
