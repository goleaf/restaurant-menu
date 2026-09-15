<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Tailwind CSS 4

Tailwind 4.3.3 is integrated directly through `@tailwindcss/vite` 4.3.3. [`resources/css/app.css`](../resources/css/app.css) is CSS-first: `@import 'tailwindcss' source(none)`, explicit `@source` paths for first-party PHP/Blade/JavaScript, Laravel pagination and installed Flux Free stubs, `@custom-variant dark`, an OKLCH `@theme` token system and a small `@utility touch-target`. No Tailwind 3 JavaScript/PostCSS configuration, Sass/Less, Flux Pro source or unsafe runtime class construction remains.

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

## CSS architecture and measured cleanup — 2026-09-15

| Category | Retained ownership |
| --- | --- |
| Theme/design tokens | `app.css`: semantic `@theme`, dark token overrides, neutral aliases; `fonts.css`: local subset faces |
| Reusable utility | `workspace-nav-item`, `touch-target`, `content-safe`, `skip-link` |
| Component integration | Supported Flux accent configuration; loading cursor and `x-cloak` |
| Domain print | `qr-print.css`, a separate Vite entry included only by the print layout |
| Accessibility compatibility | Focus, reduced motion, forced colors, light badge/danger contrast and coarse-pointer minimum targets |
| Removed redundancy | Old universal border fallback; duplicated body/theme rules; menu layout aliases; global Flux surface/field/label selectors; unused published templates and control clones |

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
