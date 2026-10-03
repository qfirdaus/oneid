# Input dan syarat pengaktifan production

| Perkara | Status |
|---|---|
| Skop platform | Disahkan pengguna: Android sahaja |
| Package/application ID build production | Belum disahkan; jangan andaikan sama dengan staging |
| Client ID / exact redirect URI Android | Belum disahkan; template tidak boleh didaftarkan seperti sedia ada |
| Android App Link signing/assetlinks | Jika memakai HTTPS App Links, perlu domain/path dan fingerprint signing sebenar; jika custom scheme, semak pemilikan dan collision |
| MyDigital ID callback mobile | `https://oneid.upnm.edu.my/mobile/mydigitalid/callback` perlu allowlist provider/client production |
| MyDigital ID web | Kekalkan client/config/callback web asal; jangan overwrite dengan credential staging |
| Database physical target | Isi `database_name` dan `@@hostname` sebenar dalam private release target selepas semakan |
| Schema source | 9 jadual ditemui; semakan lengkap column/index/engine, trigger coexistence dan privilege belum production-rehearsed |
| Backup | Kod/vendor/config, MySQL dengan triggers, provider PostgreSQL + keys; bukti restore dan pemilik diperlukan |
| Hydra/PostgreSQL | Private service, pinned artifact, DSN/keys baharu, permission, retention, monitoring dan pemilik patching diperlukan |
| Kapasiti/maintenance window | Web pool cadangan mengekalkan 10 workers production; memory/performance acceptance perlu pemilik sahkan |
| Log/monitoring mobile | Nginx query-bearing errors ditindas; dedicated FPM log/logrotate dan access log sanitized wajib tersedia |

Jangan salin `mobile-oidc-hosted.php`, sessions, tokens, PostgreSQL data directory atau secrets UAT ke production. Production public client Android tidak mempunyai client secret; secrets provider/adapter kekal server-side.

Konfigurasi Flutter release menggunakan issuer production, exact client/redirect berdaftar dan PKCE S256. Gunakan authorization-code + refresh, simpan token mengikut mekanisme secure storage aplikasi. Jangan tukar kod integrasi downstream sebagai sebahagian release ini; protokol legacy dikekalkan dan diuji dengan downstream sedia ada.

Ujian ringkas selepas aktivasi: satu login web + downstream sedia ada, satu aliran **build Android sebenar** (login/sesi/refresh/logout), satu MyDigital ID melalui callback production, dan satu akaun tidak dibenarkan. `/mobile-test/` UAT tidak menggantikan native production E2E; harness ini ditutup pada production.

Had terdahulu kekal: inventori runtime/ODBC downstream remote dan formal performance acceptance belum lengkap. Kejayaan UAT ialah bukti staging, bukan pengesahan automatik production.
