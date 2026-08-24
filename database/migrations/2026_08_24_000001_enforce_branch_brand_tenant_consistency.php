<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->unique(['organization_id', 'id'], 'brands_organization_id_id_unique');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->index(['organization_id', 'brand_id'], 'branches_organization_id_brand_id_index');
            $table->foreign(
                ['organization_id', 'brand_id'],
                'branches_organization_id_brand_id_foreign',
            )->references(['organization_id', 'id'])->on('brands')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropForeign(['organization_id', 'brand_id']);
            $table->dropIndex('branches_organization_id_brand_id_index');
        });

        Schema::table('brands', function (Blueprint $table): void {
            $table->dropUnique('brands_organization_id_id_unique');
        });
    }
};
