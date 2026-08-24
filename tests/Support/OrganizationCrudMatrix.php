<?php

declare(strict_types=1);

namespace Tests\Support;

final class OrganizationCrudMatrix
{
    /**
     * @return array<string, array{
     *     resource: string,
     *     surface: string,
     *     create: string,
     *     read: string,
     *     update: string,
     *     delete: string,
     *     fixture: string,
     *     feature_test: string,
     *     implementation_paths: list<string>
     * }>
     */
    public static function resources(): array
    {
        return [
            'organization' => self::resource('Organization', '/organizations', 'Create organization', 'Paginate accessible organizations', 'Update identity and logo', 'Soft delete after confirmation', 'Canonical demo organization with logo', 'tests/Feature/OrganizationManagementTest.php', ['app/Livewire/Organizations/Index.php', 'app/Actions/Organizations/CreateOrganizationAction.php']),
            'brand' => self::resource('Brand', 'Organization brands', 'Create brand', 'Paginate tenant brands', 'Update identity and logo', 'Soft delete after confirmation', 'Three demo brands with media coverage', 'tests/Feature/BrandManagementTest.php', ['app/Livewire/Organizations/Brands/Index.php', 'app/Actions/Brands/CreateBrandAction.php']),
            'branch' => self::resource('Branch', 'Brand branches', 'Create branch', 'Paginate authorized branches', 'Update identity, locale, currency and state', 'Soft delete after confirmation', 'Active and inactive demo branches', 'tests/Feature/BranchManagementTest.php', ['app/Livewire/Organizations/Brands/Branches/Index.php', 'app/Actions/Branches/CreateBranchAction.php']),
            'branch_public_profile' => self::resource('Branch public profile', 'Branch settings', 'Create defaults with branch', 'Read current public profile', 'Update contact, social and media fields', 'Remove media and clear optional fields', 'Complete profile with local media', 'tests/Feature/RestaurantPublicProfileTest.php', ['app/Livewire/Organizations/Brands/Branches/Settings.php', 'app/Actions/Branches/UpdateBranchPublicProfileAction.php']),
            'branch_settings' => self::resource('Branch settings', 'Branch settings', 'Ensure singleton exists', 'Read current settings', 'Update guest, service and locale rules', 'Reset through validated defaults', 'Complete settings for every demo branch', 'tests/Feature/BranchSettingsTest.php', ['app/Livewire/Organizations/Brands/Branches/Settings.php', 'app/Actions/Branches/UpdateBranchSettingsAction.php']),
            'opening_hours' => self::resource('Opening hours', 'Branch settings', 'Add opening intervals', 'Read weekly schedule', 'Replace opening intervals', 'Remove intervals or close day', 'Regular, split and closed demo days', 'tests/Feature/BranchOpeningHoursTest.php', ['app/Livewire/Organizations/Brands/Branches/Settings.php', 'app/Actions/Branches/UpdateBranchOpeningHoursAction.php']),
            'temporary_closure' => self::resource('Temporary closure', 'Branch settings', 'Set temporary closure', 'Read closure state', 'Update reason and end time', 'Clear closure', 'One bounded demo closure', 'tests/Feature/BranchTemporaryClosedModeTest.php', ['app/Livewire/Organizations/Brands/Branches/Settings.php', 'app/Actions/Branches/UpdateBranchTemporaryClosureAction.php']),
            'organization_staff' => self::resource('Organization staff', 'Organization staff', 'Add member', 'Paginate organization members', 'Update role and status', 'Suspend or reactivate membership', 'All roles and a suspended member', 'tests/Feature/StaffManagementUiTest.php', ['app/Livewire/Organizations/Staff/Index.php', 'app/Actions/Staff/AddOrganizationStaffMemberAction.php']),
            'branch_staff' => self::resource('Branch staff', 'Branch staff', 'Assign organization member', 'Paginate branch assignments', 'Update role, status and waiter areas', 'Suspend, reactivate or detach lifecycle', 'Active and suspended assignments', 'tests/Feature/StaffManagementUiTest.php', ['app/Livewire/Organizations/Brands/Branches/Staff/Index.php', 'app/Actions/Staff/AddBranchStaffMemberAction.php']),
            'invitation' => self::resource('Invitation', 'Organization and branch staff', 'Create invitation link or code', 'Paginate invitation history', 'Keep recipient and expiry immutable', 'Cancel pending invitation', 'Pending, expired and cancelled invitations', 'tests/Feature/StaffInvitationTest.php', ['app/Actions/Invitations/CreateInvitationAction.php', 'app/Livewire/Organizations/Staff/Index.php']),
            'permission_override' => self::resource('Permission override', 'Staff permissions', 'Set allow or deny', 'Read grouped effective matrix', 'Switch allow or deny', 'Return to role default', 'One allow and one deny override', 'tests/Feature/PermissionOverrideUiTest.php', ['app/Livewire/Organizations/Staff/Permissions.php', 'app/Actions/Staff/SetUserPermissionOverrideAction.php']),
            'area_node' => self::resource('Area node', 'Branch areas', 'Create root or child area', 'Read ordered tree', 'Update hierarchy, icon, order and state', 'Soft delete after confirmation', 'Nested active and inactive areas', 'tests/Feature/AreaNodeCrudTest.php', ['app/Livewire/Organizations/Brands/Branches/Areas.php', 'app/Actions/AreaNodes/CreateAreaNodeAction.php']),
            'service_point' => self::resource('Service point', 'Branch service points', 'Create one or many service points', 'Search, filter and paginate', 'Update identity, location and state', 'Soft delete only without active session', 'Multiple types, statuses and QR states', 'tests/Feature/ServicePointCrudTest.php', ['app/Livewire/Organizations/Brands/Branches/ServicePoints/Index.php', 'app/Actions/ServicePoints/CreateServicePointAction.php']),
            'qr_identity' => self::resource('QR identity', 'Service point QR pages', 'Generate missing identity', 'Show, download and print', 'Reissue identity', 'Disable or revoke old identity', 'Active and historical QR identities', 'tests/Feature/PermanentQrFunctionalTest.php', ['app/Livewire/Organizations/Brands/Branches/ServicePoints/Qr/Show.php', 'app/Actions/QrCodes/ReissueQrCodeForServicePointAction.php']),
            'kitchen_department' => self::resource('Kitchen department', 'Branch menu departments', 'Create department', 'Read ordered list', 'Update identity, type, order and state', 'Delete when assignments and history allow', 'Kitchen, bar and inactive custom department', 'tests/Feature/KitchenDepartmentTest.php', ['app/Livewire/Organizations/Brands/Branches/Menu/KitchenDepartments.php', 'app/Actions/KitchenDepartments/CreateKitchenDepartmentAction.php']),
            'menu' => self::resource('Menu', 'Branch menu catalog', 'Create menu', 'Read ordered list', 'Update name, status and order', 'Soft delete after confirmation', 'Active, draft and archived menus', 'tests/Feature/MenuCrudTest.php', ['app/Livewire/Organizations/Brands/Branches/Menu/Catalog.php', 'app/Actions/Menus/CreateMenuAction.php']),
            'menu_schedule' => self::resource('Menu availability schedule', 'Branch menu catalog', 'Create interval', 'Read ordered intervals', 'Update day and time range', 'Delete interval', 'Weekday and weekend intervals', 'tests/Feature/MenuScheduleTest.php', ['app/Livewire/Organizations/Brands/Branches/Menu/Catalog.php', 'app/Actions/Menus/CreateMenuAvailabilityScheduleAction.php']),
            'menu_category' => self::resource('Menu category', 'Branch menu catalog', 'Create root or child category', 'Read ordered tree', 'Update base and localized content', 'Soft delete after confirmation', 'Localized active and inactive categories', 'tests/Feature/MenuCrudTest.php', ['app/Livewire/Organizations/Brands/Branches/Menu/Catalog.php', 'app/Actions/Menus/CreateMenuCategoryAction.php']),
            'menu_item' => self::resource('Menu item', 'Branch menu catalog', 'Create dish', 'Read ordered list', 'Update content, price, nutrition and department', 'Soft delete after confirmation', 'Localized available and unavailable dishes', 'tests/Feature/MenuCrudTest.php', ['app/Livewire/Organizations/Brands/Branches/Menu/Catalog.php', 'app/Actions/Menus/CreateMenuItemAction.php']),
            'menu_item_images' => self::resource('Menu item images', 'Dish edit form', 'Upload up to eight images', 'Read primary and ordered gallery', 'Promote gallery image to primary', 'Remove image and clean parent media', 'Primary and secondary representative images', 'tests/Feature/MenuItemImageGalleryTest.php', ['app/Livewire/Organizations/Brands/Branches/Menu/Catalog.php', 'app/Actions/Menus/AddMenuItemImagesAction.php']),
            'menu_item_availability' => self::resource('Menu item availability', 'Branch menu availability', 'Create availability with dish', 'Read catalog and stop list', 'Mark available or unavailable', 'Unavailable hides from guest menu', 'Both availability states', 'tests/Feature/MenuCrudTest.php', ['app/Livewire/Organizations/Brands/Branches/Menu/Availability.php', 'app/Actions/Menus/SetMenuItemAvailabilityAction.php']),
            'modifier_group' => self::resource('Modifier group', 'Branch menu modifiers', 'Create group', 'Read ordered groups', 'Update required limits and order', 'Delete group', 'Required and optional groups', 'tests/Feature/MenuCrudTest.php', ['app/Livewire/Organizations/Brands/Branches/Menu/Modifiers.php', 'app/Actions/Modifiers/CreateModifierGroupAction.php']),
            'modifier_option' => self::resource('Modifier option', 'Branch menu modifiers', 'Create option', 'Read nested options', 'Update name, price, availability and order', 'Delete option', 'Free, surcharge, discount and unavailable options', 'tests/Feature/MenuCrudTest.php', ['app/Livewire/Organizations/Brands/Branches/Menu/Modifiers.php', 'app/Actions/Modifiers/CreateModifierOptionAction.php']),
            'item_modifier_assignment' => self::resource('Item modifier assignment', 'Branch menu modifiers', 'Attach group to dish', 'Read assigned groups', 'Reattach idempotently', 'Detach group', 'Assigned and unassigned dishes', 'tests/Feature/MenuCrudTest.php', ['app/Livewire/Organizations/Brands/Branches/Menu/Modifiers.php', 'app/Actions/Modifiers/AssignModifierGroupToMenuItemAction.php']),
            'menu_item_variant' => self::resource('Menu item variant', 'Branch menu variants', 'Create variant', 'Read ordered variants', 'Update data and EN/LT/RU content', 'Delete with default invariant', 'Default, optional and unavailable variants', 'tests/Feature/MenuItemVariantTest.php', ['app/Livewire/Organizations/Brands/Branches/Menu/Variants.php', 'app/Actions/Menus/CreateMenuItemVariantAction.php']),
            'branch_subscription_context' => self::resource('Branch subscription context', 'Organization access', 'Ensure subscription', 'Evaluate organization access', 'Transition status', 'Use inactive lifecycle state', 'Active canonical subscription', 'tests/Feature/OrganizationSubscriptionTest.php', ['app/Actions/Subscriptions/EnsureOrganizationSubscriptionAction.php', 'app/Actions/Subscriptions/SetOrganizationSubscriptionStatusAction.php']),
        ];
    }

    /**
     * @param  list<string>  $implementationPaths
     * @return array{resource: string, surface: string, create: string, read: string, update: string, delete: string, fixture: string, feature_test: string, implementation_paths: list<string>}
     */
    private static function resource(string $resource, string $surface, string $create, string $read, string $update, string $delete, string $fixture, string $featureTest, array $implementationPaths): array
    {
        return [
            'resource' => $resource,
            'surface' => $surface,
            'create' => $create,
            'read' => $read,
            'update' => $update,
            'delete' => $delete,
            'fixture' => $fixture,
            'feature_test' => $featureTest,
            'implementation_paths' => $implementationPaths,
        ];
    }
}
