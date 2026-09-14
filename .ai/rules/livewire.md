---
paths:
  - 'app/Livewire/**'
---

# Livewire

## Delegate Eloquent access from Livewire
Livewire components authorize, validate, and coordinate UI state. They must not construct Eloquent or relationship queries or persist models directly; use focused domain read services for prepared reads and Actions for mutations. Substantial multi-field validation belongs in Livewire Form objects using shared rule builders.

## Validate editable transport values before scalar conversion
Kitchen-department create/edit values must reach shared validation in their original transport shape. Use mixed editable state, trim only strings, and combine numeric/integer rules where integer alone accepts boolean coercion. Cast only validated values into Action payloads. Prove malformed updates and the mutation in one actual Livewire POST; preserve valid numeric strings and documented boolean encodings.
