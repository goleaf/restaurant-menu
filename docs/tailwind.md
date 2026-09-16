<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Tailwind CSS 4

## Current SCSS / Alpine boundary — 2026-09-16

The accepted migration supersedes earlier native-CSS-only and page-script decisions for first-party code. `resources/css/app.css` is only the Tailwind/installed Flux bridge. `resources/scss/app.scss` owns product tokens, light/dark variables, fonts, base accessibility and semantic compositions; `qr-print.scss` is a print-only entry. `pdf-qr.scss`, `pdf-report.scss` and `emergency.scss` compile into fixed committed `resources/views/generated/styles/*.blade.php` artifacts, so emergency/PDF rendering never needs a Vite manifest or runtime Node. `resources/build/styles.js` generates token aliases and concrete breakpoints automatically in both Vite build and dev/HMR; `npm run styles:check` detects drift. Do not edit generated CSS.

Cascade order remains Tailwind `theme, base, components, utilities`. Sass emits into existing layers and preserves narrowly scoped unlayered accessibility fixes; there is no new reset. Runtime `--rm-*` values have one canonical Sass map; generated `@theme inline static` aliases resolve the current Flux `.dark` appearance without another theme store. QR physical geometry stays 76×104 mm, 48 mm code plus quiet zone and 8 mm A4 margins. Emergency actions now meet the product's 44 px minimum.

`resources/js/app.js` imports the installed Livewire ESM runtime and its Alpine instance, registers named component factories/bindings, then calls `Livewire.start()` once. Compatibility palette/radius values used by semantic compositions are retained in the same map; static aliases prevent Tailwind pruning variables used only by the separate SCSS output. Every layout supplies `@livewireScriptConfig` before retained `@fluxScripts`. No independent Alpine package or `Alpine.start()` exists. Registration is cheap and synchronous; the WebAuthn SDK loads only when a ceremony starts. Local panel/focus/clipboard/preview/timer/audio state belongs to Alpine; forms, filters, uploads and mutations belong to class-based Livewire and existing Actions. The obsolete twelve root page/behavior modules are removed.

Transport allowlist: Fortify authentication; the single first-party `fetch` boundary in `resources/js/integrations/passkeys.js` for WebAuthn options/credential HTTP protocol; invitation credential/identity transitions; demo identity entry; authorized CSV/PDF/media/SQLite downloads; exclusive-barrier SQLite restore POST. Restore must obtain its lock before authentication/session reads and therefore cannot be moved behind the ordinary Livewire update transport. Static landing/error/PDF/email and navigation/redirect responses remain SSR. `LivewireInteractionBoundaryTest` pins all 30 class-based page routes, 17 HTTP exceptions and 15 CSRF-protected native POST forms; existing negative/replay/tenant tests exercise those boundaries. There were no first-party fetch/XHR/Axios requests at migration baseline, so none are claimed removed. The sole added passkey adapter replaces `@laravel/passkeys` with its installed lower-level `@simplewebauthn/browser` SDK pinned to 13.3.0: cryptographic browser operations stay in the SDK, while owned GET/POST requests enforce same-origin URLs, credentials, CSRF and redirect rejection. Every await checks the current owner; destroy/cancel aborts its request and ceremony. A cancelled POST has an unknown server outcome and never produces a local success claim. `alpine-passkeys-adapter.test.mjs` tests the real SDK after a cancelled delayed GET and controlled cancel/error/success transport boundaries. Application CRUD continues through Livewire.

Quality entrypoint: `npm run verify:migration` executes manifest validation, architecture rules, Stylelint, ESLint, Pint, Larastan, production build, complete PHP discovery/execution, JS coverage, PHP coverage, isolated browser coordinator, translations and budgets with real exit codes. This command defines verification; its presence does not imply it has passed. Exact current results and open gates belong in `PROGRESS.md` and `IMPLEMENTATION_PLAN.md`; older dated counts below remain historical.


## 2026-09-16 — Native CSS cascade and delivery

This continuation's verified Free slice keeps three native CSS source files and reduces `app.css` from 216 to 212 lines. `app.css` imports Tailwind, installed Flux CSS and local `fonts.css`; fonts now compile into the same CSS request. The separate `qr-print.css` Vite entry and explicit source list remain unchanged within this stage. No source path, palette or language subset is removed. A subsequently added Pro source belongs to the parallel integration and is outside these measurements and this stage's prepared diff.

No cascade-layer migration is needed: preserve Tailwind's theme/base/components/utilities ordering and the existing narrowly scoped accessibility exceptions. Replace the global selector tied to Flux's `text-orange-700` utility with supported class attributes on the three actual orange badge callers. Their light appearance uses static `not-dark:text-orange-900`; dark appearance keeps the upstream treatment. Deduplicate coarse-pointer control/button selectors without changing 44/56-pixel targets. Staff summary flex bases allow long action groups to wrap by actual container width, without new CSS or viewport-specific wrappers. Only three vendor data-selector occurrences remain, all in the coarse-pointer accessibility integration. The generic data-loading and red-button patches were already absent at this baseline and are not new removals.

