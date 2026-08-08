<?php
declare(strict_types=1);

/*
 * Sumber tunggal changelog BM/English.
 * Kekalkan setiap item sejarah; mudahkan bahasa tanpa menggabung atau membuang item.
 */
return array_values(array (
  'release-2.8.3' =>
  array (
    'version' => '2.8.3',
    'date' => '2026-08-08',
    'bm' =>
    array (
      0 => 'Sync Log Administrator kini merekod dan memaparkan sumber canonical bagi setiap sesi baharu sebagai Staf, Prasiswazah atau ODL.',
      1 => 'Setiap sumber yang diproses oleh cron menghasilkan sesi audit berasingan apabila terdapat perubahan, manakala sumber tanpa perubahan kekal tanpa header baharu.',
      2 => 'Rekod sejarah tanpa metadata sumber dipaparkan secara jujur sebagai Legacy / Tidak diketahui tanpa membuat inferens yang berisiko daripada data perubahan.',
      3 => 'Sesi yang dimulakan oleh cron dan Administrator dibezakan dengan label Cron atau Manual serta identiti pelaksana yang jelas.',
      4 => 'Identiti Administrator manual kini memaparkan nama bersama nombor staf penuh daripada data3 dan tidak lagi mendedahkan nombor kad pengenalan sebagai paparan utama.',
      5 => 'Senarai Sync Log direka semula kepada susun atur enam kolum yang lebih padat dengan ID dan sumber digabungkan, masa mula dan tamat tersusun serta metrik perubahan yang kemas.',
      6 => 'Detail sesi memaparkan sasaran sebagai Nama dan nombor staf dengan fallback snapshot audit atau ID asal untuk rekod lama yang tidak lengkap.',
      7 => 'Pembacaan detail menyokong perbezaan collation antara jadual legacy melalui perbandingan ID binary tanpa migration atau perubahan kepada data pengguna.',
      8 => 'Migration idempotent menambah source_code nullable dan indeks khusus pada header Sync Log, sementara Apply baharu gagal secara selamat jika schema belum tersedia.',
      9 => 'Perubahan ini kekal setempat kepada OneID Admin dan tidak mengubah token SSO, kontrak service provider, sumber data luaran atau aplikasi lain.',
    ),
    'en' =>
    array (
      0 => 'The Administrator Sync Log now records and displays the canonical source for each new session as Staff, Undergraduate or ODL.',
      1 => 'Each source processed by cron creates a separate audit session when changes exist, while sources with no changes continue without a new header.',
      2 => 'Historical records without source metadata are honestly displayed as Legacy / Unknown without risky inference from change data.',
      3 => 'Cron-triggered and Administrator-triggered sessions are distinguished with Cron or Manual labels and clear actor identity.',
      4 => 'Manual Administrator identity now shows the name with the full staff number from data3 and no longer exposes the identity card number as the primary display.',
      5 => 'The Sync Log list has been redesigned as a compact six-column layout with combined session and source, structured start and end times, and concise change metrics.',
      6 => 'Session details display the target as name and staff number with an audit snapshot or original ID fallback for incomplete historical records.',
      7 => 'Detail reads support differing legacy table collations through binary ID comparison without a migration or user-data changes.',
      8 => 'An idempotent migration adds a nullable source_code and dedicated index to Sync Log headers, while new Apply operations fail safely when the schema is unavailable.',
      9 => 'These changes remain local to OneID Admin and do not alter SSO tokens, service-provider contracts, external data sources or other applications.',
    ),
  ),
  'release-2.8.2' =>
  array (
    'version' => '2.8.2',
    'date' => '2026-08-07',
    'bm' =>
    array (
      0 => 'PHP idle timeout portal pengguna kini mengikuti tetapan timeout Administrator semasa, dengan fallback selamat 30 minit dan had mutlak sesi lapan jam.',
      1 => 'Endpoint status, pembaharuan dan tamat sesi menggunakan deadline server yang berautoriti, CSRF serta kod respons stabil tanpa mengubah kontrak API aplikasi SSO lain.',
      2 => 'Popup SweetAlert pengguna memberi amaran dua minit sebelum deadline efektif dan Stay Connected hanya memperbaharui sesi portal OneID tanpa rotate atau revoke token SSO.',
      3 => 'Jika pengguna tidak memberi respons, sesi portal setempat tamat dengan selamat tanpa menutup sesi aplikasi lain yang masih menggunakan token SSO aktif.',
      4 => 'Technical heartbeat dan status polling tidak memanjangkan idle session, manakala aktiviti pengguna yang bermakna menyelaraskan semula deadline dengan server.',
      5 => 'Keadaan multi-tab, tab tersembunyi, back-forward cache, rangkaian terputus, ralat server, CSRF tidak sah, akaun tidak aktif dan token dibatalkan dikendalikan secara berasingan.',
      6 => 'Sesi Administrator dan pengguna berkongsi deadline asas yang konsisten; pembaharuan grant Administrator tidak boleh menghidupkan semula sesi pengguna yang sudah tamat.',
      7 => 'Audit khusus merekodkan sesi portal tamat, diperbaharui atau ditamatkan tanpa menambah perubahan kepada token validation dan integrasi service provider sedia ada.',
      8 => 'Readiness gate staging mengesahkan polisi timeout, endpoint, popup, heartbeat, sesi Administrator, SSO, multilingual dan kontrak keselamatan sebelum release.',
      9 => 'Konfigurasi private runtime boleh disusun mengikut kategori dengan komen yang konsisten, backup automatik, pengesahan nilai dan pengekalan permission asal untuk akses PHP-FPM.',
    ),
    'en' =>
    array (
      0 => 'The user portal PHP idle timeout now follows the current Administrator timeout setting, with a safe 30-minute fallback and an eight-hour absolute session boundary.',
      1 => 'Session status, renewal and end endpoints use authoritative server deadlines, CSRF and stable response codes without changing other SSO application API contracts.',
      2 => 'The user SweetAlert appears two minutes before the effective deadline, and Stay Connected renews only the OneID portal session without rotating or revoking the SSO token.',
      3 => 'If the user does not respond, the local portal session ends safely without closing other applications that continue to use an active SSO token.',
      4 => 'Technical heartbeat and status polling do not extend the idle session, while meaningful user activity resynchronizes the deadline with the server.',
      5 => 'Multi-tab, hidden-tab, back-forward cache, offline, server-error, invalid-CSRF, inactive-account and revoked-token states are handled independently.',
      6 => 'Administrator and user sessions share a consistent base deadline; renewing the Administrator grant cannot revive a user session that has already expired.',
      7 => 'Dedicated audit events record portal session expiry, renewal and termination without changing existing token-validation and service-provider integrations.',
      8 => 'The staging readiness gate verifies timeout policy, endpoints, popup, heartbeat, Administrator sessions, SSO, multilingual and security contracts before release.',
      9 => 'Private runtime configuration can be organized into documented categories with automatic backup, value preservation checks and original permission retention for PHP-FPM access.',
    ),
  ),
  'release-2.8.1' =>
  array (
    'version' => '2.8.1',
    'date' => '2026-08-06',
    'bm' =>
    array (
      0 => 'Kategori Admin SSO kini mengira dan menyenaraikan akaun berdasarkan peranan Administrator aktif, bukan keahlian kategori biasa, supaya jumlah sebenar dipaparkan dengan tepat.',
      1 => 'Data ujian bukan Administrator dikeluarkan daripada peranan Admin SSO manakala tiga Administrator sah dikekalkan tanpa mengubah status staf biasa mereka.',
      2 => 'Laporan User List kategori direka semula sebagai paparan print-ready profesional dengan identiti UPNM dan OneID, metadata kategori, masa penjanaan, rujukan laporan serta jumlah pengguna.',
      3 => 'Kolum pengenalan laporan kini memaparkan nombor staf daripada data3 bagi staf dan nombor matrik daripada data4 bagi pelajar tanpa mendedahkan nombor kad pengenalan.',
      4 => 'Nama tambahan daripada data7 dipaparkan pada baris kecil di bawah nama utama, manakala lebar kolum ID, Nama dan Description diseimbangkan untuk cetakan A4 landskap.',
      5 => 'Laporan cetak menyokong header berulang, baris yang tidak terpotong, warna cetakan konsisten, paparan responsif dan tindakan kembali atau cetak yang diasingkan daripada output fizikal.',
      6 => 'Sesi Administrator kini memaparkan amaran SweetAlert dengan countdown tepat dua minit sebelum grant ADMIN_ACCESS tamat bagi semua nilai lifetime konfigurasi.',
      7 => 'Popup sesi menggunakan layout profesional responsif dengan identiti OneID, lapisan latar kabur, hierarki keselamatan, badge countdown serta tindakan Kekal Bersambung atau kembali ke akaun pengguna.',
      8 => 'Kekal Bersambung menggantikan grant lama secara atomik menggunakan lifetime konfigurasi semasa, CSRF, binding sesi dan pelayar, transaksi database serta audit event ADMIN_ACCESS_RENEW.',
      9 => 'Pembaharuan grant diselaraskan merentas tab dan semakan status server diklasifikasikan sebagai technical heartbeat supaya polling tidak memanjangkan idle session pengguna.',
      10 => 'Jika tiada respons, grant tamat secara server-side dan Administrator dikembalikan ke dashboard pengguna tanpa menamatkan token SSO yang masih aktif; akses admin seterusnya memerlukan pengesahan semula.',
      11 => 'Selepas pembaharuan berjaya, mesej pengesahan kekal terbuka sehingga Administrator menekan OK dan tidak lagi ditutup secara automatik.',
      12 => 'Asset JavaScript dan CSS sesi Administrator kini dilayan daripada public web root dengan cache versioning, contract runtime dan regression checks bagi mencegah kegagalan popup berulang.',
    ),
    'en' =>
    array (
      0 => 'The Admin SSO category now counts and lists accounts by active Administrator role rather than ordinary category membership, so the true total is displayed accurately.',
      1 => 'The non-Administrator test record was removed from the Admin SSO role while the three legitimate Administrators remain unchanged as ordinary staff accounts.',
      2 => 'The category User List report has been redesigned as a professional print-ready view with UPNM and OneID identity, category metadata, generation time, report reference and total users.',
      3 => 'The report identifier column now displays staff numbers from data3 for staff and matric numbers from data4 for students without exposing identity card numbers.',
      4 => 'The additional name from data7 appears as a smaller line beneath the primary name, while the ID, Name and Description columns are balanced for A4 landscape printing.',
      5 => 'The print report supports repeating headers, unbroken rows, consistent print colours, responsive display and return or print actions that are excluded from physical output.',
      6 => 'Administrator sessions now show a SweetAlert warning with an exact countdown two minutes before the ADMIN_ACCESS grant expires for every configured lifetime.',
      7 => 'The session popup uses a professional responsive layout with OneID identity, a blurred backdrop, security hierarchy, countdown badge and actions to stay connected or return to the user account.',
      8 => 'Stay Connected atomically replaces the old grant using the current configured lifetime, CSRF, session and browser binding, a database transaction and the ADMIN_ACCESS_RENEW audit event.',
      9 => 'Grant renewal is synchronized across tabs, and server status checks are classified as technical heartbeats so polling does not extend the user idle session.',
      10 => 'Without a response, the grant expires server-side and the Administrator returns to the user dashboard without ending an otherwise active SSO token; entering admin again requires fresh verification.',
      11 => 'After a successful renewal, the confirmation remains open until the Administrator presses OK and no longer closes automatically.',
      12 => 'Administrator session JavaScript and CSS assets are now served from the public web root with cache versioning, runtime contracts and regression checks to prevent recurring popup failures.',
    ),
  ),
  'release-2.8.0' =>
  array (
    'version' => '2.8.0',
    'date' => '2026-08-06',
    'bm' =>
    array (
      0 => 'External Sync staging kini dijalankan tepat pada awal setiap jam untuk sumber Staff, Prasiswazah dan ODL.',
      1 => 'Setiap sumber dibandingkan secara berasingan dan tamat sebagai SKIP_NO_CHANGES tanpa transaction atau perubahan database apabila tiada tindakan diperlukan.',
      2 => 'Staging boleh memproses semua perubahan selamat termasuk New, Update, Deactivate dan Reactivate tanpa had volum cron.',
      3 => 'Kawalan integriti bagi sumber gagal atau kosong, identity collision, plan drift dan reconciliation mismatch kekal fail-closed.',
      4 => 'Filesystem flock dan database advisory lock menghalang cron, sync manual atau run sebelumnya daripada menulis secara bertindih.',
      5 => 'Kegagalan database cron kini melaporkan stage, SQLSTATE dan driver code yang selamat tanpa mendedahkan SQL, credential atau data pengguna.',
      6 => 'Identiti service cron dipendekkan kepada ONEID-CRON supaya serasi dengan medan triggered_by legacy dan kekal jelas dalam audit.',
      7 => 'Log External Sync dirotasi setiap minggu, dimampatkan dan disimpan sehingga 52 minggu untuk sejarah operasi setahun.',
      8 => 'Runtime staging mengekalkan permission 640 serta ownership iqs:www-data supaya CLI cron dan PHP-FPM boleh membaca konfigurasi private yang sama.',
      9 => 'Contract cron, transaction, rollback, reconciliation, metadata release dan validasi operasi staging telah disahkan lulus.',
    ),
    'en' =>
    array (
      0 => 'Staging External Sync now runs exactly at the start of every hour for Staff, Undergraduate and ODL sources.',
      1 => 'Each source is compared separately and exits as SKIP_NO_CHANGES without a transaction or database mutation when no action is required.',
      2 => 'Staging can process every safe change, including New, Update, Deactivate and Reactivate, without cron volume limits.',
      3 => 'Integrity controls for failed or empty sources, identity collisions, plan drift and reconciliation mismatches remain fail-closed.',
      4 => 'Filesystem flock and the database advisory lock prevent cron, manual sync or an earlier run from writing concurrently.',
      5 => 'Cron database failures now report a safe stage, SQLSTATE and driver code without exposing SQL, credentials or user data.',
      6 => 'The cron service identity is shortened to ONEID-CRON for compatibility with the legacy triggered_by field and clear audit attribution.',
      7 => 'External Sync logs are rotated weekly, compressed and retained for up to 52 weeks to provide one year of operational history.',
      8 => 'The staging runtime retains mode 640 and iqs:www-data ownership so CLI cron and PHP-FPM can read the same private configuration.',
      9 => 'Cron, transaction, rollback, reconciliation, release metadata and staging operational validation contracts were verified successfully.',
    ),
  ),
  'release-2.7.4' =>
  array (
    'version' => '2.7.4',
    'date' => '2026-08-05',
    'bm' =>
    array (
      0 => 'Active Sessions Administrator direka semula kepada jadual empat kolum yang lebih padat dengan carian, filter lifecycle, metrik status dan pagination 10 rekod secara default.',
      1 => 'Administrator boleh membatalkan sesi Due atau Expired melalui aliran Preview dan Apply yang memerlukan Step-Up, sebab pentadbiran serta exact confirmation.',
      2 => 'Pembatalan sesi menggunakan opaque one-use target, transaction tepat, perlindungan self-lockout dan audit correlation supaya sesi tidak boleh ditamatkan sewenang-wenangnya.',
      3 => 'Centralized return-context memulihkan tab dan subtab Administrator yang tepat selepas Step-Up sebelum transaksi Preview atau Apply disambung semula.',
      4 => 'Audit Log kini memuat rekod tarikh hari ini secara automatik apabila tab dibuka tanpa menunggu Administrator menekan Search.',
      5 => 'Kad profil Administrator menggunakan cover OneID, badge peranan, foto bulat, nama, nombor staf, jabatan, jawatan dan pemilih bahasa dalam susun atur profesional.',
      6 => 'Foto profil Administrator dan pengguna Active Sessions dimuat melalui resolver OneID same-origin yang berkongsi sumber Staff dan Pelajar serta fallback tempatan.',
      7 => 'Resolver foto pengguna lain hanya tersedia kepada Administrator dengan sesi SSO aktif, ID tervalidasi, HTTPS, semakan TLS, timeout, had saiz dan pengesahan MIME.',
      8 => 'Dashboard pengguna kini memaparkan badge peranan PENGGUNA atau USER mengikut bahasa dengan gaya visual yang selaras dengan kad Administrator.',
      9 => 'Contract multilingual, profile photo, Active Sessions, controlled revocation dan return-context telah disahkan lulus sebelum deployment staging.',
    ),
    'en' =>
    array (
      0 => 'Administrator Active Sessions has been redesigned as a compact four-column table with search, lifecycle filters, status metrics and 10-row pagination by default.',
      1 => 'Administrators can revoke Due or Expired sessions through a Preview and Apply flow that requires Step-Up, an administrative reason and exact confirmation.',
      2 => 'Session revocation uses an opaque one-use target, an exact transaction, self-lockout protection and correlation auditing so sessions cannot be ended arbitrarily.',
      3 => 'Centralized return context restores the exact Administrator tab and subtab after Step-Up before the Preview or Apply transaction resumes.',
      4 => 'Audit Log now loads records for today automatically when its tab opens without waiting for the Administrator to press Search.',
      5 => 'The Administrator profile card now uses a OneID cover, role badge, circular photo, name, staff number, department, position and language selector in a professional layout.',
      6 => 'Administrator and Active Sessions profile photos are loaded through a same-origin OneID resolver that shares Staff and Student sources with a local fallback.',
      7 => 'Resolving another user photo is limited to Administrators with an active SSO session, validated IDs, HTTPS, TLS checks, timeouts, size limits and MIME validation.',
      8 => 'The user dashboard now displays a localized PENGGUNA or USER role badge with a visual style aligned to the Administrator card.',
      9 => 'Multilingual, profile photo, Active Sessions, controlled revocation and return-context contracts were verified before staging deployment.',
    ),
  ),
  'release-2.7.3' =>
  array (
    'version' => '2.7.3',
    'date' => '2026-08-05',
    'bm' =>
    array (
      0 => 'External Sync kini menyediakan cronjob conditional untuk memeriksa dan menyelaraskan sumber Staff, Prasiswazah dan ODL secara automatik mengikut jadual operasi.',
      1 => 'Cron memisahkan setiap source kepada plan dan keputusan tersendiri supaya data Staff, Prasiswazah dan ODL tidak bercampur semasa synchronization.',
      2 => 'Apabila tiada perubahan, cron tamat sebagai SKIP_NO_CHANGES tanpa transaction, header Sync Log atau perubahan database.',
      3 => 'New, Update dan Reactivate hanya diproses apabila jumlah berada dalam threshold khusus source dan semua safety gate lulus.',
      4 => 'Sebarang Deactivate, collision, anomaly, warning atau perubahan melebihi threshold disekat dan diserahkan kepada Administrator untuk semakan manual.',
      5 => 'Cron menggunakan one-use exact-plan approval, fresh snapshot verification, advisory lock, transaction, reconciliation dan audit marker yang sama ketat dengan safe writer.',
      6 => 'Dry-run dan emergency-disable flag membolehkan scheduler diperiksa atau dihentikan tanpa menutup fungsi Preview dan Apply manual Administrator.',
      7 => 'Staff provenance, UG membership dan ODL source isolation melindungi ownership akaun serta mencegah perubahan merentas sumber.',
      8 => 'CLI runner menolak akses HTTP, tidak menggunakan session Administrator dan menghasilkan output beraudit tanpa IC, matrik, nama atau e-mel.',
      9 => 'Contract cron, sync safety, source-scoped Apply dan ODL operational regression telah disahkan lulus sebelum deployment staging.',
    ),
    'en' =>
    array (
      0 => 'External Sync now provides a conditional cron job to check and synchronize Staff, Undergraduate and ODL sources automatically according to the operational schedule.',
      1 => 'Cron separates every source into its own plan and outcome so Staff, Undergraduate and ODL data cannot be mixed during synchronization.',
      2 => 'When there are no changes, cron exits with SKIP_NO_CHANGES without a transaction, Sync Log header or database mutation.',
      3 => 'New, Update and Reactivate actions are processed only when their counts are within source-specific thresholds and every safety gate passes.',
      4 => 'Any Deactivate, collision, anomaly, warning or over-threshold change is blocked and handed to an Administrator for manual review.',
      5 => 'Cron uses one-use exact-plan approval, fresh snapshot verification, advisory locking, transactions, reconciliation and an audit marker with the same strict safe writer.',
      6 => 'Dry-run and emergency-disable flags allow the scheduler to be assessed or stopped without disabling Administrator manual Preview and Apply.',
      7 => 'Staff provenance, UG membership and ODL source isolation protect account ownership and prevent cross-source mutation.',
      8 => 'The CLI runner rejects HTTP access, does not use an Administrator session and produces audited output without identity numbers, student IDs, names or e-mail addresses.',
      9 => 'Cron, sync safety, source-scoped Apply and ODL operational regression contracts were verified before staging deployment.',
    ),
  ),
  'release-2.7.2' =>
  array (
    'version' => '2.7.2',
    'date' => '2026-08-02',
    'bm' =>
    array (
      0 => 'Administrator kini boleh mengurus banner halaman login secara terus melalui Configuration tanpa mengubah source code bagi setiap pertukaran kandungan.',
      1 => 'Banner menyokong imej BM dan English yang berasingan atau satu imej bersama, lengkap dengan teks alternatif mengikut bahasa.',
      2 => 'Administrator boleh mencipta draf, mengemas kini imej atau jadual, menerbitkan, menyahaktifkan, menyusun semula dan rollback banner terdahulu.',
      3 => 'Muat naik JPEG, PNG dan WebP divalidasi serta dinormalisasikan kepada aset WebP immutable dengan kawalan dimensi, saiz dan integriti.',
      4 => 'Halaman login memilih banner mengikut bahasa pengguna dan kembali kepada banner statik dengan selamat jika aset dinamik tidak tersedia.',
      5 => 'Operasi banner mempunyai versioning, audit, backup, reconciliation dan rollback untuk menyokong pemulihan serta pengesanan perubahan.',
      6 => 'Dialog pengurusan banner menggunakan SweetAlert, dan imej yang sama boleh digunakan semula tanpa menghasilkan ralat aset pendua.',
      7 => 'Configuration kini disusun kepada General, Security, Multi-Factor Authentication dan Audit dengan subtab serta kandungan yang terasing dengan betul.',
      8 => 'Garisan header mempunyai pergerakan cahaya kiri-kanan yang lebih lembut, nipis dan berbilang warna selaras dengan identiti OneID.',
      9 => 'Halaman cabaran User 2FA kini menggunakan penerangan keselamatan akaun pengguna yang berasingan daripada mesej Admin Step-Up.',
    ),
    'en' =>
    array (
      0 => 'Administrators can now manage login-page banners directly through Configuration without changing source code for every content update.',
      1 => 'Banners support separate BM and English images or one shared image, with language-specific alternative text.',
      2 => 'Administrators can create drafts, update images or schedules, publish, inactivate, reorder and roll back earlier banners.',
      3 => 'JPEG, PNG and WebP uploads are validated and normalized into immutable WebP assets with dimension, size and integrity controls.',
      4 => 'The login page selects banners according to the user language and safely returns to static banners when dynamic assets are unavailable.',
      5 => 'Banner operations include versioning, audit, backup, reconciliation and rollback to support recovery and change traceability.',
      6 => 'Banner management dialogs now use SweetAlert, and an identical image can be reused without causing a duplicate-asset error.',
      7 => 'Configuration is now organized into General, Security, Multi-Factor Authentication and Audit with correctly isolated subtabs and content.',
      8 => 'The header line now has a softer, thinner, multicolour left-to-right motion aligned with the OneID visual identity.',
      9 => 'The User 2FA challenge now uses user-account security guidance that is separate from the Admin Step-Up message.',
    ),
  ),
  'release-2.7.1' =>
  array (
    'version' => '2.7.1',
    'date' => '2026-08-01',
    'bm' =>
    array (
      0 => 'ODL di staging kini menyokong Preview dan Apply secara on-demand tanpa perlu mengubah operational window bagi setiap ujian.',
      1 => 'Had volum Sync kini menjadi amaran dan pengesahan tambahan; perubahan sah dalam jumlah besar boleh diteruskan sementara ralat integriti kekal disekat.',
      2 => 'Kiraan External Sync, badge dan ringkasan kini disegarkan selepas child modal ditutup supaya keputusan terkini dipaparkan tanpa refresh halaman.',
      3 => 'Paparan Protected Manual Accounts dikeluarkan daripada UI Sync, tetapi perlindungan backend terhadap akaun manual kekal aktif.',
      4 => 'Pengguna baharu yang masuk melalui MyDigital ID kini boleh menetapkan kata laluan OneID tanpa memasukkan kata laluan lama yang tidak pernah diketahui.',
      5 => 'Initial password setup MyDigital ID menggunakan grant lima minit yang terikat kepada pengguna dan sesi, kemudian membatalkan token lama serta meminta login semula.',
      6 => 'Halaman padanan MyDigital ID yang gagal kini memberi arahan generik yang lebih membantu untuk login menggunakan ID OneID tanpa mendedahkan status akaun.',
      7 => 'Menu Account Security dan halaman enrollment kini memuatkan dependency User MFA secara terus supaya tersedia secara konsisten pada staging.',
      8 => 'Header dashboard pengguna dan admin mempunyai pergerakan cahaya halus pada garisan bawah dengan sokongan Reduce Motion.',
      9 => 'Paparan Account Security diperkemas, lebih padat dan menyokong enrollment serta pemulihan Microsoft Authenticator dengan lebih jelas.',
    ),
    'en' =>
    array (
      0 => 'ODL staging now supports on-demand Preview and Apply without changing the operational window for every test.',
      1 => 'Sync volume limits are now warnings with additional confirmation; legitimate large changes can proceed while integrity errors remain blocked.',
      2 => 'External Sync counts, badges and summaries now refresh when a child modal closes so the latest result appears without a page refresh.',
      3 => 'Protected Manual Accounts has been removed from the Sync UI while backend protection for manual accounts remains active.',
      4 => 'New users signing in through MyDigital ID can now set a OneID password without entering an old password they never knew.',
      5 => 'MyDigital ID initial password setup uses a five-minute user- and session-bound grant, then revokes old tokens and requires sign-in again.',
      6 => 'The unmatched MyDigital ID page now gives more helpful generic guidance for signing in with a OneID ID without exposing account status.',
      7 => 'The Account Security menu and enrollment page now load User MFA dependencies directly for consistent staging availability.',
      8 => 'User and administrator dashboard headers now include a subtle moving light along the lower line with Reduce Motion support.',
      9 => 'Account Security has been refined into a more compact view with clearer Microsoft Authenticator enrollment and recovery.',
    ),
  ),
  'release-2.7.0' =>
  array (
    'version' => '2.7.0',
    'date' => '2026-07-31',
    'bm' =>
    array (
      0 => 'User 2FA kini tersedia sepenuhnya untuk akaun staf dan pelajar aktif melalui e-mel OTP atau Microsoft Authenticator.',
      1 => 'Polisi global User 2FA menyediakan mod Off, Enrollment, Pilot Enforced dan Enforced dengan runtime/database parity yang fail-closed.',
      2 => 'Pentadbir boleh mengaktifkan enforcement secara berasingan untuk kategori Staff dan Student tanpa menjejaskan Admin 2FA.',
      3 => 'Akaun pentadbir dikecualikan daripada User 2FA dan terus menggunakan Admin Step-Up 2FA yang berasingan.',
      4 => 'Temporary User 2FA Exemption membolehkan seorang pengguna aktif dikecualikan untuk tempoh terhad dengan sebab, rujukan dan compensating control.',
      5 => 'Carian exemption menyokong ID, nama dan identity reference secara masa nyata dengan keputusan terhad dan boleh ditatal.',
      6 => 'Pengguna boleh enroll, memilih dan revoke Microsoft Authenticator melalui Account Security, termasuk pemulihan revoke menggunakan OTP e-mel.',
      7 => 'Enrollment, OTP, TOTP, session rotation, rate limit, audit dan recovery dilindungi oleh kawalan backend serta UI BM/English.',
      8 => 'Full enforcement memerlukan dua pentadbir aktif yang berbeza, exact confirmation, versioned policy history dan correlated audit.',
      9 => 'User 2FA full enforcement telah diaktifkan di staging bagi Staff dan Student dengan e-mel serta TOTP enabled.',
    ),
    'en' =>
    array (
      0 => 'User 2FA is now fully available to active staff and student accounts through e-mail OTP or Microsoft Authenticator.',
      1 => 'The global User 2FA policy provides Off, Enrollment, Pilot Enforced and Enforced modes with fail-closed runtime/database parity.',
      2 => 'Administrators can enable enforcement separately for Staff and Student categories without affecting Admin 2FA.',
      3 => 'Administrator accounts are excluded from User 2FA and continue to use the separate Admin Step-Up 2FA flow.',
      4 => 'Temporary User 2FA Exemption allows one active user to be excluded for a limited period with a reason, reference and compensating control.',
      5 => 'Exemption search supports real-time ID, name and identity-reference lookup with limited scrollable results.',
      6 => 'Users can enroll, select and revoke Microsoft Authenticator through Account Security, including e-mail OTP recovery for revocation.',
      7 => 'Enrollment, OTP, TOTP, session rotation, rate limits, audit and recovery are protected by backend controls and BM/English UI.',
      8 => 'Full enforcement requires two distinct active administrators, exact confirmation, versioned policy history and correlated audit.',
      9 => 'User 2FA full enforcement has been activated in staging for Staff and Student with e-mail and TOTP enabled.',
    ),
  ),
  'release-2.6.4' =>
  array (
    'version' => '2.6.4',
    'date' => '2026-07-26',
    'bm' =>
    array (
      0 => 'Log masuk menggunakan MyDigital ID kini tersedia sebagai pilihan kedua di samping log masuk OneID biasa.',
      1 => 'Hanya pengguna yang mempunyai akaun OneID aktif dibenarkan masuk; pengguna lain menerima mesej penolakan yang jelas tanpa akaun baharu dicipta.',
      2 => 'Pengguna boleh mencuba akaun MyDigital ID yang lain, dan proses log keluar kini turut menamatkan sesi MyDigital ID.',
      3 => 'Paparan log masuk, mesej BM/English, rekod aktiviti dan perlindungan maklumat log masuk telah dipertingkatkan.',
      4 => 'Pengguna yang tidak mempunyai akses OneID kini dibawa ke halaman khas dengan mesej yang jelas serta pilihan untuk mencuba akaun MyDigital ID lain.',
      5 => 'Alamat OneID yang tidak sah kini memaparkan halaman 404 khas yang lebih jelas dan mesra pengguna.',
    ),
    'en' =>
    array (
      0 => 'Sign in with MyDigital ID is now available as a second option alongside the standard OneID login.',
      1 => 'Only users with an active OneID account may sign in; other users receive a clear rejection message and no new account is created.',
      2 => 'Users can try a different MyDigital ID account, and signing out now also ends the MyDigital ID session.',
      3 => 'The login screen, BM/English messages, activity records and protection of sign-in information have been improved.',
      4 => 'Users without OneID access are now taken to a dedicated page with a clear message and an option to try another MyDigital ID account.',
      5 => 'Invalid OneID addresses now display a clearer, user-friendly custom 404 page.',
    ),
  ),
  1 =>
  array (
    'version' => '2.6.3',
    'date' => '2026-07-26',
    'bm' =>
    array (
      0 => 'Infrastruktur locale BM/English dilengkapkan dengan Bahasa Melayu sebagai default dan hard fallback, English sebagai bahasa kedua serta preference pengguna, sesi dan cookie yang divalidasi.',
      1 => 'Login, Pemulihan Kata Laluan, OTP, User Dashboard dan Administrator Dashboard kini menyokong pertukaran BM/English tanpa mengubah authentication, authorization atau ACL.',
      2 => 'Active Sessions, Audit Log, Sync Audit, Configuration dan senarai pengguna kategori dilengkapkan dengan label, pagination serta loading, empty, success dan error state BM/English.',
      3 => 'External Sync Summary, Staff, Prasiswazah dan ODL kini mempunyai presentation BM/English sementara source code, plan hash, counts, correlation ID dan exact confirmation kekal canonical.',
      4 => 'Admin Step-Up menyokong arahan dan feedback BM/English bagi OTP e-mel, Microsoft Authenticator, enrollment, reset, expiry dan rate limit tanpa mengubah purpose, factor atau grant security.',
      5 => 'API/AJAX, notification dan e-mel dalam skop dilengkapi stable response code serta translation key sambil mengekalkan legacy msg untuk compatibility.',
      6 => 'Metadata aplikasi dan kategori menggunakan translation tables additive, fallback kepada metadata asal, audit history dan optimistic concurrency tanpa mengubah ID, URL, SSO atau ACL.',
      7 => 'Semua 84 rekod metadata diklasifikasi; 33 terjemahan English baharu dan 33 audit history telah direkonsiliasi dengan content completeness 100%.',
      8 => 'Login dan User Dashboard berkongsi 8 FAQ BM/English daripada satu sumber kandungan dengan explicit fallback notice dan accessibility semantics.',
      9 => 'Administrator Version Releases mempunyai parity 37/37 release dan 217/217 changelog BM/English dengan digest approval fail-closed serta fallback penuh kepada BM.',
      10 => 'English User Manual PDF ditangguhkan oleh owner; MANUAL_SALAM.pdf kekal rasmi dan pengguna English menerima notis fallback BM yang jelas.',
      11 => 'Audit pre-ML9 merekonsiliasi semua fasa multilingual sebagai PASS/CLOSED pada Local WSL dengan document inventory 149, duplicate 0, missing target 0 dan blocking code 0.',
    ),
    'en' =>
    array (
      0 => 'The BM/English locale infrastructure is complete with Bahasa Melayu as the default and hard fallback, English as the secondary language, and validated user, session and cookie preferences.',
      1 => 'Login, Password Recovery, OTP, the User Dashboard and the Administrator Dashboard now support BM/English switching without changing authentication, authorization or ACL behaviour.',
      2 => 'Active Sessions, Audit Log, Sync Audit, Configuration and category user lists now provide BM/English labels, pagination, and loading, empty, success and error states.',
      3 => 'External Sync Summary, Staff, Undergraduate and ODL now provide BM/English presentation while source codes, plan hashes, counts, correlation IDs and exact confirmations remain canonical.',
      4 => 'Admin Step-Up provides BM/English guidance and feedback for e-mail OTP, Microsoft Authenticator, enrollment, reset, expiry and rate limits without changing purpose, factor or grant security.',
      5 => 'In-scope API/AJAX responses, notifications and e-mails now use stable response codes and translation keys while retaining legacy msg compatibility.',
      6 => 'Application and category metadata use additive translation tables, original-metadata fallback, audit history and optimistic concurrency without changing IDs, URLs, SSO or ACL configuration.',
      7 => 'All 84 metadata records were classified; 33 new English translations and 33 audit-history records were reconciled with 100% content completeness.',
      8 => 'Login and the User Dashboard share 8 BM/English FAQs from one content source with an explicit fallback notice and accessible semantics.',
      9 => 'Administrator Version Releases provide parity for 37/37 releases and 217/217 BM/English changelog items with fail-closed approval binding and full BM fallback.',
      10 => 'The English User Manual PDF is deferred by the owner; MANUAL_SALAM.pdf remains official and English users receive a clear BM fallback notice.',
      11 => 'The pre-ML9 audit reconciled every multilingual phase as PASS/CLOSED on Local WSL with 149 document identities, 0 duplicates, 0 missing targets and 0 blocking codes.',
    ),
  ),
  2 =>
  array (
    'version' => '2.6.2',
    'date' => '2026-07-24',
    'bm' =>
    array (
      0 => 'ODL Fasa 9 Manual Operational Sync ditutup selepas exact-plan Apply menambah 18 akaun NEW melalui header 50; active membership STUDENT_ODL_PG meningkat kepada 71.',
      1 => 'F9A melengkapkan semua tindakan manual ODL: UPDATE dan DEACTIVATE melalui header 52 serta REACTIVATE melalui header 53 dengan reconciliation dan rollback readiness PASS.',
      2 => 'Post-Apply Preview kembali zero action; akaun ujian kekal kategori Pelajar/10, membership ODL aktif serta login dan ACL smoke test PASS.',
      3 => 'Badge sumber kini hanya memaparkan tindakan sebenar dan tidak lagi mengira rekod KEEP yang telah diselaraskan.',
      4 => 'Menu Add User dinamakan semula kepada Sync User dan setiap sumber Staff, Prasiswazah serta ODL mengekalkan Preview/Apply yang terasing.',
      5 => 'Paparan External Sync menggunakan istilah mesra admin, keputusan berorientasikan tindakan dan bahagian teknikal tertutup untuk rujukan audit.',
      6 => 'Ringkasan Sync memaparkan jumlah rekod, tindakan dan status berasingan bagi Staff, Prasiswazah dan ODL tanpa fungsi Apply.',
      7 => 'Parent modal Sync User serta semua child modal Summary, Preview/Apply dan Manual Add User direka semula dengan warna konsisten, layout responsif dan hierarchy visual profesional.',
      8 => 'Label akaun manual dilindungi dan konflik identiti kini diasingkan supaya status keselamatan lebih mudah difahami.',
      9 => 'Exact confirmation, source isolation, transaction safety dan audit gate tidak diubah oleh penambahbaikan UI.',
      10 => 'Automatic scheduler, unattended mutation dan production rollout ODL kekal disabled.',
    ),
    'en' =>
    array (
      0 => 'ODL Phase 9 Manual Operational Sync closed after exact-plan Apply added 18 NEW accounts via header 50; active membership STUDENT_ODL_PG increased to 71.',
      1 => 'F9A completes all ODL manual actions: UPDATE and DEACTIVATE through header 52 and REACTIVATE through header 53 with reconciliation and rollback readiness PASS.',
      2 => 'Post-Apply Preview returns zero action; permanent test account for Student/10 category, active ODL membership and login and ACL smoke test PASS.',
      3 => 'Resource badges now only display actual actions and no longer count adjusted KEEP records.',
      4 => 'The Add User menu is renamed to Sync User and each Staff, Undergraduate and ODL resource maintains a separate Preview/Apply.',
      5 => 'External Sync View uses admin-friendly terminology, action-oriented results and closed technical sections for audit reference.',
      6 => 'Sync Summary displays separate record totals, actions and statuses for Staff, Undergraduate and ODL without the Apply function.',
      7 => 'Parent modal Sync User as well as all child modal Summary, Preview/Apply and Manual Add User redesigned with consistent colors, responsive layout and professional visual hierarchy.',
      8 => 'Manual account labels are protected and identity conflicts are now separated so security status is easier to understand.',
      9 => 'Exact confirmation, source isolation, transaction safety and audit gate are not changed by UI improvements.',
      10 => 'Automatic scheduler, unattended mutation and ODL production rollout remain disabled.',
    ),
  ),
  3 =>
  array (
    'version' => '2.6.1',
    'date' => '2026-07-24',
    'bm' =>
    array (
      0 => 'ODL Fasa 7 Controlled Pilot selesai dengan tiga akaun NEW, provenance STUDENT_ODL_PG, reconciliation tepat serta login dan ACL smoke test PASS.',
      1 => 'ODL Fasa 8 Controlled Full Apply selesai untuk 50 akaun NEW; keseluruhan 53 membership ODL aktif dan semua tindakan bukan NEW kekal sifar.',
      2 => 'Cross-source isolation kini meliputi Staff, Undergraduate, ODL dan akaun manual supaya Preview serta Apply tidak mencampurkan ownership sumber.',
      3 => 'Fasa 9 menyediakan ODL Manual Operational Preview melalui modal Admin yang sama seperti Undergraduate dengan plan hash, expiry dan action counts.',
      4 => 'ODL operational Apply mempunyai private gate berasingan dan kekal disabled sehingga exact-plan authorization diterima.',
      5 => 'Matrik, IC dan external membership collision diblock sebelum approval; persistence turut menyemak semula ownership dalam transaction.',
      6 => 'E-mel kosong daripada ODL tidak memadam e-mel OneID sedia ada, manakala akaun manual kekal protected.',
      7 => 'External Sync Summary kekal read-only dan notifikasi parent modal dipaparkan hanya apabila sesuatu sumber mempunyai tindakan atau block.',
      8 => 'Automatic scheduler, unattended mutation dan production rollout ODL kekal disabled.',
      9 => 'Dokumen audit merekod Fasa 0 hingga 8 PASS/CLOSED serta Fasa 9 Preview Ready di bawah ONEID-ODL-F9-20260724-01.',
    ),
    'en' =>
    array (
      0 => 'ODL Phase 7 Controlled Pilot completed with three NEW accounts, provenance STUDENT_ODL_PG, accurate reconciliation and login and ACL smoke test PASS.',
      1 => 'ODL Phase 8 Controlled Full Apply completed for 50 NEW accounts; a total of 53 active ODL memberships and all non-NEW actions remain zero.',
      2 => 'Cross-source isolation now covers Staff, Undergraduate, ODL and manual accounts so that Preview and Apply do not mix resource ownership.',
      3 => 'Phase 9 provides ODL Manual Operational Preview through the same Admin capital as Undergraduate with plan hash, expiry and action counts.',
      4 => 'ODL operational Apply has a separate private gate and remains disabled until exact-plan authorization is received.',
      5 => 'Matrik, IC and external membership collision blocked before approval; persistence also checks the ownership in the transaction.',
      6 => 'Empty emails from ODL do not delete existing OneID emails, while manual accounts remain protected.',
      7 => 'External Sync Summary remains read-only and the modal parent notification is displayed only when a resource has an action or block.',
      8 => 'Automatic scheduler, unattended mutation and ODL production rollout remain disabled.',
      9 => 'The audit document records Phases 0 to 8 PASS/CLOSED and Phase 9 Preview Ready under ONEID-ODL-F9-20260724-01.',
    ),
  ),
  4 =>
  array (
    'version' => '2.6.0',
    'date' => '2026-07-23',
    'bm' =>
    array (
      0 => 'External Sync dipisahkan kepada Summary, Staff, Undergraduate dan ODL supaya status serta tindakan setiap sumber tidak bercampur.',
      1 => 'Staff dan Undergraduate menggunakan Preview serta Operational Apply berasingan yang diikat kepada source, one-time approval dan fresh writer plan.',
      2 => 'ODL postgraduate diperkenalkan melalui provenance schema, adapter MySQL read-only, TLS fail-closed, data-quality audit dan Shadow Preview.',
      3 => 'Parent modal menerima notifikasi source-specific, Preview modal diperluas dan child modal kembali kepada pilihan utama apabila ditutup.',
      4 => 'ODL Apply dan automatic scheduler kekal disabled pada baseline release ini.',
    ),
    'en' =>
    array (
      0 => 'External Sync is separated into Summary, Staff, Undergraduate and ODL so that the status and actions of each resource are not mixed.',
      1 => 'Staff and Undergraduate use separate Preview and Operational Apply which are tied to source, one-time approval and fresh writer plan.',
      2 => 'ODL postgraduate is introduced through provenance schema, MySQL read-only adapter, TLS file-closed, data-quality audit and Shadow Preview.',
      3 => 'Parent modal receives source-specific notifications, Preview modal is expanded and child modal returns to main selection when closed.',
      4 => 'ODL Apply and automatic scheduler remain disabled on this baseline release.',
    ),
  ),
  5 =>
  array (
    'version' => '2.5.4',
    'date' => '2026-07-22',
    'bm' =>
    array (
      0 => 'Admin Step-Up 2FA kini melindungi akses Administrator dan perubahan konfigurasi sensitif menggunakan OTP e-mel atau Microsoft Authenticator.',
      1 => 'Lifecycle TOTP lengkap merangkumi enrollment QR lokal, secret terenkripsi, confirmation, anti-replay, preference per-admin serta reset dan revoke beraudit.',
      2 => 'Grant keselamatan diikat kepada admin, session, browser dan purpose; session serta CSRF dirotasi selepas verification berjaya.',
      3 => 'Controlled bootstrap, lifetime grant, audit berstruktur, recovery OTP e-mel, monitoring F7.6 dan rollback fail-closed telah dilaksanakan dan diuji di staging.',
      4 => 'Issuer Authenticator boleh dibezakan mengikut environment, manakala keyring kekal di luar Git dan mesti boleh dibaca oleh akaun PHP-FPM.',
      5 => 'Reset Authenticator menggunakan SweetAlert dan enrollment kini memberikan diagnosis keyring yang selamat tanpa mendedahkan path, secret atau OTP.',
    ),
    'en' =>
    array (
      0 => 'Admin Step-Up 2FA now protects Administrator access and sensitive configuration changes using email OTP or Microsoft Authenticator.',
      1 => 'Complete TOTP lifecycle includes local QR enrollment, encrypted secret, confirmation, anti-replay, per-admin preference as well as audited reset and revoke.',
      2 => 'Security grants are tied to admin, session, browser and purpose; session and CSRF are rotated after successful verification.',
      3 => 'Controlled bootstrap, lifetime grant, structured audit, email OTP recovery, F7.6 monitoring and fail-closed rollback have been implemented and tested in staging.',
      4 => 'The Issuer Authenticator can be differentiated by environment, while the keyring remains outside of Git and must be readable by the PHP-FPM account.',
      5 => 'Reset Authenticator using SweetAlert and enrollment now provides secure keyring diagnosis without revealing path, secret or OTP.',
    ),
  ),
  6 =>
  array (
    'version' => '2.5.3',
    'date' => '2026-07-19',
    'bm' =>
    array (
      0 => 'Gambar profil dashboard pengguna kini melalui resolver same-origin supaya Firefox tidak lagi membuat probe terus ke domain gambar luar.',
      1 => 'Akaun tanpa ID gambar, ID tidak sah atau akaun TEST terus menerima fallback profile lokal tanpa request upstream.',
      2 => 'Resolver menguatkuasakan active session, HTTPS/TLS verification, timeout, had 2MB dan decoded MIME validation sebelum gambar dipaparkan.',
      3 => 'Kegagalan upstream, gambar tiada atau respons bukan imej menghasilkan silhouette lokal tanpa OpaqueResponseBlocking pada browser.',
    ),
    'en' =>
    array (
      0 => 'User dashboard profile images now go through the same-origin resolver so that Firefox no longer probes directly to external image domains.',
      1 => 'Accounts without picture ID, invalid ID or TEST account continue to receive local profile fallback without upstream request.',
      2 => 'Resolver enforces active session, HTTPS/TLS verification, timeout, 2MB limit and decoded MIME validation before the image is displayed.',
      3 => 'Upstream failure, no image or non-image response produces a local silhouette without OpaqueResponseBlocking on the browser.',
    ),
  ),
  7 =>
  array (
    'version' => '2.5.2',
    'date' => '2026-07-19',
    'bm' =>
    array (
      0 => 'Menu Back to My Account dalam Administrator kini menggunakan warna kuning yang sama dengan menu Administrator pada dashboard pengguna.',
      1 => 'Warna normal, hover dan focus menggunakan class pill-yellow sedia ada tanpa mengubah navigasi atau authorization.',
    ),
    'en' =>
    array (
      0 => 'The Back to My Account menu in the Administrator now uses the same yellow color as the Administrator menu on the user dashboard.',
      1 => 'Normal color, hover and focus use the existing pill-yellow class without changing navigation or authorization.',
    ),
  ),
  8 =>
  array (
    'version' => '2.5.1',
    'date' => '2026-07-19',
    'bm' =>
    array (
      0 => 'Audit induk Admin Step-Up 2FA kini menyatukan keperluan Configuration, SC7-SC8, Password Recovery, token lifecycle dan Active Sessions.',
      1 => 'Purpose ADMIN_ACCESS, SECURITY_CONFIGURATION_CHANGE dan ACTIVE_SESSION_REVOCATION diasingkan dengan kontrak authorization fail-closed.',
      2 => 'Dokumen meliputi controlled bootstrap, encrypted TOTP lifecycle, structured rejection, break-glass, session revocation, UAT, monitoring dan rollout gate.',
      3 => 'Kontrak dokumentasi SC7 memastikan audit induk dan dokumen handoff berkaitan kekal selaras; implementasi masih on hold.',
    ),
    'en' =>
    array (
      0 => 'Admin Step-Up 2FA master audit now consolidates Configuration, SC7-SC8, Password Recovery, token lifecycle and Active Sessions requirements.',
      1 => 'Purpose ADMIN_ACCESS, SECURITY_CONFIGURATION_CHANGE and ACTIVE_SESSION_REVOCATION are separated by a fail-closed authorization contract.',
      2 => 'Documents cover controlled bootstrap, encrypted TOTP lifecycle, structured rejection, break-glass, session revocation, UAT, monitoring and rollout gate.',
      3 => 'The SC7 documentation contract ensures that master audits and related handoff documents remain consistent; implementation is still on hold.',
    ),
  ),
  9 =>
  array (
    'version' => '2.5.0',
    'date' => '2026-07-19',
    'bm' =>
    array (
      0 => 'Pagination Audit History kini sepadan dengan Active Sessions dan berada di kanan bawah pada desktop.',
      1 => 'Ruang selepas jadual, saiz butang, hover, focus dan disabled state diseragamkan untuk paparan yang lebih kemas.',
      2 => 'Pada skrin kecil pagination kembali ke tengah bagi mengekalkan kawalan yang mudah dicapai.',
    ),
    'en' =>
    array (
      0 => 'Pagination Audit History now matches Active Sessions and is in the bottom right of the desktop.',
      1 => 'The space after the table, button size, hover, focus and disabled state are standardized for a neater display.',
      2 => 'On small screens pagination returns to the center to maintain easy-to-reach controls.',
    ),
  ),
  10 =>
  array (
    'version' => '2.4.4',
    'date' => '2026-07-19',
    'bm' =>
    array (
      0 => 'Audit History dipadatkan daripada tujuh kolum kepada empat kumpulan maklumat yang mudah diimbas.',
      1 => 'Semua header dan data audit menggunakan top-left alignment, lebar kolum stabil, ellipsis serta tooltip bagi reason yang panjang.',
      2 => 'Outcome, revision, perubahan, actor, reason code dan reference disusun secara hierarki dengan paparan responsif seperti Active Sessions.',
    ),
    'en' =>
    array (
      0 => 'Audit History is condensed from seven columns to four information groups that are easy to scan.',
      1 => 'All headers and audit data use top-left alignment, stable column width, ellipsis and tooltip for a long reason.',
      2 => 'Outcome, revision, change, actor, reason code and reference are organized hierarchically with a responsive display such as Active Sessions.',
    ),
  ),
  11 =>
  array (
    'version' => '2.4.3',
    'date' => '2026-07-19',
    'bm' =>
    array (
      0 => 'Configuration kini menggunakan tiga tab khusus: Authentication Policy, Account Recovery dan Audit History.',
      1 => 'Setiap tab mengekalkan form serta kawalan keselamatan sedia ada sambil mengurangkan panjang halaman dan memudahkan navigasi.',
      2 => 'Tab responsif boleh discroll pada skrin kecil, menggunakan status aksesibiliti tab, dan memuat semula audit history apabila dibuka.',
    ),
    'en' =>
    array (
      0 => 'Configuration now uses three specific tabs: Authentication Policy, Account Recovery and Audit History.',
      1 => 'Each tab maintains existing forms and security controls while reducing page length and simplifying navigation.',
      2 => 'Responsive tabs can be scrolled on a small screen, use the tab\'s accessibility status, and refresh the audit history when opened.',
    ),
  ),
  12 =>
  array (
    'version' => '2.4.2',
    'date' => '2026-07-19',
    'bm' =>
    array (
      0 => 'Fasa 3 Configuration kini mewajibkan change reason dan mengikat setiap preview kepada <code>configuration_version</code> semasa.',
      1 => 'Optimistic locking menolak Apply daripada preview lama supaya perubahan dua admin tidak boleh saling menindih tanpa amaran.',
      2 => 'Structured Configuration History merekod success/rejection, actor, revision, before/after, reason code, change reason dan correlation tanpa token atau credential.',
      3 => 'Halaman Configuration memaparkan Last Changed serta history read-only newest-first dengan pagination.',
      4 => 'Forward/down migration dan concurrency contract tersedia; activation staging memerlukan migration check/apply sebelum reload PHP-FPM.',
    ),
    'en' =>
    array (
      0 => 'Phase 3 Configuration now requires a change reason and binds each preview to the current <code>configuration_version</code>.',
      1 => 'Optimistic locking rejects Apply from old previews so that changes from two admins can\'t overlap each other without warning.',
      2 => 'Structured Configuration History records success/rejection, actor, revision, before/after, reason code, change reason and correlation without tokens or credentials.',
      3 => 'The Configuration page displays Last Changed and newest-first read-only history with pagination.',
      4 => 'Forward/down migration and concurrency contract available; activation staging requires a migration check/apply before reloading PHP-FPM.',
    ),
  ),
  13 =>
  array (
    'version' => '2.4.1',
    'date' => '2026-07-19',
    'bm' =>
    array (
      0 => 'Semua nama dan kandungan dokumen release aktif telah diaudit supaya menggunakan penomboran baharu; nombor legacy hanya dikekalkan dalam jadual migrasi rasmi.',
      1 => 'Contract dokumentasi baharu menolak nama fail atau kandungan Markdown yang memperkenalkan semula release <code>2.0.5</code> dan ke atas di luar polisi migrasi.',
      2 => 'Dokumen release lama kini menggunakan canonical path <code>v2.1.0</code> hingga <code>v2.1.3</code> mengikut urutan release sebenar.',
      3 => 'Metadata pusat dan <code>package.json</code> kini menggunakan v2.4.1, release pertama selepas baseline normalisasi v2.4.0.',
      4 => 'Penomboran dependency pihak ketiga dan alamat IP bertitik tidak dianggap sebagai versi aplikasi OneID.',
    ),
    'en' =>
    array (
      0 => 'All active release document names and contents have been audited to use the new numbering; legacy numbers are only retained in official migration tables.',
      1 => 'The new documentation contract excludes filenames or Markdown content that reintroduces release <code>2.0.5</code> and above outside the migration policy.',
      2 => 'Old release documents now use the canonical path <code>v2.1.0</code> to <code>v2.1.3</code> according to the actual release order.',
      3 => 'Central metadata and <code>package.json</code> now use v2.4.1, the first release after the v2.4.0 normalization baseline.',
      4 => 'Third-party dependency numbering and dotted IP addresses are not considered OneID application versions.',
    ),
  ),
  14 =>
  array (
    'version' => '2.4.0',
    'date' => '2026-07-19',
    'bm' =>
    array (
      0 => 'Penomboran release 2.x dinormalisasi kepada lima patch setiap minor: <code>.0</code> hingga <code>.4</code>, kemudian minor seterusnya bermula semula pada <code>.0</code>.',
      1 => 'Browser UAT AS2 bagi <code>multi_session=1</code>, revoked token tanpa perubahan polisi global dan <code>multi_session=0</code> telah dilaporkan PASS oleh owner.',
      2 => 'UAT mengesahkan multiple session berfungsi apabila dibenarkan dan browser lama memerlukan login semula selepas token ditamatkan.',
      3 => 'AS3 notification, idle warning, absolute-timeout warning dan revoked reason direkod sebagai UX follow-up yang ditangguhkan oleh owner.',
      4 => 'Hard session cap, Controlled Admin Revoke, housekeeping Apply, retention 90 hari, monitoring dan penamatan compatibility refresh kekal pending dengan gate berasingan.',
      5 => 'Release ini menutup evidence dan dokumentasi UAT AS2 tanpa mengubah database, runtime configuration atau enforcement yang telah lulus.',
    ),
    'en' =>
    array (
      0 => 'The 2.x release numbering is normalized to five patches per minor: <code>.0</code> to <code>.4</code>, then the next minor starts again at <code>.0</code>.',
      1 => 'Browser UAT AS2 for <code>multi_session=1</code>, revoked token without global policy change and <code>multi_session=0</code> has been reported PASS by the owner.',
      2 => 'UAT verifies multiple sessions work when allowed and older browsers require re-login after the token expires.',
      3 => 'AS3 notification, idle warning, absolute-timeout warning and revoked reason are recorded as UX follow-up that is postponed by the owner.',
      4 => 'Hard session cap, Controlled Admin Revoke, housekeeping Apply, 90 day retention, monitoring and termination compatibility refresh remains pending with a separate gate.',
      5 => 'This release closes the evidence and documentation of UAT AS2 without changing the database, runtime configuration or enforcement that has passed.',
    ),
  ),
  15 =>
  array (
    'version' => '2.3.4',
    'date' => '2026-07-18',
    'bm' =>
    array (
      0 => 'Setiap action OneID yang terlindung kini mengikat PHP session kepada cookie SSO token yang masih aktif untuk pengguna tersebut.',
      1 => 'Apabila login baharu merevoke token lama semasa multiple session dimatikan, browser lama menerima HTTP 401 pada action atau heartbeat seterusnya.',
      2 => 'Revoked browser membersihkan cookie SSO, authenticated session state dan merotasi PHP session ID sebelum kembali ke login.',
      3 => 'Dashboard user, dashboard admin dan report user list turut menolak direct page access menggunakan token yang telah tidak aktif.',
      4 => 'Dokumen AS2 merekodkan baki UAT dua browser/PC, hard session cap, Admin Revoke, housekeeping, retention dan monitoring sebagai gate berasingan.',
    ),
    'en' =>
    array (
      0 => 'Each protected OneID action now binds the PHP session to the SSO token cookie that is still active for that user.',
      1 => 'When the new login revokes the old token while the multiple session is turned off, the old browser receives an HTTP 401 on the next action or heartbeat.',
      2 => 'Revoked browser clears the SSO cookie, authenticated session state and rotates the PHP session ID before returning to login.',
      3 => 'The user dashboard, admin dashboard and report user list also reject direct page access using tokens that have been inactive.',
      4 => 'Document AS2 records the remaining UAT of two browsers/PCs, hard session cap, Admin Revoke, housekeeping, retention and monitoring as separate gates.',
    ),
  ),
  16 =>
  array (
    'version' => '2.3.3',
    'date' => '2026-07-18',
    'bm' =>
    array (
      0 => 'Jadual Admin <b>Active Sessions</b> kini memastikan setiap nilai Issued At, Last Heartbeat, User, Device dan Status dipaparkan dalam satu baris.',
      1 => 'Kolum User hanya memaparkan nama; ID/IC penuh kekal tersedia melalui tooltip apabila tetikus berada di atas nama.',
      2 => 'Nilai panjang menggunakan ellipsis tanpa mengubah tinggi row, dan kandungan penuh bagi masa serta peranti kekal tersedia melalui tooltip.',
      3 => 'Masa revocation untuk status Grace atau Due dipindahkan ke tooltip status supaya badge kekal satu baris.',
      4 => 'Contract AS0 melindungi paparan nama sahaja dan memastikan detail sensitif yang diperlukan tidak diwujudkan sebagai baris kedua.',
    ),
    'en' =>
    array (
      0 => 'The Admin <b>Active Sessions</b> table now ensures that each Issued At, Last Heartbeat, User, Device and Status values ​​are displayed in one row.',
      1 => 'The User column only displays the name; The full ID/IC remains available via a tooltip when the mouse is over the name.',
      2 => 'Long values ​​use ellipsis without changing the row height, and the full content of time and device remains available via the tooltip.',
      3 => 'The revocation time for Grace or Due status is moved to the status tooltip so that the badge remains one line.',
      4 => 'Contract AS0 protects the display of name only and ensures that the required sensitive details are not created as a second line.',
    ),
  ),
  17 =>
  array (
    'version' => '2.3.2',
    'date' => '2026-07-18',
    'bm' =>
    array (
      0 => 'Heartbeat teknikal lima minit kini mengekalkan liveness token tanpa memperbaharui idle activity PHP; idle 30 minit dan absolute timeout 8 jam kekal berasingan.',
      1 => 'Admin <b>Active Sessions</b> menambah state <b>Refresh Window</b> dan metrik Current, Active, Refresh, Grace, Due serta Expired.',
      2 => 'Timestamp UI diperjelas sebagai <b>Issued At</b> dan <b>Last Heartbeat</b>, selari dengan lifecycle absolute token dan compatibility window 60 minit.',
      3 => 'Tool housekeeping menyediakan mod <code>--check</code> read-only serta Apply fail-closed dengan batch 500, advisory lock, transaction, typed confirmation dan exact reconciliation.',
      4 => 'Housekeeping Apply, retention purge, cron scheduler, hard multi-session cap dan controlled admin revoke kekal disabled sehingga gate operasi masing-masing diluluskan.',
    ),
    'en' =>
    array (
      0 => 'The five-minute technical heartbeat now maintains token liveness without renewing idle PHP activity; idle 30 minutes and absolute timeout 8 hours remain separate.',
      1 => 'Admin <b>Active Sessions</b> adds state <b>Refresh Window</b> and metrics Current, Active, Refresh, Grace, Due and Expired.',
      2 => 'The UI timestamp is clarified as <b>Issued At</b> and <b>Last Heartbeat</b>, in line with the token\'s absolute lifecycle and compatibility window of 60 minutes.',
      3 => 'The housekeeping tool provides <code>--check</code> read-only mode as well as Apply fail-closed with batch 500, advisory lock, transaction, typed confirmation and exact reconciliation.',
      4 => 'Housekeeping Apply, retention purge, cron scheduler, hard multi-session cap and controlled admin revoke remain disabled until their respective operation gates are approved.',
    ),
  ),
  18 =>
  array (
    'version' => '2.3.1',
    'date' => '2026-07-18',
    'bm' =>
    array (
      0 => 'Admin <b>Active Sessions</b> kini menggunakan listing read-only sebenar; Refresh, carian, filter dan pagination tidak lagi menukar status token secara tersembunyi.',
      1 => 'Response browser menggunakan explicit projection tanpa <code>token_id</code>, token hash atau policy correlation material.',
      2 => 'Lifecycle sesi membezakan <b>Current, Active, Grace, Due</b> dan <b>Expired</b> berdasarkan absolute issuance serta jadual revocation SC5.',
      3 => 'UI memisahkan <b>Issued At</b> daripada <b>Last Activity</b> serta menambah carian pengguna/peranti, status filter dan page size 10, 25 atau 50.',
      4 => 'Contract dan preflight AS0 mengesahkan bounded query, zero mutation dan forbidden-field protection; controlled revoke kekal deferred sehingga Admin Step-Up tersedia.',
    ),
    'en' =>
    array (
      0 => 'Admin <b>Active Sessions</b> now uses real read-only listings; Refresh, search, filter and pagination no longer change token status incognito.',
      1 => 'Browser response uses explicit projection without <code>token_id</code>, token hash or policy correlation material.',
      2 => 'The session lifecycle differentiates <b>Current, Active, Grace, Due</b> and <b>Expired</b> based on absolute issuance and revocation table SC5.',
      3 => 'The UI separates <b>Issued At</b> from <b>Last Activity</b> and adds user/device search, filter status and page size 10, 25 or 50.',
      4 => 'Contract and AS0 preflight confirm bounded query, zero mutation and forbidden-field protection; controlled revoke remains deferred until Admin Step-Up is available.',
    ),
  ),
  19 =>
  array (
    'version' => '2.3.0',
    'date' => '2026-07-18',
    'bm' =>
    array (
      0 => 'Konfigurasi dan secrets kini menggunakan satu runtime file resolver; <code>ONEID_SECRETS_FILE</code> kekal sebagai alias legacy tetapi path bercanggah akan ditolak.',
      1 => 'Tool <code>configuration_audit.php</code> memeriksa 66 key, duplicate source key, permission, URL, timezone, SMTP, API, credentials dan mode Sync secara read-only.',
      2 => 'Template private runtime dikemas kini dan disusun mengikut kumpulan Application, API, database, SMTP, Sync, external source dan diagnostics.',
      3 => 'Template Nginx serta PHP-FPM UAT diselaraskan kepada project path staging <code>/var/www/oneid-uat</code>.',
      4 => 'Configuration contract membuktikan resolver fail-closed, template lengkap, audit tanpa mutation dan perlindungan private/public kekal lulus.',
    ),
    'en' =>
    array (
      0 => 'Configuration and secrets now use a runtime file resolver; <code>ONEID_SECRETS_FILE</code> remains a legacy alias but conflicting paths will be rejected.',
      1 => 'The <code>configuration_audit.php</code> tool checks 66 keys, duplicate source keys, permission, URL, timezone, SMTP, API, credentials and Sync mode in a read-only manner.',
      2 => 'The private runtime template is updated and organized according to Application, API, database, SMTP, Sync, external source and diagnostics groups.',
      3 => 'The Nginx and PHP-FPM UAT templates are aligned to the staging project path <code>/var/www/oneid-uat</code>.',
      4 => 'Configuration contract proves file-closed resolver, complete template, audit without mutation and private/public protection remains passed.',
    ),
  ),
  20 =>
  array (
    'version' => '2.2.4',
    'date' => '2026-07-18',
    'bm' =>
    array (
      0 => 'Operational Sync kini menggunakan soft warning bagi New melebihi 500, Update melebihi 1,000, Reactivate melebihi 100 atau jumlah perubahan melebihi 1,500.',
      1 => 'Batch besar kekal boleh di-Apply selepas semakan dan typed confirmation yang mengikat exact New, Update, Deactivate, Reactivate serta plan hash.',
      2 => 'Deactivate melebihi 50 disekat pada preview dan server Apply; plan tersebut mesti melalui Controlled Full Sync dengan kelulusan khusus.',
      3 => 'Nilai ambang boleh ditetapkan dalam private runtime, divalidasi secara ketat dan dipaparkan oleh preflight tanpa mendedahkan rahsia.',
      4 => 'Runbook dan characterization contract dikemas kini untuk membezakan batch biasa, batch besar dan hard block.',
    ),
    'en' =>
    array (
      0 => 'Operational Sync now uses a soft warning for New exceeding 500, Update exceeding 1,000, Reactivating exceeding 100 or total changes exceeding 1,500.',
      1 => 'Large batches can still be applied after review and typed confirmation that binds exact New, Update, Deactivate, Reactivate and plan hash.',
      2 => 'Deactivate over 50 is blocked on the preview and Apply server; the plan must go through Controlled Full Sync with special approval.',
      3 => 'Threshold values ​​can be set in private runtime, strictly validated and displayed by preflight without revealing secrets.',
      4 => 'Runbook and contract characterization updated to distinguish normal batch, large batch and hard block.',
    ),
  ),
  21 =>
  array (
    'version' => '2.2.3',
    'date' => '2026-07-18',
    'bm' =>
    array (
      0 => 'Identiti IC/pasport pelajar dinormalisasi kepada format alfanumerik tanpa ruang atau sengkang sebelum Preview dan Apply.',
      1 => 'Matching planner menggunakan identiti canonical pada snapshot external dan akaun sedia ada supaya cleanup menghasilkan Update pada matrik sama, bukan Deactivate dan New.',
      2 => 'Dry-run read-only terhadap 6,485 source rows menghasilkan tepat 137 Update pada <code>data2</code>, tanpa New, Deactivate atau Reactivate.',
      3 => 'Dua akaun staf dengan ID alternatif yang telah disahkan kekal tidak disentuh oleh cleanup khusus Pelajar.',
      4 => 'Protected manual collision matching turut menerima bentuk identiti canonical supaya akaun manual kekal fail-closed selepas normalisasi.',
    ),
    'en' =>
    array (
      0 => 'IC identity/student passport is normalized to alphanumeric format without spaces or dashes before Preview and Apply.',
      1 => 'Matching planner uses canonical identity on external snapshot and existing account so that cleanup produces Update on the same matrix, not Deactivate and New.',
      2 => 'Dry-run read-only against 6,485 source rows produces exactly 137 Updates on <code>data2</code>, without New, Deactivate or Reactivate.',
      3 => 'Two staff accounts with verified alternative IDs remain untouched by the Student-specific cleanup.',
      4 => 'Protected manual collision matching also accepts a canonical identity form so that the manual account remains file-closed after normalization.',
    ),
  ),
  22 =>
  array (
    'version' => '2.2.2',
    'date' => '2026-07-18',
    'bm' =>
    array (
      0 => '<b>Operational External Sync</b> membolehkan Administrator menjalankan Apply berulang selepas fresh preview tanpa count/hash private baharu atau full database dump bagi setiap batch biasa.',
      1 => 'Setiap Apply kekal diikat kepada approval session sekali guna, exact plan fingerprint, admin aktif dan expiry 5 minit; fresh snapshot mesti sepadan sebelum transaction bermula.',
      2 => 'Plan yang mempunyai Deactivate memerlukan typed confirmation dengan exact Deactivate count, manakala source anomaly, collision, invalid rows dan blast-radius threshold terus menyekat Apply.',
      3 => 'Writer selamat mengekalkan advisory lock, transaction, reconciliation dan audit marker; Operational, Pilot dan Full Cutover tidak boleh aktif serentak.',
      4 => 'Preflight dan runbook S4G ditambah untuk activation sekali sahaja, operasi setiap batch, backup berjadual serta disable segera melalui private runtime.',
    ),
    'en' =>
    array (
      0 => '<b>Operational External Sync</b> allows Administrator to run Apply repeatedly after fresh preview without new private count/hash or full database dump for each normal batch.',
      1 => 'Each Apply remains tied to a one-time approval session, exact fingerprint plan, active admin and 5-minute expiry; fresh snapshot must match before transaction starts.',
      2 => 'Plans that have Deactivate require typed confirmation with exact Deactivate count, while source anomaly, collision, invalid rows and blast-radius threshold continue to block Apply.',
      3 => 'Writer safely maintains advisory lock, transaction, reconciliation and audit markers; Operational, Pilot and Full Cutover cannot be active at the same time.',
      4 => 'Preflight and S4G runbook added for one-time activation, operation of each batch, scheduled backup and immediate disable via private runtime.',
    ),
  ),
  23 =>
  array (
    'version' => '2.2.1',
    'date' => '2026-07-18',
    'bm' =>
    array (
      0 => 'Pengurusan kategori Web Apps kini menyediakan tindakan <b>Edit</b> untuk membetulkan nama kategori dengan validation, duplicate protection, transaction dan audit log yang wajib.',
      1 => 'Modal Edit Category kembali ke <b>Manage Categories</b> selepas Cancel, tutup atau simpan supaya aliran kerja pentadbir kekal lancar.',
      2 => 'Kolum nombor pada <b>External Sync Log</b> dilebarkan dan dikekalkan pada satu baris untuk menyokong nombor sehingga tiga digit.',
      3 => 'Logo header pengguna dan pentadbir dikemas kini kepada identiti <b>UPNM 30 Tahun</b> menggunakan aset PNG tempatan tanpa kebergantungan kepada pelayan luar.',
      4 => 'Full External Sync yang diluluskan selesai dengan reconciliation tepat: 70 New, 33 Update, 1 Deactivate dan 0 Reactivate; runtime Apply dikembalikan kepada disabled selepas operasi.',
    ),
    'en' =>
    array (
      0 => 'The Web Apps category management now provides an <b>Edit</b> action to correct the category name with mandatory validation, duplicate protection, transaction and audit logs.',
      1 => 'Modal Edit Category returns to <b>Manage Categories</b> after Cancel, close or save so that the admin workflow remains smooth.',
      2 => 'The number column on the <b>External Sync Log</b> is expanded and kept on one line to support numbers up to three digits.',
      3 => 'User and administrator header logo updated to <b>UPNM 30 Year</b> identity using local PNG assets without dependency on external servers.',
      4 => 'Approved Full External Sync completed with accurate reconciliation: 70 New, 33 Update, 1 Deactivate and 0 Reactivate; the Apply runtime is returned to disabled after the operation.',
    ),
  ),
  24 =>
  array (
    'version' => '2.2.0',
    'date' => '2026-07-18',
    'bm' =>
    array (
      0 => 'Fasa <b>S4F Full External Sync</b> menyediakan endpoint dan UI Apply berasingan yang kekal disabled secara default serta hanya tersedia dalam maintenance window yang diluluskan.',
      1 => 'Full Apply diikat kepada exact New, Update, Deactivate dan Reactivate counts, full 64-character plan hash, admin session, approval sekali guna dan typed confirmation.',
      2 => 'Writer mengambil fresh external snapshot sebelum transaction, mengesahkan semula plan, menggunakan advisory lock dan mewajibkan transaction serta reconciliation audit sebelum commit.',
      3 => 'Preflight, post-run result audit, characterization contract, cutover/rollback runbook dan gate register ditambah untuk operasi full sync yang fail-closed.',
      4 => 'Semakan private menerima 33 Update dan 1 Deactivate; backup baharu disahkan melalui checksum serta isolated restore 18 jadual tanpa mengubah source database.',
    ),
    'en' =>
    array (
      0 => 'The <b>S4F Full External Sync</b> phase provides a separate Apply endpoint and UI that remains disabled by default and is only available in an approved maintenance window.',
      1 => 'Full Apply is tied to exact New, Update, Deactivate and Reactivate counts, full 64-character plan hash, admin session, one-time approval and typed confirmation.',
      2 => 'Writer takes a fresh external snapshot before the transaction, reconfirms the plan, uses an advisory lock and requires the transaction and reconciliation audit before committing.',
      3 => 'Preflight, post-run result audit, characterization contract, cutover/rollback runbook and gate register are added for fail-closed full sync operation.',
      4 => 'Private review received 33 Updates and 1 Deactivate; The new backup is verified through checksum as well as isolated restore of 18 tables without changing the source database.',
    ),
  ),
  25 =>
  array (
    'version' => '2.1.4',
    'date' => '2026-07-18',
    'bm' =>
    array (
      0 => 'Keserasian Chrome dan Firefox dipertingkat dengan atribut <b>autocomplete</b>, username tersembunyi serta identiti medan yang lengkap untuk login, password recovery, OTP dan pertukaran kata laluan.',
      1 => 'Content Security Policy, cookie pihak ketiga dan logo MyDigital ID diperbaiki dengan polisi aktif serta aset logo tempatan untuk mengelakkan warning dan permintaan luaran.',
      2 => 'CSS login, dashboard pengguna dan dashboard pentadbir dibersihkan daripada selector, prefix, filter dan at-rule browser legacy yang tidak lagi sah tanpa mengubah fungsi antaramuka.',
      3 => 'Aset ikon dan font diperkemas, termasuk Font Awesome, Icomoon, Dropify dan font dashboard, bagi menghapuskan warning parser serta glyph dalam browser moden.',
      4 => 'Modal password, label borang, input OTP dan pemuatan halaman diperbaiki untuk accessibility, autofill serta prestasi layout yang lebih konsisten.',
    ),
    'en' =>
    array (
      0 => 'Chrome and Firefox compatibility is enhanced with the <b>autocomplete</b> attribute, hidden usernames and complete identity fields for login, password recovery, OTP and password change.',
      1 => 'Content Security Policy, third-party cookies and MyDigital ID logo are improved with active policies and local logo assets to avoid warnings and external requests.',
      2 => 'CSS login, user dashboard and administrator dashboard are cleaned of selectors, prefixes, filters and legacy browser at-rules that are no longer valid without changing the functionality of the interface.',
      3 => 'Streamlined icon and font assets, including Font Awesome, Icomoon, Dropify and dashboard fonts, to eliminate parser warnings and glyphs in modern browsers.',
      4 => 'Modal password, form label, OTP input and page loading are improved for accessibility, autofill and more consistent layout performance.',
    ),
  ),
  26 =>
  array (
    'version' => '2.1.3',
    'date' => '2026-07-17',
    'bm' =>
    array (
      0 => 'Dashboard pengguna dan Administrator kini memaparkan pecahan <b>Jumlah, Full SSO dan Non SSO</b> berdasarkan aplikasi unik serta kontrak <code>sp_sso_support</code> yang sama dengan tindakan akses.',
      1 => 'Kad ringkasan aplikasi menggunakan identiti warna berbeza dan susun atur responsive, termasuk state loading, kosong dan kegagalan data.',
      2 => 'Audit Fasa 7 <b>Admin Step-Up 2FA</b> diperluas dengan pilihan OTP e-mel atau Microsoft Authenticator melalui TOTP, server-side enforcement, enrollment, recovery dan audit tanpa melaksanakan feature tersebut lagi.',
      3 => 'Audit serta pelan pelaksanaan <b>multi-language Bahasa Melayu dan English</b> disusun dalam ML0 hingga ML9 meliputi Configuration default language, preference pengguna, UI, API, e-mel, accessibility, metadata, UAT dan rollback.',
    ),
    'en' =>
    array (
      0 => 'User and Administrator dashboards now display <b>Total, Full SSO and Non SSO</b> breakdowns based on unique applications and <code>sp_sso_support</code> contracts that are the same as access actions.',
      1 => 'The application summary card uses a different color identity and responsive layout, including state loading, empty and data failure.',
      2 => 'Phase 7 Audit <b>Admin Step-Up 2FA</b> is expanded with the option of email OTP or Microsoft Authenticator via TOTP, server-side enforcement, enrollment, recovery and audit without implementing the feature again.',
      3 => 'The audit and <b>multi-language Bahasa Melayu and English</b> implementation plan are organized in ML0 to ML9 covering Configuration default language, user preferences, UI, API, e-mail, accessibility, metadata, UAT and rollback.',
    ),
  ),
  27 =>
  array (
    'version' => '2.1.2',
    'date' => '2026-07-17',
    'bm' =>
    array (
      0 => 'Konfigurasi SSO pentadbir diperkukuh melalui validation, audit correlation, integriti database, token lifecycle dan pemisahan polisi <b>Password Recovery</b>.',
      1 => 'Penghantaran test email dan OTP Password Recovery telah disahkan sehingga mailbox; OTP kekal sah 5 minit dan hanya boleh digunakan sekali.',
      2 => 'Aliran <b>Tukar Kata Laluan</b> kini memberikan feedback SweetAlert/toast yang jelas, mengesahkan password semasa dan kualiti password, merotasi session serta membatalkan session/token lain.',
      3 => 'Halaman login memaparkan logo MyDigital ID sebagai preview tanpa mengaktifkan fungsi authentication baharu.',
      4 => 'Admin Web Apps Add/Edit kini menggunakan validation HTTPS, App ID kriptografi, confirmation, double-submit protection, atomic persistence dan audit correlation.',
      5 => 'Admin Web Apps kini mempunyai <b>carian semua aplikasi</b> merentas kategori berdasarkan nama, fungsi, URL dan App ID, dengan kiraan hasil serta clear action.',
      6 => 'Icon Web Apps disimpan mengikut environment <code>local</code>/<code>staging</code> walaupun database dikongsi; setiap filesystem kekal berasingan dan missing asset jatuh kepada placeholder.',
      7 => 'Upload icon baharu didecode dan dinormalisasi kepada static PNG 256×256; metadata dibuang, animated image dan input melebihi had keselamatan ditolak.',
      8 => 'Login dan Password Recovery kini mempunyai request timeout, double-submit protection, session-lock release, correlation audit serta feedback SweetAlert apabila respons tergendala.',
      9 => 'WA6 menyediakan reconciliation read-only dengan SHA-256 bagi missing reference dan orphan candidate; tiada quarantine atau deletion dibenarkan tanpa kelulusan owner.',
    ),
    'en' =>
    array (
      0 => 'Administrator SSO configuration is strengthened through validation, audit correlation, database integrity, token lifecycle and separation of <b>Password Recovery</b> policies.',
      1 => 'Email test delivery and OTP Password Recovery have been confirmed to the mailbox; The OTP remains valid for 5 minutes and can only be used once.',
      2 => 'The <b>Change Password</b> flow now provides clear SweetAlert/toast feedback, confirms the current password and password quality, rotates sessions and cancels other sessions/tokens.',
      3 => 'The login page displays the MyDigital ID logo as a preview without activating the new authentication function.',
      4 => 'Admin Web Apps Add/Edit now uses HTTPS validation, App ID cryptography, confirmation, double-submit protection, atomic persistence and audit correlation.',
      5 => 'Admin Web Apps now has <b>search all apps</b> across categories based on name, function, URL and App ID, with result count and clear action.',
      6 => 'Icon Web Apps are stored according to the <code>local</code>/<code>staging</code> environment even though the database is shared; each filesystem remains separate and missing assets fall to placeholders.',
      7 => 'Upload new icon decoded and normalized to static PNG 256×256; metadata is removed, animated image and input exceeding the security limit is rejected.',
      8 => 'Login and Password Recovery now have request timeout, double-submit protection, session-lock release, correlation audit and SweetAlert feedback when the response is delayed.',
      9 => 'WA6 provides read-only reconciliation with SHA-256 for missing reference and orphan candidate; no quarantine or deletion is allowed without the owner\'s approval.',
    ),
  ),
  28 =>
  array (
    'version' => '2.1.1',
    'date' => '2026-07-16',
    'bm' =>
    array (
      0 => '<b>Controlled Pilot External Sync</b> berjaya melaksanakan subset terkawal 2 akaun baharu dan 1 kemas kini tanpa Deactivate atau Reactivate; Apply kemudiannya dikembalikan kepada disabled.',
      1 => 'Backup penuh <code>oneiddb</code>, restore rehearsal dan isolated pilot rehearsal disahkan melalui checksum, row reconciliation serta cleanup database sementara tanpa mengubah sumber.',
      2 => 'Struktur deployment memisahkan public root, konfigurasi runtime persekitaran dan secret store di dalam direktori projek tetapi di luar capaian web.',
      3 => 'Semua notifikasi aplikasi distandardkan sebagai <b>toast top-right</b>; native alert diganti dengan toast dan tindakan berisiko menggunakan SweetAlert confirmation.',
      4 => 'Audit Log kini menyediakan pagination 10 rekod setiap halaman serta date picker yang lebih padat dengan Apply dan Cancel di bawah kalendar.',
      5 => 'Aset CSS legacy yang tidak digunakan dibuang daripada dashboard untuk mengurangkan warning browser tanpa mengubah Dropify, SweetAlert atau fungsi aktif.',
      6 => 'Paparan <b>Version Releases</b> kini menggunakan accordion eksklusif: release terkini terbuka secara default dan hanya satu release dipaparkan pada satu masa.',
    ),
    'en' =>
    array (
      0 => '<b>Controlled Pilot External Sync</b> successfully executed a controlled subset of 2 new accounts and 1 update without Deactivate or Reactivate; Apply is then returned to disabled.',
      1 => 'Full <code>oneiddb</code> backup, restore rehearsal and isolated pilot rehearsal are confirmed through checksum, row reconciliation and temporary database cleanup without changing the source.',
      2 => 'The deployment structure separates the public root, environment runtime configuration and secret store inside the project directory but out of web reach.',
      3 => 'All application notifications are standardized as <b>toast top-right</b>; native alerts are replaced with toasts and risky actions using SweetAlert confirmation.',
      4 => 'Audit Log now provides pagination of 10 records per page as well as a more compact date picker with Apply and Cancel under the calendar.',
      5 => 'Unused legacy CSS assets are removed from the dashboard to reduce browser warnings without changing Dropify, SweetAlert or active functionality.',
      6 => 'The <b>Version Releases</b> view now uses an exclusive accordion: the latest release is open by default and only one release is displayed at a time.',
    ),
  ),
  29 =>
  array (
    'version' => '2.1.0',
    'date' => '2026-07-14',
    'bm' =>
    array (
      0 => 'Audit Log kini memaparkan rekod <b>terbaharu di bahagian paling atas</b> menggunakan susunan stabil <code>datetime DESC, id DESC</code>.',
      1 => 'Jika beberapa aktiviti direkod pada saat yang sama, ID audit terbaharu menentukan susunan supaya paparan tidak berubah-ubah selepas reload.',
      2 => 'Julat tarikh Audit Log kini merangkumi keseluruhan hari akhir yang dipilih dan tidak lagi berhenti pada jam 00:00:00.',
      3 => 'UI melaksanakan susunan defensif selepas data dimuatkan, manakala database kekal sebagai source of truth untuk urutan dan had 50 rekod terkini.',
    ),
    'en' =>
    array (
      0 => 'Audit Log now displays the <b>most recent record at the top</b> using a stable order of <code>datetime DESC, id DESC</code>.',
      1 => 'If several activities are recorded at the same time, the most recent audit ID determines the order so that the display does not change after a reload.',
      2 => 'The Audit Log date range now spans the entire selected end day and no longer stops at 00:00:00.',
      3 => 'The UI performs a defensive order after the data is loaded, while the database remains the source of truth for the sequence and the limit of 50 latest records.',
    ),
  ),
  30 =>
  array (
    'version' => '2.0.4',
    'date' => '2026-07-14',
    'bm' =>
    array (
      0 => 'Dashboard pengguna kini mempunyai <b>carian aplikasi merentas kategori</b> berdasarkan nama dan fungsi aplikasi, dengan kategori padanan dipilih secara automatik.',
      1 => 'Tab <b>Favourite</b> berikon bintang ditambah pada kedudukan pertama untuk mengumpulkan aplikasi yang kerap digunakan tanpa mengeluarkannya daripada kategori asal.',
      2 => 'Pilihan Favourite disimpan secara persistent mengikut akaun melalui jadual preference khusus dan kekal selepas logout atau login semula.',
      3 => 'Favourite dikawal oleh session dan effective ACL; preference tidak menambah akses serta tidak boleh memintas category allow, direct allow atau blacklist deny.',
      4 => 'Setiap kad aplikasi kini menyediakan tindakan Favourite berkeadaan kelabu/kuning dan tindakan akses yang lebih jelas serta responsive.',
      5 => 'Aplikasi OneID SSO menggunakan label <b>Login</b>, manakala aplikasi NON SSO menggunakan label <b>Akses</b> dan badge <b>Akses terus</b>.',
      6 => 'Tab <b>NON SSO</b> diberi identiti warna berbeza supaya pautan terus mudah dibezakan daripada aplikasi berintegrasi OneID.',
      7 => 'Migration, rollback, characterization contract dan runbook UAT U1 ditambah; smoke HTTP, structure, M2, M3 dan release regression kekal lulus.',
      8 => 'Gate live Apply-path M1 direkod sebagai ditangguhkan oleh owner sehingga akaun external ujian yang sesuai tersedia; penangguhan ini tidak mengaktifkan External Sync Apply S4E.',
      9 => 'Paparan <b>Version Releases</b> dihadkan kepada 10 versi terkini dan menyediakan tindakan untuk melihat release terdahulu secara berperingkat.',
      10 => 'Direktori admin kini hanya memaparkan tab kategori yang mempunyai aplikasi aktif; inventori penuh dan sebab kategori tidak boleh dipadam tersedia melalui <b>Manage Categories</b>.',
      11 => 'Kategori sistem dilindungi dan kategori berisi tidak boleh dipadam; create/delete menggunakan validation, transaction, rollback serta correlated audit trail.',
      12 => 'Remove App kini mengarkib aplikasi ke kategori sistem dan membersihkan group ACL, direct ACL, blacklist serta Favourite secara atomic; aplikasi inactive ditolak oleh effective ACL.',
      13 => 'Integriti schema diperkukuh dengan nama kategori unik dan foreign key <code>sp_list.sp_group_id</code> berpolisi <code>ON DELETE RESTRICT</code>.',
    ),
    'en' =>
    array (
      0 => 'The user dashboard now has a <b>cross-category application search</b> based on application name and function, with matching categories automatically selected.',
      1 => 'A <b>Favorite</b> tab with a star icon is added in the first position to group frequently used applications without removing them from the original category.',
      2 => 'Favorites are saved persistently by account through a specific preference table and remain after logout or login again.',
      3 => 'Favorites are controlled by session and effective ACL; preference does not add access and cannot bypass category allow, direct allow or blacklist deny.',
      4 => 'Each application card now provides a gray/yellow Favorite action and a more clear and responsive access action.',
      5 => 'The OneID SSO application uses the <b>Login</b> label, while the NON SSO application uses the <b>Access</b> label and the <b>Direct Access</b> badge.',
      6 => 'The <b>NON SSO</b> tab is given a different color identity so that direct links are easily distinguished from OneID integrated applications.',
      7 => 'Migration, rollback, characterization contract and UAT U1 runbook added; smoke HTTP, structure, M2, M3 and release regression remain passed.',
      8 => 'Gate live Apply-path M1 is recorded as delayed by the owner until a suitable test external account is available; this delay does not activate External Sync Apply S4E.',
      9 => 'The <b>Version Releases</b> display is limited to the 10 latest versions and provides an action to view previous releases in stages.',
      10 => 'The admin directory now only displays category tabs that have active applications; The full inventory and reasons why categories cannot be deleted are available through <b>Manage Categories</b>.',
      11 => 'System categories are protected and filled categories cannot be deleted; create/delete using validation, transaction, rollback and correlated audit trail.',
      12 => 'Remove App now archives applications to the system category and cleans group ACL, direct ACL, blacklist and Favorites atomically; inactive applications are rejected by effective ACL.',
      13 => 'Schema integrity is reinforced with unique category names and foreign keys <code>sp_list.sp_group_id</code> with an <code>ON DELETE RESTRICT</code> policy.',
    ),
  ),
  31 =>
  array (
    'version' => '2.0.3',
    'date' => '2026-07-14',
    'bm' =>
    array (
      0 => '<b>Profile Save, category policy dan ACL hardening</b> disiapkan melalui fasa M3 dengan validation, explicit confirmation, transaction, rollback dan correlated audit trail.',
      1 => 'Butang <b>Save Profile</b> kini menjadi satu-satunya laluan menyimpan nama dan kategori; perubahan dropdown tidak lagi terus mengubah database.',
      2 => 'Nama akaun external-managed dijadikan read-only dan hanya boleh dikemas kini melalui Safe Resync, manakala nama akaun manual boleh disimpan selepas validation.',
      3 => 'Kategori pengguna dipisahkan daripada role administrator; hardcoded category ID 9 dan mutator category/role legacy telah dibuang supaya <b>u_type</b> sentiasa dikekalkan.',
      4 => 'ACL khusus pengguna untuk <b>Allow, Deny dan Uplift</b> kini mengesahkan user, aplikasi, duplicate state dan ownership deny record sebelum mutation.',
      5 => 'Perubahan kategori dan ACL membatalkan sesi aktif pengguna supaya policy baharu berkuat kuasa serta-merta.',
      6 => 'Nama aplikasi dinyahkod secara selamat sebelum dimasukkan ke DOM dan kegagalan AJAX kini memaparkan code serta correlation reference.',
      7 => 'Manual UAT profile/category, Forbidden admin route, ACL allow/deny/uplift, session revocation dan Audit Log telah disahkan lulus menggunakan akaun ujian terkawal.',
      8 => 'Defense-in-depth consumer turut disahkan: direct allow OneID tidak memintas authorization dalaman aplikasi sasaran.',
    ),
    'en' =>
    array (
      0 => '<b>Profile Save, category policy and ACL hardening</b> are completed through phase M3 with validation, explicit confirmation, transaction, rollback and correlated audit trail.',
      1 => 'The <b>Save Profile</b> button is now the only way to save names and categories; dropdown changes no longer continue to change the database.',
      2 => 'External-managed account names are made read-only and can only be updated via Safe Resync, while manual account names can be saved after validation.',
      3 => 'The user category is separated from the administrator role; hardcoded category ID 9 and category/role legacy mutator have been removed so that <b>u_type</b> is always preserved.',
      4 => 'User-specific ACLs for <b>Allow, Deny and Uplift</b> now verify user, application, duplicate state and ownership deny record before mutation.',
      5 => 'Category and ACL changes cancel the user\'s active session so that the new policy takes effect immediately.',
      6 => 'The application name is safely decoded before being entered into the DOM and AJAX failure now displays the code and correlation reference.',
      7 => 'Manual UAT profile/category, Forbidden admin route, ACL allow/deny/uplift, session revocation and Audit Log have been confirmed to pass using a controlled test account.',
      8 => 'Defense-in-depth consumer is also confirmed: direct allow OneID does not bypass the internal authorization of the target application.',
    ),
  ),
  32 =>
  array (
    'version' => '2.0.2',
    'date' => '2026-07-14',
    'bm' =>
    array (
      0 => 'Menyelaraskan identiti release kepada <b>Version 2.0.2</b> melalui satu source metadata untuk login, dashboard pengguna, dashboard admin, footer dan latest release badge.',
      1 => 'Copyright aplikasi distandardkan kepada <b>2026 © PTMK | Aplikasi Digital</b> pada semua paparan utama.',
      2 => 'UI <b>Version Releases</b> dibina semula menggunakan release cards, metadata release dan changelog yang lebih tersusun serta responsive.',
      3 => 'UI <b>SSO Configuration</b>, <b>Sync Log</b>, <b>Audit Log</b>, <b>Active Sessions</b>, <b>User Accounts</b>, <b>Web Apps</b> dan dashboard pengguna disusun semula dengan hierarchy, compact table/card serta responsive state yang konsisten.',
      4 => 'Maklumat peranti sesi diperbetulkan: kurungan brand kosong dibuang dan login baharu merekod jenis peranti, browser serta sistem operasi daripada User-Agent.',
      5 => 'Single-user <b>Resync User Info</b> diperkukuh dengan external SELECT-only lookup, provenance protection, preview perubahan, one-time approval, confirmation, transaction, rollback dan correlated audit trail.',
      6 => 'Action modal <b>Force Reset Password, Remove User dan Reactivate User</b> diperkukuh dengan row lock, verified mutation, session/OTP revocation, mandatory correlated audit, transaction rollback dan perlindungan self-lockout.',
    ),
    'en' =>
    array (
      0 => 'Coordinate the release identity to <b>Version 2.0.2</b> through one metadata source for login, user dashboard, admin dashboard, footer and latest release badge.',
      1 => 'Application copyright is standardized to <b>2026 © PTMK | Digital Application</b>on all main displays.',
      2 => 'The <b>Version Releases</b> UI has been rebuilt using release cards, release metadata and a more organized and responsive changelog.',
      3 => 'The UI <b>SSO Configuration</b>, <b>Sync Log</b>, <b>Audit Log</b>, <b>Active Sessions</b>, <b>User Accounts</b>, <b>Web Apps</b> and the user dashboard are reorganized with hierarchy, compact table/card and a consistent responsive state.',
      4 => 'Corrected session device information: empty brand brackets removed and new login records device type, browser and operating system from User-Agent.',
      5 => 'Single-user <b>Resync User Info</b> is strengthened with external SELECT-only lookup, provenance protection, change preview, one-time approval, confirmation, transaction, rollback and correlated audit trail.',
      6 => 'Modal actions <b>Force Reset Password, Remove User and Reactivate User</b> are strengthened with row lock, verified mutation, session/OTP revocation, mandatory correlated audit, transaction rollback and self-lockout protection.',
    ),
  ),
  33 =>
  array (
    'version' => '2.0.1',
    'date' => '2026-07-14',
    'bm' =>
    array (
      0 => 'Memperkenalkan <b>External Sync Preview</b> yang read-only — memaparkan jumlah sumber, cadangan akaun baharu/kemas kini, deactivate/reactivate, perlindungan akaun manual, collision, plan hash dan tempoh sah tanpa mengubah database.',
      1 => 'Menambah lapisan keselamatan sync merangkumi <b>single-run lock, transaction boundary, source completeness, blast-radius policy</b> dan reconciliation sebelum commit.',
      2 => 'Preview dan Apply kini direka dengan <b>server-bound approval</b>, strict feature flags dan perlindungan replay; Apply kekal disabled sehingga semua gate operasi mendapat kelulusan.',
      3 => 'Akaun yang ditambah secara manual mempunyai <b>provenance</b> dan perlindungan supaya external sync tidak menimpa akaun manual secara tidak sengaja.',
      4 => 'Verification sync diperkukuh dengan regression contracts, external SELECT-only evidence, backup penuh dan isolated restore rehearsal.',
      5 => 'Flow admin preview diperbetulkan supaya menggunakan token CSRF baharu selepas login dan session rotation.',
      6 => 'Paparan <b>Version Releases</b> direka semula menggunakan release cards yang lebih kemas, mudah dibaca dan responsive.',
    ),
    'en' =>
    array (
      0 => 'Introducing a read-only <b>External Sync Preview</b> — displays total resources, new/update account recommendations, deactivate/reactivate, manual account protection, collision, hash plan and validity period without changing the database.',
      1 => 'Adding a sync security layer includes <b>single-run lock, transaction boundary, source completeness, blast-radius policy</b> and reconciliation before commit.',
      2 => 'Preview and Apply are now designed with <b>server-bound approval</b>, strict feature flags and replay protection; Apply remains disabled until all operational gates are approved.',
      3 => 'Manually added accounts have <b>provenance</b> and protection so that external sync does not accidentally overwrite manual accounts.',
      4 => 'Sync verification is strengthened with regression contracts, external SELECT-only evidence, full backup and isolated restore rehearsal.',
      5 => 'Fixed preview admin flow to use new CSRF token after login and session rotation.',
      6 => 'The <b>Version Releases</b> display was redesigned using release cards that are neater, easier to read and responsive.',
    ),
  ),
  34 =>
  array (
    'version' => '2.0.0',
    'date' => '2026-07-14',
    'bm' =>
    array (
      0 => '<b>Major security hardening</b> untuk authentication dan authorization: server-side admin guard, default-deny action mapping, CSRF protection dan session regeneration.',
      1 => 'Password legacy dimigrasikan secara terkawal kepada hash moden; reset password, OTP, rate limiting, session cookie dan token SSO diperkukuh.',
      2 => 'Secrets database, SMTP dan integrasi dipindahkan daripada source code kepada runtime secret configuration dengan permission yang lebih ketat.',
      3 => 'Upload icon diperketat menggunakan validation MIME/kandungan, allowlist format, nama rawak dan larangan script execution dalam direktori upload.',
      4 => 'Endpoint API, IDMS dan SKP diperkukuh melalui validation, parameterized query, TLS verification, response yang lebih selamat dan kawalan akses integrasi.',
      5 => 'Document root dimigrasikan sepenuhnya ke <b>public/</b>; source aplikasi, konfigurasi, docs, storage, tools dan database dump tidak lagi terdedah melalui web.',
      6 => 'Struktur projek disusun semula kepada boundary <b>app/, bootstrap/, config/, public/, resources/, storage/, tests/ dan tools/</b> dengan compatibility wrapper untuk URL legacy yang masih sah.',
      7 => 'Fail lama, diagnostic endpoint, duplicate implementation dan aset transitional melalui inventori, quarantine dan cleanup terkawal tanpa memutuskan login, API atau SSO consumer.',
      8 => 'Kod application layer mula diekstrak kepada service, adapter, planner dan orchestrator yang boleh diuji tanpa mengubah caller production secara terus.',
      9 => 'Manual Add User diperkukuh dengan validation, transaction, audit, provenance dan perlindungan collision dengan external source.',
      10 => 'Automated characterization, contract tests, smoke tests, rollback runbook dan dokumentasi berfasa ditambah untuk menyokong deployment serta audit yang boleh diulang.',
    ),
    'en' =>
    array (
      0 => '<b>Major security hardening</b> for authentication and authorization: server-side admin guard, default-deny action mapping, CSRF protection and session regeneration.',
      1 => 'Legacy passwords are migrated in a controlled manner to modern hashes; password reset, OTP, rate limiting, session cookie and SSO token are strengthened.',
      2 => 'Database, SMTP and integration secrets are moved from source code to runtime secret configuration with stricter permissions.',
      3 => 'Upload icon is tightened using MIME/content validation, format allowlist, random name and prohibition of script execution in the upload directory.',
      4 => 'Endpoint API, IDMS and SKP are strengthened through validation, parameterized query, TLS verification, more secure response and integration access control.',
      5 => 'Document root is fully migrated to <b>public/</b>; Application source, configuration, docs, storage, tools and database dump are no longer exposed through the web.',
      6 => 'The project structure is rearranged to the boundaries of <b>app/, bootstrap/, config/, public/, resources/, storage/, tests/ and tools/</b> with compatibility wrappers for legacy URLs that are still valid.',
      7 => 'Old files, diagnostic endpoints, duplicate implementations and transitional assets through controlled inventory, quarantine and cleanup without disconnecting login, API or SSO consumer.',
      8 => 'The application layer code is first extracted to services, adapters, planners and orchestrators that can be tested without changing caller production directly.',
      9 => 'The Add User manual is reinforced with validation, transaction, audit, provenance and collision protection with external sources.',
      10 => 'Automated characterization, contract tests, smoke tests, rollback runbooks and phased documentation are added to support deployment and repeatable audits.',
    ),
  ),
  35 =>
  array (
    'version' => '1.0.4',
    'date' => '2026-07-13',
    'bm' =>
    array (
      0 => 'Release penyelenggaraan terakhir untuk siri <b>1.x.x</b> dan baseline sebelum program security hardening serta restructuring bermula.',
      1 => 'Tiada patch baharu akan dikeluarkan untuk siri <b>1.x.x</b>; versi ini dikekalkan sebagai rujukan legacy sahaja.',
      2 => 'Semua pembangunan seterusnya diteruskan melalui major upgrade <b>v2.0.0</b>.',
    ),
    'en' =>
    array (
      0 => 'The last maintenance release for the <b>1.x.x</b> and baseline series before the security hardening and restructuring program begins.',
      1 => 'No new patches will be released for the <b>1.x.x</b> series; This version is maintained as a legacy reference only.',
      2 => 'All further development continues through the major upgrade <b>v2.0.0</b>.',
    ),
  ),
  36 =>
  array (
    'version' => '1.0.3',
    'date' => '2026-06-17',
    'bm' =>
    array (
      0 => 'Carian pengguna di panel admin kini <b>lebih pantas</b> — hasil carian nama, No. Staf/Pelajar, atau No. K/P dipaparkan dengan lebih cepat semasa taip.',
    ),
    'en' =>
    array (
      0 => 'User search in the admin panel is now <b>faster</b> — name search result, No. Staff/Student, or No. K/P is displayed faster while typing.',
    ),
  ),
  37 =>
  array (
    'version' => '1.0.2',
    'date' => '2026-06-16',
    'bm' =>
    array (
      0 => 'Menu baharu <b>Sync Log</b> — semak sejarah sync, statistik setiap sesi (baharu, dikemaskini, dinyahaktifkan, diaktifkan semula), dan butiran perubahan mengikut sesi.',
      1 => 'Penjadual sync automatik berjalan setiap hari pada <b>12:00 tengah malam (00:00)</b>.',
      2 => 'Pembaikan bug kritikal pada penjadual sync — rekod sesi lebih tepat, elak sync berulang akaun yang sama, dan kestabilan proses harian.',
      3 => 'Penambahbaikan <b>Sync Pengguna</b>: sistem hanya kemaskini akaun yang benar-benar berubah; sync lebih pantas dan tepat.',
      4 => 'Akaun staf/pelajar yang muncul semula dalam sumber data akan <b>diaktifkan semula</b> secara automatik.',
      5 => 'Data sync sebelum ini telah <b>dikosongkan</b> (truncate). <b>17 Jun 2026</b> menjadi tarikh mula sesi sync <b>Generasi 2</b>. Sync Generasi 1 mengandungi banyak bug dan tidak lagi dirujuk.',
    ),
    'en' =>
    array (
      0 => 'New <b>Sync Log</b> menu — check sync history, statistics per session (new, updated, deactivated, reactivated), and change details by session.',
      1 => 'The automatic sync scheduler runs every day at <b>12:00 midnight (00:00)</b>.',
      2 => 'Critical bug fixes on the sync scheduler — more accurate session records, avoid repeated syncs of the same account, and daily process stability.',
      3 => '<b>User Sync</b> improvements: the system only updates accounts that have actually changed; sync is faster and more accurate.',
      4 => 'Staff/student accounts that reappear in the data source will be <b>reactivated</b> automatically.',
      5 => 'The previous sync data has been <b>emptied</b> (truncate). <b>June 17, 2026</b> is the start date of the <b>Generation 2</b> sync session. Sync Generation 1 contains many bugs and is no longer referenced.',
    ),
  ),
  38 =>
  array (
    'version' => '1.0.1',
    'date' => '2025-11-12',
    'bm' =>
    array (
      0 => 'Bahasa & istilah diseragamkan: Application → <b>Sistem Aplikasi</b>, Directory → <b>Direktori Staf</b>, FAQ → <b>Soalan Lazim (FAQ)</b>, Logout → <b>Log Keluar</b>, Close → <b>Tutup</b>, Change → <b>Tukar</b>.',
      1 => '‘List of accessible apps’ → <b>Senarai Sistem Aplikasi</b>.',
      2 => '<b>Kata Laluan Semasa</b> (No. KP/No. Pasport), <b>Kata Laluan Baharu</b>, dan <b>Sahkan Kata Laluan Baharu</b>.',
      3 => 'Keperluan kata laluan: ≥8 aksara, ≥1 huruf kecil (a–z), ≥1 huruf besar (A–Z), ≥1 nombor (0–9), ≥1 aksara khas (cth: ! @ # $ %).',
      4 => 'Counter multiple login yang tersangkut: <b>dimatikan</b> & mesej: “Akaun dikunci. Sila log semula selepas 2 minit.”',
      5 => 'Rekod multiple attempt login dihantar ke modul <b>Audit</b>.',
      6 => 'Had input login: <b>Max 10 karakter</b>, hanya <b>A–Z, 0–9</b> dan <b>“-”</b>.',
      7 => 'Kategori tab “Non-SSO” ditukar nama kepada <b>Pautan Terus</b>.',
      8 => 'Kategori <b>Pautan Terus</b> dialihkan ke <b>akhir</b> senarai tab.',
      9 => 'Buang butang <b>Logout</b> pada menu <b>Tukar Password</b> (Dashboard).',
      10 => 'Alert bertukar kepada <b>SweetAlert</b> (pusat skrin).',
      11 => 'Templat placeholder Login: <b>No.Staf (XXXX-XX) / No.Pelajar</b>. Password kali pertama: <b>No. K/P</b>.',
    ),
    'en' =>
    array (
      0 => 'Standardized language & terminology: Application → <b>Application System</b>, Directory → <b>Staff Directory</b>, FAQ → <b>Frequently Asked Questions (FAQ)</b>, Logout → <b>Log Out</b>, Close → <b>Close</b>, Change → <b>Change</b>.',
      1 => '\'List of accessible apps\' → <b>List of Application Systems</b>.',
      2 => '<b>Current Password</b> (KP No./Passport No.), <b>New Password</b>, and <b>Confirm New Password</b>.',
      3 => 'Password requirements: ≥8 characters, ≥1 lowercase letter (a–z), ≥1 uppercase letter (A–Z), ≥1 number (0–9), ≥1 special character (eg: ! @ # $ %).',
      4 => 'Stuck multiple login counter: <b>turned off</b> & message: "Account locked. Please log in again after 2 minutes."',
      5 => 'Multiple login attempt records are sent to the <b>Audit</b> module.',
      6 => 'Login input limit: <b>Max 10 characters</b>, only <b>A–Z, 0–9</b> and <b>“-”</b>.',
      7 => 'The “Non-SSO” tab category was renamed to <b>Direct Links</b>.',
      8 => 'The <b>Direct Links</b> category is moved to the <b>end</b> of the tab list.',
      9 => 'Remove the <b>Logout</b> button on the <b>Change Password</b> menu (Dashboard).',
      10 => 'Alert changes to <b>SweetAlert</b> (center of screen).',
      11 => 'Login placeholder template: <b>Staff No. (XXXX-XX) / Student No.</b>. First time password: <b>No. K/P</b>.',
    ),
  ),
));
