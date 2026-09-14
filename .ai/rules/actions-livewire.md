---
paths:
  - 'app/{Actions,Livewire}/**'
---

# Actions Livewire

## Save compound branch settings atomically
The branch Settings component delegates untrusted form state to BranchSettingsForm and invokes SaveBranchConfigurationAction once. That Action reloads and authorizes the branch, scopes the settings row, and owns the transaction for settings, profile, closure, hours and images. Shared ReplaceLocalImageAction/RemoveLocalImageAction defer old-file deletion until the outer default SQLite transaction commits; replacement files are removed on rollback. Never compensate a newly committed file in a catch around transaction commit callbacks, which may fail after the database has committed.

## Validate cyclic weekly hours before persistence
Branch weekly schedules allow overnight intervals but reject overlap across every day, including Sunday into Monday. Apply NonOverlappingOpeningHours with the structural seven-day/four-interval limits; the rule skips malformed shapes so field rules can report them. Keep the editor's four-interval guard and prepared Add-control visibility aligned. Opening status sorts by clock time, determines overnight from stored wall-clock values, and skips an occurrence if daylight-saving normalization collapses its duration.

## Treat media write vetoes as rollback, not successful persistence
Shared ReplaceLocalImageAction and RemoveLocalImageAction own the persistence transaction. Register new-file rollback cleanup immediately after storage, before invoking the callback; never delete a committed replacement from a catch around commit callbacks. Required model writes must check false from save/delete (including saveOrFail/deleteOrFail); bounded gallery createMany results must all exist. Throw inside the owning transaction on veto so references, observer writes and files roll back together. After-commit exceptions may leave old orphan files but must preserve committed references.
