#!/usr/bin/env python3
"""Executable check for human-gate.py. Run: python .claude/hooks/test_human_gate.py"""
import json
import subprocess
import sys
from pathlib import Path

HOOK = Path(__file__).with_name("human-gate.py")

BLOCKED = [
    "git push",
    "git push origin main",
    "git -C /tmp/x push",
    "git --no-pager push --force",
    "git -c user.name=x push",
    "git --git-dir=.git --work-tree=. tag v1",
    "git tag v0.3.0",
    "git merge build/urs-002",
    "git reset --hard HEAD~1",
    "git branch -D main",
    "cd ticketflow && git push",
    "tools/php vendor/bin/phpunit; git push",
    "gh pr merge 12",
    "gh release create v1",
    "APP_ENV=prod tools/php bin/console cache:clear",
    "tools/php bin/console doctrine:migrations:migrate -n --env=prod",
    "tools/php bin/console doctrine:migrations:migrate -n --env prod",
    "kubectl apply -f deploy.yaml",
    "docker compose up -d",
    "rsync -av . user@host:/srv/app",
]
ALLOWED = [
    "git status",
    "git diff --stat",
    "git log --oneline",
    "git add -A",
    "git commit -m 'x'",
    "git checkout -b build/urs-003",
    "git stash push -u -m spec",
    "git show 5441fa6 -- src/",
    "tools/php vendor/bin/phpunit",
    "tools/php bin/console app:simulate-traffic --reset",
    "tools/php bin/console doctrine:migrations:migrate -n --env=test",
    "grep -n pushState src/app.js",
    "echo 'do not git push here' > notes.txt",
]


def run(cmd: str) -> int:
    payload = json.dumps({"tool_name": "Bash", "tool_input": {"command": cmd}})
    return subprocess.run([sys.executable, str(HOOK)], input=payload, text=True,
                          capture_output=True).returncode


failures = []
for cmd in BLOCKED:
    if run(cmd) != 2:
        failures.append(f"should block: {cmd}")
for cmd in ALLOWED:
    if run(cmd) != 0:
        failures.append(f"should allow: {cmd}")

if failures:
    print("\n".join(failures))
    sys.exit(1)
print(f"human-gate OK: {len(BLOCKED)} blocked, {len(ALLOWED)} allowed")
