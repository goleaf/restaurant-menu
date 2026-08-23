<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillMoneyColumns();
        $this->migrateSnapshotMoneyKeys(toCents: true);
    }

    public function down(): void
    {
        $this->migrateSnapshotMoneyKeys(toCents: false);

        foreach ($this->moneyColumnMap() as $table => $columns) {
            $this->query($table)->update(array_fill_keys(array_values($columns), null));
        }
    }

    private function backfillMoneyColumns(): void
    {
        foreach ($this->moneyColumnMap() as $table => $columns) {
            $this->query($table)
                ->select(Schema::getColumnListing($table))
                ->chunkById(500, function (Collection $rows) use ($columns, $table): void {
                    $updates = $rows
                        ->map(function (Model $row) use ($columns): array {
                            $update = $row->getAttributes();

                            foreach ($columns as $decimalColumn => $centsColumn) {
                                $update[$centsColumn] = self::decimalToCents($row->getAttribute($decimalColumn));
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

    private function migrateSnapshotMoneyKeys(bool $toCents): void
    {
        $jsonColumns = [
            'draft_order_items' => ['selected_modifiers'],
            'order_items' => ['selected_modifiers', 'modifiers_snapshot'],
            'kitchen_ticket_items' => ['selected_modifiers'],
            'manual_payments' => ['metadata'],
        ];

        foreach ($jsonColumns as $table => $columns) {
            $this->query($table)
                ->select(Schema::getColumnListing($table))
                ->chunkById(500, function (Collection $rows) use ($columns, $table, $toCents): void {
                    $updates = $rows
                        ->map(function (Model $row) use ($columns, $toCents): array {
                            $update = $row->getAttributes();

                            foreach ($columns as $column) {
                                $update[$column] = json_encode(
                                    self::transformMoneyKeys(
                                        self::decodeJson($row->getAttribute($column)),
                                        $toCents,
                                    ),
                                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                                );
                            }

                            return $update;
                        })
                        ->all();

                    if ($updates !== []) {
                        $this->query($table)->upsert($updates, ['id'], $columns);
                    }
                });
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function moneyColumnMap(): array
    {
        return [
            'menu_items' => ['price' => 'price_cents'],
            'modifier_options' => ['price_delta' => 'price_delta_cents'],
            'draft_order_items' => [
                'unit_price' => 'unit_price_cents',
                'modifier_total' => 'modifier_total_cents',
                'total_price' => 'total_price_cents',
            ],
            'orders' => ['total_price' => 'total_price_cents'],
            'order_items' => [
                'unit_price' => 'unit_price_cents',
                'unit_price_snapshot' => 'unit_price_snapshot_cents',
                'modifier_total' => 'modifier_total_cents',
                'total_price' => 'total_price_cents',
            ],
            'branch_settings' => ['service_charge_percent' => 'service_charge_basis_points'],
            'manual_payments' => [
                'covered_subtotal_amount' => 'covered_subtotal_cents',
                'service_charge_percent' => 'service_charge_basis_points',
                'service_charge_amount' => 'service_charge_cents',
                'tips_amount' => 'tips_cents',
                'amount' => 'amount_cents',
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

    private static function decimalToCents(mixed $amount): int
    {
        $normalized = trim((string) ($amount ?? '0'));

        if (preg_match('/^[+-]?\d+(?:[.,]\d{1,2})?$/', $normalized) !== 1) {
            throw new InvalidArgumentException('A persisted money value is not a canonical decimal amount.');
        }

        $normalized = str_replace(',', '.', $normalized);
        $negative = str_starts_with($normalized, '-');
        $unsigned = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    private static function centsToDecimal(mixed $cents): string
    {
        $amount = (int) $cents;
        $negative = $amount < 0;
        $absolute = abs($amount);
        $decimal = intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$decimal : $decimal;
    }

    /**
     * @return array<mixed>
     */
    private static function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function transformMoneyKeys(array $value, bool $toCents): array
    {
        $transformed = [];
        $keyMap = self::snapshotKeyMap();

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $item = self::transformMoneyKeys($item, $toCents);
            }

            $targetKey = is_string($key) ? ($keyMap[$key] ?? null) : null;

            if ($toCents && is_string($targetKey)) {
                $transformed[$targetKey] = self::decimalToCents($item);

                continue;
            }

            $legacyKey = is_string($key) ? array_search($key, $keyMap, true) : false;

            if (! $toCents && is_string($legacyKey)) {
                $transformed[$legacyKey] = self::centsToDecimal($item);

                continue;
            }

            $transformed[$key] = $item;
        }

        return $transformed;
    }

    /**
     * @return array<string, string>
     */
    private static function snapshotKeyMap(): array
    {
        return [
            'price_delta' => 'price_delta_cents',
            'confirmed_total' => 'confirmed_total_cents',
            'covered_subtotal_amount' => 'covered_subtotal_cents',
            'service_charge_percent' => 'service_charge_basis_points',
            'service_charge_amount' => 'service_charge_cents',
            'tips_amount' => 'tips_cents',
            'total_amount' => 'total_cents',
        ];
    }
};
