# Audit susulan kesediaan teknikal OneID Mobile

Tarikh: 29 September 2026. Baseline `06f364d`. Semakan kedua selepas reka bentuk awal.

## Keputusan

Keperluan fungsi jelas. Reka bentuk masih **belum sedia untuk rollout production**. Semakan kedua menemui isu konkrit tambahan; ia tidak sekadar mengulangi senarai semak. Tiada kod runtime, konfigurasi, dependency atau data aplikasi diubah. Fail dokumentasi sahaja dikemas kini.

Status bukti dibezakan sebagai: **disahkan kod**, **disahkan konfigurasi/agregat staging**, **disokong dokumentasi upstream**, atau **belum diuji hujung-ke-hujung**. Tiada jaminan bahawa semua masalah masa depan boleh dikesan tanpa provider/Flutter UAT sebenar.

## 1. Pemeriksaan tambahan yang telah dilakukan

- Kod login, MFA pending/finalize, kategori/polisi MFA, password reset, account status, sync rollback, bootstrap, maintenance dan revocation.
- Bacaan database menggunakan konfigurasi `ONEID_ENVIRONMENT=staging`, PDO terus tanpa bootstrap login; `START TRANSACTION READ ONLY`, timeout query 5 saat, SELECT agregat dan metadata sahaja, kemudian rollback. Tidak mencetak DSN, secret, hash atau rekod identiti. Label staging tidak membuktikan database itu terasing fizikal daripada production.
- Konfigurasi Nginx OneID UAT yang dipasang, log format, PHP-FPM pool, storan/RAM dan status penyegerakan masa. Tidak reload servis dan tidak menguji akaun manusia.
- Dokumentasi rasmi provider, Flutter AppAuth, secure storage dan sumber AppAuth Android. Belum memuat turun/menjalankan provider atau build Flutter.

### Hasil agregat staging

| Pemeriksaan | Hasil semasa |
|---|---|
| Jumlah akaun / akaun aktif | 11,180 / 7,448 |
| Akaun aktif wajib tukar password | 1,524 |
| Akaun aktif dengan u_type admin | 3 |
| Membership staff aktif | 1,072 akaun |
| Membership student aktif | 6,375 akaun |
| Akaun aktif tanpa membership staff/student | 1; kategori 3, u_type 0 |
| Akaun dengan dua source family aktif | 0 |
| Matrik pelajar | Semua 6,375 pelajar bermembership mempunyai data4 dan u_id = data4 |
| Duplicate nombor staf aktif / matrik dalam pelajar bermembership | 0 / 0 kumpulan |
| Nombor staf bertembung dengan u_id akaun aktif lain | 0 pasangan |
| Padanan tepat calon IC staf data4 dengan IC pelajar data2 | 0 pasangan; bukan bukti tiada individu dwistatus kerana format/provenance berbeza mungkin tidak dipadankan |
| Nama/email kosong dalam akaun aktif | 0 / 1 |
| Multi-session legacy / token_timeout | 0 (satu sesi) / 0.5 jam |
| MFA runtime / stored mode | ENFORCED / ENFORCED; scope PASSWORD_ONLY |
| MFA kategori | STAFF enforcement 0; STUDENT enforcement 1 |
| Faktor diaktifkan / TTL pending dan OTP | Email dan TOTP / 300 saat setiap satu |
| Maintenance konfigurasi | SCHEDULED; tidak membuktikan maintenance sedang aktif, jadual tidak dievaluasi |
| Charset runtime / collation user_tbl | latin1 / utf8mb4_0900_ai_ci |

Ini snapshot konfigurasi staging, bukan pengesahan polisi production. Jumlah akaun ialah bukti perancangan dan bukan sasaran load/SLA yang telah diluluskan.

## 2. Daftar isu dan tindakan penutupan

### R01 — Login mobile boleh membatalkan sesi web (kritikal, disahkan)

