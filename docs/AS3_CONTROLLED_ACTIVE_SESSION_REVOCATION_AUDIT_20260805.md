# AS3 — Audit Controlled Active-Session Revocation

**Tarikh audit:** 5 Ogos 2026

**Skop:** Administrator → Active Sessions

**Status:** IMPLEMENTED IN CODE / COMMITTED DEFAULT OFF / STAGING UAT NOT STARTED

> **Implementation update — 5 Ogos 2026:** Pilot single-session `Due`/`Expired`
> telah dibina dengan opaque one-use Preview/Apply, exact-purpose Step-Up,
> current/Admin target block, targeted transaction, audit reconciliation dan
> feature flag fail-closed. Listing kekal zero database mutation. Activation
> staging masih memerlukan pemasangan audit event 66 dan controlled UAT.

## 1. Keputusan Audit

Code semasa sudah mempunyai asas enforcement yang sesuai untuk Controlled
Active-Session Revocation, tetapi tindakan `Kill Session` belum boleh ditambah
terus pada jadual. Feature mesti dibina sebagai flow Preview → Admin Step-Up →
exact confirmation → targeted transaction → audit/reconciliation.

Cadangan rollout pertama ialah **single-session revoke sahaja** untuk sesi
berstatus `Due` atau `Expired`, dengan current Administrator session sentiasa
diblock. Revoke sesi `Active`, `Refresh`, `Grace`, revoke-all dan target akaun
Administrator perlu kekal di luar pilot pertama sehingga evidence operasi dan
owner decision tersedia.

Listing AS0 mesti kekal read-only. Search, filter, refresh, pagination dan
sekadar membuka tab tidak boleh menghasilkan token mutation.

## 2. Struktur Code Semasa

### 2.1 Listing dan state

`ActiveSessionService` dan `Database::admin_list_active_sessions()` menyediakan
listing terhad dengan state berikut:

| State | Maksud code semasa | Cadangan tindakan pilot |
|---|---|---|
| `current` | Sesi Administrator yang sedang melihat halaman | Block mutlak |
| `active` | Token masih dalam absolute lifetime | Tiada action pilot |
| `refresh` | Melepasi lifetime tetapi masih dalam compatibility refresh | Tiada action pilot |
| `grace` | Dijadualkan untuk revoke tetapi masa belum tiba | Tiada action pilot |
| `due` | Masa `policy_revoke_at` telah tiba | Single revoke dibenarkan selepas control penuh |
| `expired` | Melepasi absolute lifetime dan compatibility window | Single revoke dibenarkan selepas control penuh |

Semua row listing masih datang daripada `token_tbl.status=1`. Label `expired`
ialah lifecycle classification, bukan bukti bahawa row sudah dimutasi kepada
`status=0`.

### 2.2 Enforcement revoked token

AS2 telah mengikat protected request dan page kepada token database aktif.
Selepas token ditukar kepada `status=0`, browser sasaran pada request seterusnya:

- menerima HTTP 401 bagi AJAX;
- kehilangan cookie SSO;
- authenticated PHP session dibersihkan;
- PHP session ID dirotasi; dan
- perlu login semula.

Oleh itu revocation tidak perlu cuba memadam PHP session pada host/browser lain.
Database token status ialah enforcement source yang betul.

### 2.3 Primitive mutation sedia ada

`Database` mempunyai:

- `update_specific_token_status(user_id, token_id, status)`; dan
- `update_whole_token_status(user_id, status)`.

Kedua-duanya ialah primitive legacy dan tidak mencukupi sebagai endpoint Admin:

- tiada preview atau stale-target protection;
- tiada Step-Up enforcement;
- tiada reason/typed confirmation;
- tiada audit atomik atau reconciliation;
- method specific menerima token/token hash secara terus; dan
- method whole-user terlalu luas untuk digunakan melalui satu row action.

Primitive ini boleh dibalut di belakang persistence/service baharu, tetapi
tidak boleh dipanggil terus daripada UI atau endpoint listing.

### 2.4 Token secrecy dan target identity

Response Active Sessions sengaja tidak mengandungi `token_id`, token hash atau
`policy_revoke_correlation`. Perlindungan ini mesti dikekalkan.

Jangan:

- letak token/hash dalam DOM, `data-*`, hidden input atau URL;
- gunakan `user_id + issued_at` daripada browser sebagai authority akhir;
- terima token ID melalui request body; atau
- log token/hash untuk tujuan audit.

Browser hanya boleh menerima random opaque preview/action ID. Server menyimpan
mapping target secara server-side, terikat kepada actor, Admin PHP session,
browser digest, target fingerprint dan expiry.

## 3. Authorization Yang Wajib

Mutation baharu mesti didaftarkan sebagai guarded Admin POST action dengan:

