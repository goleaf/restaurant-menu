<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Frontend architecture

## Prompt 8 Team navigation and styles

One employee card replaces repeated person selection for role, status, organizational permission and room operations. List filters and page context survive return; section history uses native Livewire URL state. Long permission groups use the installed local Flux Pro accordion/listbox components, with separate standard role, configured override and effective result. Only short confirmations use dialogs.

`resources/scss/team.scss` is a separately built card stylesheet loaded through the existing layout head stack on direct entry and wire:navigate. It reuses canonical tokens and container-responsive composition. The existing staffWorkspace guard now supports inline card editors, blocks transitions during requests, and discards an offline-dismissed draft only on a subsequent conscious action; reconnect never saves it automatically. No new global navigation handler, router, AJAX layer or theme store is added.

The existing restaurant selector uses a Flux listbox with a separate server-search slot. Selecting a long scoped label does not overwrite form.search. The branch identifier, current authorization, 100-character search bound, pagination, offline controls and existing navigation/draft guard remain unchanged.

## Connectivity after cached navigation — 2026-09-17

The shared bootstrap reapplies the browser's current online/offline event after `livewire:navigated`, once restored directives have initialized. Livewire 4.4.1 otherwise retains `wire:offline` inline visibility and disabled attributes captured in cached history, even if connectivity changed on another page. This synchronization uses no network request and does not replay mutations. The existing client indicator and native connection-change listeners remain in place.

## Application forms — prompt 2

Login, MFA/recovery, forgot/reset/confirm password, verification notice, logout, demo/local identity and invitation review now initialize real PHP Livewire components. Existing Flux inputs, OTP, buttons, named dialogs, loading and offline states remain in separate Blade views. Invitation form errors use `form.*`; export periods and restore uploads use their own Form prefixes. No second runtime, router, browser credential store or application AJAX client is introduced. The passkey SDK keeps its established browser protocol boundary.

Existing Exports, QR print and platform administration screens own file preparation. Bounded PDFs use Livewire download effects; large CSV/backups use an ordinary binary response after Livewire preparation. Restore uses the available Flux Pro upload control and a reviewed candidate; its final native form contains only CSRF and the opaque one-time grant. Completion means preparation/response delivery, not proof that a user saved a file on their device. Product styling continues to originate in SCSS, with the separate Tailwind/Flux bridge and unchanged design tokens. Restore finalization and feature-enabled passkey login share a scoped SCSS wrapping rule so translated labels stay within the form at320px and can grow vertically. Passkey entry uses the existing vendor protocol only when enabled; its full-navigation result clears the previous authenticated runtime.

Auth/account transitions perform a full page transition to discard private runtime state. Ordinary navigation remains the already implemented workspace navigation. Current responsive/browser evidence and remaining limitations are recorded in testing.md and PROGRESS.md.


## Prompt 3 refresh — verified navigation corrections (2026-09-17)

The existing restaurant task order and one shared Flux Pro selector remain canonical. Route-owned account, restaurant-management and platform screens ignore incidental branch/department query values for their header mode; they cannot display a misleading restaurant context. Restaurant object/route/query conflicts remain errors. Table-detail Back keeps its own branch; kitchen/bar ticket-print Back keeps both branch and department, regardless of another tab's preference.

Current halls navigation uses the existing service-points workspace. Restaurant management uses the existing restaurant center. Browser consumers follow these current routes, their actual form fields and their existing editor lifecycle; legacy links retain the compatibility contracts tested by their own Feature tests. Current verification belongs to PROGRESS.md; older complete workspace runs below are historical.

The existing pending barrier now restores a browser traversal to the confirmed history entry before the old form can acquire a different URL. It preserves dirty guards when idle, disposes listeners and metadata wrappers, and never retries the save or queued transition. The real delayed-response test covers native and forced-fallback history, including a hash entry and multi-entry traversal. Exact recovery from an unknown/ambiguous segment after cancelling native departure in older browsers without Navigation API remains unverified; see DECISIONS.md. Modern tested WebKit exposes the actual entry index.


