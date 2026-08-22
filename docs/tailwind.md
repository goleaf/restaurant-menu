# Tailwind CSS 4

Tailwind is integrated through `@tailwindcss/vite` and a CSS-first stylesheet. The build uses `@import "tailwindcss"`, explicit first-party/vendor `@source` paths, `@theme` design variables, and narrowly justified custom variants/utilities. Obsolete Tailwind 3/PostCSS configuration is not retained after all behavior has been migrated.

## Source and class rules

All Blade, PHP-rendered class maps and Flux Free templates required by the application are discoverable. Flux Pro sources are included only if a licensed Pro package is installed. Runtime fragments such as `text-${color}-500`, `bg-${status}` and `grid-cols-${count}` are prohibited; use complete controlled class maps or source-detectable declarations.

Repeated arbitrary values become theme variables or intentional utilities. `@apply` is not the default component abstraction; repeated structures use Blade/Flux components and design tokens.

## Feature applicability

| Feature | Candidate / decision | Responsive/accessibility effect | Browser consideration | Evidence |
|---|---|---|---|---|
| CSS-first `@theme` | Used for color, type, space, radius, shadow, transition and layers | consistent responsive UI and contrast | CSS variables require modern supported browsers | build + visual review |
| `@source` | Used for first-party Blade/PHP and installed Flux Free paths | prevents missing production utilities | validate minified build | build/smoke |
| Container queries | Reusable cards/toolbars that live in variable-width panes | component responds to container, not viewport | progressive modern CSS | viewport matrix |
| Logical utilities | Navigation, forms, metadata and icon spacing | RTL-ready and direction independent | native logical properties | LTR/long-text review |
| aria/data/has/not/group/peer variants | controls and stateful lists | state visible beyond color; less custom JS | supported target browsers | browser/a11y tests |
| Reduced-motion/forced-colors/contrast variants | focus, transitions and status controls | respects preferences and high contrast | OS emulation/manual review | browser review |
| Dynamic viewport units | mobile full-height shells/dialogs where required | avoids browser chrome clipping | mobile viewport check | responsive smoke |
| Text shadow/masks/zoom/tab-size | Not currently needed for product UI | avoid decorative/maintenance cost | n/a | design review |
| View-transition utilities | Only with `wire:navigate` and orientation value | reduced-motion fallback required | navigation lifecycle | browser test |

## Responsive matrix

The accepted representative widths are 360, 430, 768, 1024, 1280 and 1536 CSS pixels. Base styles target the smallest viewport. Navigation, cards, forms, filters, dialogs, tables, pagination, kitchen/waiter boards, QR print layouts and long localized strings must have no page-level horizontal overflow. Touch targets do not rely on hover.

Design variables and component principles are in [`design-system.md`](design-system.md).
