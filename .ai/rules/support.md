---
paths:
  - 'tests/Support/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Support

## Pest browser request and upload boundaries
Pest Browser 4.3.1 reuses its Laravel container; IsolatedBrowserIdentity must refresh the native Redirector and ResponseFactory after resetting its session. The version-pinned PestFileUploadServer configures only the existing loopback socket to 2 MiB; retain signature/CSRF/file validation and fixture hash checks. Browser multipart adaptation is not PHP-FPM parser or 256 MiB browser evidence.
