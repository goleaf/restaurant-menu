# Code review checklist

Review the complete owned diff, not only the last file touched.

- Requirement IDs and intended behavior are clear; docs/tests/code agree.
- Routes are named/grouped/scoped; mutations authorize and validate server-side.
- Transactions, locking/idempotency and side-effect compensation preserve invariants.
- Eloquent reads are scoped, selected, eager-loaded and bounded; cache keys cannot leak context.
- Blade is presentation-only, escaped and localized; Livewire state is typed/minimal.
- Money avoids floats; files use generated names/configured disks; secrets are absent from code/logs.
- Factories/states/seeders create valid safe data and refuse unsafe production demo behavior.
- Accessibility, responsive states, reduced motion, focus and translated text expansion are verified.
- Targeted tests, Pint, Larastan, build and relevant browser checks have observed results.
- Final status/diff/staged content contain no unrelated or generated artifacts.

No review finding is closed solely because a file exists; cite the passing command or runtime evidence.
