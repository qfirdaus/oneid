# Panduan Integrasi OneID — UPNM Mobile iOS

**Tarikh:** 7 Oktober 2026  
**Environment:** STAGING SAHAJA

## Konfigurasi diluluskan

| Tetapan | Nilai |
| --- | --- |
| Nama aplikasi | UPNM Mobile |
| Bundle ID | `com.upnmmobile1.app` |
| Client ID OneID | `upnm-mobile-ios-staging` |
| Redirect URI | `com.upnmmobile1.app://oneid/callback` |
| Minimum iOS | iOS 15.0 |
| Peranti penerimaan | iPhone 15, iOS 18.1.1 |
| Issuer | `https://oneid-uat.upnm.edu.my/` |
| Discovery | `https://oneid-uat.upnm.edu.my/.well-known/openid-configuration` |
| Flow | Authorization Code dengan PKCE S256 |
| Scopes | `openid profile offline_access mobile:session` |
| Audience | `oneid-mobile-session` |
| Jenis client | Public client, `token_endpoint_auth_method=none` |
| Client secret | Tiada |

Client telah didaftarkan pada provider dan allowlist adapter OneID staging pada 7 Oktober 2026. Authorization request server dengan PKCE S256 berjaya sampai ke halaman `Login dengan OneID` melalui HTTPS. Ini mengesahkan konfigurasi server, tetapi belum menggantikan ujian native pada peranti.

Gunakan browser sistem melalui `ASWebAuthenticationSession` atau sokongan setara dalam pustaka OIDC Flutter. Aplikasi mesti menjana dan mengesahkan `state`, `nonce` serta pasangan PKCE bagi setiap cubaan. Jangan gunakan WebView terbenam dan jangan simpan token dalam storan biasa.

## Callback iOS

Daftar custom URL scheme `com.upnmmobile1.app` pada target iOS. Terima hanya callback yang sepadan tepat dengan scheme `com.upnmmobile1.app`, host `oneid` dan path `/callback`, kemudian serahkan kepada transaksi OIDC asal.

Uji callback ketika aplikasi aktif, di background dan cold start. Uji juga app-switch MyDigital ID, pembatalan pengguna, state/nonce tidak sepadan, code replay, refresh rotation, logout dan pembukaan semula aplikasi.

## Had semasa

Konfigurasi ini untuk staging sahaja dan tidak boleh digunakan untuk production. Pendaftaran server tidak membuktikan native iOS E2E; penerimaan akhir memerlukan iPhone 15 dengan iOS 18.1.1. Mobile production kekal OFF.

## Semakan native 7 Oktober 2026

Log server mengesahkan iPhone 15/iOS 18.1.1 berjaya melalui authorization, login, consent, token exchange dan `/mobile/session` HTTP 200. Semakan rekod binding mendapati sesi berjaya tersebut masih menggunakan `upnm-mobile-android-staging`; client iOS hanya mempunyai transaksi yang berhenti pada peringkat `PASSWORD` dan belum menghasilkan sesi.

Team mobile perlu menetapkan `client_id=upnm-mobile-ios-staging` pada build iOS, membersihkan state/token ujian lama dan mengulangi login. Penerimaan iOS hanya lengkap apabila rekod sesi baharu terikat kepada client iOS dan aliran session/refresh/logout lulus.
