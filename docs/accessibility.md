# Accessibility

The target is WCAG 2.2 AA for critical user and staff workflows. Native semantic HTML is preferred over redundant ARIA.

## Required behavior

- One logical page `h1`, correctly nested headings and landmarks.
- Every control has a visible label or explicit accessible name; descriptions/errors are programmatically associated.
- Keyboard access and visible focus for navigation, menus, dialogs, forms, QR lookup, order, kitchen, waiter and payment actions.
- Dialog focus trap and restoration; useful focus after validation, navigation and destructive action completion.
- Status/loading/error/offline changes announced without repeatedly interrupting the user.
- Sufficient contrast; statuses include text/icon; forced-colors mode keeps critical controls visible.
- Touch targets are practical, content works at 200% zoom, and long EN/LT/RU strings do not clip.
- Motion respects `prefers-reduced-motion`; no workflow requires hover, drag, animation or color perception.
- Tables expose headers and retain context on narrow screens; images use meaningful or empty alt text as appropriate.
- QR SVG/print output has an accessible textual equivalent.

## Verification workflows

Browser checks cover: login; main navigation; organization/branch setup; menu editing; public QR entry and draft submission; waiter approval; kitchen/bar progression; payment and table closure; role/permission administration; responsive navigation and dialogs. For each: keyboard-only pass, focus order/restoration, accessible names, validation association, console errors, reduced motion, forced colors where tooling permits, and 360/430/768/1024/1280/1536 widths.

Automated checks supplement rather than replace manual keyboard and screen-reader-oriented DOM inspection. Physical assistive-technology testing not executed in the current environment is reported as an environmental limitation, never as a pass.
