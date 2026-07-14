# Release OneID v2.0.2 — UI, Metadata dan Device Session

**Tarikh:** 14 Julai 2026
**Versi:** 2.0.2
**Jenis:** UI/UX, release metadata dan pembetulan paparan device session

## Ringkasan

Release ini menyelaraskan UI utama OneID untuk admin dan pengguna tanpa
mengubah flow authorization, SSO consumer atau external sync Apply. Ia turut
menjadikan metadata versi sebagai satu source of truth dan membetulkan format
maklumat peranti bagi active session. Single-user Resync turut diganti dengan
workflow preview-confirm-apply yang fail-closed.
Action keselamatan pengguna dalam modal turut diperkukuh melalui M2 untuk reset
password, deactivate dan reactivate secara atomic serta boleh diaudit.

## Perubahan UI

- Version Releases menggunakan release cards dan changelog berstruktur.
- SSO Configuration disusun sebagai configuration panel yang lebih jelas.
- Sync Log menggunakan jadual compact, status badge, pagination dan action ikon.
- Audit Log menggunakan filter card, result counter, tooltip dan state lengkap.
- Active Sessions memisahkan masa token, pengguna, peranti dan status.
- User Accounts menambah search result card, category metrics dan action compact.
- Web Apps admin menggunakan category navigation dan application cards.
- Dashboard pengguna menggunakan application directory serta badge SSO/direct.
- Semua jadual berkaitan disusun left/top align dan mengelakkan scroll mendatar.

## Metadata Aplikasi

`config/application.php` menjadi source tunggal untuk:

- `ONEID_APP_VERSION`;
- tahun copyright;
- pemilik aplikasi;
- teks footer login, dashboard pengguna dan dashboard admin.

Nilai release ini ialah:

`2026 © PTMK | Aplikasi Digital. Version 2.0.2`

## Pembetulan Device Session

Kod lama menggabungkan `getDeviceName()` dengan `getBrandName()` secara terus.
Untuk desktop, User-Agent biasanya hanya memberi jenis `desktop` dan tidak
memberi brand perkakasan. Ini menghasilkan nilai `desktop ()`.

Mulai v2.0.2:

- rekod lama seperti `desktop ()` dinormalkan kepada `Desktop` ketika dipaparkan;
- login baharu menyimpan format seperti `Desktop · Firefox · Windows`;
- brand/model hanya ditambah apabila parser benar-benar menemuinya;
- password rotation menggunakan maklumat peranti yang sama, bukan label
  `Password change`.

Nama komputer atau hostname tidak boleh diperoleh dengan boleh dipercayai
daripada browser biasa dan tidak direka untuk direkod oleh perubahan ini.

## Boundary Keselamatan

- Tiada historical token row dikemas kini secara pukal.
- Tiada token aktif ditamatkan.
- Tiada perubahan kepada timeout atau multi-session policy.
- Tiada perubahan kepada endpoint atau contract SSO consumer.
- External Sync Apply kekal disabled mengikut gate S4E.
- Single-user Resync hanya membenarkan akaun external, menggunakan external
  SELECT-only lookup dan memerlukan preview server-side sebelum Apply.
- Single-user Resync dan full External Sync ialah dua flow berbeza; penambahan
  M1 tidak mengaktifkan full-sync Apply S4E.
- Force Reset Password, Remove dan Reactivate kini mengunci row pengguna,
  membatalkan token/OTP dan mewajibkan audit dalam transaction yang sama.
- Admin tidak boleh force-reset atau deactivate akaun sendiri melalui modal.

## Verification

- PHP syntax lint untuk fail yang berubah.
- Contract aset dan compatibility.
- Sync preview zero-mutation contract.
- Public-root smoke test.
- Manual hard refresh dan navigation antara tab disyorkan selepas deployment.
