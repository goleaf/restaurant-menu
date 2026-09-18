<?php

use App\Support\Floor\FloorOptions;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Assert;

test('rooms and table icon choices have translated labels for every persisted icon', function () {
    foreach (['en' => 'Hall', 'lt' => 'Salė', 'ru' => 'Зал'] as $locale => $hall) {
        app()->setLocale($locale);
        $options = FloorOptions::iconOptions();
        expect(array_keys($options))->toBe(FloorOptions::icons())
            ->and($options['rectangle-group'])->toBe($hall);
        foreach ($options as $label) {
            expect($label)->not->toBeEmpty()->not->toStartWith('floor.');
        }
    }
});

test('field translation manifest has labels placeholders help text and validation attributes in every locale', function () {
    $translations = fieldTranslationAuditTranslations();
    $manifest = fieldTranslationAuditManifest();

    foreach ($manifest as $formName => $fields) {
        expect($fields)->not->toBeEmpty($formName.' must list checked fields.');

        foreach ($fields as $fieldName => $contracts) {
            Assert::assertArrayHasKey(
                'label',
                $contracts,
                "{$formName}.{$fieldName} must define a label key.",
            );

            Assert::assertArrayHasKey(
                'attribute',
                $contracts,
                "{$formName}.{$fieldName} must define a validation attribute key.",
            );

            foreach (['label', 'placeholder', 'help', 'attribute'] as $part) {
                if (! isset($contracts[$part])) {
                    continue;
                }

                fieldTranslationAuditAssertKeyExists(
                    translations: $translations,
                    key: $contracts[$part],
                    context: "{$formName}.{$fieldName}.{$part}",
                );
            }
        }
    }
});

