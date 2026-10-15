# OneID 2.20.0 — Administrator Access Management

**Release date:** 15 October 2026  
**Scope:** Risk-based administrator step-up, category application assignment and user-specific application access

## Bahasa Melayu

Pengesahan tambahan administrator kini menggunakan polisi berasaskan risiko. Pengesahan MFA semasa memasuki dashboard boleh digunakan semula selama lima minit untuk tindakan kritikal. Selepas tempoh itu, hanya tindakan kritikal meminta step-up semula; operasi pentadbiran biasa terus menggunakan sesi administrator yang aktif.

Pengurusan aplikasi bagi kategori kini menyediakan carian, penapis, kiraan assigned/available, semakan perubahan dan satu tindakan simpan. Server mengira perbezaan sebenar, mengunci data berkaitan dan menggunakan satu transaksi supaya perubahan separa tidak berlaku.

Skrin Manage User kini mempunyai tab Profile dan Application Access. Akses aplikasi dibezakan sebagai Inherited, Direct, Denied atau Available. Perubahan dikumpulkan untuk semakan, konflik serentak dikesan, audit ber-ID rujukan diwajibkan dan sesi pengguna dibatalkan sekali sahaja selepas batch berjaya. Susun atur desktop dan telefon turut diseragamkan.

## English

Administrator step-up now uses a risk-based policy. MFA verification performed when entering the admin dashboard can be reused for critical actions for five minutes. After that period only critical actions request step-up again; routine administration continues under the active administrator session.

Category application management now provides search, filters, assigned/available counts, change review and a single save action. The server calculates the authoritative difference, locks the relevant records and uses one transaction so partial changes cannot be committed.

Manage User now separates Profile and Application Access. Application access is identified as Inherited, Direct, Denied or Available. Changes are reviewed together, concurrent conflicts are detected, reference-based audit is mandatory and user sessions are revoked once after a successful batch. Desktop and mobile layouts are responsive and consistent.

## Validation

- PHP syntax and release metadata contracts.
- Category application-access transaction and rollback contracts.
- User application-access batch, conflict detection and mandatory audit contracts.
- Administrator step-up enforcement, direct-bypass and renewal contracts.
- Existing profile/ACL, session revocation and MFA policy regression contracts.
- Read-only staging database catalogue query; no production database migration is required.