## Shared restaurant workspace — prompt 3, 2026-09-16

The existing Flux sidebar/header/search/notification owner is retained. A single header RestaurantSwitcher uses the installed local Pro combobox with bounded server search (20 matches, at most 200 authorized rows scanned per request, explicit continuation). Matching folds case and diacritics for search only; stored names remain untouched. The current restaurant name is independent of the option page. Native anchors and Livewire Navigate preserve direct links and new-tab behavior. The installed Command control remains unsuitable for native link semantics; the existing accessible section-search mechanism consumes the same presenter registry.

| Existing entry | Context and resulting task |
| --- | --- |
| `dashboard` | Class-based Entry; opens last permitted task / sole restaurant or the real selector; no second dashboard card |
| Nested menu, team, areas, service points, QR, settings | Verified organization/brand/branch route; direct task links stay within that branch |
| Restaurant dashboard / waiter | Validated explicit `branch`; shared header selection replaces each local branch picker |
| Kitchen / bar | Branch-bound accessible departments; a department ID cannot silently switch restaurant |
| Waiter table detail / QR object | Verified object's branch determines the header, independent of session preference |
| Exports / audit | Explicit branch filters the actual query; supported broad views clearly use aggregate mode |
| QR short-code lookup without branch | Explicitly labelled aggregate lookup; a scoped link filters the actual search |
| Organizations / onboarding / platform / account | Distinct structure/platform/account scope; no false restaurant label |

Menu and staff retain their existing local editors and dirty/history guards. Dashboard ordering reuses the menu guard with a dedicated named Flux modal. The shell's request barrier ignores search/preferences and bounded polling, blocks navigation during other in-flight messages, and does not schedule retries. Every owned listener is cleaned up when the old shell is destroyed. Search and switcher do not poll; the existing notification component remains single.

Custom shell rules remain in `resources/scss/components/_workspace.scss`; existing shared spacing/layout utilities are reused. Tailwind/Flux still has a separate CSS bridge. Read-only restaurant identifiers are bound at page entry, not registered as mutable Livewire URL filters; this avoids history trying to assign a Locked property. The existing client-only offline indicator replaces a stateless server component. Dialog dimensions account for `dvh`; the restaurant identity and controls remain usable with long translated text. Full evidence and remaining device/runtime limits belong to PROGRESS.md, not this contract.


## Profile appearance preferences — 2026-09-16

The profile page owns the labelled appearance section below its information form and above account deletion. Its existing Flux segmented radio group uses `$flux.appearance`; browser persistence and the account menu's quick theme controls share the same state. It does not participate in profile submission or add server state. Settings navigation contains Profile and Security. The named, authenticated `appearance.edit` route remains a compatibility redirect to `profile.edit`; the separate Appearance Livewire class/view are removed.

## 2026-09-16 — Current component system continuation

The organization → brand → branch lists share `components/structure/list-toolbar` with Flux search, filters, visible-row count, loading/offline and explicit clear search. The existing simple paginator remains accessible and recovers from an empty late page after archive/restore without clearing filters. Breadcrumb labels are literal by default; static translation keys opt in explicitly.

The authorized navigation search remains a Flux modal with real links and named Alpine keyboard behavior. Up/Down and Home/End move among visible links; Enter and modified clicks retain browser semantics. A cancelled shortcut, text input, IME composition or another open dialog cannot launch search. One notification component/poller remains: opening the bell never marks messages read, closed polling only refreshes the count, and restaurant tasks remain independent of notification read state.

The shared image picker keeps the existing native file input and Livewire upload. Its help and field error have stable IDs; caller descriptions merge without duplicate IDs, modifiers/events/keys remain forwarded, and detailed per-file errors stay with the gallery. Focal controls retain native ranges with linked validation errors. Danger styling is centralized in SCSS; normal/disabled/loading/operational controls are shown on the restricted reference page.

