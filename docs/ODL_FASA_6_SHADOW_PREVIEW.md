# ODL Fasa 6 — Shadow Preview

**Tarikh:** 23 Julai 2026

**Status:** `WSL PASSED / STAGING VERIFICATION PENDING`

## Boundary

- Endpoint: `admin_preview_odl_shadow`.
- UI: `ODL Shadow Preview (read-only)`.
- Feature gate private default ialah `false`.
- Service tidak bergantung pada approval service, coordinator atau writer.
- Response hanya aggregate counts, blocking codes dan preview digest.
- `can_apply=false` dan `mutation_statements=0` adalah tetap.
- Legacy Preview/Apply tidak menerima row ODL daripada laluan ini.
- Tiada scheduler diwujudkan.

## Private runtime

WSL menggunakan:

```php
'ONEID_ODL_SHADOW_PREVIEW_ENABLED' => 'true',
'ONEID_ODL_SHADOW_ODL_BASELINE_ROWS' => '53',
'ONEID_ODL_SHADOW_UG_BASELINE_ROWS' => '5452',
```

Nilai staging mesti dimasukkan terus ke `.private/runtime.php` staging selepas
pull. Gate hanya menerima literal `true` atau `false`.

## Live WSL result

```text
risk_level=normal
STUDENT_UG rows=5452
STUDENT_ODL_PG rows=53
KEEP_MEMBERSHIP_ACTIVE=5423
KEEP_ACCOUNT_ACTIVE=5423
CANDIDATE_NEW=53
blocking_codes=[]
can_apply=false
mutation_statements=0
preview_digest=1ea0244ebcc482003b7bc482240ca692f2dcf077d24c04e8407aa68edbe0fed3
```

Tiada raw action array, nama, IC, nombor matrik atau e-mel dihantar kepada
browser.

## Tests

```text
Fasa 6 characterization: 12/12
Fasa 6 contract: 8/8
Dashboard characterization: 21/21
Legacy planner purity: 17/17
Legacy orchestrator parity: 17/17
Legacy dry-run zero mutation: 25/25
Production adapter contracts: 32/32
Production orchestrator parity: 18/18
```

Baseline checksum `lib/q_func.php` dikemas kini kepada:

```text
308b1e581eb9f876fc9cd5a2e2562dcf1b1faf521725843d88702c2bfbbe6257
```

Perubahan checksum adalah disebabkan endpoint Shadow Preview read-only yang
baharu. `lib/Database.php` dan `lib/sync_user_runner.php` tidak berubah.

## Staging verification

Selepas pull dan private runtime dikemas kini:

```bash
php tests/characterization/odl_f6_shadow_preview.php
php tools/odl_f6_shadow_contract.php
php tools/odl_f6_shadow_preview.php
```

Live staging result mesti kekal aggregate-only, `can_apply=false` dan
`mutation_statements=0`. Perbezaan row count, action count, blocking code atau
digest perlu direview sebelum Fasa 6 ditutup.

Rollback ialah menetapkan:

```php
'ONEID_ODL_SHADOW_PREVIEW_ENABLED' => 'false',
```

**Change ID:** `ONEID-ODL-F6-20260723-01`
