# Release OneID v2.0.3 — Profile, Category dan ACL Hardening

**Tarikh:** 14 Julai 2026  
**Versi:** 2.0.3  
**Jenis:** Security hardening, user-management policy dan patch UI modal admin

## Ringkasan

v2.0.3 merekod pelaksanaan M3 selepas checkpoint v2.0.2. Release ini
menjadikan Profile Save dan ACL khusus pengguna sebagai workflow server-side
yang tervalidasi, atomic dan boleh diaudit. Ia tidak mengaktifkan External Sync
Apply S4E dan tidak mengubah contract SSO consumer.

## Perubahan utama

- Butang Save Profile mempunyai handler, confirmation dan correlated response.
- Edit dropdown kategori hanya staged pada UI sehingga Save ditekan.
- Nama manual boleh diedit; nama external-managed read-only dan menggunakan
  Safe Resync M1.
- Category tidak lagi mengubah `u_type`; hardcoded category ID `9` dan mutator
  category/role legacy dibuang.
- Kategori mesti wujud serta aktif sebelum disimpan.
- Profile/category save menggunakan row lock, transaction, mandatory audit dan
  rollback.
- ACL Allow, Deny dan Uplift mengesahkan user/app, effective state, duplicate
  dan ownership blacklist record.
- Perubahan kategori dan ACL membatalkan sesi aktif pengguna.
- Nama aplikasi di-encode sebelum dimasukkan ke DOM.
- UI memaparkan policy/transport error bersama code dan correlation reference.

## Manual UAT

UAT menggunakan `S1TEST-20260714` telah lulus:

- Save nama dan kategori;
- category staging tanpa auto-mutation;
- role user kekal `u_type=0`;
- direct route admin menghasilkan `Forbidden`;
- Allow, Deny dan Uplift ACL;
- session revocation;
- correlated Audit Log;
- direct allow OneID tidak memintas authorization dalaman consumer.

Reference lengkap dan keputusan data akhir direkod dalam
`docs/M3_PROFILE_SAVE_CATEGORY_POLICY_DAN_ACL_HARDENING.md`.

## Verification automatik

```bash
php tools/m3_profile_acl_contract.php
php tools/release_metadata_contract.php
php tools/m1_safe_user_resync_contract.php
php tools/m2_user_security_actions_contract.php
php tools/s4d_pre_pilot_contract.php
```

## Rollback

Tiada schema migration dalam v2.0.3. Gunakan:

```bash
git revert <commit-v2.0.3>
```

Revert code tidak mengembalikan mutation profil/ACL UAT. Sebarang rollback
data perlu menggunakan audit reference dan backup dalam maintenance window.
