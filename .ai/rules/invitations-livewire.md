---
paths:
  - 'app/Actions/Invitations/**'
  - 'app/Http/Controllers/Invitations/**'
  - 'app/Livewire/Invitations/**'
  - 'app/Livewire/Organizations/Staff/**'
  - 'app/Livewire/Organizations/Brands/Branches/Staff/**'
  - app/Models/Invitation.php
  - 'app/Support/Invitations/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Invitations Livewire

## Rotate invitation links instead of storing plaintext
Invitation bearer plaintext exists only in the immediate authorized response. Persist SHA-256 digests only. Resend means atomically rotate pending or expired credentials, invalidate the old link, and show the new link to the authorized administrator; never reconstruct or store plaintext in the database or session, and do not send mail without confirmed delivery configuration.
