# Inventori Consumer Integrasi OneID — Fasa 6

**Tarikh snapshot:** 13 Julai 2026  
**Status:** Inventori teknikal awal; pengesahan owner masih diperlukan.

## 1. Ringkasan

Snapshot live database merekodkan **34 aplikasi aktif** dalam `sp_list`:

- 8 rekod mempunyai `sp_sso_support=1` dan UI semasa memaparkannya sebagai
  **SSO Not Supported**;
- 26 rekod mempunyai `sp_sso_support=0` dan aliran aplikasi semasa
  menggunakannya sebagai calon **SSO supported**;
- owner, pembekal, PIC teknikal, field data dan tarikh akhir migrasi belum disimpan dalam registry;
- jumlah ini tidak boleh terus dianggap sebagai jumlah consumer protokol SSO sebenar.

Pemilik sistem memaklumkan terdapat hampir 30 sistem yang menggunakan OneID.
Semakan UI dan pilot `DYYOWQGYLE` mengesahkan tafsiran awal flag ini adalah
terbalik: nilai `0` mewakili SSO supported dalam implementasi semasa, manakala
nilai `1` dipaparkan sebagai SSO Not Supported. Walau bagaimanapun, **jangan
disable legacy flow berdasarkan flag ini sahaja** kerana status sebenar setiap
consumer, owner dan endpoint masih perlu disahkan.

## 2. Endpoint Integrasi Yang Ditemui

| Endpoint | Fungsi semasa | Data/risiko utama | Scope baharu | Bukti access log semasa |
|---|---|---|---|---|
| `api.php` | Validasi dan refresh token SSO | Token dan user packet `data1..data12` | `sso:validate` | Ada panggilan dari localhost; consumer luar mungkin muncul sebagai proxy yang sama |
| `idms.php` | Carian staf dan jabatan | Nama, no. pekerja, e-mel, jabatan, jawatan | `idms:read` | Tiada hit dalam snapshot access log semasa |
| `skp_api.php` | Profil dan full sync pelajar | IC, alamat, telefon, keluarga dan data akademik | `skp:profile`, `skp:sync` | Tiada hit dalam snapshot access log semasa |

Access log semasa bukan bukti bahawa endpoint tidak digunakan kerana log boleh dirotate, panggilan boleh melalui reverse proxy, dan aktiviti vendor mungkin berkala. Minimum 30 hari observability diperlukan sebelum penutupan.

## 3. Snapshot Aplikasi Aktif

`Owner/Pembekal`, jenis integrasi sebenar dan data diperlukan perlu dilengkapkan melalui sesi pengesahan. Untuk tindakan inventori di bawah, `0` ditafsir sebagai calon consumer SSO dan `1` sebagai SSO Not Supported berdasarkan UI/kod semasa; status sebenar masih wajib disahkan.

