export const meta = {
  name: 'improve',
  description: 'TicketFlow self-improvement round: triage logs + requests, then spec -> dev/test -> verify with bounded fix rounds',
  whenToUse: 'When the user says "use a workflow" or "ultracode" to run an improvement round deterministically',
  phases: [
    { title: 'Triage', detail: 'log-analyst reads var/log and requests/' },
    { title: 'Spec', detail: 'one spec file per backlog item' },
    { title: 'Build', detail: 'developer + tester in parallel, from the spec only' },
    { title: 'Verify', detail: 'fresh verifier each round, up to maxRounds' },
  ],
}

// Deterministic version of .claude/skills/improve/SKILL.md.
// The skill version lets the model make the control-flow decisions;
// this version pins them in code. Same agents, same contracts.
//
// args: { maxItems?: number, maxRounds?: number, round?: number }

const maxItems = (args && args.maxItems) || 2
const maxRounds = (args && args.maxRounds) || 3
const roundNo = (args && args.round) || 1

const BACKLOG = {
  type: 'object',
  required: ['items'],
  properties: {
    items: {
      type: 'array',
      items: {
        type: 'object',
        required: ['id', 'kind', 'title', 'evidence', 'observed', 'expected', 'priority'],
        properties: {
          id: { type: 'string' },
          kind: { type: 'string', enum: ['bug', 'feature'] },
          title: { type: 'string' },
          evidence: { type: 'array', items: { type: 'string' } },
          observed: { type: 'string' },
          expected: { type: 'string' },
          priority: { type: 'number' },
        },
      },
    },
    ignored: { type: 'array', items: { type: 'string' } },
  },
}

const SPEC = {
  type: 'object',
  required: ['path', 'criteria'],
  properties: {
    path: { type: 'string' },
    criteria: { type: 'number', description: 'number of acceptance criteria written' },
  },
}

const DEV_REPORT = {
  type: 'object',
  required: ['filesChanged', 'tests'],
  properties: {
    filesChanged: { type: 'array', items: { type: 'string' } },
    schemaChanged: { type: 'boolean' },
    tests: { type: 'string' },
    notes: { type: 'string' },
  },
}

const TEST_REPORT = {
  type: 'object',
  required: ['testFile', 'covered'],
  properties: {
    testFile: { type: 'string' },
    covered: { type: 'array', items: { type: 'string' } },
    notCovered: { type: 'array', items: { type: 'string' } },
    runResult: { type: 'string' },
  },
}

const VERDICT = {
  type: 'object',
  required: ['verdict', 'failures'],
  properties: {
    verdict: { type: 'string', enum: ['PASS', 'FAIL'] },
    suite: { type: 'string' },
    failures: {
      type: 'array',
      items: {
        type: 'object',
        required: ['what', 'where'],
        properties: {
          criterion: { type: 'string' },
          what: { type: 'string' },
          where: { type: 'string' },
          fix_hint: { type: 'string' },
        },
      },
    },
    remaining_criticals_out_of_scope: { type: 'array', items: { type: 'string' } },
    notes: { type: 'string' },
  },
}

phase('Triage')
const backlog = await agent(`Produce the backlog for round ${roundNo}.`, {
  label: 'log-analyst',
  agentType: 'log-analyst',
  schema: BACKLOG,
})
const items = (backlog && backlog.items ? backlog.items : [])
  .sort((a, b) => a.priority - b.priority)
  .slice(0, maxItems)
log(`Triage: ${backlog && backlog.items ? backlog.items.length : 0} candidates, taking ${items.length}`)
if (!items.length) return { round: roundNo, results: [], note: 'nothing to do' }

// Each item flows through spec -> build -> verify independently (pipeline, no barrier).
// Items run concurrently, so each developer gets its own worktree to avoid conflicting edits.
const results = await pipeline(
  items,

  item => agent(
    `Write the spec for this backlog item:\n${JSON.stringify(item, null, 2)}\nReturn the spec path and the number of acceptance criteria.`,
    { label: `spec:${item.id}`, phase: 'Spec', agentType: 'spec-writer', schema: SPEC },
  ),

  async (spec, item) => {
    if (!spec || spec.criteria < 2) throw new Error(`${item.id}: spec incomplete`)
    const [dev, test] = await parallel([
      () => agent(`Implement \`${spec.path}\`.`, {
        label: `dev:${item.id}`, phase: 'Build', agentType: 'developer', schema: DEV_REPORT, isolation: 'worktree',
      }),
      () => agent(`Write tests for \`${spec.path}\`.`, {
        label: `test:${item.id}`, phase: 'Build', agentType: 'tester', schema: TEST_REPORT, isolation: 'worktree',
      }),
    ])
    return { spec, dev, test }
  },

  async (built, item) => {
    let verdict = null
    let round = 0
    while (round < maxRounds) {
      round += 1
      // Always a fresh verifier: no memory of what it expected last time.
      verdict = await agent(`Verify \`${built.spec.path}\`.`, {
        label: `verify:${item.id}#${round}`, phase: 'Verify', agentType: 'verifier', schema: VERDICT,
      })
      if (!verdict || verdict.verdict === 'PASS') break
      log(`${item.id}: FAIL on round ${round} with ${verdict.failures.length} failure(s)`)
      if (round >= maxRounds) break
      // Route failures. Test-file problems go to the tester, everything else to the developer.
      const toTester = verdict.failures.filter(f => /tests\//.test(f.where))
      const toDev = verdict.failures.filter(f => !/tests\//.test(f.where))
      await parallel([
        () => toDev.length ? agent(
          `Spec: \`${built.spec.path}\`. The verifier found these failures. Fix only these, then re-run the suite:\n${JSON.stringify(toDev, null, 2)}`,
          { label: `dev-fix:${item.id}#${round}`, phase: 'Build', agentType: 'developer', schema: DEV_REPORT },
        ) : Promise.resolve(null),
        () => toTester.length ? agent(
          `Spec: \`${built.spec.path}\`. The verifier found these problems in the tests. Fix only these:\n${JSON.stringify(toTester, null, 2)}`,
          { label: `test-fix:${item.id}#${round}`, phase: 'Build', agentType: 'tester', schema: TEST_REPORT },
        ) : Promise.resolve(null),
      ])
    }
    return { item, spec: built.spec.path, verdict, fixRounds: round - 1 }
  },
)

const done = results.filter(Boolean)
log(`Round ${roundNo}: ${done.filter(r => r.verdict && r.verdict.verdict === 'PASS').length}/${items.length} items PASS`)
return { round: roundNo, results: done }
