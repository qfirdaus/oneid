# ML1 Controlled UAT Migration and Pilot Gate

**Status sejarah dokumen ini:** PREPARED / DORMANT sebelum controlled Apply
**Status semasa:** LOCAL MIGRATION PASS; ML1 INFRASTRUCTURE ACTIVE ON LOCAL WSL
**Environment:** UAT
**Implementation reference:** `ONEID-ML1-20260725-01`

> Boundary dan runtime example di bawah ialah rekod pre-activation. Controlled
> Local migration kemudiannya diluluskan melalui
> `ONEID-ML1-UAT-20260725-01`, disahkan dalam change window 25 Julai 2026 dan
> diikuti closure ML2. Ia tidak memberi authorization kepada staging atau
> Production.

## Safe preview

```bash
php tools/ml1_uat_migration_gate.php --preview
```

Preview hanya membaca status table, jumlah preference dan feature state.

## Private runtime keys

```php
'ONEID_LOCALE_INFRASTRUCTURE_ENABLED' => 'false',
'ONEID_ML1_SCHEMA_APPLY_ENABLED' => 'false',
'ONEID_ML1_CHANGE_REFERENCE' => '',
'ONEID_ML1_BACKUP_REFERENCE' => '',
'ONEID_ML1_WINDOW_START' => '',
'ONEID_ML1_WINDOW_END' => '',
'ONEID_ML1_EXPECTED_EXISTING_PREFERENCES' => '0',
```

Jangan aktifkan atau isi authorization values sebelum approval exact-plan.
Apply memerlukan environment UAT, runtime opt-in tepat, change/backup reference,
change window aktif, expected row reconciliation dan environment-only canonical
confirmation `APPLY ML1 LOCALE SCHEMA`.

Rollback memerlukan infrastructure disabled, preference rows `0` dan canonical
confirmation `ROLLBACK ML1 LOCALE SCHEMA`.

## Authorization yang diperlukan pada checkpoint sejarah

Keperluan berikut telah dipenuhi untuk Local WSL melalui authorization dan
evidence selepas dokumen ini disediakan. Ia dikekalkan untuk audit trail, bukan
sebagai blocker semasa.

Cadangan Pilot kekal:

- Login, Password Recovery dan OTP e-mel sahaja;
- dua pengguna BM, dua pengguna English dan seorang Administrator;
- minimum tiga hari bekerja;
- legacy `msg` dikekalkan;
- exact confirmation serta identifier teknikal kekal canonical; dan
- Production tidak dibenarkan.
