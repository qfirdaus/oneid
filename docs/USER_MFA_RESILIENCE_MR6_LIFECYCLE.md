# MR6 — Emergency Bypass Lifecycle dan Worker

**Tarikh:** 7 September 2026
**Status:** IMPLEMENTED / CONTROLLED UAT PASSED / WORKER DORMANT

MR6 menyediakan worker CLI bounded dan row-locked untuk menamatkan grace work
serta memulihkan exact previous mode apabila Emergency Bypass tamat. Restore
terikat kepada request aktif dan `applied_policy_version`; mismatch gagal
tertutup tanpa melanjutkan bypass secara logik.

UI memaparkan banner persistent bagi `ENROLLMENT`, `EMERGENCY_BYPASS` dan
`OFF`, deadline bypass, serta tindakan manual restore yang memerlukan Admin
Step-Up, reason, reference dan typed confirmation `RESTORE USER MFA NOW`.

Worker mempunyai `--check` tanpa mutation dan `--apply` yang dikawal oleh
`ONEID_USER_MFA_LIFECYCLE_WORKER_ENABLED`. Ia merekod run idempotent,
history, syslog dan notifikasi amaran 30/10 minit menggunakan idempotency seed.
Committed default worker kekal `false`; tiada cron dipasang dalam fasa ini.

Controlled UAT pertama menemui pembacaan epoch yang mencampurkan
`UTC_TIMESTAMP()` dan timestamp window berasaskan `NOW(6)`. Keadaan ini gagal
selamat kepada `ENFORCED` dan tidak membuka bypass. Reader kemudian diselaraskan
kepada `UNIX_TIMESTAMP(NOW(6))` supaya current time dan window menggunakan jam
database yang sama.

Controlled UAT pada 7–8 September 2026 mengesahkan Emergency Bypass,
password-only login dalam window, akses User Security, banner persistent dan
manual restore kepada exact previous mode `ENFORCED`. Isu paparan input modal
dan sambung-semula selepas Admin Step-Up purpose mismatch telah dibetulkan;
regression contract melindungi kedua-dua tingkah laku tersebut.