`multi_session=0` benar-benar aktif pada staging. `lib/q_func.php` dan finalizer MyDigital ID boleh memanggil pembatalan semua token legacy. Oleh itu login mobile tidak boleh sekadar redirect kepada finalisasi legacy.

Tindakan: adapter/finalizer mobile dan token store berasingan; cookie/session namespace host-only berasingan. Ujian wajib membuka sesi web A dahulu, login/logout mobile B, kemudian membuktikan A masih sah. Uji arah sebaliknya. Pemilik: OneID.

### R02 — Forced password change ialah kes pengguna besar (kritikal, disahkan)

1,524 akaun aktif mempunyai password_change_required. Verifier turut boleh menaikkan flag apabila password default dikenal pasti, jadi angka itu bukan had maksimum. Jika mobile hanya menerima boolean password betul, pengguna boleh memintas proses wajib atau tersekat tanpa jalan selesai.

Tindakan: sediakan UI hosted password-change dalam transaksi mobile sebelum code dikeluarkan. Jangan mewujudkan sesi penuh dahulu. Pastikan refresh sedia ada ditolak apabila password change diwajibkan kemudian. Ujian akaun ujian sahaja: first login, reset admin, self-reset, rehash legacy dan password reuse/history. Rehash kerana algoritma dinaik taraf tidak sama dengan pengguna menukar password; jangan batalkan semua sesi kerana rehash sahaja. Pemilik: OneID.

### R03 — Polisi MFA bukan satu tetapan untuk semua pengguna (kritikal, disahkan)

Polisi kategori staff/student berbeza. `PdoUserMfaPolicyReader::selfServiceEligible()` mengehadkan u_type=0; `UserMfaPrimaryAuthDecision` menggabungkan syarat itu sebelum mewajibkan kategori. Terdapat 3 akaun admin aktif. Menggunakan decision ini sahaja untuk mobile boleh tidak melaksanakan MFA admin seperti yang dijangka; ini risiko integrasi, bukan dakwaan eksploit yang diuji pada web semasa.

Tindakan: matriks user category × u_type × policy effective × exemption × factor availability. Akaun admin yang juga staf tidak mendapat role admin mobile, tetapi mesti menerima polisi pengesahan yang betul. Jangan jadikan account role sebagai pengecualian MFA automatik. Factor reset/recovery/exemption tamat/policy berubah perlu mengesan assurance sesi persistent yang sudah diterbitkan. Aktifkan reauthentication apabila assurance lama tidak lagi memenuhi polisi; auth_time tidak diperbaharui seolah-olah password/MFA diulang pada setiap refresh. Pemilik: OneID; aturan admin perlu disahkan sebelum adapter dibekukan.

### R04 — Race antara password, MFA, account disable dan code exchange (kritikal, disahkan kod)

`UserMfaPendingLoginCoordinator::lockedPending()` memeriksa session/browser binding dan expiry; `PdoUserMfaPendingLoginPersistence::pendingLoginForUpdate()` membaca transaksi, bukan current account status/password flag. Factor method tidak disimpan oleh `markFactorVerified()` yang menerima factorType dalam signature tetapi hanya menukar status.

Tindakan: mobile revalidate current account/security version dan effective policy semasa finalize, token exchange dan refresh. Evidence amr/acr diambil daripada rekod faktor berjaya yang terikat transaksi, bukan input browser atau tekaan daripada VERIFIED. Jangan guna IP kekal sebagai binding telefon; kod semasa memeriksa session + browser, IP untuk audit. Uji Wi-Fi → data mudah alih semasa OTP, disable selepas password, reset selepas MFA dan policy berubah sebelum token diterbitkan. Pemilik: OneID.

### R05 — Sebab revocation legacy tidak cukup tepat (kritikal, disahkan)

`UserProfilePolicyService` mengeluarkan reason ACCOUNT_DISABLED ketika kategori berubah, walaupun tidak mengubah avail_status. `UserAclManagementService` menggunakan SECURITY_ACTION bagi perubahan akses aplikasi lama. `UserSecurityActionService` menggunakan reason umum juga untuk reset/deactivate/reactivate. Pemetaan satu reason → revoke mobile akan menghasilkan logout yang salah.

