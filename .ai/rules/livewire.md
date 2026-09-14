---
paths:
  - 'app/Livewire/**'
---

# Livewire

## Delegate Eloquent access from Livewire
Livewire components authorize, validate, and coordinate UI state. They must not construct Eloquent or relationship queries or persist models directly; use focused domain read services for prepared reads and Actions for mutations. Substantial multi-field validation belongs in Livewire Form objects using shared rule builders.

## Validate editable transport values before scalar conversion
Kitchen-department create/edit values must reach shared validation in their original transport shape. Use mixed editable state, trim only strings, and combine numeric/integer rules where integer alone accepts boolean coercion. Cast only validated values into Action payloads. Prove malformed updates and the mutation in one actual Livewire POST; preserve valid numeric strings and documented boolean encodings.

## Validate raw selections before persistence
Menu editor inputs retain their original transport types until validation, including translations, flags, integer limits and money. Use numeric with integer to reject booleans while accepting browser numeric strings. Dependent reads and uniqueness scopes use a safe string projection of selections; never overwrite invalid public input with that projection or coerce it before validation. Test malformed update payloads as well as valid create/edit persistence.
