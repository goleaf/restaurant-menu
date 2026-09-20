<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Research and settings analysis

## Existing settings
| Setting | Current behavior | Decision |
| --- | --- | --- |
| Interface language | users.locale, Profile and session | Keep independent from formats |
| Theme | Flux light/dark/system in Profile, device persistence | Reuse; no second theme store |
| Date/time | LocalizedDateFormatter follows language only | Add account presets + language default |
| Numbers/money | MoneyFormatter follows locale; several numeric labels raw | Add separator presets and shared number presentation |
| Timezone/currency | Restaurant-owned scheduling and accounting | Keep authoritative; personal format cannot change these |
| Sound | Waiter localStorage preference, browser audio permission | Next candidate: scope by user/device and preserve browser permission |
| Density | Preparation compact flag in URL | Next candidate: user default with per-screen override |
| Starting workspace | Existing session remembered restaurant/task | Next candidate: durable user default with access revalidation |
| Calendar week start | Locale/calendar behavior | Next candidate after calendar + report-period contracts are aligned |
| Email/notification preferences | Operational/security notifications have distinct importance | Analyze opt-out classes first; do not silently mute critical events |

## Formatting decisions
Use finite date/time presets and number separator pairs. Preserve localized currency placement and currency code using ICU symbols (official PHP NumberFormatter::setSymbol/formatCurrency docs checked September20). No arbitrary format templates. Null/unknown stored preferences fall back to language default; raw invalid form input must instead fail validation. Dates preserve the supplied timezone; relative text remains locale-based.

No personal timezone control: changing it globally would make branch schedules and operational deadlines misleading. No personal currency conversion or measurement-unit conversion is implied.

## Cache and public boundaries
Analytics and restaurant dashboard cache formatted strings by branch/access and locale. Add bounded preference fingerprints to ordinary and fallback keys and explicitly capture preferences in deferred callbacks. Guest money is formatted after cache in GuestMenu/DraftOrder/DraftTotals/PublicQrOrderQueryService; use explicit locale defaults. Guest menu cache DOES store opening-status labels: propagate locale-only preferences through both menu and branch opening Actions. Notifications currently store raw cents/ISO, with no formatter call sites.

## Bypass inventory
Prioritize report period headings, dish preview generated-at, service-point checked-at and catalogue hidden-until labels. Canonical form data, CSV columns, filenames, tokens, timers and API dates stay unchanged. Availability time inputs currently fix 24-hour display, while summaries concatenate raw HH:mm values; their editor integration needs separate prepared presentation. Numeric measure/percentage labels need their own display fields without changing raw arithmetic/plural operands. This is a bounded first implementation, not a claim that every native date control or historic snapshot uses personal formatting.

## Sources
Installed code and independent read-only inventory. PHP documentation: https://www.php.net/manual/en/numberformatter.setsymbol.php and https://www.php.net/manual/en/numberformatter.formatcurrency.php. Existing localization and frontend contracts remain authoritative.
