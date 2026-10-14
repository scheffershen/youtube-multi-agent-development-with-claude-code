#!/usr/bin/env python3
"""PreToolUse hook: refuse commands that belong to a human.

Claude Code pipes the tool call as JSON on stdin. Exit code 2 blocks the call and shows
stderr to the model, which then reports the gate to the user instead of retrying.
The deny list in .claude/settings.json is the first line of defence; this hook is the
second, and the one that explains why.
"""
import json
import re
import sys

GATED = [
    (r"\bgit\s+(tag|merge|push|reset\s+--hard)\b", "git tag/merge/push/reset --hard"),
    (r"\bgh\s+pr\s+merge\b", "merging a pull request"),
    (r"\b(deploy|rsync|scp|kubectl\s+apply|docker\s+(push|compose\s+up))\b", "deployment"),
    (r"doctrine:migrations:migrate\b(?!.*--env=test).*\bprod\b", "production migration"),
]

try:
    payload = json.load(sys.stdin)
except Exception:
    sys.exit(0)

if payload.get("tool_name") not in ("Bash", "PowerShell"):
    sys.exit(0)

command = str(payload.get("tool_input", {}).get("command", ""))
for pattern, label in GATED:
    if re.search(pattern, command):
        sys.stderr.write(
            f"HUMAN GATE: {label} is reserved for a person. "
            "Write the request to approvals/pending/ and stop; do not retry this command.\n"
        )
        sys.exit(2)

sys.exit(0)
