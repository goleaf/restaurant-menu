---
paths:
  - 'app/Livewire/Onboarding/**'
  - 'app/Livewire/Forms/Onboarding/**'
  - 'app/Support/RestaurantSetupOptions.php'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Onboarding Support

## Keep onboarding international defaults non-assumptive
Initialize editable business-name suggestions through EN/LT/RU JSON keys. Leave country and currency unselected; suggest only a valid configured app timezone with UTC fallback. Validate ISO alpha-2 at the form boundary, but keep the existing branches.country representation until a real normalized-code consumer justifies additive schema.

## Validate hostile Livewire JSON before coercion
Livewire Form properties must accept transport-level mixed values so arrays/null/booleans reach Laravel validation instead of causing hydration TypeErrors. Normalize text fields only from actual strings, reject booleans, and cast validated numeric fields only after validation.

## Reject binary-float money input
Onboarding money accepts decimal strings (and exact integer values where required by an Action contract), never PHP floats or booleans. Reject floats before validation/conversion so MoneyFormatter receives no binary floating-point value.
