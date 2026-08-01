# LB5 — UI Administrator Banner Login

**Status:** LOCAL PASS / MIGRATION NOT APPLIED / PUBLIC LOGIN UNCHANGED

LB5 menambah tab **Banner Login** dalam halaman Konfigurasi Administrator. UI
ini menggunakan enam action LB4 dan tidak berhubung terus dengan database atau
filesystem.

## Capability UI

- senarai banner khusus `ONEID_ENVIRONMENT`, termasuk status, version, schedule,
  locale dan immutable preview;
- create draft dengan banner key, order 1–5, optional schedule, alt text BM/EN,
  upload BM dan upload EN;
- pilihan **gunakan imej BM yang sama untuk English**, tanpa duplicate upload;
- local preview sebelum upload;
- publish, inactivate dan rollback dengan confirmation dan change reason;
- edit semua position kemudian simpan reorder secara atomic;
- refresh, localized live status dan correlation reference.

## Security dan failure behavior

Semua request membawa CSRF header. Mutation membawa `expected_version` daripada
senarai terakhir dan masih tertakluk kepada Admin Step-Up
`SECURITY_CONFIGURATION_CHANGE` pada LB4. Grant tiada atau luput mengarahkan
Administrator ke flow Step-Up; upload perlu dipilih semula selepas kembali.

Schema dormant memaparkan localized unavailable state dan tidak membuka
workspace mutasi. Data daripada server dibina menggunakan DOM `textContent`,
bukan HTML injection. Preview menggunakan immutable filename yang telah
divenarkan LB4 sahaja.

## Accessibility dan responsive

Tab mempunyai relationship `aria-controls`/`aria-labelledby`; status menggunakan
`role=status` dan `aria-live`; semua input mempunyai label; action ialah native
button. Layout dua kolum berubah kepada satu kolum pada skrin kecil dan imej
kekal bernisbah 2:1.

## Verification

- `php tools/lb5_login_banner_admin_ui_contract.php`
- syntax PHP/JavaScript dan semua regression contract LB0–LB4.

Migration LB1 masih belum diaplikasi. Login page terus menggunakan `banner6.png`
dan `banner7.png`. Langkah seterusnya ialah LB6 dynamic login renderer dengan
static fail-safe fallback.
