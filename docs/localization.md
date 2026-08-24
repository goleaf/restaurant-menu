# Localization

## Supported locales

The application supports English (`en`), Lithuanian (`lt`) and Russian (`ru`). All interface text lives in the flat `lang/en.json`, `lang/lt.json` and `lang/ru.json` catalogues and uses semantic dot keys. English is the safe default for a new context, but it is not an accepted substitute for a missing Lithuanian or Russian value: the automated audit requires exact key parity, non-empty values and a narrow allowlist for language-neutral codes, units and example proper names.

An authenticated choice is stored in `users.locale`; the web session applies it immediately and restores it on later requests. Public/guest choices are stored in the web session and copied to both `table_session_guests.locale` and pending `table_session_join_requests.locale`, so approval, cookie restoration and later QR scans retain the guest's language. A valid explicit `?lang=` choice has request precedence; unsupported values are ignored rather than persisted.

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

## Presentation formatting

`LocalizedDateFormatter` formats human-facing dates, times and relative values through Laravel's locale-aware number/date facilities. Machine values such as CSV columns, filenames, database-normalized timestamps and `datetime-local` form values remain canonical and are never reused as display labels. `MoneyFormatter::formatCents()` formats integer minor units with the active locale and ISO currency; formatting never feeds storage or arithmetic. Plural messages use complete locale-specific forms and are regression-tested at singular, paucal and plural boundaries. The first-party Livewire pagination overrides use the same semantic catalogue for result ranges, navigation actions and accessibility labels.

## Verification

The audit enforces valid flat JSON, exact key parity, semantic keys, non-empty values, placeholder parity, compatible plural structures, zero phrase-style calls and zero unused keys. The scanner covers PHP, Blade, JavaScript and MJS direct, indirect and bounded dynamic key construction, including runtime validation attributes. Tests render critical routes in all locales, exercise locale persistence, localized validation, notifications, pagination, dates, times, money and guest menu data, and assert that raw keys do not appear in normal paths. Translated text expansion is included in the responsive browser matrix.

Final evidence on 2026-08-24: exactly 2,157 used semantic keys in each locale and 6,471 total entries, with no missing, extra, unused, empty, invalid, nested, placeholder-incompatible, plural-incompatible or phrase-style entries. The scanner inspected 635 first-party files. The focused localization suite passed 33 tests with 28,921 assertions; the current sequential and parallel repository suites each pass 1,458 total tests with 1,450 passed, 8 feature-gated skips and 44,532 assertions, and canonical application coverage passes at 93.5%. `docs/TRANSLATION_KEY_MAP.md` remains a namespace reference; this file is the canonical policy.
