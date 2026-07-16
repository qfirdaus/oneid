# SC0 — Baseline Admin SSO Configuration dan Pengesahan Owner

**Tarikh snapshot:** 16 Julai 2026, 09:59 +08:00  
**Skop:** Fasa 0 bagi `Administrator > SSO Configuration`  
**Status:** COMPLETE — TECHNICAL BASELINE AND OWNER POLICY CONFIRMED  
**Mutation production:** Tiada; pemeriksaan database dibuat secara read-only

## 1. Objektif dan Sempadan

Fasa ini menangkap behavior, schema, nilai, caller, consumer awal dan keputusan
owner yang diperlukan sebelum remediasi. Fasa ini tidak mengubah:

- nilai `sys_config`;
- schema database;
- protocol atau lifecycle token;
- session policy;
- OTP delivery;
- authorization admin; atau
- consumer SSO.

## 2. Live Database Baseline

Database UAT yang dikonfigurasi oleh runtime mengandungi satu row `sys_config`:

| Medan | Jenis | Null | Nilai snapshot |
|---|---|---|---:|
| `id` | `int`, auto increment, primary key | Tidak | 1 row wujud |
| `token_timeout` | `double` | Tidak | `0.5` jam |
| `multi_session` | `int` | Tidak | `1` |
| `email_OTP` | `int` | Tidak | `1` |

Pemerhatian:

- jadual telah mempunyai primary key `id`;
- hanya satu row wujud ketika snapshot;
- tiada singleton constraint yang membuktikan hanya satu row boleh wujud;
- query aplikasi tidak menggunakan `WHERE id = ...`; dan
- nilai semasa bermaksud token nominal 30 minit, multiple token dibenarkan dan
  e-mel OTP reset password diaktifkan.

Credential database dan runtime secret tidak disalin ke dokumen ini.

## 3. Source Baseline

| Fail | SHA-256 |
|---|---|
| `admin/dashboard.php` | `8265cf60a190c787b1720baf8dcf39f026f33d383b326e5cd147bd3a3b7509fb` |
| `lib/q_func.php` | `aab833babcd5c4efb56f7ab5a2fa6d82922b29419a7f59b53bced1b8850dcd85` |
| `lib/Database.php` | `2a9e9c70e0379714658a7eaaa2b60f89aee5f9ccc7f2c62f2083b93250957379` |
| `lib/request_security.php` | `a6d76ef897e66906ac8e15fd8d1fe78a2568cba3a058c644a3939b2d85244701` |
| `lib/session_security.php` | `4f6ae087d3f20d2e606b4444129c1b9f6f12c95d5d8b1e7e05ba50fdbeec3a7b` |
| `api.php` | `52829bc828ff1d75d9ce5ef71bf592373fdf5ce4000d0a52dbb74288a935b028` |

Hash ialah evidence snapshot, bukan mekanisme deployment atau integrity runtime.

## 4. Caller dan Enforcement Map

Caller map terperinci direkod dalam `SC0_SSO_CONFIGURATION_CALLER_MAP.tsv`.
Ringkasannya:

| Setting | Enforcement sebenar |
|---|---|
| `token_timeout` | API validation dan cleanup semasa active-session listing |
| `multi_session` | Login baharu sahaja; token lama dibatalkan jika nilai `0` |
| `email_OTP` | Penghantaran e-mel bagi Forgot Password sahaja |
| PHP idle timeout | 30 minit dalam `lib/session_security.php` |
| PHP absolute timeout | 8 jam dalam `lib/session_security.php` |

Tiada caller lain ditemui dalam source PHP/JavaScript bagi tiga medan tersebut
pada tarikh snapshot. Pemerhatian source tidak membuktikan tiada consumer luaran
yang bergantung kepada behavior API.

## 5. Consumer Inventory Baseline

Snapshot read-only `sp_list` mendapati:

- 35 aplikasi aktif;
- 27 rekod mempunyai `sp_sso_support = 0`;
- 8 rekod mempunyai `sp_sso_support = 1`; dan
- hash snapshot row aktif ialah
  `2c60829971663586263764e8779ee647c7cc1a9d29cf092749c9ad6031ccce80`.

Ini konsisten dengan inventori lama yang mempunyai 34 aplikasi pada 13 Julai
serta tambahan `BTOG4WZNQP` pada 14 Julai. Namun, flag `sp_sso_support` tidak
boleh dianggap bukti muktamad jenis integrasi. Registry masih belum lengkap
untuk owner, vendor, endpoint, callback, data minimum, credential, UAT dan
rollback contact.

Endpoint integrasi yang diketahui kekal:

| Endpoint | Fungsi |
|---|---|
| `api.php` | Validasi dan legacy refresh token SSO |
| `idms.php` | Integrasi data staf/jabatan |
| `skp_api.php` | Integrasi profil/sync pelajar |
| `public/lib/sso_IDP_index.php` | Compatibility wrapper legacy |
| `public/lib/sso_IDP_sub.php` | Compatibility redirect legacy |

