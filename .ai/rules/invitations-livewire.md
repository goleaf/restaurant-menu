---
paths:
  - 'app/{Actions/Invitations,Http/Controllers/Invitations,Livewire/**,Models/Invitation.php}/**'
---

# Invitations Livewire

## Rotate invitation links instead of storing plaintext
Invitation bearer plaintext exists only in the immediate authorized response. Persist SHA-256 digests only. Resend means atomically rotate pending or expired credentials, invalidate the old link, and show the new link to the authorized administrator; never reconstruct or store plaintext in the database or session, and do not send mail without confirmed delivery configuration.
