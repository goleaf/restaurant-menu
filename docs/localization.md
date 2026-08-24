# Localization

## Supported locales

The application supports English (`en`), Lithuanian (`lt`) and Russian (`ru`), with English as the fallback. User preference is stored on `users.locale`; guest/public locale behavior follows the existing session/interface strategy. PHP/JSON translation resources remain the single translation system.

## Contributor workflow

1. Reuse or add one stable semantic key; do not concatenate translated sentence fragments.
2. Add the key to every supported locale in the same change.
3. Keep placeholders identical across locales and preserve plural forms.
4. Use framework/Flux validation and error integration with localized custom messages.
5. Format dates, times, relative values, numbers, percentages, lists and currency for the active locale; persist raw time/minor-unit values.
6. Translate visible labels, placeholders, buttons, headings, states, notifications, confirmations, emails, accessibility names and public metadata.
7. Run `php artisan translations:scan --json`, `php artisan translations:audit` and the locale-focused Pest tests.

Blade and PHP may not hardcode new user-facing messages. Enum/store values remain stable canonical data; localized labels are obtained through translation keys. API/business errors intended for users carry a stable error type and localized presentation message rather than a raw exception.

Restaurant onboarding initializes editable area, table, menu, category and sample-dish names through the active JSON locale. Country and currency stay unselected until the user chooses them; the time-zone suggestion uses the configured application time zone only when it is a Laravel-supported identifier and otherwise falls back to `UTC`. The country control validates an ISO 3166-1 alpha-2 code and resolves it to the existing canonical `branches.country` value for backward compatibility. A separate `country_code` column is intentionally not added while no query, integrity rule or downstream integration consumes it; duplicating the same fact would introduce synchronization risk without a demonstrated schema benefit.

## Verification

The audit enforces valid JSON, complete key parity and placeholder parity. Tests render critical routes in all locales, exercise fallback behavior, localized validation and notifications, and assert that raw keys do not appear in normal paths. Translated text expansion is included in the responsive browser matrix.

Final evidence on 2026-08-24: 2,322 semantic keys in each locale, 6,966 total entries, no missing/invalid/bad/legacy/phrase-style keys and zero critical audit issues. The scanner inspected 604 first-party files, found 1,718 used semantic keys and no missing or phrase-style calls. `docs/TRANSLATION_KEY_MAP.md` remains a namespace reference; this file is the canonical policy.
