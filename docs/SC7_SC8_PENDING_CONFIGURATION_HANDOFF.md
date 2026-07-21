# SC7-SC8 Pending Configuration Handoff

**Tarikh direkod:** 18 Julai 2026  
**Skop:** Administrator > Configuration  
**Status:** F7.6 ACCEPTED/CLOSED / AS3 DAN SC8 SCHEDULER BACKLOG BERASINGAN
**Keputusan:** Owner menerima F7.6 pada 21 Julai 2026 selepas functional UAT dan
observation melebihi 24 jam. Monitoring tujuh hari dikecualikan sebagai exit
gate dan diganti dengan continuous operational monitoring. Acceptance ini tidak
memberi kebenaran untuk Active-Session Revocation atau scheduler activation.

Dokumen ini ialah register status dan gate. Reka bentuk induk, purpose matrix,
security contract, Active-Session Revocation, break-glass, UAT dan monitoring
Fasa 7 telah disatukan dalam
`docs/ADMIN_STEP_UP_2FA_AUDIT_DAN_CADANGAN.md`. Jika terdapat perbezaan reka
bentuk, dokumen induk tersebut mengatasi ringkasan dalam handoff ini.

## 1. Baseline Semasa

Fasa SC0 hingga SC6 telah dilaksanakan dan kontrak semasa lulus:

- ketepatan label serta operational feedback UI;
- validation polisi server-side dan explicit response projection;
- singleton `sys_config`, targeted update dan audit mutation atomik;
- absolute SSO token lifetime dengan compatibility refresh terkawal;
- preview impak, grace period 15 minit dan lazy revocation enforcement; dan
- Password Recovery berasingan dengan SMTP readiness, test delivery serta OTP
  end-to-end yang telah disahkan.

Kawalan semasa masih merangkumi authenticated admin session, role admin, CSRF,
exactly-one-action guard, fresh policy preview dan transaction atomik. Baseline
ini perlu dikekalkan sepanjang tempoh penangguhan.

## 2. Task Pending

### SC7-01 Admin Step-Up 2FA

Bangunkan server-enforced step-up mengikut dokumen induk. Purpose berasingan
yang diluluskan untuk reka bentuk ialah:

- `ADMIN_ACCESS` untuk akses admin biasa;
- `SECURITY_CONFIGURATION_CHANGE` untuk mutation keselamatan; dan
- `ACTIVE_SESSION_REVOCATION` untuk revoke sesi terkawal.

Keperluan teras:

- OTP e-mel dan Microsoft Authenticator melalui standard TOTP;
- challenge diikat kepada admin, session, browser dan purpose;
- step-up session berasingan daripada SSO, allowlist 5/10/15/30 minit dan
  default 15 minit;
- rate limit, resend cooldown, replay prevention dan audit;
- enrollment, confirmation, revocation dan recovery TOTP;
- secret TOTP dienkripsi menggunakan key di luar database dan Git; dan
- direct URL atau direct endpoint tidak boleh memintas step-up.

### SC7-02 Mandatory Change Reason — COMPLETE IN FASA 3

- Admin wajib memberikan sebab perubahan sebelum polisi disimpan.
- Server perlu memvalidasi panjang, format dan aksara kawalan.
- Reason direkod bersama actor, IP, before/after dan correlation ID tanpa data
  sensitif.

### SC7-03 Optimistic Locking — COMPLETE IN FASA 3

- Tambah `configuration_version` atau revision equivalent.
- Preview perlu mengikat revision asal.
- Update mesti ditolak jika revision berubah selepas preview walaupun impact
  count kebetulan sama.
- Concurrent update oleh dua admin perlu mempunyai contract dan UAT khusus.

### SC7-04 Configuration History UI — COMPLETE IN FASA 3

- Paparkan sejarah perubahan Configuration secara read-only.
- Paparan minimum: masa, actor, changed fields, before/after, reason, outcome
  dan correlation ID.
- Jangan paparkan token, OTP, session ID, cookie, password atau credential.

### SC7-05 Rejected-Update Audit — PARTIAL

- Validation, stale revision dan Apply rejection kini direkod menggunakan
  reason code allowlisted. Authorization dan Step-Up rejection menunggu SC7-01.
- Audit tidak boleh merekod payload mentah atau nilai rahsia.
- Rejection mesti kekal zero mutation terhadap configuration state.

### SC7-06 Controlled Active-Session Revocation

