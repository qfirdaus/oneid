import hashlib
import json
from pathlib import Path
import secrets
import subprocess
import time


class PrivateMysql:
    def __init__(self, root, runtime):
        self.root, self.runtime = root, runtime
        self.directory = runtime / ('sql-' + secrets.token_hex(6))
        self.directory.mkdir(mode=0o700)
        self.socket = self.directory / 'mysql.sock'
        self.process = None

    def start(self):
        mysql = self.runtime / 'mysql'
        for item in json.loads((self.root / 'tools/mobile-oidc-adapter/mysql-lock.json').read_text()):
            package = self.runtime / item['filename']
            if not package.exists():
                subprocess.run(['apt-get', 'download', item['package'] + '=' + item['version']], cwd=self.runtime, check=True)
            if hashlib.sha256(package.read_bytes()).hexdigest() != item['sha256']:
                raise RuntimeError('MySQL checksum mismatch')
            subprocess.run(['dpkg-deb', '-x', str(package), str(mysql)], check=True)
        env = {'PATH': '/usr/bin:/bin', 'LANG': 'C.UTF-8', 'LD_LIBRARY_PATH': str(mysql / 'usr/lib/x86_64-linux-gnu')}
        args = [str(mysql / 'usr/sbin/mysqld'), '--no-defaults', '--basedir=' + str(mysql / 'usr'),
                '--datadir=' + str(self.directory / 'data'), '--innodb-buffer-pool-size=32M', '--innodb-redo-log-capacity=32M']
        with (self.directory / 'mysql.log').open('ab') as log:
            subprocess.run(args + ['--initialize-insecure'], stdout=log, stderr=log, env=env, timeout=90, check=True)
            self.process = subprocess.Popen(args + ['--skip-networking', '--mysqlx=0', '--socket=' + str(self.socket),
                '--pid-file=' + str(self.directory / 'mysql.pid'), '--secure-file-priv=NULL'], stdout=log, stderr=log, env=env)
        until = time.monotonic() + 30
        while not self.socket.exists():
            if self.process.poll() is not None or time.monotonic() > until:
                raise RuntimeError('Private MySQL unavailable')
            time.sleep(.1)

    def action(self, action, **extra):
        result = subprocess.run(['php', str(self.root / 'tests/mobile-oidc-hosted/database.php')],
            input=json.dumps({'socket': str(self.socket), 'action': action, **extra}), text=True,
            capture_output=True, cwd=self.root, env={'PATH':'/usr/bin:/bin'}, timeout=30)
        if result.returncode:
            (self.directory / 'fixture-error.log').write_text(result.stderr)
            raise RuntimeError('Fixture SQL failed; private diagnostics retained')
        return json.loads(result.stdout)

    def close(self):
        if self.process and self.process.poll() is None:
            self.process.terminate()
            try:
                self.process.wait(timeout=20)
            except subprocess.TimeoutExpired:
                self.process.kill()
                self.process.wait(timeout=5)
