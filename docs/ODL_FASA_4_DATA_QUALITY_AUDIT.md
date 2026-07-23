# ODL Fasa 4 — Data-quality Audit Read-only

**Tarikh:** 23 Julai 2026

**Environment:** WSL development

**Status:** `CLOSED — WSL AND STAGING AUDIT PASSED`

## Kawalan

- Audit menggunakan adapter ODL TLS read-only.
- Fixed query menapis `status_code IN (2,4,5)` sebagai defense-in-depth.
- OneID hanya dibaca melalui fixed `SELECT` untuk identity dan provenance.
- Output mengandungi aggregate dan snapshot digest sahaja.
- Tiada raw nama, IC, nombor matrik atau e-mel dalam report.
- `can_apply=false` dan `mutation_statements=0`.
- Apply, scheduler dan wiring pengguna kekal disabled.

## Baseline WSL

```text
source_rows=53
status_2=53
status_4=0
status_5=0
ineligible_status_rows=0
valid_student_identities=53
blocking_findings=0
review_findings=0
snapshot_digest=b01c648e5c0acdf98e83bbdfc0bd34acaf5edbd5be4e5210f07e07e5575f436d
mutation_statements=0
```

## Data-quality findings

| Pemeriksaan | Hasil |
|---|---:|
| Blank nama / IC / matrik | 0 / 0 / 0 |
| Blank e-mel / fakulti / program | 0 / 0 / 0 |
| Overlength semua mapped fields | 0 |
| Invalid UTF-8 | 0 |
| Invalid nonblank e-mel | 0 |
| Invalid format matrik | 0 |
| Duplicate identity pair | 0 |
| Satu matrik kepada beberapa IC | 0 |
| Satu IC kepada beberapa matrik | 0 |
| Protected manual collision | 0 |
| Ambiguous OneID match | 0 |
| Provenance membership conflict | 0 |
| UG membership overlap | 0 |

Semua 53 identity ODL belum mempunyai padanan akaun OneID semasa audit. Ia
direkod sebagai `unmatched_external=53`, iaitu candidate baharu untuk fasa
Preview kemudian dan bukan arahan mutation.

## Ujian

```bash
php tests/characterization/odl_f4_data_quality_audit.php
php tools/odl_f4_audit_contract.php
php tools/odl_f4_data_quality_audit.php
```

## Baki exit gate

Audit staging menghasilkan baseline dan digest yang sama dengan WSL. Tiada
Apply dibenarkan oleh Fasa 4.

**Change ID:** `ONEID-ODL-F4-20260723-01`
