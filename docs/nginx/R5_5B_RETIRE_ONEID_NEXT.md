# R5.5B — Runbook Penamatan `oneid-next.local`

Tarikh: 14 Julai 2026
Status: `READY_NOT_EXECUTED`

## Keputusan

`oneid-next.local` diwujudkan sebagai parallel public-root untuk R3/R4. Hostname
utama `oneid.local` kini menggunakan `/var/www/app/oneid-uat/public` dan semua
smoke, characterization, login, logout, upload serta SSO pilot telah lulus.

Access log yang tersedia semasa R5.5B mengandungi 1,324 request dari
14 Julai 2026 01:00 hingga 10:54 +0800. Semua source address ialah `127.0.0.1`;
user-agent yang kelihatan ialah browser owner dan tooling verification. Ini
menyokong keputusan bahawa hostname tersebut ialah test-only. Ia bukan bukti
untuk tempoh sebelum log yang tersedia.

Penamatan tidak dilakukan oleh perubahan repository. Ia ialah change Nginx
berasingan oleh change/rollback owner.

## Pre-check

```bash
cd /var/www/app/oneid-uat

php tools/restructure_smoke.php https://oneid.local --insecure
php tools/r52_characterization.php https://oneid.local --insecure

sudo nginx -T | grep -n "server_name oneid-next.local"
```

Jangan teruskan jika `oneid.local` gagal atau terdapat consumer bukan owner pada
access log `oneid-next`.

## Backup

```bash
export ONEID_R55B_CHANGE_ID="R5-5B-NGINX-$(date +%Y%m%d-%H%M%S)"
export ONEID_R55B_BACKUP_DIR="/var/backups/oneid/${ONEID_R55B_CHANGE_ID}"

sudo install -d -o root -g root -m 0700 "$ONEID_R55B_BACKUP_DIR"
sudo cp --preserve=all /etc/nginx/sites-available/local-projects \
  "$ONEID_R55B_BACKUP_DIR/local-projects.before-oneid-next-retirement"
sudo sha256sum \
  "$ONEID_R55B_BACKUP_DIR/local-projects.before-oneid-next-retirement" \
  | sudo tee "$ONEID_R55B_BACKUP_DIR/SHA256SUMS"
```

## Pelaksanaan owner

Edit `/etc/nginx/sites-available/local-projects` dan buang hanya keseluruhan
`server { ... }` yang mempunyai:

```nginx
server_name oneid-next.local;
```

Jangan ubah block `oneid.local` atau server block projek lain. Kemudian:

```bash
sudo nginx -t
sudo systemctl reload nginx

php /var/www/app/oneid-uat/tools/restructure_smoke.php \
  https://oneid.local --insecure
```

Selepas observation, mapping hosts/DNS dan certificate khusus
`oneid-next.local` boleh dibuang dalam change kecil berasingan. Jangan padam key
atau certificate dalam change yang sama dengan vhost retirement kerana ia
mengurangkan pilihan rollback.

## Rollback

```bash
sudo cp --preserve=all \
  "$ONEID_R55B_BACKUP_DIR/local-projects.before-oneid-next-retirement" \
  /etc/nginx/sites-available/local-projects

sudo nginx -t
sudo systemctl reload nginx

php /var/www/app/oneid-uat/tools/restructure_smoke.php \
  https://oneid-next.local
```
