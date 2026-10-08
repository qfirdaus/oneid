# OneID 2.18.4 — System Guide Displayed Once

**Release date:** 8 October 2026
**Scope:** User dashboard System Guide progress persistence

The authenticated progress endpoint now accepts System Guide version 2. Completing or skipping the guide stores the status for the signed-in account, so automatic presentation occurs only once. Later sign-ins read the stored status and do not reopen the guide.

Users can still replay the guide manually through **View System Guide**. The endpoint keeps a bounded allowlist for supported guide versions and continues enforcing authenticated CSRF protection and valid completion states.

No database schema, mobile-production configuration, login-session policy or SSO token behavior changes are included.
