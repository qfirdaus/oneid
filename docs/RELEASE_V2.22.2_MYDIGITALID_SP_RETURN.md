# OneID 2.22.2 — MyDigital ID Service Provider Return

Tarikh: 28 Oktober 2026

Aliran MyDigital ID yang dimulakan daripada aplikasi berdaftar kini mengekalkan
`site_id` sehingga pengesahan selesai. Selepas pengguna berjaya disahkan,
OneID meneruskan hand-off SSO sedia ada dan mengembalikan pengguna kepada
aplikasi yang memulakan log masuk.

Log masuk MyDigital ID secara terus tanpa `site_id` kekal kembali ke dashboard
OneID. Return path hanya menerima path dalaman dengan satu `site_id` yang sah;
URL luar, fragment, parameter tambahan dan pengenal tidak sah akan kembali
kepada destinasi selamat.

## English

MyDigital ID flows initiated by a registered application now preserve the
`site_id` until authentication completes. After successful authentication,
OneID continues through the existing SSO hand-off and returns the user to the
application that initiated sign-in.

Direct MyDigital ID sign-in without a `site_id` continues to return to the
OneID dashboard. The return path accepts only an internal path containing one
valid `site_id`; external URLs, fragments, additional parameters and invalid
identifiers fall back to the safe destination.
