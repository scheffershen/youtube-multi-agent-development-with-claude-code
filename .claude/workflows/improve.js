export const meta = {
  name: 'improve',
  description: 'TicketFlow improvement round, deterministic: baseline -> triage -> approval gate -> per item (spec -> dev + tester -> integrate -> verify, bounded fix rounds -> commit + evidence -> docs) -> report',
  whenToUse: 'When the user says "use a workflow" or "ultracode" to run an improvement round with the control flow pinned in code',
  phases: [
    { title: 'Baseline', detail: 'clean tree and green suite, or stop' },
    { title: 'Triage', detail: 'log-analyst reads var/log and requests/' },
    { title: 'Gate', detail: 'approvals/ file; stops here when not yet approved' },
    { title: 'Spec', detail: 'one spec file per item' },
    { title: 'Build', detail: 'developer + tester on the same tree, split ownership' },
    { title: 'Verify', detail: 'integration check, then a fresh verifier per round' },
    { title: 'Close', detail: 'commit, evidence store, request to done/' },
    { title: 'Publish', detail: 'doc-writer + changelog-writer' },
  ],
}

// The authoritative process is .claude/skills/improve/SKILL.md. This script is a
// deterministic implementation of the same stages: every decision the Markdown version
// leaves to the orchestrating model is code here, and every agent is forced to return
// JSON matching a schema. Items run strictly one after another on the shared working
// tree; developer and tester of one item run in parallel with the tests/ ownership split.
//
// Deliberate difference from SKILL.md: there is no chat question. The approval gate is
// file-based only. First run writes approvals/pending/improve-round-N.md and returns
// {status: 'waiting_approval'}; the human moves the file to approved/ and runs the
// workflow again (same args), which resumes from the approved table.
//
// args: { maxItems?: number, maxRounds?: number, unattended?: boolean }

const maxItems = (args && args.maxItems) || 2
const maxRounds = (args && args.maxRounds) || 3

const BASELINE = {
  type: 'object', required: ['ok', 'round', 'suite'],
  properties: {
    ok: { type: 'boolean' }, round: { type: 'number' }, suite: { type: 'string' },
    tree: { type: 'string' }, reason: { type: 'string' },
    approvedItems: { type: 'array', items: { type: 'object' } },
    pendingApproval: { type: 'boolean' },
  },
}
const ITEM = {
  type: 'object',
  required: ['id', 'kind', 'title', 'evidence', 'observed', 'expected', 'priority'],
  properties: {
    id: { type: 'string' }, kind: { type: 'string', enum: ['bug', 'feature'] },
    title: { type: 'string' }, evidence: { type: 'array', items: { type: 'string' } },
    observed: { type: 'string' }, expected: { type: 'string' }, priority: { type: 'number' },
  },
}
const BACKLOG = { type: 'object', required: ['items'], properties: { items: { type: 'array', items: ITEM }, ignored: { type: 'array', items: { type: 'string' } } } }
const GATE = { type: 'object', required: ['state'], properties: { state: { type: 'string', enum: ['approved', 'pending'] }, file: { type: 'string' }, items: { type: 'array', items: ITEM } } }
const SPEC = { type: 'object', required: ['path', 'criteria'], properties: { path: { type: 'string' }, criteria: { type: 'number' } } }
const DEV_REPORT = { type: 'object', required: ['filesChanged', 'tests'], properties: { filesChanged: { type: 'array', items: { type: 'string' } }, schemaChanged: { type: 'boolean' }, tests: { type: 'string' }, notes: { type: 'string' } } }
const TEST_REPORT = { type: 'object', required: ['testFile', 'covered'], properties: { testFile: { type: 'string' }, covered: { type: 'array', items: { type: 'string' } }, notCovered: { type: 'array', items: { type: 'string' } }, runResult: { type: 'string' } } }
const INTEGRATION = { type: 'object', required: ['suite', 'green', 'changedFiles'], properties: { suite: { type: 'string' }, green: { type: 'boolean' }, changedFiles: { type: 'array', items: { type: 'string' } } } }
const VERDICT = {
  type: 'object', required: ['verdict', 'failures'],
  properties: {
    verdict: { type: 'string', enum: ['PASS', 'FAIL'] }, suite: { type: 'string' },
    failures: { type: 'array', items: { type: 'object', required: ['what', 'where'], properties: { criterion: { type: 'string' }, what: { type: 'string' }, where: { type: 'string' }, fix_hint: { type: 'string' } } } },
    remaining_criticals_out_of_scope: { type: 'array', items: { type: 'string' } }, notes: { type: 'string' },
  },
}
const CLOSE = { type: 'object', required: ['commit', 'status'], properties: { commit: { type: 'string' }, status: { type: 'string', enum: ['committed', 'blocked'] }, stash: { type: 'string' }, requestMoved: { type: 'string' } } }
const DOC_REPORT = { type: 'object', required: ['pages'], properties: { pages: { type: 'array', items: { type: 'string' } }, documented: { type: 'string' }, notDocumented: { type: 'string' } } }
const FRAG_REPORT = { type: 'object', required: ['fragment', 'section'], properties: { fragment: { type: 'string' }, section: { type: 'string' } } }

