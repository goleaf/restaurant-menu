# Translation standard

The canonical localization policy is [`localization.md`](localization.md), with namespace guidance in [`TRANSLATION_KEY_MAP.md`](TRANSLATION_KEY_MAP.md).

Keys are stable semantic dot paths, not copied English phrases. Every added key exists in `en`, `lt`, and `ru`; placeholders/plurals match; user-facing code and Blade do not hardcode fallback text. Persisted enum codes remain untranslated domain values and resolve to keys for presentation.

## Legacy phrase-key allowlist

The executable test reads the bounded section below. It is empty because all active JSON keys are semantic.

<!-- legacy-translation-keys:start -->
<!-- legacy-translation-keys:end -->
