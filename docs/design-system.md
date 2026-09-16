<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Design system

## Current SCSS / Alpine boundary — 2026-09-16

The accepted migration supersedes earlier native-CSS-only and page-script decisions for first-party code. `resources/css/app.css` is only the Tailwind/installed Flux bridge. `resources/scss/app.scss` owns product tokens, light/dark variables, fonts, base accessibility and semantic compositions; `qr-print.scss` is a print-only entry. `pdf-qr.scss`, `pdf-report.scss` and `emergency.scss` compile into fixed committed `resources/views/generated/styles/*.blade.php` artifacts, so emergency/PDF rendering never needs a Vite manifest or runtime Node. `resources/build/styles.js` generates token aliases and concrete breakpoints automatically in both Vite build and dev/HMR; `npm run styles:check` detects drift. Do not edit generated CSS.

Cascade order remains Tailwind `theme, base, components, utilities`. Sass emits into existing layers and preserves narrowly scoped unlayered accessibility fixes; there is no new reset. Runtime `--rm-*` values have one canonical Sass map; generated `@theme inline static` aliases resolve the current Flux `.dark` appearance without another theme store. QR physical geometry stays 76×104 mm, 48 mm code plus quiet zone and 8 mm A4 margins. Emergency actions now meet the product's 44 px minimum.

`resources/js/app.js` imports the installed Livewire ESM runtime and its Alpine instance, registers named component factories/bindings, then calls `Livewire.start()` once. Compatibility palette/radius values used by semantic compositions are retained in the same map; static aliases prevent Tailwind pruning variables used only by the separate SCSS output. Every layout supplies `@livewireScriptConfig` before retained `@fluxScripts`. No independent Alpine package or `Alpine.start()` exists. Registration is cheap and synchronous; the WebAuthn SDK loads only when a ceremony starts. Local panel/focus/clipboard/preview/timer/audio state belongs to Alpine; forms, filters, uploads and mutations belong to class-based Livewire and existing Actions. The obsolete twelve root page/behavior modules are removed.

Transport allowlist: Fortify authentication; the single first-party `fetch` boundary in `resources/js/integrations/passkeys.js` for WebAuthn options/credential HTTP protocol; invitation credential/identity transitions; demo identity entry; authorized CSV/PDF/media/SQLite downloads; exclusive-barrier SQLite restore POST. Restore must obtain its lock before authentication/session reads and therefore cannot be moved behind the ordinary Livewire update transport. Static landing/error/PDF/email and navigation/redirect responses remain SSR. `LivewireInteractionBoundaryTest` pins all 30 class-based page routes, 17 HTTP exceptions and 15 CSRF-protected native POST forms; existing negative/replay/tenant tests exercise those boundaries. There were no first-party fetch/XHR/Axios requests at migration baseline, so none are claimed removed. The sole added passkey adapter replaces `@laravel/passkeys` with its installed lower-level `@simplewebauthn/browser` SDK pinned to 13.3.0: cryptographic browser operations stay in the SDK, while owned GET/POST requests enforce same-origin URLs, credentials, CSRF and redirect rejection. Every await checks the current owner; destroy/cancel aborts its request and ceremony. A cancelled POST has an unknown server outcome and never produces a local success claim. `alpine-passkeys-adapter.test.mjs` tests the real SDK after a cancelled delayed GET and controlled cancel/error/success transport boundaries. Application CRUD continues through Livewire.

Quality entrypoint: `npm run verify:migration` executes manifest validation, architecture rules, Stylelint, ESLint, Pint, Larastan, production build, complete PHP discovery/execution, JS coverage, PHP coverage, isolated browser coordinator, translations and budgets with real exit codes. This command defines verification; its presence does not imply it has passed. Exact current results and open gates belong in `PROGRESS.md` and `IMPLEMENTATION_PLAN.md`; older dated counts below remain historical.


## Flux Pro target and present baseline — 2026-09-15

Pro adoption is accepted under `ui-flux-pro-001`, with all 18 supplied families mapped in the [integration plan](superpowers/plans/2026-09-15-flux-pro-integration.md). The local package is installed and all families pass server-render smoke tests. Each product composition still needs interaction, keyboard, localization and responsive acceptance; the local reference screen alone does not prove a migrated workflow.

Preserve the existing product tokens and semantic compositions. Searchable selections must remain scoped; timelines use actual recorded events; charts distinguish missing/zero and currencies; kitchen boards retain authoritative state transitions and a usable mobile list. Alternative component variants belong in bounded fixtures when no real workflow needs them. Shared control changes retain the accessibility/localization contracts in their topic documents.

## Workspace composition — 2026-09-15

Use one shared header/account menu above product workspaces. Official Flux sidebar collapse, modal, input, radio cards and buttons own generic interaction; native branch details retains useful offline/focus behavior. Navigation search and notification viewing are product compositions, not new generic widget frameworks. Keep one notification host per shell and distinguish opening, reading and completing restaurant work. The restricted local component reference demonstrates actual Free controls and semantic state panels with fictional data.


## Shared control contract — 2026-09-16

Danger actions use the explicit `rm-action-danger` class on Flux buttons. Its few important declarations live in `resources/scss/components/_controls.scss`, use the canonical danger/inverse tokens and retain system colors under forced colors; they do not target unrelated red utilities or every button. The same module owns the native image picker, file-selector target, invalid/disabled states and semantic helper/error association. The local reference demonstrates normal, disabled, loading and 56px operational danger controls.

