# D1: Accept adding symfony/property-access and symfony/property-info for item U1?

**Item:** U1   **Role:** orchestrator (decision taken without a person on 2026-10-14)
**Why it is a decision:** scope - new dependencies, files the spec listed out of scope (composer.json, config/)

| Option | Consequence |
|--------|-------------|
| A. Ratify | Keep the two components. `#[MapRequestPayload]` on DTOs works; U1, U2, U3 and FX depend on it. Spec-writer now checks for them before relying on the attribute. |
| B. Reverse | Remove the components and rewrite U1, U2, U3 and FX request binding by hand (json_decode + manual Validator), against AGENTS.md conventions. Reopens four items. |

**Recommendation:** A - the dependency is what the spec's own required behaviour needs; verifier judged it necessary, not drift.
**Work so far:** committed in b78d25e; recorded in reports/items/U1/verdict-1.json as "orchestrator_decision".
**Decision:**
A by Yi Shen on 2026-10-14 (chat, Claude Code Desktop) - ratified as recommended
