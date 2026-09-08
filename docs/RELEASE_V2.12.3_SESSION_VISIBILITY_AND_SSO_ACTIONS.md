# OneID 2.12.3 — Session Visibility and SSO Actions

OneID 2.12.3 packages the user-session visibility, controlled renewal,
multilingual authentication guidance and application-directory action updates
completed after version 2.12.2.

User and Administrator headers now show server-authoritative countdowns while
keeping the OneID idle deadline separate from the Administrator access grant.
Explicit user renewal remains CSRF protected, audited, cooldown controlled and
bounded by the eight-hour absolute session limit. The final two-minute warning
continues to operate independently.

Application actions now use **Access** for OneID SSO and **Sign in** for Non-SSO
destinations. The SSO cookie compatibility handler also returns a safe object
for missing, empty or malformed legacy cookies, preventing PHP warning and
internal-path disclosure during session-expiry handling.

This release contains no database migration and does not change production
runtime values or MFA operating modes.
