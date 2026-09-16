---
paths:
  - 'resources/{scss,css,build}/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Scsscssbuild

## Compile first-party Sass independently of Tailwind
Own product styles in resources/scss using @use; resources/css/app.css contains framework imports, sources and dark variant only. Vite applicationStyles generates Tailwind aliases and concrete breakpoints from canonical Sass settings and fixed PDF/emergency Blade CSS artifacts before build/dev and on Sass HMR. Never run Sass during PHP requests or edit generated output manually. Preserve theme/base/components/utilities order and targeted accessibility overrides.