test('visible form controls do not expose raw literal field labels or placeholders', function () {
    $findings = [];

    foreach (fieldTranslationAuditBladeFiles() as $file) {
        $contents = File::get($file->getPathname());

        preg_match_all(
            '/<(?:flux:)?(?:input|textarea|select|checkbox|switch|radio|radio\.group|otp)\b(?P<attrs>[^>]*)>/su',
            $contents,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($matches['attrs'] as [$attributes, $offset]) {
            $line = substr_count(substr($contents, 0, $offset), "\n") + 1;

            foreach (fieldTranslationAuditVisibleAttributes() as $attribute) {
                if (! preg_match('/(?:^|\s)'.preg_quote($attribute, '/').'="([^"]*)"/u', $attributes, $attributeMatch)) {
                    continue;
                }

                $value = trim($attributeMatch[1]);

                if ($value !== '' && ! fieldTranslationAuditIsTranslatedAttribute($value)) {
                    $findings[] = sprintf(
                        '%s:%d %s="%s"',
                        fieldTranslationAuditRelativePath($file->getPathname()),
                        $line,
                        $attribute,
                        $value,
                    );
                }
            }
        }
    }

    expect($findings)->toBe([]);
});

/**
 * @return array<string, array<string, array{label: string, attribute: string, placeholder?: string, help?: string}>>
 */
function fieldTranslationAuditManifest(): array
{
    return [
        'organization form' => [
            'name' => ['label' => 'center.organization_name', 'attribute' => 'validation.attributes.name'],
            'logo' => ['label' => 'uploads.labels.logo', 'attribute' => 'validation.attributes.logo'],
        ],
        'brand form' => [
            'name' => ['label' => 'center.brand_name', 'attribute' => 'validation.attributes.name'],
            'logo' => ['label' => 'uploads.labels.logo', 'attribute' => 'validation.attributes.logo'],
        ],
        'branch form' => [
            'name' => ['label' => 'center.restaurant_name', 'attribute' => 'validation.attributes.name'],
            'address' => ['label' => 'center.address', 'attribute' => 'validation.attributes.address'],
            'city' => ['label' => 'center.city', 'attribute' => 'validation.attributes.city'],
            'country' => ['label' => 'center.country', 'attribute' => 'validation.attributes.country'],
            'timezone' => ['label' => 'center.timezone', 'attribute' => 'validation.attributes.timezone'],
            'currency' => ['label' => 'center.currency', 'attribute' => 'validation.attributes.currency'],
            'active' => ['label' => 'center.administrative_active', 'attribute' => 'validation.attributes.is_active'],
        ],
        'restaurant onboarding form' => [
            'organization_name' => ['label' => 'center.organization_name', 'attribute' => 'validation.attributes.organization_name'],
            'brand_name' => ['label' => 'center.brand_name', 'attribute' => 'validation.attributes.brand_name'],
            'branch_name' => ['label' => 'center.restaurant_name', 'attribute' => 'validation.attributes.branch_name'],
            'branch_address' => ['label' => 'center.address', 'attribute' => 'validation.attributes.branch_address'],
            'branch_city' => ['label' => 'center.city', 'attribute' => 'validation.attributes.branch_city'],
            'branch_country' => ['label' => 'center.country', 'attribute' => 'validation.attributes.branch_country'],
            'branch_timezone' => ['label' => 'center.timezone', 'attribute' => 'validation.attributes.branch_timezone'],
            'branch_currency' => ['label' => 'center.currency', 'attribute' => 'validation.attributes.branch_currency'],
            'area_name' => ['label' => 'center.room_name', 'attribute' => 'validation.attributes.area_name'],
            'area_type' => ['label' => 'ui.onboarding.restaurant_setup.cto_eto', 'attribute' => 'validation.attributes.area_type'],
            'area_icon' => ['label' => 'ui.onboarding.restaurant_setup.ikonka', 'attribute' => 'validation.attributes.area_icon'],
            'table_count' => ['label' => 'center.table_count', 'attribute' => 'validation.attributes.table_count'],
            'table_prefix' => ['label' => 'center.table_prefix', 'attribute' => 'validation.attributes.table_prefix'],
            'table_capacity' => ['label' => 'center.capacity', 'attribute' => 'validation.attributes.table_capacity'],
            'menu_name' => ['label' => 'center.menu_name', 'attribute' => 'validation.attributes.menu_name'],
            'category_name' => ['label' => 'center.category_name', 'attribute' => 'validation.attributes.category_name'],
            'item_name' => ['label' => 'center.item_name', 'attribute' => 'validation.attributes.item_name'],
            'item_price' => ['label' => 'center.price', 'attribute' => 'validation.attributes.item_price'],
        ],
        'availability pause form' => [
            'pause.reason' => ['label' => 'availability.public_reason', 'help' => 'availability.public_reason_description', 'attribute' => 'availability.fields.reason'],
            'pause.untilDate' => ['label' => 'availability.until_date', 'placeholder' => 'availability.choose_date', 'attribute' => 'availability.fields.deadline'],
            'pause.untilTime' => ['label' => 'availability.until_time', 'placeholder' => 'availability.choose_time', 'attribute' => 'availability.fields.deadline'],
        ],
        'branch settings form' => [
            'public_name' => ['label' => 'ui.organizations.brands.branches.settings.venue_name', 'attribute' => 'validation.attributes.public_name'],
            'phone' => ['label' => 'ui.organizations.brands.branches.settings.phone', 'placeholder' => 'fields.placeholders.phone_example', 'attribute' => 'validation.attributes.phone'],
            'email' => ['label' => 'ui.auth.reset_password.email', 'placeholder' => 'fields.placeholders.branch_email_example', 'attribute' => 'validation.attributes.email'],
            'website_url' => ['label' => 'guest.table.website', 'placeholder' => 'fields.placeholders.website_url_example', 'attribute' => 'validation.attributes.website_url'],
            'instagram_url' => ['label' => 'ui.organizations.brands.branches.settings.instagram_link', 'placeholder' => 'fields.placeholders.instagram_url_example', 'attribute' => 'validation.attributes.instagram_url'],
            'facebook_url' => ['label' => 'ui.organizations.brands.branches.settings.facebook_link', 'placeholder' => 'fields.placeholders.facebook_url_example', 'attribute' => 'validation.attributes.facebook_url'],
            'tiktok_url' => ['label' => 'ui.organizations.brands.branches.settings.tiktok_link', 'placeholder' => 'fields.placeholders.tiktok_url_example', 'attribute' => 'validation.attributes.tiktok_url'],
            'service_charge_percent' => ['label' => 'ui.organizations.brands.branches.settings.service_charge_percent', 'attribute' => 'validation.attributes.service_charge_percent'],
            'polling_interval_seconds' => ['label' => 'ui.organizations.brands.branches.settings.polling_interval_seconds', 'attribute' => 'validation.attributes.polling_interval_seconds'],
        ],
        'area_node form' => [
            'name' => ['label' => 'ui.onboarding.restaurant_setup.nazvanie_zony', 'attribute' => 'validation.attributes.name'],
            'type' => ['label' => 'ui.onboarding.restaurant_setup.cto_eto', 'attribute' => 'validation.attributes.type'],
            'icon' => ['label' => 'ui.onboarding.restaurant_setup.ikonka', 'attribute' => 'validation.attributes.icon'],
            'parent' => ['label' => 'ui.organizations.brands.branches.area_node_row.gde_naxoditsia', 'attribute' => 'validation.attributes.parent_id'],
            'sort_order' => ['label' => 'ui.organizations.brands.branches.area_node_row.poriadok_v_spiske', 'attribute' => 'validation.attributes.sort_order'],
            'active' => ['label' => 'ui.organizations.brands.branches.area_node_row.ispolzovat_seicas', 'attribute' => 'validation.attributes.is_active'],
        ],
        'service_point form' => [
            'name' => ['label' => 'floor.fields.name', 'attribute' => 'validation.attributes.name'],
            'display_number' => ['label' => 'floor.fields.number', 'attribute' => 'validation.attributes.display_number'],
            'type' => ['label' => 'floor.fields.type', 'attribute' => 'validation.attributes.type'],
            'area' => ['label' => 'floor.fields.area', 'attribute' => 'validation.attributes.area_node_id'],
            'capacity' => ['label' => 'floor.fields.capacity', 'attribute' => 'validation.attributes.capacity'],
            'bulk_prefix' => ['label' => 'floor.fields.prefix', 'attribute' => 'validation.attributes.bulk_prefix'],
            'search' => ['label' => 'floor.search_tables', 'attribute' => 'validation.attributes.search'],
        ],
        'QR form/page' => [
            'short_code' => ['label' => 'qr.labels.short_code', 'placeholder' => 'qr.placeholders.short_code_example', 'attribute' => 'validation.attributes.short_code'],
            'zone' => ['label' => 'qr.labels.zone', 'attribute' => 'validation.attributes.area_node_id'],
            'label_design' => ['label' => 'qr.print.label_design', 'attribute' => 'validation.attributes.preset'],
            'disable_reason' => ['label' => 'guest.table.reason', 'placeholder' => 'qr.placeholders.disable_reason', 'attribute' => 'validation.attributes.reason'],
        ],
        'menu/category/item/variant/modifier/allergen/tag forms' => [
            'menu_name' => ['label' => 'reports.csv.name', 'attribute' => 'validation.attributes.menu_name'],
            'menu_status' => ['label' => 'guest.table.status', 'attribute' => 'validation.attributes.menu_status'],
            'category_name' => ['label' => 'reports.csv.name', 'attribute' => 'validation.attributes.category_name'],
            'item_name' => ['label' => 'reports.csv.name', 'attribute' => 'validation.attributes.item_name'],
            'item_price' => ['label' => 'guest.cart.price', 'attribute' => 'validation.attributes.item_price'],
            'item_weight' => ['label' => 'reports.csv.weight', 'attribute' => 'validation.attributes.item_weight'],
            'item_volume' => ['label' => 'reports.csv.volume', 'attribute' => 'validation.attributes.item_volume'],
            'item_calories' => ['label' => 'reports.csv.calories', 'attribute' => 'validation.attributes.item_calories'],
            'variant' => ['label' => 'menu.variants.admin.title', 'help' => 'menu.variants.admin.description', 'attribute' => 'validation.attributes.variant'],
            'modifier_group' => ['label' => 'ui.organizations.brands.branches.menu.index.modifier_group', 'attribute' => 'validation.attributes.modifier_group_id'],
            'modifier_option_price_delta' => ['label' => 'ui.organizations.brands.branches.menu.index.price_change', 'attribute' => 'validation.attributes.price_delta'],
            'allergen' => ['label' => 'menu.allergens.title', 'help' => 'menu.allergens.help', 'attribute' => 'validation.attributes.allergen'],
            'tag' => ['label' => 'menu.dietary_labels.title', 'help' => 'menu.dietary_labels.help', 'attribute' => 'validation.attributes.tag'],
        ],
        'staff and invitation forms' => [
            'name' => ['label' => 'reports.csv.name', 'attribute' => 'validation.attributes.name'],
            'email' => ['label' => 'ui.auth.reset_password.email', 'placeholder' => 'fields.placeholders.email_example', 'attribute' => 'validation.attributes.email'],
            'phone' => ['label' => 'ui.organizations.brands.branches.settings.phone', 'attribute' => 'validation.attributes.phone'],
            'role' => ['label' => 'staff.role', 'attribute' => 'validation.attributes.role_id'],
        ],
        'permission draft form' => [
            'reason' => ['label' => 'staff.workspace.reason', 'help' => 'team.card.permission_scope', 'attribute' => 'permissions.forms.change_reason'],
            'states' => ['label' => 'team.card.access', 'help' => 'team.card.resource_rules', 'attribute' => 'permissions.labels.manage_permissions'],
            'confirmed' => ['label' => 'permissions.draft.confirm', 'attribute' => 'permissions.draft.confirm'],
        ],
        'guest name form' => [
            'guest_name' => ['label' => 'guest.table.your_name', 'placeholder' => 'guest.table.enter_name', 'attribute' => 'validation.attributes.guest_name'],
        ],
        'waiter review form' => [
            'manual_guest_name' => ['label' => 'ui.waiter.table_detail.new_guest_name', 'placeholder' => 'ui.waiter.table_detail.type_a_name_if_the_guest_is_not_in_the_list', 'attribute' => 'validation.attributes.manual_guest_name'],
            'quantity' => ['label' => 'guest.cart.quantity', 'attribute' => 'validation.attributes.quantity'],
            'rejection_reason' => ['label' => 'guest.table.reason', 'placeholder' => 'ui.waiter.table_detail.tell_guests_what_needs_to_change', 'attribute' => 'validation.attributes.rejection_reason'],
        ],
        'payment form' => [
            'method' => ['label' => 'payments.forms.method', 'attribute' => 'validation.attributes.payment_method'],
            'amount' => ['label' => 'payments.forms.amount', 'attribute' => 'validation.attributes.amount'],
            'note' => ['label' => 'payments.forms.note', 'attribute' => 'validation.attributes.note'],
        ],
        'superadmin backup/system forms' => [
            'backup_reason' => ['label' => 'guest.table.reason', 'placeholder' => 'ui.confirmations.reason.placeholder', 'attribute' => 'validation.attributes.backup_reason'],
            'suspension_reason' => ['label' => 'guest.table.reason', 'placeholder' => 'ui.confirmations.reason.placeholder', 'attribute' => 'validation.attributes.suspension_reason'],
        ],
        'security two-factor forms' => [
            'code' => ['label' => 'ui.auth.two_factor_challenge.authentication_code', 'attribute' => 'validation.attributes.code'],
            'recovery_code' => ['label' => 'ui.auth.two_factor_challenge.recovery_code', 'attribute' => 'validation.attributes.recovery_code'],
        ],
    ];
}

/**
 * @return array<string, array<string, string>>
 */
function fieldTranslationAuditTranslations(): array
{
    return collect(['en', 'lt', 'ru'])
        ->mapWithKeys(function (string $locale): array {
            $path = base_path("lang/{$locale}.json");

            Assert::assertTrue(File::exists($path), "Missing {$path}");

            return [$locale => json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR)];
        })
        ->all();
}