Inventori aplikasi bukan semestinya inventori caller endpoint. Access log dan
pengesahan owner minimum 30 hari masih diperlukan sebelum sebarang legacy flow
ditamatkan.

## 6. Characterization Baseline

Contract `tools/sc0_sso_configuration_contract.php` mengunci pemerhatian berikut:

- UI masih menyediakan lapan nilai timeout yang diketahui;
- read dan update action diklasifikasikan admin-only;
- endpoint masih melalui CSRF/admin guard;
- database update masih menggunakan prepared binding tetapi tanpa `WHERE`;
- multiple-session enforcement berlaku pada login;
- OTP e-mel hanya digunakan dalam Forgot Password; dan
- PHP session timeout kekal 30 minit idle dan 8 jam absolute.

Contract ini sengaja mencirikan behavior legacy. Ia bukan kelulusan untuk
mengekalkan kelemahan tersebut. Apabila remediasi dilaksanakan, contract perlu
diganti bersama acceptance test fasa baharu.

## 7. Backup dan Rollback Readiness

Baseline source dan nilai konfigurasi telah direkod. Berdasarkan arahan owner
pada 16 Julai 2026, full database backup dan restore rehearsal dilaksanakan oleh
DBA dalam environment lain. Aktiviti tersebut tidak diperlukan dalam sistem
atau repo ini pada masa sekarang dan bukan gate penutupan Fasa 0 tempatan.

Evidence atau pelaksanaan backup hanya perlu diminta semula apabila owner
mengarahkan demikian atau apabila perubahan yang dicadangkan memerlukan
migration/database operation berisiko. Jika diaktifkan semula, minimum kawalan
operasi ialah:

1. tetapkan Change ID dan maintenance window;
2. pilih destinasi backup di luar public root;
3. ambil schema serta row `sys_config` menggunakan prosedur DBA yang diluluskan;
4. rekod checksum tanpa credential;
5. uji restore ke database terasing;
6. sahkan nilai tiga setting selepas restore; dan
7. rekod DBA, masa, keputusan serta rollback owner.

Rollback Fasa 0 hanya memerlukan pembuangan artifak dokumentasi/test kerana tiada
runtime mutation dibuat.

## 8. Owner Decision Gate

Semua keputusan direkod dalam `SC0_OWNER_DECISION_REGISTER.tsv`. Dua belas
keputusan polisi telah diluluskan oleh owner pada 16 Julai 2026. Kelulusan
polisi tidak bermaksud semua deliverable operasi atau implementasi telah siap.
Consumer registry, prosedur SMTP, Admin Step-Up dan change schedule masih perlu
dihasilkan dalam fasa pelaksanaan berkaitan.

Keputusan authorization menggunakan satu role admin sedia ada
`login_user_type = 1`. Tiada role `security-admin` akan diperkenalkan dalam skop
ini. Admin sedia ada boleh melihat dan mengubah konfigurasi; perubahan sensitif
akan dilindungi oleh server-enforced Admin Step-Up 2FA apabila fasa tersebut
dilaksanakan.

Fasa 1 yang membetulkan label dan operational feedback boleh dimulakan. Fasa
berikutnya masih tertakluk kepada acceptance test dan deliverable pelaksanaan
masing-masing.

## 9. Gate Fasa 0

| Gate | Status | Evidence / baki kerja |
|---|---|---|
| Schema dan row count disahkan | PASS | Read-only metadata query; satu row, primary key `id` |
| Nilai konfigurasi direkod | PASS | `0.5`, `1`, `1` pada masa snapshot |
| Caller map disediakan | PASS | `SC0_SSO_CONFIGURATION_CALLER_MAP.tsv` |
| Source hash direkod | PASS | Enam fail enforcement utama |
| Consumer snapshot dikemas kini | PASS WITH LIMITATION | 35 aktif; owner/protocol belum disahkan |
| Characterization contract | PASS | Static contract, tiada database mutation |
| Backup/restore tempatan | NOT REQUIRED | Owner menetapkan DBA melaksanakannya dalam environment lain; buka semula hanya atas arahan atau keperluan perubahan berisiko |
| Owner decisions lengkap | PASS | 12 keputusan polisi diluluskan pada 16 Julai 2026 |

## 10. Keputusan Fasa

Baseline teknikal Fasa 0 telah ditangkap tanpa mengubah runtime. Backup/restore
tempatan bukan gate dan semua keputusan polisi telah diluluskan. Deliverable
pelaksanaan yang disebut dalam keputusan akan dijejak dalam fasa masing-masing.
Status yang tepat ialah:

```text
TECHNICAL BASELINE COMPLETE
OWNER POLICY CONFIRMED
PHASE 0 COMPLETE
NO RUNTIME MUTATION
```
