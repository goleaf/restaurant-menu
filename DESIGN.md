---
name: Restaurant Menu
description: Calm, context-first restaurant ordering and operations interface
colors:
  brand: "oklch(0.55 0.16 37)"
  brand-soft: "oklch(0.98 0.016 55)"
  canvas: "oklch(0.985 0.002 85)"
  surface: "oklch(1 0 0)"
  surface-raised: "oklch(0.995 0.001 85)"
  surface-muted: "oklch(0.97 0.004 85)"
  surface-selected: "oklch(0.98 0.016 55)"
  border-subtle: "oklch(0.9 0.008 75)"
  border-strong: "oklch(0.76 0.018 55)"
  ink: "oklch(0.21 0.018 45)"
  ink-muted: "oklch(0.48 0.025 45)"
  ink-inverse: "oklch(1 0 0)"
  success: "oklch(0.49 0.13 151)"
  success-surface: "oklch(0.96 0.035 151)"
  success-border: "oklch(0.78 0.075 151)"
  warning: "oklch(0.55 0.13 72)"
  warning-surface: "oklch(0.97 0.045 82)"
  warning-border: "oklch(0.8 0.095 72)"
  danger: "oklch(0.51 0.19 27)"
  danger-surface: "oklch(0.96 0.035 25)"
  danger-border: "oklch(0.79 0.1 27)"
  information: "oklch(0.5 0.12 245)"
  information-surface: "oklch(0.96 0.025 245)"
  information-border: "oklch(0.8 0.07 245)"
  focus: "oklch(0.55 0.16 37)"
  dark-canvas: "oklch(0.15 0.008 45)"
  dark-surface: "oklch(0.2 0.01 45)"
  dark-surface-raised: "oklch(0.23 0.012 45)"
  dark-surface-muted: "oklch(0.24 0.012 45)"
  dark-surface-selected: "oklch(0.28 0.035 37)"
  dark-border-subtle: "oklch(0.34 0.015 45)"
  dark-border-strong: "oklch(0.48 0.025 45)"
  dark-ink: "oklch(0.96 0.006 75)"
  dark-ink-muted: "oklch(0.72 0.018 55)"
  dark-ink-inverse: "oklch(0.18 0.01 45)"
  print-premium-accent: "#d6b35a"
typography:
  headline:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.25
    letterSpacing: "-0.02em"
  title:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.35
  body:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 600
    lineHeight: 1.25
  operational:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 500
    lineHeight: 1.35
rounded:
  control: "0.625rem"
  card: "0.875rem"
  dialog: "1rem"
  pill: "999px"
spacing:
  dense: "0.5rem"
  control: "0.75rem"
  section: "1.5rem"
  touch: "2.75rem"
  operational-touch: "3.5rem"
components:
  button-primary:
    backgroundColor: "{colors.brand}"
    textColor: "{colors.ink-inverse}"
    typography: "{typography.label}"
    rounded: "{rounded.control}"
    padding: "0.625rem 1rem"
    height: "{spacing.touch}"
  button-operational:
    backgroundColor: "{colors.brand}"
    textColor: "{colors.ink-inverse}"
    typography: "{typography.label}"
    rounded: "{rounded.control}"
    padding: "0.75rem 1rem"
    height: "{spacing.operational-touch}"
  container:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.card}"
    padding: "1rem"
  priority-row:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "0.75rem"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.control}"
    padding: "0.625rem 0.75rem"
    height: "{spacing.touch}"
---

# Design System: Restaurant Menu

## 1. Overview

**Creative North Star: “The Calm Service Pass”**

The interface behaves like a well-run service pass: scope, urgency and the next hand-off remain visible without visual shouting. A waiter under mixed dining-room light, a kitchen worker glancing between tasks and a guest using one hand should all understand the current state within seconds. Service Clay marks the active path; neutral surfaces protect legibility and status colors carry named operational meaning.

