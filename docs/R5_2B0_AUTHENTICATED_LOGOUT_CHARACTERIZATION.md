# R5.2B0 — Authenticated Logout Characterization

Tarikh: 14 Julai 2026

Change ID: `R5-2B0-20260714-031141`

Owner perubahan: Pemilik sistem OneID

Owner rollback: Pemilik sistem OneID

Status: **DITUTUP DENGAN PENGECUALIAN NORMAL-USER DITERIMA OWNER**

## 1. Skop

R5.2B0 menyediakan regression contract bagi login, role authorization, logout
dan session invalidation sebelum logik logout diextract ke `app/`.

Tiada perubahan dibuat kepada:

- `page/logout.php` atau `admin/logout.php`;
- public wrapper;
- bootstrap/config runtime;
- database schema;
- Nginx atau URL awam.

## 2. Fail R5.2B0

- `tests/characterization/r52_authenticated_logout_contracts.php`;
- `tools/r52_authenticated_logout.php`;
- kemas kini `tests/README.md`;
- dokumen ini.

## 3. Contract Yang Diuji

Setiap kombinasi host dan role menjalankan 14 checks:

1. anonymous landing page `200`;
2. CSRF token diterbitkan;
3. dashboard anonymous ditolak ke root;
4. anonymous PHP session tersedia;
5. login berjaya dengan JSON body contract;
6. login redirect URI kekal `page/dashboard`;
7. session ID berputar selepas authentication;
8. SSO cookie diterbitkan;
9. dashboard user boleh dicapai;
10. admin authorization ialah `403` untuk user dan `200` untuk admin;
11. logout menghasilkan `302` tepat ke root host semasa;
12. SSO cookie dibuang;
13. cookie jar selepas logout tidak boleh membuka dashboard;
14. salinan cookie session sebelum logout turut gagal apabila direplay.

Check terakhir membuktikan server-side session telah dimusnahkan, bukan sekadar
browser diarahkan semula atau cookie dipadam.

Baseline semasa menunjukkan endpoint `q_func` menghantar JSON body dengan
content type legacy `text/html`. Runner merekod content type tetapi menentukan
kejayaan melalui HTTP status, JSON yang sah dan `login_status`. Pembetulan
content type ialah change berasingan dan tidak digabungkan dengan extraction
logout.

## 4. Kawalan Credential dan Data Sensitif

Credential hanya dibaca daripada environment:

| Role | Username | Password |
|---|---|---|
| User | `ONEID_R52_USER_USERNAME` | `ONEID_R52_USER_PASSWORD` |
| Admin | `ONEID_R52_ADMIN_USERNAME` | `ONEID_R52_ADMIN_PASSWORD` |

Runner:

- tidak menerima password melalui command-line argument;
- tidak mencetak username, password, CSRF token, session ID atau SSO token;
- menggunakan cookie jar sementara berpermission `0600`;
- memadam cookie jar melalui shutdown handler;
- tidak menyimpan response body ke docs atau repository.

Gunakan akaun ujian khusus. Login mencipta token OneID dan logout membatalkannya.
Jika `multisession` dimatikan, login ujian mungkin membatalkan session lain bagi
akaun sama.

## 5. Cara Menetapkan Credential Tanpa Shell History

```bash
read -rp "OneID test user: " ONEID_R52_USER_USERNAME
read -rsp "OneID test user password: " ONEID_R52_USER_PASSWORD; echo
read -rp "OneID test admin: " ONEID_R52_ADMIN_USERNAME
read -rsp "OneID test admin password: " ONEID_R52_ADMIN_PASSWORD; echo

export ONEID_R52_USER_USERNAME ONEID_R52_USER_PASSWORD
export ONEID_R52_ADMIN_USERNAME ONEID_R52_ADMIN_PASSWORD
```

Jalankan matrix:

```bash
php tools/r52_authenticated_logout.php https://oneid.local user --insecure
php tools/r52_authenticated_logout.php https://oneid.local admin --insecure
php tools/r52_authenticated_logout.php https://oneid-next.local user
php tools/r52_authenticated_logout.php https://oneid-next.local admin
```

