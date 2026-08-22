---
name: Restaurant Menu
description: Calm, high-legibility restaurant ordering and operations interface
colors:
  brand: "oklch(0.55 0.16 37)"
  brand-soft: "oklch(0.95 0.035 52)"
  canvas: "oklch(0.985 0.002 85)"
  surface: "oklch(1 0 0)"
  surface-muted: "oklch(0.97 0.004 85)"
  border-subtle: "oklch(0.9 0.008 75)"
  ink: "oklch(0.21 0.018 45)"
  ink-muted: "oklch(0.48 0.025 45)"
  success: "oklch(0.49 0.13 151)"
  warning: "oklch(0.55 0.13 72)"
  danger: "oklch(0.51 0.19 27)"
  information: "oklch(0.5 0.12 245)"
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
rounded:
  control: "0.625rem"
  card: "0.875rem"
  dialog: "1rem"
spacing:
  compact: "0.5rem"
  control: "0.75rem"
  section: "1.5rem"
  touch: "2.75rem"
components:
  button-primary:
    backgroundColor: "{colors.brand}"
    textColor: "{colors.surface}"
    typography: "{typography.label}"
    rounded: "{rounded.control}"
    padding: "0.625rem 1rem"
    height: "{spacing.touch}"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.card}"
    padding: "1rem"
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

Like a well-run service pass, the interface keeps context, state and the next hand-off in view without raising its voice. Warm brand color signals the active path; neutral surfaces preserve legibility in a dining room, at the bar and in the kitchen. Guest screens breathe and explain, while staff screens become denser only where repeated operational work benefits.

The product rejects generic SaaS card galleries, theme-restaurant decoration, neon control-room styling and mobile layouts that merely shrink desktop tables. The implementation source of truth remains `resources/css/app.css`; this document describes how its tokens should be applied.

**Key Characteristics:**

- restrained warm accent used for action and orientation;
- high-legibility neutral surfaces with explicit semantic status;
- one clear page heading and primary action per task context;
- familiar controls, practical touch targets and progressive disclosure;
- calm guest pacing and faster operational density for staff.

## 2. Colors

The palette combines clay-toned brand color with nearly neutral canvas and ink, reserving green, amber, red and blue for named status.

### Primary

- **Service Clay** (`oklch(0.55 0.16 37)`): primary actions, active navigation and the current step; it is not decorative filler.
- **Clay Wash** (`oklch(0.95 0.035 52)`): low-emphasis selected or contextual surfaces paired with verified dark text.

### Neutral

- **Dining Canvas** (`oklch(0.985 0.002 85)`): light page background.
- **Service Surface** (`oklch(1 0 0)`): controls and content planes.
- **Quiet Surface** (`oklch(0.97 0.004 85)`): toolbars, secondary regions and grouped controls.
- **Primary Ink** (`oklch(0.21 0.018 45)`): headings and body text.
- **Muted Ink** (`oklch(0.48 0.025 45)`): secondary copy only when its contrast remains at least 4.5:1.

### Named Rules

**The Active-Service Rule.** Service Clay appears on primary action, current selection and focus—not on every icon, heading or card.

**The Status Vocabulary Rule.** Success, warning, danger and information always include text or icon meaning in addition to color.

## 3. Typography

**Display Font:** Instrument Sans with system sans-serif fallbacks  
**Body Font:** Instrument Sans with system sans-serif fallbacks

**Character:** One dependable humanist sans family keeps multilingual labels, operational data and guest guidance coherent. Hierarchy comes from weight, size and spacing rather than a decorative display face.

### Hierarchy

- **Headline** (600, 1.5rem, 1.25): the single page `h1`; balanced wrapping, never truncated on narrow screens.
- **Title** (600, 1.125rem, 1.35): sections and primary list groups.
- **Body** (400, 1rem, 1.5): instructions and guest content, normally capped near 70 characters per line.
- **Label** (600, 0.875rem, 1.25): controls, table headings and compact metadata; sentence case rather than tracked uppercase.

