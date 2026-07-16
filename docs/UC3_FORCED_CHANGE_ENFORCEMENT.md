# UC3 — Forced Password Change Enforcement

**Status:** IMPLEMENTED — CONTRACT PASS

Setiap protected `q_func` action kini membaca `password_change_required` dan
`avail_status` secara authoritative daripada database. Jika flag aktif, hanya
`check_default_password` dan `action_change_password` dibenarkan; application
listing/launch, token action dan admin action ditolak dengan HTTP 403 serta code
`UC3_PASSWORD_CHANGE_REQUIRED`. Logout kekal tersedia melalui flow berasingan.

Ini menutup bypass direct POST/direct action dan menyegarkan session flag yang
sebelum ini hanya diambil ketika login.
