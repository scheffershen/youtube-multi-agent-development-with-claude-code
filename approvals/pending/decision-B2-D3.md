# D3: May B2 install `symfony/property-access` and `symfony/property-info` (composer require + Flex recipe) so the developer can bind the POST body with #[MapRequestPayload] on a DTO?

**Item:** B2   **Role:** spec-writer   **Date:** 2026-10-15
**Why it is a decision:** A new dependency changes the scope. composer.json has symfony/serializer and symfony/validator but neither property-access nor property-info.

| Option | Consequence |
|--------|-------------|
| A. Install both (in scope); readonly DTO with #[Assert\NotBlank], bound with #[MapRequestPayload] | Follows AGENTS.md. composer.json, composer.lock and possibly config/ change in the diff; the spec says so in Hints so the verifier does not flag drift. The default #[MapRequestPayload] error is a 422 with violations, not `{"errors":{"<field>":"..."}}`, so the spec also needs a rule to produce that shape. F1 can reuse the DTO to add a Choice constraint. |
| B. Install nothing; validate by hand in the controller (Validator or plain check on the decoded array) | Smaller diff, no dependency change. Goes against AGENTS.md; thin-controller debt, and F1 would have to extend the manual check. |
| C. Install the packages in a separate backlog item first; B2 waits | Cleanest scope, but B2 is delayed and this round's priority-2 item is blocked. |

**Recommendation:** A - follows AGENTS.md, sets F1 up cleanly, and the dependency is small and Flex-managed.
**Work so far:** D1=A and D2=B are final. Existing behaviour is preserved (201 happy path, PATCH status 422 shape, allowed priorities low/normal/high; F1 owns new values). The spec is drafted apart from the Hints section and the dependency scope lines.
**Orchestrator note:** both packages are already in composer.lock and vendor/ as transitive dependencies (they are not listed in composer.json). Option A therefore only adds them as direct requirements in composer.json; no new code is downloaded.
**Decision:**
