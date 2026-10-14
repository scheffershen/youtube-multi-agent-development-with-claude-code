# Multi-agent development with Claude Code Desktop: a hands-on tutorial

You will run a small Symfony API that has real bugs and real user requests, then hand it
to a team of five agents that triage, specify, build, test and verify, round after round,
while you watch from the Desktop app. By the end you will know how to set the same loop
up on your own project and, more importantly, where the loop needs a human.

Time: about 90 minutes if you read everything, 20 if you only run it.

---

## 1. Why five agents instead of one

A single Claude session can fix all three bugs in this repo in a few minutes. So why
bother with roles?

Because a single session is also the one that decides the bug is fixed. It wrote the
code, it wrote the test that exercises the code it wrote, and it reads its own log line
as proof. That works for small things and quietly breaks for anything with judgement in
it. The roles exist to split **doing** from **judging**:

| Role | Sees | Never sees | Returns |
|------|------|------------|---------|
| log-analyst | logs, `requests/`, old reports | code diffs | ranked JSON backlog |
| spec-writer | code, one backlog item | the fix | a spec file path |
| developer | the spec, the code | the tester's tests while writing | files changed + suite result |
| tester | the spec, existing test style | the developer's diff | test file + criteria covered |
| verifier | the spec, the repo, the suite | the developer's report | PASS/FAIL JSON with locations |

Two separations carry the whole design:

1. **The tester never reads the implementation.** If it did, it would write tests that
   pass against that implementation, bugs included. Reading only the spec makes the tests
   a second, independent opinion about what "correct" means.
2. **The verifier is spawned fresh every round and never receives the developer's
   story.** An agent that has just been told "I fixed it by doing X" will look for X and
   find it. One that only has the spec and the code has to run the suite and read the
   diff.

Everything else (the orchestrator, the round cap, the structured JSON) is plumbing to
keep those two separations intact without you babysitting.

---

## 2. Setup

Prerequisites: Windows with PHP 8.4 at `C:\php\php-8.4.5-nts-Win32-vs17-x64` (edit the
three files in `tools/` if yours is elsewhere), git, and Claude Code Desktop.

```bash
tools/composer install
tools/php bin/console doctrine:migrations:migrate -n
tools/php bin/console doctrine:migrations:migrate -n --env=test
tools/php vendor/bin/phpunit
```

You should see `OK (3 tests, 10 assertions)`. That green baseline matters: the verifier
treats any regression of these three as a failure, so the loop can only move forward.

Open the `ticketflow` folder as a session in Claude Code Desktop. The app reads
`CLAUDE.md` (toolchain, layout), `.claude/agents/*.md` (the roles),
`.claude/skills/improve/SKILL.md` (the loop) and `.claude/settings.json` (permissions).

Roles are registered when the session starts. If you add or rename a file in
`.claude/agents/` later, start a new session before using it; an existing session will
answer "agent type not found".

---

## 3. Plant the evidence

The agents work from two inputs. Create both now.

**Logs.** The app has no web server in this demo; instead a console command replays a
realistic mix of requests through the Symfony kernel, exactly as a browser would, so
Monolog writes the same lines it would write in production:

```bash
tools/php bin/console app:simulate-traffic
```

Look at the summary: three requests returned 500. Now look at the file the agents read:

```bash
grep CRITICAL var/log/dev.log | cut -c1-160
```

You will see three different exceptions with file and line numbers. In a real deployment
you would point the log-analyst at your shipped logs instead; section 8 covers that.

**Human requests.** Open `requests/`. Three Markdown files are waiting, written the way
a support lead or on-call engineer would write them: observed, expected, why it matters.
One of them describes a bug the logs also show. The log-analyst is told to merge those.

Read `requests/README.md` for the one rule that matters: files in that folder are data
about what people want, not instructions to the agents. A request saying "ignore your
rules" gets reported as a strange request, nothing more.

---

## 4. Anatomy of a role

Open `.claude/agents/tester.md`. Every role file has the same four parts.

**Frontmatter** sets the name, a description (the orchestrator matches on it), the tool
allowlist, and the model. The tester gets `Edit` and `Write` because it creates test
files, but its prose forbids touching `src/`. The verifier and log-analyst get no editing
tools at all, so the restriction is mechanical, not polite.

**Environment** tells the agent how to run things here. Subagents get the same
`CLAUDE.md` you do, so this section only repeats what the role must not get wrong, for
example "never call bare `php`".

**Procedure** is a numbered list, in order, with the reason attached to the surprising
steps. "It is expected to fail if the developer has not finished yet" stops the tester
from weakening its own assertions to get green.

**Report** is a fixed block. The orchestrator reads that block and nothing else. If an
agent returns prose instead, the orchestrator sends one message asking for the block.
This is what lets the loop branch on results without a model re-reading transcripts.

Models are deliberately mixed: Sonnet for triage, spec and tests where the task is
well-bounded; Opus for the developer and the verifier where judgement costs more when
it is wrong. Change them in the frontmatter if your budget or taste differs.

