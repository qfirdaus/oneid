# SC5 — Enforcement dan Revocation Polisi

**Tarikh pelaksanaan:** 16 Julai 2026  
**Status:** IMPLEMENTED DAN CONTROLLED UAT PASS  
**Tempoh grace yang diluluskan owner:** 15 minit

## Skop

Fasa ini memastikan perubahan yang memendekkan hayat token atau mematikan
multiple active token turut dikenakan kepada token sedia ada. Ia tidak mengubah
format token, kontrak consumer atau timeout PHP session.

## Aliran kawalan

1. UI meminta preview server-side sebelum confirmation.
2. Preview memaparkan bilangan token dan pengguna yang akan terjejas.
3. Preview diikat kepada admin, payload dan tamat selepas lima minit.
4. Semasa simpan, impak dikira semula dalam transaksi. Preview yang stale
   ditolak tanpa mutation.
5. Perubahan konfigurasi, audit dan jadual revocation disimpan secara atomik.
6. Token terjejas diberi `policy_revoke_at` 15 minit selepas perubahan.
7. Selepas matang, token direvoke secara lazy ketika digunakan atau secara batch
   melalui runner operasi.

## Komponen

- `Database::preview_policy_revocation()` mengira impak timeout dan duplicate
  active token tanpa mengubah data.
- `Database::schedule_policy_revocation()` menanda token secara targeted.
- `SsoConfigurationService` menguatkuasakan preview segar dan transaksi atomik.
- `api.php` merevoke token due ketika consumer menggunakannya.
- `tools/sc5_policy_revocation_runner.php` memproses semua token due.
- `tools/sc5_cancel_policy_revocation.php` membatalkan jadual yang masih dalam
  grace period menggunakan correlation ID.

## Audit event

| ID | Event | Tujuan |
|---:|---|---|
| 19 | `ADMIN_SSO_CONFIGURATION_UPDATED` | Perubahan konfigurasi |
| 30 | `ADMIN_SSO_POLICY_REVOCATION_SCHEDULED` | Jadual revocation dicipta |
| 31 | `SSO_POLICY_TOKEN_REVOKED` | Token due direvoke |
| 32 | `ADMIN_SSO_POLICY_REVOCATION_CANCELLED` | Jadual future dibatalkan |

## Operasi

Semakan tanpa mutation:

```bash
php tools/sc5_policy_revocation_runner.php --check
php tools/sc5_cancel_policy_revocation.php <correlation-id> --check
```

Pelaksanaan:

```bash
php tools/sc5_policy_revocation_runner.php --apply
php tools/sc5_cancel_policy_revocation.php <correlation-id> --apply
```

Runner lama/sync cron tidak diaktifkan semula. Pengaktifan scheduler berkala bagi
runner SC5 memerlukan keputusan operasi berasingan. Walaupun tanpa scheduler,
API tetap fail-closed terhadap token due apabila token itu digunakan.

## Bukti UAT terkawal

- Contract preview, stale preview, transaksi dan schedule mismatch: PASS.
- Runner merevoke satu token ujian due dan menulis event 31: PASS.
- Cancellation membatalkan satu token ujian future dan menulis event 32: PASS.
- Token ujian dibersihkan selepas verifikasi.
- Correlation runner: `3d4dcf0c70b590fb`.
- Correlation cancellation: `6620e262acf00b61`.
- Semakan akhir runner: `due_tokens=0`.
- Baseline konfigurasi kekal `token_timeout=0.5`, `multi_session=1`,
  `email_OTP=1`.

## Rollback operasi

Dalam grace period, gunakan cancellation tool dengan correlation ID. Selepas
token telah direvoke, pembatalan tidak menghidupkan semula token; pengguna perlu
login semula. Down migration hanya boleh dibuat selepas semua jadual future
dibatalkan dan proses yang membaca medan SC5 dihentikan.
