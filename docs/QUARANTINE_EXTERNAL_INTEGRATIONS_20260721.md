# Quarantine external integrations — 21 July 2026

## Scope

The IDMS and SKP endpoints and the legacy SKP helper are disabled while their
usage and consumer ownership are being confirmed.

| Original path | Quarantine path |
|---|---|
| `idms.php` | `storage/quarantine/EXTERNAL-INTEGRATIONS-20260721/payload/idms.php` |
| `skp_api.php` | `storage/quarantine/EXTERNAL-INTEGRATIONS-20260721/payload/skp_api.php` |
| `lib/skp_api.php` | `storage/quarantine/EXTERNAL-INTEGRATIONS-20260721/payload/lib/skp_api.php` |
| `public/idms.php` | `storage/quarantine/EXTERNAL-INTEGRATIONS-20260721/payload/public/idms.php` |
| `public/skp_api.php` | `storage/quarantine/EXTERNAL-INTEGRATIONS-20260721/payload/public/skp_api.php` |

## Runtime effect

- `/idms.php` and `/skp_api.php` are no longer exposed by this application.
- The active student external sync remains implemented by
  `lib/external_data_source_API.php`; it does not require `lib/skp_api.php`.
- Historical documentation and characterization tools may still mention the
  quarantined paths and should not be interpreted as active runtime callers.

## Restore

After consumer ownership and authorization are confirmed, restore each file to
its original path from the quarantine payload and rerun the integration and
structure contracts before enabling traffic.