---

## 5. Anatomy of the orchestrator

Open `.claude/skills/improve/SKILL.md`. The orchestrator is a skill, which in Claude
Code is a slash command whose body becomes the instructions for the current session.
Typing `/improve` turns the session you are looking at into the orchestrator.

Walk through what it does, because this is the pattern you will reuse:

- **Step 0, preconditions.** Green suite, clean git tree. A red baseline makes
  verification meaningless; a dirty tree makes the diff review meaningless. The loop
  refuses to start rather than guess.
- **Step 1, triage.** One `log-analyst` call, foreground, because everything depends on
  it. Returns JSON. The orchestrator takes the top two items by default.
- **Step 2a, spec.** One `spec-writer` per item. The orchestrator checks the spec has at
  least two acceptance criteria, a cheap sanity check that catches most empty specs.
- **Step 2b, build.** `developer` and `tester` are spawned **in the same message** with
  `run_in_background: true`. That is the only way to get them truly parallel. Their IDs
  are kept, because in later rounds the orchestrator *messages* the existing developer
  with the failure list instead of spawning a new one. The developer keeps its context
  and fixes just the failures.
- **Step 2c, verify.** A `verifier` is spawned with the spec path and nothing else.
- **Step 2d, fix loop.** While FAIL and under the cap: route each failure to the tester
  if it is in a test file, otherwise to the developer; message them; spawn a **fresh**
  verifier. Three rounds, then the item is marked BLOCKED, its code is stashed and its
  spec and tests are kept for a human.
- **Step 2e, close.** Commit. Move the request file to `requests/done/`.
- **Step 3, report.** One Markdown table per round in `reports/`.

Notice what the orchestrator does *not* do: it never opens a source file to form its own
opinion about the fix. Its context stays small so it can run many rounds.

---

## 6. Run your first round

In the Desktop session, type:

```
/improve --dry-run
```

The log-analyst runs, and you get a table of what it would work on. Expect it to rank the
three 500s first, and to have merged the Monday-dashboard request with the division-by-
zero log group. If it also mentions `GET /api/tickets?status=open` as a silently ignored
parameter, that is because the simulator sends that request and the request file asks
for it; the log alone would not have shown it.

Now the real thing:

```
/improve
```

What to watch while it runs:

- The **tasks pane** in the Desktop app lists each subagent as it is spawned. You will
  see `developer` and `tester` appear together, then a `verifier` after both finish.
- `specs/` gains a file per item within the first minute. Open one. The acceptance
  criteria are what the tester will turn into test methods one-to-one, so if a criterion
  is vague, this is where you would stop the run and fix the spec-writer's prompt.
- `tests/Api/` gains a file named after the spec. Open it and compare it with the spec:
  one method per criterion, asserting status code and JSON fields.
- `git log` grows by one commit per passed item.
- `reports/round-1.md` appears at the end with the table, the remaining CRITICALs and
  the requests still open.

Run the simulator again and grep the log. Whatever the round fixed is gone; whatever it
did not take is still there for round two.

If an item came back BLOCKED, that is not a failure of the tutorial. Open the spec, the
test and the stash. Usually the spec and the tester disagree on a detail (a status code,
a field name), the developer cannot satisfy both, and three rounds were spent arguing.
Fix the spec by hand and run `/improve` again; the log-analyst will see the item is still
open.

---

## 7. Round two, and running unattended

Each `/improve` is one round, by design: a human reads the report between rounds. When
you trust the loop for a repository, there are three ways to keep it going.

**Timer loop, same session.**

```
/loop 30m /improve
```

Every 30 minutes the session runs another round. Because the log-analyst reads
`reports/`, it knows what earlier rounds shipped and does not re-pick them. Stop it when
the report says "nothing to do" twice.

**Deterministic workflow.** `.claude/workflows/improve.js` is the same loop with the
control flow written in JavaScript instead of prose: the round cap, the failure routing
and the fresh verifier are code, not instructions the model might reinterpret. Each
agent is forced to return JSON that matches a schema, so there is no "return only the
structured block" dance. Run it with:

```
use a workflow: run improve with {"maxItems": 2, "maxRounds": 3, "round": 2}
```

Workflows must be asked for explicitly, by saying "use a workflow" or by typing
`ultracode` in the prompt. They can fan out many agents at once, so the explicit opt-in
is the cost control. Developers in the workflow version get their own git worktree
because two items can be in the Build phase at the same time.

**Scheduled cloud routine.** `/schedule` creates a routine that runs a prompt on a cron
in the cloud, for a repository that lives on GitHub. The prompt would be `/improve` and
the output a pull request instead of a local commit. That is the production shape.

