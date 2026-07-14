# M0 — Audit Modal User Info dan Characterization

Tarikh: 14 Julai 2026
Owner perubahan: Pemilik sistem OneID
Owner rollback: Pemilik sistem OneID
Status: **SELESAI — SUPERSEDED BY M1 SAFE USER RESYNC**

## 1. Objektif dan batas

M0 merekod behavior semasa bagi hasil carian User Accounts dan modal `User
Info` sebelum pembaikan functional dibuat. Audit meliputi paparan profil,
perubahan kategori, Resync, Reset Password, Remove/Reactivate dan ACL aplikasi.

M0 tidak:

- menekan mana-mana action modal;
- menyambung ke external database;
- membaca atau menulis live database OneID;
- mereset password, token atau OTP;
- mengubah status, kategori atau ACL pengguna;
- membaiki production handler Resync.

Satu perubahan presentation-only dibuat: hasil autocomplete dan result card
ditetapkan kepada top/left alignment. Perubahan ini tidak mengubah request atau
data.

## 2. Peta aliran semasa

```text
User Accounts search
  → admin_search_keyword_user
  → pilih suggestion (data4/u_id)
  → admin_search_user_account
  → View
  → modal User Info
      ├─ admin_get_get_specific_user_profile_info
      ├─ get_specific_user_sp_access_list
      ├─ admin_change_user_category
      ├─ admin_resync_specific_user
      ├─ admin_reset_password_user
      ├─ admin_deactivate_user_record / admin_reactivate_user_record
      └─ add/deny/uplift ACL aplikasi
```

Semua action tersebut melalui `oneid_guard_q_func_request()` dan memerlukan
POST, exactly-one recognized action, CSRF, authenticated session dan role admin.

## 3. Ringkasan finding

| ID | Severity | Komponen | Finding | Keputusan |
| --- | --- | --- | --- | --- |
| M0-F01 | Kritikal | Resync | Handler masih menggunakan sample localhost, bukan authoritative external lookup | Disable/replace dalam M1 |
| M0-F02 | Kritikal | Resync | Empty result tidak ditolak sebelum `data1..data12` digunakan dan ditulis | Fail-closed dalam M1 |
| M0-F03 | Tinggi | Resync | Tiada provenance protection, preview, confirmation, transaction atau rollback | Bina service terkawal dalam M1 |
| M0-F04 | Tinggi | Profile | Nama kelihatan editable tetapi tiada persistence handler | Polisi manual/external dalam M3 |
| M0-F05 | Tinggi | Profile | Butang Save mempunyai handler kosong | Ganti dengan workflow jelas dalam M3 |
| M0-F06 | Sederhana | Category | Dropdown terus mutate sebelum Save; category `9` di-hardcode sebagai admin | Validasi dan role mapping dalam M3 |
| M0-F07 | Tinggi | Reset password | Password rawak + token revocation baik, tetapi tiada audit event, transaction, result verification atau explicit OTP invalidation | Hardening dalam M2 |
| M0-F08 | Tinggi | Status | Remove/Reactivate memulangkan success walaupun row mungkin tidak berubah | Verify row/result dalam M2 |
| M0-F09 | Tinggi | Status | Status, token dan audit bukan satu atomic transaction | Transaction dalam M2 |
| M0-F10 | Sederhana | ACL | Add/Deny/Uplift tiada audit event, duplicate/resource validation dan error feedback yang cukup | Hardening dalam M3 |
| M0-F11 | Tinggi | Output | Nama aplikasi dibina terus sebagai HTML dalam modal | Contextual encoding dalam M3 |
| M0-F12 | Sederhana | UI | Banyak AJAX error callback kosong dan state gagal tidak diterangkan | Standard error state M1–M3 |

## 4. Penilaian setiap fungsi

### 4.1 Papar profil

Profil pengguna berdaftar dan effective application access boleh dibaca.
Walau bagaimanapun, handler `source=2` tidak menghasilkan response dan error
callback UI kosong. Nama profil dimasukkan melalui `.val()` tetapi nama aplikasi
ACL dicantum terus ke HTML.

### 4.2 Resync User Info

Resync semasa ialah **NO-GO**. Production action memanggil
`SAMPLE_DATA_SOURCE_GET_SPECIFIC_USER()` yang membaca
`http://localhost/SSO_IDP/sample_data.php`. Jika identiti tidak ditemui, helper
memulangkan array kosong tetapi controller masih membina hash dan berpotensi
menulis dua belas field kosong ke `user_tbl`.

Handler juga:

- tidak memeriksa `account_source` atau `sync_protected`;
- tidak menggunakan `SyncPlanner`/preview S2;
- merekod audit sebelum outcome diketahui;
- tidak mempunyai transaction/rollback;
- tidak membezakan `not found`, `unchanged` dan source failure dengan selamat.

Sehingga M1 selesai, pentadbir tidak patut menggunakan butang Resync.

### 4.3 Reset Password

Baseline positif:

