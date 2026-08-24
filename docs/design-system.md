# Design system

The interface uses a restrained restaurant operations identity: high-legibility neutral surfaces, warm brand accents, direct status language, compact operational density on staff screens, and calmer public-menu presentation. Flux UI Free supplies accessible control primitives where available.

## Token contract

`resources/css/app.css` is the source of truth for semantic tokens:

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