Local Pro 0.1.1 contains four documented translation patches; its upstream version remains unknown. No vendor files are edited manually. `node packages/livewire/flux-pro/build-runtime.mjs --check` verifies the inventory and unchanged runtime, and Composer installs the local path normally. The original snapshot remains until the separate complete Pro clean-release/rollback acceptance.

## Current SCSS / Alpine boundary — 2026-09-16

The accepted migration supersedes earlier native-CSS-only and page-script decisions for first-party code. `resources/css/app.css` is only the Tailwind/installed Flux bridge. `resources/scss/app.scss` owns product tokens, light/dark variables, fonts, base accessibility and semantic compositions; `qr-print.scss` is a print-only entry. `pdf-qr.scss`, `pdf-report.scss` and `emergency.scss` compile into fixed committed `resources/views/generated/styles/*.blade.php` artifacts, so emergency/PDF rendering never needs a Vite manifest or runtime Node. `resources/build/styles.js` generates token aliases and concrete breakpoints automatically in both Vite build and dev/HMR; `npm run styles:check` detects drift. Do not edit generated CSS.

Cascade order remains Tailwind `theme, base, components, utilities`. Sass emits into existing layers and preserves narrowly scoped unlayered accessibility fixes; there is no new reset. Runtime `--rm-*` values have one canonical Sass map; generated `@theme inline static` aliases resolve the current Flux `.dark` appearance without another theme store. QR physical geometry stays 76×104 mm, 48 mm code plus quiet zone and 8 mm A4 margins. Emergency actions now meet the product's 44 px minimum.

`resources/js/app.js` imports the installed Livewire ESM runtime and its Alpine instance, registers named component factories/bindings, then calls `Livewire.start()` once. Compatibility palette/radius values used by semantic compositions are retained in the same map; static aliases prevent Tailwind pruning variables used only by the separate SCSS output. Every layout supplies `@livewireScriptConfig` before retained `@fluxScripts`. No independent Alpine package or `Alpine.start()` exists. Registration is cheap and synchronous; the WebAuthn SDK loads only when a ceremony starts. Local panel/focus/clipboard/preview/timer/audio state belongs to Alpine; forms, filters, uploads and mutations belong to class-based Livewire and existing Actions. The obsolete twelve root page/behavior modules are removed.

Transport allowlist: Fortify authentication; the single first-party `fetch` boundary in `resources/js/integrations/passkeys.js` for WebAuthn options/credential HTTP protocol; invitation credential/identity transitions; demo identity entry; authorized CSV/PDF/media/SQLite downloads; exclusive-barrier SQLite restore POST. Restore must obtain its lock before authentication/session reads and therefore cannot be moved behind the ordinary Livewire update transport. Static landing/error/PDF/email and navigation/redirect responses remain SSR. `LivewireInteractionBoundaryTest` pins all 30 class-based page routes, 17 HTTP exceptions and 15 CSRF-protected native POST forms; existing negative/replay/tenant tests exercise those boundaries. There were no first-party fetch/XHR/Axios requests at migration baseline, so none are claimed removed. The sole added passkey adapter replaces `@laravel/passkeys` with its installed lower-level `@simplewebauthn/browser` SDK pinned to 13.3.0: cryptographic browser operations stay in the SDK, while owned GET/POST requests enforce same-origin URLs, credentials, CSRF and redirect rejection. Every await checks the current owner; destroy/cancel aborts its request and ceremony. A cancelled POST has an unknown server outcome and never produces a local success claim. `alpine-passkeys-adapter.test.mjs` tests the real SDK after a cancelled delayed GET and controlled cancel/error/success transport boundaries. Application CRUD continues through Livewire.

Quality entrypoint: `npm run verify:migration` executes manifest validation, architecture rules, Stylelint, ESLint, Pint, Larastan, production build, complete PHP discovery/execution, JS coverage, PHP coverage, isolated browser coordinator, translations and budgets with real exit codes. This command defines verification; its presence does not imply it has passed. Exact current results and open gates belong in `PROGRESS.md` and `IMPLEMENTATION_PLAN.md`; older dated counts below remain historical.


