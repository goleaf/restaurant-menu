<?php

declare(strict_types=1);

use App\Models\BranchSetting;
use App\Models\DraftOrderItem;
use App\Models\ManualPayment;
use App\Models\MenuItem;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\MoneyFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

test('all persisted monetary values use explicitly named integer cents columns', function (): void {
    $columns = [
        'menu_items' => ['price_cents'],
        'modifier_options' => ['price_delta_cents'],
        'draft_order_items' => ['unit_price_cents', 'modifier_total_cents', 'total_price_cents'],
        'orders' => ['total_price_cents'],
        'order_items' => ['unit_price_cents', 'unit_price_snapshot_cents', 'modifier_total_cents', 'total_price_cents'],
        'manual_payments' => ['covered_subtotal_cents', 'service_charge_basis_points', 'service_charge_cents', 'tips_cents', 'amount_cents'],
        'branch_settings' => ['service_charge_basis_points'],
    ];

    $legacyColumns = [
        'menu_items' => ['price'],
        'modifier_options' => ['price_delta'],
        'draft_order_items' => ['unit_price', 'modifier_total', 'total_price'],
        'orders' => ['total_price'],
        'order_items' => ['unit_price', 'unit_price_snapshot', 'modifier_total', 'total_price'],
        'manual_payments' => ['covered_subtotal_amount', 'service_charge_percent', 'service_charge_amount', 'tips_amount', 'amount'],
        'branch_settings' => ['service_charge_percent'],
    ];

    foreach ($columns as $table => $moneyColumns) {
        expect(Schema::hasColumns($table, $moneyColumns))->toBeTrue();

        foreach ($moneyColumns as $column) {
            expect(Schema::getColumnType($table, $column))->toBe('integer');
        }

        expect(Schema::hasColumns($table, $legacyColumns[$table]))->toBeFalse();
    }
});

test('eloquent exposes persisted money as integers', function (): void {
    $menuItem = MenuItem::factory()->create(['price_cents' => 1450]);
    $modifierOption = ModifierOption::factory()->create(['price_delta_cents' => -125]);
    $draftItem = DraftOrderItem::factory()->create([
        'unit_price_cents' => 1450,
        'modifier_total_cents' => -125,
        'total_price_cents' => 1325,
    ]);
    $order = Order::factory()->create(['total_price_cents' => 1325]);
    $orderItem = OrderItem::factory()->for($order)->create([
        'unit_price_cents' => 1450,
        'unit_price_snapshot_cents' => 1450,
        'modifier_total_cents' => -125,
        'total_price_cents' => 1325,
    ]);
    $payment = ManualPayment::factory()->create([
        'covered_subtotal_cents' => 1325,
        'service_charge_basis_points' => 1000,
        'service_charge_cents' => 133,
        'tips_cents' => 200,
        'amount_cents' => 1658,
    ]);

    expect($menuItem->price_cents)->toBe(1450)
        ->and($modifierOption->price_delta_cents)->toBe(-125)
        ->and($draftItem->unit_price_cents)->toBe(1450)
        ->and($draftItem->modifier_total_cents)->toBe(-125)
        ->and($draftItem->total_price_cents)->toBe(1325)
        ->and($order->total_price_cents)->toBe(1325)
        ->and($orderItem->unit_price_cents)->toBe(1450)
        ->and($orderItem->unit_price_snapshot_cents)->toBe(1450)
        ->and($orderItem->modifier_total_cents)->toBe(-125)
        ->and($orderItem->total_price_cents)->toBe(1325)
        ->and($payment->covered_subtotal_cents)->toBe(1325)
        ->and($payment->service_charge_basis_points)->toBe(1000)
        ->and($payment->service_charge_cents)->toBe(133)
        ->and($payment->tips_cents)->toBe(200)
        ->and($payment->amount_cents)->toBe(1658);
});

test('decimal input and percentage calculations are exact without floating point', function (): void {
    expect(MoneyFormatter::decimalToCents('0.29'))->toBe(29)
        ->and(MoneyFormatter::decimalToCents('999999.99'))->toBe(99999999)
        ->and(MoneyFormatter::decimalToCents('-1.25'))->toBe(-125)
        ->and(MoneyFormatter::decimalToBasisPoints('12.50'))->toBe(1250)
        ->and(MoneyFormatter::centsToDecimal(-125))->toBe('-1.25')
        ->and(MoneyFormatter::percentageOf(1999, 1250))->toBe(250)
        ->and(MoneyFormatter::percentageOf(1, 5000))->toBe(1)
        ->and(MoneyFormatter::formatCents(1450, 'EUR'))->toBe('€14.50')
        ->and(MoneyFormatter::formatSignedCents(-125, 'USD'))->toBe('-$1.25')
        ->and(fn (): string => MoneyFormatter::centsToDecimal(PHP_INT_MIN))->toThrow(OverflowException::class)
        ->and(fn (): int => MoneyFormatter::roundedDivide(PHP_INT_MAX, 2))->toThrow(OverflowException::class);
});

