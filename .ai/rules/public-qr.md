---
paths:
  - 'app/Livewire/PublicQr/**'
---

# Public Qr

## Reauthorize isolated guest polling
Every guest polling action must resolve the current QR-scoped cookie or session credential and active guest again before querying. If access is revoked or the QR does not belong to the session service point or an active merged link, clear all serialized guest state immediately.
