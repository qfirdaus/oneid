# S4D — Verifikasi Operasi Sebelum S4E

Tarikh: 14 Julai 2026  
Release diuji: `68f10ff` (`feat(sync): wire dormant pre-pilot readiness`)  
Status: **VERIFIKASI OPERASI LULUS — GATE DBA DAN PRE-PILOT LAIN MASIH NO-GO**

## Keputusan

| Pemeriksaan | Keputusan | Evidence |
| --- | --- | --- |
| Regression S1–S4D | Lulus | S1 39/39, S2 29/29, S3 26/26, S4A 16/16, S4B 19/19, S4C 15/15, S4D 21/21 |
| Public-root smoke | Lulus | 10/10 pada `https://oneid.local` |
| External staff SELECT | Lulus | `ehrmdb.dbo.SSO_Staf_Aktif`, 1,062 baris; tiada DML dihantar |
| External student SELECT | Lulus | `asisdb..v210_sso_student_aktif`, 5,423 baris; tiada DML dihantar |
| Jumlah external snapshot | Lulus | 6,485 baris, sama dengan preview S2 yang diterima |
| External credential benar-benar SELECT-only | Belum terbukti sepenuhnya | Runtime login tidak dibenarkan membaca metadata grant; DBA perlu beri grant/role evidence |
| Full OneID backup | Lulus | 73,881,422 bait; SHA-256 `3f9efeae73079169cc5df825797badbeab79572b7d6007f99ed7b9dba7ec4794` |
| Restore rehearsal | Lulus | 15 jadual, exact row-count digest sama, target rehearsal telah dibuang |
| Admin login → fresh preview → logout | Lulus | Disahkan change owner/tester pada 14 Julai 2026; tiada Apply dijalankan |
| SSO consumer `iqs-framework.local` | Lulus | Login, return ke consumer, tiada redirect loop/5xx dan logout disahkan pada 14 Julai 2026 |

## Backup dan Restore

Evidence private berada di:

```text
/var/www/app/oneid-uat/storage/backups/S4D-20260714-160232/
```

Direktori ini berada di luar `public/`, diabaikan Git dan menggunakan permission
private. Backup dibuat menggunakan consistent snapshot. Ia dipulihkan ke
database sementara `oneiddb_s4d_20260714_160232_8dd0`, dibandingkan dengan
database sumber menggunakan kiraan tepat bagi setiap jadual, kemudian database
sementara itu dibuang. `oneiddb` sumber tidak diubah.

Untuk mengulangi rehearsal pada OneID DEV yang sama:

```bash
cd /var/www/app/oneid-uat
php tools/s4d_backup_restore_rehearsal.php
```

Tool akan berhenti jika database bukan `oneiddb` atau hostname server tidak
mengandungi `DEV`.

## External DB Read Evidence

Arahan berikut hanya mengandungi dua `SELECT COUNT(*)`. Ia tidak mempunyai
`INSERT`, `UPDATE`, `DELETE`, DDL atau transaction:

```bash
cd /var/www/app/oneid-uat
php tools/s4d_external_readonly_evidence.php
```

Keputusan ini membuktikan aplikasi hanya menghantar SELECT semasa pemeriksaan.
Ia tidak boleh membuktikan akaun tiada privilege tulis kerana metadata grant
tidak boleh dibaca. Gate DBA kekal pending sehingga DBA mengesahkan credential
staff dan student diberi hak SELECT sahaja.

## Ujian Admin dan Fresh Preview

Jalankan dalam terminal sendiri supaya password tidak masuk shell history,
log, dokumen atau chat:

```bash
cd /var/www/app/oneid-uat

read -rp "Test admin: " ONEID_R52_ADMIN_USERNAME
read -rsp "Admin password: " ONEID_R52_ADMIN_PASSWORD; echo
export ONEID_R52_ADMIN_USERNAME ONEID_R52_ADMIN_PASSWORD

php tools/r52_authenticated_logout.php \
  https://oneid.local admin --preview --insecure

unset ONEID_R52_ADMIN_USERNAME ONEID_R52_ADMIN_PASSWORD
```

Jangkaan:

- login, session rotation, dashboard admin dan logout semuanya `PASS`;
- `fresh external sync preview (no Apply)` ialah `PASS`;
- evidence memaparkan counts dan prefix plan hash sahaja;
- tool tidak mempunyai field Apply, tidak menerima approval ID dan tidak
  menghantar `admin_add_sync_user`.

## Ujian SSO Consumer

1. Logout daripada OneID dan `https://iqs-framework.local/`.
2. Buka `https://iqs-framework.local/` dalam private window.
3. Klik login OneID.
4. Pastikan redirect menuju `https://oneid.local/?site_id=...`.
5. Login dan pastikan kembali ke IQS Framework sebagai pengguna yang betul.
6. Logout consumer dan pastikan protected page tidak lagi boleh dicapai.
7. Semak Network: tiada redirect loop dan tiada response 5xx.

Jangan gunakan `eprestasi-uat` sebagai pilot ini kerana consumer tersebut pernah
menunjukkan redirect loop. Keputusan browser baharu untuk `iqs-framework.local`
telah diterima pada 14 Julai 2026 dan gate SSO ditanda lulus.

## Keputusan Gate

Backup, restore, public smoke, admin login/fresh preview/logout, SSO consumer
dan runtime SELECT evidence telah lulus. S4E masih **NO-GO** sehingga DBA
mengesahkan kedua-dua external credential memang SELECT-only dan baki gate
pre-pilot seperti scheduler, monitoring, maintenance window dan acceptance
diselesaikan.

Tiada Apply atau live sync dijalankan dalam verifikasi ini.
