<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Data model

Add nullable branches.public_translations JSON only, no data backfill or default-language guess. Cast array; EN/LT/RU maps of name max160 and description max1200. Nullable optional translations are distinct from missing keys.

Add branch_settings_changes: id, branch_id FK/index, nullable user_id FK/index, unique UUID request_id, group string, payload_hash64, result JSON, timestamps. Receipt records are actor/group/payload/version bound, hidden hash/request fields, contain no credentials/files/raw requests. Atomic with operation; completed replay reauthorizes.

Existing branch_settings unique branch_id remains creation concurrency protection. Existing defaults stay canonical. No new visit, payment, QR or schedule tables.
