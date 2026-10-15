# GitHub setup (run by a person, not by an agent)

Publishing, pushing and merging are human actions in this project. These commands are
collected here so they are run once, by you, from a shell with `gh` authenticated.
Nothing below is run by the loop.

## 1. Create the repositories

`ticketflow` is public, `ticketflow-tutorial` is private. Replace `<owner>` with your
GitHub user or organisation.

```bash
cd D:\Sandbox\yt-30-10-2026\ticketflow
gh repo create <owner>/ticketflow --public --source=. --remote=origin --description "Symfony multi-agent playground: ten Claude Code roles, human gates, evidence-based releases"
```

```bash
cd D:\Sandbox\yt-30-10-2026\ticketflow-tutorial
gh repo create <owner>/ticketflow-tutorial --private --source=. --remote=origin --description "Tutorial, starter kit and video outline for the ticketflow demo"
```

## 2. Push

```bash
cd D:\Sandbox\yt-30-10-2026\ticketflow
git push -u origin main
git push origin build/urs-002 demo/round-1-example demo-start
git push origin v0.4.0
```

```bash
cd D:\Sandbox\yt-30-10-2026\ticketflow-tutorial
git push -u origin main
```

## 3. Protect `main` on ticketflow

Requires the `ci` workflow to have run once so the check names exist. The file
`docs/github/branch-protection.json` requires the two CI jobs, one approving review, no
force pushes, no deletions, and applies to administrators too.

```bash
cd D:\Sandbox\yt-30-10-2026\ticketflow
gh api -X PUT repos/<owner>/ticketflow/branches/main/protection --input docs/github/branch-protection.json
```

After this, `main` moves only by pull request. The PR template asks the reviewer to
confirm the verdicts, the decisions and the dossier conclusion before merging; that is
where the human signature lives.

## 4. Before the first public push, decide

- **Licence.** The repository has none. Pick one (MIT is the usual choice for a
  teaching demo) and add `LICENSE`; without it the code is "all rights reserved" by
  default.
- **`tools/composer.phar`** (3.6 MB) is committed for Windows convenience. Keep it, or
  remove it and tell readers to download Composer.
- **Names in the record.** The approvals and decision files carry your name and the
  commit history your e-mail; that is the point of the audit trail, but it is public.

## What the CI enforces on every push and PR

- Migrations apply on an empty database, dev and test.
- The whole suite passes with PHP 8.4 on Linux.
- The standard traffic replay produces no 5xx and writes no CRITICAL line.
- The human-gate hook passes its executable checks.
- The Docker image builds and the suite passes inside it.
