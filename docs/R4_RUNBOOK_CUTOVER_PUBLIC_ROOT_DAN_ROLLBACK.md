# R4 Runbook — Cutover `oneid.local` ke Public Root

**Tarikh disediakan:** 14 Julai 2026  
**Status:** CLOSED — cutover dan business validation lengkap  
**Perubahan tunggal:** Tukar Nginx document root `oneid.local` ke `/var/www/app/oneid-uat/public`  
**Tiada dalam R4:** Tiada delete, quarantine, dependency upgrade, refactor atau perubahan database.

> **Precondition dipulihkan:** Server block legacy `oneid.local` pernah hilang semasa penyediaan runbook tetapi telah dipulihkan. Verifikasi akhir menunjukkan `oneid.local` dan `oneid-next.local` masing-masing lulus smoke 10/10. Template emergency restore dikekalkan di `docs/nginx/oneid-legacy-restore.server-block.conf`.

**Change selesai:** `R4-20260714-014057` bermula pada 14 Julai 2026, 01:40:57 +0800 dan ditutup pada 02:34 +0800. Semua gate lulus termasuk SSO control pilot IQS-Framework. Defect e-Prestasi diasingkan sebagai isu consumer dan tidak mencetuskan rollback R4. Rujuk `R4_CHANGE_RECORD_20260714_014057.md`.

## 1. Objektif dan Topologi

| Host | Sebelum R4 | Selepas R4 |
|---|---|---|
| `oneid.local` | `/var/www/app/oneid-uat` | `/var/www/app/oneid-uat/public` |
| `oneid-next.local` | `/var/www/app/oneid-uat/public` | Kekal sementara untuk diagnosis/soak |

Certificate `oneid.local` dan DNS tidak berubah. Kod implementasi lama kekal di tempat asal dan dipanggil melalui wrapper public, membolehkan rollback Nginx tanpa memulihkan source.

## 2. Autoriti dan Go/No-Go

Rujuk `R4_GATE_ACCEPTANCE_REGISTER.md`. R4 hanya boleh dimulakan apabila:

- `oneid.local` legacy baseline telah dipulihkan dan smoke kembali 10/10;
- semua gate `BLOCKED` diselesaikan atau diterima secara bertulis oleh owner berkuasa;
- admin logout disahkan; **selesai**;
- change owner, rollback owner dan window direkodkan;
- backup konfigurasi Nginx tersedia dan checksum direkodkan;
- tiada change lain berjalan serentak.

Jika mana-mana medan keputusan akhir masih kosong, keputusan ialah **NO-GO**.

## 3. Persediaan Change Window

Tetapkan nilai sebenar:

```bash
export ONEID_CHANGE_ID="R4-YYYYMMDD-HHMM"
export ONEID_PROJECT="/var/www/app/oneid-uat"
export ONEID_NGINX_CONFIG="/etc/nginx/sites-available/local-projects"
export ONEID_BACKUP_DIR="/var/backups/oneid/${ONEID_CHANGE_ID}"
```

Simpan konfigurasi dan checksum sebelum edit:

```bash
sudo install -d -o root -g root -m 0700 "$ONEID_BACKUP_DIR"
sudo cp --preserve=all "$ONEID_NGINX_CONFIG" "$ONEID_BACKUP_DIR/local-projects.before-r4"
sudo sha256sum "$ONEID_BACKUP_DIR/local-projects.before-r4" | sudo tee "$ONEID_BACKUP_DIR/SHA256SUMS"
sudo nginx -T > "/tmp/${ONEID_CHANGE_ID}.nginx-before.txt"
```

Rekod permission penting tanpa mengubahnya:

```bash
namei -l "$ONEID_PROJECT/public/index.php"
namei -l "$ONEID_PROJECT/public/public_img"
ls -ld "$ONEID_PROJECT/storage" "$ONEID_PROJECT/storage/logs"
```

## 4. Pre-Cutover Baseline

Jalankan smoke pada kedua-dua host:

```bash
cd /var/www/app/oneid-uat
php tools/restructure_smoke.php https://oneid.local --insecure
php tools/restructure_smoke.php https://oneid-next.local
```

Expected bagi setiap host: `RESULT checks=10 failed=0`.

Simpan checksum aset contoh:

```bash
curl --silent --show-error https://oneid.local/assetsM/css/custom.css | sha256sum
curl --silent --show-error https://oneid-next.local/assetsM/css/custom.css | sha256sum
```

Kedua-dua hash mesti sama.

## 5. Perubahan Nginx

Edit fail yang sudah aktif:

```bash
sudoedit /etc/nginx/sites-available/local-projects
```

Dalam fail tersebut, gantikan **hanya** server block `server_name oneid.local` dengan kandungan `docs/nginx/oneid-r4.server-block.conf`.

Jangan:

- ubah block projek lain;
- buang block `oneid-next.local`;
- salin template sebagai block tambahan ke `conf.d`;
- ubah certificate atau DNS;
- pindahkan/padam source lama.

Validate sebelum reload:

```bash
sudo nginx -t
```

Jika gagal, jangan reload. Pulihkan fail backup mengikut Seksyen 9.

Jika lulus:

```bash
sudo systemctl reload nginx
```

Reload digunakan supaya connection projek lain tidak diputuskan.

## 6. Verifikasi Automatik Selepas Cutover

```bash
cd /var/www/app/oneid-uat
php tools/restructure_smoke.php https://oneid.local --insecure
```