// ---------------------------------------------------------------- Baseline + resume
phase('Baseline')
const base = await agent(
  `You are the orchestrator's preflight for TicketFlow (read CLAUDE.md). Do exactly this and return JSON:
1. git status --porcelain must be empty (tree = "clean"); otherwise ok=false, reason.
2. tools/php vendor/bin/phpunit must end with OK; put the summary line in suite; otherwise ok=false.
3. round = 1 + number of files matching reports/round-*.md.
4. If approvals/approved/improve-round-<round>.md exists and reports/round-<round>.md does not: parse its table into approvedItems (id, kind, title, evidence, observed, expected, priority; observed/expected may be copied from the title when absent) and set pendingApproval=false.
   Else if approvals/pending/improve-round-<round>.md exists: pendingApproval=true, approvedItems=[].
   Else: pendingApproval=false, approvedItems=[].
Do not change any file.`,
  { label: 'baseline', schema: BASELINE },
)
if (!base || !base.ok) return { status: 'aborted', reason: base ? base.reason : 'no baseline' }
const roundNo = base.round
if (base.pendingApproval) return { status: 'waiting_approval', round: roundNo, file: `approvals/pending/improve-round-${roundNo}.md` }

// ---------------------------------------------------------------- Triage + gate
let items
if (base.approvedItems && base.approvedItems.length) {
  items = base.approvedItems
  log(`Round ${roundNo}: resuming from approved backlog (${items.length} items)`)
} else {
  phase('Triage')
  const backlog = await agent(`Produce the backlog for round ${roundNo}.`, { label: 'log-analyst', agentType: 'log-analyst', schema: BACKLOG })
  items = (backlog && backlog.items ? backlog.items : []).sort((a, b) => a.priority - b.priority).slice(0, maxItems)
  log(`Triage: ${backlog && backlog.items ? backlog.items.length : 0} candidates, taking ${items.length}`)
  if (!items.length) {
    await agent(`Write reports/round-${roundNo}.md saying triage found nothing to do, and commit it as "round ${roundNo}: nothing to do". Return {"state":"approved"}.`, { label: 'empty-round', schema: GATE })
    return { status: 'nothing_to_do', round: roundNo }
  }
  phase('Gate')
  const gate = await agent(
    `Human gate for round ${roundNo} (read approvals/README.md). Write approvals/pending/improve-round-${roundNo}.md with this backlog as the table:
${JSON.stringify(items, null, 2)}
Commit it as "gate: improve-round-${roundNo} pending". ${args && args.unattended ? 'Then send one push notification naming the file.' : ''}
Return {"state":"pending","file":"approvals/pending/improve-round-${roundNo}.md"}.`,
    { label: 'gate', schema: GATE },
  )
  return { status: 'waiting_approval', round: roundNo, file: gate ? gate.file : undefined }
}

