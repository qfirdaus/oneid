# OneID v2.9.0 — Sync Preview Fail-Closed Ordering

**Tarikh:** 09 Ogos 2026  
**Skop:** Administrator read-only synchronization preview

## Outcome

Endpoint Summary kini menilai `ODL_SHADOW_PREVIEW_ENABLED` sebelum membina
konfigurasi atau dependency sambungan ODL. Environment yang mematikan preview
menerima `ODL_SHADOW_PREVIEW_DISABLED` secara tepat tanpa memerlukan ODL secret
tersedia.

Perubahan ini tidak mengaktifkan sebarang Apply. Respons gagal kekal
`can_apply=false` dan `mutation_statements=0`.

## Verification

- `php tools/odl_f6_shadow_contract.php` — 10 checks, 0 failures.
- `php tools/s2_sync_preview_contract.php` — 28 checks, 0 failures.
- Ujian browser staging — Summary, Staff, Undergraduate dan ODL Review lulus.
- Semua Apply, Pilot, Full, Operational Apply dan cron production mesti kekal
  disabled sehingga clearance berasingan diberikan.
