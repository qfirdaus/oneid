# OneID

Single Sign-On (SSO) dan Identity Management System.

## Runtime boundary

Web server mesti menggunakan `public/` sebagai document root. Directory projek
di luar `public/` ialah private application, configuration, documentation,
test, build source atau runtime data dan tidak boleh disajikan oleh web server.

```text
oneid-uat/
├── public/       # satu-satunya document root; entry point dan aset browser
├── app/          # application service/domain yang telah diextract
├── admin/        # implementation admin legacy di belakang public wrapper
├── page/         # implementation user legacy di belakang public wrapper
├── lib/          # library dan integration implementation private
├── bootstrap/    # stable path/bootstrap definition
├── config/       # konfigurasi bukan secret
├── resources/    # resource bukan-public
├── src/          # source SCSS untuk build
├── tests/        # characterization, contract dan support test
├── tools/        # audit/smoke/verification CLI
├── storage/      # cache, log dan quarantine; bukan document root
├── vendors/      # dependency PHP/server-side private
└── docs/         # audit, migration dan runbook
```

Pasangan `public/admin` + `admin`, `public/page` + `page` dan `public/lib` +
`lib` bukan salinan berganda. Directory di bawah `public/` mengandungi thin
compatibility entry point, manakala implementation masih private di project
root. `public/vendors` ialah aset browser; root `vendors` ialah dependency PHP.
Pemindahan implementation mesti dibuat per endpoint bersama characterization
dan rollback, bukan melalui bulk directory move.

## Keperluan

- PHP 8.3 dan PHP-FPM;
- Nginx atau Apache dengan document root `public/`;
- MySQL yang serasi dengan schema OneID;
- secret melalui `.private/runtime.php`, environment atau `ONEID_RUNTIME_FILE`.

Jangan commit `.sql`, credential, upload runtime atau quarantine payload. Fail
`lib/config.php` ialah compatibility bootstrap yang masuk Git; default bukan
secret berada di `config/runtime.php`, nilai server-local berada di
`.private/runtime.php`, dan satu runtime resolver digunakan oleh konfigurasi
serta `lib/secrets.php`. `ONEID_SECRETS_FILE` hanya dikekalkan sebagai alias
legacy dan tidak boleh menunjuk ke fail yang berbeza.

## Verification

```bash
php tools/r54_structure_contract.php
php tools/r54_asset_contract.php
php tools/r54_compatibility_contract.php
php tools/r55_structure_boundary_contract.php
php tools/restructure_smoke.php https://oneid.local --insecure
```

Runbook deployment dan permission terperinci berada di
`docs/R5_5A_FINAL_STRUCTURE_BASELINE_DAN_DEPLOYMENT_RUNBOOK.md`.
