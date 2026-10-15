#!/usr/bin/env bash
# Smoke check for the verifier (check 3) and the validation recorder, for projects that
# have no traffic simulator. Drives a running server with read-only HTTP probes that must
# answer a known status and never a 5xx, then counts the CRITICAL records appended to a
# log file while it ran.
#
# Probes come from .claude/scripts/smoke-probes.txt, one per line:
#   <expected status> <METHOD> <path> [extra curl arguments]
# Lines starting with # are ignored. "$$" in a path is replaced by the script's pid, so a
# probe such as  404 GET /does-not-exist-$$  never hits a cached page.
#
# Usage:  bash .claude/scripts/smoke.sh [--base https://host] [--resolve host:ip]
#                                       [--log var/log/dev.log] [--probes file] [--insecure]
# Env:    SMOKE_BASE_URL overrides the default base (http://127.0.0.1:8000).
# Exit:   0 = every probe answered as expected and no new CRITICAL
#         1 = a probe answered wrongly or a CRITICAL appeared
#         2 = the server is not reachable (nothing was checked; not a verdict)
#
# Read-only: no login, no request that changes data, nothing written outside stdout.

set -u
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
BASE="${SMOKE_BASE_URL:-http://127.0.0.1:8000}"
LOG="${ROOT}/var/log/dev.log"
PROBES="${ROOT}/.claude/scripts/smoke-probes.txt"
EXTRA=""
while [ $# -gt 0 ]; do
  case "$1" in
    --base)     BASE="$2"; shift 2 ;;
    --resolve)  host="${2%%:*}"; ip="${2#*:}"
                EXTRA="$EXTRA --resolve ${host}:443:${ip} --resolve ${host}:80:${ip}"; shift 2 ;;
    --log)      case "$2" in /*|?:*) LOG="$2" ;; *) LOG="${ROOT}/$2" ;; esac; shift 2 ;;
    --probes)   PROBES="$2"; shift 2 ;;
    --insecure) EXTRA="$EXTRA -k"; shift ;;
    *) echo "unknown option $1" >&2; exit 2 ;;
  esac
done
CURL="curl -s -o /dev/null --max-time 30 $EXTRA"

[ -f "$PROBES" ] || { echo "no probes file at ${PROBES}"; exit 2; }

# 1. Reachability: the first probe's path must answer at all.
first=$(grep -vE '^\s*(#|$)' "$PROBES" | head -1 | awk '{print $3}')
code=$($CURL -w '%{http_code}' "${BASE}${first//\$\$/$$}" || true)
if [ -z "$code" ] || [ "$code" = "000" ]; then
  echo "server not reachable at ${BASE}; start it first"
  exit 2
fi

before=0
[ -f "$LOG" ] && before=$(wc -l < "$LOG")

# 2. Probes.
fail=0
while read -r want method path extra; do
  case "$want" in ''|'#'*) continue ;; esac
  path="${path//\$\$/$$}"
  # shellcheck disable=SC2086
  got=$($CURL -w '%{http_code}' -X "$method" $extra "${BASE}${path}" || echo 000)
  if [ "$got" = "$want" ]; then
    echo "OK  ${want} ${got} ${method} ${path}"
  else
    echo "KO  ${want} ${got} ${method} ${path}"; fail=1
  fi
done < "$PROBES"

# 3. New CRITICAL records while the probes ran: only lines appended after `before` count.
if [ -f "$LOG" ]; then
  after=$(wc -l < "$LOG")
  new=$(tail -n +"$((before + 1))" "$LOG" | grep -c '\.CRITICAL:' || true)
  if [ "$new" -gt 0 ]; then
    echo "KO  ${new} new CRITICAL record(s) in ${LOG#"$ROOT"/} (lines $((before + 1))-${after}):"
    tail -n +"$((before + 1))" "$LOG" | grep '\.CRITICAL:' | cut -c1-220
    fail=1
  else
    echo "OK  no new CRITICAL in ${LOG#"$ROOT"/}"
  fi
else
  echo "OK  no log at ${LOG#"$ROOT"/} (nothing was logged at CRITICAL)"
fi

[ "$fail" -eq 0 ] && echo "SMOKE PASS" || echo "SMOKE FAIL"
exit "$fail"
