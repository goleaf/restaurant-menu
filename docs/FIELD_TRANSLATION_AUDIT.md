# Translation audit evidence

The 2026-08-22 baseline `composer translations:audit` inspected `en.json`, `lt.json`, and `ru.json`: 2,002 keys per locale, 6,006 total entries, 1,541 referenced keys, 461 unused keys, and zero missing or invalid keys.

Counts are evidence from that execution, not a permanent guarantee. The canonical workflow and required parity checks are in [`localization.md`](localization.md); rerun the command after every user-facing text change and update this snapshot only with observed output.
