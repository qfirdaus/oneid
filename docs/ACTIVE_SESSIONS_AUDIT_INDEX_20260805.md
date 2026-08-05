# Active Sessions — Indeks dan Rekonsiliasi Audit

**Tarikh semakan:** 5 Ogos 2026
**Status:** DOCUMENTATION RECONCILED / AS3 CODE COMPLETE / DEFAULT OFF / UAT PENDING

## Authority Semasa

Untuk pembangunan Controlled Active-Session Revocation, rujukan utama ialah
`AS3_CONTROLLED_ACTIVE_SESSION_REVOCATION_AUDIT_20260805.md`. Dokumen terdahulu
kekal sebagai evidence sejarah bagi baseline masing-masing dan tidak memberi
kebenaran tersirat untuk mutation.

## Pemetaan Dokumen

| Dokumen | Hasil asal | Kedudukan selepas semakan |
|---|---|---|
| `AS0_ACTIVE_SESSIONS_AUDIT_DAN_REMEDIASI.md` | Listing selamat, projection terhad dan zero mutation | Selari; kekal baseline read-only |
| `AS1_IDLE_HEARTBEAT_LIFECYCLE_DAN_HOUSEKEEPING.md` | Lifecycle serta housekeeping fail-closed | Selari; housekeeping kekal task berasingan |
| `AS2_REVOKED_TOKEN_DAN_BAKI_ACTIVE_SESSION_AUDIT.md` | Revoked token memaksa login semula | Selari; menjadi enforcement selepas revoke |
| `ADMIN_STEP_UP_2FA_AUDIT_DAN_CADANGAN.md` | Exact-purpose Step-Up, preview, confirmation dan audit | Selari; cadangan revoke-all diganti oleh pilot sempit AS3 |
| `F7_4_SERVER_SIDE_ENFORCEMENT.md` | Purpose isolation dan direct-bypass enforcement | Selari secara reka bentuk; contract semasa 11/14 perlu direconcile |
| `F7_6_UAT_CONTROLLED_ROLLOUT_DAN_OBSERVATION.md` | Step-Up diterima, revocation dikecualikan | Selari; pautan dikemas kini ke audit AS3 |
| `SC7_SC8_PENDING_CONFIGURATION_HANDOFF.md` | Revocation ialah task berasingan | Selari; dependency Step-Up kini tersedia |
| `AS3_CONTROLLED_ACTIVE_SESSION_REVOCATION_BACKLOG.md` | Backlog umum single/revoke-all | Rekod sejarah; superseded oleh audit AS3 terkini |

## Keputusan Rekonsiliasi

Prinsip audit lama dan audit terkini adalah konsisten:

- operasi listing kekal read-only dan zero mutation;
- token/hash tidak boleh dihantar ke browser atau audit;
- mutation memerlukan exact-purpose `ACTIVE_SESSION_REVOCATION`;
- preview, reason, typed confirmation dan stale-target protection diwajibkan;
- current-session/self-lockout mesti dilindungi;
- mutation dan audit perlu transactional serta direconcile; dan
- revoked-token enforcement AS2 menamatkan akses pada request seterusnya.

Perubahan keputusan terkini ialah mengecilkan blast radius pilot. Hanya satu
sesi bukan Admin berstatus `Due` atau `Expired` boleh dipertimbangkan. `Active`,
`Refresh`, `Grace`, current session, target Administrator, revoke-all dan bulk
revoke kekal di luar pilot.

## Gate Dokumentasi dan Pelaksanaan

Dokumentasi telah diselaraskan dan implementation pilot telah dibina. Sebelum
controlled activation acceptance:

1. owner menerima skop pilot AS3;
2. identifier target server-side dipilih;
3. reason/audit retention dan notification diputuskan;
4. F7.4 inventory/expectation drift ditutup sehingga contract semasa lulus;
5. contract mutation, rollback dan UAT disediakan; dan
6. feature flag kekal OFF sehingga controlled activation.

```text
ACTIVE SESSION LISTING: READ-ONLY
AS3 CONTROLLED REVOCATION: IMPLEMENTED / DEFAULT OFF / UAT PENDING
```
