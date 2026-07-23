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
'ONEID_ODL_SHADOW_STAFF_BASELINE_ROWS' => '1061',
'ONEID_ODL_SHADOW_ODL_BASELINE_ROWS' => '53',
'ONEID_ODL_SHADOW_UG_BASELINE_ROWS' => '5452',
```

Nilai staging mesti dimasukkan terus ke `.private/runtime.php` staging selepas
pull. Gate hanya menerima literal `true` atau `false`.

## Live WSL result

```text
risk_level=normal
STAFF_HR rows=1061
STUDENT_UG rows=5452
STUDENT_ODL_PG rows=53
KEEP_MEMBERSHIP_ACTIVE=5423
KEEP_ACCOUNT_ACTIVE=5423
CANDIDATE_NEW=53
blocking_codes=[]
can_apply=false
mutation_statements=0
preview_digest=13c2ae876287574dd4af3e1b22c21e4c6ff5646db03c400602ec9a64c460ffa8
```

Dashboard memisahkan paparan kepada:

- `External Sync Summary` — perbandingan Staff, UG dan ODL;
- `Staff External Sync` — metrik Staff sahaja;
- `Undergraduate External Sync` — metrik UG sahaja;
- `ODL External Sync` — metrik ODL sahaja.

Membership dan candidate counts turut dipecahkan mengikut `source_code`.
Legacy combined Apply tidak didedahkan melalui menu source-specific ini.

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
e310108ddb89aa0a8a15c1dce3605e15f31a1eb60150f3a579c68905dda9d8eb
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