Tindakan: event khusus berdasarkan caller/tindakan dan before/after state, bukan reason sahaja. Inventori wajib: password change, InitialPasswordSetupService, reset melalui q_func (dua cabang), admin reset/deactivate/reactivate, MFA recovery/revoke, sync serta rollback yang boleh DELETE user_tbl. Pisahkan ACL web daripada kelayakan mobile. Delete/recreate ID tidak mewarisi subject atau sesi lama. Pemilik: OneID.

### R06 — Kekal login belum boleh dianggap selesai dengan refresh token sahaja (kritikal, reka bentuk)

Lost response, app crash selepas server memutar token, dua request refresh serentak, storan rosak dan credential tamat boleh menyebabkan logout walaupun pengguna tidak memilih logout. User tidak meminta timeout rutin; jangan memasukkan expiry tersembunyi sebagai default implementasi.

Tindakan: pin provider/version; sahkan sliding/absolute/inactivity semantics; pilih grace kecil yang terhad dan replay detection family. Flutter single-flight, penyimpanan atomik serta generation counter: response refresh yang tiba selepas logout atau account switch mesti dibuang dan tidak menulis token semula. Semua profil/cache/request in-flight lama dibersihkan apabila akaun berubah. Polisi expiry kekal keputusan terbuka dengan pemilik; bukti restart/kill/long-idle sebenar diperlukan. Pemilik: OneID + Flutter.

### R07 — Provider candidate mempunyai hook relevan, tetapi self-hosted belum dibuktikan (kritikal)

Dokumentasi Ory menyatakan generic token webhook boleh menolak pertukaran token dan kegagalan hook menghentikan exchange. Ia juga membezakan legacy refresh-only hook yang deprecated. Ini menguatkan kesesuaian reka bentuk, tetapi dokumentasi managed/current tidak membuktikan semua fungsi dalam release self-hosted yang bakal dipilih.

Tindakan: proof of concept menggunakan release pin dan lesen yang diperiksa; generic hook authenticated, fail closed, bounded timeout dan tiada token/password dalam log. Uji client/session/subject context sah pada initial exchange dan refresh, penyekatan akaun serta per-device revocation. Provider login cookie atau remembered consent tidak boleh memintas status akaun semasa. Jangan jemput Flutter guna endpoint sebelum perkara ini dibuktikan. Pemilik: OneID/infrastruktur.

### R08 — Endpoint /mobile/session belum mempunyai kontrak authorization lengkap (kritikal, diperincikan)

Tetapkan access token audience khusus `oneid-mobile-session` (nama cadangan) dan scope `mobile:session`; daftar allowlist client Android/iOS setiap environment. Adapter memeriksa active, expiry, audience, scope, client ID, opaque subject, mobile session reference dan account security version. Jangan menerima ID token atau token SSO lama. Jika opaque token, introspection melalui private provider API; private API credential tidak diberikan kepada Flutter.

Cadangan respons minimum: subject, account_type, display_name dan session_status. HTTP 401 token invalid/revoked → reauth; 403 account unavailable → hentikan akses; 503 dependency/maintenance → simpan credential tetapi jangan buka akses online; 429 → retry terkawal. Jangan pulangkan status active daripada cache tanpa had ketika DB gagal. Test cross-client/environment/audience dan replay sesi A pada konteks B. Pemilik: OneID.

### R09 — SDK Flutter tidak semestinya mengesahkan signature seperti yang disebut dahulu (tinggi, disahkan upstream)

AppAuth Android yang disemak menggunakan TLS token endpoint sebagai pilihan OIDC code flow dan tidak membuat signature verification ID token secara default. Ini bukan bukti SDK rosak, tetapi andaian reka bentuk awal perlu dibetulkan.