The system has three deliberate densities. Guest screens breathe and explain. Management screens use compact, structured lists with persistent Organization → Brand → Branch context. Operational screens prioritize age, table, state and one next valid action, with larger targets and less decorative framing. Desktop staff workspaces use queue plus detail; mobile moves from list to a normal detail page instead of shrinking a desktop board.

The product rejects generic SaaS card galleries, theme-restaurant decoration, stock food imagery, neon control-room styling and mobile layouts that merely compress desktop tables. `resources/css/app.css` is the runtime source of truth; this document defines how its semantic tokens and components are applied.

**Key Characteristics:**

- restrained clay accent for action, focus and orientation;
- visible organization, branch or table context before local controls;
- one dominant next action per task region;
- flat, bordered content structure with elevation reserved for real layering;
- prepared, localized presentation data with no Blade queries or business decisions;
- keyboard, touch, 200% zoom, reduced-motion and forced-colors resilience.

## 2. Colors

Service Clay is pointwise rather than drenched. The light and dark themes share the same semantic vocabulary: canvas, surface, raised surface, muted surface, selected surface, two border strengths, primary/muted/inverse text, focus and four named status families with surface and border companions.

### Primary

- **Service Clay:** primary action, current navigation, selected operational row and focus orientation. Never use it as decorative filler.
- **Clay Wash:** selected or contextual surfaces paired with verified readable text and a complete border.

### Neutral

- **Dining Canvas:** the quiet page background.
- **Service Surface:** the default content and control plane.
- **Raised Surface:** dropdown, docked control or other genuinely layered plane.
- **Quiet Surface:** navigation, toolbars and grouped secondary controls.
- **Primary, Muted and Inverse Ink:** stable text roles; muted text remains body-readable rather than ornamental gray.

### Named Rules

**The Active-Service Rule.** Service Clay appears on the primary action, current selection and focus—not on every icon, heading or container.

**The Complete-Status Rule.** Success, warning, danger and information use the matching text, surface and border family and always include a localized label or icon meaning. Color is never the only signal.

**The Theme-Parity Rule.** A semantic role exists in both themes. Components consume roles, never choose a raw light or dark palette independently.

**The Print-Exact Rule.** QR presets may use documented sRGB ink values where predictable physical printing matters. Those values stay inside the print boundary and never become application chrome.

## 3. Typography

**Display Font:** Instrument Sans with system sans-serif fallbacks  
**Body Font:** Instrument Sans with system sans-serif fallbacks

**Character:** One dependable humanist sans family keeps multilingual labels, operational data and guest guidance coherent. Hierarchy comes from weight, size, spacing and placement rather than a decorative display face.

### Hierarchy

- **Headline** (600, 1.5rem, 1.25): the single page `h1`; balanced wrapping, never truncated on narrow screens.
- **Title** (600, 1.125rem, 1.35): section and primary list-group headings.
- **Body** (400, 1rem, 1.5): guidance and guest content, normally capped near 70 characters per line.
- **Label** (600, 0.875rem, 1.25): controls, column headings and concise metadata in sentence case.
- **Operational** (500, 0.8125rem, 1.35): timestamps, table codes and dense secondary facts; never the only presentation of a critical state.

### Named Rules

**The Translation-Expansion Rule.** Titles and actions wrap before they truncate. Only redundant table metadata may deliberately ellipsize, with its full value still available.

**The Stable-Scale Rule.** Product typography uses fixed rem sizes. Responsive behavior changes layout, not the meaning of the hierarchy.

## 4. Elevation

Depth is structural: canvas, surfaces, complete borders and spacing define hierarchy. Ordinary cards and list groups remain flat. A small shadow may distinguish a docked action bar; the elevated shadow belongs to dialogs, menus and temporary overlays only.

### Shadow Vocabulary

- **Card** (`0 1px 2px oklch(0.21 0.018 45 / 0.08)`): rare low separation where a border is not sufficient; never paired with a decorative wide blur.
- **Docked** (`0 -4px 8px oklch(0.21 0.018 45 / 0.1)`): a mobile action region above scrolling content.
- **Elevated** (`0 18px 50px oklch(0.21 0.018 45 / 0.16)`): dialogs, dropdowns and temporary overlays.