// ---------------------------------------------------------------- Items, strictly sequential
const results = []
for (const item of items) {
  const id = item.id
  phase('Spec')
  const spec = await agent(
    `Write the spec for this backlog item:\n${JSON.stringify(item, null, 2)}\nReturn the spec path and the number of acceptance criteria.`,
    { label: `spec:${id}`, agentType: 'spec-writer', schema: SPEC },
  )
  if (!spec || spec.criteria < 2) { results.push({ id, status: 'spec_incomplete' }); log(`${id}: spec incomplete, skipped`); continue }

  await agent(`Create reports/items/${id}/item.json containing exactly this JSON and nothing else:\n${JSON.stringify({ ...item, spec: spec.path })}\nReturn {"suite":"n/a","green":true,"changedFiles":["reports/items/${id}/item.json"]}.`, { label: `evidence:${id}`, schema: INTEGRATION, effort: 'low' })

  phase('Build')
  const ownership = 'Ownership of tests/: the developer owns only shared test infrastructure the spec names explicitly; the tester owns every test class, including structural changes to existing ones the spec requires. Both work on the same tree.'
  let [dev, test] = await parallel([
    () => agent(`Implement \`${spec.path}\`. ${ownership}`, { label: `dev:${id}`, agentType: 'developer', schema: DEV_REPORT }),
    () => agent(`Write tests for \`${spec.path}\`. ${ownership} Do not read src/.`, { label: `test:${id}`, agentType: 'tester', schema: TEST_REPORT }),
  ])

  let verdict = null
  let round = 0
  while (round < maxRounds) {
    round += 1
    phase('Verify')
    // Integration point: the candidate revision is the working tree after both agents
    // stopped. One agent runs the whole suite on it and records what changed, so the
    // verifier and the eventual commit describe the same tree.
    const integ = await agent(
      `Integration check for item ${id}: run tools/php vendor/bin/phpunit once on the current working tree, list git status --porcelain paths, and save these two reports verbatim to reports/items/${id}/developer.md and reports/items/${id}/tester.md:\nDEVELOPER:\n${JSON.stringify(dev)}\nTESTER:\n${JSON.stringify(test)}\nReturn the suite summary line, green=true only if it ends with OK, and changedFiles.`,
      { label: `integrate:${id}#${round}`, schema: INTEGRATION, effort: 'low' },
    )
    log(`${id}: integration ${integ && integ.green ? 'green' : 'red'} (${integ ? integ.suite : 'n/a'})`)
    // Always a fresh verifier; it never sees dev/test reports.
    verdict = await agent(`Verify \`${spec.path}\`.`, { label: `verify:${id}#${round}`, agentType: 'verifier', schema: VERDICT })
    await agent(`Write reports/items/${id}/verdict-${round}.json containing exactly this JSON:\n${JSON.stringify(verdict)}\nReturn {"suite":"n/a","green":true,"changedFiles":["reports/items/${id}/verdict-${round}.json"]}.`, { label: `evidence:${id}#${round}`, schema: INTEGRATION, effort: 'low' })
    if (!verdict || verdict.verdict === 'PASS') break
    log(`${id}: FAIL on round ${round} with ${verdict.failures.length} failure(s)`)
    if (round >= maxRounds) break
    const toTester = verdict.failures.filter(f => /tests\//.test(f.where))
    const toDev = verdict.failures.filter(f => !/tests\//.test(f.where))
    ;[dev, test] = await parallel([
      () => toDev.length ? agent(`Spec: \`${spec.path}\`. The verifier found these failures. Fix only these, then re-run the suite:\n${JSON.stringify(toDev, null, 2)}`, { label: `dev-fix:${id}#${round}`, agentType: 'developer', schema: DEV_REPORT }) : Promise.resolve(dev),
      () => toTester.length ? agent(`Spec: \`${spec.path}\`. The verifier found these problems in the tests. Fix only these:\n${JSON.stringify(toTester, null, 2)}`, { label: `test-fix:${id}#${round}`, agentType: 'tester', schema: TEST_REPORT }) : Promise.resolve(test),
    ])
  }

  phase('Close')
  const passed = verdict && verdict.verdict === 'PASS'
  const close = await agent(
    passed
      ? `Close item ${id} (spec ${spec.path}) as PASS, following .claude/skills/improve/SKILL.md step 2e: git add -A, commit "${id}: ${item.title}" with the standard attribution trailer; if the item's evidence cites a file in requests/, git mv it to requests/done/ with a "Resolved in round ${roundNo}, commit <sha>" line and commit that too. Return the final commit sha, status "committed", requestMoved (path or "").`
      : `Close item ${id} (spec ${spec.path}) as BLOCKED, following SKILL.md step 2d exactly: write approvals/pending/blocked-${id}.md with the verdicts in reports/items/${id}/ side by side; commit the spec, reports/items/${id}/ and that file as "gate: blocked-${id}"; then git stash push -u -m "blocked-${id}" for everything else; confirm git status --porcelain is empty and tools/php vendor/bin/phpunit is green at HEAD. Return the gate commit sha, status "blocked", stash name.`,
    { label: `close:${id}`, schema: CLOSE },
  )
  if (!passed) { results.push({ id, status: 'blocked', fixRounds: round - 1, stash: close ? close.stash : undefined }); continue }

  phase('Publish')
  // Serialized after the commit; neither agent receives the developer's report.
  const [doc, frag] = await parallel([
    () => agent(`Document \`${spec.path}\` (item \`${id}\`, verdict in \`reports/items/${id}/\`).`, { label: `doc:${id}`, agentType: 'doc-writer', schema: DOC_REPORT }),
    () => agent(`Write the fragment for \`${spec.path}\` (item \`${id}\`, commit \`${close.commit}\`).`, { label: `changelog:${id}`, agentType: 'changelog-writer', schema: FRAG_REPORT }),
  ])
  await agent(`Save these two reports to reports/items/${id}/doc-writer.md and reports/items/${id}/changelog-writer.md, then git add docs release reports/items/${id} and commit "docs: ${id}". Return {"commit":"<sha>","status":"committed"}.`, { label: `publish-commit:${id}`, schema: CLOSE, effort: 'low' })
  results.push({ id, status: 'committed', commit: close.commit, fixRounds: round - 1, pages: doc ? doc.pages : [], fragment: frag ? frag.fragment : undefined })
}

// ---------------------------------------------------------------- Report
await agent(
  `Write reports/round-${roundNo}.md in the format of SKILL.md step 3 from these results, move approvals/approved/improve-round-${roundNo}.md to approvals/approved/ if it is still in pending/ (it should already be approved), and commit "round ${roundNo}: report". Results:\n${JSON.stringify(results, null, 2)}\nReturn {"commit":"<sha>","status":"committed"}.`,
  { label: 'report', schema: CLOSE, effort: 'low' },
)
log(`Round ${roundNo}: ${results.filter(r => r.status === 'committed').length}/${items.length} items committed`)
return { status: 'done', round: roundNo, results }