Tindakan: pin flutter_appauth serta native SDK Android/iOS; audit validation path, issuer, audience/azp, nonce dan state. Jika kontrak memerlukan local signature validation, pilih verifier standard dan uji key rotation. Jangan hanya decode JWT. Refresh ID token mungkin tiada; jangan wajibkan identity token baharu pada setiap refresh. Jangan gunakan ID token lama yang expired sebagai alasan memadam refresh session yang masih sah. Pemilik: Flutter + OneID.

### R10 — Route server sedia ada boleh menyekat discovery dan callback (tinggi, disahkan konfigurasi)

Nginx UAT mempunyai regex `location ~ /\.` deny all. Ia meliputi `.well-known`; jika template ini disalin ke issuer/link host, discovery, JWKS atau fail app-link verification boleh disekat. PHP routes semasa juga memerlukan fail/rewrite yang wujud, bukan semua route extensionless diterima.

Tindakan: host baharu dengan allowlist exact location bagi discovery/JWKS/assetlinks/AASA yang diperlukan tanpa membuka semua dotfiles. Uji WAF/proxy, TLS chain pada telefon sebenar, Content-Type dan tiada redirect tidak diingini untuk link association. Android memerlukan signing certificate fingerprint termasuk Play signing jika digunakan; iOS memerlukan Team ID/bundle ID/associated domains. Debug dan release mesti diuji berasingan. Pemilik: Infrastruktur + Flutter.

### R11 — Semua akaun aktif dan resolver membership perlu konsisten (tinggi, disahkan agregat)

Satu akaun staff-category aktif tidak mempunyai membership staff/student. Resolver yang hanya percaya membership akan menolak akaun sah walaupun syarat pemilik ialah semua akaun aktif. Data dwistatus pula belum dapat dibuktikan; tiada contoh dual family pada snapshot.

Tindakan: pemetaan kategori/fallback yang disahkan dan provenance, tanpa menjadikan pendaftaran membership sebagai kelulusan tambahan. Gunakan fixture staff/student berasingan untuk satu individu dan akaun manual aktif. Angka 0 collision hanya snapshot; kekalkan uniqueness check pada runtime. Nilai 0/1 dalam borang bukan nilai u_category OneID dan bukan u_type admin. Pemilik: OneID.

### R12 — Bootstrap/cookie/maintenance bukan helper neutral (tinggi, disahkan)

`lib/config.php` mengaktifkan session policy dan MaintenanceGate selepas DB init. Shared CSP `form-action self`/connect-src serta APP_URL tidak semestinya sesuai untuk redirect host mobile. Memanggil bootstrap itu secara terus boleh membawa redirect HTML/timeout lama kepada endpoint JSON.

Tindakan: bootstrap mobile khusus tanpa finalisasi/session expiry legacy; kekalkan polisi maintenance organisasi dengan respons mobile yang jelas. Bezakan maintenance login baharu dan refresh/session sedia ada. Jangan jadikan maintenance sebagai invalid credential atau padam token. Host-only cookie secure/httpOnly, nama sesi mobile berbeza, CSRF pada form hosted, tiada wildcard Domain dan tiada automatic authenticated session dari user ID dalam query. Pemilik: OneID.

### R13 — Reuse servis keselamatan boleh menjejaskan web melalui resource dan rate limit (tinggi)

Nginx OneID UAT menggunakan pool PHP-FPM umum `/run/php/php8.3-fpm.sock`; pool ditemui max_children=20. Shared MFA DB/audit, email quota dan rate-limit juga boleh memberi kesan silang walaupun token dipisahkan.

Tindakan: pool/worker budget mobile berasingan, bounded DB/provider/mail timeout. Rate-limit per akaun/per IP dengan trusted proxy resolver; jangan percaya X-Forwarded-For bebas. Kekalkan kawalan brute-force merentasi identiti tanpa membiarkan client yang gagal memenuhi pool web. Load test login+OTP+refresh bersama traffic legacy. Pengecualian bukan blanket IP allowlist untuk pengguna telefon awam. Pemilik: Infrastruktur + OneID.

