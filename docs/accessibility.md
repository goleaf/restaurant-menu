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

## Final verification evidence

- Native `main` landmarks were added to all auth and authenticated layouts; one logical `h1` is asserted by layout tests.
- The Flux password toggle and modal close controls use semantic EN/LT/RU accessible-name keys; the isolated browser accessibility tree showed `Показать или скрыть пароль` and `Close dialog` in the active locale.
- Keyboard traversal covered login fields, password toggle, reset link, remember checkbox, submit and registration navigation with visible focus; native email validation remained active.
- Browser flows covered registration, password confirmation, settings navigation, locale persistence, logout/login and destructive account deletion; focus traps and controls remained reachable.
- Lighthouse accessibility scored 100 on the public page, login and authenticated dashboard samples; console inspection found no warnings, errors or issues.
- Automated markup/design/architecture tests cover field labels/errors, dialog semantics, focus rules, touch tokens, reduced motion, forced colors and non-color status text throughout first-party views.

Chrome tooling cannot emulate a physical screen reader, switch input or actual touch hardware. That environmental evidence gap is recorded in [`known-limitations.md`](known-limitations.md); it does not waive the automated DOM, keyboard, responsive, contrast and reduced-motion checks that were executed.
