"""Record a red baseline for an /improve round from a saved PHPUnit output.

Usage:
  python -I .claude/scripts/record_baseline.py <phpunit-output.txt> <round> \
      [--command "..."] [--accepted-by "Name, date"] [--note "..."] [--out reports/baseline-round-<round>.md]
  python -I .claude/scripts/record_baseline.py --selftest

Reads the numbered entries PHPUnit prints under "There were N errors/failures:", one line
per failing test with the first line of its message, and writes reports/baseline-round-N.md
with a summary line, the failure kinds, the classes and the full list. The skill's Step 0
accepts a red suite only when this file exists and the approved round file cites it; the
verifier then passes an item only if every failing test is in the list.

Pure standard library, no network, reads only the file given.
"""
import argparse, collections, pathlib, re, sys

SAMPLE = """PHPUnit 11.0

There were 2 errors:

1) App\\Tests\\Foo\\BarTest::testOne
Doctrine\\DBAL\\Exception\\InvalidFieldNameException: Unknown column 't0.logo_uri' in 'field list'

/var/www/html/tests/Foo/BarTest.php:12

2) App\\Tests\\Foo\\BarTest::testTwo with data set #3 ('2024-05-09', 0)
Error: Typed property App\\Tests\\Foo\\BarTest::$manager must not be accessed before initialization

There was 1 failure:

1) App\\Tests\\Utils\\UtilsTest::testHoliday
Failed asserting that 2024-05-09 is correctly handled as holiday

ERRORS!
Tests: 10, Assertions: 20, Errors: 2, Failures: 1.
"""


def normalise(message: str) -> str:
    message = re.sub(r"\d{4}-\d{2}-\d{2}", "<date>", message)
    message = re.sub(r"Unknown column '[^']+'", "Unknown column <c>", message)
    message = re.sub(r"Table '[^']+'", "Table <t>", message)
    return message.strip()[:150]


def parse(output: str):
    summary = re.search(r"^(Tests: \d+.*)$", output, re.M)
    entries = re.findall(r"^(\d+)\) (\S[^\n]*)\n((?:(?!\n\n).)*)", output, re.M | re.S)
    rows = []
    for _, test, body in entries:
        first = next((l for l in body.splitlines() if l.strip() and not l.startswith("/")), "")
        rows.append((test.strip(), normalise(first)))
    return (summary.group(1) if summary else "summary line not found"), rows


def render(summary, rows, round_no, command, accepted_by, note):
    kinds = collections.Counter(k for _, k in rows)
    classes = collections.Counter(t.split("::")[0].strip() for t, _ in rows)
    lines = [f"# Recorded red baseline for improve round {round_no}", "",
             f"Command: `{command}`", f"Accepted by: {accepted_by}"]
    if note:
        lines += [f"Note: {note}"]
    lines += ["", f"**Summary line:** `{summary}`", "", "## Rule", "",
              "An item PASSes only if every failing test after its change is in the list below. A listed",
              "test inside the item's scope must pass after the change. A listed test that fails for a",
              "new reason after an item, or that now passes, is reported by the verifier and becomes a",
              "decision for a person; on acceptance the orchestrator writes the next round's baseline file.",
              "", "## Failure kinds", "", "| Count | First line of the failure |", "|------:|---------------------------|"]
    lines += [f"| {c} | {k} |" for k, c in kinds.most_common()]
    lines += ["", "## Test classes", "", "| Count | Class |", "|------:|-------|"]
    lines += [f"| {c} | `{k}` |" for k, c in classes.most_common()]
    lines += ["", f"## Failing tests ({len(rows)})", ""]
    lines += [f"- `{t}`: {k}" for t, k in rows]
    return "\n".join(lines) + "\n"


def main(argv):
    ap = argparse.ArgumentParser()
    ap.add_argument("output", nargs="?")
    ap.add_argument("round", nargs="?", type=int)
    ap.add_argument("--command", default="tools/php vendor/bin/phpunit")
    ap.add_argument("--accepted-by", default="(fill in: name, date, where the acceptance is recorded)")
    ap.add_argument("--note", default="")
    ap.add_argument("--out")
    ap.add_argument("--selftest", action="store_true")
    a = ap.parse_args(argv)
    if a.selftest:
        summary, rows = parse(SAMPLE)
        assert summary.startswith("Tests: 10"), summary
        assert len(rows) == 3, rows
        assert rows[0][1] == "Doctrine\\DBAL\\Exception\\InvalidFieldNameException: Unknown column <c> in 'field list'", rows[0]
        assert rows[1][0].endswith("('<date>', 0)") is False and "data set #3" in rows[1][0], rows[1]
        assert rows[2][1] == "Failed asserting that <date> is correctly handled as holiday", rows[2]
        text = render(summary, rows, 1, "cmd", "tester", "")
        assert "## Failing tests (3)" in text and "| 2 | `App\\Tests\\Foo\\BarTest` |" in text
        print("record_baseline selftest OK")
        return 0
    if not a.output or a.round is None:
        ap.error("output file and round number are required (or --selftest)")
    output = pathlib.Path(a.output).read_text(encoding="utf-8", errors="replace")
    summary, rows = parse(output)
    out = pathlib.Path(a.out or f"reports/baseline-round-{a.round}.md")
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(render(summary, rows, a.round, a.command, a.accepted_by, a.note), encoding="utf-8", newline="\n")
    print(f"{out}: {len(rows)} failing tests recorded; {summary}")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
