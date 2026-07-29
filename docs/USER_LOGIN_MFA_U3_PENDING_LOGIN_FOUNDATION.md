# User Login MFA U3 — Pending Login Foundation

**Tarikh:** 30 Julai 2026

**Status:** `PASS / CLOSED LOCALLY / DORMANT`

**Login wiring:** `0`

## Objektif

U3 memisahkan primary password authentication daripada finalisasi login:

```text
Password sah
  -> policy/scope check
  -> pending transaction
  -> factor verified
  -> one-use finalization authorization
  -> token/session persistence disediakan
```

Coordinator tidak menerima atau menyimpan raw password. Ia bermula hanya
selepas caller mengesahkan primary password.

## Tingkah laku

- `OFF` dan `ENROLLMENT` tidak mencipta pending transaction untuk enforcement;
- `PILOT_ENFORCED` hanya mencabar allowlist pilot;
- `ENFORCED` mencabar semua password login dalam skop;
- primary method selain `PASSWORD`, termasuk MyDigital ID, kekal out of scope;
- pending transaction terikat kepada hash session dan browser digest;
- transaction mempunyai TTL dan ditutup apabila expired;
- factor verification mengubah `PENDING` kepada `VERIFIED`;
- finalizer tidak boleh dipanggil sebelum status `VERIFIED`;
- consumption adalah one-use;
- finalizer failure menyebabkan rollback;
- consume/audit failure menjalankan compensation; dan
- audit failure menggagalkan mutation.

## Finalizer boundary

`UserMfaLoginFinalizerInterface::prepare()` hanya menyediakan persistence handle
opaque dalam transaction. Ia tidak boleh mengeluarkan cookie atau response.
Cookie/session response hanya boleh diterbitkan oleh wiring U4/U6 selepas
coordinator berjaya commit.

Boundary ini mengelakkan token/cookie/session diwujudkan selepas password sahaja.

## Dormant boundary

- tiada `lib/q_func.php` wiring;
- tiada endpoint;
- tiada database implementation live;
- tiada token/cookie/session sebenar;
- tiada schema staging mutation;
- committed mode kekal `OFF`; dan
- MyDigital ID serta Admin Step-Up tidak berubah.

## Exit gate

- OFF parity;
- pilot allowlist behavior;
- MyDigital ID `PASSWORD_ONLY` parity;
- zero token sebelum MFA;
- cross-session rejection;
- verified then one-use finalization;
- replay rejection;
- finalizer rollback;
- durable expiry closure;
- audit tanpa raw session; dan
- full authentication regression.

## Evidence dan keputusan

```text
U3 characterization: 11/11 PASS
U3 static/lint contract: 14/14 PASS
Tokens before MFA: 0
U0/U1/U2 contracts: PASS
Admin Email OTP: 15/15 PASS
Admin TOTP/multilingual: PASS
MyDigital ID security/regression: 24/24 PASS
Live database mutations: 0
Runtime activation: 0
```

U3 ditutup `PASS / CLOSED` secara lokal. U4 boleh membina OTP e-mel login di
atas pending transaction ini, masih tanpa wiring kepada login production atau
global activation.
