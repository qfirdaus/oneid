# Laluan ujian melalui SSH dari Windows

Server staging disemak pada 29 September 2026: alamat interface 172.16.2.153. IP 172.16.4.60 yang dilihat oleh Nginx bagi browser pengguna tidak sepadan dengan interface komputer pengguna; jangan gunakan alamat tersebut sebagai bukti identiti penguji.

## Semakan tunnel tanpa pengaktifan endpoint

Dalam PowerShell pertama pada komputer penguji, dengan sambungan rangkaian/VPN sedia ada:

```powershell
ssh -N -o ExitOnForwardFailure=yes -o ServerAliveInterval=30 -L 127.0.0.1:8443:127.0.0.1:443 iqs@172.16.2.153
```

Gunakan authentication SSH biasa pengguna; jangan hantar password ke chat. Semak fingerprint melalui saluran pentadbir jika ini sambungan pertama, jangan abaikan amaran host-key berubah. Terminal kekal terbuka tanpa prompt baharu apabila tunnel aktif. Bind hanya 127.0.0.1, bukan semua interface.

Dalam PowerShell kedua:

```powershell
curl.exe --noproxy "*" --connect-to oneid-uat.upnm.edu.my:443:127.0.0.1:8443 --max-time 15 -sS -o NUL -w "%{http_code}\n" https://oneid-uat.upnm.edu.my/mobile/session
```

Keputusan dijangka: 404. `--connect-to` menukar destinasi TCP sahaja; nama TLS/SNI, hostname HTTP dan verifikasi sijil kekal menggunakan hostname staging. Jangan gunakan `-k` atau membuka URL `https://localhost:8443` sebagai pengganti semakan ini.

Semakan ini tidak menguji login/MFA atau callback. Ia hanya mengesahkan Windows dapat membuka laluan SSH ke HTTPS server tanpa membenarkan IP gateway bersama. Jika port 22 tidak dapat dicapai atau forwarding ditolak polisi SSH, laporkan ralat sebelum mengubah firewall atau polisi server.

## Selepas laluan disahkan

Sediakan browser ujian berasingan dengan pemetaan hostname yang mengekalkan origin HTTPS staging, harness callback PKCE pada komputer dan allowlist identiti ujian di adapter. Harness belum tersedia dalam langkah semakan tunnel ini. Kemudian barulah selaraskan epoch, aktifkan observer dan pasang lokasi pilot loopback. Semua endpoint awam kekal dormant sekarang.

Tutup tunnel menggunakan Ctrl+C dalam terminal pertama selepas ujian. Tiada fail hosts, firewall, VPN atau polisi SSH diubah oleh arahan di atas.
