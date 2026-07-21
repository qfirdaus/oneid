# F7.1 Schema dan Encryption Foundation

**Change ID:** `ONEID-F7-2FA-20260720-01`  
**Tarikh mula:** 20 Julai 2026  
**Status:** APPLIED / VERIFIED / ACCEPTED — FEATURE OFF  
**Feature state:** `admin_2fa_enabled=0` selepas migration  
**Runtime enforcement:** NONE

## 1. Skop

F7.1 menyediakan migration dan crypto primitive sahaja. Ia belum membina OTP,
TOTP enrollment, challenge endpoint, grant authorization, UI atau admin guard.
Oleh itu migration tidak boleh dianggap sebagai 2FA aktif.

Foundation terdiri daripada:

- `sys_config.admin_2fa_enabled`, wajib default `0`;
- `admin_step_up_challenges` untuk challenge yang diikat kepada admin, session,
  browser dan purpose;
- `admin_step_up_grants` untuk authorization grant server-side;
- `admin_mfa_factors` untuk ciphertext TOTP, nonce dan key version; dan
- `admin_mfa_preferences` untuk pilihan default per-admin yang bukan grant.

## 2. Encryption boundary

`TotpKeyring` memuatkan versioned 32-byte libsodium key daripada fail luar
repository/database. Default path operasi ialah:

```text
/etc/oneid/keys/admin-totp-keyring.php
```

Environment `ONEID_TOTP_KEYRING_FILE` boleh menunjuk kepada path lain bagi test
atau deployment terkawal. Keyring ditolak jika tiada, format salah, key bukan
32-byte, active version hilang, mempunyai sebarang akses `other`, atau boleh
ditulis/dilaksanakan oleh group.

Untuk UAT, `/etc/oneid` tidak writable kepada deployment owner. Keyring sebenar
telah dicipta pada `/home/iqs/.config/oneid/keys/admin-totp-keyring.php`, di luar
repo/web root, kerana PHP-FPM berjalan sebagai `iqs`. Directory menggunakan
`0700` dan fail `0600`. Deployment F7.2/F7.3 kelak mesti menetapkan
`ONEID_TOTP_KEYRING_FILE` kepada path ini bagi PHP-FPM tanpa menyalin key ke
runtime store atau repository.

`TotpSecretCipher` menggunakan libsodium `secretbox`, random 24-byte nonce dan
key version aktif. Decryption memerlukan key version rekod; ciphertext yang
diubah atau version tidak dikenali ditolak. Plaintext, key dan provisioning URI
tidak boleh ditulis ke log.

## 3. Migration gate

Semakan read-only:

```bash
php tools/f7_1_schema_migrate.php --check
```

`--apply` memerlukan semua perkara berikut:

- `ONEID_F7_CHANGE_ID=ONEID-F7-2FA-20260720-01`;
- `ONEID_F7_BACKUP_EVIDENCE` menunjuk kepada evidence fresh backup yang boleh
  dibaca;
- keyring sah tersedia;
- tiada partial F7.1 schema; dan
- owner merekod `GO F7.1 APPLY` selepas backup/restore rehearsal.

Arahan apply tidak direkod sebagai arahan siap-jalan sehingga gate tersebut
ditutup. Migration mesti menghasilkan setting `0`; runner gagal jika flag hidup.

## 4. Rollback

Rollback SQL berada dalam
`docs/migrations/20260720_f7_1_admin_step_up_foundation_down.sql`. Ia membuang
grant dan challenge dahulu, diikuti preference, factor serta setting. Down
migration adalah destructive dan hanya boleh digunakan dalam maintenance
window berdasarkan fresh backup serta reconciliation.

Feature flag `OFF` menghentikan activation tetapi tidak membuang data yang
sudah ditulis. Selepas F7.2 dan seterusnya, rollback schema memerlukan export dan
reconciliation factor/challenge/grant sebelum table dibuang.

## 5. Acceptance F7.1

- Forward/down SQL tersedia.
- Setting mempunyai default `0` dan CHECK constraint.
- Purpose/factor/status mempunyai database constraint.
- Foreign key mengikat semua pemilik rekod kepada canonical `user_tbl.u_id`.
- Preference per-admin dipisahkan daripada factor dan grant.
- Ciphertext, nonce dan key version disimpan; plaintext secret tiada dalam schema.
- Keyring permissions, format, version, round-trip dan tamper rejection diuji.
- Apply runner fail closed tanpa Change ID, backup evidence atau keyring.
- Selepas apply, compatibility behavior lama kekal kerana flag `OFF` dan guard
  belum membaca setting.

## 6. Evidence live apply

Owner menggantikan maintenance window dan mengarahkan execution segera pada
20 Julai 2026. Baseline mengesahkan 0 table dan tiada flag F7.1 sebelum mutation.

Fresh backup dan isolated restore:

```text
evidence: storage/backups/S4D-20260720-160133/EVIDENCE.txt
bytes: 81881933
sha256: 4aa7f5048d0f960b469c39466c82b040e4b5f723b40e9ae806aeacad1b30ae4e
tables reconciled: 19
row-count digest: 29efa6c57af7f1002b80a8da28041fa0281a5c4409983f05be13865b8771b04d
restore target dropped: yes
```

Live verification selepas apply:

```text
F7.1 tables: 4/4
foreign keys: 4
CHECK constraints: 9
admin_2fa_enabled: 0
rows across new stores: 0
```

Foundation/crypto contract lulus 14/14. Regression SC0, SC3, SC6 dan runtime
configuration audit turut lulus. Tiada enrollment, challenge atau grant dicipta.

## 7. Keputusan semasa

F7.1 diterima sebagai `APPLIED / VERIFIED / ACCEPTED`. Feature kekal `OFF` dan
guard runtime belum menggunakan schema baharu, maka behavior admin lama kekal.
F7.2 belum bermula.
