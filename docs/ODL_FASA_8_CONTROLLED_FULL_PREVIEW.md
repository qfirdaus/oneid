# ODL Fasa 8 — Controlled Full Preview

**Status:** `FULL PREVIEW READY / APPLY NOT AUTHORIZED`

**Environment:** UAT

## Boundary

Fasa ini membenarkan implementation dan read-only Full Preview sahaja. Full
Apply, automatic scheduler, production dan semua user mutation tidak
dibenarkan.

Expected snapshot dikunci kepada:

```text
source=STUDENT_ODL_PG
source rows=53
existing/keep=3
new=50
update=0
deactivate=0
reactivate=0
```

Planner memerlukan exact Matrik+IC, membership ODL yang aktif bagi tiga akaun
sedia ada, zero cross-source identity collision dan zero missing-source
membership. Preview hanya mengeluarkan aggregate counts, hashed identity,
plan hash dan digest.

## UAT Preview evidence

Tiga snapshot berturut-turut adalah byte-identical:

```text
source rows=53
new=50
keep=3
update=0
deactivate=0
reactivate=0
mutation=0
plan hash=4215ca6cae2b56374a4c3df591483b8dc076fc78a51a9f4336c84f544255ac17
preview digest=f3cfc6855a01769490eba24dee1ea696d58a23527d45fbacd73a70110b03529e
snapshot sha256=4a10fa94920dd02211035b9eb32e31d83c77cc0430605fed32b5e0913bef34f1
```

Characterization `7/7` dan Full Preview contract `10/10` lulus. Apply config
ditolak walaupun cuba ditetapkan `true`; tiada web endpoint atau scheduler
diwiring.
