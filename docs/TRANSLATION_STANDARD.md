<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Translation standard

The canonical localization policy is [`localization.md`](localization.md), with namespace guidance in [`TRANSLATION_KEY_MAP.md`](TRANSLATION_KEY_MAP.md).

Keys are stable semantic dot paths, not copied English phrases. Every added key exists in `en`, `lt`, and `ru`; placeholders/plurals match; user-facing code and Blade do not hardcode fallback text. Persisted enum codes remain untranslated domain values and resolve to keys for presentation.

## Legacy phrase-key allowlist

The executable test reads the bounded section below. It is empty because all active JSON keys are semantic.

<!-- legacy-translation-keys:start -->
<!-- legacy-translation-keys:end -->
