# MR4 — Enjin Mode Operasi User MFA

**Tarikh:** 7 September 2026
**Fasa:** 4 — mode operasi
**Status:** IMPLEMENTED / AWAITING CONTROLLED UAT

## Hasil

Lima mode canonical kini diterima oleh domain User MFA: `OFF`, `ENROLLMENT`,
`PILOT_ENFORCED`, `ENFORCED` dan `EMERGENCY_BYPASS`.

- `ENROLLMENT` tidak mencabar login kata laluan tetapi mengekalkan self-service
  enrollment dan pengurusan faktor.
- `EMERGENCY_BYPASS` hanya efektif dalam window permintaan `ACTIVE` yang sah,
  maksimum lapan jam, dan tidak mencabar login kata laluan.
- Apabila bypass tamat, pembacaan polisi terus menggunakan exact `restore_mode`
  walaupun worker rekonsiliasi belum mengemas kini row utama.
- Bypass tanpa bukti operational yang lengkap gagal tertutup kepada `ENFORCED`.
- Runtime kini dinilai sebagai ceiling: contoh ceiling `ENFORCED` membenarkan
  database beroperasi pada `ENROLLMENT`, bukan memerlukan padanan string tepat.

Fasa ini tidak menukar polisi staging yang sedang aktif, tidak membuka bypass,
dan tidak mencipta approval. Mutation workflow, maker-checker dan worker
rekonsiliasi kekal untuk fasa berikutnya.

## Verification

Jalankan:

```bash
php tools/user_mfa_resilience_mr4_operational_modes_contract.php
php tools/user_mfa_resilience_mr3_policy_separation_contract.php
php tools/user_login_mfa_u3_contract.php
php tools/user_login_mfa_u4_u5_contract.php
```