Expected: `RESULT checks=10 failed=0`.

Boundary test:

```bash
for path in \
  README.md package.json sso_db.sql test.php atest.php \
  lib/config.php lib/secrets.php cron/run_sync.php \
  docs/AUDIT_PROJEK_ONEID_UAT_2026-07-13.md \
  bootstrap/paths.php storage/logs tools/restructure_smoke.php diag/agent.php
do
  curl --silent --show-error --output /dev/null \
    --write-out "%{http_code}  /$path\n" \
    "https://oneid.local/$path"
done
```

Expected: semua 13 path menghasilkan `404`.

Extensionless contract:

```bash
curl --silent --output /dev/null --write-out "%{http_code}\n" https://oneid.local/page/dashboard
curl --silent --output /dev/null --write-out "%{http_code}\n" https://oneid.local/admin/dashboard
curl --silent --output /dev/null --write-out "%{http_code}\n" https://oneid.local/lib/q_func
```

Expected tanpa session/request body: `302`, `302`, `405`.

Legacy SSO compatibility contract:

```bash
curl --silent --output /dev/null --write-out "%{http_code}\n" \
  https://oneid.local/lib/sso_IDP_index.php
curl --silent --output /dev/null --write-out "%{http_code} %{redirect_url}\n" \
  https://oneid.local/lib/sso_IDP_sub.php
```

Expected tanpa cookie: `200`, kemudian `302 https://oneid.local/`. Pastikan event `legacy_compat_route` muncul dalam log tanpa token/query string.

## 7. Business Smoke Selepas Cutover

Gunakan browser private session pada `https://oneid.local`:

- [ ] User login, dashboard, semua icon dan navigasi.
- [ ] User logout dan session tidak boleh digunakan semula.
- [ ] Admin login, role guard dan logout.
- [ ] OTP/reset password menggunakan mailbox UAT.
- [ ] Upload satu icon fixture; rekod filename dan bersihkan melalui flow aplikasi jika dibenarkan.
- [ ] API/IDMS/SKP live credential dan negative contract.
- [ ] SSO pilot login/return/logout.
- [ ] Consumer legacy yang diluluskan dalam acceptance register.
- [ ] Tiada 404 aset atau console error baharu.

Jangan jalankan reset/OTP, upload atau sync berulang kali jika satu bukti lulus sudah mencukupi.

## 8. Pemerhatian Selepas Cutover

Pantau sekurang-kurangnya 30 minit dalam UAT:

```bash
sudo tail -F \
  /var/log/nginx/oneid-r4.access.log \
  /var/log/nginx/oneid-r4.error.log
```

Perhatikan:

- HTTP 5xx;
- redirect loop;
- PHP fatal/warning baharu;
- asset 404;
- kegagalan session/cookie;
- SSO callback atau integration error;
- request sah ke endpoint yang kini 404.

Structured audit message yang sengaja dihantar melalui PHP stderr perlu dibezakan daripada fatal error.

## 9. Rollback

### Trigger segera

Rollback jika berlaku mana-mana berikut dan tidak boleh dibetulkan dalam window:

- login user/admin gagal;
- redirect loop atau session invalidation rosak;
- OTP/reset password gagal secara sistemik;
- API/IDMS/SKP atau SSO consumer sah gagal;
- icon/aset utama hilang;
- peningkatan HTTP 5xx/PHP fatal;
- monitor atau endpoint legacy sah terputus tanpa replacement.

### Arahan rollback

Kaedah utama ialah memulihkan full backup pre-cutover. Bagi change `R4-20260714-014057`, backup tersebut tidak wujud dan deviation telah direkodkan. Gunakan prosedur mitigasi berikut:

1. buka `/etc/nginx/sites-available/local-projects` menggunakan `sudoedit`;
2. gantikan hanya block `server_name oneid.local` dengan `docs/nginx/oneid-legacy-restore.server-block.conf`;
3. kekalkan block `oneid-next.local` dan semua projek lain;
4. jalankan `sudo nginx -t`, reload dan smoke `oneid.local`.

Arahan full-backup di bawah hanya terpakai jika `local-projects.before-r4` benar-benar wujud:

```bash
sudo cp --preserve=all \
  "$ONEID_BACKUP_DIR/local-projects.before-r4" \
  "$ONEID_NGINX_CONFIG"

sudo nginx -t
sudo systemctl reload nginx
```

Selepas rollback:

```bash
cd /var/www/app/oneid-uat
php tools/restructure_smoke.php https://oneid.local --insecure
```

Pastikan root kembali kepada:

```nginx
root /var/www/app/oneid-uat;
```

Jangan rollback hardening Fasa 1–6, password hash, secret rotation, cookie security atau authorization control. R4 rollback hanya memulihkan konfigurasi Nginx sebelum cutover.

## 10. Rekod Selepas Change

```text
Change ID:
Start/end time:
Pelaksana:
Rollback owner:
Pre-cutover smoke:
nginx -t:
Post-cutover smoke:
Boundary 13/13:
Business smoke:
Log observation:
Cron/monitoring/legacy decision:
Rollback diperlukan: Ya / Tidak
Keputusan: Berjaya / Rollback / Pemerhatian dilanjutkan
```

Selepas soak yang dipersetujui, `oneid-next.local` boleh dinyahaktif dalam change berasingan. R5 file movement/cleanup hanya bermula selepas R4 stabil; jangan gabungkan dengan cutover ini.
