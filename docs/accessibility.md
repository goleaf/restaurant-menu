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

- Localized skip links and native `main` landmarks are present on public, guest, auth and authenticated layouts; semantic `h1` behavior is asserted by layout/design tests.
- The Flux password toggle and modal close controls use semantic EN/LT/RU accessible-name keys; the isolated browser accessibility tree showed `Показать или скрыть пароль` and `Close dialog` in the active locale.
- Keyboard traversal covered the skip link, login controls, native waiter branch disclosure and destructive Flux dialogs. Dialog opening focuses the safe cancel action and `Escape` restores focus to its trigger.
- Browser flows covered account authentication, password confirmation, settings navigation, locale persistence, logout/login and destructive account deletion; invitation registration and public-registration rejection have automated form/route coverage.
- Public, waiter, service-point and menu mobile Lighthouse accessibility scored 100; the final checked pages had no console errors/issues or horizontal overflow, and checked critical controls were at least 40 CSS px at the 390 px touch viewport.
- Automated markup/design/architecture tests cover field labels/errors, dialog semantics, focus rules, touch tokens, reduced motion, forced colors, image dimensions, non-color status text and legacy area/service-point/menu-category icon fallback throughout first-party views.
- A 2026-08-23 waiter release smoke pass reconfirmed skip-link-first keyboard order, visible focus, semantic `h1`/`h2` hierarchy, 390 CSS-pixel reflow without horizontal overflow, and no checked interactive target below 24 CSS pixels. The complete four-test browser suite also passed in Playwright WebKit 26.5.

Chrome tooling cannot emulate a physical screen reader, switch input or actual touch hardware. That environmental evidence gap is recorded in [`known-limitations.md`](known-limitations.md); it does not waive the automated DOM, keyboard, responsive, contrast and reduced-motion checks that were executed.
