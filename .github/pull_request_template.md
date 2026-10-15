<!-- The merge into main is the human gate. The checklist is what the reviewer signs. -->

## What ships

- Items: <!-- B1, U3, ... with their spec paths -->
- Release: <!-- version, or "none" -->

## Evidence

- [ ] Every item has `reports/items/<id>/verdict-<n>.json` with `"verdict": "PASS"`.
- [ ] Every decision in `reports/items/<id>/decisions.md` has a person's name and date.
- [ ] If a release is cut: the dossier's conclusion is quoted here verbatim, with its anomaly count.
- [ ] No agent ran `git tag`, `git merge` or `git push` (the hook log would show a refusal).

## Dossier conclusion (verbatim)

<!-- "La version X est validée au commit ..." or "n'est pas intégralement validée ..." -->

## Reviewer

<!-- name, date -->
