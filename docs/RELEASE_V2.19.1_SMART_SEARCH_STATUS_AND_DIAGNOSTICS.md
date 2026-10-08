# OneID 2.19.1 — Smart Search, Status and Diagnostics

**Release date:** 8 October 2026  
**Scope:** User dashboard application discovery, loading feedback, downstream status and safe UI diagnostics

## Bahasa Melayu

Release ini menambah carian aplikasi berdasarkan nama, fungsi dan singkatan, cadangan semasa menaip serta pemulihan carian dalam sesi browser. Skeleton loading lima baris mengurangkan perubahan susun atur ketika senarai aplikasi dimuatkan dan menghormati tetapan reduced motion.

Status downstream kini mempunyai masa semakan yang boleh dilihat dan diproses secara batch terkawal. Aplikasi berstatus penyelenggaraan atau tidak tersedia menyahaktifkan tindakan Access buat sementara, manakala aplikasi perlahan kekal boleh digunakan.

Panel Status Sambungan dan Paparan memberi status loading, masa kejayaan, penerangan skop Clear cache serta retry berwarna merah apabila refresh gagal. View diagnostics menunjukkan environment, browser, saiz skrin, masa respons, sambungan, masa semakan dan reference ID. Paparan dan salinan diagnostik tidak memasukkan password, token, cookie, identiti pengguna atau query URL. Pautan Sokongan PTMK ditempatkan bersama ikon di footer panel.

## English

This release adds application search by name, function and abbreviation, type-ahead suggestions and browser-session search restoration. A five-row skeleton reduces layout shifts while the application directory loads and respects reduced-motion preferences.

Downstream status now includes a visible check time and runs in controlled batches. Applications under maintenance or unavailable temporarily disable Access, while slow applications remain accessible.

The Connection and Display Status panel shows loading state, success time, the narrow scope of Clear cache and a red retry action when refresh fails. View diagnostics presents the environment, browser, screen size, response time, connection, check time and reference ID. Displayed and copied diagnostics exclude passwords, tokens, cookies, user identity and URL queries. The PTMK Support link and icon are placed in the panel footer.

## Validation

- PHP syntax checks for changed page, service, locale and release metadata files.
- User dashboard experience, health panel and existing user dashboard contracts.
- Release metadata and version documentation contracts.
- No database migration, mobile-production activation or PHP runtime change is included.