| Bil. | `sp_id` | Nama aplikasi | `sp_sso_support` | Tindakan inventori |
|---:|---|---|---:|---|
| 1 | `8R8QLPLTDN` | Celik Madani (ASNB) | 0 | Triage consumer SSO |
| 2 | `255P8BZEDL` | MyCampus | 1 | Sahkan portal link/API/non-SSO dan owner |
| 3 | `OY169SDFVY` | Portal E-Learning | 1 | Sahkan portal link/API/non-SSO dan owner |
| 4 | `NRINRFSSEN` | Portal Gaji | 1 | Sahkan portal link/API/non-SSO dan owner |
| 5 | `XIBYRMNYYX` | Portal MOOC | 1 | Sahkan portal link/API/non-SSO dan owner |
| 6 | `ROSNJ0FR3D` | Sistem Attendance | 0 | Triage consumer SSO |
| 7 | `PKMUSQM80X` | Sistem Alumni (Pentadbir) | 0 | Triage consumer SSO |
| 8 | `NKBUVTXBWN` | Sistem E-Cover | 0 | Triage consumer SSO |
| 9 | `VXMWPZQR2H` | Sistem E-HRM | 0 | Triage consumer SSO |
| 10 | `LULYRSYM8I` | Sistem E-Kaunseling | 0 | Triage consumer SSO |
| 11 | `VAX0HZO9HX` | Sistem E-Kenderaan | 0 | Triage consumer SSO |
| 12 | `G24JJET3EV` | Sistem E-Keselamatan | 0 | Triage consumer SSO |
| 13 | `Q1V3FJDZ0Q` | Sistem E-LPPT | 0 | Triage consumer SSO |
| 14 | `OOYOUFQQHS` | Sistem E-LPPT (Pentadbir) | 0 | Triage consumer SSO |
| 15 | `VWGNQLQLES` | Sistem E-Mesyuarat | 0 | Triage consumer SSO |
| 16 | `LYFO08UX23` | Sistem E-PMS | 0 | Triage consumer SSO |
| 17 | `0Y4IIXKILT` | Sistem E-PTW | 0 | Triage consumer SSO |
| 18 | `O9NDT81CYJ` | Sistem E-SSPLN | 0 | Triage consumer SSO |
| 19 | `OMNRTOBCXR` | Sistem EP | 0 | Triage consumer SSO |
| 20 | `H22UTX4U7Y` | Sistem IStAD | 0 | Triage consumer SSO |
| 21 | `NQ1B9X7D9D` | Sistem PEKA | 1 | Sahkan portal link/API/non-SSO dan owner |
| 22 | `KQF5YRG3GD` | Sistem PEKA (Pentadbir) | 0 | Triage consumer SSO |
| 23 | `3LOCHMW45E` | Sistem Pendaftaran APEL (Pentadbir) | 0 | Triage consumer SSO |
| 24 | `XNNW6TDOHO` | Sistem Pengurusan Kolej Kediaman (Pentadbir) | 0 | Triage consumer SSO |
| 25 | `ZUGLXDNM49` | Sistem SKP | 1 | Sahkan portal link/API/non-SSO dan owner |
| 26 | `5OFZ2YYNNG` | Sistem SPA | 1 | Sahkan portal link/API/non-SSO dan owner |
| 27 | `5QK1E12NKR` | Sistem Survey | 0 | Triage consumer SSO |
| 28 | `YJRRRWRMX1` | Sistem Zakat | 0 | Triage consumer SSO |
| 29 | `NQLH2V1M3X` | Sistem Zakat (Pentadbir) | 0 | Triage consumer SSO |
| 30 | `PYZF3KU6QH` | Sistem e-BDR (Pentadbir) | 0 | Triage consumer SSO |
| 31 | `XR6OQUXAL7` | Sistem e-Kolej | 0 | Triage consumer SSO |
| 32 | `DYYOWQGYLE` | Sistem e-Prestasi (Pentadbir) | 0 | Pilot SSO gagal pada callback; trace runtime consumer |
| 33 | `C2TGT7QQK4` | Sistem e-Risk | 1 | Sahkan portal link/API/non-SSO dan owner |
| 34 | `XJKYI1VDZY` | Sistem i-MAP | 0 | Triage consumer SSO |

## 4. Medan Yang Perlu Dilengkapkan Bagi Setiap Consumer

| Medan | Contoh nilai |
|---|---|
| Owner bisnes / PIC teknikal | Jabatan, nama, e-mel, telefon |
| Model pembangunan | In-house / vendor / SaaS |
| Kontrak dan vendor contact | Nombor kontrak, tarikh tamat, SLA |
| Integrasi sebenar | Portal link / legacy SSO / API / gabungan |
| Endpoint digunakan | `api.php`, `idms.php`, `skp_api.php` |
| Data minimum | Nama, ID dalaman, e-mel; nyatakan justifikasi setiap field sensitif |
| Source IP | IP UAT dan produksi, jika stabil |
| Callback/redirect URI | URI tepat; wildcard tidak dibenarkan |
| Credential | Client ID unik; secret unik dan tidak dikongsi |
| Migration mode | Legacy / dual / baharu / legacy disabled |
| UAT evidence | Tarikh, tester, keputusan login/logout/error flow |
| Rollback owner | PIC yang boleh membuat keputusan rollback |

## 5. Cadangan Wave

1. **Wave 0 — OneID sendiri:** daftarkan panggilan localhost `api.php` sebagai client dalaman.
2. **Wave 1 — Pilot:** pilih 2–3 sistem in-house, owner aktif dan risiko operasi rendah.
3. **Wave 2 — In-house lain:** kumpulan 3–5 sistem setiap wave.
4. **Wave 3 — Vendor responsif:** beri integration pack dan tempoh UAT formal.
5. **Wave 4 — Vendor terkandas:** kekalkan legacy secara exception bertarikh dengan compensating controls.

Legacy hanya dimatikan **per client** selepas evidence UAT dan rollback contact tersedia.

## 6. Delta Selepas Snapshot — 14 Julai 2026

Consumer `BTOG4WZNQP` (`IQS-Framework`) ditemui aktif selepas snapshot asal:

| `sp_id` | Nama | Domain | Flag | Evidence |
|---|---|---|---:|---|
| `BTOG4WZNQP` | IQS-Framework | `https://iqs-framework.local/index.php` | 0 | Pilot SSO end-to-end melalui `oneid.local` berjaya pada 02:31 |

Rekod ini tidak dimasukkan semula ke jumlah snapshot 34 aplikasi bertarikh 13
Julai. Inventori seterusnya perlu mengambil snapshot baharu dan menjadikan jumlah
serta registry consumer semasa sebagai baseline terkini.