### Named Rules

**The Flat-by-Default Rule.** A normal group uses spacing or one complete subtle border. Elevation must communicate an actual layer, interaction or docked relationship.

## 5. Components

### App Shell and Context Header

Navigation groups work by frequency: service, management and governance. The current location is visible without color alone. Every authenticated task page starts with one `h1`, concise description, prepared breadcrumbs or scope text, optional named status and one primary action region.

### Workspace Split

Desktop staff workspaces use a bounded priority queue beside a stable detail region. Selection is visible through surface, border and `aria-current`, not color alone. Below the desktop breakpoint, queue items navigate to the existing full detail route; the detail panel is never squeezed under the list.

### Priority Row

Rows lead with the subject, then age/state/context and finally the single next valid action. They use a complete border, compact radius, durable key and at least 3.5rem operational height. A selected row uses Clay Wash plus a stronger border. Urgent states use named status roles rather than colored side stripes.

### State Panel

Normal, empty, filtered-empty, loading, slow, offline, stale, validation and fatal states use one shared visual vocabulary. Empty states teach the next step. Loading uses bounded skeletons with a stable polite status region. Errors explain recovery without exposing exceptions or secrets.

### Buttons

- **Shape:** compact rounded rectangle with at least a 2.75rem target; operational controls use 3.5rem.
- **Primary:** Service Clay with inverse text and a specific verb-object label.
- **Hover / Focus / Active:** state feedback completes within 150–200ms; focus uses the named two-pixel ring with offset.
- **Loading / Disabled:** loading preserves the action label and disables only the request in flight; disabled meaning is available in text.
- **Danger:** danger vocabulary plus consequence, reason where required and a safe cancel path.

### Cards and Containers

- **Corner Style:** `0.875rem` maximum for normal content containers.
- **Background:** Service Surface or Quiet Surface according to hierarchy.
- **Border:** one subtle complete border only when grouping needs an edge.
- **Internal Padding:** `1rem` on mobile, increasing only when reading rhythm benefits.
- **Nesting:** avoid nested cards; use sections, dividers and lists.

### Inputs and Fields

- **Style:** visible label, Service Surface, subtle border, `0.625rem` radius and at least 2.75rem height.
- **Focus:** named accent ring plus offset remains visible in light, dark and forced-colors modes.
- **Error / Disabled:** localized recovery text is programmatically associated with the field; placeholders are never labels.

### Product Mark

The mark is a monochrome 24×24 “service pass”: three equal rounded nodes connected by one continuous path. It uses `currentColor`, no food/cutlery motif and no decorative texture. Beside the wordmark it is decorative; when shown alone it receives the localized product name.

## 6. Do's and Don'ts

### Do:

- **Do** make context, current state and one next valid action understandable within seconds.
- **Do** use guest, management and operational density intentionally.
- **Do** use semantic tokens, native HTML and existing Flux controls before creating custom interaction behavior.
- **Do** verify EN/LT/RU expansion, keyboard order, 200% zoom, 320px reflow, reduced motion and forced colors.
- **Do** keep long names and labels wrapping safely with `min-width: 0` on flex children.
- **Do** preserve full detail routes as the mobile counterpart of desktop queue-plus-detail workspaces.

### Don't:

- **Don't** build generic SaaS dashboards from identical floating cards, oversized hero metrics, purple gradients or decorative glass.
- **Don't** add theme-restaurant ornament, stock food imagery or nostalgic motifs that compete with the task.
- **Don't** use dense black control-room styling, neon inactive states or tiny controls.
- **Don't** shrink desktop tables or boards into unusable mobile layouts or require hover, drag or color perception.
- **Don't** use a colored side stripe, gradient text, over-rounded containers or border-plus-wide-shadow ghost cards.
- **Don't** add motion, badges, uppercase eyebrows or numbered markers as decoration.
- **Don't** expose models, query builders, internal secrets or authorization decisions to Blade or public Livewire state.
