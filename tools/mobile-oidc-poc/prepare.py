#!/usr/bin/env python3
"""Download pinned tools into ignored private storage; never install system packages."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import platform
import subprocess
import tarfile
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
RUNTIME = ROOT / '.private/mobile-oidc-poc'
LOCK = json.loads((Path(__file__).parent / 'provider-lock.json').read_text())


def guard():
    if ROOT != Path('/var/www/oneid-uat') or platform.machine() != 'x86_64':
        raise RuntimeError('This PoC is restricted to the designated amd64 UAT workspace.')
    if RUNTIME.is_symlink() or (RUNTIME.exists() and RUNTIME.resolve() != RUNTIME):
        raise RuntimeError('Runtime directory must not be redirected.')
    os.umask(0o077)
    RUNTIME.mkdir(exist_ok=True, mode=0o700)


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--uat-only', action='store_true', required=True)
    p.parse_args()
    guard()
    archive = RUNTIME / 'hydra.tar.gz'
    base = 'https://github.com/ory/hydra/releases/download/' + LOCK['hydra_version'] + '/'
    if not archive.exists():
        with urllib.request.urlopen(base + LOCK['hydra_archive'], timeout=120) as r:
            archive.write_bytes(r.read())
    if hashlib.sha256(archive.read_bytes()).hexdigest() != LOCK['hydra_archive_sha256']:
        raise RuntimeError('Hydra archive checksum mismatch; refusing extraction.')
    (RUNTIME / 'bin').mkdir(exist_ok=True)
    binary = RUNTIME / 'bin/hydra'
    with tarfile.open(archive) as tar:
        entry = tar.getmember('hydra')
        if not entry.isfile():
            raise RuntimeError('Invalid binary archive entry')
        binary.write_bytes(tar.extractfile(entry).read())
    binary.chmod(0o700)
    if hashlib.sha256(binary.read_bytes()).hexdigest() != LOCK['hydra_binary_sha256']:
        raise RuntimeError('Hydra binary checksum mismatch')
    package = LOCK['postgres_package']
    version = package.split('=')[1]
    deb = RUNTIME / ('postgresql-16_' + version + '_amd64.deb')
    if not deb.exists():
        subprocess.run(['apt-get', 'download', package], cwd=RUNTIME, check=True)
    if hashlib.sha256(deb.read_bytes()).hexdigest() != LOCK['postgres_deb_sha256']:
        raise RuntimeError('PostgreSQL package checksum mismatch; refusing extraction.')
    metadata = subprocess.check_output(['dpkg-deb', '-f', str(deb), 'Package', 'Version', 'Architecture'], text=True)
    if 'Version: ' + version not in metadata or 'Architecture: amd64' not in metadata:
        raise RuntimeError('Unexpected PostgreSQL package')
    subprocess.run(['dpkg-deb', '-x', str(deb), str(RUNTIME / 'postgres')], check=True)
    (RUNTIME / 'artifact-evidence.json').write_text(json.dumps({
        'hydra_archive_sha256': LOCK['hydra_archive_sha256'],
        'postgres_package': package,
        'postgres_deb_sha256': hashlib.sha256(deb.read_bytes()).hexdigest(),
        'source': 'GitHub release checksum and configured Ubuntu APT package metadata',
    }, indent=2) + '\n')
    print('Prepared pinned Hydra and PostgreSQL binaries in ignored private UAT directory.')
    print('No service installed, no application database accessed.')


if __name__ == '__main__':
    main()
