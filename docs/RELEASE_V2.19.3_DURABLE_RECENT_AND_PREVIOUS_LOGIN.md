# OneID 2.19.3 — Durable Recently Used and Previous Login

**Release date:** 9 October 2026  
**Scope:** Cross-browser Recently Used history and accurate previous-login display

## Bahasa Melayu

Recently Used kini menyimpan maksimum enam aplikasi terakhir pada server mengikut akaun. Senarai yang sama tersedia selepas logout dan apabila pengguna bertukar browser atau peranti. Setiap aplikasi masih perlu melepasi ACL semasa sebelum direkodkan atau dipaparkan.

Clear Recently Used memadam sejarah akaun pada server. Jadual tambahan hanya menyimpan `u_id`, `sp_id` dan masa penggunaan terakhir sebagai preference paparan; ia tidak memberi akses aplikasi dan mempunyai migration rollback khusus.

Ringkasan Keselamatan Akaun kini mencari sesi paling hampir sebelum sesi browser semasa. Sesi semasa dikecualikan supaya Last Login menunjukkan login terdahulu sebenar.

## English

Recently Used now retains up to six latest applications on the server for each account. The same list remains available after logout and when the user changes browsers or devices. Every application must still pass the current ACL before it is recorded or displayed.

Clear Recently Used removes the account history from the server. The additive table stores only `u_id`, `sp_id` and the last-used time as a display preference; it grants no application access and has a dedicated rollback migration.

The Account Security summary now finds the closest session before the current browser session. The current session is excluded so Last Login reflects the actual previous sign-in.

## Validation

- PHP syntax and release metadata validation.
- Recently Used persistence, six-item cap, ACL filtering and server clear contracts.
- Previous-login exclusion and Account Security contracts.
- Existing dashboard, health panel and application-management regression contracts.
- Production deployment requires the additive `user_app_recent` migration before application verification.