`npm run build` now also executes the manifest-driven `build:check`. `tests/frontend-budget.json` specifies entry, full-build and deduplicated scenario ceilings; `npm run test:assets` checks its measuring engine with synthetic missing-file, cycle, path, font and exact-boundary cases. Measurements and the rationale for five-percent headroom are recorded in [performance.md](performance.md).

## Unified workspace continuation — 2026-09-16

The existing three-file native CSS architecture and 216-line app.css remain intact. Navigation, account preferences, branch controls and notification presentation use available Flux components and static Tailwind classes without another generic wrapper layer. The retained six vendor-selector occurrences remain limited to the documented orange-badge contrast and coarse-pointer touch integrations. New error contrast uses supported `class` / `error:class` with `text-danger!`, avoiding a global vendor error override.

Print CSS now applies the existing paper variable to both document root and body only under print media, preventing the dark appearance from coloring empty parts of later PDF pages. Sticker dimensions and preset rules are unchanged. Current before/after assets and HTML/poll payload measurements are in `performance.md`; local Noto Sans and all three language subsets remain unchanged.


Tailwind 4.3.3 is integrated directly through `@tailwindcss/vite` 4.3.3. [`resources/css/app.css`](../resources/css/app.css) is CSS-first: `@import 'tailwindcss' source(none)`, explicit `@source` paths for first-party PHP/Blade/JavaScript, Laravel pagination and installed Flux Free stubs, `@custom-variant dark`, an OKLCH `@theme` token system and a small `@utility touch-target`. No Tailwind 3 JavaScript/PostCSS configuration, Sass/Less, active Flux Pro source path or unsafe runtime class construction remains.

## Flux Pro preparation — 2026-09-15

The internal source snapshot at `packages/livewire/flux-pro/` is not a Tailwind or runtime entrypoint. P1/P14 of the [integration plan](superpowers/plans/2026-09-15-flux-pro-integration.md) will source the accepted installed Pro templates only after Composer compatibility is resolved. Refresh the physical vendor mirror before Vite; verify its digest matches the accepted internal source. Do not scan both root/internal copies or broaden sources to the entire repository. Measure final CSS and lazy editor assets against the current build when activation occurs; copying source alone changes no bundle.

## Design tokens

The theme defines brand scale, canvas/surface/raised/selected/border/text roles, success/warning/danger/information foreground-surface-border roles, focus colors, font stack, 44-pixel touch and 56-pixel operational-touch targets, content/reading containers, extra-small breakpoint, control/card/dialog radii, restrained elevation shadows and product easing. Critical controls have visible focus rings; status includes text/icon; reduced-motion and forced-colors rules are explicit. Repeated QR print values remain domain-specific semantic CSS because printer labels require exact colors/aspect ratios.

## Feature applicability

| Feature | Decision and location | Responsive/accessibility effect | Verification |
|---|---|---|---|
| CSS-first `@theme`, `@source`, custom dark variant | used in `app.css` | coherent sources/tokens, no purged production utilities | architecture tests and build |
| OKLCH semantic colors | used for application tokens | maintainable contrast roles; status never color-only | design tests and Lighthouse |
| Logical utilities/properties | used in navigation, dialogs and component spacing | direction-independent start/end layout | long-text/locale review |
| Reduced motion / forced colors | explicit media rules in `app.css` | motion/high-contrast preferences retained | CSS/design tests |
| Dynamic viewport units and safe-area insets | used for mobile sheets, action docks and print shells where needed | avoids browser-chrome clipping and keeps one-hand actions reachable | responsive browser checks |
| Data/ARIA/group/peer variants | used where component state benefits | state remains semantic with minimal custom JS | markup/browser tests |
| Container queries | not applicable: current reusable panes respond correctly to viewport/grid and have no independent container-width contract | avoids needless complexity | layout review |
| Text shadows, masks, zoom, tab-size | not applicable to product workflows | avoids decorative/maintenance cost | design review |
| View transitions | not added: normal `wire:navigate` orientation and focus behavior is sufficient | avoids decorative motion | browser navigation review |

## Current CSS ownership

| Category | Retained ownership |
| --- | --- |
| Theme/design tokens | `app.css`: semantic `@theme`, dark token overrides; `fonts.css`: local subset faces |
| Reusable utility | `workspace-nav-item`, `touch-target`, `content-safe`, `skip-link`, `callout-contrast` |
| Component integration | Supported Flux accent/callout variables and `x-cloak` |
| Domain print | `qr-print.css`, a separate Vite entry included only by the print layout |
| Accessibility compatibility | Focus, reduced motion, forced colors, light orange badge contrast and coarse-pointer minimum targets |
| Removed redundancy | Old universal border fallback; duplicated body/theme rules; menu layout aliases; global Flux surface/field/label selectors; unused published templates and control clones |

## First pass measurements — 2026-09-15

First-party CSS changes from **2 files / 649 lines / 18,405 bytes** to **3 files / 468 lines / 13,731 bytes**. `app.css` shrinks from 620 to 244 lines. `@apply` occurrences fall from 20 to 10 (eight remaining are physical QR composition); Flux data-selector occurrences fall from 26 to 8, all remaining ones serve accessibility. No preprocessor or legacy Tailwind/PostCSS configuration is introduced. Explicit sources retain all installed Free stubs and Laravel pagination; source scanning is disabled by default with `source(none)` and first-party JavaScript is explicitly included.

