# Field Translation Audit

Last verified: 2026-06-05
Runtime: Laravel 13.13.0, PHP 8.5, SQLite.

## Result

All implemented visible field labels, placeholders, help text contracts, and
validation attributes covered by seeded demo screens have JSON translation keys
in `lang/en.json`, `lang/lt.json`, and `lang/ru.json`.

The automated guard lives in `tests/Feature/FieldTranslationAuditTest.php` and
checks both the field manifest and visible Blade controls. It prevents new raw
literal `label`, `placeholder`, `description`, `aria-label`, or `title`
attributes from being introduced on form controls.

## Forms Checked

- Organization form.
- Brand form.
- Branch form.
- Branch settings form.
- Area node form.
- Service point form.
- QR form/page.
- Menu form.
- Category form.
- Item form.
- Variant form contract.
- Modifier form.
- Allergen/tag form contract.
- Staff form.
- Invitation form.
- Permission override form.
- Guest name form.
- Waiter review form.
- Payment form.
- Report filters.
- Superadmin backup/system forms.
- Security two-factor forms.

## Missing Keys Fixed

Reusable placeholders:

- `fields.placeholders.branch_email_example`
- `fields.placeholders.email_example`
- `fields.placeholders.facebook_url_example`
- `fields.placeholders.instagram_url_example`
- `fields.placeholders.phone_example`
- `fields.placeholders.service_point_prefix_example`
- `fields.placeholders.tiktok_url_example`
- `fields.placeholders.website_url_example`
- `qr.placeholders.short_code_example`

Form labels and help text:

- `menu.forms.allergen`
- `menu.forms.allergen_help`
- `menu.forms.tag`
- `menu.forms.tag_help`
- `menu.forms.variant`
- `menu.forms.variant_help`
- `payments.forms.tips_amount`
- `permissions.forms.override_state`
- `reports.filters.date_from`
- `reports.filters.date_to`
- `reports.filters.type`

Validation attributes:

- `validation.attributes.address`
- `validation.attributes.allergen`
- `validation.attributes.amount`
- `validation.attributes.area_node_id`
- `validation.attributes.backup_reason`
- `validation.attributes.bulk_prefix`
- `validation.attributes.capacity`
- `validation.attributes.category_name`
- `validation.attributes.city`
- `validation.attributes.code`
- `validation.attributes.country`
- `validation.attributes.critical_reason`
- `validation.attributes.currency`
- `validation.attributes.date_from`
- `validation.attributes.date_to`
- `validation.attributes.display_number`
- `validation.attributes.email`
- `validation.attributes.facebook_url`
- `validation.attributes.guest_name`
- `validation.attributes.icon`
- `validation.attributes.instagram_url`
- `validation.attributes.is_active`
- `validation.attributes.item_calories`
- `validation.attributes.item_name`
- `validation.attributes.item_price`
- `validation.attributes.item_volume`
- `validation.attributes.item_weight`
- `validation.attributes.logo`
- `validation.attributes.manual_guest_name`
- `validation.attributes.menu_name`
- `validation.attributes.menu_status`
- `validation.attributes.modifier_group_id`
- `validation.attributes.name`
- `validation.attributes.note`
- `validation.attributes.override_state`
- `validation.attributes.parent_id`
- `validation.attributes.payment_method`
- `validation.attributes.phone`
- `validation.attributes.polling_interval_seconds`
- `validation.attributes.preset`
- `validation.attributes.price_delta`
- `validation.attributes.public_name`
- `validation.attributes.quantity`
- `validation.attributes.reason`
- `validation.attributes.rejection_reason`
- `validation.attributes.recovery_code`
- `validation.attributes.role_id`
- `validation.attributes.search`
- `validation.attributes.service_charge_percent`
- `validation.attributes.short_code`
- `validation.attributes.sort_order`
- `validation.attributes.suspension_reason`
- `validation.attributes.tag`
- `validation.attributes.temporary_closed_reason`
- `validation.attributes.temporary_closed_until`
- `validation.attributes.tiktok_url`
- `validation.attributes.timezone`
- `validation.attributes.tips_amount`
- `validation.attributes.type`
- `validation.attributes.variant`
- `validation.attributes.website_url`

Raw visible placeholders replaced in Blade:

- Auth email fields now use `fields.placeholders.email_example`.
- Branch settings phone/email/site/social fields now use `fields.placeholders.*`.
- Service point bulk prefix now uses `fields.placeholders.service_point_prefix_example`.
- QR short-code lookup now uses `qr.placeholders.short_code_example`.
- Two-factor OTP fields now use `ui.auth.two_factor_challenge.authentication_code`.

## Remaining

No remaining missing translation keys were found for implemented forms.

Dedicated standalone variant, allergen, and tag forms are not implemented as
separate UI screens in the current product; their label/help/validation keys are
reserved in the audit so future UI cannot ship without translations.

## Verification

```bash
php artisan test --compact tests/Feature/FieldTranslationAuditTest.php
php artisan test --compact tests/Unit/TranslationStandardTest.php
php artisan translations:audit
php artisan translations:scan --json
```
