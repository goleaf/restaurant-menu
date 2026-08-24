# Restaurant Onboarding Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `/onboarding/restaurant` as a clear, responsive eight-step setup flow with strict server validation, correct field semantics, bounded option catalogues, localized guidance, and verified browser accessibility.

**Architecture:** Preserve the existing Organization -> Brand -> Branch -> Area -> Service points -> QR -> Menu Actions and their transaction boundaries. Keep all mutable state in `RestaurantSetupForm`, keep Livewire as the authorized coordinator, and provide finite presentation options from a pure support catalogue. The redesign changes no route, database schema, tenant boundary, or permanent QR behavior.

**Tech Stack:** PHP 8.5, Laravel 13.26, Livewire 4.4 class components and Form objects, Flux UI Free 2.17, Blade SSR, Tailwind CSS 4.3 semantic tokens, SQLite, Pest 4.7, disposable Chrome.

---

## Field contract

| Step | Property | HTML control | Server contract |
| --- | --- | --- | --- |
| Company | `form.organizationName` | text, `autocomplete=organization` | normalized plain text, required string, max 120, unique for owner |
| Restaurant | `form.brandName` | text, `autocomplete=organization` | normalized plain text, required string, max 120, unique in organization |
| Branch | `form.branchName` | text, `autocomplete=off` because HTML has no branch-name token | normalized plain text, required string, max 160, unique in brand |
| Branch | `form.branchAddress` | text, `autocomplete=street-address` | normalized plain text, required string, max 255 |
| Branch | `form.branchCity` | text, `autocomplete=address-level2` | normalized plain text, required string, max 120 |
| Branch | `form.branchCountryCode` | text plus native datalist autocomplete | uppercase ISO 3166-1 alpha-2 code from finite catalogue |
| Branch | `form.branchTimezone` | text plus native datalist autocomplete | canonical PHP time-zone identifier, max 64 |
| Branch | `form.branchCurrency` | Flux select | three-character code from `SupportedCurrency` |
| Area | `form.areaName` | text | normalized plain text, required string, max 160 |
| Area | `form.areaType` | Flux select | one `AreaNodeType` value |
| Area | `form.areaIcon` | Flux select | one audited Heroicon name from the onboarding catalogue |
| Tables | `form.tableCount` | integer number input | 1 to 20, step 1 |
| Tables | `form.tablePrefix` | text | normalized plain text, required string, max 40 |
| Tables | `form.tableCapacity` | integer number input | 1 to 50, step 1 |
| Menu | `form.menuName` | text | normalized plain text, required string, max 160 |
| Menu | `form.categoryName` | text | normalized plain text, required string, max 160 |
| Menu | `form.itemName` | text | normalized plain text, required string, max 180 |
| Menu | `form.itemPrice` | decimal number input | non-negative decimal string, zero to 999999.99, at most two fractional digits |

## Task 1: Lock the validation and option contracts with failing tests

**Files:**
- Modify: `tests/Feature/OnboardingRestaurantWizardTest.php`
- Modify: `tests/Feature/FieldTranslationAuditTest.php`

- [x] **Step 1: Add dataset-driven invalid-boundary tests**

```php
test('restaurant onboarding rejects invalid field boundaries', function (string $action, string $property, mixed $value, string $rule): void {
    Livewire::actingAs(User::factory()->create())
        ->test(RestaurantSetup::class)
        ->set($property, $value)
        ->call($action)
        ->assertHasErrors([$property => $rule]);
})->with([
    'invalid time zone' => ['createBranch', 'form.branchTimezone', 'Europe/Not_A_Zone', 'timezone'],
    'unsupported currency' => ['createBranch', 'form.branchCurrency', 'BTC', 'in'],
    'too many tables' => ['createServicePoints', 'form.tableCount', 21, 'max'],
    'fractional precision' => ['createStarterMenu', 'form.itemPrice', '1.999', 'decimal'],
]);
```

- [x] **Step 2: Add direct option-catalogue and localized-attribute assertions**

