<?php

declare(strict_types=1);

use App\Actions\AreaNodes\CreateAreaNodeAction;
use App\Enums\AreaNodeType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

test('every foreign key has a leading supporting index', function (): void {
    $violations = [];

    foreach (Schema::getTables() as $table) {
        $tableName = (string) $table['name'];
        $indexes = Schema::getIndexes($tableName);

        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            $foreignColumns = $foreignKey['columns'];
            $hasSupportingIndex = collect($indexes)->contains(
                fn (array $index): bool => array_slice($index['columns'], 0, count($foreignColumns)) === $foreignColumns,
            );

            if (! $hasSupportingIndex) {
                $violations[] = $tableName.'.'.implode(',', $foreignColumns);
            }
        }
    }

    expect($violations)->toBeEmpty();
});

test('non unique indexes do not duplicate another index prefix', function (): void {
    $violations = [];

    foreach (Schema::getTables() as $table) {
        $tableName = (string) $table['name'];
        $indexes = Schema::getIndexes($tableName);

        foreach ($indexes as $index) {
            if ($index['unique']) {
                continue;
            }

            $redundantWith = collect($indexes)->first(function (array $candidate) use ($index): bool {
                if ($candidate['name'] === $index['name']) {
                    return false;
                }

                if (count($candidate['columns']) < count($index['columns'])) {
                    return false;
                }

                return array_slice($candidate['columns'], 0, count($index['columns'])) === $index['columns'];
            });

            if (is_array($redundantWith)) {
                $violations[] = $tableName.'.'.$index['name'].' duplicates '.$redundantWith['name'];
            }
        }
    }

    expect($violations)->toBeEmpty();
});