1. authenticated active SSO token;
2. Administrator role aktif;
3. CSRF token;
4. exactly-one-action request guard;
5. Step-Up grant purpose tepat `ACTIVE_SESSION_REVOCATION`;
6. grant terikat kepada Admin, rotated PHP session dan browser digest;
7. mandatory reason; dan
8. one-use exact target preview.

Grant `ADMIN_ACCESS` atau `SECURITY_CONFIGURATION_CHANGE` tidak boleh diterima.
Apabila Admin 2FA dimatikan secara sah di deployment, code guard semasa boleh
meluluskan purpose sebagai `STEP_UP_DISABLED`; feature revocation patut
menambah private feature flag fail-closed supaya mutation tidak aktif hanya
kerana global Step-Up disabled.

Cadangan committed default:

```php
'ONEID_ACTIVE_SESSION_REVOCATION_ENABLED' => 'false',
'ONEID_ACTIVE_SESSION_REVOCATION_PILOT_STATES' => 'due,expired',
'ONEID_ACTIVE_SESSION_REVOCATION_ALLOW_ADMIN_TARGET' => 'false',
'ONEID_ACTIVE_SESSION_REVOCATION_ALLOW_REVOKE_ALL' => 'false',
```

## 4. Flow Dicadangkan

```text
Admin buka listing read-only
  → pilih row Due/Expired
  → POST preview menggunakan opaque row action request
  → server re-query target dan bina masked preview
  → issue one-use preview ID (TTL maksimum 5 minit)
  → require Step-Up ACTIVE_SESSION_REVOCATION
  → Admin isi reason dan exact typed confirmation
  → POST Apply dengan preview ID, reason dan confirmation
  → consume preview sebelum validation akhir
  → begin transaction
  → re-query exact target FOR UPDATE
  → reject current/stale/already-revoked/state-changed target
  → targeted status transition status=1 → status=0
  → write audit dalam transaction
  → reconcile requested=matched=revoked=audited=1
  → commit
  → refresh listing
```

Cadangan exact confirmation pilot:

```text
REVOKE SESSION <MASKED-USER-ID> <FINGERPRINT-PREFIX>
```

**Keputusan UX owner 5 Ogos 2026:** Frasa exact boleh diklik untuk mengisi
confirmation input dan alasan lazim boleh dipilih untuk mengisi textarea.
Kawalan compensating dikekalkan: tiada auto-Apply, Admin masih perlu menyemak
target dan menekan butang Apply secara eksplisit, approval kekal one-use dan
server membandingkan phrase tepat. Ini mengutamakan ketepatan operasi berbanding
proof-of-manual-typing; keputusan tersebut perlu diliputi staging UAT.

Confirmation dijana server dan mengikat target fingerprint. Reason disyorkan
minimum 10, maksimum 250 aksara, trim/normalize, control character ditolak dan
tidak boleh mengandungi token/OTP.

## 5. Persistence dan Transaction Design

Tambah domain service khusus, contoh:

```text
app/Admin/ActiveSessionRevocationService.php
app/Admin/ActiveSessionRevocationConfig.php
app/Admin/Adapters/SessionRevocationPreviewStore.php
```

Jangan menambah mutation ke `ActiveSessionService::list()`.

Persistence baharu perlu menyediakan operasi sempit:

- resolve masked target preview;
- lock exact active target;
- compare current-session fingerprint;
- transition exact target `status=1` kepada `status=0`;
- count exact audit row; dan
- rollback penuh apabila mutation/audit/reconciliation gagal.

Target row memerlukan identifier server-side yang tidak menjadi bearer token.
Pilihan terbaik ialah surrogate immutable token-row ID melalui migration. Jika
migration belum diluluskan, process/session-side opaque mapping boleh digunakan
untuk pilot, tetapi apply tetap perlu re-query dan fingerprint exact target.
Jangan bergantung kepada indexless broad matching sebagai design production.

Status transition mesti idempotent:

- matched `1`, changed `1` → success;
- matched `1`, changed `0` kerana sudah revoked → stable already-revoked result;
- matched `0` atau fingerprint/state berubah → stale target, zero mutation;
- matched lebih daripada `1` → integrity violation dan rollback.

## 6. Self-Lockout dan Admin Target Policy

### Pilot yang disyorkan

- current session: block mutlak pada preview dan Apply;
- target user dengan `u_type=1` (Administrator): block;
- revoke-all: disabled;
- multi-select/bulk: tidak disediakan;
- hanya satu row `Due` atau `Expired` setiap Apply.

Current-session protection perlu dibandingkan di server menggunakan current
cookie token hash dan token row target. UI badge `current` sahaja tidak cukup
kerana request boleh stale atau diubah.

### Fasa kemudian

Single revoke bagi `Active`, `Refresh` atau `Grace` hanya selepas owner memilih
use case yang sah, notification/monitoring tersedia dan pilot Due/Expired
stabil. Revoke-all perlu flow berasingan dengan exact target user, exact active
count, confirmation lebih kuat serta perlindungan Administrator/last-admin.