## Historical — superseded screen resource ownership, 2026-09-16

`resources/js/app.js` owns common navigation and the critical-editor readiness barrier. Menu, staff, waiter and kitchen/bar views push their corresponding static Vite entry into the application head; their existing modules retain their own UI behavior. No runtime license detection, second Alpine copy, parallel AJAX layer or guest editor import is added.

Menu/staff roots retain their real Livewire identity with `wire:ignore.self` for browser-owned readiness attributes. A failed or too-late critical entry keeps the root inert; the localized Flux callout outside it offers a native reload link. No automatic reload or draft reset occurs. Asset registration is coordinated with the installed Livewire head-script and cached-history lifecycle, and ordinary form morphs must not restore inert state. Blade pushOnce identifiers are global across stacks: use distinct `module-scripts` and `module-status` identifiers so the recovery callout is actually rendered. Operational entries show their own loading/retry explanation without disabling the dashboard; only sound controls start disabled until their handler is ready.

Staff filter rendering validates only its filter payload and clears only filter errors. Invalid invitation input and its field message survive subsequent renders and filter changes; a corrected explicit preview clears its own error.

Font faces are bundled into the existing main CSS request. QR print remains its separate entry. See [Tailwind](tailwind.md) for cascade ownership and [performance](performance.md) for current measurements and manifest budgets.

## Flux Pro migration status and composition — 2026-09-15

The complete Pro source is maintained internally as installed local release `0.1.0`, using Free 2.17.0 and Livewire 4.4.1. Server rendering passes for 18 supplied families; the combined runtime is being adapted to the installed Free behavior before workflow acceptance. Existing navigation, notification, branch-picker and dialog work is retained and adopted by P3–P12 of the [integration plan](superpowers/plans/2026-09-15-flux-pro-integration.md).

After activation, prefer official primitives and retain domain compositions for context, recovery and repeated semantics. Do not publish all 125 upstream templates into first-party views. Localize supported props/slots first; use a narrowly reviewed override only for a demonstrated API gap. Every control replacement needs value-shape, validation, focus, morph, offline/retry and permission equivalence. Browser proof begins after compatible runtime installation; source integrity is not UI acceptance.

## Unified Flux workspace — 2026-09-15

The application shell uses one Flux header, one account menu and one notification component at every breakpoint. Flux owns mobile disclosure and persisted desktop collapse; permitted links are rebuilt on the server for the current identity. A local Flux modal searches the same prepared navigation items, including accent-insensitive matching, keyboard entry and focus restoration. Its component-owned shortcut ignores text entry and existing dialogs; no permission data is persisted.

The notification bell only opens the panel. The existing query service supplies the count and, only while open, at most 20 recent accessible notifications. The existing read Action performs explicit single/all read operations within the stated current-user/accessible-branch scope. Reading is independent of restaurant work completion. Destinations are derived from current table context and authorization, never an arbitrary payload URL. Closed stable polls skip HTML rendering; the first poll after dismissal clears rendered message details. Opening offline exposes a retry after reconnect, and closing always works locally.

Branch selection retains native details for local dismissal and focus, with Flux Input and radio cards. Its option projection contains at most 25 search matches plus the currently selected authorized branch. This limits rendered options, not the dashboard service's existing complete permitted branch graph; no new unbounded search query was added. Help/error associations and the installed radio disabled-state bridge are explicit and browser-tested.

`/local/components` is available only in local/testing to an authenticated superadmin, with authorization repeated on hydration. Its fictional examples exercise controls, states, lists, modal validation and independent drafts without changing restaurant records. The component is unavailable in production even with cached routes. Existing menu/media, QR print and guest native-dialog contracts are preserved.


