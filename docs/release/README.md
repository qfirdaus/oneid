# Release candidate: OneID web + Android + PHP 8.4.26

Disediakan 3 Oktober 2026 dalam worktree `release/production-php84-mobile`.
**Pakej untuk review; belum diluluskan/diaktifkan pada production.** Checkpoint commit/push dan langkah sambungan dirujuk dalam [RESUME-HERE.md](RESUME-HERE.md). UAT aktif tidak diubah oleh persediaan ini.

## Skop

- Semua perubahan source staging yang belum di-commit: UI web/mobile, dashboard, sesi, MyDigital ID, mobile OIDC/password recovery/MFA serta pembetulan legacy refresh/ACL.
- Sokongan production baharu: root, issuer, origin, client allowlist dan callback dipadankan dengan environment. `PdoIdentitySource` menggunakan environment sebenar untuk polisi MFA. Wrapper staging lama dikekalkan.
- Android sahaja untuk release pertama; iOS tidak didaftarkan. Tiada client production direka atau didaftarkan.
- PHP 8.4.26 untuk pool **OneID web dan mobile** sahaja. CLI, phar, cron dan pool sistem lain kekal 8.3 dahulu.
- Composer lock + patch nullable MyDigital ID dikekalkan supaya `vendor` boleh dibina semula. Vendor, secrets, uploads dan database UAT tidak masuk archive.

## Kandungan

| Bahan | Tujuan |
|---|---|
| `git-manifest.json` di folder artifact | Base commit, branch, status setiap fail dan SHA-256 |
| `oneid-production-candidate.tar` | Source lengkap + `RELEASE-MANIFEST.json`; tiada vendor/secrets |
| `tracked-changes.patch` | Diff tracked terhadap base; fail baharu ada dalam tar/manifest, bukan patch ini |
| `SHA256SUMS` | Checksum archive, manifest, patch dan status |
| `deployment/production/` | Template FPM/Nginx/Hydra/PostgreSQL/Android, semuanya belum dipasang |
| `tools/release/mobile-migration.php` | Plan/check/apply guarded dan disable-observer; bukan installer semua sistem |
| `docs/migrations/20261003_mobile_production_up.sql` | Plan penuh 4 jadual + 28 trigger; observer OFF |
| `RUNBOOK.md`, `ROLLBACK.md`, `INPUTS.md` | Deployment, pemulihan dan perkara belum disahkan |
| `validation.json`, `migration-validation.json`, `syntax-validation.json` | Bukti ujian candidate, bukan ujian production |

## Status production

Read-only Git semasa persediaan: branch `main`, HEAD `06f364d5d2fbd936d8ed496d7e68095aa640cdcb`, working tree bersih. Base ini sama dengan UAT. Semakan drift wajib dibuat semula sebelum deployment; semakan Git sahaja tidak membuktikan runtime/DB masih sama.

Mobile belum wujud pada production; PHP 8.4 belum dipasang. Pakej ini menyediakan source, konfigurasi, migration dan prosedur; **bukan janji deployment satu arahan**. Pemasangan provider/private DB, secrets, pendaftaran Android/MyDigital ID serta pengesahan production masih diperlukan.

## Build / verify tanpa target server

Dari worktree candidate:

```sh
python3 -B tools/release/build-package.py --output /path/di-luar-worktree/release-candidate
python3 -B tools/release/verify-package.py /path/di-luar-worktree/release-candidate
```

Builder tidak mengubah index Git, commit, push atau target server. Ia mengambil semua fail Git serta fail untracked yang tidak diabaikan; fail ignored yang diperlukan mesti didokumenkan/dimasukkan secara sengaja. Archive deterministic bagi snapshot yang sama. Checksum ialah pengesanan perubahan, bukan tandatangan kepercayaan; hantar nilai hash melalui saluran dipercayai.

Perubahan diserahkan melalui branch release berasingan atas arahan pemilik; main dan production kekal. Artifact awal ialah snapshot sebelum commit/checkpoint, maka bina semula pakej final daripada commit yang telah direview sebelum deployment. Rujuk RESUME-HERE.md untuk cara mengenal pasti HEAD checkpoint dan keadaan working tree UAT.
