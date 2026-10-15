# D3: In GET /api/stats, how are existing rows with an out-of-list priority counted in by_priority?

**Item:** F1   **Role:** spec-writer   **Date:** 2026-10-15
**Why it is a decision:** It decides the JSON keys of /api/stats and the relation between by_priority and total (API contract). It follows from D1=A (rows not rewritten) and D2=B (by_priority with four keys).

| Option | Consequence |
|--------|-------------|
| A. Leave them out: exactly low, normal, high, urgent | Sum of by_priority can be smaller than total when junk rows exist. Contract stays at four keys; the spec states the sum is not guaranteed to equal total. Junk rows are invisible in stats. |
| B. Add a fifth key `other` counting every row outside the four values | Sum of the five keys always equals total. Clients see data problems. A non-priority key is added to the contract permanently. |
| C. Add `other` only when its count is above 0 | Shape varies with the data; clients must handle an optional key. Not recommended. |

**Recommendation:** B - the sum always equals total, so stats never silently drop rows, which is the failure this request is about. Choose A for a strict four-key shape.
**Work so far:** D1=A and D2=B applied; the spec outline is final except for this point. An AC for the out-of-list case will insert a row through the EntityManager, since the API now rejects such values.
**Decision:** B by Shen on 2026-10-15 (chat) - by_priority always has a fifth key 'other'; the five keys sum to total
