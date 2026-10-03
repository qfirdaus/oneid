#!/usr/bin/env python3
"""Pinned private MySQL fixture, UNIX socket only; never application DB/config."""
import hashlib
import json
from pathlib import Path
import secrets
import signal
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parents[2]
sys.dont_write_bytecode = True
sys.path.insert(0, str(ROOT / 'tools/mobile-oidc-poc'))
from prepare import RUNTIME, guard


def main():
    if sys.argv[1:] != ['--uat-only']:
        raise SystemExit('Usage: sql-test.py --uat-only')
    guard()
    mysql = RUNTIME / 'mysql'
    for item in json.loads((Path(__file__).parent / 'mysql-lock.json').read_text()):
        package = RUNTIME / item['filename']
        if not package.exists():
            subprocess.run(['apt-get', 'download', item['package'] + '=' + item['version']], cwd=RUNTIME, check=True)
        if hashlib.sha256(package.read_bytes()).hexdigest() != item['sha256']:
            raise RuntimeError('Pinned package checksum mismatch')
        subprocess.run(['dpkg-deb', '-x', str(package), str(mysql)], check=True)
    run = RUNTIME / ('sql-' + secrets.token_hex(6))
    run.mkdir(mode=0o700)
    datadir = run / 'data'
    sock = run / 'mysql.sock'
    env = {'PATH': '/usr/bin:/bin', 'LANG': 'C.UTF-8',
           'LD_LIBRARY_PATH': str(mysql / 'usr/lib/x86_64-linux-gnu')}
    binary = mysql / 'usr/sbin/mysqld'
    args = [str(binary), '--no-defaults', '--basedir=' + str(mysql / 'usr'),
            '--datadir=' + str(datadir), '--innodb-buffer-pool-size=32M', '--innodb-redo-log-capacity=8M']
    process = None
    result = None
    failure = None
    def interrupt(*_):
        raise KeyboardInterrupt()
    signal.signal(signal.SIGTERM, interrupt)
    try:
        with (run / 'mysql.log').open('ab') as log:
            subprocess.run(args + ['--initialize-insecure'], stdout=log, stderr=log, env=env, timeout=90, check=True)
            process = subprocess.Popen(args + ['--skip-networking', '--mysqlx=0', '--socket=' + str(sock),
                '--pid-file=' + str(run / 'mysql.pid'), '--secure-file-priv=NULL'], stdout=log, stderr=log, env=env)
            until = time.monotonic() + 30
            while not sock.exists():
                if process.poll() is not None or time.monotonic() > until:
                    raise RuntimeError('Fixture MySQL unavailable')
                time.sleep(.1)
            result = subprocess.run(['php', str(ROOT / 'tests/mobile-oidc/pdo.php')],
                input=json.dumps({'socket': str(sock)}), text=True, capture_output=True,
                cwd=ROOT, env={'PATH': '/usr/bin:/bin'}, timeout=90)
            (run / 'tests.log').write_text(result.stdout + result.stderr)
            # Test output is labels only; no PDO exception/SQL/credentials exposed.
            for line in result.stdout.splitlines():
                if line.startswith(('PASS ', 'FAIL ', 'Result:')):
                    print(line, flush=True)
            if result.returncode:
                failure = 'PHP fixture checks failed; inspect private tests.log'
    except (Exception, KeyboardInterrupt) as e:
        failure = type(e).__name__
    finally:
        if process and process.poll() is None:
            process.terminate()
            try:
                process.wait(timeout=20)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait(timeout=5)
    report = {'phase': 'B', 'scope': 'isolated MySQL fixture via private UNIX socket; no OneID application database',
              'mysql_version': '8.0.46', 'failure': failure,
              'checks': [line[5:] for line in (result.stdout.splitlines() if result else []) if line.startswith('PASS ')],
              'services_stopped': process is None or process.poll() is not None}
    (run / 'report.json').write_text(json.dumps(report, indent=2) + '\n')
    print('Report: ' + str(run / 'report.json'))
    print('Private MySQL stopped. ' + (failure or 'Fixture checks passed.'))
    return 1 if failure else 0


if __name__ == '__main__':
    raise SystemExit(main())