| Production asset | Before bytes | After bytes | Before gzip bytes | After gzip bytes |
| --- | ---: | ---: | ---: | ---: |
| Main CSS | 310,242 | 298,374 | 41,173 | 39,288 |
| Font CSS | 964 | 964 | 398 | 398 |
| Print CSS, loaded only on print layouts | included above | 6,713 | included above | 1,689 |
| All generated CSS | 311,206 | 306,051 | 41,571 | 41,375 |
| Application JS | 22,760 | 22,760 | 6,212 | 6,212 |

Gzip comparison uses Python `gzip.compress(..., compresslevel=9, mtime=0)` consistently, independent of Vite's rounded display. Baseline build took 3.62 seconds; final build 835 ms (timing is environment-sensitive). The main CSS is 3.8% smaller; total CSS including the separate print entry is 1.7% smaller. Noto subset binaries are unchanged. Broad Flux sources remain because they provide required component variants; no unsupported byte-saving source exclusions were made.

QR print keeps 76×104 mm stickers, 2-column A4 layout, 8 mm page margins, exact colors and `break-inside: avoid`. A 48 mm square QR, 4 mm inner padding and bounded logo/title/code geometry prevent the old code-label overflow. All six presets with optional table numbers fit in EN/LT/RU print emulation. The exported seven-sticker PDF has two A4 pages (four and three complete labels), verified by page rasterization. Screen previews reuse the same domain classes. PDF templates retain their separate renderer-compatible CSS; emergency error pages retain self-contained styles when Vite is unavailable. Bounded image focal positions and area indentation remain data-dependent inline styles. Other screen layout is expressed with static Tailwind utilities; no new arbitrary style blocks or dynamic class fragments were added.

Scrollbar styling, decorative masks/shadows and additional variants did not remove a current requirement or existing rule, so they were not added. Existing logical properties and viewport units remain sufficient.

## Earlier responsive/build evidence (historical)

- Production build: CSS 303.36 kB / 39.72 kB gzip; application JS 4.56 kB / 1.73 kB gzip; Vite 8.2.2 completed in 658 ms on the final run.
- Public entry has no horizontal overflow at 360/390/430/768/1024/1440/1920 CSS px. Waiter checks covered 390/768/1024/1440; service points covered 390/768/1440; menu covered 390/1440 in light and dark modes.
- Operational metrics use two columns on small touch screens and six on desktop. Checked waiter and service-point controls meet the practical touch-target contract at 390 CSS px.
- Public, waiter, service-point and menu mobile Lighthouse samples scored 100 in every reported category; final console inspection found no errors or issues.

Component principles and token roles are in [`design-system.md`](design-system.md). Physical-device and non-Chromium evidence limits are recorded in [`known-limitations.md`](known-limitations.md).

## Second pass — native CSS and Flux ownership

Removed the unused control-active, brand-400/500/600 and toast/overlay z-index tokens, the redundant generic data-loading rule, the global red-button patch and eleven Zinc-to-Neutral aliases. Those aliases were not identical to Tailwind Zinc; no durable palette-pinning contract required them. Flux now uses Tailwind's standard Zinc while the application retains its semantic warm surfaces/text/status tokens. `callout-contrast` is the small accessibility integration for the installed callout variables. No source path or local Noto subset/weight range was removed.

QR paper/ink and repeated preset border/accent values share domain CSS variables; physical dimensions remain unchanged. Three focused native CSS files remain sufficient. Stylesheets stay directly in the document head; Laravel's supported preload callback omits redundant CSS preloads that generated warnings after `wire:navigate`, while script module preloads remain enabled.


| Second-pass measure | Before | After |
| --- | ---: | ---: |
| PHP UI components | 12 | 7 |
| Blade UI components | 19 | 14 |
| Published Flux templates | 2 | 2 |
| Native CSS files / total lines | 3 / 468 | 3 / 442 |
| `app.css` lines | 244 | 216 |
| Main CSS bytes / gzip bytes | 298,374 / 39,288 | 295,175 / 39,026 |
| Font CSS bytes / gzip bytes | 964 / 398 | 964 / 398 |
| Print CSS bytes / gzip bytes | 6,713 / 1,689 | 6,895 / 1,708 |
| All CSS bytes / gzip bytes | 306,051 / 41,375 | 303,034 / 41,132 |
| Application JS bytes / gzip bytes | 22,760 / 6,212 | 22,760 / 6,212 |
| Vite production build | 629 ms | 728 ms |

These measurements compare the second pass against its own baseline, with the same gzip settings as above. Main CSS decreases by 3,199 bytes; all CSS decreases by 3,017 bytes (243 bytes gzip). Print CSS grows slightly because explicit shared custom properties replace literals; this keeps physical output maintainable. `@apply` remains at 10 occurrences; vendor data-selector occurrences decrease from 8 to 6, restricted to documented contrast and coarse-pointer target compatibility. Build time is environment-sensitive, not a performance guarantee.
