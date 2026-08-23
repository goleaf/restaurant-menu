---
paths:
  - 'app/Livewire/**'
---

# Livewire

## Delegate Eloquent access from Livewire
Livewire components authorize, validate, and coordinate UI state. They must not construct Eloquent or relationship queries or persist models directly; use focused domain read services for prepared reads and Actions for mutations. Substantial multi-field validation belongs in Livewire Form objects using shared rule builders.
