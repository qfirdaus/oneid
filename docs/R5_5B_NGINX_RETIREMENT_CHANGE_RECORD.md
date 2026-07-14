# R5.5B — Nginx Retirement Change Record

Tarikh pelaksanaan: 14 Julai 2026
Status: `PASS`
Change owner: pemilik OneID
Rollback owner: pemilik OneID

## Perubahan

Server block `oneid-next.local` dibuang daripada
`/etc/nginx/sites-available/local-projects`. Server block `oneid.local` kekal
menggunakan document root `/var/www/app/oneid-uat/public` dan
`ONEID_APP_URL=https://oneid.local`.

Tiada perubahan dibuat pada certificate, hosts/DNS mapping, PHP-FPM, database,
secret atau source aplikasi dalam external change ini.

## Bukti penerimaan

| Gate | Keputusan |
| --- | --- |
| Nginx syntax/configuration validation | PASS |
| Nginx reload | PASS |
| `oneid-next.local` server block absent | PASS |
| `oneid.local` server block exactly once | PASS |
| Smoke `oneid.local` | 10/10 PASS |
| Full characterization `oneid.local` | 69/69 PASS |

## Rollback evidence

Output pelaksanaan owner tidak menunjukkan backup baharu bagi edit ini. Sumber
rollback yang diketahui:

1. `/var/backups/oneid/R4-20260714-014057/local-projects.after-r4`, yang pernah
   direkodkan dengan SHA-256
   `7dbe68cef80ff6e1d7cb03bac8daece3717b67588b61d20ecf1fdb6c5d5480e4`;
2. `docs/nginx/oneid-next.local.conf` sebagai template server block;
3. runbook `docs/nginx/R5_5B_RETIRE_ONEID_NEXT.md`.

Certificate dan mapping hostname mesti dikekalkan sehingga observation selesai
supaya rollback tidak memerlukan pengeluaran certificate/DNS baharu.

## Baki kerja

- pantau `oneid.local` error/access log sepanjang observation;
- jangan padam certificate atau hosts/DNS mapping dalam change ini;
- selepas observation, buat disposition certificate dan mapping sebagai change
  kecil berasingan;
- legacy SSO compatibility route masih tertakluk kepada Fasa 6B dan tidak
  berkaitan dengan retirement hostname parallel ini.
