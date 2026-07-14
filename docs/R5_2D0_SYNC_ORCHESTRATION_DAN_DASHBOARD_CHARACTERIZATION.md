# R5.2D0 — Sync Orchestration dan Dashboard Characterization

Tarikh: 14 Julai 2026

Change ID: `R5-2D0-20260714-034500`

Owner perubahan: Pemilik sistem OneID

Owner rollback: Pemilik sistem OneID

Status: **SELESAI — CHARACTERIZATION SAHAJA, TIADA RUNTIME MOVEMENT**

## 1. Objektif

R5.2D0 menyediakan baseline sebelum menyentuh dua kawasan berisiko tinggi:

- orchestration `run_admin_sync_user`;
- dashboard user/admin dan admin user list.

Subfasa ini tidak memindahkan orchestration, dashboard, `q_func`, database code,
view atau JavaScript. Live sync juga tidak dijalankan.

## 2. Caller dan Sempadan Sync

`run_admin_sync_user` mempunyai dua caller repository:

1. action `admin_add_sync_user` melalui `lib/q_func.php`;
2. `cron/run_sync.php`, walaupun owner sebelum ini menerima cron sebagai retired.

Orchestration bergantung pada:

- external source `EXTERNAL_DATA_SOURCE_GET_ALL_USER`;
- banyak method `Database` untuk transaction, staging, user update dan audit;
- matching staf menggunakan identifier `data4`;
- matching pelajar menggunakan gabungan matrix `u_id` dan IC `data2`;
- stored/inactive user state;
- hardcoded category mapping, excluded UID dan random initial password.

Map ringkas mesin-baca disimpan dalam
`docs/R5_2D0_ORCHESTRATION_DASHBOARD_MAP.tsv`.

## 3. In-memory Sync Fixture

Fail `tests/characterization/r52_sync_orchestration.php` menggunakan fake
operation object dan external rows dalam memory. Ia tidak membuka database,
network, session atau fail output.

Arahan:

```bash
php tests/characterization/r52_sync_orchestration.php
```

Fixture merangkumi:

- transaction success dan commit;
- existing matched user berubah → `UPDATE`;
- user SSO hilang daripada source → `DEACTIVATE`;
- external user baharu → `NEW`;
- inactive user kembali → `REACTIVATE`;
- category mapping `Pentadbiran → 3`, `Akademik → 2`;
- hardcoded UID exclusion;
- staging/body status dan audit action order;
- empty external source;
- exception dalam mutation block → rollback;
- exception upstream sebelum mutation block.

Keputusan: **18/18 PASS**.

## 4. Dashboard Static Characterization

Fail:

- `tests/characterization/r52_dashboard_contracts.php`;
- `tools/r52_dashboard_characterization.php`.

Arahan:

```bash
php tools/r52_dashboard_characterization.php
```

Keputusan: **21/21 PASS**.

| Entry point | LOC | `q_func` AJAX | Inline functions | Script tags |
|---|---:|---:|---:|---:|
| `page/dashboard.php` | 1,261 | 7 | 15 | 12 |
| `admin/dashboard.php` | 3,905 | 41 | 62 | 18 |
| `admin/user_list.php` | 156 | 0 | 0 | 3 |

Static contract turut mengunci auth/admin guard, top include, logout seam,
sync actions dan user-list navigation. Ia tidak menggantikan browser visual,
plugin, modal atau authenticated feature UAT.

## 5. Weakness Ditemui

### D0-W01 — Transaction boundary tidak meliputi upstream/preprocessing

`beginTransaction()` dipanggil sebelum external-source fetch, tetapi `try/catch`
yang memanggil rollback hanya bermula selepas fetch, filtering dan matching.
Fixture membuktikan upstream exception berlaku selepas transaction bermula tanpa
commit atau rollback oleh orchestration.

Ini behavior sedia ada dan tidak dibetulkan dalam D0. Pembetulan perlu change
functional tersendiri kerana ia mengubah failure/transaction semantics.

### D0-W02 — Tiada orchestration interface

Function bergantung terus pada global external-source function dan operation
object tanpa interface. Ini menyukarkan isolated test; fixture perlu menyediakan
global function stub dan fake method surface yang besar.

### D0-W03 — Policy bercampur dengan orchestration

