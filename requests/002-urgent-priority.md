# Add an "urgent" priority

**From:** Dev (on-call engineer)
**Observed:** The API accepts any string as `priority`. I posted `"priority": "urgent"`
and it was stored as-is, but the stats and the dashboard only understand low, normal,
high, so urgent tickets vanish from every filter.
**Expected:** `urgent` is a real priority, ranked above `high`. Anything that is not one
of low, normal, high, urgent should be rejected with a validation error, not stored.
