# AS3 — Controlled Session Revocation Implementation dan UAT

**Tarikh:** 5 Ogos 2026
**Status:** CODE COMPLETE / DEFAULT OFF / STAGING ACTIVATION PENDING

> **UAT finding 5 Ogos 2026:** Revocation berjaya, tetapi return selepas Step-Up
> tidak membuka semula tab Active Sessions sebelum pending preview di-resume.
> Isu ini dan flow lain yang berkaitan direkod dalam
> `AUDIT_ADMIN_STEP_UP_RETURN_CONTEXT_20260805.md`. Enforcement revocation kekal
> sah. Centralized return-context remediation kini telah dibina; staging UAT
> perlu mengesahkan tab Active Sessions dibuka dan listing selesai refresh
> sebelum pending preview disambung.

## Skop Yang Dibina

- single-session revoke bagi state `Due` atau `Expired` sahaja;
- opaque target ID dan one-use approval ID dalam PHP session;
- approval TTL lima minit dan target locator TTL sepuluh minit;
- exact-purpose grant `ACTIVE_SESSION_REVOCATION` wajib dan Admin 2FA mesti ON;
- current session, target Administrator, state lain, revoke-all dan bulk block;
- mandatory reason 10–250 aksara dan exact typed confirmation;
- guided modal menyediakan empat alasan lazim yang boleh mengisi textarea dan
  frasa confirmation yang boleh diklik untuk mengisi input; Apply masih perlu
  ditekan secara eksplisit dan server tetap memvalidasi reason/phrase;
- re-query `FOR UPDATE`, fingerprint stale check dan exact `status=1 → 0`;
- audit event 66 dalam transaction yang sama; dan
- exact reconciliation `requested=matched=revoked=audited=1`.

Raw token, token hash penuh, PHP session ID, cookie dan reason text tidak masuk
response atau audit. Audit hanya menyimpan fingerprint prefix dan reason digest.

## Committed Defaults

```php
'ONEID_ACTIVE_SESSION_REVOCATION_ENABLED' => 'false',
'ONEID_ACTIVE_SESSION_REVOCATION_PILOT_STATES' => 'due,expired',
'ONEID_ACTIVE_SESSION_REVOCATION_ALLOW_ADMIN_TARGET' => 'false',
'ONEID_ACTIVE_SESSION_REVOCATION_ALLOW_REVOKE_ALL' => 'false',
```

## Staging Preflight — Feature OFF

```bash
cd /var/www/oneid-uat
php tools/f7_4_server_enforcement_contract.php
php tools/as3_controlled_session_revocation_contract.php
php tools/as0_active_sessions_contract.php
php tools/as1_session_policy_contract.php
php tools/as2_revoked_token_contract.php
php -r 'require "bootstrap/app.php"; printf("enabled=%s states=%s admin=%s all=%s\n",oneid_config("ONEID_ACTIVE_SESSION_REVOCATION_ENABLED"),oneid_config("ONEID_ACTIVE_SESSION_REVOCATION_PILOT_STATES"),oneid_config("ONEID_ACTIVE_SESSION_REVOCATION_ALLOW_ADMIN_TARGET"),oneid_config("ONEID_ACTIVE_SESSION_REVOCATION_ALLOW_REVOKE_ALL"));'
```

Expected: semua contract PASS dan `enabled=false`.

## Audit Event Migration

Backup serta Change ID perlu diluluskan sebelum:

```bash
mysql --defaults-extra-file=/path/to/private-client.cnf DATABASE_NAME \
  < docs/migrations/20260805_as3_session_revocation_audit_event_up.sql
```

Sahkan event:

```sql
SELECT syslog_event_id,syslog_event_name
FROM syslog_event_conf
WHERE syslog_event_id=66;
```

Expected: tepat satu row `ADMIN_ACTIVE_SESSION_REVOKE`.

## Controlled Activation

Dalam `.private/runtime.php`, hanya selepas migration/preflight:

```php
'ONEID_ACTIVE_SESSION_REVOCATION_ENABLED' => 'true',
'ONEID_ACTIVE_SESSION_REVOCATION_PILOT_STATES' => 'due,expired',
'ONEID_ACTIVE_SESSION_REVOCATION_ALLOW_ADMIN_TARGET' => 'false',
'ONEID_ACTIVE_SESSION_REVOCATION_ALLOW_REVOKE_ALL' => 'false',
```

## UAT Wajib

1. Listing/search/filter/refresh tidak mengubah status token.
2. Butang hanya muncul pada non-Admin `Due`/`Expired`.
3. Step-Up purpose tepat diminta; wrong/expired grant ditolak.
4. Cancel Preview dan confirmation menghasilkan zero mutation.
5. Reason pendek atau confirmation salah ditolak.
6. Satu target terkawal direvoke dan audit event 66 direconcile.
7. Apply kedua dengan approval sama ditolak.
8. Browser sasaran menerima 401/forced login pada request seterusnya.
9. Current session, target Administrator dan state lain kekal block.
10. Tiada token/hash/cookie/session ID dalam browser response atau audit.

Selepas UAT, rekod target masked ID, state asal, correlation ID, masa browser
dipaksa login dan keputusan rollback owner. Jika hard-stop berlaku, set
`ONEID_ACTIVE_SESSION_REVOCATION_ENABLED=false`; row yang telah direvoke tidak
diaktifkan semula secara automatik.