### R14 — Storan telefon, reinstall dan offline logout (tinggi, belum diuji peranti)

Keychain iOS tidak boleh dianggap sentiasa terpadam selepas uninstall; Android backup boleh memulihkan ciphertext tanpa key yang sesuai. Secure storage failure bukan bukti akaun dibatalkan. Provider browser cookie juga boleh menghidupkan semula login secara senyap selepas logout lokal jika UI terus memulakan authorization.

Tindakan: instance marker baharu pada pemasangan, dasar device-only/no cross-device token backup, first-run cleanup yang diuji, pemisahan key UAT/production dan handling key invalidation. Logout state mesti memenangi late refresh. Offline logout bersihkan lokal; simpan hanya mekanisme revocation tertangguh yang tidak memulihkan login dan uji retry. Selepas logout tunjuk butang login; jangan auto-authorize. Pemilik: Flutter.

### R15 — Migration/outbox failure dan rollback boleh bercanggah dengan syarat web tidak terganggu (kritikal, reka bentuk diperketat)

Menambah outbox INSERT kepada transaksi password/disable boleh menyebabkan tindakan legacy gagal jika jadual mobile rosak/tidak tersedia. Pada masa sama, membuang kegagalan outbox secara senyap membolehkan token mobile terus sah. Feature OFF kemudian ON juga boleh menghidupkan sesi lama jika lifecycle events tidak direkod.

Tindakan: failover keselamatan mesti ditetapkan dan diuji sebelum mengubah caller lama. Cadangan normal ialah local atomic security version + outbox; kegagalan mengekalkan invariant tidak boleh disembunyikan. Prosedur kecemasan mesti boleh mematikan issuance/status/refresh mobile pada provider/gateway secara bebas, membiarkan operasi legacy dipulihkan dan menaikkan deployment/session epoch sebelum mobile dihidupkan semula. Jangan menjanjikan zero disruption apabila shared security write gagal. Trigger/CDC atau gateway alternative tidak dipilih tanpa penilaian. Uji injected DB failure, queue down, provider down, feature off/on dan restore backup. Pemilik: OneID + Infrastruktur.

### R16 — Persistence, format dan kapasiti operasi (tinggi, sebahagian disahkan)

Connection charset default runtime latin1 sedangkan table utf8mb4. Mobile JSON/claim perlu laluan UTF-8 yang diuji tanpa menukar connection legacy secara global. Masa server terselaras NTP, tetapi masa telefon salah masih boleh menjejaskan token validation.

Snapshot host: kira-kira 80 GB disk tersedia dan 14.5 GB RAM available; ini bukan load test atau pengesahan kapasiti production. Docker/Podman tidak ditemui melalui PATH; provider belum tersedia. Logging OneID Nginx sedia ada mengecualikan query/referrer, tetapi issuer/adapter/proxy baharu mesti mempunyai redaction sendiri.

Tindakan: pilih deployment binary/service/container yang dikendalikan infrastruktur; dedicated DB credentials/schema, persistent signing/system keys dan encrypted backups. Tetapkan SLA, latency budget, RTO/RPO, retention token/audit/outbox serta reconciliation. Provider restore boleh memulihkan token yang sudah revoked; security epoch/reconciliation wajib sebelum buka traffic. Jangan restore key/state lama secara bebas. Uji UTF-8 nama, UTC epoch/time skew, key rotation dan restart. Pemilik: Infrastruktur + OneID.

## 3. Ujian tambahan yang benar-benar dijalankan

| Runner | Hasil | Had bukti |
|---|---|---|
| user_login_mfa_u3_pending_login.php | 12 lulus | Fixture in-memory; tiada DB/network; MFA pending dan once-only completion |
| login_proxy_rate_limit.php | 7 lulus | Helper proxy dan pemeriksaan sumber; bukan traffic load |
| user_session_timeout_f1_policy.php | 21 lulus | Fixture polisi browser; bukan persistent session mobile |

