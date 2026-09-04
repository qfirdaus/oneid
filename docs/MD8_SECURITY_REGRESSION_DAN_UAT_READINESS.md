# MD8 — Security Regression dan UAT Readiness

**Tarikh:** 4 September 2026  
**Rujukan:** `ONEID-MAINTENANCE-DEVELOPER-20260904-01`  
**Fasa:** 8 — penutupan pembangunan sebelum activation  
**Status:** disahkan owner; Fasa 9 dibenarkan

## Skop dan keputusan keselamatan

Fasa ini tidak memasang schema live dan tidak menghidupkan feature. Rehearsal
hujung-ke-hujung menggunakan database sementara sahaja dan meliputi grant,
binding grant/version pada sesi, token aktif, version mismatch, revoke segera,
audit lifecycle dan pengesahan bahawa `u_type` kekal `0`.

Migration runner kini turut mengunci sasaran kepada database exact `oneiddb`,
mengambil fingerprint struktur serta kiraan `user_tbl`, memeriksa enam foreign
key dan sepuluh check constraint, dan mencuba down migration jika DDL gagal.

## Cara verifikasi

```bash
php tools/maintenance_developer_phase8_security_suite.php
php tools/maintenance_developer_uat_readiness.php
```

Readiness membezakan `code_prerequisites` dengan `activation_ready`. Dalam
keadaan dormant semasa, keputusan dijangka `code_prerequisites=ready` tetapi
`activation_ready=no` kerana dua jadual maintenance belum dipasang. Ini bukan
kegagalan pembangunan dan bukan approval activation.

## Acceptance gate

- [x] Semua kontrak Fasa 1–7 dan maintenance asal lulus.
- [x] Rehearsal grant → session → revoke lulus dalam database sementara.
- [x] Token revoke dan version mismatch fail-closed.
- [x] Developer kekal pengguna biasa dan lifecycle mempunyai audit.
- [x] Database sementara dibuang; live database snapshot tidak berubah.
- [x] UAT readiness boleh diperiksa secara read-only tanpa PII atau secret.
- [x] Migration runner mengesahkan exact target dan post-migration integrity.
- [x] Feature flag kekal OFF dan schema live tidak dipasang.

Selepas owner mengesahkan Fasa 8, pemasangan schema dan activation masih perlu
approval operasi berasingan mengikut runbook.
