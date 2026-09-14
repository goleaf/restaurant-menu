---
paths:
  - 'app/Actions/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> **GitHub restriction — current user instruction.** GitHub is allowed only as the remote destination of an ordinary `git push`; create commits locally with `git commit`. Do not use GitHub for any other read or write: no API, MCP, plugin, `gh`, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses or remote checks. Do not open GitHub links, change repository settings/integrations, or create, edit or delete `.github/workflows/*`. Do not run fetch/pull/ls-remote or make an extra GitHub request to verify a push. Use local history and local quality gates; report the actual push command result. Historical GitHub references below are archival evidence and grant no authorization. Keep this single marked block in every tracked or newly created project Markdown file, after YAML frontmatter when present. Do not modify external skills or generated dependencies.
<!-- END GITHUB_PUSH_ONLY -->

# Actions

## Resolve entity image paths from current scoped persistence
Organization, brand and branch image Actions reload a selected active record inside their transaction before reading the old image path. Retain original organization/brand ownership predicates; stale instances after another save or rollback must not drive cleanup. Save only the fresh image state and synchronize image/timestamp attributes back to the caller without persisting or clearing unrelated dirty fields. Reuse shared rollback/outer-commit media Actions.

## Enforce bulk allocation bounds inside the reusable Action
Component validation alone does not bound direct Action callers. Guard positive ascending range size before range/allocation with overflow-safe arithmetic; preserve transactional model events and throw on rejected required saves. Build successful results from known existing codes and actual saved model values instead of rerunning ownership and preview reads; retain archived-code reservations.