- password diganti dengan secret rawak yang tidak diketahui admin;
- password disimpan melalui `password_hash`;
- `password_change_required=1`;
- token pengguna dinyahaktif;
- pengguna diarahkan menggunakan Forgot Password/OTP.

Baki kelemahan ialah tiada audit khusus, correlation ID, transaction, row-count
verification dan explicit invalidation OTP aktif. Action ini tidak menghantar
email; pengguna perlu memulakan flow Forgot Password sendiri.

### 4.4 Remove dan Reactivate

Remove menukar `avail_status=0`, membatalkan token dan menulis audit. Reactivate
menukar `avail_status=1` dan menulis audit. Kedua-dua endpoint bagaimanapun
memulangkan `status=1` secara unconditional dan tidak menggabungkan mutation
serta audit dalam transaction.

### 4.5 Kategori dan Save

Kategori berubah serta-merta melalui event `change`, membatalkan token dan
menulis audit. Butang Save tidak digunakan. Category ID `9` menentukan admin
secara hardcoded, dan server belum mengesahkan category yang dihantar ialah
active category.

Polisi sasaran:

- nama external-managed adalah read-only dan berubah melalui safe Resync;
- nama manual boleh diedit melalui validated manual profile workflow;
- kategori disimpan melalui action yang jelas;
- perubahan privilege admin memerlukan explicit elevated confirmation;
- role mapping tidak bergantung pada numeric ID hardcoded.

### 4.6 ACL aplikasi

Add, Deny dan Uplift mempunyai confirmation serta admin/CSRF guard. Baki
keperluan ialah audit event, resource validation, duplicate-safe persistence,
transaction yang sesuai, encoded output dan visible error state.

## 5. Characterization contract

Artefak M0:

- `tests/characterization/m0_user_profile_modal_contracts.php`;
- `tools/m0_user_profile_modal_characterization.php`;
- alias `npm run check:user-modal-baseline`.

Arahan:

```bash
php tools/m0_user_profile_modal_characterization.php
npm run check:user-modal-baseline
```

Contract hanya membaca source dan menjalankan PHP lint. Ia tidak include
application entry point, membuka session, membuat HTTP request atau mengakses
database/network.

Assertion berlabel `known risk` ialah bukti kelemahan legacy masih wujud, bukan
tanda behavior tersebut selamat. Assertion itu perlu diganti dengan safety
contract apabila fasa berkaitan dilaksanakan.

### Keputusan verification

| Semakan | Keputusan |
| --- | --- |
| Aggregate M0 static characterization | 65/65 PASS |
| PHP lint lima runtime source | PASS |
| Admin action-map/CSRF/authorization | PASS |
| Top/left search-result alignment contract | PASS |
| Resync/reset/status/category/ACL known-risk baseline | PASS, risiko disahkan |
| Release metadata/device-session regression | 10/10 PASS |
| Live HTTP/database/network mutation | Tidak dijalankan |

Arahan PHP terus telah dijalankan dan lulus. Alias npm telah ditambah ke
`package.json`, tetapi executable `npm` tidak tersedia pada host semakan ini;
kesahan JSON `package.json` telah disemak menggunakan parser PHP.

## 6. Pelan pembaikan

| Fasa | Skop | Production mutation |
| --- | --- | --- |
| M0 | Audit, caller map, static characterization dan top alignment | Tidak |
| M1 | Safe Resync lookup, provenance guard, preview/diff, transaction, audit dan fail-closed response | Ya, hanya selepas confirmation |
| M2 | Reset Password serta Remove/Reactivate safety | Ya, terkawal |
| M3 | Profile Save/category policy dan ACL hardening | Ya, terkawal |

## 7. Gate M1

M1 tidak boleh mengaktifkan Resync sehingga semua syarat berikut dipenuhi:

- test external lookup menggunakan fixture, bukan live mutation;
- zero/multiple row ditolak tanpa mutation;
- protected manual account ditolak;
- field diff dipaparkan sebelum Apply;
- confirmation mengikat user ID dan fingerprint/diff semasa;
- transaction, rollback dan audit diuji;
- source failure menghasilkan safe response dengan correlation ID;
- current M0 known-risk assertions telah diganti dengan M1 safety assertions.

## 8. Rollback M0

M0 tidak memerlukan rollback data. Sebelum commit, artefak boleh dibuang dengan:

```bash
git restore admin/dashboard.php package.json
git clean -f docs/M0_AUDIT_MODAL_USER_INFO_DAN_CHARACTERIZATION.md
git clean -f tests/characterization/m0_user_profile_modal_contracts.php
git clean -f tools/m0_user_profile_modal_characterization.php
```

Selepas commit, gunakan `git revert <commit-M0>`.

## 9. Keputusan

M0 menetapkan baseline tanpa mutation. Keputusan operasi kekal: **Resync
User Info ialah NO-GO sehingga M1 selesai**. Reset Password dan action lain
belum diubah oleh M0.
