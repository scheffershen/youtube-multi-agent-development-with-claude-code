# D3: May the tester amend four more U5SlaReportTest methods (AC1, AC2, AC4, AC10) that pin "exactly three report rows", now that the empty report has a fourth row for urgent?

**Item:** R2   **Role:** developer and tester (both raised it independently)   **Date:** 2026-10-14
**Why it is a decision:** an existing test's assertions would change beyond what the spec explicitly lists; Required behaviour 8 names exactly four amendments and calls any other failing test an implementation defect, but Required behaviour 5 / AC5 (URS-F-05.2, D2) mandates an urgent row even at total 0, so the two contradict.

| Option | Consequence |
|--------|-------------|
| A. Amend the four U5 methods minimally | assertCount(3) -> 4 at three places; testAc2's literal three-row array gains a trailing urgent row with zeros. Implementation untouched; suite green; spec RB8/AC10 extended to record these four as amended by decision. |
| B. Leave them failing as RB8 literally says | Verifier FAIL with no possible fix in src/ (SlaReport is out of scope and AC5 requires the row); item blocked until the spec is rewritten. |
| C. Emit the urgent row only when its total is above zero | Contradicts AC5, URS-F-05.2 and D2; touches SlaReport (out of scope); row count becomes data-dependent for API clients. |

**Recommendation:** A (both roles) - the extra failures are the direct consequence of D2 and URS-F-05.2; the spec under-counted the hard-coded "three rows" assertions.
**Work so far:** developer: Ticket::PRIORITIES and SLA_TARGET_HOURS updated, nothing else in src/, no migration; simulator 200:6 201:3 404:1 422:2, no 5xx. Tester: tests/Api/R2UrgentPriorityTest.php (9 methods) and the four listed amendments done; suite 126 tests, 4 failures, all the U5 three-row assertions.
**Decision:**
A by Yi Shen on 2026-10-14 (chat, Claude Code Desktop) - amend the four U5 three-row assertions
