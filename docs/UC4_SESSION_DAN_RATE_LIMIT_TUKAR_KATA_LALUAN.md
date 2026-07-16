# UC4 — Session Rotation dan Rate Limit

**Status:** IMPLEMENTED — CONTRACT PASS

- Lima current-password failure dalam 15 minit bagi user+IP menghasilkan HTTP
  429 dan `UC4_RATE_LIMITED`.
- Voluntary change mengekalkan login tetapi merotasi PHP session ID, CSRF dan
  SSO token; frontend menerima CSRF baharu.
- Forced change merevoke semua token, tidak mencipta replacement, membersihkan
  cookie/session dan mengarahkan pengguna login semula.
- Semua rejected outcomes mempunyai correlation ID dan tiada credential dalam log.
