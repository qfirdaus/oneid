# OneID 2.20.1 — Admin Access Keep-Alive

Tarikh: 16 Oktober 2026

Release ini membetulkan keadaan apabila simpanan akses aplikasi berjaya tetapi
permintaan muat semula selepasnya menghantar administrator keluar daripada
dashboard kerana grant `ADMIN_ACCESS` tamat pada sempadan antara dua request.

Aktiviti pentadbiran rutin yang telah melalui authorization kini memperbaharui
grant apabila baki tiga minit atau kurang. Paparan deadline diselaraskan semula
selepas request AJAX berjaya. Renewal mengekalkan masa MFA asal, maka tetingkap
lima minit bagi tindakan kritikal tidak dipanjangkan.

Kawalan sesi sah, token SSO aktif, role administrator, CSRF, idle timeout,
absolute timeout dan purpose khusus bagi tindakan kritikal kekal dikuatkuasakan.
