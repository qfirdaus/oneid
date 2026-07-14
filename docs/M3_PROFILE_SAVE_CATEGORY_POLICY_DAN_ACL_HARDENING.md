# M3 — Profile Save, Category Policy dan ACL Hardening

**Tarikh:** 14 Julai 2026  
**Change owner:** Pemilik sistem OneID  
**Rollback owner:** Pemilik sistem OneID  
**Status:** SELESAI — IMPLEMENTASI DAN MANUAL UAT LULUS

**Release:** OneID v2.0.3

## 1. Objektif

M3 menutup finding M0-F04, M0-F05, M0-F06, M0-F10, M0-F11 dan M0-F12
tanpa mengubah flow Safe Resync M1 atau tindakan keselamatan M2.

Skop:

- aktifkan butang **Save Profile** sebagai satu-satunya jalan menyimpan nama dan
  kategori;
- hentikan mutation apabila dropdown kategori berubah;
- pisahkan kategori pengguna daripada role administrator;
- jadikan nama akaun external-managed read-only;
- harden direct allow, deny dan uplift deny ACL;
- tambah transaction, row lock, validation, audit, correlation reference dan
  visible failure response;
- encode nama aplikasi yang dimasukkan ke DOM.

## 2. Polisi profil dan kategori

| Keadaan | Nama | Kategori | Role `u_type` |
| --- | --- | --- | --- |
| Akaun manual aktif | Boleh diubah selepas validation | Boleh diubah kepada kategori aktif | Dikekalkan |
| Akaun external aktif | Read-only; guna Safe Resync M1 | Boleh diubah kepada kategori aktif | Dikekalkan |
| Akaun inactive | Tidak boleh disimpan | Tidak boleh disimpan | Dikekalkan |

Category ID `9` tidak lagi memberikan administrator secara automatik. M3 tidak
mempunyai endpoint grant/revoke admin. Perubahan privilege perlu menggunakan
workflow berasingan yang explicit, diluluskan dan diaudit.

Save Profile:

1. mengesahkan ID, nama dan kategori;
2. lock row pengguna `FOR UPDATE`;
3. mengesahkan pengguna aktif dan kategori aktif;
4. menguatkuasakan provenance nama;
5. menyimpan nama/kategori dalam satu transaction;
6. membatalkan semua sesi jika kategori berubah;
7. menulis event audit `18` bersama correlation ID;
8. rollback jika mutation atau audit gagal.

## 3. Polisi ACL khusus pengguna

### Allow

- user dan aplikasi mesti aktif;
- direct/category allow yang sedia ada ditolak sebagai duplicate;
- deny sedia ada tidak boleh dilangkau; pentadbir perlu uplift dahulu;
- insert, session revocation dan event audit `28` adalah atomic.

### Deny

- hanya effective access yang wujud boleh dinafikan;
- duplicate deny ditolak;
- deny, session revocation dan event audit `29` adalah atomic.

### Uplift deny

- blacklist ID mesti sah dan wujud;
- rekod mesti benar-benar milik user yang sedang dibuka dalam modal;
- delete, session revocation dan audit event `28` adalah atomic.

Lock row pengguna menyusun perubahan ACL serentak bagi user sama. Ini memberi
duplicate safety walaupun schema legacy belum mempunyai unique constraint.

## 4. Fail terlibat

- `app/User/UserManagementException.php`
- `app/User/UserProfilePolicyService.php`
- `app/User/UserAclManagementService.php`
- `lib/Database.php`
- `lib/request_security.php`
- `lib/q_func.php`
- `admin/dashboard.php`
- `tests/characterization/m3_profile_acl_hardening.php`
- `tools/m3_profile_acl_contract.php`
- `package.json`

## 5. Verification automatik

```bash
php tools/m3_profile_acl_contract.php
php tests/characterization/m3_profile_acl_hardening.php
```

Fixture M3 ialah zero-I/O: tiada HTTP, database, external DB atau production
mutation. Ia memeriksa success, zero mutation, provenance, role preservation,
duplicate rejection, ownership, transaction, rollback, token revocation dan
mandatory audit.

## 6. Manual UAT

Gunakan akaun ujian manual bukan akaun pentadbir semasa.

### Ujian profil manual

1. Buka User Accounts dan modal akaun manual aktif.
2. Pastikan nama boleh diedit.
3. Tukar nama/kategori tetapi jangan klik Save; tutup dan buka semula modal.
4. Pastikan perubahan belum disimpan.
5. Ulang perubahan dan klik **Save Profile**.
6. Simpan reference, pastikan data berubah dan role admin tidak berubah.
7. Jika kategori berubah, pastikan sesi lama pengguna ditolak.

