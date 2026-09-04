# MD9 — Controlled UAT Schema Installation

**Tarikh:** 4 September 2026  
**Rujukan pembangunan:** `ONEID-MAINTENANCE-DEVELOPER-20260904-01`  
**Fasa:** 9 — pemasangan schema dormant pada UAT  
**Status:** disahkan owner; Fasa 10 dibenarkan

## Boundary

Fasa ini hanya memasang dua jadual additive. Feature mesti kekal OFF sepanjang
operasi. Ia tidak memberi grant, mengaktifkan maintenance atau membenarkan
developer login.

## Preflight

```bash
php tools/maintenance_developer_phase9_preflight.php
```

Migration hanya boleh dijalankan apabila keputusan `GO`. Nilai private runtime
yang diperlukan ialah approval schema `true`, reference change, reference
backup yang sah, serta window ISO-8601 aktif tidak melebihi dua jam.

Arahan apply selepas semua bukti tersedia:

```bash
php tools/maintenance_developer_schema_migrate.php --apply
php tools/maintenance_developer_uat_readiness.php
```

Acceptance memerlukan `tables=2`, enam foreign key, sepuluh check constraint,
`user_tbl_unchanged=yes`, `activation_ready=yes`, dan feature masih `false`.
Activation feature ialah fasa serta approval berasingan.

## Keputusan pemasangan

Migration `ONEID-MD9-UAT-20260904-01` dijalankan dalam window yang diluluskan
pada 4 September 2026. Reconciliation menghasilkan dua jadual, enam foreign
key dan sepuluh check constraint. Struktur serta kiraan `user_tbl` tidak
berubah. Approval schema ditutup semula selepas apply dan feature kekal OFF.

## Evidence backup Fasa 9

- Reference: `ONEID-MD9-BACKUP-20260904-144711`
- Saiz: `87,540,565` bait
- SHA-256: `83c3227c1273ad5eeba7d4719267fee390fecc5abda92c560a1f5bc44b945f5b`
- Jadual source/restore: `50/50`
- Exact row-count reconciliation: `pass`
- Restore target dibuang: `yes`
- Source mutation: `0`
- Evidence: `storage/backups/ONEID-MD9-BACKUP-20260904-144711/EVIDENCE.txt`
