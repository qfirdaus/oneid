# SC7-SC8 Pending Configuration Handoff

**Tarikh direkod:** 18 Julai 2026  
**Skop:** Administrator > Configuration  
**Status:** DEFERRED BY OWNER  
**Keputusan:** Pelaksanaan ditangguhkan dan akan disambung melalui change scope
berasingan. Dokumen ini tidak memberi kebenaran untuk mutation database,
pengaktifan scheduler atau perubahan polisi live.

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

Bangunkan server-enforced step-up untuk perubahan security configuration:

- purpose khusus `SECURITY_CONFIGURATION_CHANGE`;
- OTP e-mel dan Microsoft Authenticator melalui standard TOTP;
- challenge diikat kepada admin, session, browser dan purpose;
- step-up session maksimum 15 minit;
- rate limit, resend cooldown, replay prevention dan audit;
- enrollment, confirmation, revocation dan recovery TOTP;
- secret TOTP dienkripsi menggunakan key di luar database dan Git; dan
- direct URL atau direct endpoint tidak boleh memintas step-up.

### SC7-02 Mandatory Change Reason

- Admin wajib memberikan sebab perubahan sebelum polisi disimpan.
- Server perlu memvalidasi panjang, format dan aksara kawalan.
- Reason direkod bersama actor, IP, before/after dan correlation ID tanpa data
  sensitif.

### SC7-03 Optimistic Locking

- Tambah `configuration_version` atau revision equivalent.
- Preview perlu mengikat revision asal.
- Update mesti ditolak jika revision berubah selepas preview walaupun impact
  count kebetulan sama.
- Concurrent update oleh dua admin perlu mempunyai contract dan UAT khusus.

### SC7-04 Configuration History UI

- Paparkan sejarah perubahan Configuration secara read-only.
- Paparan minimum: masa, actor, changed fields, before/after, reason, outcome
  dan correlation ID.
- Jangan paparkan token, OTP, session ID, cookie, password atau credential.

### SC7-05 Rejected-Update Audit

- Rekod validation rejection dan authorization/step-up rejection menggunakan
  reason code allowlisted.
- Audit tidak boleh merekod payload mentah atau nilai rahsia.
- Rejection mesti kekal zero mutation terhadap configuration state.

### SC7-06 Controlled Active-Session Revocation

Listing Active Sessions telah dijadikan read-only melalui AS0. Tindakan revoke
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

Urutan yang diluluskan untuk penilaian akan datang:

1. refresh baseline, backup dan consumer inventory;
2. mandatory change reason dan optimistic locking;
3. Admin Step-Up OTP e-mel secara fail-closed;
4. TOTP enrollment, verification, recovery dan encryption-key operation;
5. Configuration History serta rejected-update audit;
6. controlled pilot menggunakan satu admin;
7. keputusan scheduler revocation SC5; dan
8. monitoring, observation window serta owner acceptance SC8.

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

Handoff ini hanya boleh ditutup apabila SC7 dan SC8 mempunyai implementation,
contract, migration/rollback, controlled UAT, monitoring serta keputusan owner
yang direkod. Sehingga itu status rasmi ialah:

```text
SC0-SC6: COMPLETE
SC7-SC8: DEFERRED BY OWNER
RUNTIME CHANGE FROM THIS HANDOFF: NONE
```
