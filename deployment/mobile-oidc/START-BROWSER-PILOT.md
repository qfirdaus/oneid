# Browser pilot melalui SSH — akaun 0530-09

Skrip ini mengaktifkan pilot staging, bukan pelancaran umum. Jalankan selepas tunnel SSH 8443 disahkan. Pengguna sendiri memasukkan password/MFA. Harness tidak menerima password; credential hanya dihantar kepada halaman OneID.

## Buka pilot pada server

```bash
cd /var/www/oneid-uat
sudo python3 tools/mobile-oidc-hosted/pilot-control.py start
```

Skrip mendaftarkan client public `oneid-uat-controlled-browser`, callback HTTPS `/mobile-test/callback`, PKCE wajib dan scope/audience mobile. Ia menyelaraskan epoch kemudian mengaktifkan observer; hanya akaun dengan identifier `0530-09` dibenarkan melalui adapter. Config peribadi disnapshot dengan masa tamat dua jam. Ini berbeza daripada langkah terdahulu: observer ON sepanjang pilot, dan trigger turut memerhatikan penulisan sumber OneID. Tiada password, status akaun atau faktor pengguna diubah oleh skrip.

Prerequisite: state mobile kosong dan observer OFF. Pelan ini ialah pilot pertama sahaja; ia menolak pemasangan semula jika fail pilot sudah wujud. Jangan padam state untuk memaksa pemeriksaan ini lulus. Jika gagal, skrip cuba memulihkan route dormant dan menutup observer. Baki client/fail/data dikekalkan untuk semakan, bukan dipadam automatik.

Nginx membenarkan hanya loopback pada route mobile. Binding hostname hook Hydra ke 127.0.0.1 hanya dalam namespace systemd Hydra; DNS sistem tidak diubah. Servis Hydra khusus direstart, Nginx diperiksa kemudian direload. Pool web lama tidak diubah. Harness menggunakan pengguna www-data dan port loopback 24147, menyimpan token dalam memori sahaja dan tidak mencatat query/cookie/token. Harness menggunakan token melalui HTTPS sebenar dengan verifikasi sijil.

## Buka Edge berasingan pada Windows

Biarkan terminal SSH pertama aktif. Dalam PowerShell komputer pengguna:

```powershell
& "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe" --user-data-dir="$env:TEMP\oneid-pilot-uat" --no-proxy-server --disable-quic --host-resolver-rules="MAP oneid-uat.upnm.edu.my 127.0.0.1:8443" "https://oneid-uat.upnm.edu.my/mobile-test/"
```

Jika Edge dipasang di lokasi lain, guna lokasi msedge.exe sebenar. Profil ini berasingan daripada browser harian; jangan gunakan browser biasa untuk ujian. Tutup semua window profil ujian sebelum melancarkan semula supaya flags baharu digunakan. Pemetaan host kepada IP:port disokong oleh [ujian host mapping Chromium](https://github.com/chromium/chromium/blob/main/net/base/host_mapping_rules_unittest.cc); verifikasi sijil kekal ON. Windows Edge sebenar belum diuji oleh agent.

Halaman pertama memberi pautan masuk ke ujian. Tekan Login dengan OneID, masukkan 0530-09 dan password sendiri, kemudian lengkapkan MFA yang diminta oleh polisi. Jangan hantar screenshot yang mengandungi OTP/password.

Harness menyemak state/PKCE, signature RS256 melalui JWKS, issuer/audience/nonce/masa ID token dan subject semakan sesi. Butang Semak sesi, Uji refresh dan Logout memaparkan keputusan ringkas tanpa token. Selepas logout, harness cuba refresh lama untuk memastikan ia ditolak. Ini menguji protokol browser, bukan lifecycle secure storage/telefon Flutter.

## Tutup pilot selepas ujian

Selepas pilot dihentikan, gunakan `sudo python3 tools/mobile-oidc-hosted/pilot-control.py retry` untuk ujian seterusnya. Mod ini menyemak backup dormant dan client sedia ada, serta memerlukan konfigurasi pilot terdahulu terhad kepada 0530-09. Rekod sesi/audit tidak dipadam. Epoch/generasi diselaraskan semula kerana observer pernah OFF; sesi lama tidak dipulihkan dan subject ujian boleh berubah. Ini prosedur pilot sahaja, bukan prosedur restart biasa deployment aktif. Mulakan login baharu selepas membuka semula pilot.

```bash
sudo python3 tools/mobile-oidc-hosted/pilot-control.py stop
```

Ini mematikan hosted feature/observer, memulihkan route 404, menghentikan harness dan membuang override DNS Hydra. Client serta data audit dikekalkan. Adapter menolak permintaan selepas dua jam dan servis harness mempunyai RuntimeMaxSec=7200, tetapi observer masih memerhatikan perubahan sehingga arahan stop dijalankan. Oleh itu jalankan stop selepas ujian walaupun had masa sudah tamat.

Tutup browser profil ujian dan gunakan Ctrl+C pada terminal tunnel. Jangan padam keyring, system secret atau database provider.

## Bukti sebelum pengaktifan

Ujian sintetik signature/claim ID token (8 checks), sekatan akaun pilot termasuk senarai kosong, regresi domain/protokol dan pemeriksaan sintaks PHP/Python/Nginx dilaksanakan. Ujian ini tidak menggantikan login sebenar atau membuktikan Windows browser routing. Tiada client pilot didaftarkan oleh agent sebelum arahan start dijalankan pentadbir.
