<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

## Personal display formats — 2026-09-20

Profile now contains an independent account-owned date/time/number form with live examples, explicit Save and a draft reset to language defaults. Dates support language default, dotted/slashed day-first, slashed month-first and year-first ISO-style display; time supports language default and12/24 hours; numbers support language default and four decimal/grouping pairs. All labels and confirmations use17 EN/LT/RU semantic keys. Currency code and localized symbol placement remain restaurant-owned; grouping spaces are non-breaking. Persisted null/unknown values safely read as language defaults.

LocalizedDateFormatter, LocalizedNumberFormatter and MoneyFormatter accept an explicit immutable DisplayPreferences object and otherwise use the already-hydrated authenticated account, without a preference query. Relative phrases stay locale-based; supplied timezones are preserved. Guest money and opening-status consumers explicitly use language defaults, including the cached guest availability labels. Analytics/dashboard ordinary, deferred and fallback cache keys include the bounded format fingerprint; deferred report callbacks capture both locale and preferences. MCP report presentation uses explicit locale defaults. Dates/amounts in storage, input controls, CSV and arithmetic retain their existing canonical representations.

Report period headings, dish preview evaluation time, service-point checked time and catalogue hidden-until labels use the shared date formatter; catalogue and variant measurements gain separate formatted labels. Raw editor values remain unchanged. Profile locale save sends a targeted event to refresh the format section while retaining its draft. Installed Livewire SupportLocales restores each child's snapshot locale during hydration, so that listener explicitly applies the current authenticated user's validated locale before rendering.

This is the first preference delivery, not universal formatting acceptance: native scheduling controls/summary values, some quantity and percentage labels and remaining history displays require a separate consumer pass. Further personal-setting candidates and evidence are in [Spec Kit research](../specs/005-user-display-formats/research.md). Existing broader translation omissions remain open.


# Localization

## System audit and first repair batch — 2026-09-20

Scope: first-party PHP, Blade, JavaScript/MJS and routes, all three JSON catalogues, enum presentation methods, department data flow and local Flux Pro template defaults. The initial scanner inspected 984 files and 3,194 used keys against 3,179 catalogue keys: 15 direct keys were missing in every language, while `translations:audit` incorrectly returned zero issues. The shared checkout was changing during this audit: preparation labels and many messages were subsequently supplied by concurrent Prompt10 work. Those concurrent translations are preserved and are not attributed to this batch. Counts below describe the recorded source inventory, not a frozen release or an exhaustive browser journey through every role and error state.

| Finding | Evidence / scope | Status |
| --- | --- | --- |
| Standard department names remain English when interface language changes | `KitchenDepartment.name` stores Kitchen/Bar/Dessert/Hookah; management rows, catalogue labels and DishQuery options used the raw name | Repaired: explicit `localizedName()` presentation, EN/LT/RU labels and localized selector search; custom names and original edit values remain literal |
| Locale-dependent default seeding after the preparation enum change | `KitchenDepartmentType::defaultSeedRows()` called the now-localized `label()` | Repaired: stable canonical default names are separate from translated labels; no database rewrite or reseed |
| Operational branch settings use English labels and descriptions | BranchServiceMode: 6 labels + 6 descriptions; BranchOrderFlowMode: 2 labels; Blade translated their English results as if they were semantic keys | Repaired with 14 semantic keys / 42 localized values; canonical option values unchanged |
| Audit falsely passes a direct key absent from all catalogues | Catalogue parity only compared keys already present somewhere | Repaired: every discovered semantic call missing from all languages is now a failure; `--no-code-scan` keeps its explicit catalogue-only semantics |
| Concatenated translation prefixes are reported as literal missing keys | `__('preparation.timer.'.$basis)` | Repaired in scan and audit; standalone incomplete literal keys still fail. The bounded prefix scan cannot prove every runtime suffix exists |
| Disabled preparation department uses nonexistent `qr.status.inactive` | `resources/views/livewire/departments/dashboard.blade.php` | Reused existing `ui.status.inactive`; no redundant catalogue alias |
| Superadmin production safety warnings | BuildProductionSafetyReportAction: APP_DEBUG enabled and public-storage PHP execution guard missing | Two confirmed user-visible messages pending translation; preserve their diagnostic codes and security behavior |
| Other English enum presentation labels | 55 strings in six enum families below | Confirmed follow-up work; not translated by this first batch |
| English local Flux Pro template defaults | 48 occurrences / 37 distinct strings / 34 files below | Requires mounted call-site verification and the existing Pro localization/provenance workflow; package sources and installed vendor copies were not patched here |

