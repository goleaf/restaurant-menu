# Translation audit evidence

The final 2026-08-22 `php artisan translations:audit` inspected `en.json`, `lt.json`, and `ru.json`: 2,026 semantic keys per locale, 6,078 total entries, zero missing/invalid/bad/legacy/phrase-style keys and zero critical issues. `translations:scan --json` scanned 412 first-party files: 1,495 semantic keys are used, 531 are currently unused, and none are missing or legacy.

Counts are evidence from that execution, not a permanent guarantee. The canonical workflow and required parity checks are in [`localization.md`](localization.md); rerun both commands after every user-facing text change.
