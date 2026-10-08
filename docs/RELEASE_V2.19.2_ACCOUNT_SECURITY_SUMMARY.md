# OneID 2.19.2 — Account Security Summary

**Release date:** 8 October 2026  
**Scope:** User dashboard account-security summary and responsive context panel

## Bahasa Melayu

Release ini menambah ikon Keselamatan Akaun bersebelahan ikon Recently Used dalam direktori aplikasi pengguna. Ikon membuka panel konteks yang padat tanpa menambah atau memanjangkan tab kategori aplikasi.

Panel memaparkan status perlindungan MFA, bilangan sesi aktif, masa login terakhir dan semakan peranti. Setiap maklumat menggunakan kad satu kolum dengan label, ikon berwarna, nilai status dan penerangan ringkas yang disusun untuk paparan desktop serta telefon.

Ringkasan menggunakan respons sesi pengguna yang telah disahkan dan pertanyaan baca sahaja untuk keadaan MFA. Ia tidak memaparkan token, cookie atau pengecam sensitif, tidak mengubah database dan tidak mengaktifkan mobile production.

## English

This release adds an Account Security icon beside Recently Used in the user application directory. The icon opens a compact context panel without adding or extending the application-category tabs.

The panel presents MFA protection, active-session count, last-login time and device review. Each item uses a single-column card with a label, coloured icon, status value and concise explanation arranged for desktop and mobile displays.

The summary uses the authenticated user-session response and a read-only MFA-state query. It does not expose tokens, cookies or sensitive identifiers, does not change the database and does not activate production mobile access.

## Validation

- PHP syntax validation for the dashboard and release metadata.
- Account Security, dashboard experience, health panel and existing user-dashboard contracts.
- Release metadata and version-documentation contracts.
- No database migration, runtime configuration change or production mobile activation.
