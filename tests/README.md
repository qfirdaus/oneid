# Tests

Characterization dan regression test untuk restructuring OneID.

Jalankan baseline R5.2A:

```bash
php tools/r52_characterization.php https://oneid.local --insecure
php tools/r52_characterization.php https://oneid-next.local
```

Test ini read-only dan tidak menggunakan credential, session pengguna atau
request body sensitif.

R5.2B0 authenticated logout characterization menggunakan akaun ujian melalui
environment sahaja:

```bash
php tools/r52_authenticated_logout.php https://oneid.local user --insecure
php tools/r52_authenticated_logout.php https://oneid.local admin --insecure
php tools/r52_authenticated_logout.php https://oneid-next.local user
php tools/r52_authenticated_logout.php https://oneid-next.local admin
```

Runner ini mencipta dan membatalkan token login sebagai sebahagian daripada
logout. Jangan gunakan akaun manusia aktif atau akaun yang sedang digunakan
dalam browser lain jika konfigurasi OneID hanya membenarkan satu session.

R5.2C0 pure-helper characterization bersifat local dan read-only:

```bash
php tools/r52_pure_helpers.php
```

Ia tidak memerlukan credential, hostname, session, database atau integration.
Selepas R5.2C1, runner yang sama turut menguji parity antara compatibility
function `sync_*` dan `app/Sync/SyncDataTransformer.php`.

R5.2D0 sync orchestration fixture dan dashboard static characterization:

```bash
php tests/characterization/r52_sync_orchestration.php
php tools/r52_dashboard_characterization.php
```

Kedua-duanya tidak menggunakan live database atau external source. Sync fixture
menggunakan fake operation dalam memory; dashboard runner hanya membaca source.

R5.2D1 interface/DTO seam design:

```bash
php tools/r52_sync_seam_design.php
```

Runner ini turut memastikan production sync runner, `q_func`, cron dan dashboard
belum di-wire kepada contract design.

R5.2D2 test-only adapter parity:

```bash
php tests/characterization/r52_sync_adapter_parity.php
```

Fixture ini menggunakan callable dan operation spy dalam memory. Ia menguji
exact mapping method/argumen legacy serta checksum runtime tanpa database,
network, session atau production sync.

R5.2D3 legacy versus test-only orchestrator parity:

```bash
php tests/characterization/r52_sync_orchestrator_parity.php
```

Fixture menjalankan kedua-dua orchestration menggunakan input dan fake operation
berasingan, kemudian membandingkan call trace, failure behavior dan result.
Initial password hash rawak sahaja dinormalisasi selepas kedua-dua path disahkan
membekalkan hash tidak kosong.

R5.2D5 pure plan dan zero-mutation dry-run:

```bash
php tests/characterization/r52_sync_dry_run_zero_mutation.php
```

Fixture menggunakan persistence spy yang melempar exception pada setiap mutation
method. Run yang lulus hanya dibenarkan membuat external fetch serta dua bacaan
user state dalam memory.

R5.2D6 production-grade pure planner extraction:

```bash
php tests/characterization/r52_sync_planner_purity.php
```

Purity guard mengesahkan `app/Sync/SyncPlanner.php` hanya bergantung pada policy,
transformer dan plan DTO serta belum dirujuk oleh production caller.

R5.2D7 dormant production adapter contracts:

```bash
php tests/characterization/r52_sync_production_adapter_contracts.php
```

Fixture menguji external, password, policy dan Database persistence bridge
menggunakan global fixture/operation spy sahaja tanpa live I/O.

R5.2D8 dormant production orchestrator parity:

```bash
php tests/characterization/r52_sync_production_orchestrator_parity.php
```

Fixture membandingkan exact persistence trace dan result antara legacy runner
dan production orchestrator menggunakan production adapter serta operation spy.
