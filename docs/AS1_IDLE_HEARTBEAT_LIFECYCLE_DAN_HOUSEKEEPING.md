# AS1 Idle, Heartbeat, Lifecycle dan Housekeeping

**Tarikh keputusan:** 18 Julai 2026
**Status:** IMPLEMENTED / CHECK MODE PASS / APPLY AND SCHEDULER DISABLED
**Scheduler:** DISABLED; activation memerlukan rehearsal dan keputusan operasi

## Polisi Diluluskan

1. PHP session OneID kekal mempunyai idle timeout 30 minit dan absolute timeout
   8 jam.
2. Request teknikal `update_specific_token_datetime` tidak boleh memperbaharui
   idle timeout PHP session.
3. Request pengguna sebenar kekal memperbaharui idle timeout. Absolute timeout
   8 jam tidak boleh dipanjangkan.
4. Heartbeat 5 minit boleh kekal untuk liveness/token heartbeat, tetapi tidak
   dianggap aktiviti manusia.
5. SSO token lifetime kekal datang daripada Admin Configuration.
6. Compatibility refresh window 60 minit kekal sehingga consumer migration
   diluluskan.

## Lifecycle Token

Untuk token lifetime `T`:

```text
0 hingga T                Active
selepas T hingga T+60 min Refresh Window
pada/selepas T+60 min     Expired
```

SC5 `policy_revoke_at` kekal menghasilkan Grace sebelum masa revocation dan Due
apabila masa itu dicapai. Active Sessions perlu memaparkan Current, Active,
Refresh Window, Grace, Due dan Expired.

## Housekeeping

Housekeeping natural expiry hanya boleh menukar `status=0` apabila:

- token melepasi lifetime + refresh window 60 minit;
- token mempunyai issuance timestamp masa hadapan yang tidak sah; atau
- token SC5 telah Due.

Pelaksanaan mesti mempunyai `--check`, batch maksimum, advisory lock,
transaction, exact reconciliation dan audit summary. Tool masuk Git dalam
keadaan fail-closed; tiada cron dipasang oleh release ini.

Arahan read-only:

```bash
php tools/as1_session_policy_contract.php
php tools/as0_active_sessions_contract.php
php tools/as0_active_sessions_preflight.php
php tools/as1_session_housekeeping.php --check
```

`--apply` memerlukan `ONEID_SESSION_HOUSEKEEPING_APPLY_ENABLED=true`, Change ID
dan typed confirmation yang mengikat bilangan calon terkini. Opt-in ini sengaja
tidak ditambah kepada private runtime; ia perlu diberi pada satu invocation yang
diluluskan sahaja. Scheduler kekal tiada.

Token `status=0` tidak dipadam oleh natural cleanup. Retention purge 90 hari
ialah operasi berasingan yang kekal disabled sehingga backup, storage impact,
audit requirement dan owner approval disahkan.

## Multiple Sessions

Behavior `multi_session=0` sedia ada dikekalkan: login baharu menutup token lama.
Jika `multi_session=1`, release ini hanya menyediakan visibility bagi pengguna
dengan sesi berlebihan. Had keras 5 atau 10 sesi belum dipilih dan tidak boleh
mengubah sesi secara automatik tanpa keputusan owner.

## Admin Revoke

Controlled revoke satu/semua sesi kekal deferred sehingga Admin Step-Up 2FA,
preview sasaran, typed confirmation, self-lockout protection, transaction dan
mandatory audit tersedia.

## Urutan Gate

1. Contract idle/heartbeat lulus.
2. Active Sessions lifecycle dan metrics lulus.
3. Housekeeping `--check` dan isolated characterization lulus.
4. Backup serta rollback owner disahkan.
5. Satu controlled `--apply` batch diluluskan dan direconcile.
6. Observation selesai sebelum scheduler dipertimbangkan.

Pada release ini, langkah 1 hingga 3 telah lulus. Langkah 4 hingga 6 belum
diluluskan dan tiada housekeeping mutation dibuat.
