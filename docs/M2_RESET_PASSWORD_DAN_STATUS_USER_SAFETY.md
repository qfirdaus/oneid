# M2 — Reset Password dan Status User Safety

Tarikh: 14 Julai 2026
Owner perubahan: Pemilik sistem OneID
Owner rollback: Pemilik sistem OneID
Status: **SELESAI — AUTOMATED DAN MANUAL UAT VERIFIED**

## 1. Objektif

M2 mengeraskan tiga action dalam modal User Info:

- Force Reset Password;
- Remove User atau deactivate;
- Reactivate User.

M2 tidak mengubah profile Save, category/role atau ACL aplikasi. Item tersebut
kekal skop M3.

## 2. Finding baseline yang ditutup

Sebelum M2, ketiga-tiga action menjalankan beberapa mutation berasingan tanpa
transaction. Remove dan Reactivate sentiasa memulangkan success walaupun row
tidak berubah. Force Reset tidak mempunyai audit khusus, row-count verification
atau invalidation OTP aktif.

M2 menggantikan handler tersebut dengan `UserSecurityActionService`.

## 3. Workflow baharu

```text
Admin confirmation
  → admin + CSRF guard
  → validate target/admin ID
  → block reset/deactivate diri sendiri
  → begin transaction
  → SELECT target LIMIT 1 FOR UPDATE
  → validate current state
  → password atau status mutation, wajib tepat satu row
  → revoke seluruh token
  → invalidate seluruh OTP aktif
  → tulis audit dengan correlation ID
  → commit
  ↘ sebarang failure: rollback keseluruhan
```

### Force Reset Password

- hanya untuk akaun aktif;
- password lama diganti dengan secret rawak 256-bit yang tidak dipaparkan atau
  dilog;
- hash disimpan melalui `password_hash()`;
- `password_change_required=1`;
- semua token dan OTP aktif dibatalkan;
- pengguna perlu menggunakan Forgot Password untuk menetapkan password sendiri;
- audit menggunakan event sedia ada `10` (`SSO_RESET_PWD`) dengan admin, target,
  action dan correlation ID.

### Remove User

- menolak akaun yang sudah inactive;
- admin tidak boleh deactivate akaun sendiri;
- `avail_status` mesti benar-benar berubah daripada `1` kepada `0`;
- token dan OTP dibatalkan;
- audit event `25` diwajibkan dalam transaction yang sama.

### Reactivate User

- menolak akaun yang sudah active;
- `avail_status` mesti benar-benar berubah daripada `0` kepada `1`;
- token dan OTP kekal dibatalkan supaya session lama tidak hidup semula;
- audit event `26` diwajibkan dalam transaction yang sama.

## 4. Failure contract

UI hanya menganggap `status=1` sebagai berjaya. Failure memulangkan safe code
dan correlation ID, antaranya:

- `M2_SELF_ACTION_FORBIDDEN`;
- `M2_USER_NOT_FOUND`;
- `M2_USER_INACTIVE`;
- `M2_ALREADY_INACTIVE` / `M2_ALREADY_ACTIVE`;
- `M2_PASSWORD_NOT_RESET`;
- `M2_STATUS_NOT_CHANGED`;
- `M2_AUDIT_NOT_WRITTEN`;
- `M2_OPERATION_FAILED`.

Exception database tidak dipaparkan kepada browser. Duplicate submission turut
dihadkan dengan disabling button sepanjang request.

## 5. Artefak dan verification

- `app/User/UserSecurityActionException.php`;
- `app/User/UserSecurityActionService.php`;
- `tests/characterization/m2_user_security_actions.php`;
- `tools/m2_user_security_actions_contract.php`;
- alias `check:user-security-actions` dalam `package.json`.

Arahan:

```bash
php tests/characterization/m2_user_security_actions.php
php tools/m2_user_security_actions_contract.php
```

Fixture menggunakan fake database operation dan tidak membuka database,
network atau session sebenar.

## 6. Manual UAT gate

Gunakan akaun ujian bukan akaun admin semasa.

1. Force Reset akaun ujian aktif.
2. Pastikan success mempunyai reference dan login password lama gagal.
3. Pastikan Forgot Password/OTP boleh menetapkan password baharu.
4. Pastikan session lama akaun ujian tidak lagi sah.
5. Remove akaun ujian dan pastikan login ditolak.
6. Ulang Remove; ia mesti memulangkan `M2_ALREADY_INACTIVE`.
7. Reactivate dan pastikan pengguna masih perlu login semula.
8. Ulang Reactivate; ia mesti memulangkan `M2_ALREADY_ACTIVE`.
9. Semak Audit Log bagi event reset, deactivate dan reactivate serta correlation.
10. Cuba Reset/Remove akaun admin sendiri; ia mesti ditolak.

Jangan gunakan akaun production sebenar untuk gate pertama.

## 7. Rollback

M2 tidak mempunyai schema migration. Sebelum commit:

```bash
git restore admin/dashboard.php lib/Database.php lib/q_func.php package.json
git clean -f app/User/UserSecurityActionException.php \
  app/User/UserSecurityActionService.php \
  tests/characterization/m2_user_security_actions.php \
  tools/m2_user_security_actions_contract.php \
  docs/M2_RESET_PASSWORD_DAN_STATUS_USER_SAFETY.md
```

Selepas commit, gunakan `git revert <commit-M2>`. Jika action telah berjaya
sebelum rollback kod, jangan pulihkan hash password atau session lama. Status
akaun hanya boleh dibetulkan melalui action pentadbiran baharu yang diaudit.

## 8. Keputusan

M2 menutup finding M0-F07, M0-F08 dan M0-F09 pada peringkat kod, automated
contract dan manual UAT. Reset Password, Remove serta Reactivate telah
disahkan bersama audit correlation masing-masing.

### 8.1 Bukti manual UAT 14 Julai 2026

Force Reset bagi akaun ujian `S1TEST-20260714` lulus dengan correlation
`02fd104cb7c0116a`. Semakan read-only terhadap audit database mengesahkan:

- `SSO_RESET_PWD` oleh admin pada `20:54:17` dengan correlation yang sama;
- password lama ditolak selepas force reset;
- OTP Forgot Password dijana;
- password baharu berjaya ditetapkan;
- login menggunakan password baharu berjaya.

Remove dan Reactivate kemudiannya diulang pada akaun ujian yang sama. Semakan
read-only terhadap `syslog` mengesahkan:

- `21:04:24` — event `25 ADMIN_DEACTIVATE_USER`, correlation
  `11360178ad06660a`;
- login semasa inactive ditolak seperti dijangka;
- `21:05:59` — event `26 ADMIN_REACTIVATE_USER`, correlation
  `491e238a10c946df`;
- login baharu selepas reactivate berjaya.

Ketiga-tiga workflow M2 kini melepasi manual UAT dan audit gate. Akaun ujian
berakhir dalam status aktif.