### Remaining enum presentation inventory

| File | English labels | Visible concern |
| --- | ---: | --- |
| `app/Enums/AuditLogAction.php` | 22 | Audit/history actions: price/availability/deletion, QR, subscription, backups, orders, table sessions and payments |
| `app/Enums/AreaNodeType.php` | 12 | Group, Floor, Hall, Terrace, VIP room, Bar area, Banquet hall, Room, Hotel area, Pickup area, Delivery area, Custom |
| `app/Enums/ServicePointType.php` | 10 | Table, Bar seat, VIP table, Room, Booth, Sunbed, Hotel room, Pickup window, Delivery point, Other |
| `app/Enums/DraftOrderStatus.php` | 5 | Draft, Sent to waiter, Waiter review, Rejected, Converted to order |
| `app/Enums/OrganizationSubscriptionPaymentStatus.php` | 4 | Pending, Paid, Overdue, Failed |
| `app/Enums/OrganizationSubscriptionStatus.php` | 2 | Active, Inactive |

These are presentation-method findings. Several screens already use a separate semantic key (for example reports service-point types); repair the shared presentation contract and verify actual consumers rather than count every occurrence as a currently visible untranslated screen. The broader enum literal search found 122 occurrences in 12 files; 53 belong to canonical role/permission/override labels or neutral currency codes and require consumer-specific treatment, not blanket replacement. SystemRole already has `localizedLabel()`, and permission/override presenters use dedicated semantic keys.

### Remaining component default inventory

The 48 local Pro occurrences group into editor30, select5, time-picker3, command3, pillbox3, slider2, file-item1 and date-picker1. Distinct defaults are: Align, Blockquote, Bold, Bullet list, Center, Clear command input, Clear date, Clear selected, Clear time, Close command modal, Code, Formatting, Heading 1, Heading 2, Heading 3, Highlight, Insert link, Italic, Left, No results found, Ordered list, Redo, Remove file, Rich text editor, Right, Select a time, Strikethrough, Styles, Subscript, Superscript, Text, Underline, Undo, Unlink, end range, selected and start range. Many editor controls are only mounted in the local component reference, while selectors, dates and file controls need review at their product call sites. Existing caller labels/slots may already override a default. Do not claim all 48 are exposed or edit installed vendor files.

### Data and audit boundaries

Standard department names are recognized only when the saved name exactly equals that type's canonical default or one of its supported translated defaults. Custom department types and renamed names remain literal. Reading, switching locale or searching never rewrites `name`, type, IDs, orders, ticket snapshots or permissions. Editors retain original stored names. Historical department snapshots, waiter/print/export displays and the evolving preparation workspace need a separate presentation pass; changing the model's persisted `name` accessor globally would corrupt this boundary. Menu authoring EN fields and business names are content, not automatically translatable interface keys; existing menu translation tables remain authoritative.

The scanner detects direct calls, known semantic literals and bounded dynamic prefixes. It is not a PHP/Blade AST interpreter: keys selected by expressions and absent from every catalogue, runtime suffix combinations, database content, vendor defaults outside the explicit four maintained templates and contextual translation quality still require tests/manual inspection. The initial HTML literal/attribute sweep found no additional confirmed static English prose in first-party Blade after excluding directives and components that translate their key props. JavaScript's inspected direct text assignments use supplied localized text or numeric timers. A first-party PHP presentation-payload sweep found two additional English production warnings in BuildProductionSafetyReportAction: APP_DEBUG enabled and the missing public-storage PHP execution guard. Both are rendered by the superadmin dashboard and remain a confirmed follow-up; canonical environment values stay unchanged. This does not substitute for testing hidden states and accessible names in real browsers.

Verification and current shared-tree gate findings are recorded in `docs/PROGRESS.md`. Existing Spec Kit follow-ups T021/T022 in `specs/003-dish-card/tasks.md` track this bounded batch; remaining enum and Pro work continues under `i18n-001` and the existing implementation plan.

