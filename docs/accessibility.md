<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Accessibility

## Repeated form errors and local dismissal — 2026-09-15

Keep translated forms' `novalidate` attribute in Blade, not only in initialization JavaScript. Server errors remain required, localized and connected to visible fields; repeated failures reopen the corresponding panel. Guest Close/Escape is independent of connectivity, releases inert/focus trapping and returns focus even when the card has disappeared. Offline language and pending-photo controls are disabled without discarding existing selections. The browser regressions verify these interactions; synthetic offline events and WebKit viewport emulation are not physical-device or screen-reader verification.

## Recovery focus and offline actions — 2026-09-15

A guest detail panel whose originating card disappears returns focus to the menu heading (`tabindex="-1"`) after Livewire closes it. Both Escape and the visible close control use the same path; normal cards still recover their trigger focus. The availability error remains visible within the panel and the retry is keyboard operable.

Staff and guest mutation buttons expose the actual disabled state while offline and recover on reconnection. The existing text status remains visible; color and sound are never the only signal. Kitchen/bar action targets remain 56 pixels while compact timers reduce decorative vertical space. Real browser evidence, including viewport/theme coverage and the limits of emulation, belongs in [testing](testing.md).

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

The executable target contract is 44 CSS pixels for ordinary controls and 56 CSS pixels for operational queue/status actions. Queue selection uses `aria-current` plus surface and border changes, never color alone. Desktop queue previews always retain an ordinary mobile detail link; polling may clear a no-longer-visible selection but does not move focus.

## Final verification evidence

- Localized skip links and native `main` landmarks are present on public, guest, auth and authenticated layouts; semantic `h1` behavior is asserted by layout/design tests.
- The Flux password toggle and modal close controls use semantic EN/LT/RU accessible-name keys; the isolated browser accessibility tree showed `Показать или скрыть пароль` and `Close dialog` in the active locale.
- Keyboard traversal covered the skip link, login controls, native waiter branch disclosure and destructive Flux dialogs. Dialog opening focuses the safe cancel action and `Escape` restores focus to its trigger.
- Browser flows covered account authentication, password confirmation, settings navigation, locale persistence, logout/login and destructive account deletion; invitation registration and public-registration rejection have automated form/route coverage.
- Public, waiter, service-point and menu mobile Lighthouse accessibility scored 100; the final checked pages had no console errors/issues or horizontal overflow, and checked visible controls meet the current 44 CSS-pixel target at the 390 px touch viewport.
- Automated markup/design/architecture tests cover field labels/errors, dialog semantics, focus rules, touch tokens, reduced motion, forced colors, image dimensions, non-color status text and legacy area/service-point/menu-category icon fallback throughout first-party views.
- A 2026-08-23 waiter release smoke pass reconfirmed skip-link-first keyboard order, visible focus, semantic `h1`/`h2` hierarchy and 390 CSS-pixel reflow without horizontal overflow. The later production-ready pass superseded its preliminary target sampling and verified the 44/56 CSS-pixel contract.
- The completed `/organizations` browser journey covers all nested management pages at 375×812 and 1,440×1,000, QR confirmation-dialog focus, 200% root text scaling, named visible controls and zero horizontal overflow. Both desktop and mobile profile triggers now expose the localized account name (`Open account menu for :name` and EN/LT/RU equivalents). A post-build disposable Chrome pass confirmed those names in the accessibility tree and no new console or network failures.
- The 177-assertion restaurant onboarding browser journey covers EN/LT/RU, 320–1,440 CSS-pixel widths, 200% root text scaling, keyboard-visible focus, first-invalid-field focus, backward editing, two refresh/resume checkpoints and the complete successful flow. Native progress and non-color current/completed/future labels remain available, all validation errors are explicitly associated with their controls, and the final run reported no JavaScript errors or horizontal overflow.

Chrome tooling cannot emulate a physical screen reader, switch input or actual touch hardware. That environmental evidence gap is recorded in [`known-limitations.md`](known-limitations.md); it does not waive the automated DOM, keyboard, responsive, contrast and reduced-motion checks that were executed.
