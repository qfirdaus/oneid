#!/usr/bin/env python3
"""Verify package directory and tar contents; never extract or execute them."""
import hashlib,json,sys,tarfile
from pathlib import Path,PurePosixPath
root=Path(sys.argv[1]);sha=lambda b:hashlib.sha256(b).hexdigest()
for line in (root/'SHA256SUMS').read_text().splitlines():
    digest,name=line.split('  ',1)
    if Path(name).name!=name or sha((root/name).read_bytes())!=digest:raise SystemExit('Checksum mismatch')
m=json.loads((root/'git-manifest.json').read_bytes())
expected={x['path']:x for x in m['files'] if x['included']}
with tarfile.open(root/'oneid-production-candidate.tar') as archive:
    seen=set()
    for member in archive.getmembers():
        name=member.name
        if name in seen or not member.isfile() or PurePosixPath(name).is_absolute() or '..' in PurePosixPath(name).parts:raise SystemExit('Unsafe/duplicate tar entry')
        seen.add(name);data=archive.extractfile(member).read()
        if name=='RELEASE-MANIFEST.json':
            if data!=(root/'git-manifest.json').read_bytes():raise SystemExit('Manifest mismatch')
        elif name not in expected or sha(data)!=expected[name]['sha256'] or len(data)!=expected[name]['bytes']:raise SystemExit('Content mismatch: '+name)
    if seen!=set(expected)|{'RELEASE-MANIFEST.json'}:raise SystemExit('File set mismatch')
print('PASS: checksums, manifest, exact tar file set and safe paths verified. No extraction/deployment.')
