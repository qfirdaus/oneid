#!/usr/bin/env python3
"""Stage an exact reviewed OneID release while preserving production-owned data."""

import argparse
import datetime
import hashlib
import json
import os
import re
import subprocess
import tarfile
from pathlib import Path

LIVE = Path("/var/www/oneid")
CANDIDATE_ROOT = Path("/home/iqs/oneid-release-candidates")
PRESERVED_PATHS = (
    ".git/", ".private/", "storage/", "vendors/", "public_img/", "public_docs/",
    "public/videos/", "public/login_banners/", "public/diagnostic/", "videos/",
)

def run(args: list[str]) -> str:
    return subprocess.check_output(args, text=True, stderr=subprocess.STDOUT)

def production_status_is_safe() -> bool:
    records = subprocess.check_output([
        "git", "-C", str(LIVE), "status", "--porcelain=v1", "-z", "--untracked-files=all",
    ]).decode("utf-8", errors="replace").split("\0")
    return all(record.startswith("?? public/diagnostic/") for record in records if record)

def candidate_manifest(candidate: Path, expected_commit: str) -> dict[str, object]:
    try:
        manifest = json.loads((candidate / "RELEASE-MANIFEST.json").read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        raise RuntimeError("candidate release manifest is missing or invalid") from error
    if manifest.get("base_commit") != expected_commit:
        raise RuntimeError("candidate commit does not match --expected-commit")
    if manifest.get("branch") != "main" or manifest.get("git_pushed") is not True:
        raise RuntimeError("candidate must be built from pushed main")
    if manifest.get("format") != 2 or not isinstance(manifest.get("files"), list):
        raise RuntimeError("candidate manifest format is not supported")
    return manifest

def validate_candidate_files(candidate: Path, manifest: dict[str, object]) -> None:
    expected = {
        entry["path"]: entry
        for entry in manifest["files"]
        if isinstance(entry, dict) and entry.get("included") is True
    }
    actual: set[str] = set()
    for path in candidate.rglob("*"):
        relative = path.relative_to(candidate).as_posix()
        if relative == "RELEASE-MANIFEST.json" or relative == "vendor" or relative.startswith("vendor/"):
            continue
        if path.is_symlink():
            raise RuntimeError("candidate contains a symlink outside vendor")
        if not path.is_file():
            continue
        actual.add(relative)
        entry = expected.get(relative)
        if entry is None:
            raise RuntimeError("candidate contains an unexpected file: " + relative)
        data = path.read_bytes()
        if len(data) != entry.get("bytes") or hashlib.sha256(data).hexdigest() != entry.get("sha256"):
            raise RuntimeError("candidate file digest mismatch: " + relative)
    if actual != set(expected):
        raise RuntimeError("candidate is missing one or more manifest files")

def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--candidate", required=True, type=Path)
    parser.add_argument("--expected-commit", required=True)
    parser.add_argument("--apply", action="store_true")
    args = parser.parse_args()

    if os.geteuid() != 0:
        raise RuntimeError("run with sudo")
    if re.fullmatch(r"[0-9a-f]{40}", args.expected_commit) is None:
        raise RuntimeError("--expected-commit must be a full lowercase Git SHA")
    if LIVE.resolve() != Path("/var/www/oneid") or not (LIVE / "public/index.php").is_file():
        raise RuntimeError("production root guard")
    candidate = args.candidate.resolve()
    if candidate.parent != CANDIDATE_ROOT.resolve():
        raise RuntimeError("candidate must be one direct child of the approved candidate root")
    if not candidate.is_dir() or not (candidate / "vendor/autoload.php").is_file():
        raise RuntimeError("candidate/vendor missing")
    manifest = candidate_manifest(candidate, args.expected_commit)
    validate_candidate_files(candidate, manifest)
    if not production_status_is_safe():
        raise RuntimeError("live Git tree contains changes outside public/diagnostic")
    if run(["readlink", "-f", "/usr/bin/php"]).strip() != "/usr/bin/php8.4":
        raise RuntimeError("default PHP must be PHP 8.4")
    nginx_files = [path for path in Path("/etc/nginx").rglob("*") if path.is_file() and os.access(path, os.R_OK)]
    if not any("oneid-web-prod84.sock" in path.read_text(errors="ignore") for path in nginx_files):
        raise RuntimeError("OneID production is not routed to the approved PHP 8.4 pool")
    if not args.apply:
        print("CHECK PASS: exact pushed candidate, production tree, PHP 8.4 and routing guards passed; no files changed.")
        return

    stamp = datetime.datetime.now().strftime("%Y%m%d-%H%M%S")
    backup = Path("/var/backups/oneid-code-" + stamp)
    backup.mkdir(mode=0o700)
    def source_filter(member: tarfile.TarInfo) -> tarfile.TarInfo | None:
        protected = ("public/diagnostic", "public/videos", "public/login_banners")
        return None if any(member.name == path or member.name.startswith(path + "/") for path in protected) else member
    with tarfile.open(backup / "source-vendor.tar", "w") as archive:
        for name in [
            "app", "admin", "bootstrap", "config", "cron", "index.php", "api.php", "lib",
            "page", "public", "tools", "vendor", "vendors", "composer.json", "composer.lock",
        ]:
            path = LIVE / name
            if path.exists():
                archive.add(path, arcname=name, recursive=True, filter=source_filter)
    with tarfile.open(backup / "private-runtime.tar", "w") as archive:
        for name in [
            ".private", "storage", "public_img", "public_docs", "public/videos",
            "public/login_banners", "public/diagnostic",
        ]:
            path = LIVE / name
            if path.exists():
                archive.add(path, arcname=name, recursive=True)
    (backup / "before.txt").write_text(
        run(["git", "-C", str(LIVE), "rev-parse", "HEAD"])
        + run(["git", "-C", str(LIVE), "status", "--short"]), encoding="utf-8",
    )
    rsync = ["rsync", "-a", "--delete"]
    rsync.extend(f"--exclude={path}" for path in PRESERVED_PATHS)
    rsync.extend([str(candidate) + "/", str(LIVE) + "/"])
    subprocess.run(rsync, check=True)
    subprocess.run([
        "rsync", "-a", "--delete", str(candidate / "vendor") + "/", str(LIVE / "vendor") + "/",
    ], check=True)
    subprocess.run(["chown", "-R", "iqs:www-data", str(LIVE / "vendor")], check=True)
    subprocess.run(["find", str(LIVE / "vendor"), "-type", "d", "-exec", "chmod", "750", "{}", "+"], check=True)
    run(["php8.4", "-l", str(LIVE / "index.php")])
    result = {
        "status": "STAGED_CODE_VENDOR", "candidate": str(candidate),
        "expected_commit": args.expected_commit, "manifest_commit": manifest["base_commit"],
        "backup": str(backup), "preserved_paths": list(PRESERVED_PATHS),
        "nginx_changed": False, "database_changed": False, "cron_changed": False,
    }
    (backup / "result.json").write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(result, indent=2))
    print("NEXT: run PHP 8.4 smoke checks and the read-only post-deployment log check.")

if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        raise SystemExit("STOP: " + str(error) + "; inspect backup/status before retry.")
