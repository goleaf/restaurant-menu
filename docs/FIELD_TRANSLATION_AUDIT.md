# Translation audit evidence

The final 2026-08-23 `php artisan translations:audit` inspected `en.json`, `lt.json`, and `ru.json`: 2,178 semantic keys per locale, 6,534 total entries, zero missing/invalid/bad/legacy/phrase-style keys and zero critical issues. `translations:scan --json` scanned 550 first-party files: 1,614 semantic keys are used, 564 are currently unused, and none are missing or legacy.

Counts are evidence from that execution, not a permanent guarantee. The canonical workflow and required parity checks are in [`localization.md`](localization.md); rerun both commands after every user-facing text change.
