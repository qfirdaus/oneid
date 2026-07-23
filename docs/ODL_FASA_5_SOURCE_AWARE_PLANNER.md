# ODL Fasa 5 — Source-aware Planner dan Safety

**Tarikh:** 23 Julai 2026

**Status:** `WSL READY / STAGING VERIFICATION PENDING`

## Skop siap

- `SourceSnapshot` membawa source code, family, health status, row count,
  baseline dan invalid-identity count secara berasingan bagi setiap sumber.
- `SourceAwareSafetyPolicy` menghalang required source yang gagal, kosong,
  shrink melebihi 20% atau melebihi invalid threshold.
- `SourceAwareStudentPlanner` merancang membership berdasarkan gabungan
  No. Matrik dan IC.
- Akaun hanya menjadi candidate deactivation apabila tiada lagi student source
  membership aktif.
- Kehilangan membership pada satu sumber mengekalkan akaun aktif apabila sumber
  pelajar lain masih aktif.
- Source envelope mismatch, ambiguous identity, membership conflict dan
  cross-source profile conflict diblock.
- Output awam ialah safe projection berasaskan digest, `can_apply=false` dan
  `mutation_statements=0`.

## Failure behaviour

| Keadaan | Hasil |
|---|---|
| ODL connection/query failure | Block; zero actions |
| ODL empty source | Block; zero actions |
| ODL shrink melebihi 20% | Block; zero actions |
| UG connection/query failure | Block; zero cross-source actions |
| Invalid identity threshold | Block; zero actions |
| Profile atau membership conflict | Block untuk manual review |

Antara stable blocking code yang disokong ialah:

- `ODL_CONNECTION_FAILED`
- `ODL_QUERY_FAILED`
- `ODL_EMPTY_SOURCE`
- `ODL_SOURCE_SHRINK_EXCEEDED`
- `ODL_INVALID_IDENTITY_THRESHOLD_EXCEEDED`
- `STUDENT_UG_CONNECTION_FAILED`
- `STUDENT_UG_QUERY_FAILED`
- `SOURCE_ENVELOPE_CODE_MISMATCH`
- `STUDENT_PROFILE_CONFLICT`
- `AMBIGUOUS_STUDENT_IDENTITY`
- `SOURCE_MEMBERSHIP_CONFLICT`

## Bukti ujian WSL

```text
Fasa 5 multi-source characterization: 14/14
Fasa 5 contract: 11/11
Legacy planner purity: 17/17
Preview zero-mutation contract: 28/28
Operational safety: 26/26
Factory/wiring: 16/16
Approval: 19/19
Coordinator: 15/15
Dormant readiness: 21/21
External read-only policy: 9/9
Full-sync gate: 17/17
Operational gate: 24/24
```

Assertion lama yang masih menjangka `lib/skp_api.php` telah diselaraskan dengan
keadaan quarantine sebenar. Contract kini mengesahkan semua endpoint SKP
tersebut kekal tiada.

## Runtime boundary

Kelas Fasa 5 dimuatkan sebagai definisi dormant sahaja. Ia tidak dirujuk oleh
legacy runner, dashboard, Apply, scheduler atau production orchestrator.
Tiada live ODL Preview diwujudkan dalam fasa ini; wiring Shadow Preview ialah
skop Fasa 6.

## Staging verification

Selepas pull:

```bash
php tests/characterization/odl_f5_source_aware_planner.php
php tools/odl_f5_source_aware_contract.php
```

Apply dan automatic scheduler mesti kekal disabled.

**Change ID:** `ONEID-ODL-F5-20260723-01`
