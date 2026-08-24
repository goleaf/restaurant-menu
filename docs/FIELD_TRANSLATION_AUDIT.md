# Translation audit evidence

The final 2026-08-24 `php artisan translations:audit` inspected `en.json`, `lt.json`, and `ru.json`: 2,157 semantic keys per locale, 6,471 total entries, zero missing/extra/unused/empty/invalid/legacy/phrase-style/placeholder/plural issues and zero critical issues. `translations:scan --json` scanned 635 first-party files: all 2,157 semantic keys are used and none are missing, extra, unused or legacy.

Counts are evidence from that execution, not a permanent guarantee. The canonical workflow and required parity checks are in [`localization.md`](localization.md); rerun both commands after every user-facing text change.
