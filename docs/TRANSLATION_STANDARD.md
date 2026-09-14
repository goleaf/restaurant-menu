<!-- BEGIN GITHUB_PUSH_ONLY -->
> **GitHub restriction — current user instruction.** GitHub is allowed only as the remote destination of an ordinary `git push`; create commits locally with `git commit`. Do not use GitHub for any other read or write: no API, MCP, plugin, `gh`, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses or remote checks. Do not open GitHub links, change repository settings/integrations, or create, edit or delete `.github/workflows/*`. Do not run fetch/pull/ls-remote or make an extra GitHub request to verify a push. Use local history and local quality gates; report the actual push command result. Historical GitHub references below are archival evidence and grant no authorization. Keep this single marked block in every tracked or newly created project Markdown file, after YAML frontmatter when present. Do not modify external skills or generated dependencies.
<!-- END GITHUB_PUSH_ONLY -->

# Translation standard

The canonical localization policy is [`localization.md`](localization.md), with namespace guidance in [`TRANSLATION_KEY_MAP.md`](TRANSLATION_KEY_MAP.md).

Keys are stable semantic dot paths, not copied English phrases. Every added key exists in `en`, `lt`, and `ru`; placeholders/plurals match; user-facing code and Blade do not hardcode fallback text. Persisted enum codes remain untranslated domain values and resolve to keys for presentation.

## Legacy phrase-key allowlist

The executable test reads the bounded section below. It is empty because all active JSON keys are semantic.

<!-- legacy-translation-keys:start -->
<!-- legacy-translation-keys:end -->
