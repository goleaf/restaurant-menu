<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\MenuOperation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

function dishReceiptMigration(): Migration
{
    return require database_path('migrations/2026_09_17_064628_allow_branch_scoped_menu_operation_receipts.php');
}

test('nullable branch receipts preserve menu foreign keys and request uniqueness', function (): void {
    $foreignKeys = Schema::getForeignKeys('menu_operations');
    expect(collect($foreignKeys)->contains(fn (array $key): bool => $key['columns'] === ['menu_id'] && $key['foreign_table'] === 'menus'))->toBeTrue();
    $receipt = MenuOperation::factory()->completed()->create();
    expect(fn () => MenuOperation::factory()->completed()->create(['request_id' => $receipt->request_id]))->toThrow(QueryException::class);
    expect(fn () => MenuOperation::factory()->completed()->create(['menu_id' => 999999, 'branch_id' => $receipt->branch_id]))->toThrow(QueryException::class);
});

test('receipt migration can reverse empty storage and refuses to lose branch receipts', function (): void {
    $migration = dishReceiptMigration();
    $migration->down();
    $column = collect(Schema::getColumns('menu_operations'))->firstWhere('name', 'menu_id');
    expect($column['nullable'])->toBeFalse();
    $migration->up();
    $branch = Branch::factory()->create();
    $receipt = MenuOperation::factory()->completed()->create(['menu_id' => null, 'branch_id' => $branch->id, 'target_id' => $branch->id]);
    expect(fn () => $migration->down())->toThrow(LogicException::class);
    expect($receipt->fresh()->menu_id)->toBeNull()
        ->and(collect(Schema::getColumns('menu_operations'))->firstWhere('name', 'menu_id')['nullable'])->toBeTrue();
});
