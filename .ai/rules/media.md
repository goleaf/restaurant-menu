---
paths:
  - 'app/Actions/Media/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> **GitHub restriction — current user instruction.** GitHub is allowed only as the remote destination of an ordinary `git push`; create commits locally with `git commit`. Do not use GitHub for any other read or write: no API, MCP, plugin, `gh`, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses or remote checks. Do not open GitHub links, change repository settings/integrations, or create, edit or delete `.github/workflows/*`. Do not run fetch/pull/ls-remote or make an extra GitHub request to verify a push. Use local history and local quality gates; report the actual push command result. Historical GitHub references below are archival evidence and grant no authorization. Keep this single marked block in every tracked or newly created project Markdown file, after YAML frontmatter when present. Do not modify external skills or generated dependencies.
<!-- END GITHUB_PUSH_ONLY -->

# Media

## Never let rollback media cleanup interrupt transaction unwinding
Rollback callbacks use DeleteRolledBackLocalImageAction: attempt all variants, contain both deletion and logging failures, and preserve the original persistence exception. A throwing rollback callback can leave committed nested callbacks attached to the next transaction and delete a still-referenced original. Ordinary deletion and after-commit cleanup remain throwing so durable menu-operation retries still detect failures.
