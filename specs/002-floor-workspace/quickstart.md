<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Validation guide

Use explicit PHP8.5 and the repository's isolated environment helper, never working storage/database. `restaurant-p5-z2py0b1b/run.mjs` wraps the existing platform verification helpers with owned caches/storage and SQLite memory. Example: `node <artifact>/run.mjs floor -d memory_limit=512M artisan test --compact tests/Feature/FloorWorkspaceTest.php`. File-concurrency cases own their SQLite paths.

Validate room URL→table→save first; then deep/corrupt area graphs, direct/merged occupancy, stale move/archive, bulk1/200/201 and all-skipped/replay, exact selection, QR partial-file repair and reissue. Run a real authenticated browser journey through create room→bulk preview→QR→subset→PDF→return. Inspect actual saved screenshots and decoded/rasterized multi-page documents. Runtime, native zoom/physical-device gaps and source hashes must be recorded.

Final gates are the existing Composer backend/coverage/analyse/browser contracts, npm JS coverage/style/generated/build budgets, translations and isolated caches/schema/seed checks. Verify exact discovered/executed IDs and worker reports. `docs/PROGRESS.md` owns observed results; these instructions do not assert any gate passed.