The reference Command demonstrates its supported action API by opening the existing named example editor; it does not pretend that `command.item` supports `href`. Real product navigation remains anchor-based. Optional upstream clearable/closable variants in date/time/select/pillbox/command still contain untranslated fallback labels but are not enabled by current product views or the reference. Enabling those variants requires a localized, provenance-recorded patch and a rendered regression; they are not evidence of completed all-family Pro localization.

## Flux Free modernization — 2026-09-15

Installed and locked versions remain Flux **2.17.0** and Livewire **4.4.1**. Packagist metadata and local outdated checks identify compatible stable **2.20.0 / 4.4.5**. Their available distribution archives point to GitHub API URLs, prohibited by this repository's push-only rule; the local Composer cache contains only the installed versions. No lock file, unrelated dependency or Pro package was changed. This is an explicit upgrade blocker, not a completed version upgrade.

The exact installed Free package includes Button (primary, outline, filled, ghost, subtle and primary color), Toggle, Callout, Card, Field/Input/Textarea, Skeleton, Progress and Table. The first-pass claim that Toggle was absent was incorrect. Flag, searchable/rich select/listbox and charts are absent; the only select variant is native. Country/timezone datalists keep text and keyboard input, currency keeps its short native select. No premium or unsupported API was added. Toasts keep one persisted group per active layout; no artificial Undo, new event action or unsupported inverted option is introduced.

The second pass removes the generic button, alert and form-field layers and the unused table-row/validation-error components: five UI PHP classes and five UI Blade views. All their first-party callers now use Flux directly. The remaining 7 PHP / 14 Blade UI components carry product semantics. Card remains an anonymous heading/description/actions composition backed by Flux Card; EmptyState uses Card/Heading/Text, StatePanel uses Callout/Skeleton while retaining its state/announcement API. StatusBadge retains domain status normalization, semantic contrast and optional dots; money, plain text and restaurant icon mappings remain product presentation. ImageUploadInput retains the domain MIME/size/help contract and the native file picker (the installed Flux file control hardcodes English internal labels). PageHeader, MetricStrip, PriorityRow, WorkspaceSplit and MobileBottomActions remain repeated product compositions.

### UI component inventory after the second pass

| Component(s) | Classification / decision |
| --- | --- |
| `page-header`, `metric-strip`, `priority-row`, `workspace-split`, `mobile-bottom-actions` | A: keep repeated product layout and operational semantics |
| `state-panel`, `empty-state` | A: keep product state APIs; compose Flux Callout/Skeleton or Card/Heading/Text |
| `status-badge`, `money`, `plain-text`, `area-icon`, `service-point-icon` | A: keep domain status/money, tested plain-text boundary and restaurant icon mappings |
| `card` | B: keep useful heading/description/actions composition; anonymous Flux Card, no PHP tone/padding map |
| `image-upload-input` | E: keep domain upload constraints and translated help with native file picker |
| `button`, `alert`, `form-field`, `table-row`, `validation-error` | C/D: migrated or orphaned; views and unnecessary PHP classes removed |
| QR sticker/print structures outside `ui` | E: keep deterministic physical CSS and print layout |

Native multi-selection checkboxes, bounded number/radio controls, file/range inputs, language navigation selects and the branch disclosure retain useful browser semantics. Existing lightweight tables remain semantic tables: wrapping them in Flux did not remove a repeated first-party table system. The native upload progress remains bound to local Alpine state. No generic wrapper is kept merely to rename Flux props; unsupported Flag, rich Select or Chart usage is deferred to an actually available Free package.

Waiter attention filtering now uses the installed compact Toggle; persistent labeled booleans remain switches and independent selections remain checkboxes. Guest choice buttons and notifications use Flux Button; comment fields and dangerous-confirmation fields delegate labels/errors to Flux. The guest draft editor uses an always-mounted Flux Modal with a persistent initial-focus control: rendering the host only after `modal-show` loses the event, and rendering its autofocus control later loses initial focus. Authorized server actions open/close the modal; Escape closes locally, clears edit state on the server and restores the trigger. Onboarding Progress has a step-based `wire:key` because the installed element reads its value at initialization. Existing Actions, validation, tenant boundaries and query behavior remain intact.