```php
expect(RestaurantSetupOptions::countryCodes())->toContain('LT', 'US', 'JP')
    ->and(RestaurantSetupOptions::timezoneOptions())->toHaveKey('Europe/Vilnius');
```

- [x] **Step 3: Run RED and confirm missing contracts are the failure cause**

Run: `php artisan test --compact tests/Feature/OnboardingRestaurantWizardTest.php tests/Feature/FieldTranslationAuditTest.php`

Expected: failures for the missing country-code property/catalogue, missing decimal rule, incomplete option labels, or absent markup contract.

## Task 2: Implement the bounded catalogues and strict Form boundary

**Files:**
- Create: `app/Support/RestaurantSetupOptions.php`
- Modify: `app/Livewire/Forms/Onboarding/RestaurantSetupForm.php`
- Modify: `app/Support/Validation/RestaurantValidationRules.php`
- Modify: `app/Livewire/Onboarding/RestaurantSetup.php`

- [x] **Step 1: Implement finite country, timezone, area-type, and icon options**

```php
final class RestaurantSetupOptions
{
    private const ISO_ALPHA_2 = 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW';

    /** @return list<string> */
    public static function countryCodes(): array;

    /** @return array<string, string> */
    public static function countryOptions(string $locale): array;

    /** @return array<string, string> */
    public static function timezoneOptions(): array;

    /** @return array<string, string> */
    public static function areaIconOptions(): array;
}
```

The country implementation uses ICU display names when available and a stable code fallback; it never queries the database or calls the network.

- [x] **Step 2: Normalize before validation and use explicit rules**

Use `PlainText::required(..., squish: true)` for stored names/address, uppercase the country/currency codes, trim the timezone and enum values, and normalize a decimal comma to a decimal point. Use `bail`, finite `Rule::in(...)`, `timezone`, `integer`, `decimal:0,2`, and exact min/max constraints.

- [x] **Step 3: Give all errors localized human field names**

```php
protected function validationAttributes(): array
{
    return [
        'organizationName' => __('ui.onboarding.restaurant_setup.nazvanie_kompanii'),
        'branchCountryCode' => __('ui.onboarding.restaurant_setup.strana'),
        'itemPrice' => __('ui.onboarding.restaurant_setup.cena'),
    ];
}
```

- [x] **Step 4: Pass only validated, mapped data to existing Actions**

`RestaurantSetup::createBranch()` maps `branchCountryCode` to a stable English country name before `CreateBranchAction`; every other mutation keeps its existing Action and authorization path.

- [x] **Step 5: Run GREEN and static formatting**

Run: `php artisan test --compact tests/Feature/OnboardingRestaurantWizardTest.php tests/Feature/FieldTranslationAuditTest.php`

Run: `vendor/bin/pint --dirty --format agent`

Expected: all focused tests pass and Pint exits zero.

## Task 3: Replace the page with the Calm Service Pass onboarding layout

**Files:**
- Modify: `resources/views/livewire/onboarding/restaurant-setup.blade.php`

- [x] **Step 1: Replace the generic two-card shell**

Build one semantic page header, a mobile progress region, a desktop ordered step rail with `aria-current=step`, a wrapping created-context list, and one primary form surface. Use existing `canvas`, `surface`, `surface-muted`, `surface-selected`, `border-*`, `text-*`, `brand-*`, `rounded-*`, and focus tokens; add no stylesheet or dependency.

- [x] **Step 2: Render every field with the correct control metadata**

Every text/number control receives an explicit `name`, `type`, `autocomplete`, `inputmode`, `min`, `max`, `step`, and `maxlength` where applicable. Country and time-zone fields use native `<datalist>` options, while finite compact choices use `flux:select`.

- [x] **Step 3: Make validation, loading, and offline states explicit**

Render a localized validation callout when the active step has errors, retain per-field `flux:error`, disable only the active request with `wire:loading.attr=disabled`, preserve the original action label, and retain the shared authenticated offline indicator.

- [x] **Step 4: Redesign QR and completion states**

