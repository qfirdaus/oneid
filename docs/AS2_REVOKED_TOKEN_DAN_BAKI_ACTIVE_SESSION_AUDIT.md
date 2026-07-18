# AS2 Revoked Token dan Baki Active Session Audit

**Tarikh:** 18 Julai 2026
**Status:** IMPLEMENTED / CONTRACT PASS / BROWSER UAT PENDING

## Isu Ditutup

Apabila `multi_session=0`, login Browser B merevoke token Browser A. Sebelum
AS2, PHP session Browser A boleh kekal dan heartbeat yang gagal mengemas kini
token masih boleh menerima HTTP 200.

AS2 mengikat setiap action terlindung kepada user PHP session dan cookie token
aktif dalam database. Token yang hilang atau `status=0` menyebabkan:

- AJAX menerima HTTP 401;
- cookie SSO dipadam;
- state authenticated PHP dibersihkan dan session ID dirotasi; dan
- protected page redirect ke login.

Ini juga melindungi page admin, report user list dan dashboard user. Public
login/reset action kekal boleh dicapai tanpa active token.

## Baki Task Audit

| ID | Task | Status / Gate |
|---|---|---|
| AS2-01 | UAT dua browser/dua PC bagi `multi_session=0` | Pending test account dan owner observation |
| AS2-02 | UAT dua browser/dua PC bagi `multi_session=1` | Pending; kedua-dua sesi mesti kekal aktif |
| AS2-03 | Hard cap apabila multiple session dibenarkan | Pending keputusan owner sama ada 5, 10 atau visibility-only |
| AS2-04 | Controlled revoke satu/semua sesi oleh admin | Deferred sehingga Admin Step-Up 2FA, preview, confirmation dan self-lockout protection |
| AS2-05 | Housekeeping controlled Apply | Tool tersedia tetapi Apply, rehearsal dan scheduler belum diluluskan |
| AS2-06 | Retention token tidak aktif 90 hari | Pending schema revoked timestamp, backup, storage dan audit decision |
| AS2-07 | Monitoring revoked-token 401 dan lonjakan relogin | Pending monitoring owner, threshold dan alert channel |
| AS2-08 | Tamatkan compatibility refresh 60 minit | Pending consumer inventory, migration dan controlled rollout |

Cron External Sync kekal task berasingan dan tidak diaktifkan oleh AS2.

## Urutan Pelaksanaan Baki

1. Jalankan AS2-01 pada akaun ujian dengan `multi_session=0`; sahkan Browser A
   menerima 401 atau kembali ke login selepas Browser B login.
2. Pulihkan polisi dan jalankan AS2-02 dengan `multi_session=1`; sahkan kedua-dua
   token kekal aktif dan logout satu browser tidak menutup browser lain.
3. Aktifkan visibility/monitoring AS2-07 sebelum menetapkan sebarang hard cap.
4. Owner putuskan AS2-03 berdasarkan data sesi berlebihan sebenar.
5. Lengkapkan Admin Step-Up sebelum AS2-04 Controlled Revoke dibina.
6. Jalankan rehearsal dan observation housekeeping AS2-05 secara berasingan.
7. Tambah revoked timestamp sebelum AS2-06 retention purge dipertimbangkan.
8. Tutup AS2-08 hanya selepas semua SSO consumer keluar daripada compatibility
   refresh.

## Verification

```bash
php tools/as2_revoked_token_contract.php
php tools/as0_active_sessions_contract.php
php tools/as1_session_policy_contract.php
```

UAT AS2-01 perlu mengesahkan Browser A menerima 401 atau kembali ke login tidak
lebih lewat daripada heartbeat seterusnya selepas Browser B berjaya login.
