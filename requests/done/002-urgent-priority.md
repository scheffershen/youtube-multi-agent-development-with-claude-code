# Add an "urgent" priority

**From:** Dev (on-call engineer)
**Observed:** The API accepts any string as `priority`. I posted `"priority": "urgent"`
and it was stored as-is, but the stats and the dashboard only understand low, normal,
high, so urgent tickets vanish from every filter.
**Expected:** `urgent` is a real priority, ranked above `high`. Anything that is not one
of low, normal, high, urgent should be rejected with a validation error, not stored.

---
Resolved in build/urs-002 as item R2 (spec specs/R2-urgent-priority.md): urgent accepted, SLA target 2 hours (decision D1 by Yi Shen), listed after high (D2).
