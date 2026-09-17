<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Accessibility

## Frontend delivery accessibility — 2026-09-16

Failed critical screen scripts leave the editor inert with a translated, native reload link; no automatic reload discards input. Successful initialization removes the barrier before Alpine walks the editor. Operational script failure explains the unavailable sound/timer enhancement while server controls and local dismissal remain usable. History pagination announces the displayed scope and focuses its heading after visibility has painted; offline fieldsets preserve disabled boundary buttons after reconnection.

Chrome Herd checks cover 320/390/768/1024/1440 layouts, EN/LT/RU, actual Flux light/dark/system state, CSS zoom 200%, emulated forced colors/reduced motion and keyboard skip-link focus (visible 2px outline). Emulated coarse-pointer controls measure 44px and operational actions at least 56px. Staff summaries now wrap long actions instead of collapsing names to individual letters, including inside a narrower editor layout. Screenshots were inspected and this defect was corrected with a failing/passing browser regression. QR print remains white in dark appearance with unchanged 76×104mm geometry and break avoidance. These checks do not certify a physical keyboard/phone, virtual keyboard, screen reader or printer.


## Flux Pro acceptance boundary — 2026-09-15

The installed local adaptation has server-render evidence, with browser/assistive-technology acceptance pending. Test actual native dialog names and focus restoration; combobox/listbox/pillbox names, keyboard selection and empty/error states; tabs with repeated locale groups and hidden validation; calendar/time keyboard paths; and upload progress/removal. Composer shortcuts must preserve multiline input and IME composition. Context menus need ordinary visible actions; Kanban needs keyboard/touch operation and a mobile alternative; charts need equivalent accessible data.

Verify EN/LT/RU, 320px layouts, 200% zoom, reduced motion, forced colors and existing target-size/contrast requirements. Old Free browser results cannot be reused as proof for replaced Pro behavior. Each accepted family needs a workflow-level result in the [execution ledger](IMPLEMENTATION_PLAN.md).

## Unified workspace checks — 2026-09-15

The shared sidebar has translated names/tooltips in compact mode; section search restores focus and ignores shortcuts during text entry or another modal. The bell opens a labelled, scrollable Flux panel with explicit read status/actions, locally available Close, loading and persistent offline/error states. Branch radio cards retain visible descriptions and 44px targets inside a bounded scroll area; their group announces disabled state during offline/loading. Explicit description/error IDs protect the actual installed Flux association behavior. Dangerous confirmation inputs retain names, validation and safe Cancel autofocus. The local component reference exercises independent draft preservation and translated states for browser verification.


## Second Flux cleanup pass — 2026-09-15

Product state panels retain their status/alert roles, polite announcements, busy state and actions while using Flux Callout/Skeleton. A small public callout-variable utility fixes the installed green/yellow heading contrast: the browser regression reproduces the former failure and requires at least 4.5:1. Flux fields own label/error association for migrated guest entry, notes and confirmations. Guest entry keeps a 56-pixel field; normal controls retain 44-pixel touch targets.

Guest draft and dangerous-confirmation native dialogs have heading-based accessible names, safe initial focus, Escape dismissal and trigger restoration. Browser tests inspect the native dialog rather than accepting an ARIA attribute on Flux's outer custom element. Dialog max-width and wrapped actions remain within a 390-pixel viewport at 200% text size. EN/LT/RU, light/dark/system, forced colors, reduced motion, and seven viewport widths are exercised as recorded in `testing.md`. These are browser and markup checks; no physical assistive-technology certification is claimed.

## Flux modernization checks — 2026-09-15

Disposable Chrome on Herd verified translated password names and pressed state, Space/Enter visibility control, 44×44 notification dismissal and keyboard Enter dismissal. Menu dialogs focus their localized close control, use native modal background isolation and restore the trigger on Escape. Keyboard traversal reaches dialog controls without focusing background controls; native browser chrome may receive focus at the tab boundary. Existing dangerous confirmations retain safe-cancel focus. Guest gallery focus trap/restoration is covered by isolated WebKit tests.

Login follows Flux light/dark/system appearance; emulated forced colors retain a visible 2-pixel outline and reduced-motion durations collapse. At 360 CSS pixels with text enlarged to 200%, Russian department action labels wrap within their controls (32-pixel text, 214-pixel available control width). Chrome viewport/locale/theme matrices and QR print checks are recorded in `testing.md`; these checks do not establish physical-device, screen-reader or universal WCAG conformance.

## Branch control interaction contract — 2026-09-15

The [branch picker](../resources/views/components/dashboard/branch-picker.blade.php) uses a native disclosure, a labelled search field and radio choices. Organization, brand, branch and time zone remain readable outside the picker. All-branch scope is named explicitly. Branch-required actions open the picker and focus search; choosing a branch returns focus to its disclosure trigger. Readiness uses a separate native disclosure with named states and permission-aware links.

The [period form](../resources/views/components/dashboard/report.blade.php) labels each control, reveals custom dates locally and applies the complete range only on submit. The [dashboard](../resources/views/livewire/restaurant/dashboard.blade.php) handles `dashboard-validation-failed` by opening the relevant disclosure and focusing the first invalid control after rendering, with a focusable error panel as fallback. The pause form offers explicit Save and Discard actions; a rejected branch change preserves the draft and explains the required recovery.

Current queues, period totals and setup checks have separate headings and state messages. Loading keeps action labels visible; saving and refresh controls disable for their pending request and while offline. Missing permission, stale data and load failure use text as well as visual treatment. Layout and control acceptance includes 320-pixel reflow, 200% zoom, long EN/LT/RU content, keyboard focus, light/dark themes, reduced motion and forced colors. These are the implemented interaction contracts and acceptance targets; executed browser results and environmental limits belong in [testing](testing.md).

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


## Prompt 6 integration contract

The dish card retains one active form per resource, uses URL section history and a distinct content-language owner, restores localized error focus, and keeps simple image-order controls alongside dragging. Server-side bounded Flux selectors preserve the selected entry. Pending media cleanup remains explicitly resumable after reload. The shared dirty guard protects section/context exits; local offline main-form discard does not queue writes.

The focused visual DishCardWorkflowTest run passes 9 cases / 472 assertions and saves 68 screenshots. It covers EN/LT/RU at 320, 390, 768, 1024 and 1440 pixels, light/dark and CSS zoom 200%, native keyboard actions, retained/offline drafts and cross-card Back/Forward. Language labels remain whole in an internal scrollport; keyboard focus scrolls the active label fully into view. Playwright forced-colors/reduced-motion media emulation is verified through actual matchMedia values and visible focus. Representative narrow, wide, dark and focused screenshots were inspected. Native browser zoom, a physical mobile keyboard and a real OS high-contrast palette were not separately tested.

Final complete browser acceptance passes 61 candidate and 75 shared cases. Later test-only refinements distinguish an active-request navigation block from the next explicit departure, preserving the dirty dialog, cancellation and unchanged database assertions.
