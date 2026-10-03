#!/usr/bin/env python3
"""Interactive secret entry; never put token in argv, report or chat."""
import getpass
import json
from pathlib import Path
import subprocess
import sys
ROOT = Path(__file__).resolve().parent.parent
if sys.argv[1:] not in [['--run'],['--run','--applications'],['--run','--benchmark']] or not sys.stdin.isatty():
    raise SystemExit('Run interactively: python3 -B tools/php84_valid_token_check.py --run')
token = getpass.getpass('Paste only sso_cre cookie VALUE for account 0530-09 (hidden): ').strip()
result = subprocess.run(['/usr/bin/php8.4', str(ROOT/'tools/php84_valid_token_check.php')],
                        input=json.dumps({'token':token,'mode':'benchmark' if '--benchmark' in sys.argv else ('applications' if '--applications' in sys.argv else 'idp')}), capture_output=True, text=True, timeout=210)
del token
try:
    report = json.loads(result.stdout)
except ValueError:
    raise SystemExit('STOP: unexpected helper output; no raw diagnostics printed')
report['date'] = '2026-10-03'
(ROOT/('docs/php84/phase4-authenticated-api-timing.json' if '--benchmark' in sys.argv else 'docs/php84/phase4-application-acl-api.json' if '--applications' in sys.argv else 'docs/php84/phase4-valid-token-api.json')).write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps(report,indent=2))
raise SystemExit(result.returncode)