test('public and tenant identities have their required unique indexes', function (): void {
    expect(databaseAuditIndex('organizations', ['owner_user_id', 'name'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('brands', ['organization_id', 'name'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('branches', ['brand_id', 'name'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('service_points', ['branch_id', 'internal_code'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('qr_codes', ['public_token'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('qr_codes', ['short_code'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('table_session_guests', ['guest_token'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('invitations', ['invite_token_hash'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('invitations', ['invite_code_hash'], unique: true))->not->toBeNull();
});

test('onboarding retry invariants have database uniqueness guards', function (): void {
    expect(databaseAuditIndex('restaurant_onboardings', ['user_id'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('restaurant_onboardings', ['organization_id'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('restaurant_onboardings', ['brand_id'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('restaurant_onboardings', ['branch_id'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('restaurant_onboardings', ['area_node_id'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('restaurant_onboardings', ['menu_id'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('restaurant_onboardings', ['menu_category_id'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('restaurant_onboardings', ['menu_item_id'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('restaurant_onboarding_service_points', ['service_point_id'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('restaurant_onboarding_service_points', ['restaurant_onboarding_id', 'position'], unique: true))->not->toBeNull()
        ->and(databaseAuditIndex('qr_codes', ['active_service_point_id'], unique: true))->not->toBeNull();
});

test('draft item retries have a database uniqueness guard', function (): void {
    expect(databaseAuditIndex(
        'draft_order_items',
        ['draft_order_id', 'idempotency_key'],
        unique: true,
    ))->not->toBeNull();
});

test('restaurant hierarchy relationships preserve their tenant chain', function (): void {
    $organization = Organization::factory()->create();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->forBrand($brand)->create();
    $areaNode = AreaNode::factory()->forBranch($branch)->create();
    $servicePoint = ServicePoint::factory()->inAreaNode($areaNode)->create();

    $loadedOrganization = Organization::query()
        ->with('brands.branches.areaNodes.servicePoints')
        ->findOrFail($organization->id);
    $loadedBrand = $loadedOrganization->brands->sole();
    $loadedBranch = $loadedBrand->branches->sole();
    $loadedAreaNode = $loadedBranch->areaNodes->sole();

    expect($loadedBrand->organization_id)->toBe($organization->id)
        ->and($loadedBranch->organization_id)->toBe($organization->id)
        ->and($loadedBranch->brand_id)->toBe($brand->id)
        ->and($loadedAreaNode->branch_id)->toBe($branch->id)
        ->and($loadedAreaNode->servicePoints->sole()->is($servicePoint))->toBeTrue();
});

test('every model foreign key exposes a belongs to relationship', function (): void {
    $violations = [];

    foreach (databaseAuditModelClasses() as $modelClass) {
        $model = new $modelClass;
        $foreignColumns = collect(Schema::getForeignKeys($model->getTable()))
            ->flatMap(fn (array $foreignKey): array => $foreignKey['columns'])
            ->all();
        $relationshipColumns = [];
        $reflection = new ReflectionClass($modelClass);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $returnType = $method->getReturnType();

            if ($method->getDeclaringClass()->getName() !== $modelClass
                || $method->getNumberOfRequiredParameters() > 0
                || ! $returnType instanceof ReflectionNamedType
                || $returnType->getName() !== BelongsTo::class) {
                continue;
            }

            $relationshipColumns[] = $model->{$method->getName()}()->getForeignKeyName();
        }

        foreach (array_diff($foreignColumns, $relationshipColumns) as $foreignColumn) {
            $violations[] = $modelClass.'.'.$foreignColumn;
        }
    }

    expect($violations)->toBeEmpty();
});

test('nullable foreign key actions and model casts match the schema', function (): void {
    $violations = [];

    foreach (databaseAuditModelClasses() as $modelClass) {
        $model = new $modelClass;
        $columns = collect(Schema::getColumns($model->getTable()))->keyBy('name');

        foreach (Schema::getForeignKeys($model->getTable()) as $foreignKey) {
            if ($foreignKey['on_delete'] !== 'set null') {
                continue;
            }

            foreach ($foreignKey['columns'] as $column) {
                if (! $columns->get($column)['nullable']) {
                    $violations[] = $modelClass.'.'.$column.' must be nullable for SET NULL';
                }
            }
        }

        foreach ($columns as $column) {
            $columnName = (string) $column['name'];

            if (str_starts_with((string) $column['type'], 'tinyint')
                && ! $model->hasCast($columnName, ['bool', 'boolean'])) {
                $violations[] = $modelClass.'.'.$columnName.' is missing a boolean cast';
            }

            if ($column['type'] === 'datetime'
                && ! in_array($columnName, ['created_at', 'updated_at', 'deleted_at'], true)
                && ! $model->hasCast($columnName, ['date', 'datetime', 'immutable_date', 'immutable_datetime'])) {
                $violations[] = $modelClass.'.'.$columnName.' is missing a date cast';
            }
        }

        if ($columns->has('deleted_at') && ! in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $violations[] = $modelClass.' is missing SoftDeletes';
        }
    }

    expect($violations)->toBeEmpty();
});

test('branch tenant identity cannot disagree with its brand', function (): void {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();
    $brand = Brand::factory()->for($firstOrganization)->create();

    expect(fn () => Branch::factory()
        ->for($secondOrganization)
        ->for($brand)
        ->create())
        ->toThrow(QueryException::class);
});

test('area node creation rejects a parent from another branch', function (): void {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $otherParent = AreaNode::factory()->forBranch($otherBranch)->create();

    expect(fn () => app(CreateAreaNodeAction::class)->handle($branch, [
        'parent_id' => $otherParent->id,
        'type' => AreaNodeType::Hall->value,
        'name' => 'Cross-tenant hall',
        'icon' => null,
        'sort_order' => 10,
        'is_active' => true,
    ]))->toThrow(InvalidArgumentException::class, 'errors.domain.selected_parent_area_unavailable');
});

test('guest opener relation is constrained and nulls safely when the guest is deleted', function (): void {
    $tableSession = TableSession::factory()->create();
    $guest = TableSessionGuest::factory()->for($tableSession)->create();

    $tableSession->forceFill(['opened_by_guest_id' => $guest->id])->saveOrFail();
    $guest->delete();

    expect($tableSession->fresh()->opened_by_guest_id)->toBeNull();
});

test('legacy plaintext invitation credential columns are absent', function (): void {
    expect(Schema::hasColumn('invitations', 'invite_token'))->toBeFalse()
        ->and(Schema::hasColumn('invitations', 'invite_code'))->toBeFalse()
        ->and(Schema::hasColumn('table_sessions', 'guest_invite_token'))->toBeFalse();
});

/**
 * @param  list<string>  $columns
 * @return array{name: string, columns: list<string>, type: string|null, unique: bool, primary: bool}|null
 */
function databaseAuditIndex(string $table, array $columns, bool $unique = false): ?array
{
    return collect(Schema::getIndexes($table))
        ->first(fn (array $index): bool => $index['columns'] === $columns && (! $unique || $index['unique']));
}

/** @return list<class-string<Model>> */
function databaseAuditModelClasses(): array
{
    $models = [];

    foreach (File::allFiles(app_path('Models')) as $file) {
        $relativePath = str_replace(
            [app_path('Models').DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, '.php'],
            ['', '\\', ''],
            $file->getPathname(),
        );
        $modelClass = 'App\\Models\\'.$relativePath;

        if (class_exists($modelClass) && is_subclass_of($modelClass, Model::class)) {
            $models[] = $modelClass;
        }
    }

    sort($models);

    return $models;
}
