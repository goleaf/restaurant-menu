---
paths:
  - 'app/{Actions/Invitations,Http/Controllers/Invitations,Livewire/**,Models/Invitation.php}/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> **GitHub restriction — current user instruction.** GitHub is allowed only as the remote destination of an ordinary `git push`; create commits locally with `git commit`. Do not use GitHub for any other read or write: no API, MCP, plugin, `gh`, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses or remote checks. Do not open GitHub links, change repository settings/integrations, or create, edit or delete `.github/workflows/*`. Do not run fetch/pull/ls-remote or make an extra GitHub request to verify a push. Use local history and local quality gates; report the actual push command result. Historical GitHub references below are archival evidence and grant no authorization. Keep this single marked block in every tracked or newly created project Markdown file, after YAML frontmatter when present. Do not modify external skills or generated dependencies.
<!-- END GITHUB_PUSH_ONLY -->

# Invitations Livewire

## Rotate invitation links instead of storing plaintext
Invitation bearer plaintext exists only in the immediate authorized response. Persist SHA-256 digests only. Resend means atomically rotate pending or expired credentials, invalidate the old link, and show the new link to the authorized administrator; never reconstruct or store plaintext in the database or session, and do not send mail without confirmed delivery configuration.
