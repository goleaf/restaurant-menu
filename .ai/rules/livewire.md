---
paths:
  - 'app/Livewire/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> **GitHub restriction — current user instruction.** GitHub is allowed only as the remote destination of an ordinary `git push`; create commits locally with `git commit`. Do not use GitHub for any other read or write: no API, MCP, plugin, `gh`, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses or remote checks. Do not open GitHub links, change repository settings/integrations, or create, edit or delete `.github/workflows/*`. Do not run fetch/pull/ls-remote or make an extra GitHub request to verify a push. Use local history and local quality gates; report the actual push command result. Historical GitHub references below are archival evidence and grant no authorization. Keep this single marked block in every tracked or newly created project Markdown file, after YAML frontmatter when present. Do not modify external skills or generated dependencies.
<!-- END GITHUB_PUSH_ONLY -->

# Livewire

## Delegate Eloquent access from Livewire
Livewire components authorize, validate, and coordinate UI state. They must not construct Eloquent or relationship queries or persist models directly; use focused domain read services for prepared reads and Actions for mutations. Substantial multi-field validation belongs in Livewire Form objects using shared rule builders.

## Validate editable transport values before scalar conversion
Kitchen-department create/edit values must reach shared validation in their original transport shape. Use mixed editable state, trim only strings, and combine numeric/integer rules where integer alone accepts boolean coercion. Cast only validated values into Action payloads. Prove malformed updates and the mutation in one actual Livewire POST; preserve valid numeric strings and documented boolean encodings.

## Validate raw selections before persistence
Menu editor inputs retain their original transport types until validation, including translations, flags, integer limits and money. Use numeric with integer to reject booleans while accepting browser numeric strings. Dependent reads and uniqueness scopes use a safe string projection of selections; never overwrite invalid public input with that projection or coerce it before validation. Test malformed update payloads as well as valid create/edit persistence.
