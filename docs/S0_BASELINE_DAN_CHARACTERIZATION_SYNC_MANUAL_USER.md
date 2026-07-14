# S0 — Baseline dan Characterization Sync/Manual User

Tarikh: 14 Julai 2026  
Owner perubahan: Pemilik sistem OneID  
Owner rollback: Pemilik sistem OneID  
Status: **SELESAI — READ-ONLY CHARACTERIZATION, TIADA LIVE MUTATION**

## 1. Objektif dan Batas

S0 menetapkan baseline bagi `Sync with external data` dan `Manual Add User`
sebelum sebarang hardening functional. S0 tidak:

- menekan action production;
- menjalankan live external source;
- membaca atau menulis live database;
- mencipta, mengemas kini atau menyahaktif pengguna;
- mengubah schema, runtime controller atau production wiring.

## 2. Peta Flow Semasa

### External sync

```text
admin/dashboard.php
  → pick_add_sync_user()
  → POST admin_add_sync_user
  → lib/q_func.php
  → run_admin_sync_user()
  → external snapshot + Database mutation/audit
```

### Manual add

```text
admin/dashboard.php
  → form_add_new_user_manual
  → POST action_add_new_user_manual_check_user_id
  → lib/q_func.php
  → duplicate lookup
  → Database::action_add_new_user()
  → password-change flag + audit event 23
```

Kedua-dua action dilindungi oleh POST-only, exactly-one-action, CSRF,
authenticated session dan admin role melalui `oneid_guard_q_func_request()`.

## 3. Baseline Positif

- Dashboard dan kedua-dua action mempunyai admin authorization.
- Dashboard menghantar `X-CSRF-Token`.
- Manual insert menggunakan prepared statement.
- Manual initial password menggunakan 32 random bytes, di-hash dan tidak
  didedahkan kepada pentadbir.
- `password_change_required=1` dikekalkan.
- Full sync mempunyai audit summary untuk NEW/UPDATE/DEACTIVATE/REACTIVATE.
- Empty external source tidak menjalankan deactivation dan diberi status 3.
- Fixture legacy in-memory merangkumi commit, rollback dan empat action sync.
- Pure `SyncPlanner`/dry-run dormant telah membuktikan zero mutation.

## 4. Baseline Risiko

Register mesin-baca penuh berada dalam
`docs/S0_SYNC_MANUAL_USER_BASELINE_REGISTER.tsv`.

Risiko utama:

1. Full sync terus mutating selepas satu klik tanpa preview/confirmation.
2. Semua akaun aktif dibaca tanpa `account_source` atau `sync_protected`.
3. Akaun manual yang tiada dalam external snapshot boleh dinyahaktif.
4. Tiada lock server-side atau partial-source threshold.
5. External fetch berlaku selepas transaction bermula tetapi di luar rollback
   `try/catch`.
6. Manual add hanya bergantung pada validation browser.
7. Email onboarding masih optional.
8. Manual insert, password flag dan audit tidak berada dalam satu transaction.
9. Manual AJAX error callback kosong.
10. Manual change hash hanya menggunakan nama.

## 5. Characterization Tool

Arahan:

```bash
php tools/s0_user_provisioning_characterization.php
npm run check:user-provisioning
```

Tool hanya membaca source, menjalankan PHP lint dan memanggil dua fixture
in-memory sedia ada:

- legacy sync orchestration: 18/18;
- dormant pure dry-run zero mutation: 25/25.

Ia tidak memuatkan `q_func.php`, tidak membuka session/database/network dan tidak
menulis runtime storage.

## 6. Keputusan Verification

| Semakan | Keputusan |
| --- | --- |
| Aggregate S0 characterization | 50/50 PASS |
| Runtime PHP lint | PASS |
| Authorization/CSRF/action-map contract | PASS |
| External-sync UI/controller/persistence baseline | PASS |
| Manual-add UI/controller/persistence baseline | PASS |
| Cross-flow manual-account deactivation exposure | PASS, risiko disahkan |
| Legacy in-memory sync regression | 18/18 PASS |
| Dormant dry-run zero-mutation regression | 25/25 PASS |
| Runtime files berubah oleh S0 | Tiada |

Arahan PHP terus telah dijalankan dan lulus. Alias npm telah direkod untuk
environment build/developer, tetapi host audit ini tidak memasang executable
`npm`; kesahan `package.json` telah disemak menggunakan JSON parser PHP.

## 7. Polisi untuk Fasa S1

Baseline S0 menetapkan cadangan polisi berikut untuk implementation S1:

- setiap akaun mempunyai provenance `manual` atau `external`;
- akaun manual default kepada sync-protected;
- auto-deactivation hanya dibenarkan bagi akaun external yang terdapat dalam
  authoritative scope snapshot;
- perubahan protection mesti explicit, admin-only dan diaudit;
- migration schema/data mesti mempunyai backup dan rollback tersendiri.

Ini ialah keputusan design S0, bukan behavior production yang telah aktif.

## 8. Rollback

Tiada runtime rollback diperlukan. Untuk membuang artefak S0 sebelum commit:

```bash
git restore package.json
git clean -f docs/PELAN_BERFASA_SYNC_DAN_MANUAL_USER_S0_S4.md
git clean -f docs/S0_BASELINE_DAN_CHARACTERIZATION_SYNC_MANUAL_USER.md
git clean -f docs/S0_SYNC_MANUAL_USER_BASELINE_REGISTER.tsv
git clean -f tests/characterization/s0_user_provisioning_contracts.php
git clean -f tools/s0_user_provisioning_characterization.php
```

Selepas commit, gunakan `git revert <commit-S0>`.

## 9. Keputusan

S0 selesai apabila characterization tool lulus dan checksum runtime sama dengan
baseline. Production behavior kekal unchanged. Langkah seterusnya ialah **S1 —
Manual Add User hardening dan provenance**, selepas checkpoint Git serta review
owner.
