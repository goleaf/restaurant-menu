<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# ADR 0002: Class Livewire and presentation-only Blade

- Status: accepted
- Date: 2026-08-22

## Decision

All stateful UI uses normal class-based Livewire components with separate Blade templates. Volt and Livewire single-file route components are prohibited. Blade is a presentation boundary and contains no PHP blocks, Eloquent/service/container/facade calls, authorization, money/status calculations or business payload construction.

## Rationale and consequences

Separate typed PHP classes make state, validation, authorization, dependencies and tests discoverable. Presentation-only templates prevent hidden queries and security decisions during render. Static reuse stays in Blade/Flux components; business state remains server-side in Livewire/Actions. Architecture tests enforce the boundary.
