---
paths:
  - 'app/{Actions,Livewire}/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Actions Livewire

## Save restaurant settings by independent operation
Settings uses separate profile, media, guest admission, settlement, locale and advanced contracts. Never restore a full BranchSettingsForm or compose those independent groups into a public Save all. Every Action allowlists fields, reauthorizes the current actor/tenant/archive state, checks its original group fingerprint and binds retries to a durable request UUID. Reading defaults does not insert. Prepare media outside the write transaction; delete old files only after commit and compensate new files on rollback. Preserve unrelated form drafts/errors and paid history.

## Validate cyclic weekly hours before persistence
Branch weekly schedules allow overnight intervals but reject overlap across every day, including Sunday into Monday. Apply NonOverlappingOpeningHours with the structural seven-day/four-interval limits; the rule skips malformed shapes so field rules can report them. Keep the editor's four-interval guard and prepared Add-control visibility aligned. Opening status sorts by clock time, determines overnight from stored wall-clock values, and skips an occurrence if daylight-saving normalization collapses its duration.

## Treat media write vetoes as rollback, not successful persistence
Shared ReplaceLocalImageAction and RemoveLocalImageAction own the persistence transaction. Register new-file rollback cleanup immediately after storage, before invoking the callback; never delete a committed replacement from a catch around commit callbacks. Required model writes must check false from save/delete (including saveOrFail/deleteOrFail); bounded gallery createMany results must all exist. Throw inside the owning transaction on veto so references, observer writes and files roll back together. After-commit exceptions may leave old orphan files but must preserve committed references.

## Keep availability commands separate from profile settings
Profile, settings-group and media Actions own their exact fields only; it must never write a pause or weekly hours. The availability workspace uses separate versioned, replay-safe Actions for pauses, weekly hours, menu windows, date exceptions and item restrictions. Reauthorize and verify all preview dependencies inside the SQLite write transaction. Removing one restriction never clears another; expiry is evaluated at a single supplied server instant without cron.
