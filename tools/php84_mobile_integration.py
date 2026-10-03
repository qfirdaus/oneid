#!/usr/bin/env python3
"""Private real-provider rehearsal through disposable FPM + Nginx, no UAT traffic."""
import json
from pathlib import Path
import re
import signal
import sys
import time

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT/'tools/mobile-oidc-hosted'))
import rehearsal as r


class FpmLab(r.HostedLab):
    def start_php(self):
        # Fixture entrypoint allows cli-server only. Adapt only a private copy to FPM;
        # the active application's safety guard must remain unchanged.
        source = (ROOT/'mobile-public/index.php').read_text()
        guard = "PHP_SAPI !== 'cli-server'"
        if source.count(guard) != 1:
            raise RuntimeError('Fixture entrypoint guard changed; review required')
        source = source.replace(guard, "PHP_SAPI !== 'fpm-fcgi'")
        source = source.replace('__DIR__', repr(str(ROOT/'mobile-public')))
        entry = self.run_dir/'fpm-entry.php'
        entry.write_text(source); entry.chmod(0o600)
        sock = self.run_dir/'fpm.sock'
        config = self.run_dir/'fpm.conf'
        config.write_text(f'''[global]
error_log = {self.run_dir}/fpm-master.log
daemonize = no
[fixture]
listen = {sock}
pm = static
pm.max_children = 2
clear_env = yes
env[ONEID_MOBILE_CONFIG] = {self.php_config}
php_admin_value[error_reporting] = 32767
php_admin_flag[display_errors] = off
php_admin_flag[log_errors] = on
php_admin_value[error_log] = {self.run_dir}/php-errors.log
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 30
php_admin_value[post_max_size] = 64K
php_admin_value[upload_max_filesize] = 64K
php_value[date.timezone] = Asia/Kuala_Lumpur
''')
        self.php = self.spawn(['/usr/sbin/php-fpm8.4', '-F', '-y', config], 'fpm-process')
        until = time.monotonic()+15
        while not sock.exists():
            if self.php.poll() is not None or time.monotonic()>until:
                raise RuntimeError('Private FPM startup failed')
            time.sleep(.1)
        # Verify actual private worker version, not only the CLI executable.
        sys.path.insert(0, str(ROOT/'tools'))
        from php84_fpm_probe import probe
        state = probe(str(sock))
        self.check('private worker is PHP 8.4.26 FPM', state['version']=='8.4.26' and state['sapi']=='fpm-fcgi')
        # Rehearsal restarts FPM while the private HTTP frontend can stay running.
        # Reuse it instead of attempting a second bind to the same port.
        if getattr(self, 'http', None) is not None and self.http.poll() is None:
            return
        nginx = self.run_dir/'nginx.conf'
        nginx.write_text(f'''pid {self.run_dir}/nginx.pid;
error_log /dev/null crit;
events {{}}
http {{
access_log off;
client_body_temp_path {self.run_dir}/body;
proxy_temp_path {self.run_dir}/proxy;
fastcgi_temp_path {self.run_dir}/fastcgi;
uwsgi_temp_path {self.run_dir}/uwsgi;
scgi_temp_path {self.run_dir}/scgi;
server {{
listen 127.0.0.1:{self.hosted_port};
server_name 127.0.0.1;
client_max_body_size 64k;
location / {{
include /etc/nginx/fastcgi_params;
fastcgi_param SCRIPT_FILENAME {entry};
fastcgi_param HTTP_PROXY "";
fastcgi_pass unix:{sock};
fastcgi_intercept_errors off;
}}
}}
}}
''')
        self.execute(['/usr/sbin/nginx', '-t', '-p', self.run_dir, '-c', nginx], 'nginx-syntax')
        self.http = self.spawn(['/usr/sbin/nginx', '-p', self.run_dir, '-c', nginx, '-g', 'daemon off;'], 'nginx')
        self.wait_port(self.hosted_port, self.http)


def main():
    if sys.argv[1:] != ['--uat-only']:
        raise SystemExit('Usage: python3 -B tools/php84_mobile_integration.py --uat-only')
    before = r.legacy_digest()
    lab = FpmLab()
    failure = None
    def interrupt(*_):
        raise KeyboardInterrupt()
    signal.signal(signal.SIGTERM, interrupt)
    try:
        lab.start()
        r.tests(lab)
    except (Exception, KeyboardInterrupt) as error:
        failure = type(error).__name__
        print('STOPPED: '+failure+'; private diagnostics retained', flush=True)
    finally:
        lab.close()
    after = r.legacy_digest()
    errorlog = lab.run_dir/'php-errors.log'
    errors = errorlog.read_text() if errorlog.exists() else ''
    warnings = len(re.findall(r'PHP (?:Warning|Deprecated|Fatal error|Parse error|Notice):', errors))
    lab.events += [
        {'test':'tracked runtime unchanged', 'passed':before==after},
        {'test':'no PHP warning/deprecation/fatal/notice logged', 'passed':warnings==0},
        {'test':'all temporary services stopped', 'passed':all(p.poll() is not None for p in lab.processes) and (lab.mysql.process is None or lab.mysql.process.poll() is not None)}]
    report = {'date':'2026-10-03','runtime':'PHP 8.4.26 FPM (disposable worker)',
        'scope':'Real Hydra/PostgreSQL/private MySQL, synthetic identities, HTTP loopback Nginx/FPM. Token hook/session/refresh/logout all use private FPM.',
        'entrypoint_adaptation':'Private copy only: fixture CLI-server SAPI guard changed to FPM; __DIR__ preserved. Application unchanged.',
        'failure':failure, 'php_warning_count':warnings, 'tests':lab.events,
        'limits':['Not native Android/iOS E2E','Not real MyDigital ID provider or SMTP','Not downstream legacy SSO validation','Not authenticated UAT performance'],
        'legacy_before':before,'legacy_after':after}
    target=ROOT/'docs/php84/phase4-mobile-fpm-integration.json'
    target.write_text(json.dumps(report,indent=2)+'\n')
    passed=sum(x['passed'] for x in lab.events)
    print(f'Report: {target}\nResult: {passed}/{len(lab.events)} checks passed; private services stopped')
    if failure:
        print('Private diagnostics directory: '+str(lab.run_dir))
    return 1 if failure or passed!=len(lab.events) else 0

if __name__=='__main__':
    raise SystemExit(main())