Kemudian buang credential daripada shell:

```bash
unset ONEID_R52_USER_USERNAME ONEID_R52_USER_PASSWORD
unset ONEID_R52_ADMIN_USERNAME ONEID_R52_ADMIN_PASSWORD
```

## 6. Acceptance Gate

- [x] Runner dan contract tersedia.
- [x] Credential tidak berada dalam source atau argument CLI.
- [x] Runner fail-closed apabila environment tiada.
- [x] PHP lint lulus.
- [x] Cubaan pertama dikenal pasti sebagai `authentication_rejected`, bukan
      routing, CSRF atau server error.
- [ ] User biasa `oneid.local` lulus 14/14.
- [x] Admin `oneid.local` lulus 14/14.
- [ ] User biasa `oneid-next.local` lulus 14/14.
- [x] Admin `oneid-next.local` lulus 14/14.
- [x] Tiada PHP fatal/warning atau 5xx baharu dalam log.
- [x] Owner menerima pengecualian normal-user dan mengarahkan continuation ke
      R5.2B1 pada 03:30 +0800.

Pengecualian role telah diterima dan direkodkan. Gate R5.2B1 dibuka dengan
risiko baki bahawa flow akaun user biasa belum dijalankan secara langsung.

## 7. Rollback

R5.2B0 tidak mengubah runtime, maka rollback hanya membuang tooling dan
dokumentasi batch ini. Jangan buang `tests/` atau `tools/` secara keseluruhan.

Selepas execution, logout sepatutnya telah membatalkan token yang dicipta. Jika
run berhenti selepas login tetapi sebelum logout, login ke akaun ujian dan
revoke session tersebut melalui fungsi session management OneID sebelum
mengulang test.

## 8. Keputusan Semasa

Tooling R5.2B0 siap dan static validation lulus. Empat cubaan awal pada 14 Julai
2026 menerima HTTP 200 dengan JSON penolakan authentication; diagnosis
mengesahkan tiada 5xx, CSRF failure atau routing failure. Credential kemudian
dimasukkan semula tanpa disimpan dalam chat atau repository.

Cubaan kedua pada 03:21 +0800 berjaya login menggunakan akaun yang disediakan.
Matrix sebenar:

| Host | Label run | Checks | Gagal | Tafsiran |
|---|---|---:|---:|---|
| `oneid.local` | user | 14 | 1 | Akaun boleh akses admin dashboard; ia bukan user biasa |
| `oneid.local` | admin | 14 | 0 | PASS |
| `oneid-next.local` | user | 14 | 1 | Akaun boleh akses admin dashboard; ia bukan user biasa |
| `oneid-next.local` | admin | 14 | 0 | PASS |

Kedua-dua endpoint logout telah diuji pada kedua-dua hostname. Session ID
rotation, SSO cookie clearing, penolakan cookie selepas logout dan penolakan
replay session pra-logout semuanya lulus. Dua kegagalan bukan logout regression;
ia membuktikan akaun sama berperanan admin sedangkan run `user` menjangkakan
HTTP `403` daripada admin dashboard.

Access log mengesahkan urutan `200` login/dashboard, `302` logout dan `302`
session replay. Error log tidak menunjukkan 5xx, PHP fatal atau warning baharu
dalam window ujian. Mesej audit integration `missing_credentials` yang ditulis
ke stderr bukan kegagalan R5.2B0 dan response berkaitan kekal bukan 5xx.

## 9. Checksum Tooling

| Fail | SHA-256 |
|---|---|
| `tools/r52_authenticated_logout.php` | `8a168eb8811f208b607e62c49bd867239e3f59752a5954ac8ac754bc38d26880` |
| `tests/characterization/r52_authenticated_logout_contracts.php` | `90fbf64cd13d283eaf3aecc4e7d0f4fa150941b73ce6603554dc657aa23c7076` |
| `tests/README.md` selepas R5.2B0 | `e544af1a78699b7764dc8d6d2c97c5e624c7f0f84e70a9d84e452af303db515b` |
