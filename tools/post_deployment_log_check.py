#!/usr/bin/env python3
"""Read-only, redacted log checks for a completed OneID deployment."""

from __future__ import annotations

import argparse
import collections
import datetime as dt
import gzip
import json
import re
from pathlib import Path
from typing import Iterable, TextIO
from zoneinfo import ZoneInfo


LOCAL_TZ = ZoneInfo("Asia/Kuala_Lumpur")
PHP_TIME = re.compile(r"^\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2})")
ACCESS_TIME_STATUS = re.compile(
    r"\[(\d{2}/[A-Za-z]{3}/\d{4}:\d{2}:\d{2}:\d{2}) ([+-]\d{4})\].*?\"\s+(\d{3})\s+"
)


def arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Aggregate post-deployment errors without printing raw log records."
    )
    parser.add_argument("--environment", choices=("staging", "production"), default="staging")
    parser.add_argument(
        "--since",
        help="ISO-8601 cutoff with timezone; defaults to 15 minutes before execution",
    )
    parser.add_argument("--php-log", action="append", default=[], help="PHP log path or glob")
    parser.add_argument(
        "--integration-log", action="append", default=[], help="Integration audit log path or glob"
    )
    parser.add_argument("--access-log", action="append", default=[], help="Nginx access log path or glob")
    parser.add_argument(
        "--baseline-missing-per-minute",
        type=float,
        help="Optional known baseline used to identify a missing_credentials spike",
    )
    parser.add_argument("--missing-spike-factor", type=float, default=2.0)
    return parser.parse_args()


def cutoff(value: str | None) -> dt.datetime:
    if value is None:
        return dt.datetime.now(LOCAL_TZ) - dt.timedelta(minutes=15)
    parsed = dt.datetime.fromisoformat(value)
    if parsed.tzinfo is None:
        raise SystemExit("STOP: --since must include a timezone offset")
    return parsed.astimezone(LOCAL_TZ)


def paths(patterns: Iterable[str]) -> list[Path]:
    found: set[Path] = set()
    for pattern in patterns:
        candidate = Path(pattern)
        if any(character in pattern for character in "*?["):
            found.update(candidate.parent.glob(candidate.name))
        elif candidate.exists():
            found.add(candidate)
    return sorted(found, key=lambda item: str(item))


def open_text(path: Path) -> TextIO:
    if path.suffix == ".gz":
        return gzip.open(path, "rt", encoding="utf-8", errors="replace")
    return path.open("rt", encoding="utf-8", errors="replace")


def php_timestamp(line: str) -> dt.datetime | None:
    match = PHP_TIME.search(line)
    if match is None:
        return None
    return dt.datetime.strptime(match.group(1), "%d-%b-%Y %H:%M:%S").replace(tzinfo=LOCAL_TZ)


def inspect_php(logs: list[Path], since: dt.datetime) -> dict[str, object]:
    categories: collections.Counter[str] = collections.Counter()
    readable = 0
    unreadable = 0

    for log in logs:
        try:
            stream = open_text(log)
        except (OSError, PermissionError):
            unreadable += 1
            continue
        readable += 1
        with stream:
            for line in stream:
                when = php_timestamp(line)
                if when is None or when < since:
                    continue
                lowered = line.lower()
                if "php fatal error" in lowered or "php parse error" in lowered or "uncaught " in lowered:
                    categories["fatal_or_uncaught"] += 1
                elif "php warning" in lowered:
                    categories["warning"] += 1
                elif "php deprecated" in lowered:
                    categories["deprecated"] += 1

    return {
        "files_found": len(logs),
        "files_readable": readable,
        "files_unreadable": unreadable,
        "categories": dict(sorted(categories.items())),
    }


def inspect_integration(logs: list[Path], since: dt.datetime) -> dict[str, object]:
    categories: collections.Counter[str] = collections.Counter()
    auth_results: collections.Counter[str] = collections.Counter()
    missing_minutes: collections.Counter[str] = collections.Counter()
    readable = 0
    unreadable = 0
    records = 0
    for log in logs:
        try:
            stream = open_text(log)
        except (OSError, PermissionError):
            unreadable += 1
            continue
        readable += 1
        with stream:
            for line in stream:
                marker = line.find('{"component":"oneid_integration"')
                if marker < 0:
                    continue
                try:
                    record = json.loads(line[marker:])
                except json.JSONDecodeError:
                    categories["invalid_integration_audit_record"] += 1
                    continue
                recorded_at = record.get("recorded_at")
                if isinstance(recorded_at, str):
                    try:
                        when = dt.datetime.fromisoformat(recorded_at).astimezone(LOCAL_TZ)
                    except ValueError:
                        when = None
                else:
                    when = php_timestamp(line)
                if when is None or when < since:
                    continue
                records += 1
                result = str(record.get("auth_result") or "unspecified")
                auth_results[result] += 1
                event = str(record.get("event") or "")
                if event in {"rate_limit_exceeded", "rate_limit_unavailable"}:
                    categories[event] += 1
                if result == "missing_credentials":
                    missing_minutes[when.strftime("%Y-%m-%dT%H:%M")] += 1
    return {
        "files_found": len(logs),
        "files_readable": readable,
        "files_unreadable": unreadable,
        "records": records,
        "categories": dict(sorted(categories.items())),
        "auth_results": dict(sorted(auth_results.items())),
        "missing_credentials_peak_per_minute": max(missing_minutes.values(), default=0),
    }


