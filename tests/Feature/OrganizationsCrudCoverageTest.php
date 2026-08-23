<?php

declare(strict_types=1);

use Tests\Support\OrganizationCrudMatrix;

test('the organizations CRUD inventory is defined', function (): void {
    expect(OrganizationCrudMatrix::resources())->toHaveCount(26)
        ->and(array_keys(OrganizationCrudMatrix::resources()))->toBe([
            'organization',
            'brand',
            'branch',
            'branch_public_profile',
            'branch_settings',
            'opening_hours',
            'temporary_closure',
            'organization_staff',
            'branch_staff',
            'invitation',
            'permission_override',
            'area_node',
            'service_point',
            'qr_identity',
            'kitchen_department',
            'menu',
            'menu_schedule',
            'menu_category',
            'menu_item',
            'menu_item_images',
            'menu_item_availability',
            'modifier_group',
            'modifier_option',
            'item_modifier_assignment',
            'menu_item_variant',
            'branch_subscription_context',
        ]);
});

test('every organizations CRUD resource has complete executable evidence', function (): void {
    foreach (OrganizationCrudMatrix::resources() as $key => $resource) {
        expect($resource)
            ->toHaveKeys([
                'resource',
                'surface',
                'create',
                'read',
                'update',
                'delete',
                'fixture',
                'feature_test',
                'implementation_paths',
            ])
            ->and(array_filter([
                $resource['resource'],
                $resource['surface'],
                $resource['create'],
                $resource['read'],
                $resource['update'],
                $resource['delete'],
                $resource['fixture'],
                $resource['feature_test'],
            ], fn (string $value): bool => trim($value) === ''))
            ->toBe([], "CRUD resource [{$key}] contains an empty contract value.")
            ->and($resource['implementation_paths'])
            ->not->toBeEmpty();

        expect(base_path($resource['feature_test']))
            ->toBeFile("CRUD resource [{$key}] evidence test is missing.");

        foreach ($resource['implementation_paths'] as $implementationPath) {
            expect(base_path($implementationPath))
                ->toBeFile("CRUD resource [{$key}] implementation [{$implementationPath}] is missing.");
        }
    }
});
