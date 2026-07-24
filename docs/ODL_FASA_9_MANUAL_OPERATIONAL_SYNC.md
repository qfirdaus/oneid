# ODL Fasa 9 — Manual Operational Sync

**Status:** `PASS / CLOSED`

**Environment:** UAT

**Change reference:** `ONEID-ODL-F9-20260724-01`

## Authorized boundary

Fasa ini menyediakan manual/on-demand ODL Preview melalui Admin menggunakan
guarded operational flow yang sama seperti Undergraduate. Automatic scheduler,
cronjob, unattended mutation, cross-source mutation dan production tidak
dibenarkan.

Tindakan operational yang direka:

- `NEW`, `UPDATE` dan `REACTIVATE` memerlukan fresh Preview, one-time approval
  dan confirmation;
- `DEACTIVATE` memerlukan confirmation khusus dengan exact count dan plan hash;
- Apply ODL mempunyai gate private yang berasingan dan kekal disabled sehingga
  exact-plan authorization diterima.

## Safety boundary

- source dikunci kepada `STUDENT_ODL_PG`;
- kategori dikunci kepada `Pelajar/10`;
- planner read dan persistence write dikunci kepada active ODL membership;
- Matrik, IC dan external membership collision dengan sumber lain diblock;
- akaun manual kekal protected;
- e-mel ODL kosong tidak memadam e-mel OneID sedia ada;
- source failure, empty source, shrink melebihi threshold, invalid identity dan
  deactivation anomaly kekal fail-closed;
- Apply melakukan fresh-plan verification dan transaction/reconciliation
  melalui safe operational coordinator yang sedia ada.

## Private runtime

```php
'ONEID_ODL_OPERATIONAL_PREVIEW_ENABLED' => 'true',
'ONEID_ODL_OPERATIONAL_APPLY_ENABLED' => 'false',
```

Default deployment bagi kedua-dua flag kekal `false`. Apply tidak boleh
diaktifkan apabila Preview disabled.

## WSL live Preview evidence

```text
source=STUDENT_ODL_PG
source rows=53
active scope=53
New=0
Update=0
Deactivate=0
Reactivate=0
source shrink=0%
blocking codes=0
can apply=false
automatic scheduler=false
mutation statements=0
```

Characterization dan contract mesti lulus sebelum staging pull. Staging Admin
Preview perlu disahkan berasingan. Sebarang live Apply pertama Fasa 9
memerlukan exact-plan authorization baharu.

## Controlled manual Apply dan closure

Pasukan ODL menambah 18 rekod baharu. Fresh Preview UAT menghasilkan exact plan:

```text
source rows=71
New=18
Update=0
Deactivate=0
Reactivate=0
plan hash=6ee2d37e099b72b31cea8cea5d8228e43087b92770f1102442235701b771c5fd
```

Apply diluluskan melalui `ONEID-ODL-F9-20260724-02`, backup
`ONEID-UAT-BACKUP-20260724-02` dan revised change window 24 Julai 2026,
3:10 PM–3:40 PM MYT. Operational Apply menghasilkan header `50`, tepat 18
`NEW` dan zero tindakan lain.

Independent reconciliation mengesahkan:

```text
active STUDENT_ODL_PG memberships=71
header total_new=18
header total_updated=0
header total_deactivated=0
header total_reactivated=0
change log NEW=18
```

Post-Apply Preview menunjukkan 71 source rows dan semua action count sifar.
Login/ACL smoke test lulus dengan kategori `Pelajar/10` dan membership
`STUDENT_ODL_PG`. Apply flag dikembalikan kepada `false`. Firdaus, System
Analyst/DBA meluluskan `PASS / CLOSED` pada 24 Julai 2026 melalui evidence
`ONEID-ODL-F9-20260724-02`.
