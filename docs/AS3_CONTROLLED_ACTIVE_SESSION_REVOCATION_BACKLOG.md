# AS3 — Controlled Active-Session Revocation

**Status:** BACKLOG / PEMBANGUNAN BERASINGAN  
**Tarikh keputusan owner:** 21 Julai 2026  
**Skop:** Administrator > Active Sessions  
**Dependency tersedia:** Admin Step-Up 2FA F7.6 diterima

## 1. Keputusan owner

Controlled Active-Session Revocation tidak menjadi sebahagian daripada
acceptance Admin Step-Up 2FA F7.6. Ia diteruskan sebagai task pembangunan
berasingan apabila owner membuka change scope baharu.

Listing Active Sessions sedia ada mesti kekal read-only sehingga task ini
diluluskan dan selesai. Penerimaan F7.6 tidak memberi kebenaran untuk menambah
mutation revoke secara terus kepada UI atau endpoint semasa.

## 2. Objektif

Membolehkan admin menamatkan:

- satu sesi tertentu; atau
- semua sesi bagi seorang pengguna,

melalui tindakan server-side yang beraudit, transactional dan dilindungi Admin
Step-Up dengan purpose tepat `ACTIVE_SESSION_REVOCATION`.

## 3. Boundary keselamatan

Implementasi mesti:

- menggunakan fresh server-side target preview tanpa token material;
- mengekalkan listing AS0 sebagai zero-mutation;
- memerlukan grant `ACTIVE_SESSION_REVOCATION`, bukan `ADMIN_ACCESS` atau
  `SECURITY_CONFIGURATION_CHANGE`;
- memerlukan reason dan typed confirmation;
- mengikat preview kepada target, revision/fingerprint dan tempoh luput;
- melindungi current admin session daripada self-lockout tidak sengaja;
- menggunakan targeted transaction dan idempotent status transition;
- merekod actor, target, reason, counts dan correlation ID tanpa token;
- mereconcile planned, executed dan audited counts;
- gagal tertutup bagi stale target, purpose mismatch atau audit failure.

## 4. Flow sasaran

```text
Admin buka Active Sessions read-only
→ pilih satu sesi atau semua sesi pengguna
→ server bina fresh masked preview
→ step-up ACTIVE_SESSION_REVOCATION
→ reason + typed confirmation
→ targeted revoke transaction
→ audit + reconciliation
→ refresh listing
```

## 5. Perkara yang perlu diputuskan sebelum pembangunan

1. Adakah revoke current admin session diblock sepenuhnya atau memerlukan flow
   khas?
2. Apakah typed confirmation bagi single-session dan revoke-all?
3. Adakah pengguna perlu menerima notifikasi selepas sesi direvoke?
4. Apakah retention dan paparan reason dalam audit history?
5. Siapa owner, pilot, rollback owner dan change window?

## 6. Minimum test matrix

- listing kekal zero-mutation;
- single-session revoke berjaya;
- revoke-all hanya menyentuh pengguna sasaran;
- token material tidak dihantar ke browser atau audit;
- stale preview ditolak;
- wrong purpose dan expired grant ditolak;
- cross-admin/session/browser grant ditolak;
- current-session self-lockout dilindungi;
- repeated request idempotent;
- audit failure menyebabkan rollback;
- planned, executed dan audited counts sepadan;
- F7.6, AS0, AS1, AS2 dan SC5 regression lulus.

## 7. Exit gate

Task hanya boleh ditanda selesai selepas implementation, direct-bypass
contracts, controlled UAT, reconciliation, rollback rehearsal dan keputusan
owner direkod. Sehingga itu:

```text
ACTIVE SESSION LISTING: READ-ONLY
REVOCATION MUTATION: DISABLED / NOT IMPLEMENTED
```