Menu label selections use installed Flux checkbox buttons in `rm-menu-labels` groups. The bounded unlayered grid selector intentionally overrides Flux's own flex layout, without changing other checkbox groups. Labels wrap, check icons remain visible for selected values and server bindings are deferred. Structure list toolbars are a product composition with one filter set and loaded-row count, rather than another generic field abstraction.

## Flux and utility ownership — 2026-09-15

Use documented Flux props, classes and slots for controls; the sidebar heading slot owns heading presentation and `workspace-nav-item` is the reusable product navigation utility. Native details/summary, language tabs, checkbox forms and domain status panels remain where their semantics and behavior already fit. Do not replace them solely to increase the Flux tag count.

Primary actions use Flux `variant="primary"`, normal secondary actions outline/default, lower-emphasis actions ghost/subtle and destructive actions `variant="primary" color="red"`. Success/warning colors are reserved for operational meaning. The installed primary red/green palette needs semantic product styling and dark inverse text to retain contrast; dangerous actions now use the shared class above, with no global red-button patch. Direct button classes preserve wrapping and 44-pixel targets; department actions retain 56 pixels. Generic Button/Alert/form-field wrappers are forbidden by the architecture test.

The light orange badge contrast correction, coarse-pointer minimum sizes and the narrow product control integrations above use stable Flux data attributes. Installed 2.17 exposes no global public minimum-target prop. `callout-contrast` sets Flux's callout heading/text variables to the existing semantic text token: default green/yellow headings fail small-text contrast in light mode. The browser regression measures rendered callout contrast against actual composited backgrounds. These integrations are rechecked on upgrades; surface/header/menu/label/field navigation appearance uses supported classes and slots.

Theme utilities use Tailwind's real namespaces: `--transition-duration-state` and `--z-index-*`. Noto Sans Variable remains local with Latin, Latin-ext and Cyrillic subsets, `font-display: swap`, and the existing weight range. There are no duplicate font imports or remote font requests.

## Current shared product surfaces

The font stack is local Noto Sans Variable with Latin, Latin-ext and Cyrillic subsets. Shared navigation, buttons, fields, focus rings and state panels use semantic tokens from the canonical Sass settings map. Catalogue filters reflow by available width; locale tabs preserve state and reveal validation errors. Gallery controls, progress/retry and destructive confirmations use existing Blade/Flux primitives. The guest modal traps and restores focus; small cards stack photos below 360 pixels. See DESIGN.md for intent and testing.md for observed browser coverage.

The interface uses a restrained restaurant operations identity: high-legibility neutral surfaces, warm brand accents, direct status language, compact operational density on staff screens, and calmer public-menu presentation. Installed Flux Free and the maintained local Pro adaptation supply accessible control primitives where their APIs fit the product.

## Token contract

`resources/scss/settings/_tokens.scss` is the source of truth for semantic tokens; the CSS bridge aliases them for Tailwind:

- brand and accent;
- canvas, surface, elevated surface and border;
- text, muted text and inverse text;
- success, warning, danger and information, each with foreground/surface/border roles;
- focus ring;
- font families, size/line-height scale and weights;
- spacing, content widths and breakpoints;
- radii and shadows;
- z-index layers;
- durations, easing and motion-reduced alternatives.

Colors may use OKLCH when contrast is verified. Status always combines words/icons with color. Arbitrary one-off values must remain comprehensible; a repeated value becomes a token.

## Component principles

- Reuse Flux controls rather than recreating lower-quality inputs, buttons, dialogs, dropdowns and navigation.
- Repeated presentation patterns use anonymous Blade components with explicit props and slots.
- Default, hover, focus-visible, active, disabled, loading, invalid and high-contrast states are intentional.
- Icon-only controls have an accessible name and at least a practical touch target.
- Tables remain semantically tabular; on narrow screens use scroll regions with context or an intentional labelled card representation.
- Destructive operations are visually distinct, require confirmation where appropriate and never depend on color alone.
- Print-only QR/ticket layouts have isolated, deterministic print styles.

Related operational counts use the shared `x-ui.metric-strip` definition list instead of independent oversized cards. `x-ui.page-header` owns the one-page-heading, scope, breadcrumb and action contract. `x-ui.priority-row` renders named urgency without a decorative side stripe, `x-ui.workspace-split` keeps a stable desktop queue/detail relationship while mobile links to the full detail route, and class-based `x-ui.state-panel` covers empty, filtered-empty, loading, slow, offline, stale, validation, unauthorized, recoverable-error and fatal states.

Cards use a complete subtle border and, only where hierarchy requires it, the small `shadow-card`; wide floating shadows and colored side accents are not part of the system. Standard interactive targets are at least 44 CSS pixels. Operational actions and queue controls use the 56 CSS-pixel `operational-touch` token. Confirmations use Flux modal primitives, focus the safe cancel action for dangerous operations and restore focus to the trigger on close.

The product mark is the connected three-node service-pass symbol in `app-logo-icon.blade.php`. It is hidden from assistive technology beside a visible product name and receives an accessible name only when rendered standalone.

Visual acceptance and browser widths are defined in [`accessibility.md`](accessibility.md) and [`tailwind.md`](tailwind.md).