Catalogue modal hosts now sit outside the layout grid without a vendor `display: contents` workaround. Five menu dialogs use `:closable="false"` plus the existing translated close composition with explicit autofocus; Escape and backdrop handling remain owned by Flux. Appearance remains a single Flux-managed light/dark/system state with `@fluxAppearance` and the CSS dark variant.

### Published Flux templates

- Removed unused `navlist/group` and local icon copies; callers use official `squares-2x2` and `chevron-up-down` icons.
- Retained `input/viewable` only for the EN/LT/RU accessible name and `aria-pressed` state; installed Flux exposes no label prop for this internal visibility button.
- Added a minimal `toast/index` override: translated dismiss label and a 44-pixel target on the existing close button. Slots, events, duration and markup otherwise match installed upstream.
- `FrontendStyleArchitectureTest` restricts the override allowlist and hashes the two installed upstream templates. Every dependency upgrade must compare those templates and remove the override when an upstream supported API closes the accessibility gap.

## Branch control center — 2026-09-15

The [restaurant dashboard](../resources/views/livewire/restaurant/dashboard.blade.php) starts with Organization → Brand → Branch context and a searchable native branch disclosure. A single accessible branch is selected directly; multiple branches start in an explicit overview. Branch-specific links request a selection in overview mode and use only the destinations permitted for that branch. Missing access is labelled and never represented by an active link to an arbitrary first branch.

Current service queues and historical results are separate sections. Pending drafts, ready positions, waiter calls, bill requests and open tables link to the matching waiter workspace. Manual refresh updates the current view; this dashboard has no page-wide polling timer. Reports retain their own timestamp, unavailable and stale states, and show amounts separately by currency. Unavailable data is not presented as zero.

The [Livewire component](../app/Livewire/Restaurant/Dashboard.php) keeps editable period fields separate from the applied range. Apply validates the complete range and changes one URL parameter: `period=today`, `yesterday`, `last7`, or `custom:YYYY-MM-DD:YYYY-MM-DD`. Browser history restores that range as one value. Custom ranges include at most 31 calendar days; [BranchReportPeriod](../app/Support/Reports/BranchReportPeriod.php) resolves each branch's local dates before querying UTC timestamps. Search, refresh and editing the form do not apply an unfinished date range.

[Branch readiness](../app/Services/Restaurant/BranchReadinessService.php) supplies compact ready, required, review and optional checks with permission-aware settings links. Ordering status distinguishes a manual pause, schedule closure and setup problem; readiness does not rewrite onboarding completion. The explicit pause form saves through [SaveDashboardOrderingAction](../app/Actions/Dashboard/SaveDashboardOrderingAction.php). Unsaved pause fields survive refresh and failed saves; switching branches asks the user to save or discard those changes before the context changes.

Waiter drill-down accepts only `attention=all|pending|ready|calls|bills`, alongside the authorized branch and zone scope. A selected open session outside the current 50-table page is resolved within that same scope and opens its matching page when it satisfies the active filter. Selection outside the permitted branch, zone or open-session state fails closed. See [BuildWaiterDashboardAction](../app/Actions/Waiter/BuildWaiterDashboardAction.php) and [WaiterTableQueryService](../app/Services/Waiter/WaiterTableQueryService.php). Integrated browser evidence is recorded separately in [testing](testing.md).

## Focused menu workspace — 2026-09-15

Six compact navigation entries select catalogue, availability, variants, preparation, extras and CSV exchange. Inactive Livewire sections are not mounted. The mobile catalogue keeps search visible, with secondary filters in an accessible disclosure and bulk forms only after selection. The editor uses the existing responsive dialog, separate image-save feedback, keyboard photo ordering and a non-destructive focal-point preview. Dirty protection includes programmatic translation copying and separately saved photo/CSV state; completed or discarded media state releases its registration. History is retained through allowlisted URL state. Actual viewport, keyboard and screenshot evidence belongs to the current testing section.