def inspect_access(logs: list[Path], since: dt.datetime) -> dict[str, object]:
    statuses: collections.Counter[str] = collections.Counter()
    readable = 0
    unreadable = 0
    for log in logs:
        try:
            stream = open_text(log)
        except (OSError, PermissionError):
            unreadable += 1
            continue
        readable += 1
        with stream:
            for line in stream:
                match = ACCESS_TIME_STATUS.search(line)
                if match is None:
                    continue
                when = dt.datetime.strptime(
                    f"{match.group(1)} {match.group(2)}", "%d/%b/%Y:%H:%M:%S %z"
                ).astimezone(LOCAL_TZ)
                if when >= since:
                    statuses[match.group(3)] += 1
    return {
        "files_found": len(logs),
        "files_readable": readable,
        "files_unreadable": unreadable,
        "status_counts": dict(sorted(statuses.items())),
        "http_5xx": sum(count for status, count in statuses.items() if status.startswith("5")),
    }


def main() -> int:
    args = arguments()
    since = cutoff(args.since)
    root = "/var/www/oneid" if args.environment == "production" else "/var/www/oneid-uat"
    php_patterns = args.php_log or [f"{root}/storage/logs/php-error.log*"]
    integration_patterns = args.integration_log or [
        f"{root}/storage/logs/integration-audit.log*",
        # Transitional support for records written to php-error.log before separation.
        f"{root}/storage/logs/php-error.log*",
    ]
    access_patterns = args.access_log or [
        "/var/log/nginx/oneid.access.log*"
        if args.environment == "production"
        else "/var/log/nginx/oneid-uat.access.log*"
    ]
    php = inspect_php(paths(php_patterns), since)
    integration = inspect_integration(paths(integration_patterns), since)
    access = inspect_access(paths(access_patterns), since)

    fail_reasons: list[str] = []
    observations: list[str] = []
    if int(access["http_5xx"]) > 0:
        fail_reasons.append("http_5xx_present")
    if int(dict(php["categories"]).get("fatal_or_uncaught", 0)) > 0:
        fail_reasons.append("php_fatal_or_uncaught_present")
    for category in ("warning", "deprecated"):
        if int(dict(php["categories"]).get(category, 0)) > 0:
            observations.append(f"{category}_present")
    for category in ("rate_limit_exceeded", "rate_limit_unavailable"):
        if int(dict(integration["categories"]).get(category, 0)) > 0:
            observations.append(f"{category}_present")
    if int(php["files_readable"]) == 0:
        observations.append("php_log_unavailable")
    if int(access["files_readable"]) == 0:
        observations.append("access_log_unavailable")
    if int(integration["files_readable"]) == 0:
        observations.append("integration_log_unavailable")

    missing = int(dict(integration["auth_results"]).get("missing_credentials", 0))
    peak = int(integration["missing_credentials_peak_per_minute"])
    spike = False
    if args.baseline_missing_per_minute is not None:
        threshold = args.baseline_missing_per_minute * args.missing_spike_factor
        spike = peak > threshold
        if spike:
            observations.append("missing_credentials_spike")
    elif missing > 0:
        observations.append("missing_credentials_present_without_baseline")

    status = "FAIL" if fail_reasons else ("OBSERVE" if observations else "PASS")
    report = {
        "status": status,
        "environment": args.environment,
        "checked_at": dt.datetime.now(LOCAL_TZ).isoformat(timespec="seconds"),
        "since": since.isoformat(timespec="seconds"),
        "scope": "read-only log aggregation; no HTTP requests or database access",
        "php": php,
        "integration": integration,
        "nginx_access": access,
        "missing_credentials_baseline": {
            "per_minute": args.baseline_missing_per_minute,
            "spike_factor": args.missing_spike_factor,
            "spike_detected": spike,
        },
        "fail_reasons": fail_reasons,
        "observations": observations,
        "raw_log_records_emitted": 0,
    }
    print(json.dumps(report, indent=2, sort_keys=True))
    return 1 if status == "FAIL" else 0


if __name__ == "__main__":
    raise SystemExit(main())
