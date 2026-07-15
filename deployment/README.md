# Deployment configuration

Template dalam direktori ini masuk Git dan perlu dipasang oleh deployment ke
lokasi sistem operasi yang berkenaan. Jangan simpan credential di sini.

- Nginx: pasang template yang sesuai ke `/etc/nginx/sites-available/`.
- PHP-FPM: sesuaikan pool/user/socket sebelum dipasang ke `/etc/php/8.3/fpm/pool.d/`.
- Cron: pasang dengan account service yang mempunyai access minimum diperlukan.

Document root mestilah `<project>/public`. Runtime local dan credential berada
di `<project>/.private/runtime.php` dan tidak masuk Git.