### Named Rules

**The Translation-Expansion Rule.** Titles and actions wrap before they truncate; only redundant table metadata may deliberately ellipsize.

## 4. Elevation

Depth is primarily structural: canvas, surface, borders and spacing define hierarchy. The small card shadow is reserved for a genuinely raised or interactive surface; the elevated shadow belongs to dialogs and menus. A wide decorative shadow is never paired with a one-pixel ghost border simply to make a card feel designed.

### Shadow Vocabulary

- **Card** (`0 1px 2px oklch(0.21 0.018 45 / 0.06), 0 8px 24px oklch(0.21 0.018 45 / 0.06)`): sparingly for actionable guest or summary surfaces.
- **Elevated** (`0 18px 50px oklch(0.21 0.018 45 / 0.16)`): dialogs, dropdowns and temporary overlays.

### Named Rules

**The Flat-by-Default Rule.** Ordinary groups use spacing or one subtle border. Elevation must communicate layering or interaction.

## 5. Components

### Buttons

- **Shape:** compact rounded rectangle (`0.625rem`), with a `2.75rem` practical minimum target.
- **Primary:** Service Clay with white text and a specific verb-object label.
- **Hover / Focus:** increase contrast on hover-capable devices; use a visible two-pixel focus ring with offset; loading disables only the action in flight.
- **Secondary / Ghost:** neutral surface or transparent background; destructive actions use the danger vocabulary and explicit confirmation.

### Chips

- **Style:** compact labels for status or filters, not decoration; selected state combines surface, border and text/icon changes.
- **State:** every status displays a localized word; color is redundant reinforcement.

### Cards / Containers

- **Corner Style:** `0.875rem` maximum for normal content containers.
- **Background:** Service Surface or Quiet Surface according to hierarchy.
- **Shadow Strategy:** flat by default; Card shadow only for a raised task surface.
- **Border:** use one subtle full border when grouping needs an edge; never a colored side stripe.
- **Internal Padding:** `1rem` on mobile, increasing only when reading rhythm benefits.

### Inputs / Fields

- **Style:** visible label, white surface, subtle border, `0.625rem` radius and at least `2.75rem` height.
- **Focus:** accent ring plus offset remains visible in light, dark and forced-colors modes.
- **Error / Disabled:** inline localized recovery text is associated with the input; disabled state is not color-only.

### Navigation

Navigation uses familiar links, a collapsible staff sidebar and a compact mobile header. Current location is visible without color alone. Guest navigation stays minimal and keeps the table/menu task dominant. Same-origin Livewire navigation preserves normal link behavior, focus, title and scroll orientation.

### Operational Queue

Waiter, kitchen and bar rows prioritize age, table, state and the single next valid action. Targets are glove- and touch-friendly, updates are announced without stealing focus, and stale or conflicting actions recover in place.

## 6. Do's and Don'ts

### Do:

- **Do** make context, current state and one next valid action understandable within seconds.
- **Do** keep guest guidance calm while giving staff queues a denser, scan-friendly rhythm.
- **Do** use semantic tokens, native HTML and Flux controls before creating a custom control.
- **Do** verify EN/LT/RU expansion, keyboard order, 200% zoom, 320px reflow, reduced motion and forced colors.
- **Do** keep long names and labels wrapping safely with `min-width: 0` on flex children.

### Don't:

- **Don't** build generic SaaS dashboards from identical floating cards, oversized hero metrics, purple gradients or decorative glass.
- **Don't** add theme-restaurant ornament, stock food imagery or nostalgic motifs that compete with the task.
- **Don't** use dense black control-room styling, neon inactive states or tiny controls.
- **Don't** shrink desktop tables into unusable mobile layouts or require hover, drag or color perception.
- **Don't** use motion, badges or color as decoration; every treatment must clarify orientation, state or feedback.
- **Don't** use a colored side stripe, gradient text, over-rounded containers or nested cards as default styling.