## Flux Pro localization acceptance — 2026-09-15

The local package is installed, but its vendor strings are not yet accepted in EN/LT/RU. P2 must inventory visible and accessible strings in the accepted release, including nested dismiss/search/empty/upload/calendar/editor controls. Reuse semantic EN/LT/RU JSON keys with placeholder parity; do not change the global translator to accommodate English vendor keys. Prefer supported props and slots, then minimal pinned overrides for demonstrated gaps.

Pro calendars and time controls must receive the application locale explicitly; browser locale and browser "today" cannot redefine branch-local operational dates. Money and report formatting remain prepared server data. Run translation scan/audit plus three-locale keyboard and responsive browser cases after activation. Raw upstream source preservation does not imply translated product behavior.

## Content editing and guest preference — 2026-09-15

EN is the canonical authoring base and maps to base name/description on saves. LT/RU panels can show EN alongside the translation; copying fills only empty fields, marks the form unsaved and shows a review notice until changed. Existing translation rows preserve intentionally blank optional descriptions; only absent rows fall back to base content. Dish duplication preserves this distinction. Photo captions are independently optional per locale; empty alt uses the localized dish name. Guest language is remembered per branch and never overwrites the administrative interface locale. Changing language retains the guest/table/cart identity and open configuration.

## Repeated validation and offline choice — 2026-09-15

English remains the explicit base authoring language. Its visible name/description controls include errors raised for the legacy/base properties. The twelve translated forms keep `novalidate` in server-rendered markup while preserving all required EN/LT/RU server validation. Each submitted form checks errors after the installed Livewire `onRender` lifecycle, so a repeated identical error can reopen LT or RU after the editor switches away. Text in other panels is retained.

The two existing guest language selectors disable offline and during their own locale synchronization. This prevents a local selection from appearing accepted while no request can persist it; there is no second preference store or basket.

## Catalogue editing and guest continuity — 2026-09-14

All existing name-bearing menu entities share accessible EN/LT/RU panels. English is the explicit primary authoring language and synchronizes the editor's legacy base name/description; all three localized names remain required. Descriptions stay plain multiline text. Tabs preserve input, expose completeness/length and unsaved state, and activate the locale containing a server validation error. Preview uses the selected locale. Direct legacy Action arguments remain backward compatible; do not infer that base columns alone contain a complete translation set.

Translation synchronization touches only locales supplied by its caller; editing Lithuanian must not rewrite English or Russian. Existing legacy rows resolve missing translations through the established base-content fallback. Copying a dish copies its existing translations into new rows and labels the new dish for review; no automatic translation service is required.

The existing supported-locale resolution and persistence remain authoritative. Guest locale events update menu/details, draft/totals, table guests, join requests, actions and order-status siblings while retaining guest, session, table and basket identities. Search uses the selected language's prepared name and description. Unknown locale values follow the controlled existing fallback. Locale changes cannot grant permission to order.

## Supported locales

The application supports English (`en`), Lithuanian (`lt`) and Russian (`ru`). All interface text lives in the flat `lang/en.json`, `lang/lt.json` and `lang/ru.json` catalogues and uses semantic dot keys. English is the safe default for a new context, but it is not an accepted substitute for a missing Lithuanian or Russian value: the automated audit requires exact key parity, non-empty values and a narrow allowlist for language-neutral codes, units and example proper names.

An authenticated choice is stored in `users.locale`; the web session applies it immediately and restores it on later requests. Public/guest choices are stored in the web session and copied to the active `table_session_guests.locale` and the existing pending `table_session_join_requests.locale` flow, so approval, cookie restoration and later QR scans retain the guest's language. Restoring a removed, departed, rejected or pending guest record with a URL language does not mutate that guest row; URL persistence requires an active restored guest, matching explicit locale events. A valid explicit `?lang=` choice has request precedence; unsupported values are ignored rather than persisted.

## Contributor workflow

