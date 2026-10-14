#!/usr/bin/env python3
"""PreToolUse hook: refuse commands that belong to a human.

Claude Code pipes the tool call as JSON on stdin. Exit code 2 blocks the call and shows
stderr to the model, which then reports the gate to the user instead of retrying.
The deny list in .claude/settings.json is the first line of defence; this hook is the
second, and the one that explains why.

Known limits (by design, keep them in mind): this inspects the command text only. It
does not follow scripts, aliases, Makefile targets or composer scripts that themselves
push or deploy, and it cannot see what a PowerShell `Invoke-Expression` builds at run
time. Treat it as a tripwire, not as an access boundary. Real enforcement is the absence
of push credentials and deploy access in the agents' environment.
"""
import json
import re
import shlex
import sys

# git [global options...] <subcommand>: global options such as -C <path>, -c k=v,
# --git-dir=..., --no-pager, --work-tree ... may appear before the subcommand.
GIT_GLOBAL_OPT = r"(?:-[A-Za-z]\s+\S+|-[A-Za-z]\S*|--[A-Za-z-]+(?:=\S+)?(?:\s+\S+)?)"
GIT = rf"\bgit\b(?:\s+{GIT_GLOBAL_OPT})*\s+"

GATED = [
    (re.compile(GIT + r"(push|tag|merge|reset\s+--hard|branch\s+-[dD]|update-ref)\b"),
     "git tag/merge/push/reset --hard/branch -d/update-ref"),
    (re.compile(r"\bgh\s+(pr\s+merge|release\s+create|repo\s+delete)\b"), "GitHub merge/release/delete"),
    (re.compile(r"\b(rsync|scp|sftp|kubectl\s+(apply|rollout|delete)|helm\s+(install|upgrade)|"
                r"docker\s+(push|compose\s+up)|ansible-playbook|cap(istrano)?\s+\S+\s+deploy|deployer|dep\s+deploy)\b"),
     "deployment"),
    (re.compile(r"--env[= ]prod\b|APP_ENV=prod\b"), "a production-environment command"),
    (re.compile(r"\bdoctrine:(migrations:migrate|schema:(update|drop))\b.*\b(prod|production)\b|"
                r"\b(prod|production)\b.*\bdoctrine:(migrations:migrate|schema:(update|drop))\b"),
     "production migration"),
]


def commands_in(text: str) -> list[str]:
    """Split a shell line on separators so 'cd x && git push' is still caught.

    Quoted strings are blanked first so that a commit message or an echo containing the
    words "git push" does not trip the gate; the command word itself is never quoted.
    """
    text = re.sub(r"'[^']*'|\"[^\"]*\"", " _ ", text)
    parts = re.split(r"\s*(?:&&|\|\||;|\||\n)\s*", text)
    return [p for p in parts if p]


try:
    payload = json.load(sys.stdin)
except Exception:
    sys.exit(0)

if payload.get("tool_name") not in ("Bash", "PowerShell"):
    sys.exit(0)

command = str(payload.get("tool_input", {}).get("command", ""))
for part in commands_in(command):
    for pattern, label in GATED:
        if pattern.search(part):
            sys.stderr.write(
                f"HUMAN GATE: {label} is reserved for a person. "
                "Write the request to approvals/pending/ and stop; do not retry this command.\n"
            )
            sys.exit(2)

sys.exit(0)