test('money implementation and blade presentation do not use floating point', function (): void {
    $moneyTerms = '/(?:price|amount|subtotal|total|money|cent|tip|service.?charge)/i';
    $floatOperations = '/(?:\(float\)|\bfloat\b|randomFloat\s*\(|number_format\s*\(|\bround\s*\()/i';

    $matchingPaths = collect([
        app_path(),
        database_path('factories'),
        database_path('seeders'),
        resource_path('views'),
    ])
        ->flatMap(fn (string $path) => File::allFiles($path))
        ->filter(fn (SplFileInfo $file): bool => in_array($file->getExtension(), ['php', 'blade.php'], true))
        ->mapWithKeys(function (SplFileInfo $file) use ($floatOperations, $moneyTerms): array {
            $matchingLines = collect(preg_split('/\R/', File::get($file->getPathname())) ?: [])
                ->filter(fn (string $line): bool => preg_match($moneyTerms, $line) === 1
                    && preg_match($floatOperations, $line) === 1)
                ->values()
                ->all();

            return $matchingLines === []
                ? []
                : [str_replace(base_path().'/', '', $file->getPathname()) => $matchingLines];
        });

    expect($matchingPaths->all())->toBe([]);
});

test('legacy decimal money upgrades to cents without data loss and rolls back safely', function (): void {
    $menuItem = MenuItem::factory()->create(['price_cents' => 1]);
    $modifierOption = ModifierOption::factory()->create(['price_delta_cents' => 0]);
    $draftItem = DraftOrderItem::factory()->create([
        'unit_price_cents' => 1,
        'modifier_total_cents' => 0,
        'total_price_cents' => 1,
        'selected_modifiers' => [['price_delta_cents' => 0]],
    ]);
    $order = Order::factory()->create(['total_price_cents' => 1]);
    $orderItem = OrderItem::factory()->for($order)->create([
        'unit_price_cents' => 1,
        'unit_price_snapshot_cents' => 1,
        'modifier_total_cents' => 0,
        'total_price_cents' => 1,
        'selected_modifiers' => [['price_delta_cents' => 0]],
        'modifiers_snapshot' => [['price_delta_cents' => 0]],
    ]);
    $payment = ManualPayment::factory()->create([
        'covered_subtotal_cents' => 1,
        'service_charge_basis_points' => 0,
        'service_charge_cents' => 0,
        'tips_cents' => 0,
        'amount_cents' => 1,
        'metadata' => ['bill_snapshot' => ['confirmed_total_cents' => 1]],
    ]);
    $branchSetting = BranchSetting::factory()->for($payment->branch)->create();

    $legacyRow = function (array $attributes, array $centsColumns, array $legacyValues): array {
        return [
            ...array_diff_key($attributes, array_flip($centsColumns)),
            ...$legacyValues,
        ];
    };

    $legacyRows = [
        'menu_items' => $legacyRow($menuItem->getAttributes(), ['price_cents'], ['price' => '0.29']),
        'modifier_options' => $legacyRow($modifierOption->getAttributes(), ['price_delta_cents'], ['price_delta' => '-1.25']),
        'draft_order_items' => $legacyRow(
            $draftItem->getAttributes(),
            ['unit_price_cents', 'modifier_total_cents', 'total_price_cents'],
            [
                'unit_price' => '10.01',
                'modifier_total' => '-0.01',
                'total_price' => '20.00',
                'selected_modifiers' => json_encode([['price_delta' => '-0.01']], JSON_THROW_ON_ERROR),
            ],
        ),
        'orders' => $legacyRow($order->getAttributes(), ['total_price_cents'], ['total_price' => '20.00']),
        'order_items' => $legacyRow(
            $orderItem->getAttributes(),
            ['unit_price_cents', 'unit_price_snapshot_cents', 'modifier_total_cents', 'total_price_cents'],
            [
                'unit_price' => '10.01',
                'unit_price_snapshot' => '10.01',
                'modifier_total' => '-0.01',
                'total_price' => '20.00',
                'selected_modifiers' => json_encode([['price_delta' => '-0.01']], JSON_THROW_ON_ERROR),
                'modifiers_snapshot' => json_encode([['price_delta' => '-0.01']], JSON_THROW_ON_ERROR),
            ],
        ),
        'branch_settings' => $legacyRow(
            $branchSetting->getAttributes(),
            ['service_charge_basis_points'],
            ['service_charge_percent' => '12.50'],
        ),
        'manual_payments' => $legacyRow(
            $payment->getAttributes(),
            ['covered_subtotal_cents', 'service_charge_basis_points', 'service_charge_cents', 'tips_cents', 'amount_cents'],
            [
                'covered_subtotal_amount' => '20.00',
                'service_charge_percent' => '12.50',
                'service_charge_amount' => '2.50',
                'tips_amount' => '0.29',
                'amount' => '22.79',
                'metadata' => json_encode(['bill_snapshot' => [
                    'confirmed_total' => '20.00',
                    'service_charge_amount' => '2.50',
                    'tips_amount' => '0.29',
                    'total_amount' => '22.79',
                ]], JSON_THROW_ON_ERROR),
            ],
        ),
    ];

    $addCentsColumns = require database_path('migrations/2026_08_22_200620_add_integer_cents_columns_to_money_tables.php');
    $backfillCents = require database_path('migrations/2026_08_22_200621_backfill_integer_cents_money_columns.php');
    $removeLegacyColumns = require database_path('migrations/2026_08_22_200622_remove_legacy_decimal_money_columns.php');

    $connection = DB::connection();

    expect(app()->environment('testing'))->toBeTrue()
        ->and($connection->getDatabaseName())->toBe(':memory:');

    $connection->commit();

    try {
        $removeLegacyColumns->down();
        $backfillCents->down();
        $addCentsColumns->down();

        expect(Schema::hasColumn('menu_items', 'price'))->toBeTrue()
            ->and(Schema::hasColumn('menu_items', 'price_cents'))->toBeFalse();

        $upsertLegacyRow = function (string $table, array $row): void {
            $model = new class extends Model
            {
                public $timestamps = false;
            };
            $model->setTable($table);
            $model->newQuery()->upsert([$row], ['id'], array_keys($row));
        };

        $upsertLegacyRow('menu_items', $legacyRows['menu_items']);
        $upsertLegacyRow('modifier_options', $legacyRows['modifier_options']);
        $upsertLegacyRow('draft_order_items', $legacyRows['draft_order_items']);
        $upsertLegacyRow('orders', $legacyRows['orders']);
        $upsertLegacyRow('order_items', $legacyRows['order_items']);
        $upsertLegacyRow('branch_settings', $legacyRows['branch_settings']);
        $upsertLegacyRow('manual_payments', $legacyRows['manual_payments']);

        $addCentsColumns->up();
        $backfillCents->up();
        $removeLegacyColumns->up();

        expect($menuItem->refresh()->price_cents)->toBe(29)
            ->and($modifierOption->refresh()->price_delta_cents)->toBe(-125)
            ->and($draftItem->refresh()->unit_price_cents)->toBe(1001)
            ->and($draftItem->modifier_total_cents)->toBe(-1)
            ->and($draftItem->total_price_cents)->toBe(2000)
            ->and($draftItem->selected_modifiers[0]['price_delta_cents'])->toBe(-1)
            ->and($order->refresh()->total_price_cents)->toBe(2000)
            ->and($orderItem->refresh()->unit_price_snapshot_cents)->toBe(1001)
            ->and($orderItem->modifiers_snapshot[0]['price_delta_cents'])->toBe(-1)
            ->and($payment->refresh()->covered_subtotal_cents)->toBe(2000)
            ->and($payment->service_charge_basis_points)->toBe(1250)
            ->and($payment->service_charge_cents)->toBe(250)
            ->and($payment->tips_cents)->toBe(29)
            ->and($payment->amount_cents)->toBe(2279)
            ->and($payment->metadata['bill_snapshot']['total_cents'])->toBe(2279);
    } finally {
        $exitCode = Artisan::call('migrate:fresh', ['--force' => true]);

        if ($exitCode !== 0) {
            throw new RuntimeException(Artisan::output());
        }

        $this->updateLocalCacheOfInMemoryDatabases();
        $connection->beginTransaction();
    }
});
