# Ujian login terkawal staging

## Persediaan 29 September 2026

Pemilik meluluskan akaun staf 0530-09. Semakan READ ONLY: tepat satu akaun, aktif, password_change_required=0. Tiada password dibaca/diminta untuk ujian ini; pemilik memasukkan password dan MFA sendiri pada halaman OneID. Pemeriksaan flag ini bukan bukti password sah atau MFA telah lulus.

Observer masih OFF dan jumlah rekod state mobile ialah sifar ketika semakan. Endpoint awam masih dormant. Konfigurasi calon client ada dalam `deployment/mobile-oidc/controlled-test-client.json`; belum didaftarkan atau ditambah ke allowlist adapter. Callback loopback adalah cadangan untuk harness browser pada komputer penguji, bukan callback Flutter release. Harness mesti tersedia dahulu sebelum authorization dimulakan.

## Gate sebelum ujian

- Tentukan IP/rangkaian penguji yang benar-benar dilihat oleh Nginx, termasuk proxy/VPN. Jangan mempercayai header X-Forwarded-For daripada sumber tidak dipercayai atau membenarkan semua pengguna di belakang proxy secara tidak sengaja.
- Sediakan harness PKCE/state/nonce dan callback pada komputer penguji; token tidak dicetak atau disimpan dalam dokumen/log.
- Hadkan client dan identiti ujian di server, kemudian daftarkan client dan konfigurasi callback tepat.
- Selaraskan epoch akaun semasa observer OFF di bawah kawalan transaksi; hidupkan observer hanya selepas semua trigger lengkap dan pelan pemulihan tersedia.
- Aktifkan endpoint hanya untuk rangkaian penguji. Hook provider kekal loopback dan memerlukan shared secret. Sahkan resolusi hostname hook dari Hydra menuju loopback dengan TLS sah.

## Urutan pengesahan

1. Permintaan daripada luar rangkaian penguji ditolak; login web sedia ada masih boleh dibuka.
2. Login staf melalui browser sistem, masukkan credential sendiri, selesaikan MFA jika polisi memerlukannya.
3. Callback menyemak state; pertukaran code memerlukan PKCE S256. Semak signature/issuer/audience/nonce ID token dan semakan sesi access token.
4. Refresh tanpa browser login; refresh/token tidak diterima sebagai pengganti access token di API sesi.
5. Logout membatalkan sesi ujian, refresh berikutnya gagal. Semak login web biasa tidak dibatalkan oleh login mobile biasa.
6. Tutup pilot selepas ujian dan rekod keputusan sanitised. Ujian pelajar, reset faktor/password serta perubahan status akaun memerlukan akaun ujian berasingan; jangan ubah akaun staf sebenar untuk simulasi tersebut.

Keputusan login sebenar masih pending. Tiada dakwaan login, MFA, refresh atau logout sebenar telah diuji.

## Kemas kini paparan hosted login

Paparan dikemas kini dengan logo OneID dan UPNM sedia ada, header berjenama, susun atur responsif serta kad berasingan untuk MFA emel dan Authenticator. Label butang mengenal pasti kaedah yang disahkan; bantuan Authenticator menjelaskan pendaftaran pengguna berasingan daripada admin. CSP membenarkan imej same-origin sahaja. Endpoint, field POST, CSRF dan pengesahan credential tidak ditukar. Ujian staf sebenar termasuk Authenticator dilaporkan berjaya oleh pemilik sebelum perubahan paparan; reka bentuk baharu masih perlu dilihat pada browser penguji.
