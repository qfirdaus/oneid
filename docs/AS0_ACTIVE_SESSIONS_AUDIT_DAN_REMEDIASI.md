# AS0 Active Sessions Audit dan Remediasi

**Tarikh:** 18 Julai 2026  
**Skop:** Administrator > Active Sessions  
**Status:** IMPLEMENTED / CONTRACT AND READ-ONLY PREFLIGHT PASS  
**Mutation daripada listing:** Dilarang

## Penemuan Baseline

1. Persistence menggunakan `SELECT A.*`, menyebabkan token material dan medan
   dalaman dihantar ke endpoint walaupun UI tidak memerlukannya.
2. Refresh listing menukar token expired kepada status tidak aktif tanpa
   transaction atau audit, bercanggah dengan keterangan UI read-only.
3. Penanda Current tidak berfungsi kerana pengesanan current token dikomen.
4. Listing tidak membezakan Active, Grace, Due dan Expired secara konsisten
   dengan absolute lifetime serta SC5 policy revocation.
5. Tiada pagination, search, filter atau response contract khusus.
6. `token_datetime` dilabel sebagai Token Date/Time walaupun SC4 mentakrifkannya
   sebagai last activity; `token_issued_at` ialah masa issuance.

## Skop Remediasi

- explicit persistence projection tanpa token material dalam response;
- endpoint read-only dengan response berstruktur;
- lifecycle state server-side: Current, Active, Grace, Due dan Expired;
- Issued At dan Last Activity sebagai medan berasingan;
- search pengguna/peranti, status filter dan pagination;
- loading, empty, error dan pagination state yang stabil; dan
- automated contract bagi authorization boundary, zero mutation, projection
  dan UI contract.

## Boundary Ditangguhkan

Controlled revoke satu sesi atau semua sesi pengguna tidak termasuk dalam AS0.
Ia memerlukan Admin Step-Up 2FA, typed confirmation, transaction, audit dan
perlindungan self-lockout. Task tersebut kekal dalam handoff SC7-SC8.

SC5 revocation runner dan Cron External Sync juga bukan sebahagian daripada
listing ini dan tidak akan diaktifkan oleh remediasi AS0.

## Hasil Pelaksanaan

- Persistence menggunakan explicit projection dan query berparameter.
- Listing membezakan Current, Active, Grace, Due dan Expired.
- Search, status filter dan page size 10/25/50 tersedia.
- Preflight local mengesahkan response tanpa forbidden fields dan status digest
  token kekal sama sebelum serta selepas listing.
- Contract AS0 dan regression SC4/SC5 lulus tanpa mengaktifkan scheduler.

## Acceptance Criteria

- Tiada `token_id`, hash token, correlation revocation atau token material lain
  dihantar ke browser.
- Membuka, refresh, search, filter dan pagination menghasilkan zero mutation.
- Current hanya menandakan token admin semasa dan ditentukan server-side.
- Grace/Due mengikuti `policy_revoke_at`; Expired mengikuti absolute lifetime.
- Query dibataskan kepada page size yang allowlisted.
- Endpoint kekal admin-only, POST, CSRF dan exactly-one-action guarded.
- Semua contract dan regression SC4/SC5 lulus.