40 semakan tambahan lulus. Output fallback session policy/audit dalam runner timeout datang daripada fixture kes ralat yang disengajakan; proses exit 0. Digabung dengan 34 semakan audit pertama, 74 semakan baseline telah lulus, tetapi **tiada satu pun merupakan ujian end-to-end provider OIDC + Flutter**, kerana komponen itu belum dibina.

## 4. Gate sebelum pembangunan penuh dan production

| Gate | Bukti penutupan | Status |
|---|---|---|
| G1 Provider | Version/lesen/config pin; code, refresh, hook, per-device revoke dan grace dibuktikan pada instance terasing | Terbuka; dokumentasi menyokong calon sahaja |
| G2 Identity/policy | Akaun ujian staf, pelajar, admin-staf, manual tanpa membership, dwistatus, forced change dan MFA recovery | Agregat disahkan; fixture/E2E belum |
| G3 Persistent session | Polisi refresh jelas; restart/late-response/rotation/logout/offline/reinstall lulus | Terbuka bersama Flutter |
| G4 Infrastructure | DNS/TLS/callback fingerprints, private admin API, routing .well-known, pool, database isolation dan backups | UAT lokal disemak; host baharu/production belum |
| G5 Security lifecycle | Semua writer reset/disable/sync/factor dipetakan; failover/outbox/feature off-on diuji | Inventori kod ditambah; implementasi belum |
| G6 Legacy regression | Web+SSO+MFA+MyDigitalID masih berfungsi ketika mobile aktif, gagal dan rollback | Baseline helper lulus; E2E belum |
| G7 Client validation | SDK pin/validation contract, audience scope status endpoint, Android/iOS release callback | Terbuka; Flutter source belum diberikan |

Proof of concept boleh bermula tanpa data/akaun sebenar. Pembangunan penuh tidak patut dibekukan atas andaian gate ini telah selesai. Tidak perlu menambah MyCampus atau pendaftaran aplikasi untuk menutup mana-mana gate.

## 5. Maklumat minimum yang masih diperlukan daripada pihak lain

Team mobile: Flutter/Dart version, min Android/iOS, package/bundle IDs setiap environment, signing fingerprint/Team ID untuk link association, pilihan callback, build UAT dan akses kod auth untuk review. Jangan minta private signing key atau password.

Infrastruktur: lokasi issuer/adapter, DNS/TLS, bukti pemisahan DB UAT/production, kaedah operasi provider, backup/restore, trafik jangkaan dan pemilik on-call. Snapshot host ini tidak menggantikan maklumat production.

Pemilik OneID: maksud sesi selepas berbulan tidak aktif, kesan reset password, dan pengesahan polisi akaun admin-staf. Keperluan semasa ialah tiada logout rutin; tiada expiry baharu diaktifkan dalam audit ini.

## Rujukan upstream

- [Ory token webhooks](https://www.ory.com/docs/hydra/guides/claims-at-refresh): generic hook dan rejection; release self-hosted perlu disahkan.
- [Ory graceful rotation](https://changelog.ory.com/announcements/graceful-token-rotation-in-ory-oauth2-ory-hydra): refresh retry/grace dan chain revocation; bukan jaminan config release yang belum dipilih.
- [AppAuth Android IdToken.java](https://github.com/openid/AppAuth-Android/blob/master/library/java/net/openid/appauth/IdToken.java): validation code flow melalui TLS serta claim checks. Master ialah bukti audit semasa, bukan versi dependency yang dipin.
- [flutter_appauth](https://pub.dev/packages/flutter_appauth): code exchange, nonce/verifier, refresh dan pembatalan browser.
- [flutter_secure_storage](https://pub.dev/packages/flutter_secure_storage): platform secure storage dan isu backup Android.
- [RFC 9700](https://www.rfc-editor.org/rfc/rfc9700.html): refresh rotation dan inactivity policy.
