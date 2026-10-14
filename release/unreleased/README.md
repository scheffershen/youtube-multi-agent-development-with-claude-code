# Changelog fragments

One file per shipped item, written by the `changelog-writer` agent right after the
verifier's PASS and the commit. `/release <version>` merges every fragment here into a
new version block of the changelog, hands them to the flash-info writer, then moves them
to `release/<version>/` for the record.

Never edit the changelog directly for an item that has a fragment. Fix the fragment.

File name: `<item-id>.md`. Format: see `.claude/agents/changelog-writer.md`.
