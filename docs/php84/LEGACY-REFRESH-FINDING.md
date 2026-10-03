# Legacy refresh: access checks bypassed in refresh branch

Date: 2026-10-03. Detected during Phase 4; UAT remediation implemented and tested (see outcome below).

## Evidence and scope

`tools/php84_legacy_expiry_fixture.py` executes temporary copies of the current api.php branch code through actual web FPM 8.3 and 8.4. Configuration/bootstrap, integration authentication guard and request body are replaced; operation storage is synthetic in-memory. The lifetime policy is the actual application class. No live tokens or ACLs are modified. This is a branch-level reproduction, not a full database/provider exploit test.

Eight cases match across runtimes. Five meet expectations: active token, revoked active token, future-issued token, ordinary refresh, fully expired token. Three fail expected rejection: revoked token within refresh window, application denied within refresh window, and due policy revocation within refresh window. In all three, response flag=2, respond=1, a new token and user packet are returned, and the synthetic store records new token issuance.

## Cause

api.php evaluates token age and enters LEGACY_REFRESH before checking token status or the application's ACL. Status and ACL checks are inside the ACTIVE branch only. Due policy revocation sets status=0 before age evaluation, but the refresh branch ignores it. The preceding registered-credential resolution does not establish user authorization for that application.

## Required fix design

- Apply token status/revocation checks before issuing any refreshed token.
- Apply the registered application's user ACL to both active and refresh branches; preserve the deliberate IDP contract separately.
- Ensure rejected requests never issue a token or return user packets.
- Review atomicity of old-token retirement/new-token issuance and concurrent revocation before implementation.
- Keep downstream response contracts unchanged for legitimate requests and repeat the expiry fixture on both runtimes, followed by private database integration tests.

This is a pre-existing behavioral flaw in both 8.3 and 8.4, not a runtime incompatibility. Do not mark security acceptance or cutover ready based on runtime parity. The original failing results above describe the pre-fix behavior.


## Remediation outcome

UAT api.php now rejects inactive/revoked tokens and denied application ACL before either active or refresh branches. Database::refresh_legacy_token starts a transaction, re-reads the token/account with FOR UPDATE, checks current lifetime and policy revocation and re-evaluates authorization. Old-token retirement and replacement insertion commit together; any failure rolls back. A replay/concurrent refresh of the same old token cannot issue a second replacement.

Validation: 8/8 synthetic actual-API branch scenarios pass on both FPM 8.3 and 8.4; 10/10 private MySQL storage tests pass on each CLI runtime (including concurrent refresh and forced insert-failure rollback); 3/3 real unauthenticated API negative contracts match. No live UAT tokens were expired/refreshed for these tests. The MySQL test invokes the real Database method with a fixture timeout configuration and injected private connection; it is not a live database end-to-end test.

Application code is shared by existing UAT PHP 8.3 and the isolated 8.4 listener, so this fix applies to both UAT routes. No production or downstream code changed, no routing cutover. Real active-token/ACL confirmation should be repeated after this code change; other Phase 4 gates remain open.
