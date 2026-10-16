# OneID 2.20.3 — Dynamic Performance Gauge

Tarikh: 16 Oktober 2026

Gauge status kini melakukan animasi sweep semasa pemeriksaan prestasi sedang
berjalan. Selepas pemeriksaan selesai, jarum bergerak secara lancar ke sudut
yang dikira daripada masa respons sebenar dan mengekalkan status Fast,
Moderate, Slow atau Offline.

Pemeriksaan baharu dijalankan apabila panel gauge dibuka serta apabila Refresh
Data atau Clear Cache digunakan. Animasi mempunyai tempoh minimum supaya status
pengukuran jelas, dan tetapan `prefers-reduced-motion` terus dihormati.
