<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('content_version')->default(0);
            $table->unsignedBigInteger('media_version')->default(0);
            $table->unsignedBigInteger('variants_version')->default(0);
            $table->unsignedBigInteger('modifier_links_version')->default(0);
        });
        Schema::table('modifier_groups', function (Blueprint $table): void {
            $table->unsignedBigInteger('content_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table): void {
            $table->dropColumn('content_version');
        });
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn(['content_version', 'media_version', 'variants_version', 'modifier_links_version']);
        });
    }
};
