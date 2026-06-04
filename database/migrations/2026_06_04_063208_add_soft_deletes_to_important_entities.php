<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tablesMissingDeletedAt() as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->softDeletes();
                $table->index('deleted_at', $this->deletedAtIndexName($tableName));
            });
        }

        foreach ($this->tablesAlreadyUsingDeletedAt() as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->index('deleted_at', $this->deletedAtIndexName($tableName));
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tablesAlreadyUsingDeletedAt() as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($this->deletedAtIndexName($tableName));
            });
        }

        foreach (array_reverse($this->tablesMissingDeletedAt()) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($this->deletedAtIndexName($tableName));
                $table->dropSoftDeletes();
            });
        }
    }

    /**
     * @return list<string>
     */
    private function tablesMissingDeletedAt(): array
    {
        return [
            'organizations',
            'brands',
            'branches',
            'menus',
            'menu_categories',
            'menu_items',
        ];
    }

    /**
     * @return list<string>
     */
    private function tablesAlreadyUsingDeletedAt(): array
    {
        return [
            'area_nodes',
            'service_points',
        ];
    }

    private function deletedAtIndexName(string $tableName): string
    {
        return $tableName.'_deleted_at_index';
    }
};
