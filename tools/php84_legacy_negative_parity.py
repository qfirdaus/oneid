#!/usr/bin/env python3
"""Compare safe negative legacy API contracts; no valid tokens/credentials used."""
import json
from pathlib import Path
import subprocess

host = 'oneid-uat.upnm.edu.my'
cases = [('missing_body', None), ('invalid_json', '{'), ('unknown_token', json.dumps({
    'flag': '1', 'data': {'site_id': 'IDP', 'token': 'php84-isolated-nonexistent-token-probe'}}))]
results = []
for name, payload in cases:
    pair = {}
    for runtime, port in [('8.3', 443), ('8.4', 24484)]:
        cmd = ['curl', '--silent', '--show-error', '--noproxy', '*', '--max-time', '20',
               '--connect-to', f'{host}:443:127.0.0.1:{port}', '-w', '\n%{http_code}', f'https://{host}/api.php']
        if payload is not None:
            cmd += ['-H', 'Content-Type: application/json', '--data-binary', payload]
        out = subprocess.run(cmd, check=True, capture_output=True, text=True).stdout
        body, code = out.rsplit('\n', 1)
        parsed = json.loads(body)
        # request_id is generated for every error response; verify its presence/type,
        # then compare all remaining payload fields without masking other differences.
        has_id = isinstance(parsed, dict) and 'request_id' in parsed
        valid_id = not has_id or (isinstance(parsed['request_id'], str) and bool(parsed['request_id']))
        if has_id:
            parsed.pop('request_id')
        pair[runtime] = {'status': int(code), 'request_id_present': has_id,
                         'request_id_valid': valid_id, 'body': parsed}
    rejected = all(x['status'] >= 400 or (isinstance(x['body'], dict) and x['body'].get('respond') == '0') for x in pair.values())
    results.append({'case': name, 'match_excluding_request_id_value': pair['8.3'] == pair['8.4'],
                    'baseline_status': pair['8.3']['status'], 'php84_status': pair['8.4']['status'],
                    'rejected_both': rejected, 'request_ids_valid': all(x['request_id_valid'] for x in pair.values())})
report = {'date': '2026-10-03', 'scope': 'Negative legacy API parity only; no authenticated downstream validation',
          'normalization': 'Only per-request request_id value excluded; presence/type validated', 'results': results}
(Path(__file__).resolve().parent.parent/'docs/php84/phase4-legacy-api-negative-parity.json').write_text(json.dumps(report, indent=2)+'\n')
print(json.dumps(report, indent=2))
raise SystemExit(0 if all(x['match_excluding_request_id_value'] and x['rejected_both'] and x['request_ids_valid'] for x in results) else 1)