## 7. Audit dan Notification

Success audit minimum:

```text
actor=<admin-id>
action=ADMIN_ACTIVE_SESSION_REVOKE
target_user=<bounded-id>
target_session_fingerprint=<non-reversible-prefix>
state_before=<due|expired>
requested=1 matched=1 revoked=1 audited=1
reason_code=<allowlisted-code>
correlation=<16-hex>
```

Jangan simpan raw token, token hash penuh, cookie, PHP session ID, OTP, e-mel
atau device string mentah dalam audit. Free-text reason perlu disimpan dalam
medan yang sesuai atau disanitasi/bounded; elakkan mencantumkannya tanpa had
dalam `syslog.log_detail`.

User notification ialah keputusan owner yang masih diperlukan. Minimum UX
semasa browser sasaran ditamatkan kekal generic forced-login. Jika notifikasi
e-mel ditambah, penghantaran tidak patut berada dalam transaction database;
gunakan post-commit/outbox dengan status delivery beraudit.

## 8. Possibility Matrix

| Tindakan | Pilot | Syarat |
|---|---|---|
| Revoke single `due` | Ya | Full control + non-admin + bukan current |
| Revoke single `expired` | Ya | Full control + non-admin + bukan current |
| Revoke single `active` | Tidak | Owner approval/pilot kemudian |
| Revoke single `refresh` | Tidak | Compatibility consumer impact review |
| Revoke single `grace` | Tidak | Elak memintas scheduled grace policy |
| Revoke current Admin session | Tidak | Block mutlak |
| Revoke sesi Administrator lain | Tidak | Two-person/stronger policy belum diputuskan |
| Revoke semua sesi pengguna | Tidak | Separate exact-count flow belum dibina |
| Bulk revoke ramai pengguna | Tidak | Tiada justifikasi; blast radius tinggi |
| Auto-kill daripada listing/filter | Tidak | Listing mesti zero mutation |
| Housekeeping expired/due | Task berasingan | Guna AS1 runner/gate, bukan UI row action |

## 9. Minimum Contract dan UAT

1. AS0 listing kekal zero mutation.
2. Action map memerlukan Admin + CSRF + Step-Up purpose tepat.
3. Wrong/expired/replayed/cross-browser grant ditolak.
4. Token/hash tidak muncul dalam response, DOM, log atau exception.
5. Preview ID random, session-bound, actor-bound, one-use dan luput ≤5 minit.
6. Current session dan Admin target diblock server-side.
7. State selain Due/Expired diblock pilot.
8. Stale fingerprint/state ditolak dengan zero mutation.
9. Single target hanya mengubah satu row.
10. Repeated Apply idempotent dan tidak melaporkan false success.
11. Audit failure menyebabkan rollback.
12. Requested/matched/revoked/audited reconciliation tepat.
13. Revoked browser menerima 401 dan local session dibersihkan.
14. Manual logout, multi-session, SC5 grace/due dan housekeeping regression.
15. BM/English loading, preview, confirmation, success dan error state.
16. Chrome/Firefox, dua browser/dua PC dan stale-tab UAT.

## 10. Evidence Audit Semasa

Contract yang lulus ketika audit:

```text
AS0 Active Sessions read-only: 23/23 PASS
AS1 lifecycle/housekeeping:     14/14 PASS
AS2 revoked-token enforcement:  14/14 PASS
SC7 Step-Up document contract:  17/17 PASS
```

`f7_4_server_enforcement_contract.php` semasa melaporkan `11/14` dan tiga
failure inventory/expectation (`inventory_49`, `sensitive_exact`,
`ordinary_admin_access`). Contract ini perlu diaudit/ditutup sebelum AS3
implementation acceptance; keputusan itu tidak membatalkan evidence runtime
AS0–AS2, tetapi menjadi gate supaya direct-bypass contract Step-Up terkini
selaras dengan inventory code semasa.

## 11. Exit Gate Sebelum Implementation

- [ ] Owner meluluskan pilot Due/Expired sahaja.
- [ ] Current session block mutlak diluluskan.
- [ ] Admin target dan revoke-all kekal disabled diterima.
- [ ] Surrogate target ID atau server-side opaque mapping dipilih.
- [ ] Reason code, free-text retention dan audit event diputuskan.
- [ ] User notification decision direkod.
- [ ] Feature flag dan rollback owner ditetapkan.
- [ ] F7.4 contract 14/14 atau expectation drift ditutup secara beraudit.
- [ ] Backup, migration rollback dan staging change window tersedia.

Sehingga activation/UAT gate ditutup:

```text
ACTIVE SESSION LISTING: READ-ONLY
CONTROLLED REVOCATION: IMPLEMENTED / DEFAULT OFF / UAT PENDING
```