## Interaction recovery — 2026-09-15

The native dish dialog has local Alpine visibility and synchronous showModal/close; no competing x-trap remains. Background scroll locking follows the open element, including removal after adding. Dish dismissal is local state: Escape and Close release the focus trap immediately and restore the original card or the stable menu heading. Reconnect and unrelated Livewire renders do not reopen it. A successful explicit open shows the current selected dish again, preserving its pending comment, modifiers and attempt UUID; choosing another dish resets that configuration.

All twelve translated catalogue forms declare `novalidate` in Blade so morphing cannot restore browser validation over hidden language panels. Required server rules remain intact. Base-field conflicts are displayed beside EN fields, and every completed submit reopens the first invalid locale even when the errors are unchanged. Existing guest language selectors disable during their locale request and while offline.

Image selection appends previews in the same order as Livewire's temporary uploads. Stable preview keys and a shared busy state serialize selection, removal and save. Failed temporary batches release only their own previews; a permanent save refusal keeps the selected batch and request identity for retry. Completed upload receipts recover a successful commit whose later callback failed.

## Recovery and operational presentation — 2026-09-15

Guest details stay open when their configured dish disappears from the current menu. The existing translated error and retry control preserve the comment, options and addition identity; unavailable dishes expose no Add action. Closing restores focus to the original trigger or the stable menu heading if that trigger no longer exists.

Kitchen/bar timers use one compact wrapping row. Operational mutations use Flux Button directly with 56-pixel targets. Waiter detail and dangerous confirmations use semantic surface, text, border and state tokens in both themes. Mutation controls disable for offline and targeted loading states; browsing, reading and dismissing remain available. Server authorization is unchanged.

## Catalogue and guest product integration

`menu-translations.js` owns ephemeral tab/preview/focus state and synchronizes English authoring to existing base inputs. Livewire owns validation and persistence. `menu-image-picker.js` presents local file previews, pending removal and upload progress; `ManagesItemImages` owns server upload state and receipt replay. Neither helper introduces another frontend framework.

Only selected-item gallery data enters the guest detail view; primary responsive metadata stays in the existing menu payload. Locale events refresh adjacent guest components without replacing guest or cart identity. Main-screen responsive/theme/keyboard and real upload/locale tests live in ProductMenuWorkflowTest.php.

## Area and service-point transport contract — 2026-09-14

Existing area/table Blade bindings and prepared presentation data remain unchanged. Malformed editable values receive field validation instead of typed hydration or trimming exceptions. Valid numeric strings and checkbox encodings retain their existing storage semantics. Bulk preview and confirmation use the same bounded Action contract; direct invalid-range messages are present in EN/LT/RU. No new UI framework, component, layout or dependency is introduced.

## Menu editor transport contract — 2026-09-14

The catalog, modifiers and variants keep their existing flat wire:model bindings and prepared Blade presentation. Editable values remain mixed until server validation so a malformed update returns a field error instead of a hydration TypeError or silent scalar conversion. Dependent selector reads use safe projections while validation retains the original public value. No template, layout, CSS, translation key or browser dependency changes are required. The regression matrix exercises actual Livewire update payloads for create/edit and dependent selection updates; valid controls verify exact saved values.

The interface is server-rendered Laravel Blade enhanced by class-based Livewire 4 and Flux UI Free 2. It is not a SPA and does not use React, Vue, Inertia, Volt, jQuery, Axios or a second Alpine installation.

## Rendering contract

Controllers and Livewire classes prepare all data. Blade may render escaped values, translations, presentational conditionals, simple loops and Blade/Flux/Livewire components. It may not invoke Eloquent, Actions, services, facades or the container; make authorization, status or monetary decisions; create SEO/JSON-LD data; or run `@php`, `@endphp` or ordinary PHP blocks.

Repeated presentation patterns belong in anonymous Blade/Flux components. Stateful server interactions belong in Livewire. Alpine is reserved for local ephemeral DOM behavior and third-party widget lifecycle. Custom JavaScript must initialize and tear down correctly across `wire:navigate`.