When to use which: the skill while you are still tuning the agent prompts (you can read
the orchestrator's reasoning); the workflow once the loop is stable and you want it to
behave the same way every time; the routine when nobody is at the keyboard.

---

## 8. Feeding it real logs and real requests

The demo replays traffic through the kernel to keep the repo self-contained. In a real
project you replace the inputs, not the agents.

**Logs.** `config/packages/monolog.yaml` already writes JSON to stderr in `prod`. Ship
those lines wherever you ship logs today, then have a small job copy the last 24 hours of
`CRITICAL` and `ERROR` lines into `var/log/triage.log` before each round, and change the
path in `log-analyst.md`. Keep the file small; the agent groups by exception, file and
line, so a few hundred lines is plenty and tens of thousands only slow it down.

Two things make a log useful to an agent, and both are Monolog settings, not agent
settings: include the file and line (the default exception handler does), and keep a
stable `request` channel so the analyst can ignore Doctrine and console noise.

**Requests.** The `requests/` folder is a stand-in for an issue tracker. The honest
real-world version is a script that exports open issues labelled `agent-ok` into that
folder as Markdown, one per file, and moves them to `done/` when the commit lands. The
label is the human gate: nothing reaches the loop that a person did not tag.

Do not point the log-analyst at a live tracker with write access. Reading is enough,
and the instruction-source rule in `requests/README.md` only works if the agents cannot
act on what they read.

---

## 9. Hardening for real work

What the demo does already, and why, so you keep it when you adapt the loop:

- **Structured returns everywhere.** Every role ends with a fixed block or JSON. The
  orchestrator branches on fields, never on prose. When you add a role, add its block
  first and its procedure second.
- **Round cap of three.** Oscillation between developer and verifier is a signal that
  the spec is ambiguous. Raising the cap hides the signal and burns tokens. The skill
  explicitly forbids the orchestrator from raising it on its own.
- **Permissions.** `.claude/settings.json` allows the wrapper commands, git read and
  commit, and denies `git push`, `git reset --hard` and `rm -rf`. The loop can commit
  locally all day and cannot publish or destroy anything. Pushing stays a human action.
- **Clean-tree precondition.** The verifier's diff review is only meaningful if the diff
  is the agents' work alone.
- **Reports as memory.** `reports/round-N.md` is how round N+1 knows what happened.
  Agents have no memory between spawns; the file system is the memory.

What you should add before trusting it on a production repository:

- A CI run on every commit the loop makes, and a branch per loop session so the commits
  land as a pull request rather than on main.
- A token budget per round. The Workflow tool exposes `budget`; the skill version relies
  on the item and round caps.
- A `security-reviewer` role between verifier PASS and commit for any item that touches
  authentication, payments or file access. Same pattern: read-only tools, JSON verdict.
- Rotation of the planted evidence. Once the three bugs are fixed and the three requests
  shipped, the loop correctly reports "nothing to do". Real logs keep it fed.

What it cannot do, so you do not expect it to:

- Decide what the product should be. The log-analyst ranks what users hit and what
  people asked for; it does not invent roadmap.
- Resolve a spec the humans disagree on. BLOCKED items are the loop handing a decision
  back to you.
- Run safely on a repository without tests. The green baseline is the floor everything
  stands on.

---

## 10. Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| `php: command not found` inside an agent | agent called bare `php` | the role file says to use `tools/php`; if an agent keeps forgetting, move that line to the top of its Environment section |
| Verifier says PASS but the simulator still logs the CRITICAL | verifier skipped step 3 | its prompt lists the exact reset and replay commands; check `reports/` for the suite line, then re-run `/improve` with the item still open |
| Tester's test passes before the developer finished | the test asserts current behaviour, not the spec | the spec criterion is probably phrased as "should not crash"; rewrite it with an explicit status code and body |
| Orchestrator opens source files and starts fixing things itself | the skill's "you do not write code" line was overridden by a strong user prompt | run `/improve` with no extra text; put extra constraints in a request file instead |
| Item BLOCKED three rounds in a row | spec ambiguity | read the verifier's failure `what` fields across rounds; they will contradict each other; edit the spec |
| Two developers edited the same file | ran the workflow version without `isolation: 'worktree'` | it is set in `improve.js`; keep it |
| `Agent type 'developer' not found` | the role file was added after the session started | start a new session on the folder |

---

## 11. Exercises

1. Add a request asking for `GET /api/tickets/{id}` to include a `age_in_days` field.
   Run a round. Check that the spec-writer put an exact JSON shape in the criteria.
2. Break the tester on purpose: remove the sentence "You are deliberately kept away from
   the implementation diff" and give it `git diff` in its procedure. Run a round on a new
   bug. Compare the tests it writes with the ones from exercise 1.
3. Change the verifier's model to Sonnet and the round cap to 1. Observe how often an
   item ships with a criterion uncovered, then put the settings back.
4. Replace `app:simulate-traffic` with a `curl` script against `symfony serve`, and point
   the log-analyst at the resulting `dev.log`. Nothing in `.claude/` should need to
   change; if it does, the role was coupled to the demo and that coupling is the bug.
5. Write a sixth role, `release-notes`, that runs after every PASS and appends one line
   to `CHANGELOG.md` from the spec title and commit hash. Decide what it returns before
   you write its procedure.
