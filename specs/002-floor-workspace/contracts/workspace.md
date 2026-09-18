<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# UI and operation contracts

Canonical existing service-points route keeps immutable parent/branch identity. URL fields are validated floor filters,exact zone,mode,selected point/area editor and panel; no selected-ID arrays,form drafts or QR bearer. All/no-area are virtual filters. The selected room and object are separately authorized regardless of current list filters.

AreaEditor creates/updates one room and dispatches its concrete saved identity to the parent; PointEditor changes physical properties but an existing area change requires the separate MovePanel. Inputs retain malformed transport shapes until validation; display_number stays text. Domain Actions authorize current actor and version inside transaction. Default exact-area semantics remain visible.

Selection is an explicit bounded fixed set shared by cards/list. Add current page never means all results. Hidden selections are disclosed; adding one QR to print must retain existing selected targets. Empty bulk output is a no-op for selection. All mutation targets are rechecked; no automatic replay on reconnect.

PrintPanel prepares target eligibility without generating QR. Missing/nonactive QR targets remain visible with an authorized recovery path. Confirm creates a fixed snapshot; label/QR/permission changes invalidate it. Screen labels,browser print and PDF use that same snapshot. Only prepared labels appear on printed pages; generated images retain white background and physical geometry. QR repair reuses identity and publishes only a complete local image. Disable/reissue retain reason,current-code confirmation and stale-state checks.

The area/table/QR/print and service permissions remain separate. Service actions link/reuse their existing operation; this workspace does not own payment,guest orders or merged service. Parent or actor mismatch fails without disclosing foreign data or writing.
