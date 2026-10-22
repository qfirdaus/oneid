#!/usr/bin/env python3
"""Build a deterministic candidate from the clean, pushed main commit."""

import argparse
import hashlib
import io
import json
import os
import re
import subprocess
import tarfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

def git(*args: str) -> bytes:
    return subprocess.check_output(["git", *args], cwd=ROOT)

def sha(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()

def excluded(name: str) -> bool:
    path = Path(name)
    return (
        any(part in path.parts for part in (".git", ".private", "vendor", "vendors", "node_modules", "__pycache__"))
        or name.endswith((".pyc", ".log"))
        or name.startswith(("storage/runtime/", "storage/backups/", "storage/quarantine/"))
    )

def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()
    output = args.output.resolve()
    if output == ROOT or ROOT in output.parents:
        raise SystemExit("Output must be outside source worktree")
    if output.exists():
        raise SystemExit("Refusing to overwrite existing package directory")
    status = git("status", "--porcelain=v1", "--untracked-files=all")
    if status:
        raise SystemExit("Refusing to package a dirty worktree; commit and review every file first")
    branch = git("branch", "--show-current").decode().strip()
    commit = git("rev-parse", "HEAD").decode().strip()
    remote_commit = git("rev-parse", "origin/main").decode().strip()
    if branch != "main" or commit != remote_commit:
        raise SystemExit("Candidate must be built from main at the cached origin/main commit")

    tracked = [name for name in git("ls-files", "-z").decode().split("\0") if name]
    files: dict[str, bytes] = {}
    entries: list[dict[str, object]] = []
    omitted: list[str] = []
    for name in sorted(tracked):
        if excluded(name):
            omitted.append(name)
            continue
        path = ROOT / name
        if path.is_symlink():
            raise SystemExit("Review symlink before packaging: " + name)
        if not path.is_file():
            raise SystemExit("Tracked file is missing: " + name)
        data = path.read_bytes()
        if re.search(rb"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----\r?\n[A-Za-z0-9+/=]{20}", data):
            raise SystemExit("Private key requires exclusion/review: " + name)
        files[name] = data
        entries.append({
            "path": name, "change": "committed", "included": True,
            "bytes": len(data), "sha256": sha(data),
            "mode": "0755" if os.access(path, os.X_OK) else "0644",
        })
    application = (ROOT / "config/application.php").read_text(encoding="utf-8")
    version_match = re.search(r"ONEID_APP_VERSION', '([^']+)'", application)
    if version_match is None:
        raise SystemExit("Unable to determine OneID version")
    manifest = {
        "format": 2, "release": "oneid-" + version_match.group(1),
        "status": "PREPARED_NOT_APPROVED_FOR_DEPLOYMENT", "base_commit": commit,
        "branch": branch, "source_tree": str(ROOT),
        "scope": ["committed OneID application source", "PHP 8.4 production runtime"],
        "default_cli_php": "8.4", "production_changed": False, "git_pushed": True,
        "excluded_paths": omitted,
        "exclusions": [
            "private configuration and secrets", "vendor and legacy vendors",
            "ignored uploads, diagnostic content and runtime data",
        ],
        "files": entries,
    }
    output.mkdir(parents=True, mode=0o700)
    manifest_bytes = (json.dumps(manifest, indent=2, ensure_ascii=False) + "\n").encode()
    (output / "git-manifest.json").write_bytes(manifest_bytes)
    (output / "tracked-changes.patch").write_bytes(b"")
    (output / "git-status.txt").write_bytes(status)
    archive_path = output / "oneid-production-candidate.tar"
    with tarfile.open(archive_path, "w", format=tarfile.PAX_FORMAT) as archive:
        for name, data in sorted({**files, "RELEASE-MANIFEST.json": manifest_bytes}.items()):
            info = tarfile.TarInfo(name)
            info.size = len(data)
            info.mtime = 0
            info.uid = info.gid = 0
            info.mode = 0o755 if name in files and os.access(ROOT / name, os.X_OK) else 0o644
            archive.addfile(info, io.BytesIO(data))
    sums = []
    for path in sorted(output.iterdir()):
        if path.is_file():
            sums.append(sha(path.read_bytes()) + "  " + path.name)
    (output / "SHA256SUMS").write_text("\n".join(sums) + "\n", encoding="utf-8")
    print(json.dumps({
        "output": str(output), "version": version_match.group(1), "commit": commit,
        "files": len(files), "archive_sha256": sha(archive_path.read_bytes()),
        "note": "Built from clean pushed main; no deployment performed.",
    }, indent=2))

if __name__ == "__main__":
    main()
