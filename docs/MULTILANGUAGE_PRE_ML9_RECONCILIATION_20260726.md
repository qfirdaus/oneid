# Multilanguage Pre-ML9 Reconciliation

**Audit date:** 26 Julai 2026
**Environment:** Local WSL
**Mode:** Read-only reconciliation dan documentation cleanup
**Git push, staging dan Production:** tidak dibenarkan

## Keputusan

Status implementation multilingual Local WSL adalah lengkap mengikut skop yang
telah diluluskan. Tiada blocker implementation atau content kritikal ditemui
untuk memulakan authorization ML9.

| Skop | Status authoritative | Evidence |
|---|---|---|
| ML0 Language Contract | PASS / CLOSED | `ONEID-ML0-20260725-01` |
| ML1 Locale Infrastructure dan schema Local | PASS | `ONEID-ML1-UAT-20260725-01` |
| ML2/ML3 Login, Recovery dan OTP Pilot | PASS / CLOSED | `ONEID-ML2-LOCAL-20260725-02` |
| ML4 User Dashboard | PASS / CLOSED | `ONEID-ML45-LOCAL-20260725-01` |
| ML5 Administrator | PASS / CLOSED | `ONEID-ML45-LOCAL-20260725-01` |
| ML6 API, e-mel dan notification | PASS / CLOSED | `ONEID-ML6-LOCAL-20260725-01` |
| ML7/ML7A metadata dan content completion | PASS / CLOSED | `ONEID-ML7A-BULK-LOCAL-20260725-02` |
| ML8A document contract | PASS / CLOSED | `ONEID-ML8A-LOCAL-20260725-01` |
| ML8B shared FAQ | PASS / CLOSED | `ONEID-ML8B-LOCAL-20260725-01` |
| ML8C Version Releases | PASS / CLOSED | `ONEID-ML8C-ACTIVATE-LOCAL-20260725-01` |
| External Sync multilingual | PASS / CLOSED | `ONEID-ML-EXTSYNC-LOCAL-20260726-01` |
| Admin Step-Up multilingual | PASS / CLOSED | `ONEID-ML-STEPUP-LOCAL-20260726-01` |
| Administrator Multilingual Completeness | PASS / CLOSED | `ONEID-ML-ADMIN-COMPLETE-LOCAL-20260726-01` |

## Reconciliation semasa

- BM dan English catalogue mempunyai ordered parity.
- FAQ mempunyai parity `8/8`.
- Pada checkpoint pre-release, Version Releases mempunyai parity `37/37`
  release dan `217/217` changelog. Activation v2.6.3 seterusnya menaikkan
  approved catalogue kepada `38/38` dan `229/229`.
- Current document inventory selepas penambahan release document v2.6.3
  mempunyai `150` identity, duplicate `0`, missing
  target `0` dan blocking code `0`.
- External Sync exact confirmation kekal server-supplied dan canonical.
- Admin Step-Up purpose/factor/identifier kekal invariant.
- Authentication, authorization, ACL dan session lifetime tidak diubah.
- Legacy `msg` masih dikekalkan seperti contract ML0/ML6.

Current read-only document inventory digest:
`2c05e1ae5e36465b2fdf242fed8e97b98dd4d2612d27516a9a183608f055a108`.

Digest ini ialah reconciliation semasa selepas penambahan evidence documents
dan release document v2.6.3.
Approved historical ML8A digest
`598e46cbb5e55fe72ae227be70fba7f7b2f59d9ed2ca6c966a7e35797fb66530`
kekal sebagai evidence checkpoint ML8A dan tidak ditulis semula.

## Dokumen lapuk yang diperjelaskan

- `ML1_UAT_MIGRATION_AND_PILOT_GATE.md` kini membezakan gate dormant sejarah
  daripada migration Local yang telah PASS.
- `ML8C_BILINGUAL_CONTENT_PREVIEW.md` kini membezakan Preview gate sejarah
  daripada Version Release activation yang telah CLOSED.
- `ML8C_RELEASE_ENGLISH_DRAFT_REVIEW.md` ditanda superseded oleh approved
  `217/217` English catalogue; baris `REVIEW_REQUIRED` dikekalkan sebagai
  provenance sebelum approval.
- Evidence External Sync, Admin Step-Up dan Administrator completeness
  dikecualikan daripada manifest ML8A untuk mengelakkan self-referential drift.

## Accepted deferred item

English User Manual PDF ialah **DEFERRED BY OWNER / NOT A CURRENT BLOCKER**.
`MANUAL_SALAM.pdf` kekal manual rasmi. Locale English menerima notis fallback
BM yang jelas; silent fallback dan auto-approved machine translation tidak
dibenarkan.

## Readiness sebelum ML9

Keputusan audit: **READY TO REQUEST ML9 AUTHORIZATION**.

Ini bukan authorization untuk Git push, migration staging, UAT staging,
Production atau removal legacy `msg`. ML9 perlu menentukan exact source state,
migration inventory, runtime keys, backup/rollback, UAT matrix, observation
window dan deployment gate secara berasingan.
