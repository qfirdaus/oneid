# Production PHP 8.4 Web Cutover — Post-cutover Checkpoint

Date: 2026-10-05 (Asia/Kuala_Lumpur)
Scope: `oneid.upnm.edu.my` web traffic only.

## Result

The production web cutover to PHP 8.4.26 is operationally successful. Browser login, dashboard rendering, session countdown, logout, and downstream SSO access were tested and reported successful.

The missing frontend vendor assets discovered after cutover were restored under the Nginx document root `/var/www/oneid/public/vendors`. jQuery, Bootstrap, and vectormap assets subsequently returned HTTP 200 with the expected JavaScript/CSS content types. The session countdown then rendered normally.

## Recorded production state

- Nginx web route: `/run/php/oneid-web-prod84.sock`
- PHP-FPM web runtime: PHP 8.4.26
- PHP 8.3 FPM: retained and running for rollback readiness
- Mobile production routing: disabled
- PHP CLI/default: remains PHP 8.3
- Cron/timers: unchanged; remain on the existing PHP 8.3 path
- Database: unchanged
- Rollback backup: `/var/backups/oneid-nginx-cutover-20261005-081739`
- Code backup: `/var/backups/oneid-code-20261005-081341`
- Legacy vendor restore backup: `/var/backups/oneid-legacy-vendors-20261005-082053`
- Frontend vendor backup: `/var/backups/oneid-bower-components-before-restore-20261005-*`

## Validation completed

- Public HTTPS response: HTTP 200.
- PHP-FPM and Nginx services active.
- Browser login and authenticated dashboard: passed.
- Session countdown and renewal control: passed.
- Logout/session behaviour: passed.
- Downstream SSO access: passed.
- Frontend vendor content types: passed after restoration.
- Browser console: no remaining asset corruption errors reported after restoration.

## Deferred work

Do not enable production mobile login until the Android package ID and production redirect URI are approved. CLI, `phar`, cron and timer migration to PHP 8.4 is a separate staged change and has not been performed. Keep monitoring web traffic and PHP 8.4-FPM logs before starting that phase.

## Next checkpoint

1. Monitor Nginx 5xx, PHP-FPM errors and application errors during normal operation.
2. Record the monitoring window and any incident-free period.
3. Decide separately whether to migrate CLI/cron/phar to PHP 8.4.26.
4. Keep the documented rollback backups until the monitoring window is accepted.
