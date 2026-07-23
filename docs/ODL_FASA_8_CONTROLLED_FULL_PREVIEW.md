# ODL Fasa 8 — Controlled Full Apply

**Status:** `FULL APPLY COMPLETE / OBSERVATION AND CLOSURE PENDING`

**Environment:** UAT

## Boundary

Implementation bermula sebagai read-only Full Preview. Exact-plan Full Apply
kemudiannya dibenarkan untuk UAT, sumber `STUDENT_ODL_PG` dan tindakan `NEW`
sahaja. Automatic scheduler dan production kekal tidak dibenarkan.

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

## Controlled Full Apply evidence

Authorization:

```text
change reference=ONEID-ODL-F8-20260724-01
backup reference=ONEID-UAT-BACKUP-20260724-01
change window=24 Julai 2026, 12:30 AM–1:00 AM MYT
allowed action=NEW sahaja
```

One-shot Apply pada 24 Julai 2026 menghasilkan:

```text
correlation=fd405ef23c4ff844
new users=50
ODL memberships=50
audit events=50
other actions=0
post-Apply active scope: STAFF_HR=1061, STUDENT_UG=5423, STUDENT_ODL_PG=53
```

Independent reconciliation sepadan `50/50/50`. Post-Apply Shadow Preview
menunjukkan kesemua 53 ODL sebagai `KEEP`, zero candidate baharu, zero blocking
code dan zero mutation. Apply flag dikembalikan kepada `false` selepas one-shot;
tiada web Apply endpoint atau scheduler diwiring. Fasa memerlukan observation,
login/ACL smoke test dan approval closure pemilik.
