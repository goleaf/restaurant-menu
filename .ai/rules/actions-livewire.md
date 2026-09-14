---
paths:
  - 'app/{Actions,Livewire}/**'
---

# Actions Livewire

## Save compound branch settings atomically
The branch Settings component delegates untrusted form state to BranchSettingsForm and invokes SaveBranchConfigurationAction once. That Action reloads and authorizes the branch, scopes the settings row, and owns the transaction for settings, profile, closure, hours and images. Shared ReplaceLocalImageAction/RemoveLocalImageAction defer old-file deletion until the outer default SQLite transaction commits; replacement files are removed on rollback. Never compensate a newly committed file in a catch around transaction commit callbacks, which may fail after the database has committed.
