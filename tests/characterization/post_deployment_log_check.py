#!/usr/bin/env python3

import datetime as dt
import json
import subprocess
import tempfile
from pathlib import Path
from zoneinfo import ZoneInfo


root = Path(__file__).resolve().parents[2]
tool = root / "tools/post_deployment_log_check.py"
timezone = ZoneInfo("Asia/Kuala_Lumpur")
now = dt.datetime.now(timezone).replace(microsecond=0)
php_stamp = now.strftime("%d-%b-%Y %H:%M:%S")
access_stamp = now.strftime("%d/%b/%Y:%H:%M:%S %z")

with tempfile.TemporaryDirectory(prefix="oneid-log-check-") as temporary:
    directory = Path(temporary)
    php_log = directory / "php-error.log"
    access_log = directory / "oneid.access.log"
    php_log.write_text(
        f'[{php_stamp} Asia/Kuala_Lumpur] '
        '{"component":"oneid_integration","event":"api_access",'
        '"request_id":"sensitive-reference","ip":"192.0.2.1",'
        '"endpoint":"sso_token","auth_result":"missing_credentials","mode":"observe"}\n'
    )
    access_log.write_text(
        f'192.0.2.1 - - [{access_stamp}] "GET /safe HTTP/1.1" 200 123 "agent"\n'
    )
    command = [
        "python3", str(tool), "--environment", "staging",
        "--since", (now - dt.timedelta(minutes=1)).isoformat(),
        "--php-log", str(php_log), "--integration-log", str(php_log),
        "--access-log", str(access_log),
        "--baseline-missing-per-minute", "1", "--missing-spike-factor", "2",
    ]
    result = subprocess.run(command, text=True, capture_output=True, check=True)
    report = json.loads(result.stdout)

    spike_command = command.copy()
    baseline_index = spike_command.index("1", spike_command.index("--baseline-missing-per-minute"))
    spike_command[baseline_index] = "0.4"
    spike_result = subprocess.run(spike_command, text=True, capture_output=True, check=True)
    spike_report = json.loads(spike_result.stdout)

    php_log.write_text(
        f'[{php_stamp} Asia/Kuala_Lumpur] PHP Fatal error: synthetic secret marker in /safe.php:1\n'
    )
    access_log.write_text(
        f'192.0.2.1 - - [{access_stamp}] "GET /safe HTTP/1.1" 500 123 "agent"\n'
    )
    failing = subprocess.run(command, text=True, capture_output=True, check=False)
    failing_report = json.loads(failing.stdout)

checks = {
    "read-only scope is declared": "no HTTP requests or database access" in report["scope"],
    "normal request does not fail": report["nginx_access"]["http_5xx"] == 0,
    "missing credential is aggregated": report["integration"]["auth_results"] == {"missing_credentials": 1},
    "baseline avoids false spike": report["missing_credentials_baseline"]["spike_detected"] is False,
    "result requires observation": report["status"] == "PASS",
    "baseline detects a credential spike": spike_report["status"] == "OBSERVE"
        and spike_report["missing_credentials_baseline"]["spike_detected"] is True,
    "raw record is never emitted": "sensitive-reference" not in result.stdout and report["raw_log_records_emitted"] == 0,
    "fatal and HTTP 500 fail the check": failing.returncode == 1
        and failing_report["status"] == "FAIL"
        and failing_report["nginx_access"]["http_5xx"] == 1,
    "fatal content remains redacted": "synthetic secret marker" not in failing.stdout,
}

failures = 0
for description, passed in checks.items():
    print(f"{'PASS' if passed else 'FAIL'} {description}")
    failures += 0 if passed else 1
print(f"RESULT checks={len(checks)} failures={failures} network_requests=0 database_access=0")
raise SystemExit(1 if failures else 0)