Category mapping, excluded UID, initial password generation, matching identity,
audit construction dan transaction lifecycle berada dalam satu function 300+
baris.

### D0-W04 — Dashboard monolith

Dashboard admin mempunyai 3,905 baris, 41 AJAX seams dan 62 inline functions.
Dashboard user mempunyai 1,261 baris dan 7 AJAX seams. PHP auth/data load, HTML,
plugin markup dan JavaScript bercampur dalam fail sama.

### D0-W05 — Single AJAX controller blast radius

Kedua-dua dashboard bergantung pada `lib/q_func.php`. Pemindahan dashboard yang
turut mengubah action names, DOM IDs atau relative URL boleh menjejaskan auth,
admin, upload, sync dan SSO serentak.

## 6. Keputusan Risiko

- orchestration sync belum sesuai dipindahkan terus ke `app/Sync`;
- dashboard penuh tidak boleh dipindahkan sebagai satu batch;
- `q_func` tidak boleh dipecahkan bersama dashboard movement;
- transaction weakness tidak boleh dibaiki dalam restructuring-only change;
- browser UAT diperlukan bagi setiap presentation batch.

## 7. Cadangan Langkah Seterusnya

Langkah paling selamat ialah **R5.2D1 — seam preparation tanpa runtime switch**:

1. definisikan interface untuk data source dan sync persistence di bawah `app/Sync`;
2. gunakan fixture D0 untuk menetapkan method/DTO contract;
3. jangan wire interface kepada production orchestration dahulu;
4. sediakan dashboard feature inventory mengikut domain, bukan mengikut blok baris;
5. pilih satu presentation-only seam kecil untuk batch berasingan;
6. kekalkan URL, action key, DOM ID dan relative asset contract.

Pemindahan `run_admin_sync_user` atau dashboard sebenar memerlukan arahan owner
berasingan selepas design D1 disemak.

## 8. Rollback D0

Tiada runtime rollback diperlukan. Tooling sahaja boleh dibuang:

```text
tests/characterization/r52_sync_orchestration.php
tests/characterization/r52_dashboard_contracts.php
tools/r52_dashboard_characterization.php
docs/R5_2D0_ORCHESTRATION_DASHBOARD_MAP.tsv
docs/R5_2D0_SYNC_ORCHESTRATION_DAN_DASHBOARD_CHARACTERIZATION.md
```

Jangan ubah atau buang sync runner/dashboard kerana D0 tidak menyentuhnya.

## 9. Checksum Baseline

| Fail | SHA-256 |
|---|---|
| `lib/sync_user_runner.php` tidak berubah | `7a5fbe4e9d176661eda4f7d26449641edef683e77d355f9b9d5e227a9331b9df` |
| `page/dashboard.php` tidak berubah | `9077b77174d7ec33fcc91b9a66ca349c7f6a105ccbc764dc06511e9f195fc361` |
| `admin/dashboard.php` tidak berubah | `24b2028f0d978a0ce38d1915d2a7bf60445e7ac36c0a360fea04b3db089be7dc` |
| `admin/user_list.php` tidak berubah | `d63cd414e51cf78399c2c4b54dae63ce310058dd08350977e32ab3c4a1ebe9d3` |
| `tests/characterization/r52_sync_orchestration.php` | `d983a32a0365fa6e5b86011a6f3bd9f70845ec1337b1fcfd2ef8faf615aea11c` |
| `tests/characterization/r52_dashboard_contracts.php` | `edf6d01f127c32f78789de9984113787a8466f517503e69408f45b587f7fecf9` |
| `tools/r52_dashboard_characterization.php` | `a252d8c7621ca1219545687feabd92e8edc3f2743c59322ca63fc39256c6213a` |

## 10. Keputusan

R5.2D0 selesai. Sync orchestration fixture dan dashboard static baseline tersedia,
tetapi tiada GO untuk runtime movement. GO hanya untuk design/interface dan seam
preparation D1 tanpa production wiring.

**Kemaskini R5.2D1:** Empat interface dan readonly summary DTO telah disediakan
di `app/Sync` tanpa production wiring. Design validation lulus 18/18 dan checksum
sync runner kekal sama. Rujuk `R5_2D1_SYNC_INTERFACE_SEAM_DESIGN.md`.
