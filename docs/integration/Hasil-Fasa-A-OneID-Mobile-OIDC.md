# Hasil Fasa A — OneID Mobile OIDC

Tarikh: 29 September 2026. Workspace: `/var/www/oneid-uat`.

**Fasa A proof of concept provider telah selesai dalam skop identiti rekaan.**
100 assertion lulus merentas dua konfigurasi (banyak kes asas diulang):
48/48 strict rotation dan 52/52 grace rotation. Ini membolehkan pembangunan
identity adapter diteruskan; ia bukan pengesahan sedia production atau Flutter.

Dokumen ini ialah tambahan hasil pelaksanaan kepada
[Dokumen Induk](Dokumen-Induk-Analisis-Teknikal-Kontrak-OneID-Mobile.md)
dan versi DOCX audit terdahulu. Pernyataan gate provider yang masih terbuka
dalam audit asal hendaklah dibaca bersama bukti baharu di sini.

## Skop perubahan

Fail baharu hanya di `tools/mobile-oidc-poc/` dan dokumentasi integrasi.
Executable, secret ujian, log dan database berasingan berada dalam direktori
private yang diabaikan Git. Tiada fail login/PHP legacy, konfigurasi Nginx,
PHP-FPM, database aplikasi atau polisi multisession diubah. Tiada commit/push,
production deployment, client Flutter sebenar atau service kekal dibuat.

Semua listener PoC menggunakan loopback dan port sementara. Semua child
services telah dihentikan selepas ujian. Hash fail runtime tracked sama
sebelum/selepas setiap run; bukti hash ada dalam laporan JSON. Ujian login
web/SSO end-to-end masih wajib apabila adapter sebenar diperkenalkan.

## Provider dan runtime dipin

| Komponen | Keputusan |
|---|---|
| Provider | Ory Hydra v26.2.0, commit `0b84568fffccf151dc5e6c7955fdfb738555bf4b` |
| Lesen | LICENSE release Apache-2.0; tiada ciri enterprise diandaikan |
| Database ujian | PostgreSQL 16.15, Ubuntu package `16.15-0ubuntu0.24.04.1`, diekstrak secara lokal tanpa install sistem |
| Adapter/runner | Python 3 standard library; mock sahaja |
| Client | Dua public clients rekaan: Android/iOS, tiada client secret |
| Access token | Opaque; introspection melalui private Admin API |
| Pengasingan | Cluster DB baharu setiap run, password rawak, SCRAM, bind loopback |

Checksum archive/binary Hydra serta pakej PostgreSQL dipin dalam
[`provider-lock.json`](../../tools/mobile-oidc-poc/provider-lock.json).
Binary rasmi Hydra yang diuji tidak mengandungi sokongan SQLite. Oleh itu
PoC menggunakan PostgreSQL peribadi dan tidak menumpang database OneID.
Pilihan DB operasi sebenar masih perlu dimuktamadkan; hasil ini tidak
membuktikan kesetaraan tingkah laku provider pada MySQL.

