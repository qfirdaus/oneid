# ODL Fasa 7 — Controlled Pilot Implementation

**Tarikh authorization:** 23 Julai 2026

**Status:** `IMPLEMENTATION COMPLETE / EXECUTION NOT AUTHORIZED`

**Environment:** UAT

**Approver:** Firdaus, System Analyst/DBA

**Change reference:** `ONEID-ODL-F7-20260723-01`

## Authorization boundary

Authorization yang diterima ialah:

```text
Setuju authorize Fasa 7 implementation only.
Pilot scope: 3 pelajar ODL.
Allowed action: NEW sahaja.
```

Authorization ini membenarkan code, migration draft, isolated rehearsal dan
read-only Preview tooling dibina. Ia tidak membenarkan migration live, pemilihan
pelajar, pengaktifan Preview pilot atau Apply terhadap data UAT sebenar.

## Implementation

- `OdlPilotConfig` menerima dua feature flag strict dan tepat tiga digest
  identiti SHA-256 daripada private runtime;
- digest dibina daripada identiti normalized `No. Matrik|IC`; raw identity
  tidak disimpan dalam Git;
- `OdlPilotPlanner` hanya menghasilkan tepat tiga tindakan `NEW`;
- identity yang sudah wujud, membership sedia ada, allowlist tidak lengkap,
  duplicate, blank identity atau source mismatch akan block;
- kategori writer dikunci kepada `Pelajar/10`;
- password awal dijana secara rawak dan pengguna perlu menukar password;
- user, membership `STUDENT_ODL_PG` dan audit event ditulis dalam satu
  transaction;
- one-time approval mesti sepadan dengan fresh plan;
- advisory lock, exact reconciliation `3/3/3` dan rollback apabila mismatch
  diwajibkan;
- targeted rollback menggunakan correlation ID dan hanya menerima tepat tiga
  akaun external yang mempunyai membership ODL tunggal;
- migration additive menyediakan `user_external_identity_event`;
- tiada endpoint web Apply atau scheduler diwiring.

## Runtime defaults

Committed defaults dan WSL kekal:

```php
'ONEID_ODL_PILOT_PREVIEW_ENABLED' => 'false',
'ONEID_ODL_PILOT_APPLY_ENABLED' => 'false',
'ONEID_ODL_PILOT_IDENTITY_DIGESTS' => '',
```

`ONEID_ODL_PILOT_APPLY_ENABLED` tidak mempunyai live endpoint consumer dalam
fasa implementation ini. Ia kekal sebagai fail-closed contract untuk fasa
authorization seterusnya.

## Rehearsal evidence

```text
Controlled pilot characterization: 10/10 PASS
Controlled pilot contract: 17/17 PASS
Isolated audit schema forward/rollback: 2/2 PASS
Live user mutation: 0
Live membership mutation: 0
Live Apply endpoint: absent
```

Rehearsal membuktikan:

- execution tanpa authorization menghasilkan zero persistence call;
- exact 3 NEW menghasilkan 3 users, 3 memberships dan 3 events pada fake
  persistence;
- reconciliation mismatch rollback transaction dan release lock;
- targeted correlation rollback menerima tepat tiga row;
- migration sementara boleh dipasang dan dibuang dengan zero user mutation.

## Gate sebelum Pilot Preview

Sebelum read-only Pilot Preview boleh dijalankan:

1. data owner memilih tepat tiga pelajar daripada 53 candidate;
2. Matrik dan IC disemak melalui saluran private;
3. tiga digest dimasukkan dalam private runtime, bukan Git atau shell history;
4. backup/restore rehearsal UAT direkod;
5. migration audit live mendapat authorization berasingan;
6. owner memberi `AUTHORIZE F7 PILOT PREVIEW`.

Selepas itu sahaja:

```bash
php tools/odl_f7_pilot_preview.php
```

Output mesti mempunyai `New=3`, tindakan lain sifar, `can_apply=false`,
`execution_authorized=false` dan `mutation_statements=0`.

## Gate sebelum Apply

Apply sebenar masih memerlukan keputusan baharu yang menyatakan exact Preview
digest, plan hash, tiga identity digest, backup reference, change window,
rollback owner dan typed authorization. Full Apply, production dan automatic
scheduler kekal tidak dibenarkan.