/**
 * @param  array<string, array<string, string>>  $translations
 */
function fieldTranslationAuditAssertKeyExists(array $translations, string $key, string $context): void
{
    Assert::assertMatchesRegularExpression(
        '/\A[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+\z/',
        $key,
        "{$context} must use a semantic dotted translation key.",
    );

    foreach ($translations as $locale => $lines) {
        Assert::assertArrayHasKey($key, $lines, "{$context} missing {$locale} key [{$key}].");
        Assert::assertNotSame('', trim($lines[$key]), "{$context} has empty {$locale} value for [{$key}].");
    }
}

/**
 * @return list<SplFileInfo>
 */
function fieldTranslationAuditBladeFiles(): array
{
    return collect(File::allFiles(resource_path('views')))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getPathname(), '.blade.php'))
        ->values()
        ->all();
}

/**
 * @return list<string>
 */
function fieldTranslationAuditVisibleAttributes(): array
{
    return ['label', 'placeholder', 'description', 'aria-label', 'title'];
}

function fieldTranslationAuditIsTranslatedAttribute(string $value): bool
{
    if (str_contains($value, '{{') || str_contains($value, '__(') || str_contains($value, '@lang(')) {
        return true;
    }

    return preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+\z/', $value) === 1;
}

function fieldTranslationAuditRelativePath(string $path): string
{
    return str_replace(base_path().'/', '', $path);
}