Listing Active Sessions telah dijadikan read-only melalui AS0 dan revoked-token
enforcement browser telah dilaksanakan melalui AS2. Tindakan revoke
satu sesi atau semua sesi pengguna kekal ditangguhkan sehingga Step-Up tersedia:

- server-enforced Step-Up dengan purpose khusus;
- fresh target preview tanpa token material dalam response;
- typed confirmation dan perlindungan self-lockout;
- targeted transaction serta mandatory audit; dan
- result reconciliation bagi bilangan sesi yang direvoke.

### SC8-01 SC5 Revocation Scheduler Decision

Runner `tools/sc5_policy_revocation_runner.php` belum dijadualkan. Lazy
enforcement pada API kekal aktif dan menolak token due apabila digunakan.

Sebelum scheduler diaktifkan:

- tentukan owner, frequency, lock, timeout dan alerting;
- gunakan service account berpermission minimum;
- jalankan `--check` dan controlled `--apply` rehearsal;
- sahkan idempotency, audit event dan rollback/cancellation boundary; dan
- asingkan keputusan ini daripada Cron External Sync yang turut ditangguhkan.

### SC8-02 Monitoring dan Controlled Rollout

Sediakan monitoring dan alert bagi:

- configuration update dan rejected update;
- token validation/refresh failure serta lonjakan login semula;
- scheduled/due/revoked token;
- SMTP dan Password Recovery delivery failure;
- Step-Up failure, resend dan rate limit; dan
- consumer yang masih menggunakan compatibility refresh atau route legacy.

## 3. Urutan Sambung Semula

Urutan semasa untuk penilaian akan datang:

1. refresh baseline, backup dan consumer inventory;
2. sahkan purpose/action matrix dan endpoint inventory;
3. sediakan encryption-key operation, migration dan controlled bootstrap;
4. Admin Step-Up OTP e-mel secara fail-closed;
5. TOTP enrollment, verification, recovery dan factor lifecycle;
6. lengkapkan authorization/Step-Up rejected audit;
7. controlled Active-Session Revocation;
8. controlled pilot menggunakan satu admin;
9. keputusan scheduler revocation SC5; dan
10. monitoring, observation window serta owner acceptance SC8.

Mandatory change reason, optimistic locking dan Configuration History tidak
lagi berada dalam urutan pending kerana telah lengkap dan lulus UAT dalam SC3.

## 4. Gate Sebelum Pelaksanaan

Pelaksanaan tidak boleh bermula sehingga tersedia:

- Change ID, owner, executing admin dan maintenance window;
- backup serta rollback owner;
- encryption key custody dan recovery procedure bagi TOTP;
- mailbox/admin pilot yang telah disahkan;
- prosedur break-glass yang diaudit dan tidak menjadi bypass kekal;
- contract zero-mutation bagi semua rejection;
- UAT direct endpoint bypass, expiry, logout, replay dan concurrent update; dan
- monitoring owner serta communication channel.

## 5. Keadaan Sepanjang Penangguhan

- Jangan aktifkan feature flag atau schema separa bagi Step-Up/TOTP.
- Jangan aktifkan scheduler revocation SC5 tanpa keputusan operasi berasingan.
- Jangan aktifkan Cron External Sync; ia kekal design-only dan ditangguhkan.
- Kekalkan Operational External Sync manual mengikut runbook S4G.
- Sebarang perubahan Configuration terus menggunakan preview, confirmation,
  audit atomik dan grace-period enforcement sedia ada.

## 6. Exit Criteria Handoff

Handoff gabungan lama ini ditutup bagi skop Admin Step-Up 2FA. Baki kerja telah
dipecahkan supaya acceptance F7.6 tidak mengaktifkan mutation atau scheduler
secara tersirat:

```text
SC0-SC6: COMPLETE
F7.6 ADMIN STEP-UP 2FA: ACCEPTED / CLOSED
AS3 ACTIVE-SESSION REVOCATION: SEPARATE BACKLOG
SC8 REVOCATION SCHEDULER: SEPARATE DECISION BACKLOG
MONITORING: CONTINUOUS OPERATIONAL PROCESS
```

Rujuk `docs/AS3_CONTROLLED_ACTIVE_SESSION_REVOCATION_BACKLOG.md` dan
`docs/SC8_REVOCATION_SCHEDULER_DECISION_BACKLOG.md` sebelum membuka development
scope baharu.
