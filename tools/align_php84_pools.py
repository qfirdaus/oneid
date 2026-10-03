#!/usr/bin/env python3
"""Align only the two isolated OneID PHP 8.4 pools; no routing edits."""
import argparse, datetime, hashlib, json, os, subprocess
from pathlib import Path
from php84_fpm_probe import probe
KEYS=['memory_limit','max_execution_time','post_max_size','upload_max_filesize','date.timezone','session.gc_maxlifetime','session.cookie_secure','session.cookie_httponly']
PAIRS=[('php8.3-fpm','oneid-web-uat84'),('oneid-mobile-uat','oneid-mobile-uat84')]
def protected():
    return {str(p):hashlib.sha256(p.read_bytes()).hexdigest() for root in ['/etc/nginx','/etc/php/8.3'] for p in Path(root).rglob('*') if p.is_file() and (p.suffix in ['.conf','.ini'] or p.parent.name in ['sites-enabled','sites-available'])}
def main():
    parser=argparse.ArgumentParser();parser.add_argument('--apply',action='store_true');a=parser.parse_args()
    changes={};expected={}
    for old,new in PAIRS:
        state=probe('/run/php/'+old+'.sock');expected[new]=state['settings']
        path=Path('/etc/php/8.4/fpm/pool.d/'+new+'.conf');original=path.read_text()
        # Settings remain overridable by application code, notably mobile session GC=900.
        lines=original.splitlines();lines=[l for l in lines if not any(l.startswith('php_value['+k+']') or l.startswith('php_admin_value['+k+']') or l.startswith('php_admin_flag['+k+']') for k in KEYS)]
        for k in KEYS:
            v=state['settings'][k]
            if not v or '\n' in v:raise RuntimeError('Unexpected baseline setting: '+k)
            kind='php_value' if k.startswith('session.') else 'php_admin_value'
            lines.append(kind+'['+k+'] = '+v)
        changes[path]=(original,'\n'.join(lines)+'\n')
        print(new, {k:state['settings'][k] for k in KEYS})
    if not a.apply:print('CHECK only: no changes.');return
    if os.geteuid()!=0:raise RuntimeError('Use sudo for --apply.')
    before=protected();backup=Path('/var/backups/oneid-php84-alignment-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'));backup.mkdir(mode=0o700)
    for p,(original,_) in changes.items():(backup/p.name).write_text(original)
    try:
        for p,(_,content) in changes.items():p.write_text(content)
        subprocess.run(['/usr/sbin/php-fpm8.4','-t'],check=True)
        subprocess.run(['systemctl','reload','php8.4-fpm'],check=True)
        import time
        time.sleep(1)
        for _,new in PAIRS:
            actual=probe('/run/php/'+new+'.sock')
            if actual['version']!='8.4.26':raise RuntimeError('Wrong FPM version')
            for k in KEYS:
                if actual['settings'][k]!=expected[new][k]:raise RuntimeError('Mismatch: '+new+' '+k)
        if before!=protected():raise RuntimeError('Protected configuration drift')
    except Exception:
        for p,(original,_) in changes.items():p.write_text(original)
        subprocess.run(['/usr/sbin/php-fpm8.4','-t'],check=True)
        subprocess.run(['systemctl','reload','php8.4-fpm'],check=True)
        raise
    print('PASS: two PHP 8.4 pools aligned and verified by FastCGI. Routing and PHP 8.3 unchanged.')
    print('Backup:',backup)
if __name__=='__main__':
    try:main()
    except Exception as e:raise SystemExit('STOP: '+str(e))
