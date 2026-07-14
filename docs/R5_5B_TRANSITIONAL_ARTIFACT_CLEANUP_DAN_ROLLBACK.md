# R5.5B — Transitional Artifact Cleanup dan Rollback

Tarikh: 14 Julai 2026
Status: `PASS`

## Skop

R5.5B membuat disposition item-by-item untuk artefak kecil selepas public-root
stabil. Ia tidak memindahkan dashboard, API, IDMS, SKP, `q_func`, SSO consumer
route, database atau upload.

Register keputusan berada di
`docs/R5_5B_TRANSITIONAL_ARTIFACT_DISPOSITION.tsv`.

## Perubahan repository

1. Root `.htaccess` dibuang kerana Nginx tidak membacanya dan Apache
   public-root menggunakan `public/.htaccess`.
2. Constant `LEGACY_PUBLIC_PATH` dibuang kerana zero caller; `PROJECT_ROOT`,
   `PUBLIC_PATH`, `STORAGE_PATH` dan override `ONEID_PUBLIC_PATH` dikekalkan.
3. `config/` dan `resources/` dikekalkan sebagai private architecture boundary,
   bukan dipadam hanya kerana kandungannya masih berupa README.
4. Runbook penamatan `oneid-next.local` disediakan tetapi Nginx tidak diubah.
5. Contract baharu mengunci keputusan tersebut.

## OneID Next

Available access log mempunyai 1,324 request dan semuanya dari `127.0.0.1`.
Keputusan ialah `READY_NOT_EXECUTED`: owner boleh menamatkan parallel hostname
menggunakan `docs/nginx/R5_5B_RETIRE_ONEID_NEXT.md` selepas backup Nginx.

## Verification

| Semakan | Keputusan |
| --- | --- |
| PHP lint bootstrap dan contract | PASS |
| Bootstrap constant behavior | PASS; tiga constant aktif kekal dan legacy constant tiada |
| R5.5B transitional contract | 19/19 PASS |
| R5.5A structure-boundary regression | 60/60 PASS |
| R5.4A structure regression | 31/31 PASS |
| R5.4B asset regression | 27/27 PASS |
| R5.4C compatibility regression | 24/24 PASS |
| Smoke `oneid.local` | 10/10 PASS |
| Smoke `oneid-next.local` | 10/10 PASS |
| Full characterization `oneid.local` | 69/69 PASS |
| Full characterization `oneid-next.local` | 69/69 PASS |

R5.5B repository batch ditutup sebagai PASS. Penamatan Nginx
`oneid-next.local` pada mulanya dijadualkan sebagai change owner-operated yang
berasingan dan kemudiannya selesai dengan status `EXECUTED_PASS`.

## Closure penamatan `oneid-next.local`

Owner membuang server block parallel pada 14 Julai 2026. `nginx -t` dan reload
berjaya, `nginx -T` tidak lagi memaparkan hostname tersebut, manakala
`oneid.local` lulus smoke 10/10 dan characterization 69/69. Semakan read-only
selepas pelaksanaan mengesahkan hanya satu block `oneid.local` kekal dan tiada
block `oneid-next.local`.

Certificate serta hosts/DNS mapping belum dibuang. Output owner tidak
menunjukkan backup pra-retirement baharu; rollback masih boleh menggunakan
backup R4 yang telah direkodkan atau template vhost dalam repository.

```bash
php -l bootstrap/paths.php
php -l tools/r55_transitional_contract.php
php tools/r55_transitional_contract.php
php tools/r55_structure_boundary_contract.php
php tools/r54_structure_contract.php
php tools/r54_asset_contract.php
php tools/r54_compatibility_contract.php
php tools/restructure_smoke.php https://oneid.local --insecure
php tools/restructure_smoke.php https://oneid-next.local
```

## Rollback

Sebelum commit:

```bash
git restore .htaccess bootstrap/paths.php package.json
```

Selepas commit:

```bash
git revert <R5.5B-commit-hash>
```

Rollback Nginx, jika owner telah menjalankan retirement berasingan, mesti
menggunakan backup dalam runbook Nginx dan bukan melalui Git revert.
