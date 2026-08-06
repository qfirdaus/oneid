# OneID v2.7.3 — Conditional External Sync Cron

**Versi:** 2.7.3

**Tarikh release:** 5 Ogos 2026

**Environment sasaran:** Staging/UAT

**Status:** IMPLEMENTED / STAGING ACTIVATED

## Ringkasan

Release ini menyediakan cronjob conditional bagi External Sync Staff, UG dan
ODL. Scheduler memeriksa setiap source secara berasingan dan hanya memanggil
safe writer apabila terdapat perubahan yang selamat serta berada dalam
threshold source. Manual Preview dan Apply melalui skrin Administrator kekal
tersedia dan menggunakan shared database advisory lock yang sama.

## Behavior Operasi

- `SKIP_NO_CHANGES` menghasilkan zero transaction, header dan mutation;
- secara default, `New`, `Update` dan `Reactivate` dalam threshold boleh diproses automatik dan `Deactivate` disekat;
- staging boleh menetapkan `ONEID_SYNC_CRON_ALLOW_ALL_SAFE_CHANGES=true` untuk memproses semua volume serta `Deactivate` tanpa threshold/warning gate;
- collision, anomaly integriti, source failure, plan drift dan reconciliation failure kekal fail-closed;
- Staff, UG dan ODL mempunyai source scope, provenance/membership serta
  threshold masing-masing;
- fresh second snapshot mesti sepadan dengan exact plan approval;
- transaction mesti lulus exact planned/executed/audited reconciliation;
- secondary audit failure dilaporkan sebagai `APPLIED_AUDIT_WARNING` dan tidak
  menyebabkan writer diulang; dan
- CLI output tidak mengandungi PII atau credential.

## Deployment Staging

Staging menggunakan service crontab account `iqs` pada `23:00`
`Asia/Kuala_Lumpur` dengan filesystem `flock`. Private runtime mengawal source,
threshold, dry-run dan emergency stop; nilai private deployment tidak disimpan
dalam Git.

Command deployment:

```cron
15 * * * * /usr/bin/flock -n /var/www/oneid-uat/.private/locks/external-sync.lock /usr/bin/php /var/www/oneid-uat/cron/run_conditional_external_sync.php >> /var/www/oneid-uat/storage/logs/external-sync-cron.log 2>&1
```

Emergency stop:

```php
'ONEID_SYNC_CRON_ENABLED' => 'false',
```

## Validasi

```bash
php tools/s4h_conditional_sync_cron_contract.php
php tools/s3_sync_safety_contract.php
php tools/s4g_operational_sync_contract.php
php tools/source_scoped_sync_apply_contract.php
php tools/odl_f9_manual_operational_contract.php
php tools/release_metadata_contract.php
php tools/version_documentation_contract.php
```

Contract cron membuktikan defaults committed kekal disabled/dry-run,
zero-change berhenti sebelum writer, Deactivate disekat, approval one-use,
manual endpoint kekal tersedia dan audit outcome source-specific diwajibkan.
