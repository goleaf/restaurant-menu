---
paths:
  - 'app/Livewire/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Livewire

## Delegate Eloquent access from Livewire
Livewire components authorize, validate, and coordinate UI state. They must not construct Eloquent or relationship queries or persist models directly; use focused domain read services for prepared reads and Actions for mutations. Substantial multi-field validation belongs in Livewire Form objects using shared rule builders.

## Validate editable transport values before scalar conversion
Kitchen-department create/edit values must reach shared validation in their original transport shape. Use mixed editable state, trim only strings, and combine numeric/integer rules where integer alone accepts boolean coercion. Cast only validated values into Action payloads. Prove malformed updates and the mutation in one actual Livewire POST; preserve valid numeric strings and documented boolean encodings.

## Validate raw selections before persistence
Menu editor inputs retain their original transport types until validation, including translations, flags, integer limits and money. Use numeric with integer to reject booleans while accepting browser numeric strings. Dependent reads and uniqueness scopes use a safe string projection of selections; never overwrite invalid public input with that projection or coerce it before validation. Test malformed update payloads as well as valid create/edit persistence.

## Render-time filter validation must preserve editor errors
In installed Livewire 4.4.1 a successful Form::validate() resets the parent component error bag, not just that form. Do not call it while preparing render-time filters beside another editor. Validate the bounded filter payload with Laravel Validator, retain filters.* error keys/localized field names and reset only those explicit filter fields. Protect invalid editor input across refresh and filter correction.
