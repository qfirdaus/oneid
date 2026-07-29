# MyDigital ID v2.6.4 — Release Audit

**Tarikh audit:** 26 Julai 2026
**Change reference:** `ONEID-V264-CHANGELOG-20260726-01`
**Keputusan:** GO untuk kemas kini kod staging; production kekal NO-GO

## Skop yang digabungkan

Versi 2.6.4 menjadi versi tunggal bagi keseluruhan pelaksanaan MyDigital ID
Fasa 0 hingga Fasa 7:

1. preflight, baseline dan semakan kontrak penyedia;
2. konfigurasi serta OIDC client yang dikawal feature flag;
3. schema identity link dan authentication audit;
4. callback, pemadanan akaun aktif dan pembentukan sesi OneID;
5. pilihan login kedua, mesej BM/English dan provider logout;
6. automated security serta regression suite; dan
7. rejection UX, pertukaran akaun dan Nginx callback-log hardening.

Kawalan utama tidak berubah: tiada auto-registration, tiada profile overwrite,
exact-one active NRIC match diperlukan, akaun password kekal tersedia dan
production tidak diaktifkan.

## Finding staging terkini

- Login MyDigital ID bagi akaun pilot berjaya dan menghasilkan sesi OneID.
- Identiti yang tiada akaun OneID aktif ditolak sebagai
  `MYDID_USER_NOT_FOUND`; penolakan satu pengguna tidak menghalang pengguna
  lain daripada memulakan login baharu.
- Butang mencuba akaun lain membersihkan transaksi tempatan dan menggunakan
  endpoint logout rasmi MyDigital ID.
- Nginx staging merekod path callback tanpa query `code` dan `state`.
- Credential dan HMAC key kekal di private runtime, bukan dalam Git.
- Schema telah diaplikasikan pada shared development/staging database selepas
  backup yang disahkan; schema flag dikembalikan kepada `false`.

## Audit changelog

Sumber paparan baharu ialah
`config/content/release_changelog_plain.php`. Katalog:

- mempunyai 39 versi unik daripada 2.6.4 hingga 1.0.1;
- mempunyai parity 235/235 catatan BM dan English;
- mengekalkan tarikh serta turutan sejarah;
- mengekalkan setiap catatan sejarah tanpa menggabung atau membuang item;
- dijana kepada artefak katalog dengan digest approval; dan
- masih menyediakan fallback BM jika katalog approved gagal dimuatkan.

Butiran teknikal tidak dipadam daripada repositori; ia kekal dalam dokumen audit
fasa dan close-out.

## Penutupan staging dan kerja production

Gate A staging ditutup `PASS / CLOSED` pada 29 Julai 2026 selepas browser
acceptance, account-switch, negative authorization, password regression, ACL,
logout, secret/reference review dan observation 72 jam disahkan berjaya.

Integrasi belum dianggap production-ready. Baki kerja berautoriti kini hanya
Gate B: keseluruhan provisioning, DBA, backup/restore, secret/key custody,
Nginx, controlled production pilot, observation dan kelulusan production.

Senarai penuh dan owner dirujuk dalam
`MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md`.

Pelan kerja untuk menyelesaikan item tersebut, termasuk Wave 0–5, evidence,
monitoring, stop condition dan closure gate dirujuk dalam
`MYDIGITALID_BAKI_ACCEPTANCE_DAN_PELAN_PENUTUPAN.md`.
