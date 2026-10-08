# OneID 2.19.0 — Dynamic Status and Recently Used

**Release date:** 8 October 2026
**Scope:** User dashboard UI and authenticated read-only downstream status checks

## Bahasa Melayu

Release ini menambah tiga penambahbaikan dashboard pengguna. Gauge Status Sambungan dan Paparan kini berubah mengikut masa respons yang diukur serta keadaan sambungan. Direktori aplikasi memaparkan status downstream melalui endpoint berautentikasi yang hanya menyemak aplikasi dalam ACL pengguna, menghadkan bilangan semakan, menggunakan timeout pendek dan menyimpan cache sementara.

Recently Used dipaparkan melalui ikon kompak di sebelah kanan tajuk Application Categories. Menu konteks menyenaraikan maksimum enam aplikasi terakhir dengan status dan timestamp penggunaan, menyokong paparan telefon, klik luar, Escape dan butang tutup. Tindakan Kosongkan Baru Digunakan hanya memadam sejarah ini daripada browser dan tidak mengubah Favourite, sesi, ACL atau database.

## English

This release adds three user-dashboard improvements. The Connection and Display Status gauge now changes according to measured response time and connectivity. The application directory presents downstream status through an authenticated endpoint that checks only applications in the user's effective ACL, caps the number of checks, applies short timeouts and uses temporary caching.

Recently Used is available through a compact icon at the right of the Application Categories heading. Its context menu lists up to six latest applications with status and last-used timestamps, supports mobile layouts, outside click, Escape and explicit close controls. Clear Recently Used removes only this browser history and does not change Favourites, sessions, ACLs or the database.

## Validation

- PHP syntax checks for the changed endpoint, service, page and locale files.
- User dashboard experience contract covering ACL derivation, URL non-disclosure, cache limits, responsive context menu and local timestamp history.
- Existing user dashboard and health-panel regression contracts.
- No database migration, mobile-production activation or runtime switch is included.
