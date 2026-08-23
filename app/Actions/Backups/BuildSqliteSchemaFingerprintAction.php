<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use Illuminate\Database\DatabaseManager;
use JsonException;

final class BuildSqliteSchemaFingerprintAction
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    /**
     * @throws JsonException
     */
    public function handle(string $connectionName): string
    {
        $schema = $this->database->connection($connectionName)->getSchemaBuilder();
        $tableNames = collect($schema->getTables())
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->sort()
            ->values();

        $tables = $tableNames
            ->map(function (string $table) use ($schema): array {
                return [
                    'name' => $table,
                    'columns' => collect($schema->getColumns($table))
                        ->map(fn (array $column): array => $this->only($column, [
                            'name',
                            'type',
                            'type_name',
                            'collation',
                            'nullable',
                            'default',
                            'auto_increment',
                            'generation',
                        ]))
                        ->sortBy('name')
                        ->values()
                        ->all(),
                    'indexes' => collect($schema->getIndexes($table))
                        ->map(fn (array $index): array => $this->only($index, [
                            'name',
                            'columns',
                            'type',
                            'unique',
                            'primary',
                        ]))
                        ->sortBy(fn (array $index): string => json_encode($index, JSON_THROW_ON_ERROR))
                        ->values()
                        ->all(),
                    'foreign_keys' => collect($schema->getForeignKeys($table))
                        ->map(fn (array $foreignKey): array => $this->only($foreignKey, [
                            'name',
                            'columns',
                            'foreign_schema',
                            'foreign_table',
                            'foreign_columns',
                            'on_update',
                            'on_delete',
                        ]))
                        ->sortBy(fn (array $foreignKey): string => json_encode($foreignKey, JSON_THROW_ON_ERROR))
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        $views = collect($schema->getViews())
            ->map(fn (array $view): array => $this->only($view, ['name', 'definition']))
            ->sortBy('name')
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'tables' => $tables,
            'views' => $views,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function only(array $values, array $keys): array
    {
        $selected = [];

        foreach ($keys as $key) {
            $selected[$key] = $values[$key] ?? null;
        }

        return $selected;
    }
}
