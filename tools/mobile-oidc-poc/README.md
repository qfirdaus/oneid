# OneID mobile OIDC — Phase A UAT PoC

Runner ini menguji provider sebenar dengan identiti rekaan. Ia **bukan endpoint
login OneID**, bukan deployment, dan belum boleh digunakan oleh Flutter.
Ia hanya dibenarkan dalam `/var/www/oneid-uat` pada Linux amd64.

## Jalankan

```bash
cd /var/www/oneid-uat
PYTHONDONTWRITEBYTECODE=1 python3 tools/mobile-oidc-poc/prepare.py --uat-only
PYTHONDONTWRITEBYTECODE=1 python3 tools/mobile-oidc-poc/run.py --uat-only
PYTHONDONTWRITEBYTECODE=1 python3 tools/mobile-oidc-poc/run.py --uat-only --grace 5s
```

Prasyarat: Python 3 standard library, git, apt-get, dpkg-deb, dan dependencies
runtime PostgreSQL 16. Download memerlukan GitHub serta repositori Ubuntu yang
menyediakan versi dipin. Tiada `sudo`, pemasangan pakej sistem atau systemd unit.
Versi/checksum berada dalam `provider-lock.json`; checksum disemak sebelum
extraction. Jika versi sudah tiada di repositori, jangan tukar pin secara senyap.

`prepare.py` mengekstrak executable Hydra dan PostgreSQL ke
`.private/mobile-oidc-poc/`. `run.py` mencipta cluster PostgreSQL baharu bagi
setiap run, menggunakan password rawak dan SCRAM, menjalankan migration Hydra
**pada cluster PoC itu sahaja**, dan memulakan provider serta mock adapter.
Semua listener terikat pada `127.0.0.1` dengan port sementara. Tiada bootstrap,
credential, database aplikasi OneID atau MyCampus digunakan. Database fixture
mempunyai satu identiti staf rekaan; client Android/iOS juga rekaan.

HTTP `--dev` digunakan hanya untuk loopback. Konfigurasi ini tidak sesuai untuk
issuer UAT yang dibuka kepada telefon atau production. Private Admin API
mempunyai kuasa penuh; loopback menghalang akses rangkaian luar tetapi tidak
mengasingkan proses lain pada host. Topologi deployment perlu polisi aksesnya
sendiri.

## Bukti dan cleanup

Runner mencetak PASS/FAIL dan lokasi `report.json`. Ia menghentikan semua child
services dalam `finally`, termasuk jika assertion gagal atau Ctrl-C/SIGTERM.
SIGKILL/power loss tidak boleh ditangkap; semak proses sebelum menghapus run
directory jika berlaku gangguan sedemikian. Tiada auto-start service dibuat.

Setiap run disimpan dalam `.private/mobile-oidc-poc/run-*/` (direktori private,
diabaikan Git): config, password ujian, database, log dan laporan. Jangan commit
atau kongsikan direktori ini. Kongsi hanya laporan sanitised dalam
`docs/integration/bukti-fasa-a/`. Log provider kekal private walaupun
`leak_sensitive_values=false`. Tiada token/password dicetak oleh runner.

## Tafsiran ujian

- Provider mengendalikan code flow, PKCE S256, token, JWKS, userinfo dan revoke.
- Fake login/consent diterima melalui Admin API; password dan MFA sebenar **tidak diuji**.
- Mock hook/session endpoint menguji binding client/session/security version
  dan penolakan ketika akaun disable, forced change atau hook tergendala.
  State akaun/session mock berada dalam memori; restart yang diuji hanya
  proses Hydra, dengan DB/kunci kekal. Persistence adapter belum dibina.
- TTL access 5 saat dan refresh 1 jam ialah nilai ujian, bukan polisi pengguna.
- `--grace 5s` menetapkan reuse count **2**: penggunaan pertama + satu retry.
  Count 1 menolak retry pertama pada versi ini. Count 0 dengan grace positif
  bukan had satu retry. Nilai ini belum ditetapkan sebagai polisi release.
- ID token diperiksa claim-nya; runner ini bukan verifier kriptografi JWT.
- Satu refresh pada satu masa di Flutter, penyimpanan token atomik dan pengujian
  gangguan rangkaian/peranti sebenar masih diperlukan. Retry di sini disimulasikan
  dengan menggunakan semula token lama selepas respons pertama diterima runner.

Hash fail runtime yang sudah tracked dibanding sebelum/selepas setiap run.
Ini bukti fail tidak berubah, bukan pengganti ujian regresi login web/SSO.

Laporan: [Hasil Fasa A](../../docs/integration/Hasil-Fasa-A-OneID-Mobile-OIDC.md).
