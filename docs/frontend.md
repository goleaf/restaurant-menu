<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Frontend architecture

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