### Ujian akaun external

1. Buka akaun staf/pelajar external.
2. Pastikan nama disabled/read-only.
3. Tukar kategori dan Save; pastikan berjaya tanpa menukar nama atau role.

### Ujian ACL

1. Add satu aplikasi yang belum accessible; simpan reference.
2. Pastikan session lama user dibatalkan dan akses wujud selepas login baharu.
3. Deny aplikasi tersebut; simpan reference dan sahkan akses ditolak.
4. Uplift deny; simpan reference dan sahkan akses kembali selepas login baharu.
5. Ulang action sama dan pastikan duplicate ditolak, bukan dipaparkan success.
6. Semak Audit Log untuk `profile_save`, `acl_allow`, `acl_deny` dan
   `acl_uplift` beserta correlation reference.

## 7. Rollback

M3 belum mempunyai schema migration. Code rollback selepas commit:

```bash
git revert <commit-M3>
```

Rollback code tidak mengembalikan perubahan profil/ACL yang telah dibuat secara
sah. Jika rollback data diperlukan, gunakan Audit Log dan backup untuk mengenal
pasti nilai asal, kemudian lakukan perubahan terkawal dalam maintenance window.

## 8. Gate selesai

M3 hanya ditanda selesai selepas:

- contract automatik lulus;
- Save staging, Save manual dan external-name read-only lulus;
- role kekal sama sebelum/selepas category change;
- allow/deny/uplift dan duplicate rejection lulus;
- session revocation disahkan;
- audit trail dan correlation reference disahkan;
- tiada error baharu dalam Nginx/PHP log.

## 9. Keputusan manual UAT

Manual UAT telah selesai pada 14 Julai 2026 menggunakan akaun ujian
`S1TEST-20260714` sebagai target dan akaun administrator berasingan sebagai
operator.

| Ujian | Keputusan | Reference |
| --- | --- | --- |
| Save nama profil | PASS | `17d5d5b1360f2cb4` |
| Save kategori pertama | PASS | `5cc4955c3744629e` |
| Restore/save kategori | PASS | `92bb48db9b08f778` |
| Direct URL admin sebagai user biasa | PASS — `Forbidden` | N/A |
| Deny application access | PASS | `8fb0871b7273c8c6` |
| Uplift deny | PASS | `c0cac08dbf08af3c` |
| Add direct application access | PASS | `e487c0b0f4328e3c` |
| Session revocation selepas ACL | PASS | Direkod pada UI dan login semula berjaya |
| Correlated Audit Log | PASS | Semua reference ditemui |

Audit membuktikan profile save mengekalkan `role_preserved=0` bagi akaun ujian.
Semakan SELECT-only selepas UAT mengesahkan:

- `S1TEST-20260714`: `u_type=0`, category `3`, active;
- aplikasi `BTOG4WZNQP`: direct allow aktif dan tiada deny;
- aplikasi `ROSNJ0FR3D`: category allow aktif dan deny telah di-uplift.

Direct allow `BTOG4WZNQP` ialah perubahan data UAT yang masih aktif. Ujian login
ke consumer mengesahkan pengguna tetap ditolak kerana akaun tersebut tidak
mempunyai authorization dalaman pada sistem consumer. Ini ialah keputusan yang
betul: ACL OneID membenarkan perjalanan SSO tetapi tidak memintas authorization
aplikasi sasaran. Rekod direct allow dikekalkan sebagai data ujian yang telah
disahkan.

## 10. Pemerhatian privilege legacy sebelum M3

Audit Log turut menunjukkan dua action legacy `Grant Admin To` sebelum M3
diaktifkan. Semakan SELECT-only mengesahkan kedua-dua akaun berikut kini
`u_type=1`, category `9`, active:

- `820705025923`;
- `790612146270`.

Akaun `790612146270` telah disahkan oleh pemilik sistem sebagai administrator
yang sah. Tiada pembetulan data diperlukan. M3 telah membuang mutator
category/role legacy, maka perubahan kategori selepas M3 tidak lagi boleh grant
atau revoke administrator secara tersirat.

## 11. Penutupan M3

Baki gate data dan privilege telah diterima secara rasmi oleh pemilik sistem:

- privilege administrator `790612146270`: **SAH**;
- direct allow `BTOG4WZNQP` untuk `S1TEST-20260714`: **DIKEKALKAN UNTUK UJIAN**;
- authorization dalaman consumer: **PASS — akses tanpa hak ditolak**.

M3 kini bersedia untuk checkpoint Git.
