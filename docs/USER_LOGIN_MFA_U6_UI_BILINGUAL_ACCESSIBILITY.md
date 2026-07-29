# User Login MFA U6 — UI BM/English dan Accessibility

**Tarikh:** 30 Julai 2026

**Status:** `PASS / CLOSED LOCALLY / DORMANT`

**Route/runtime wiring:** `0`

## Skop UI

U6 menyediakan renderer dormant bagi:

- challenge dan pemilihan OTP e-mel/Microsoft Authenticator;
- Account Security untuk enroll, confirm dan revoke;
- mesej recovery yang tidak meminta OTP atau secret;
- Admin Configuration bagi mode, e-mel wajib dan TOTP kill-switch; dan
- loading, empty, success serta error state.

## Bahasa dan canonical state

- katalog BM/English mempunyai key parity tepat;
- locale tidak sah fallback kepada BM;
- label diterjemah tetapi `OFF`, `ENROLLMENT`, `PILOT_ENFORCED`, `ENFORCED`,
  `EMAIL_OTP` dan `TOTP` kekal canonical;
- pilihan faktor dan state datang daripada server, bukan input browser; dan
- mesej request/recovery tidak mendedahkan sama ada akaun atau e-mel wujud.

## Accessibility dan mobile

- skip link dan focus target;
- semantic heading, form label, fieldset dan legend;
- `role=status`, `role=alert`, `aria-live` dan error association;
- numeric input, six-digit constraint dan `autocomplete=one-time-code`;
- focus indicator jelas;
- minimum touch target 44px;
- responsive layout pada skrin kecil; dan
- reduced-motion preference dihormati.

## Sensitive-data boundary

- semua server-derived value di-escape;
- tiada raw OTP, TOTP secret, provisioning URI atau internal error code;
- sensitive value tidak diletakkan dalam URL;
- QR menggunakan placeholder same-origin POST/blob untuk integrasi kemudian;
- QR ditandakan `no-store`; dan
- canonical security decisions tidak dibuat oleh renderer.

## Dormant boundary

- renderer belum digunakan oleh route;
- CSS belum dipautkan oleh halaman live;
- tiada JavaScript/API call;
- tiada database atau session mutation;
- tiada QR sebenar diterbitkan;
- tiada schema staging mutation; dan
- mode kekal `OFF`.

## Bukti dan keputusan

```text
U6 characterization: 11/11 PASS
U6 static/lint contract: PASS
Locale parity: yes
Keyboard/screen-reader/mobile semantics: PASS
Sensitive URL values: 0
Runtime activation: 0
```

U6 ditutup `PASS / CLOSED` secara lokal. U7 boleh menjalankan security dan
regression suite menyeluruh sebelum sebarang migration, endpoint atau pilot.
