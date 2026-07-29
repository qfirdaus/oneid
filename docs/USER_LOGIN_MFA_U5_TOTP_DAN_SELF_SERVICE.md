# User Login MFA U5 — Microsoft Authenticator dan Self-Service

**Tarikh:** 30 Julai 2026

**Status:** `PASS / CLOSED LOCALLY / DORMANT`

**Runtime/login wiring:** `0`

## Objektif

U5 menyediakan lifecycle Microsoft Authenticator pengguna di atas primitive U2
dan schema dormant U1. Ia belum menyediakan route, UI atau migration staging.

## Enrollment dan confirmation

- target enrollment sentiasa pengguna yang sedang authenticated;
- secret dijana server-side dan disimpan encrypted menggunakan keyring;
- provisioning URI hanya dipulangkan kepada pengguna untuk QR lokal;
- response ditandakan `Cache-Control: no-store`;
- secret atau provisioning URI tidak melalui persistence/audit;
- confirmation terikat kepada session dan browser asal;
- kod confirmation mesti sah sebelum factor menjadi `ACTIVE`; dan
- activation serta preference `TOTP` berlaku dalam transaksi yang sama.

## Penggunaan dan preference

- pengguna boleh memilih `TOTP` atau `EMAIL_OTP`;
- `TOTP` hanya boleh dipilih jika global switch hidup dan active factor wujud;
- verification menggunakan encrypted secret;
- `last_used_time_step` dikemas kini secara atomik; dan
- kod pada time-step sama tidak boleh dimainkan semula.

## Kill-switch dan fallback

Apabila global TOTP dimatikan:

```text
available factors = EMAIL_OTP sahaja
encrypted factor = kekal disimpan, tidak dipadam
login fallback = OTP e-mel wajib
```

Menghidupkan atau mematikan switch tidak termasuk dalam U5 dan kekal tertakluk
kepada policy/change authorization.

## Self-service revoke

Pengguna boleh revoke/tukar factor sendiri selepas fresh factor verification.
Operasi atomik:

- revoke active/pending TOTP;
- revoke pending MFA challenges;
- set preference kepada `EMAIL_OTP`;
- revoke semua sesi aktif pengguna; dan
- rekod audit tanpa secret.

Target diperoleh daripada authenticated user, bukan input target berasingan.

## Existing-Admin recovery

Tiada role admin baharu. Recovery menggunakan role Administrator sedia ada dan
memerlukan:

- fresh Admin Step-Up;
- Administrator kedua yang berbeza sebagai verifier;
- reason;
- ticket/reference;
- typed confirmation tepat bagi target; dan
- audit actor, target, outcome dan reference.

Admin hanya revoke/reset. Admin tidak melihat secret/QR dan tidak enroll factor
bagi pihak pengguna. Selepas recovery, pengguna login dan enroll sendiri.

## Dormant boundary

- tiada endpoint atau UI;
- tiada QR image disimpan;
- tiada database implementation live;
- tiada schema staging mutation;
- tiada session sebenar direvoke;
- tiada runtime wiring;
- mode committed kekal `OFF`; dan
- MyDigital ID serta Admin MFA tidak berubah.

## Bukti dan keputusan

```text
U5 characterization: 13/13 PASS
U5 static/lint contract: PASS
QR/provisioning generated locally: yes
Admin secret access: 0
Network calls: 0
Live database mutations: 0
Runtime activation: 0
Raw secret output: 0
```

U5 ditutup `PASS / CLOSED` secara lokal. U6 boleh membina UI BM/English dan
accessibility di atas service boundary ini tanpa mengaktifkan User MFA.