1. Reuse or add one stable semantic key; do not concatenate translated sentence fragments.
2. Add the key to every supported locale in the same change.
3. Keep placeholders identical across locales and preserve plural forms.
4. Use framework/Flux validation and error integration with localized custom messages.
5. Format dates, times, relative values, numbers, percentages, lists and currency for the active locale; persist raw time/minor-unit values.
6. Translate visible labels, placeholders, buttons, headings, states, notifications, confirmations, emails, accessibility names and public metadata.
7. Run `php artisan translations:scan`, `php artisan translations:audit` and the locale-focused Pest tests.

Blade and PHP may not hardcode new user-facing messages. Enum/store values remain stable canonical data; localized labels are obtained through translation keys. API/business errors intended for users carry a stable error type and localized presentation message rather than a raw exception.

Restaurant onboarding initializes editable area, table, menu, category and sample-dish names through the active JSON locale. Country and currency stay unselected until the user chooses them; the time-zone suggestion uses the configured application time zone only when it is a Laravel-supported identifier and otherwise falls back to `UTC`. The country control validates an ISO 3166-1 alpha-2 code and resolves it to the existing canonical `branches.country` value for backward compatibility. A separate `country_code` column is intentionally not added while no query, integrity rule or downstream integration consumes it; duplicating the same fact would introduce synchronization risk without a demonstrated schema benefit.

## Menu content translations

Guest-visible menu content uses one SQLite-compatible relational strategy. `menu_translations`, `menu_category_translations`, `menu_item_translations`, `menu_item_variant_translations`, `modifier_group_translations` and `modifier_option_translations` each store one row per owner and locale, protected by an owner+locale unique constraint. The administration workflow requires `en`, `lt` and `ru` names in the same validated mutation; descriptions remain optional only on entities that own a description. Base name/description columns are retained as a safe fallback for legacy rows, not as a competing translation workflow.

Internal IDs, enum values, status codes, locale codes and currency codes remain canonical untranslated data. Their visible labels use JSON translation keys. Guest-menu cache keys include locale, translation observers invalidate affected branch caches, and draft item, variant, modifier-group and option snapshots are resolved from persisted translations on the server rather than accepted from browser payloads.

The guest-menu read Action selects localized category/item names and descriptions as scalar subquery attributes, matching the existing menu/variant/modifier name projections. It does not hydrate translation models for these display fields. Names fall back when their translation is missing or blank. Optional descriptions fall back only when the translation row is absent; an existing row preserves null, empty and whitespace descriptions. Management forms load the complete translation records they edit. The dish card prefills the original EN fields from legacy base content only when the EN row is absent, without writing on GET; explicit save applies the existing English-canonical contract. Saved preview uses the same row-presence semantics as the guest presenter, independently of interface locale.

## Presentation formatting

`LocalizedDateFormatter` formats human-facing dates, times and relative values through Laravel's locale-aware number/date facilities. Machine values such as CSV columns, filenames, database-normalized timestamps and `datetime-local` form values remain canonical and are never reused as display labels. `MoneyFormatter::formatCents()` formats integer minor units with the active locale and ISO currency; formatting never feeds storage or arithmetic. Plural messages use complete locale-specific forms and are regression-tested at singular, paucal and plural boundaries. The first-party Livewire pagination overrides use the same semantic catalogue for result ranges, navigation actions and accessibility labels.

## Verification

The audit enforces valid flat JSON, exact key parity, semantic keys, non-empty values, placeholder parity, compatible plural structures, zero phrase-style calls and zero unused keys. The scanner covers PHP, Blade, JavaScript and MJS direct, indirect and bounded dynamic key construction, including runtime validation attributes. Tests render critical routes in all locales, exercise locale persistence, localized validation, notifications, pagination, dates, times, money and guest menu data, and assert that raw keys do not appear in normal paths. Translated text expansion is included in the responsive browser matrix.

Final evidence on 2026-08-24: exactly 2,157 used semantic keys in each locale and 6,471 total entries, with no missing, extra, unused, empty, invalid, nested, placeholder-incompatible, plural-incompatible or phrase-style entries. The scanner inspected 635 first-party files. The focused localization suite passed 33 tests with 28,921 assertions; the current sequential and parallel repository suites each pass 1,458 total tests with 1,450 passed, 8 feature-gated skips and 44,532 assertions, and canonical application coverage passes at 93.5%. `docs/TRANSLATION_KEY_MAP.md` remains a namespace reference; this file is the canonical policy.