Present the QR count and permanence consequence as a flat state panel. On completion, make the guest menu the single primary action and render printing, branch settings, and menu management as a secondary semantic list rather than four identical cards.

- [x] **Step 5: Run view and architecture tests plus the production build**

Run: `php artisan test --compact tests/Feature/OnboardingRestaurantWizardTest.php tests/Feature/ProjectCleanupConsistencyTest.php`

Run: `npm run build`

Expected: the Blade boundary tests and Vite build pass with no dynamic utility or forbidden PHP/lookup in the view.

## Task 4: Localize the full interaction contract

**Files:**
- Modify: `lang/en.json`
- Modify: `lang/lt.json`
- Modify: `lang/ru.json`

- [x] **Step 1: Add exact parity keys**

Add labels, descriptions, examples, option labels, progress wording, validation recovery copy, created-context copy, QR consequences, and completion action descriptions in all three locales with identical placeholders.

- [x] **Step 2: Correct ambiguous existing labels**

Replace "Point name" / "What is this?" / "What to call it?" / "Guests at the table" with unambiguous localized branch name, area type, table-name prefix, and seats-per-table labels.

- [x] **Step 3: Run localization gates**

Run: `php artisan translations:scan --json`

Run: `php artisan translations:audit`

Expected: zero missing keys, zero phrase-style calls, and zero placeholder/parity issues.

## Task 5: Browser and final plan-to-result verification

**Files:**
- Modify: `tests/Browser/RegistrationToPaidTableClosureTest.php`
- Test: `tests/Feature/OnboardingRestaurantWizardTest.php`

- [x] **Step 1: Extend the isolated browser journey**

Assert the new mobile progress text, field roles/names, country/time-zone autocomplete lists, invalid-field recovery, all step transitions, and final primary/secondary actions while retaining the existing registration-to-paid-table flow.

- [x] **Step 2: Run focused and full automated gates**

Run: `php artisan test --compact tests/Feature/OnboardingRestaurantWizardTest.php tests/Feature/FieldTranslationAuditTest.php`

Run: `php artisan test --compact`

Run: `composer analyse`

Run: `vendor/bin/pint --dirty --format agent`

- [x] **Step 3: Run disposable-browser runtime checks**

Resolve the URL with Laravel Boost. In an isolated profile, verify 320, 390, 768, 1024, and 1440 CSS-pixel layouts; light/dark; EN/LT/RU expansion; keyboard order and visible focus; accessible names and `aria-current`; no horizontal overflow; and zero fresh console/network failures.

- [x] **Step 4: Audit the implementation against this plan**

Re-read every field-contract row and task checkbox, compare them with the final diff and observed commands, and report any skipped physical screen-reader/device evidence exactly. Confirm the final diff does not overwrite the concurrent staff/service-point/seeder work or the untracked product-wide UX plan.

## Final plan audit

- [x] All 18 field-contract rows are represented by explicit server rules and HTML metadata.
- [x] Country, timezone, currency, area type, and icon inputs use bounded catalogues; country and timezone retain native autocomplete through datalists.
- [x] The real Vite stylesheet is enabled in the browser test; CSS token loading is asserted before responsive and dark-theme claims.
- [x] EN, LT, and RU are selected through the real profile form and each rendered onboarding page is checked for language, visible heading, and responsive overflow.
- [x] The disposable browser scenario covers 320, 390, 768, 1024, and 1440 CSS pixels, light/dark tokens, Flux mobile/desktop state, visible keyboard focus, and zero JavaScript errors.
- [x] Focused Pest, Pint, PHPStan, translation audit/scan, production Vite build, Blade cache, and `composer analyse` were observed passing.
- [x] The final full Pest suite passed: 1,168 tests, 1,159 passed, 9 skipped, 0 failures/errors, and 25,782 assertions.
- [ ] A physical screen reader and physical mobile device were not available in this run; semantic HTML, accessible names, keyboard focus, responsive emulation, and automated browser checks are the available evidence.
- [x] No route, schema, dependency, tenant boundary, QR identity, or concurrent staff/service-point/seeder/product-wide-plan change was overwritten.