Rujukan pin dan semantik:
[release Hydra](https://github.com/ory/hydra/releases/tag/v26.2.0),
[LICENSE](https://github.com/ory/hydra/blob/v26.2.0/LICENSE),
[schema konfigurasi](https://github.com/ory/hydra/blob/v26.2.0/spec/config.json),
[refresh persistence](https://github.com/ory/hydra/blob/v26.2.0/persistence/sql/persister_oauth2.go).

## Bukti ujian

| Keupayaan | Hasil |
|---|---|
| Discovery/JWKS | Issuer tepat dan kunci awam tersedia |
| PKCE | S256 berjaya; missing/plain/wrong verifier ditolak |
| Binding client/callback | Callback tidak berdaftar, pertukaran code/refresh menggunakan client lain ditolak |
| Code dan profil | Pertukaran code, claim issuer/sub/aud/nonce, userinfo identiti rekaan lulus |
| Token validation | Access token sah diterima mock session endpoint; ID token sebagai bearer ditolak |
| Refresh | Access token tamat selepas 5 saat; refresh memulihkan akses tanpa fake login baharu |
| Restart | Refresh masih boleh digunakan selepas proses Hydra restart dengan DB/kunci yang sama |
| Logout peranti | Revoke token peranti A membatalkan access/refresh A; peranti B pada client sama kekal boleh refresh |
| Akaun/security version | Mock disable/forced password change menyekat status/refresh; perubahan version menghalang sesi lama hidup semula |
| Hook | Tanpa secret/context ditolak; refresh menerima session binding; hook 503 menolak refresh tanpa menghabiskan credential |
| Replay | Code replay ditolak dan membatalkan access token terbitannya; strict refresh replay membatalkan successor |
| Cleanup | Child processes berhenti; hash fail runtime tracked tidak berubah |

Bukti terperinci sanitised:
[strict.json](bukti-fasa-a/strict.json) dan [grace.json](bukti-fasa-a/grace.json).
Arahan ulang ujian: [README runner](../../tools/mobile-oidc-poc/README.md).

## Penemuan retry yang mempengaruhi Flutter

Pada versi dipin, `rotation_grace_reuse_count=1` turut mengira penggunaan
pertama dan menyebabkan retry pertama ditolak. Ujian akhir menggunakan
`oauth2.grant.refresh_token.rotation_grace_period=5s` dan
`rotation_grace_reuse_count=2`: penggunaan pertama + satu retry berjaya,
refresh daripada respons retry boleh digunakan, penggunaan lama seterusnya
ditolak, dan retry token lama selepas tamat grace juga ditolak.

Ini toleransi retry terhad, bukan jaminan token lama sentiasa boleh digunakan.
Flutter masih perlu satu operasi refresh pada satu masa, simpan pasangan
token baharu secara atomik, dan elakkan retry tanpa had. Respons pertama
disengajakan tidak digunakan oleh runner untuk mensimulasikan lost response;
gangguan rangkaian dan concurrent refresh sebenar pada telefon belum diuji.
Tetapan 5 saat/count 2 ialah eksperimen dan belum polisi release yang diluluskan.

## Apa OneID akan sediakan kepada mobile

Provider PoC membuktikan asas untuk discovery, authorization code + PKCE,
token/refresh, JWKS, userinfo dan revocation. Versi sebenar nanti perlu
issuer HTTPS UAT yang stabil, client ID berasingan mengikut platform/environment,
callback tepat dan kontrak profil/error. Jangan berikan URL/port sementara PoC
kepada team Flutter sebagai endpoint integrasi.

Endpoint `/mobile/session` dan token hook dalam runner hanyalah mock bagi
membuktikan semakan session/client/account. Ia **belum route dalam OneID**.
OneID adapter sebenar akan menyediakan pengesahan akaun, mapping `sub`,
polisi password/MFA, status sesi dan invalidation lifecycle. Password atau
hash password tidak dipulangkan kepada aplikasi.

## Perkara yang belum dibuktikan

- Password/MFA/reset sebenar, akaun pelajar/dwistatus/admin-staf dan semua
  polisi OneID; fixture hanya satu identiti staf rekaan dengan login diterima
  secara terus melalui Admin API.
- Persistence session/security-version adapter: mock disimpan dalam memori;
  restart yang diuji ialah Hydra sahaja, bukan host, PostgreSQL atau adapter.
- Sesi kekal selepas berbulan tidak aktif: refresh TTL PoC ialah **1 jam**,
  nilai ujian sahaja. Ia belum memenuhi secara penuh polisi tiada logout rutin.
- Validasi tandatangan JWT oleh SDK Flutter, TLS, deep/app/universal links,
  secure storage, cold start, offline, reinstall dan backup/restore.
- Browser SSO/consent/logout sebenar, rate limit, key rotation, load/failover,
  lifecycle/outbox dan regresi web + SSO + MyDigitalID.
- DB/failover sebenar, routing `.well-known`, private Admin API dan deployment
  isolation. Loopback PoC tidak menggantikan polisi akses host/deployment.

## Kedudukan gate dan langkah seterusnya

G1 provider: bukti feasibility Fasa A lengkap untuk stack ujian ini.
G2–G7 kekal terbuka mengikut audit; sebahagian asas refresh/revocation G3/G5
sudah dibuktikan dengan mock, belum dengan OneID/Flutter sebenar.

Fasa B ialah membina identity adapter terasing di UAT: resolver staf/pelajar,
subject mapping, password/MFA/forced change, rate limit, session binding dan
security version; sediakan parity tests sebelum route diaktifkan. Legacy
finalizer yang menulis token/cookie web tidak boleh dipanggil terus oleh
adapter mobile. Kerja ini boleh dimulakan tanpa menunggu semua metadata Flutter;
callback/client sebenar diperlukan sebelum integrasi telefon.

Arahan pengguna kekal: **UAT sahaja; jangan push Git tanpa kebenaran.**
