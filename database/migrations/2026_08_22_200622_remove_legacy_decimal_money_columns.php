<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            $this->migrateToCentsSchema();
        });
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            $this->restoreLegacySchema();
        });
    }

    private function migrateToCentsSchema(): void
    {
        $this->ensureBackfillIsComplete();

        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedBigInteger('price_cents')->default(0)->nullable(false)->change();
            $table->dropColumn('price');
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->bigInteger('price_delta_cents')->default(0)->nullable(false)->change();
            $table->dropColumn('price_delta');
        });

        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_price_cents')->default(0)->nullable(false)->change();
            $table->bigInteger('modifier_total_cents')->default(0)->nullable(false)->change();
            $table->unsignedBigInteger('total_price_cents')->default(0)->nullable(false)->change();
            $table->dropColumn(['unit_price', 'modifier_total', 'total_price']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('total_price_cents')->default(0)->nullable(false)->change();
            $table->dropColumn('total_price');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_price_cents')->default(0)->nullable(false)->change();
            $table->unsignedBigInteger('unit_price_snapshot_cents')->default(0)->nullable(false)->change();
            $table->bigInteger('modifier_total_cents')->default(0)->nullable(false)->change();
            $table->unsignedBigInteger('total_price_cents')->default(0)->nullable(false)->change();
            $table->dropColumn(['unit_price', 'unit_price_snapshot', 'modifier_total', 'total_price']);
        });

        Schema::table('branch_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('service_charge_basis_points')->default(0)->nullable(false)->change();
            $table->dropColumn('service_charge_percent');
        });

        Schema::table('manual_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('covered_subtotal_cents')->default(0)->nullable(false)->change();
            $table->unsignedSmallInteger('service_charge_basis_points')->default(0)->nullable(false)->change();
            $table->unsignedBigInteger('service_charge_cents')->default(0)->nullable(false)->change();
            $table->unsignedBigInteger('tips_cents')->default(0)->nullable(false)->change();
            $table->unsignedBigInteger('amount_cents')->default(0)->nullable(false)->change();
            $table->dropColumn([
                'covered_subtotal_amount',
                'service_charge_percent',
                'service_charge_amount',
                'tips_amount',
                'amount',
            ]);
        });
    }

    private function restoreLegacySchema(): void
    {
        $this->addLegacyColumns();
        $this->restoreLegacyValues();
        $this->makeLegacyColumnsRequired();
        $this->makeCentsColumnsNullable();
    }

    private function addLegacyColumns(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable();
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->decimal('price_delta', 10, 2)->nullable();
        });

        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('modifier_total', 10, 2)->nullable();
            $table->decimal('total_price', 10, 2)->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_price', 10, 2)->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('unit_price_snapshot', 10, 2)->nullable();
            $table->decimal('modifier_total', 10, 2)->nullable();
            $table->decimal('total_price', 10, 2)->nullable();
        });

        Schema::table('branch_settings', function (Blueprint $table) {
            $table->decimal('service_charge_percent', 5, 2)->nullable();
        });

        Schema::table('manual_payments', function (Blueprint $table) {
            $table->decimal('covered_subtotal_amount', 10, 2)->nullable();
            $table->decimal('service_charge_percent', 5, 2)->nullable();
            $table->decimal('service_charge_amount', 10, 2)->nullable();
            $table->decimal('tips_amount', 10, 2)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
        });
    }

    private function restoreLegacyValues(): void
    {
        foreach ($this->legacyColumnMap() as $table => $columns) {
            $this->query($table)
                ->select(Schema::getColumnListing($table))
                ->chunkById(500, function (Collection $rows) use ($columns, $table): void {
                    $updates = $rows
                        ->map(function (Model $row) use ($columns): array {
                            $update = $row->getAttributes();

                            foreach ($columns as $centsColumn => $decimalColumn) {
                                $update[$decimalColumn] = self::centsToDecimal($row->getAttribute($centsColumn));
                            }

                            return $update;
                        })
                        ->all();

                    if ($updates !== []) {
                        $this->query($table)->upsert($updates, ['id'], array_values($columns));
                    }
                });
        }
    }

    private function makeLegacyColumnsRequired(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->nullable(false)->change();
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->decimal('price_delta', 10, 2)->default(0)->nullable(false)->change();
        });

        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->default(0)->nullable(false)->change();
            $table->decimal('modifier_total', 10, 2)->default(0)->nullable(false)->change();
            $table->decimal('total_price', 10, 2)->default(0)->nullable(false)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_price', 10, 2)->default(0)->nullable(false)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->default(0)->nullable(false)->change();
            $table->decimal('unit_price_snapshot', 10, 2)->default(0)->nullable(false)->change();
            $table->decimal('modifier_total', 10, 2)->default(0)->nullable(false)->change();
            $table->decimal('total_price', 10, 2)->default(0)->nullable(false)->change();
        });

        Schema::table('branch_settings', function (Blueprint $table) {
            $table->decimal('service_charge_percent', 5, 2)->default(0)->nullable(false)->change();
        });

        Schema::table('manual_payments', function (Blueprint $table) {
            $table->decimal('covered_subtotal_amount', 10, 2)->default(0)->nullable(false)->change();
            $table->decimal('service_charge_percent', 5, 2)->default(0)->nullable(false)->change();
            $table->decimal('service_charge_amount', 10, 2)->default(0)->nullable(false)->change();
            $table->decimal('tips_amount', 10, 2)->default(0)->nullable(false)->change();
            $table->decimal('amount', 10, 2)->default(0)->nullable(false)->change();
        });
    }

    private function makeCentsColumnsNullable(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedBigInteger('price_cents')->nullable()->change();
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->bigInteger('price_delta_cents')->nullable()->change();
        });

        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_price_cents')->nullable()->change();
            $table->bigInteger('modifier_total_cents')->nullable()->change();
            $table->unsignedBigInteger('total_price_cents')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('total_price_cents')->nullable()->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_price_cents')->nullable()->change();
            $table->unsignedBigInteger('unit_price_snapshot_cents')->nullable()->change();
            $table->bigInteger('modifier_total_cents')->nullable()->change();
            $table->unsignedBigInteger('total_price_cents')->nullable()->change();
        });

        Schema::table('branch_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('service_charge_basis_points')->nullable()->change();
        });

        Schema::table('manual_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('covered_subtotal_cents')->nullable()->change();
            $table->unsignedSmallInteger('service_charge_basis_points')->nullable()->change();
            $table->unsignedBigInteger('service_charge_cents')->nullable()->change();
            $table->unsignedBigInteger('tips_cents')->nullable()->change();
            $table->unsignedBigInteger('amount_cents')->nullable()->change();
        });
    }

    private function ensureBackfillIsComplete(): void
    {
        foreach ($this->requiredColumns() as $table => $columns) {
            foreach ($columns as $column) {
                if ($this->query($table)->whereNull($column)->exists()) {
                    throw new RuntimeException("Money backfill is incomplete for {$table}.{$column}.");
                }
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function requiredColumns(): array
    {
        return [
            'menu_items' => ['price_cents'],
            'modifier_options' => ['price_delta_cents'],
            'draft_order_items' => ['unit_price_cents', 'modifier_total_cents', 'total_price_cents'],
            'orders' => ['total_price_cents'],
            'order_items' => ['unit_price_cents', 'unit_price_snapshot_cents', 'modifier_total_cents', 'total_price_cents'],
            'branch_settings' => ['service_charge_basis_points'],
            'manual_payments' => ['covered_subtotal_cents', 'service_charge_basis_points', 'service_charge_cents', 'tips_cents', 'amount_cents'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function legacyColumnMap(): array
    {
        return [
            'menu_items' => ['price_cents' => 'price'],
            'modifier_options' => ['price_delta_cents' => 'price_delta'],
            'draft_order_items' => [
                'unit_price_cents' => 'unit_price',
                'modifier_total_cents' => 'modifier_total',
                'total_price_cents' => 'total_price',
            ],
            'orders' => ['total_price_cents' => 'total_price'],
            'order_items' => [
                'unit_price_cents' => 'unit_price',
                'unit_price_snapshot_cents' => 'unit_price_snapshot',
                'modifier_total_cents' => 'modifier_total',
                'total_price_cents' => 'total_price',
            ],
            'branch_settings' => ['service_charge_basis_points' => 'service_charge_percent'],
            'manual_payments' => [
                'covered_subtotal_cents' => 'covered_subtotal_amount',
                'service_charge_basis_points' => 'service_charge_percent',
                'service_charge_cents' => 'service_charge_amount',
                'tips_cents' => 'tips_amount',
                'amount_cents' => 'amount',
            ],
        ];
    }

    /**
     * @return Builder<covariant Model>
     */
    private function query(string $table): Builder
    {
        $model = new class extends Model
        {
            public $timestamps = false;
        };

        $model->setTable($table);

        return $model->newQuery();
    }

    private static function centsToDecimal(mixed $cents): string
    {
        $amount = (int) $cents;
        $negative = $amount < 0;
        $absolute = abs($amount);
        $decimal = intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$decimal : $decimal;
    }
};
