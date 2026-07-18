# AS2 Revoked Token dan Baki Active Session Audit

**Tarikh:** 18-19 Julai 2026
**Status:** IMPLEMENTED / CONTRACT PASS / OWNER-OBSERVED BROWSER UAT PASS

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
| AS2-01 | UAT dua browser/dua PC bagi `multi_session=0` | **PASS** — login baharu menamatkan sesi lama dan memerlukan login semula |
| AS2-02 | UAT dua browser/dua PC bagi `multi_session=1` | **PASS** — kedua-dua sesi dibenarkan dan kekal berfungsi |
| AS2-02B | UAT revoked token tanpa perubahan polisi global | **PASS** — revocation dikesan dan browser lama memerlukan login semula |
| AS2-03 | Hard cap apabila multiple session dibenarkan | Pending keputusan owner sama ada 5, 10 atau visibility-only |
| AS2-04 | Controlled revoke satu/semua sesi oleh admin | Deferred sehingga Admin Step-Up 2FA, preview, confirmation dan self-lockout protection |
| AS2-05 | Housekeeping controlled Apply | Tool tersedia tetapi Apply, rehearsal dan scheduler belum diluluskan |
| AS2-06 | Retention token tidak aktif 90 hari | Pending schema revoked timestamp, backup, storage dan audit decision |
| AS2-07 | Monitoring revoked-token 401 dan lonjakan relogin | Pending monitoring owner, threshold dan alert channel |
| AS2-08 | Tamatkan compatibility refresh 60 minit | Pending consumer inventory, migration dan controlled rollout |

Cron External Sync kekal task berasingan dan tidak diaktifkan oleh AS2.

## Urutan Pelaksanaan Baki

1. Aktifkan visibility/monitoring AS2-07 sebelum menetapkan sebarang hard cap.
2. Owner putuskan AS2-03 berdasarkan data sesi berlebihan sebenar.
3. Lengkapkan Admin Step-Up sebelum AS2-04 Controlled Revoke dibina.
4. Jalankan rehearsal dan observation housekeeping AS2-05 secara berasingan.
5. Tambah revoked timestamp sebelum AS2-06 retention purge dipertimbangkan.
6. Tutup AS2-08 hanya selepas semua SSO consumer keluar daripada compatibility
   refresh.

## Keputusan UAT Owner

Pada 19 Julai 2026, owner melaporkan keputusan berikut di staging dengan
`token_timeout=0.5`:

| Polisi / Senario | Keputusan |
|---|---|
| `multi_session=1`: multiple session dibenarkan | PASS |
| Revoked-token AS2 tanpa menukar polisi global | PASS |
| `multi_session=0`: login baharu menamatkan sesi lama | PASS |

UAT mengesahkan pengguna perlu login semula selepas sesi/token lama ditamatkan.
Tiada notification khusus dipaparkan sebelum atau selepas force logout. Owner
menerima behavior keselamatan semasa dan menangguhkan notification sebagai AS3.

## AS3 Notification — Deferred by Owner

Task berikut direkod tetapi tidak dilaksanakan dalam release ini:

1. AS3-01 modal apabila AJAX menerima revoked-token HTTP 401;
2. AS3-02 notis generik pada login selepas direct page access ditolak;
3. AS3-03 amaran sebelum idle timeout;
4. AS3-04 amaran sebelum absolute timeout tanpa pilihan extend;
5. AS3-05 `revoked_reason` untuk mesej yang lebih khusus; dan
6. AS3-06 UAT notification pada Chrome, Firefox, dua PC, background tab serta
   borang belum disimpan.

Ketiadaan notification ialah baki UX dan bukan kegagalan enforcement AS2.

## Verification

```bash
php tools/as2_revoked_token_contract.php
php tools/as0_active_sessions_contract.php
php tools/as1_session_policy_contract.php
```

Contract dan browser UAT AS2 telah lulus. Baki task dalam dokumen ini memerlukan
change scope serta owner gate berasingan.