## Page states

Every data-driven surface supplies an accessible initial/loading, action-loading, empty, filtered-empty, success, recoverable-error, fatal-error, offline, unauthorized and disabled state where the state can occur. `wire:loading` targets only the operation in progress. Mutating controls prevent duplicates but do not disable unrelated page regions.

## Navigation and focus

Links remain normal same-origin links. Where `wire:navigate` is enabled, titles, scroll, focus and announcements are managed, and repeated navigation creates no duplicated listeners. Validation associates errors with fields and moves focus to a useful summary or first invalid control. Modals trap and restore focus. Destructive actions use localized confirmation and remain server-authorized.

The token system is defined in [`design-system.md`](design-system.md), Livewire usage in [`livewire.md`](livewire.md), Tailwind integration in [`tailwind.md`](tailwind.md), and accessibility acceptance in [`accessibility.md`](accessibility.md).

## Page hierarchy

Public entry presents staff login as its sole primary action and explains the guest QR path as a secondary journey. Authenticated landing prioritizes the restaurant workspace; restaurant quick actions are descriptive full-row links rather than repeated generic buttons.

Staff dashboards present one ordered operational queue. The waiter dashboard validates the nullable URL-backed `table` selection within its authorized branch and zone, seeks the containing page when needed, and renders `aria-current` on a matching selected row. Desktop keeps the selected table preview beside the queue; mobile preserves the normal table-detail link. Kitchen and bar share the same priority-row hierarchy, visible department scope, oldest-first age signal and 56-pixel status actions. Their isolated polling and existing Action boundaries remain unchanged.

Guest table screens keep venue/table context above the journey, expose category anchors in an internally scrollable labelled navigation, use flat menu-item rows and keep totals/actions in the existing safe-area-aware mobile action dock. Offline state is explicit and content remains browseable where the domain allows it.

Restaurant onboarding keeps the numbered desktop navigation and exposes native progress plus a collapsible created-resource summary on narrow screens. Every mutation has an action-specific busy announcement and disabled loading/offline control, while the server remains responsible for authorization and idempotency. Validation focuses the first invalid field, falling back to the error summary, and successful step changes focus the new heading. Base grid tracks use shrinkable columns and inherited emergency word wrapping so native input sizing and long EN/LT/RU or tenant-provided names cannot create horizontal overflow at 200% text size.

## Prompt 7: availability center

Availability is a class-based Livewire workspace with Now, Schedules and Stop-list addressable sections. Flux Pro date/time/search controls use the installed local adaptation; Forms validate actual local times server-side. Weekly drafts support selected-day copy with before/after preview. The stop-list page holds20 dishes and a single selected editor; current/future evaluation labels the source of its result.

The existing menu-workspace dirty guard supplies navigation/offline cancellation. One local timer refreshes temporal status, never one poller per dish; listeners/timers are disposed on navigation. Draft editors are not reloaded by background status refresh. `resources/scss/availability.scss` is loaded through the layout page-scripts stack; no Tailwind entrypoint is processed by Sass.

## Unified dish editing (Prompt 6)

The catalogue opens `/menu/items/{item}`; `section=main|photos|variants|modifiers` and content `language=en|lt|ru` belong to the card URL. Return filters remain bounded and allowlisted. The card retains only visited editor sections, has one main form, and reuses the existing unsaved-navigation guard. Its root avoids `x-ignore` during morph cloning so language-panel bindings survive Back/Forward; initial readiness still uses the single application bootstrap and inert state.

Flux listbox searches are server bounded. Photos use Livewire uploads, with keyboard reordering, per-photo captions and safe retries. Preview explicitly labels saved versus validated draft data and does not expose real cart controls. New composition styles live in `resources/scss/components/_dish.scss`, included in the existing `app.scss` bundle; the card adds no stylesheet request. Whole language labels scroll inside their tab list on narrow screens.
