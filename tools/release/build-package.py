#!/usr/bin/env python3
"""Offline, no Git index/commit/push and no target server changes."""
import argparse
import hashlib
import io
import json
import os
from pathlib import Path
import subprocess
import tarfile

ROOT=Path(__file__).resolve().parents[2]
def git(*args):
    return subprocess.check_output(['git',*args],cwd=ROOT)
def sha(data):
    return hashlib.sha256(data).hexdigest()
def excluded(name):
    p=Path(name)
    return (any(x in p.parts for x in ('.git','.private','vendor','node_modules','__pycache__'))
            or name.endswith(('.pyc','.log')) or name.startswith(('storage/runtime/','storage/backups/','storage/quarantine/')))
def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output',required=True,type=Path)
    args=parser.parse_args();out=args.output.resolve()
    if out==ROOT or ROOT in out.parents:
        raise SystemExit('Output must be outside source worktree')
    if out.exists():raise SystemExit('Refusing to overwrite existing package directory')
    tracked=set(git('ls-files','-z').decode().rstrip('\0').split('\0'))
    candidates=set(git('ls-files','-z','--cached','--others','--exclude-standard').decode().rstrip('\0').split('\0'))
    files={};entries=[];omitted=[]
    for name in sorted(candidates):
        if not name:continue
        p=ROOT/name
        if excluded(name):omitted.append(name);continue
        if p.is_symlink():raise SystemExit('Review symlink before packaging: '+name)
        if not p.exists():
            entries.append({'path':name,'change':'deleted','included':False});continue
        data=p.read_bytes()
        if b'-----BEGIN PRIVATE KEY-----' in data or b'-----BEGIN RSA PRIVATE KEY-----' in data:
            # Detector literal inside this tool is not a key; actual key has base64 content/newline.
            import re
            if re.search(rb'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----\r?\n[A-Za-z0-9+/=]{20}',data):
                raise SystemExit('Private key requires exclusion/review: '+name)
        previous=None
        if name in tracked:
            previous=git('show','HEAD:'+name)
        change='added' if previous is None else ('unchanged' if data==previous else 'modified')
        files[name]=data
        entries.append({'path':name,'change':change,'included':True,'bytes':len(data),'sha256':sha(data),
                        'mode':'0755' if os.access(p,os.X_OK) else '0644'})
    manifest={'format':1,'release':'oneid-production-web-android-php84-candidate',
        'status':'PREPARED_NOT_APPROVED_FOR_DEPLOYMENT','base_commit':git('rev-parse','HEAD').decode().strip(),
        'branch':git('branch','--show-current').decode().strip(),'source_tree':str(ROOT),
        'scope':['all Git source changes copied from UAT','production environment support','Android only','PHP 8.4.26 web/mobile'],
        'default_cli_php':'8.3','production_changed':False,'git_pushed':False,
        'excluded_paths':omitted,'exclusions':['private configuration/secrets','vendor rebuilt from composer.lock','ignored uploads/banners/runtime data'],
        'files':entries}
    out.mkdir(parents=True,mode=0o700)
    manifest_bytes=(json.dumps(manifest,indent=2,ensure_ascii=False)+'\n').encode()
    (out/'git-manifest.json').write_bytes(manifest_bytes)
    (out/'tracked-changes.patch').write_bytes(git('diff','--binary','HEAD'))
    (out/'git-status.txt').write_bytes(git('status','--short','--untracked-files=all'))
    # Uncompressed deterministic tar metadata; tar includes the manifest but not its own digest.
    archive=out/'oneid-production-candidate.tar'
    with tarfile.open(archive,'w',format=tarfile.PAX_FORMAT) as tar:
        for name,data in sorted({**files,'RELEASE-MANIFEST.json':manifest_bytes}.items()):
            info=tarfile.TarInfo(name);info.size=len(data);info.mtime=0;info.uid=info.gid=0
            info.mode=0o755 if name in files and os.access(ROOT/name,os.X_OK) else 0o644
            tar.addfile(info,io.BytesIO(data))
    sums=[]
    for p in sorted(out.iterdir()):
        if p.is_file():sums.append(sha(p.read_bytes())+'  '+p.name)
    (out/'SHA256SUMS').write_text('\n'.join(sums)+'\n')
    summary={k:sum(x['change']==k for x in entries) for k in ('added','modified','unchanged','deleted')}
    print(json.dumps({'output':str(out),'files':len(files),'changes':summary,'archive_sha256':sha(archive.read_bytes()),
                      'note':'Manifest and static key check are not a comprehensive secrets audit. No deploy/push performed.'},indent=2))
if __name__=='__main__':main()
