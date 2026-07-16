# UC1 — UI dan Operational Feedback Tukar Kata Laluan

**Tarikh:** 16 Julai 2026  
**Status:** IMPLEMENTED — CONTRACT PASS  
**Skop:** Ketepatan UI dan feedback sahaja

## Perubahan

- Copy awal dibetulkan daripada 8 kepada 12 aksara agar sepadan dengan backend.
- Reset indicator kini menggunakan ID sebenar `p_length`, `p_lowercase`,
  `p_uppercase`, `p_number` dan `p_special`.
- Label current password sentiasa jelas bagi voluntary dan forced-change modal.
- Input menggunakan `autocomplete=current-password/new-password` dan minimum 12.
- Submit mempunyai loading state, input lock dan double-submit protection.
- Transport failure tidak lagi senyap; HTTP status dipaparkan.
- Semua feedback flow ini dipaparkan dalam modal, bukan toast di belakang modal.
- Mesej boleh dipilih dan disalin melalui butang `Salin mesej`, termasuk code
  serta correlation ID.
- Backend memulangkan outcome code dan correlation ID yang berbeza.
- Audit rejected attempt menggunakan safe reason code dan correlation tanpa
  password, hash, token atau OTP.

## Outcome code

| Code | Maksud |
|---|---|
| `UC1_PASSWORD_CHANGED` | Workflow legacy melaporkan success |
| `UC1_PASSWORD_POLICY_REJECTED` | Polisi 12/composition gagal |
| `UC1_PASSWORD_CONFIRMATION_MISMATCH` | Confirmation tidak sepadan |
| `UC1_CURRENT_PASSWORD_INVALID` | Current password tidak dapat disahkan |
| `UC1_RESPONSE_INVALID` | UI menerima response yang tidak dikenali |

## Sempadan yang dikekalkan

UC1 tidak menambah transaction, forced-change backend enforcement, rate limit,
password history, OTP invalidation atau PHP session/CSRF rotation. Risiko itu
kekal untuk UC2–UC5. Success response legacy juga belum boleh dianggap bukti
atomic sehingga UC2 dilaksanakan.

## Verification

`tools/uc1_user_password_change_ui_contract.php` mengesahkan copy, indicator,
autocomplete, loading/double-submit, transport feedback, structured outcomes,
correlated audit dan sempadan UC1.
